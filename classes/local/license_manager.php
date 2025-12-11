<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Handles license status and limits based on signed backend responses.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\local;

use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();


class license_manager {
    private const PROTOKEN_GRACE = 30; // Seconds of slack before token expiry.
    private const RESPONSE_MAX_AGE = 3600; // Reject responses older than 1h.

    /** @var api_client|null */
    private $client;

    public function __construct(?api_client $client = null) {
        $this->client = $client ?? new api_client();
    }

    /**
     * Returns true if the current license is Pro and validated.
     */
    public function is_pro(): bool {
        $status = $this->get_status();

        // If stored status is not pro but a key exists, force a refresh to recover.
        if ($status->status !== 'pro' && !empty($status->apikey)) {
            try {
                $status = $this->refresh(true);
            } catch (\Throwable $ignore) {
                // fall through to final validation.
            }
        }

        if ($status->status !== 'pro') {
            return false;
        }

        // If token is stale, try to refresh immediately to avoid losing Pro features.
        if (!$this->has_valid_pro_token($status)) {
            try {
                $status = $this->refresh(true);
            } catch (\Throwable $ignore) {
                // fall through to validation below.
            }
        }

        return $status->status === 'pro'
            && $this->validate_signature($status)
            && $this->has_valid_pro_token($status);
    }

    /**
     * Return limits for current mode.
     *
     * @return array{maxobjects:int,maxproperties:int,allowhidden:bool,periodicmax:int,periodicgapdays:int}
     */
    public function get_limits(): array {
        $status = $this->get_status();

        // If Pro but token expired, refresh to recover full limits.
        if ($status->status === 'pro' && !$this->has_valid_pro_token($status)) {
            try {
                $status = $this->refresh(true);
            } catch (\Throwable $ignore) {
                // keep going with current status.
            }
        }

        if (!$this->validate_signature($status)) {
            return $this->fallback_limits();
        }

        $limits = $this->decode_limits($status->limitsjson ?? '');
        if ($status->status !== 'pro') {
            $limits['allowhidden'] = false;
        }
        return $limits;
    }

    /**
     * Get status from DB and perform expiry/domain/signature validation.
     */
    public function get_status(): stdClass {
        global $CFG;

        $record = $this->load_license_record();

        $now = time();
        $domain = \local_inventario_current_domain();

        $changed = false;
        if (!empty($record->expiresat) && $record->expiresat < $now && $record->status !== 'free') {
            $record->status = 'free';
            $changed = true;
        }

        if (!empty($record->domain) && $record->domain !== $domain && $record->status !== 'free') {
            $record->status = 'free';
            $changed = true;
        }

        if ($record->status === 'pro' && (!$this->validate_signature($record) || !$this->has_valid_pro_token($record))) {
            $record->status = 'free';
            $changed = true;
            $record->signature = null;
            $record->protoken = null;
            $record->protokenexpires = 0;
            $record->limitsjson = null;
            $record->issuedat = 0;
        }

        if ($changed) {
            if ($record->status === 'free') {
                $record->signature = null;
                $record->protoken = null;
                $record->protokenexpires = 0;
                $record->limitsjson = null;
                $record->issuedat = 0;
            }
            $record->timemodified = $now;
            $this->persist_license($record);
        }

        return $record;
    }

    /**
     * Force a refresh of license data contacting the external API.
     */
    public function refresh(bool $force = false): stdClass {
        global $CFG;

        $record = $this->get_status();
        $now = time();
        $endpoint = trim((string)get_config('local_inventario', 'endpoint'));
        $token = trim((string)get_config('local_inventario', 'apitoken'));

        if (empty($endpoint) || empty($token)) {
            $record->status = 'free';
            $record->lastpayload = get_string('api_not_configured', 'local_inventario');
            $record->signature = null;
            $record->protoken = null;
            $record->protokenexpires = 0;
            $record->limitsjson = null;
            $record->issuedat = 0;
            $record->lastcheck = $now;
            $record->timemodified = $now;
            return $this->persist_license($record);
        }

        $checkinterval = (int)get_config('local_inventario', 'checkinterval');
        if ($checkinterval <= 0) {
            $checkinterval = 3600;
        }

        if (!$force && ($now - (int)$record->lastcheck) < $checkinterval && $this->validate_signature($record)) {
            return $record;
        }

        $record->lastpayload = '';

        // Register installation (best effort).
        if ($this->client->is_configured()) {
            try {
                $domain = \local_inventario_current_domain();
                $moodleversion = $CFG->release ?? (string)$CFG->version;
                $resp = $this->client->register_installation($domain, $moodleversion);
                if (!empty($resp['installid'])) {
                    $record->installid = $resp['installid'];
                    $record->timemodified = $now;
                    $this->persist_license($record);
                }
            } catch (\Throwable $ignore) {
                // Ignore registration failures; retry later.
            }
        }

        if (!$record->apikey || !$this->client->is_configured()) {
            $record->status = 'free';
            $record->signature = null;
            $record->protoken = null;
            $record->protokenexpires = 0;
            $record->limitsjson = null;
            $record->issuedat = 0;
            $record->lastcheck = $now;
            $record->timemodified = $now;
            return $this->persist_license($record);
        }

        try {
            $domain = \local_inventario_current_domain();
            if (empty($domain)) {
                $domain = get_config('local_inventario', 'forcedomain') ?: 'app.mdlbox.com';
            }
            $domain = \local_inventario_normalize_domain($domain);
            $response = $this->client->validate_license((string)$record->apikey, $domain);
        if (!empty($response['valid']) && $this->is_signed_response_valid($response, $token)) {
            $record = $this->update_record_from_response($record, $response, $domain, $now);
            return $this->persist_license($record);
        }

        $record->status = 'free';
        $record->lastpayload = !empty($response['message']) ? (string)$response['message'] : 'invalid_signature';
    } catch (\Throwable $ex) {
        $record->status = 'free';
        $record->lastpayload = $ex->getMessage();
    }

        $record->signature = null;
        $record->protoken = null;
        $record->protokenexpires = 0;
        $record->limitsjson = null;
        $record->issuedat = 0;
        $record->lastcheck = $now;
        $record->timemodified = $now;
        return $this->persist_license($record);
    }

