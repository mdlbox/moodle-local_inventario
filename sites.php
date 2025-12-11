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
 * Manage inventory sites/locations.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/site_form.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:managesites', $context);

$license = local_inventario_license()->refresh();
$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$PAGE->set_url('/local/inventario/sites.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('managesites', 'local_inventario'));
$PAGE->set_heading(get_string('managesites', 'local_inventario'));

$service = local_inventario_service();

if ($delete && confirm_sesskey()) {
    $service->delete_site($delete);
    redirect($PAGE->url, get_string('sitedeleted', 'local_inventario'));
}

$form = new local_inventario_site_form($PAGE->url);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/inventario/index.php'));
}
if ($data = $form->get_data()) {
    $service->save_site($data);
    redirect($PAGE->url, get_string('changessaved'), 0);
}

if ($id) {
    $site = $DB->get_record('local_inventario_sites', ['id' => $id], '*', MUST_EXIST);
    $form->set_data($site);
}

$sites = $service->get_sites();

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

$form->display();
$backurl = new moodle_url('/local/inventario/index.php');
echo html_writer::div(
    html_writer::link($backurl, get_string('pluginname', 'local_inventario'),
        ['class' => 'btn btn-outline-secondary mt-2']),
    'mt-2 mb-3'
);

$table = new html_table();
$table->head = [get_string('site', 'local_inventario'), get_string('actions')];
$table->colclasses = array_fill(0, count($table->head), '');
$table->colclasses[count($table->head) - 1] = 'inventario-actions-col';

foreach ($sites as $site) {
    $deleteurl = new moodle_url($PAGE->url, ['delete' => $site->id, 'sesskey' => sesskey()]);
    $editicon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
    $deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
    $table->data[] = [
        format_string($site->name),
        html_writer::link(new moodle_url($PAGE->url, ['id' => $site->id]), $editicon) . ' ' .
        html_writer::link($deleteurl, $deleteicon),
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();

