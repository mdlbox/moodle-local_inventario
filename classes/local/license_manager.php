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
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\local;

use moodle_exception;
use stdClass;
use local_inventario\local\secrets;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/secrets.php');

/**
 * License manager that validates signed responses and enforces limits.
 */
class license_manager {
    /** Default Free API key shipped with the plugin for baseline usage. */
    private const DEFAULT_FREE_KEY = '95b45fde6670f63efc07b51ec34f26d8';
    /** Seconds of slack before token expiry. */
    private const PROTOKEN_GRACE = 30;
    /** Maximum age (seconds) for signed responses. */
    private const RESPONSE_MAX_AGE = 1800;
    /** Maximum age (seconds) before forcing refresh of cache even if signature valid. */
    private const HARD_MAX_CACHE_AGE = 7200;
    /** Allowed license endpoints (hosts). */
    private const ALLOWED_HOSTS = ['app.mdlbox.com', 'api.mdlbox.com', 'license.mdlbox.com'];

    /** @var api_client|null */
    private $client;

    /** @var array<string,bool> Cached feature flags during a request. */
    private $featurecache = [];

    /**
     * License manager constructor.
     *
     * @param api_client|null $client Optional API client override.
     */
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
                debugging($ignore->getMessage(), DEBUG_DEVELOPER);
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
                debugging($ignore->getMessage(), DEBUG_DEVELOPER);
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
        $isdefaultkey = !empty($status->apikey) && $this->is_default_key($status->apikey);

