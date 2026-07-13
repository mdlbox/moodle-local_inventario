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
 * Feature availability helper.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\local;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Thin, self-contained feature-availability helper.
 *
 * The plugin is distributed as a complete product: every feature is always
 * available and no external activation, licence key or remote validation is
 * performed. This class is kept only so existing callers keep working without
 * changes; all of its methods report "fully enabled" and make no external calls.
 */
class license_manager {
    /**
     * Whether the full feature set is available (always true).
     *
     * @return bool
     */
    public function is_pro(): bool {
        return true;
    }

    /**
     * No-op: all features are always available.
     *
     * @return void
     */
    public function require_pro(): void {
    }

    /**
     * Whether a named feature is enabled (always true).
     *
     * @param string $feature
     * @return bool
     */
    public function is_feature_enabled(string $feature): bool {
        return true;
    }

    /**
     * Usage limits (none).
     *
     * @return array{maxobjects:int,maxproperties:int,allowhidden:bool,periodicmax:int,periodicgapdays:int}
     */
    public function get_limits(): array {
        return [
            'maxobjects' => 0,
            'maxproperties' => 0,
            'allowhidden' => true,
            'periodicmax' => 0,
            'periodicgapdays' => 365,
        ];
    }

    /**
     * No-op: there are no usage limits to enforce.
     *
     * @param int $current
     * @param string $type
     * @return void
     */
    public function enforce_limit(int $current, string $type): void {
    }

    /**
     * Return a static "fully enabled" status object.
     *
     * @return stdClass
     */
    public function get_status(): stdClass {
        global $CFG;
        return (object)[
            'status' => 'pro',
            'domain' => parse_url($CFG->wwwroot, PHP_URL_HOST) ?: '',
            'expiresat' => null,
            'lastcheck' => time(),
            'lastpayload' => '',
        ];
    }

    /**
     * No external refresh is performed; returns the static status.
     *
     * @param bool $force
     * @return stdClass
     */
    public function refresh(bool $force = false): stdClass {
        return $this->get_status();
    }
}