    /**
     * Force downgrade to free if the stored expiry is in the past.
     */
    public function downgrade_if_expired(): void {
        $record = $this->get_status();
        $now = time();
        if (!empty($record->expiresat) && (int)$record->expiresat < $now && $record->status !== 'free') {
            $record->status = 'free';
            $record->timemodified = $now;
            $this->persist_license($record);
        }
    }

    /**
     * Throws if Pro features are required.
     *
     * @throws moodle_exception
     */
    public function require_pro(): void {
        $status = $this->get_status();
        if ($status->status === 'pro' && $this->validate_signature($status) && $this->has_valid_pro_token($status)) {
            return;
        }

        try {
            $status = $this->refresh(true);
        } catch (\Throwable $ignore) {
            // Ignore refresh errors; we will throw below.
        }

        if ($status->status === 'pro' && $this->validate_signature($status) && $this->has_valid_pro_token($status)) {
            return;
        }

        throw new moodle_exception('prorequired', 'local_inventario');
    }

    /**
     * Enforce Free limits; if exceeded and not Pro an exception is thrown.
     *
     * @param int $current
     * @param string $type objects|properties
     * @throws moodle_exception
     */
    public function enforce_limit(int $current, string $type): void {
        $limits = $this->get_limits();
        if ($this->is_pro()) {
            return;
        }

        if ($type === 'objects' && $limits['maxobjects'] > 0 && $current >= $limits['maxobjects']) {
            throw new moodle_exception('objectlimit', 'local_inventario', '', $limits['maxobjects']);
        }

        if ($type === 'properties' && $limits['maxproperties'] > 0 && $current >= $limits['maxproperties']) {
            throw new moodle_exception('propertylimit', 'local_inventario', '', $limits['maxproperties']);
        }
    }

    /**
     * Persist a new license key manually set.
     */
    public function save_apikey(string $apikey): stdClass {
        $record = $this->get_status();
        $record->apikey = trim($apikey);
        $record->lastcheck = 0;
        $record->status = 'free'; // Will be set to pro after next validation.
        $record->expiresat = null;
        $record->signature = null;
        $record->protoken = null;
        $record->protokenexpires = 0;
        $record->issuedat = 0;
        $record->limitsjson = null;
        $record->lastpayload = '';
        $record->timemodified = time();
        return $this->persist_license($record);
    }

    /**
     * Return license id or create if missing.
     */
    private function get_license_id(): int {
        return (int)$this->load_license_record()->id;
    }

    /**
     * Create a free license record.
     */
    private function bootstrap_license(): stdClass {
        global $DB, $CFG;
        $now = time();
        $domain = \local_inventario_current_domain();

        $record = (object)[
            'apikey' => null,
            'domain' => $domain,
            'status' => 'free',
            'expiresat' => null,
            'installid' => null,
            'issuedat' => 0,
            'protoken' => null,
            'protokenexpires' => 0,
            'limitsjson' => null,
            'signature' => null,
            'lastcheck' => $now,
            'lasttamper' => 0,
            'lastpayload' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('local_inventario_license', $record);
        return $record;
    }

    /**
     * Load the most relevant license record, preferring the latest with a non-empty apikey.
     */
    private function load_license_record(): stdClass {
        global $DB;

        $records = $DB->get_records('local_inventario_license', null, 'id DESC');
        if ($records) {
            foreach ($records as $rec) {
                if (!empty($rec->apikey)) {
                    return $rec;
                }
            }
            // Fallback to most recent even if apikey is empty.
            return reset($records);
        }

        return $this->bootstrap_license();
    }

    /**
     * Persist license record.
     */
    private function persist_license(stdClass $record): stdClass {
        global $DB;

        if (empty($record->id)) {
            $record->id = $this->get_license_id();
        }
        $DB->update_record('local_inventario_license', $record);
        return $record;
    }

    /**
     * Compute canonical signature for a payload.
     */
    private function sign(array $payload): string {
        $secret = trim((string)get_config('local_inventario', 'apitoken'));
        if ($secret === '') {
            return '';
        }
        $payload = $this->canonicalize($payload);
        unset($payload['signature']);
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Canonicalise array recursively.
     */
    private function canonicalize(array $data): array {
        ksort($data);
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->canonicalize($v);
            }
        }
        return $data;
    }

