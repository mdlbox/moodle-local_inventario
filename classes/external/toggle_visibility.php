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
 * External service: toggle object visibility.
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
use context_system;

/**
 * Toggle the visibility of an inventory object.
 */
class toggle_visibility extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Object id', VALUE_REQUIRED),
            'visible' => new external_value(PARAM_BOOL, 'New visibility', VALUE_REQUIRED),
        ]);
    }

    /**
     * Toggle object visibility.
     *
     * @param int $id
     * @param bool $visible
     * @return array
     */
    public static function execute(int $id, bool $visible): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'visible' => $visible,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/inventario:togglevisibility', $context);

        local_inventario_service()->toggle_visibility($params['id'], $params['visible']);

        return ['success' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation result'),
        ]);
    }
}
