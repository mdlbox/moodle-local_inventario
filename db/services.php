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
 * Services definition for local_inventario.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_inventario_toggle_visibility' => [
        'classname'    => 'local_inventario\external\toggle_visibility',
        'methodname'   => 'execute',
        'description'  => 'Toggle object visibility',
        'type'         => 'write',
        'capabilities' => 'local/inventario:togglevisibility',
        'ajax'         => true,
    ],
    'local_inventario_create_reservations' => [
        'classname'    => 'local_inventario\external\create_reservations',
        'methodname'   => 'execute',
        'description'  => 'Create one reservation per selected object over a time range',
        'type'         => 'write',
        'capabilities' => 'local/inventario:reserve',
        'ajax'         => true,
    ],
    'local_inventario_mobile_inventario_view' => [
        'classname'    => 'local_inventario\external\mobile_inventario_view',
        'methodname'   => 'execute',
        'description'  => 'Returns the Inventario main view for the Moodle Mobile app',
        'type'         => 'read',
        'capabilities' => 'local/inventario:view',
        'ajax'         => true,
        'services'     => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];

$services = [];
