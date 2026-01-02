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
 * Manage inventory objects.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/object_form.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:manageobjects', $context);

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$toggleid = optional_param('toggle', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$typeidparam = optional_param('typeid', -1, PARAM_INT);

$PAGE->set_url('/local/inventario/objects.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageobjects', 'local_inventario'));
$PAGE->set_heading(get_string('manageobjects', 'local_inventario'));
$PAGE->requires->css('/local/inventario/styles.css');

$licensemanager = local_inventario_license();
$licensestatus = $licensemanager->refresh();
$allowhidden = $licensemanager->is_feature_enabled('hidden');
$typeservice = local_inventario_typeservice();
$typeoptions = local_inventario_type_options();
$service = local_inventario_service();

if (empty($typeoptions)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notypesdefined', 'local_inventario'), 'warning');
    echo $OUTPUT->single_button(new moodle_url('/local/inventario/types.php'), get_string('managetypes', 'local_inventario'));
    echo $OUTPUT->footer();
    exit;
}

if ($delete && confirm_sesskey()) {
    local_inventario_service()->delete_object($delete);
    $redirect = new moodle_url('/local/inventario/objects.php');
    redirect($redirect, get_string('objectdeleted', 'local_inventario'));
}

if ($toggleid && confirm_sesskey()) {
    local_inventario_service()->toggle_visibility($toggleid, $action !== 'hide');
    redirect($PAGE->url, get_string('visibletoggled', 'local_inventario'));
}

$typeid = ($typeidparam > 0) ? $typeidparam : 0;
$record = null;
if ($id) {
    $record = $DB->get_record('local_inventario_objects', ['id' => $id], '*', MUST_EXIST);
    // In edit mode always respect the object's current type to avoid accidental override.
    $typeid = (int)$record->typeid;
}
if (!$typeid && !empty($typeoptions)) {
    $typeid = (int)array_key_first($typeoptions);
}
$properties = $typeid ? $typeservice->get_type_properties($typeid) : [];
$siteoptions = local_inventario_site_options();
$PAGE->set_url('/local/inventario/objects.php', ['id' => $id ?: null, 'typeid' => $typeid ?: null]);
$baseurl = (new moodle_url('/local/inventario/objects.php', ['id' => $id ?: null]))->out(false);
$form = new local_inventario_object_form(
    $PAGE->url->out(false),
    $siteoptions,
    $typeoptions,
    $allowhidden,
    $properties,
    $baseurl,
    $licensemanager->is_pro()
);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/inventario/index.php'));
}

if ($data = $form->get_data()) {
    $objectid = $service->save_object($data, $USER->id);
    $values = [];
    $propsfortype = $typeservice->get_type_properties((int)$data->typeid);
    foreach ($propsfortype as $property) {
        $key = 'prop_' . $property->id;
        if (property_exists($data, $key)) {
            $values[$property->id] = $data->$key;
        }
    }
    if ($values) {
        $service->save_property_values($objectid, $values);
    }
    redirect(new moodle_url('/local/inventario/objects.php'), get_string('changessaved'), 0);
}

if ($record) {
    $propvalues = $service->get_property_values($id);
    foreach ($propvalues as $propid => $value) {
        $record->{'prop_' . $propid} = $value;
    }
    $record->typeid = $typeid;
    $record->availableperiodenabled = isset($record->availableperiodenabled) ? (int)$record->availableperiodenabled : 0;
    $record->availablefrom = $record->availablefrom ?? 0;
    $record->availableto = $record->availableto ?? 0;
    $record->availabletimes = $record->availabletimes ?? '';
    $form->set_data($record);
} else {
    $form->set_data([
        'siteid' => $siteoptions ? array_key_first($siteoptions) : 0,
        'typeid' => $typeid,
        'availableperiodenabled' => 0,
        'availablefrom' => 0,
        'availableto' => 0,
        'availabletimes' => '',
    ]);
}

$objects = $service->get_objects(true);

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

$form->display();

$table = new html_table();
$table->head = [
    get_string('object', 'local_inventario'),
    get_string('type', 'local_inventario'),
    get_string('site', 'local_inventario'),
    get_string('status', 'local_inventario'),
    get_string('visibility', 'local_inventario'),
];
if ($licensestatus->status === 'pro') {
    $table->head[] = get_string('history', 'local_inventario');
}
$table->head[] = get_string('actions');
$table->colclasses = array_fill(0, count($table->head), '');
$table->colclasses[count($table->head) - 1] = 'inventario-actions-col';

foreach ($objects as $object) {
    $togglelabel = $object->visible ? get_string('hide', 'local_inventario') : get_string('show', 'local_inventario');
    $toggleurl = new moodle_url($PAGE->url, [
        'toggle' => $object->id,
        'action' => $object->visible ? 'hide' : 'show',
        'sesskey' => sesskey(),
    ]);
    $deleteurl = new moodle_url($PAGE->url, [
        'delete' => $object->id,
        'sesskey' => sesskey(),
    ]);
    $hasactive = $service->has_active_reservation_now($object->id);
    $currentlyavailable = $service->is_available_now($object);
    if (!$currentlyavailable && empty($hasactive)) {
        $statuskey = 'unavailable';
        $statuslabel = get_string('status_unavailable', 'local_inventario');
        $statusclass = 'badge bg-secondary text-white';
    } else {
        $statuskey = $hasactive ? $object->status : 'available';
        $statuslabel = get_string('status_' . $statuskey, 'local_inventario');
        $statusclass = $statuskey === 'available' ? 'badge bg-success text-white' : 'badge bg-danger text-white';
    }
    $row = [
        format_string($object->name),
        format_string($typeoptions[$object->typeid] ?? ''),
        format_string($siteoptions[$object->siteid] ?? ''),
        html_writer::span($statuslabel, $statusclass),
        $object->visible ? get_string('visible', 'local_inventario') : get_string('hidden', 'local_inventario'),
    ];
    if ($licensestatus->status === 'pro') {
        $historyurl = new moodle_url('/local/inventario/stats.php', ['objectid' => $object->id]);
        $historyicon = $OUTPUT->pix_icon('i/log', get_string('history', 'local_inventario'));
        $row[] = html_writer::link($historyurl, $historyicon, ['title' => get_string('viewhistory', 'local_inventario')]);
    }
    $editicon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
    $toggleicon = $OUTPUT->pix_icon('t/hide', $togglelabel);
    $showicon = $OUTPUT->pix_icon('t/show', $togglelabel);
    $deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));

    $actions = [];
    $actions[] = html_writer::link(new moodle_url($PAGE->url, ['id' => $object->id]), $editicon);
    if ($allowhidden) {
        $actions[] = html_writer::link($toggleurl, $object->visible ? $toggleicon : $showicon);
    } else {
        $actions[] = get_string('proonly', 'local_inventario');
    }
    $actions[] = html_writer::link(
        $deleteurl,
        $deleteicon,
        ['onclick' => "return confirm('" . get_string('confirmdeleteobject', 'local_inventario') . "');"]
    );
    $row[] = implode(' ', $actions);
    $table->data[] = $row;
}

echo html_writer::table($table);

echo $OUTPUT->footer();
