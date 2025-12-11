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
 * Minimal API client to communicate with the external license backend.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\local;

use curl;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/filelib.php');


class api_client {
    /** @var string */
    private $endpoint;
    /** @var string */
    private $token;

    public function __construct() {
        $this->endpoint = rtrim(trim((string)get_config('local_inventario', 'endpoint')), '/');
        $this->token = trim((string)get_config('local_inventario', 'apitoken'));
    }

    /**
     * Returns true when the client has enough config to talk to the API.
     */
    public function is_configured(): bool {
        return !empty($this->endpoint) && !empty($this->token);
    }

    /**
     * Validate the provided API key with the external system.
     *
     * @param string $apikey
     * @param string $domain
     * @return array
     */
    public function validate_license(string $apikey, string $domain): array {
        return $this->post('/license/validate', [
            'apikey' => $apikey,
            'domain' => $domain,
        ]);
    }

    /**
     * Register a new installation.
     *
     * @param string $domain
     * @param string $version
     * @return array
     */
    public function register_installation(string $domain, string $version): array {
        return $this->post('/installations/register', [
            'domain' => $domain,
            'version' => $version,
        ]);
    }

    /**
     * Send any event back to the backend.
     *
     * @param string $type
     * @param array $payload
     * @return array
     */
    public function log_event(string $type, array $payload = []): array {
        return $this->post('/events', ['type' => $type, 'payload' => $payload]);
    }

    /**
     * Signed POST to the remote API.
     *
     * @param string $route
     * @param array $payload
     * @return array
     * @throws moodle_exception
     */
    private function post(string $route, array $payload): array {
        if (!$this->is_configured()) {
            throw new moodle_exception('api_not_configured', 'local_inventario');
        }

        $base = rtrim($this->endpoint, '/');
        // Allow direct script endpoints (e.g., api.php) when rewrite is not available.
        if (preg_match('/\\.php$/', $base)) {
            $url = $base . '?route=' . urlencode(ltrim($route, '/'));
        } else {
            $url = $base . '/' . ltrim($route, '/');
        }
        $payload['timestamp'] = time();
        // Include token in payload to support servers that strip auth headers.
        $payload['apitoken'] = $this->token;
        $signature = $this->sign($payload);

        // Use native cURL; fallback to PHP streams (no Moodle curl to avoid host blocking).
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload, '', '&'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->token,
                'X-Inventario-Signature: ' . $signature,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new moodle_exception('apirequestfailed', 'local_inventario', '', $err);
            }
            curl_close($ch);
        } else {
            $response = $this->native_post($url, $payload, $signature);
            // If some layer blocks localhost, retry with 127.0.0.1.
            if (is_string($response) && stripos($response, 'bloccata') !== false) {
                $parts = parse_url($url);
                if (!empty($parts['host']) && $parts['host'] === 'localhost') {
                    $url = str_replace('localhost', '127.0.0.1', $url);
                    $response = $this->native_post($url, $payload, $signature);
                }
            }
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new moodle_exception('apiinvalid', 'local_inventario', '', s($response));
        }

        if (!empty($data['tamper'])) {
            throw new moodle_exception('tamperdetected', 'local_inventario');
        }

        return $data;
    }

    /**
     * Native HTTP POST helper.
     *
     * @param string $url
     * @param array $payload
     * @param string $signature
     * @return string
     * @throws moodle_exception
     */
    private function native_post(string $url, array $payload, string $signature): string {
        $body = http_build_query($payload, '', '&');
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->token,
                'X-Inventario-Signature: ' . $signature,
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new moodle_exception('apirequestfailed', 'local_inventario', '', $err);
            }
            curl_close($ch);
            return (string)$response;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                    "Authorization: Bearer " . $this->token . "\r\n" .
                    "X-Inventario-Signature: " . $signature . "\r\n",
                'content' => $body,
                'timeout' => 10,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new moodle_exception('apirequestfailed', 'local_inventario', '', 'stream request failed');
        }
        return (string)$response;
    }

    /**
     * Compute payload signature.
     *
     * @param array $payload
     * @return string
     */
    private function sign(array $payload): string {
        $normalized = [];
        foreach ($payload as $k => $v) {
            $normalized[$k] = is_scalar($v) ? (string)$v : $v;
        }
        ksort($normalized);
        return hash_hmac('sha256', json_encode($normalized), $this->token);
    }
}

