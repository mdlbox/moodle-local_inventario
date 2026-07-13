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
 * Serve object photos and attachments.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if the file was not found
 */
function local_inventario_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): bool {
    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== 'objectfiles') {
        return false;
    }

    require_login();
    if (!has_capability('local/inventario:view', $context)) {
        return false;
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_inventario', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
    return true;
}

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

