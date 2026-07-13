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
 * External service: create one reservation per selected object.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/inventario/locallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

/**
 * Create one reservation per object over the same time range.
 */
class create_reservations extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'objectids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Object id'),
                'Objects to reserve'
            ),
            'timestart' => new external_value(PARAM_INT, 'Reservation start (unix time)'),
            'timeend' => new external_value(PARAM_INT, 'Reservation end (unix time)'),
            'location' => new external_value(PARAM_TEXT, 'Location of use', VALUE_DEFAULT, ''),
            'siteid' => new external_value(PARAM_INT, 'Site id (0 = use each object site)', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Target user id (managers only)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create the reservations.
     *
     * @param array $objectids
     * @param int $timestart
     * @param int $timeend
     * @param string $location
     * @param int $siteid
     * @param int $userid
     * @return array
     */
    public static function execute(
        array $objectids,
        int $timestart,
        int $timeend,
        string $location = '',
        int $siteid = 0,
        int $userid = 0
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'objectids' => $objectids,
            'timestart' => $timestart,
            'timeend' => $timeend,
            'location' => $location,
            'siteid' => $siteid,
            'userid' => $userid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/inventario:reserve', $context);

        local_inventario_license()->require_pro();

        $canmanageall = has_capability('local/inventario:deletereservations', $context);

        $base = (object)[
            'siteid' => (int)$params['siteid'],
            'timestart' => (int)$params['timestart'],
            'timeend' => (int)$params['timeend'],
            'location' => (string)$params['location'],
            'status' => 'active',
        ];
        if ($canmanageall && !empty($params['userid'])) {
            $base->userid = (int)$params['userid'];
        }

        $result = local_inventario_service()->create_reservations_bulk(
            $params['objectids'],
            $base,
            (int)$USER->id,
            $canmanageall
        );

        return [
            'created' => (int)$result['created'],
            'errors' => array_map(static function ($error) {
                return [
                    'objectid' => (int)$error['objectid'],
                    'message' => (string)$error['message'],
                ];
            }, $result['errors']),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'created' => new external_value(PARAM_INT, 'Number of reservations created'),
            'errors' => new external_multiple_structure(
                new external_single_structure([
                    'objectid' => new external_value(PARAM_INT, 'Object id that failed'),
                    'message' => new external_value(PARAM_TEXT, 'Failure reason'),
                ]),
                'Per-object failures',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }
}
