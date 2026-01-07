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
 * External services for the Inventario plugin.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/locallib.php');

/**
 * External functions for local_inventario.
 */
class local_inventario_external extends external_api {
    /**
     * Params for toggle_visibility.
     *
     * @return external_function_parameters
     */
    public static function toggle_visibility_parameters(): external_function_parameters {
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
    public static function toggle_visibility(int $id, bool $visible): array {
        $params = self::validate_parameters(self::toggle_visibility_parameters(), [
            'id' => $id,
            'visible' => $visible,
        ]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/inventario:togglevisibility', $context);

        $service = local_inventario_service();
        $service->toggle_visibility($params['id'], $params['visible']);

        return ['success' => true];
    }

    /**
     * Return structure for toggle_visibility.
     *
     * @return external_single_structure
     */
    public static function toggle_visibility_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation result'),
        ]);
    }

    /**
     * Params for refresh_license.
     *
     * @return external_function_parameters
     */
    public static function refresh_license_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Force refresh of the license from backend.
     *
     * @return array
     */
    public static function refresh_license(): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/inventario:managelicense', $context);

        $license = local_inventario_license();
        $license->refresh(true);

        return ['success' => true];
    }

    /**
     * Return structure for refresh_license.
     *
     * @return external_single_structure
     */
    public static function refresh_license_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Operation result'),
        ]);
    }
}