        // Force a periodic refresh for the bundled Free key to pick backend changes.
        if ($isdefaultkey && (time() - (int)($status->lastcheck ?? 0)) > 900) {
            try {
                $status = $this->refresh(true);
            } catch (\Throwable $ignore) {
                debugging($ignore->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // If Pro but token expired, refresh to recover full limits.
        if ($status->status === 'pro' && !$this->has_valid_pro_token($status)) {
            try {
                $status = $this->refresh(true);
            } catch (\Throwable $ignore) {
                debugging($ignore->getMessage(), DEBUG_DEVELOPER);
            }
        }

        if (!$this->validate_signature($status)) {
            if ($isdefaultkey) {
                try {
                    $status = $this->refresh(true);
                } catch (\Throwable $ignore) {
                    debugging($ignore->getMessage(), DEBUG_DEVELOPER);
                }
                if ($this->validate_signature($status)) {
                    $limits = $this->decode_limits($status->limitsjson ?? '');
                    $limits = $this->clamp_free_limits($limits);
                    return $limits;
                }
            }
            return $this->fallback_limits();
        }

        $limits = $this->decode_limits($status->limitsjson ?? '');
        if ($status->status !== 'pro') {
            $limits = $this->clamp_free_limits($limits);
        }
        return $limits;
    }

    /**
     * Check if a named feature is enabled given the current license state.
     *
     * @param string $feature
     * @return bool
     */
    public function is_feature_enabled(string $feature): bool {
        $feature = strtolower(trim($feature));
        if (array_key_exists($feature, $this->featurecache)) {
            return $this->featurecache[$feature];
        }

        $enabled = false;
        switch ($feature) {
            case 'hidden':
            case 'allowhidden':
                $limits = $this->get_limits();
                $enabled = $this->is_pro() && !empty($limits['allowhidden']);
                break;
            case 'availability':
            case 'timewindow':
                $enabled = $this->is_pro();
                break;
            case 'periodic':
            case 'recurring':
                $enabled = $this->is_pro();
                break;
            case 'publicpage':
                $enabled = $this->is_pro();
                break;
            default:
                $enabled = false;
        }

        $this->featurecache[$feature] = $enabled;
        return $enabled;
    }

    /**
     * Get status from DB and perform expiry/domain/signature validation.
     */
    public function get_status(): stdClass {
        global $CFG;

        $record = $this->load_license_record();

        $now = time();
        $domain = \local_inventario_current_domain();
        $isdefaultkey = !empty($record->apikey) && $this->is_default_key($record->apikey);

        $changed = false;
        if (!empty($record->expiresat) && $record->expiresat < $now && $record->status !== 'free') {
            $record->status = 'free';
            $changed = true;
        }

        if (!empty($record->domain) && $record->domain !== $domain && $record->status !== 'free' && !$isdefaultkey) {
            $record->status = 'free';
            $changed = true;
        }

        $stale = $record->status === 'pro' && $record->lastcheck > 0
            && ($now - (int)$record->lastcheck) > self::HARD_MAX_CACHE_AGE;

        if ($record->status === 'pro' && (!$this->validate_signature($record) || !$this->has_valid_pro_token($record) || $stale)) {
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
     *
     * @param bool $force
     * @return stdClass
     */
    public function refresh(bool $force = false): stdClass {
        global $CFG;

        $record = $this->get_status();
        $now = time();
        $endpoint = trim(secrets::endpoint());
        $token = trim(secrets::apitoken());

        if (empty($endpoint) || empty($token) || !$this->is_endpoint_allowed($endpoint)) {
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
            $checkinterval = 1800;
        }

        if (
            !$force
            && ($now - (int)$record->lastcheck) < $checkinterval
            && $this->validate_signature($record)
            && $this->has_valid_pro_token($record)
        ) {
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
                debugging($ignore->getMessage(), DEBUG_DEVELOPER);
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
     * Ensure the configured endpoint belongs to an allowed host over HTTPS.
     *
     * @param string $endpoint
     * @return bool
     */
    private function is_endpoint_allowed(string $endpoint): bool {
        $parts = @parse_url($endpoint);
        if (empty($parts['scheme']) || strtolower((string)$parts['scheme']) !== 'https') {
            return false;
        }
        if (empty($parts['host'])) {
            return false;
        }
        $host = strtolower((string)$parts['host']);
        return in_array($host, self::ALLOWED_HOSTS, true);
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
            debugging($ignore->getMessage(), DEBUG_DEVELOPER);
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
     *
     * @param string $apikey
     * @return stdClass
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
            'apikey' => self::DEFAULT_FREE_KEY,
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
     *
     * @param stdClass $record
     * @return stdClass
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
     *
     * @param array $payload
     * @return string
     */
    private function sign(array $payload): string {
        $secret = trim(secrets::apitoken());
        if ($secret === '') {
            return '';
        }
        $payload = $this->canonicalize($payload);
        unset($payload['signature']);
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Canonicalise array recursively.
     *
     * @param array $data
     * @return array
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
     *
     * @param stdClass $record
     * @return bool
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
     *
     * @param stdClass $record
     * @return array
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
     *
     * @param array $response
     * @param string $secret
     * @return bool
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
     *
     * @param stdClass $record
     * @param array $response
     * @param string $domain
     * @param int $now
     * @return stdClass
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
        if (!empty($record->apikey) && $this->is_default_key($record->apikey)) {
            // Allow the bundled Free key to work on any domain by aligning to the current caller domain.
            $record->domain = $domain;
        }
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
     *
     * @param array $limits
     * @return array
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
            'maxobjects' => 15,
            'maxproperties' => 5,
            'allowhidden' => false,
            'periodicmax' => 0,
            'periodicgapdays' => 7,
        ];
    }

    /**
     * Clamp Free-mode limits to safe defaults and disable hidden features.
     *
     * @param array $limits
     * @return array
     */
    private function clamp_free_limits(array $limits): array {
        $fallback = $this->fallback_limits();
        $limits['allowhidden'] = false;
        $limits['maxobjects'] = ($limits['maxobjects'] > 0)
            ? min($limits['maxobjects'], $fallback['maxobjects'])
            : $fallback['maxobjects'];
        $limits['maxproperties'] = ($limits['maxproperties'] > 0)
            ? min($limits['maxproperties'], $fallback['maxproperties'])
            : $fallback['maxproperties'];
        return $limits;
    }

    /**
     * Return the bundled Free API key used during installation.
     *
     * @return string
     */
    public static function default_free_key(): string {
        return self::DEFAULT_FREE_KEY;
    }

    /**
     * Helper to check if a key matches the bundled Free key.
     *
     * @param string $key
     * @return bool
     */
    private function is_default_key(string $key): bool {
        $key = trim($key);
        return $key !== '' && hash_equals(self::DEFAULT_FREE_KEY, $key);
    }

    /**
     * Verify current Pro token freshness.
     *
     * @param stdClass $record
     * @return bool
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
