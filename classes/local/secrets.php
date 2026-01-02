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
 * Stores encrypted endpoint and API token for the Inventario plugin.
 *
 * Update the ciphertext constants with the output of cli/encrypt_secrets.php.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Simple secret helper that keeps endpoint and token encrypted in code.
 */
class secrets {
    /** Master key used to derive the encryption key. */
    private const MASTER_KEY = '5f4e8b1d2b84413cb2a6e68fac978db7';
    /** Encrypted endpoint (base64 iv+ciphertext). */
    private const ENDPOINT_CIPHER = 'yaOUjjwOdsIfCDNe8RYaZFSpbfM7cpbS1+v9eFi4vW3/EVqXDplIbzwFUcMRPMhM';
    /** Encrypted API token (base64 iv+ciphertext). */
    private const APITOKEN_CIPHER = '/XEoujwMsDWfuq6jlixZ8s/1xMMmiQhGwITnkQ1pU4aFu4RC6rvClu4Xd5Fnd5MC';
    /** Cipher method. */
    private const CIPHER_METHOD = 'AES-256-CBC';

    /**
     * Return decrypted endpoint.
     *
     * @return string
     */
    public static function endpoint(): string {
        return self::decrypt(self::ENDPOINT_CIPHER);
    }

    /**
     * Return decrypted API token.
     *
     * @return string
     */
    public static function apitoken(): string {
        return self::decrypt(self::APITOKEN_CIPHER);
    }

    /**
     * Helper to encrypt a value using the master key (used by the CLI helper).
     *
     * @param string $plain
     * @return string base64 iv+ciphertext
     */
    public static function encrypt(string $plain): string {
        $key = self::key();
        $ivlen = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = random_bytes($ivlen);
        $cipher = openssl_encrypt($plain, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . ($cipher ?: ''));
    }

    /**
     * Decrypt a base64 iv+ciphertext bundle.
     *
     * @param string $bundle
     * @return string
     */
    private static function decrypt(string $bundle): string {
        if ($bundle === '') {
            return '';
        }

        $raw = base64_decode($bundle, true);
        if ($raw === false) {
            return '';
        }

        $ivlen = openssl_cipher_iv_length(self::CIPHER_METHOD);
        if ($ivlen <= 0 || strlen($raw) <= $ivlen) {
            return '';
        }

        $iv = substr($raw, 0, $ivlen);
        $cipher = substr($raw, $ivlen);
        $plain = openssl_decrypt($cipher, self::CIPHER_METHOD, self::key(), OPENSSL_RAW_DATA, $iv);
        return is_string($plain) ? trim($plain) : '';
    }

    /**
     * Derive a 256-bit key from the master key.
     *
     * @return string
     */
    private static function key(): string {
        return hash('sha256', self::MASTER_KEY, true);
    }
}