    /**
     * Validate stored signature against saved payload data.
     */
    private function validate_signature(stdClass $record): bool {
        if (empty($record->signature)) {
            return false;
        }

        $payload = $this->signature_payload_from_record($record);
        if (empty($payload['issuedat']) || abs(time() - (int)$payload['issuedat']) > self::RESPONSE_MAX_AGE) {
            return false;
        }

        $expected = $this->sign($payload);
        return hash_equals($expected, (string)$record->signature);
    }

    /**
     * Transform DB record to the payload signed by the backend.
     */
    private function signature_payload_from_record(stdClass $record): array {
        $limits = $this->decode_limits($record->limitsjson ?? '');
        return [
            'valid' => true,
            'apikey' => (string)$record->apikey,
            'mode' => (string)$record->status,
            'domain' => (string)($record->domain ?? ''),
            'expires' => (int)($record->expiresat ?? 0),
            'installid' => $record->installid ?? null,
            'issuedat' => (int)($record->issuedat ?? 0),
            'protoken' => (string)($record->protoken ?? ''),
            'protokenexpires' => (int)($record->protokenexpires ?? 0),
            'limits' => $limits,
        ];
    }

    /**
     * Validate remote response signature.
     */
    private function is_signed_response_valid(array $response, string $secret): bool {
        if (empty($response['signature'])) {
            return false;
        }
        if (empty($response['issuedat']) || abs(time() - (int)$response['issuedat']) > self::RESPONSE_MAX_AGE) {
            return false;
        }

        $payload = $response;
        unset($payload['signature']);
        $payload = $this->canonicalize($payload);
        $expected = hash_hmac('sha256', json_encode($payload), $secret);
        if (!hash_equals($expected, (string)$response['signature'])) {
            return false;
        }

        if (!empty($response['mode']) && $response['mode'] === 'pro') {
            if (empty($response['protoken']) || empty($response['protokenexpires'])) {
                return false;
            }
            if ((int)$response['protokenexpires'] <= time()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update local record with a validated backend response.
     */
    private function update_record_from_response(stdClass $record, array $response, string $domain, int $now): stdClass {
        $record->status = ($response['mode'] ?? 'free') === 'pro' ? 'pro' : 'free';
        $record->expiresat = $response['expires'] ?? null;
        $record->domain = $response['domain'] ?? $domain;
        $record->installid = $response['installid'] ?? $record->installid;
        $record->issuedat = (int)($response['issuedat'] ?? $now);
        $record->protoken = $response['protoken'] ?? null;
        $record->protokenexpires = (int)($response['protokenexpires'] ?? 0);
        $limits = isset($response['limits']) ? $this->sanitize_limits((array)$response['limits']) : $this->fallback_limits();
        $record->limitsjson = json_encode($limits);
        $record->signature = $response['signature'] ?? null;
        $record->lastpayload = '';
        $record->lastcheck = $now;
        $record->timemodified = $now;
        return $record;
    }

    /**
     * Decode limits JSON into a safe array.
     *
     * @param mixed $rawjson
     * @return array{maxobjects:int,maxproperties:int,allowhidden:bool,periodicmax:int,periodicgapdays:int}
     */
    private function decode_limits($rawjson): array {
        if (empty($rawjson)) {
            return $this->fallback_limits();
        }
        if (is_array($rawjson)) {
            $data = $rawjson;
        } else {
            $data = json_decode($rawjson, true);
        }
        if (!is_array($data)) {
            return $this->fallback_limits();
        }

        return $this->sanitize_limits($data);
    }

    /**
     * Sanitize limit values.
     */
    private function sanitize_limits(array $limits): array {
        return [
            'maxobjects' => max(0, (int)($limits['maxobjects'] ?? 0)),
            'maxproperties' => max(0, (int)($limits['maxproperties'] ?? 0)),
            'allowhidden' => !empty($limits['allowhidden']),
            'periodicmax' => max(0, (int)($limits['periodicmax'] ?? 0)),
            'periodicgapdays' => max(1, (int)($limits['periodicgapdays'] ?? 1)),
        ];
    }

    /**
     * Limits used when the signature is missing/invalid.
     */
    private function fallback_limits(): array {
        return [
            'maxobjects' => 1,
            'maxproperties' => 1,
            'allowhidden' => false,
            'periodicmax' => 0,
            'periodicgapdays' => 7,
        ];
    }

    /**
     * Verify current Pro token freshness.
     */
    private function has_valid_pro_token(stdClass $record): bool {
        if ($record->status !== 'pro') {
            return false;
        }
        // Require a short-lived Pro token; if missing/expired caller should trigger a refresh.
        if (empty($record->protoken) || empty($record->protokenexpires)) {
            return false;
        }
        if (((int)$record->protokenexpires - self::PROTOKEN_GRACE) <= time()) {
            return false;
        }
        return true;
    }
}

