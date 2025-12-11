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
 * Settings for the Inventario plugin.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_inventario', get_string('pluginname', 'local_inventario'));

$settings->add(new admin_setting_configtext(
    'local_inventario/endpoint',
    get_string('apiendpoint', 'local_inventario'),
    get_string('apiendpoint_desc', 'local_inventario'),
    ''
));

$settings->add(new admin_setting_configpasswordunmask(
    'local_inventario/apitoken',
    get_string('apitoken', 'local_inventario'),
    get_string('apitoken_desc', 'local_inventario'),
    ''
));

    $settings->add(new admin_setting_configtext(
        'local_inventario/checkinterval',
        get_string('checkinterval', 'local_inventario'),
        get_string('checkinterval_desc', 'local_inventario'),
        3600,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_inventario/allowperiodic',
        get_string('allowperiodic', 'local_inventario'),
        get_string('allowperiodic_desc', 'local_inventario'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_inventario/overduegrace',
        get_string('overduegrace', 'local_inventario'),
        get_string('overduegrace_desc', 'local_inventario'),
        60,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}

