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
 * External service: main view for the Moodle Mobile app.
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
use external_files;
use context_system;
use local_inventario\local\absence_service;

/**
 * Build the Inventario main view for the Moodle Mobile app.
 */
class mobile_inventario_view extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'args' => new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User the view is for', VALUE_OPTIONAL),
                'appid' => new external_value(PARAM_NOTAGS, 'App id', VALUE_OPTIONAL),
                'appversioncode' => new external_value(PARAM_INT, 'App version code', VALUE_OPTIONAL),
                'appversionname' => new external_value(PARAM_NOTAGS, 'App version name', VALUE_OPTIONAL),
                'appcustomurlscheme' => new external_value(PARAM_NOTAGS, 'Custom url scheme', VALUE_OPTIONAL),
                'lang' => new external_value(PARAM_NOTAGS, 'Language', VALUE_OPTIONAL),
            ], 'Mobile app args', VALUE_DEFAULT, []),
        ]);
    }

    /**
     * Return the main view templates for the app.
     *
     * @param array $args
     * @return array
     */
    public static function execute(array $args = []): array {
        global $OUTPUT, $USER;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/inventario:view', $context);

        $now = time();
        $service = local_inventario_service();

        $reservations = $service->get_reservations(['userid' => (int)$USER->id], false);
        $resdata = [];
        foreach ($reservations as $reservation) {
            if ($reservation->status === 'returned' || $reservation->timeend < $now) {
                continue;
            }
            $resdata[] = [
                'object' => format_string($reservation->objectname),
                'site' => format_string($reservation->sitename),
                'location' => format_string($reservation->location ?? ''),
                'period' => userdate($reservation->timestart) . ' - ' . userdate($reservation->timeend),
            ];
            if (count($resdata) >= 30) {
                break;
            }
        }

        $absdata = [];
        $showabsences = false;
        try {
            if (local_inventario_license()->is_pro()) {
                $showabsences = true;
                $absservice = new absence_service();
                $daystart = usergetmidnight($now);
                $absences = $absservice->get_absences([
                    'overlapstart' => $daystart,
                    'overlapend' => $daystart + DAYSECS - 1,
                ]);
                foreach ($absences as $absence) {
                    $teacher = (object)['firstname' => $absence->teacherfirstname, 'lastname' => $absence->teacherlastname];
                    $substitute = $absence->substitutename
                        ?: trim(($absence->substitutefirstname ?? '') . ' ' . ($absence->substitutefamilyname ?? ''));
                    $absdata[] = [
                        'teacher' => fullname($teacher),
                        'subject' => format_string($absence->subject),
                        'period' => userdate($absence->timestart) . ' - ' . userdate($absence->timeend),
                        'substitute' => $substitute,
                        'hassubstitute' => $substitute !== '',
                    ];
                }
            }
        } catch (\Throwable $e) {
            $showabsences = false;
        }

        $data = [
            'reservations' => $resdata,
            'hasreservations' => !empty($resdata),
            'showabsences' => $showabsences,
            'absences' => $absdata,
            'hasabsences' => !empty($absdata),
        ];

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('local_inventario/mobile_main', $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => [],
            'files' => [],
        ];
    }

    /**
     * Return structure.
     *
     * The template HTML and JavaScript are generated output returned to the app,
     * so PARAM_RAW is required here (this is not unvalidated user input).
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'templates' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_TEXT, 'Template id'),
                    'html' => new external_value(PARAM_RAW, 'Rendered template HTML'),
                ])
            ),
            'javascript' => new external_value(PARAM_RAW, 'JavaScript', VALUE_DEFAULT, ''),
            'otherdata' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Name'),
                    'value' => new external_value(PARAM_RAW, 'Value'),
                ]),
                'Other data',
                VALUE_DEFAULT,
                []
            ),
            'files' => new external_files('Files', VALUE_DEFAULT, []),
        ]);
    }
}
