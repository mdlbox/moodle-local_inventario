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
 * Add/edit staff absence entries.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/absence_form.php');

use local_inventario\local\absence_service;

require_login();

$context = context_system::instance();
$license = local_inventario_license();
$license->require_pro();

$canmanage = has_capability('local/inventario:manageabsences', $context);
$canreserveabsence = has_capability('local/inventario:addabsence', $context);
if (!$canmanage && !$canreserveabsence) {
    throw new required_capability_exception($context, 'local/inventario:addabsence', 'nopermissions', '');
}

$service = new absence_service();
$canmanageothers = $service->is_absence_admin($USER->id);
$absencesurl = new moodle_url('/local/inventario/absences.php');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $service->delete_absence($id, $USER->id);
    redirect($absencesurl, get_string('absencedeleted', 'local_inventario'));
}

$types = $service->get_types();
$useroptions = $service->get_booking_user_options();
$substitutes = $service->get_substitute_user_options();
$subjects = $service->get_subject_suggestions();
$formurl = new moodle_url('/local/inventario/absence.php', ['id' => $id]);

$PAGE->set_url($formurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('absence_add', 'local_inventario'));
$PAGE->set_heading(get_string('absence_add', 'local_inventario'));

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

$data = new stdClass();
$data->id = $id;
$data->userid = $USER->id;

if ($id) {
    $record = $service->get_absence($id);
    if (!$record || !$service->can_manage_entry($record, $USER->id)) {
        throw new moodle_exception('invalidabsencedata', 'local_inventario');
    }
    if ($record->timeend < time()) {
        echo $OUTPUT->notification(get_string('absencepastedit', 'local_inventario'), 'error');
    }
    $data->userid = $record->userid;
    $data->typeid = $record->typeid;
    $data->subject = $record->subject;
    $data->timestart = $record->timestart;
    $data->timeend = $record->timeend;
    $data->substituteuserid = $record->substituteuserid;
    $data->substitutename = $record->substitutename;
    $data->comment = $record->comment;
}

$form = new local_inventario_absence_form(
    $formurl,
    $types,
    $useroptions,
    $substitutes,
    $subjects,
    $data,
    $canmanageothers,
    $USER->id
);

if ($form->is_cancelled()) {
    redirect($absencesurl);
}

if ($formdata = $form->get_data()) {
    $service->save_absence($formdata, $USER->id);
    redirect($absencesurl, get_string('absencesaved', 'local_inventario'));
}

if (empty($types)) {
    echo $OUTPUT->notification(get_string('absencenotypes', 'local_inventario'), 'warning');
} else {
    $form->display();
}

echo $OUTPUT->footer();
