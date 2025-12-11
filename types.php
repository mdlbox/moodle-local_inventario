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
 * Manage object types for the inventory.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/type_form.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:manageobjects', $context);

$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$PAGE->set_url('/local/inventario/types.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('managetypes', 'local_inventario'));
$PAGE->set_heading(get_string('managetypes', 'local_inventario'));

$typeservice = local_inventario_typeservice();
$properties = local_inventario_service()->get_properties();
$form = new local_inventario_type_form($PAGE->url, $properties);
$form->set_data((object)['color' => '#2563eb']);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/inventario/index.php'));
}

if ($delete && confirm_sesskey()) {
    $typeservice->delete_type($delete);
    redirect($PAGE->url, get_string('typedeleted', 'local_inventario'));
}

if ($data = $form->get_data()) {
    $typeservice->save_type($data);
    redirect($PAGE->url, get_string('changessaved'), 0);
}

if ($id) {
    $type = $DB->get_record('local_inventario_types', ['id' => $id], '*', MUST_EXIST);
    $type->properties = $typeservice->get_type_property_ids($id);
    if (empty($type->color)) {
        $type->color = '#2563eb';
    }
    $form->set_data($type);
}

$types = $typeservice->get_types();

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

$form->display();

$table = new html_table();
$table->head = [
    get_string('typename', 'local_inventario'),
    get_string('typecolor', 'local_inventario'),
    get_string('typeproperties', 'local_inventario'),
    get_string('actions'),
];
$table->colclasses = array_fill(0, count($table->head), '');
$table->colclasses[count($table->head) - 1] = 'inventario-actions-col';

$editicon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
$deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));

foreach ($types as $type) {
    $props = $typeservice->get_type_properties($type->id);
    $proplabels = array_map(static function($p) {
        return format_string($p->name);
    }, $props);
    $color = !empty($type->color) ? $type->color : '#2563eb';
    $colorchip = html_writer::div('', '', [
        'class' => 'inventario-color-chip',
        'style' => 'background-color:' . s($color) . ';',
        'title' => s($color),
    ]);
    $table->data[] = [
        format_string($type->name),
        $colorchip . ' ' . s($color),
        implode(', ', $proplabels),
        html_writer::link(new moodle_url($PAGE->url, ['id' => $type->id]), $editicon) . ' ' .
        html_writer::link(new moodle_url($PAGE->url, ['delete' => $type->id, 'sesskey' => sesskey()]), $deleteicon),
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();

