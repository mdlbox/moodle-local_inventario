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
 * Install script for local_inventario.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install hook for local_inventario.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../locallib.php');

/**
 * Post-install actions.
 */
function xmldb_local_inventario_install(): bool {
    global $DB;

    $now = time();

    if (!$DB->record_exists('local_inventario_sites', [])) {
        $site = (object)[
            'name' => get_string('defaultsite', 'local_inventario'),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('local_inventario_sites', $site);
    }

    // Create booking-only global role.
    if (function_exists('local_inventario_create_booking_role')) {
        local_inventario_create_booking_role();
    }

    return true;
}
