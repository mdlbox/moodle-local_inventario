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
 * Core callbacks for local_inventario.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Plugin callbacks for Inventario.
 *
 * @package   local_inventario
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Adds a node to the site navigation.
 *
 * @param global_navigation $navigation
 */
function local_inventario_extend_navigation(global_navigation $navigation): void {
    if (!has_capability('local/inventario:view', context_system::instance())) {
        return;
    }

    $url = new moodle_url('/local/inventario/index.php');
    $node = navigation_node::create(
        get_string('pluginname', 'local_inventario'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_inventario',
        new pix_icon('i/report', '')
    );

    $navigation->add_node($node);
}

/**
 * Add settings link in the admin navigation.
 *
 * Signature must accept the context as second parameter, see core navigation callbacks.
 *
 * @param settings_navigation $settingsnav
 * @param context|null $context
 */
function local_inventario_extend_settings_navigation(settings_navigation $settingsnav, $context = null): void {
    if (!has_capability('local/inventario:manageobjects', context_system::instance())) {
        return;
    }
    $url = new moodle_url('/local/inventario/license.php');
    $node = navigation_node::create(
        get_string('license', 'local_inventario'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_inventario_license',
        new pix_icon('i/settings', '')
    );
    $settingsnav->add_node($node);
}
