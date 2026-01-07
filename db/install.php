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
    global $DB, $CFG;

    $now = time();

    if (!$DB->record_exists('local_inventario_sites', [])) {
        $site = (object)[
            'name' => get_string('defaultsite', 'local_inventario'),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('local_inventario_sites', $site);
    }

    $licenseid = null;
    if (!$DB->record_exists('local_inventario_license', [])) {
        $domain = local_inventario_current_domain();
        $license = (object)[
            'apikey' => null,
            'domain' => $domain ?: '',
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
        $licenseid = $DB->insert_record('local_inventario_license', $license);
    } else {
        $licenseid = (int)$DB->get_field('local_inventario_license', 'id', []);
    }

    $install = (object)[
        'installid' => null,
        'domain' => local_inventario_current_domain() ?: '',
        'version' => $CFG->version,
        'installedat' => $now,
        'lastreported' => 0,
        'status' => 'created',
    ];
    $DB->insert_record('local_inventario_install', $install);

    // Create booking-only global role.
    if (function_exists('local_inventario_create_booking_role')) {
        local_inventario_create_booking_role();
    }

    // Try to register the installation with the external backend if already configured.
    try {
        require_once(__DIR__ . '/../classes/local/api_client.php');
        $client = new \local_inventario\local\api_client();
        if ($client->is_configured()) {
            $response = $client->register_installation($install->domain, (string)$CFG->version);
            if (!empty($response['installid']) && !empty($licenseid)) {
                $licenserec = $DB->get_record('local_inventario_license', ['id' => $licenseid]);
                $licenserec->installid = $response['installid'];
                $DB->update_record('local_inventario_license', $licenserec);
            }
        }
    } catch (\Throwable $ignored) {
        debugging($ignored->getMessage(), DEBUG_DEVELOPER);
    }

    return true;
}
