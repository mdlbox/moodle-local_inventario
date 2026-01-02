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
 * AJAX endpoints for inventory actions.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHANUMEXT);
$context = context_system::instance();
$response = ['success' => false];

try {
    switch ($action) {
        case 'togglevisibility':
            require_capability('local/inventario:togglevisibility', $context);
            $id = required_param('id', PARAM_INT);
            $visible = required_param('visible', PARAM_BOOL);
            local_inventario_service()->toggle_visibility($id, $visible);
            $response['success'] = true;
            break;
        case 'refreshlicense':
            require_capability('local/inventario:managelicense', $context);
            $license = local_inventario_license()->refresh(true);
            $response['success'] = true;
            $response['mode'] = $license->status;
            break;
        default:
            throw new moodle_exception('invalidaction', 'local_inventario');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
