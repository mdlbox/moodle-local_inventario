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
 * Manage custom properties for inventory objects.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/property_form.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:manageproperties', $context);

$license = local_inventario_license()->refresh();
$id = optional_param('id', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);

$PAGE->set_url('/local/inventario/properties.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageproperties', 'local_inventario'));
$PAGE->set_heading(get_string('manageproperties', 'local_inventario'));
$PAGE->requires->css('/local/inventario/styles.css');

$form = new local_inventario_property_form($PAGE->url);
$service = local_inventario_service();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/inventario/index.php'));
}

if ($data = $form->get_data()) {
    $service->save_property($data);
    redirect($PAGE->url, get_string('changessaved'), 0);
}

if ($delete && confirm_sesskey()) {
    $service->delete_property($delete);
    redirect($PAGE->url, get_string('changessaved'), 0);
}

if ($id) {
    $property = $DB->get_record('local_inventario_properties', ['id' => $id], '*', MUST_EXIST);
    $form->set_data($property);
}

$properties = $service->get_properties();

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

$form->display();

$parents = [];
$children = [];
foreach ($properties as $prop) {
    if (!empty($prop->parentid)) {
        $children[$prop->parentid][] = $prop;
    } else {
        $parents[] = $prop;
    }
}

$accordion = '';
foreach ($parents as $parent) {
    $parentactions = [
        html_writer::link(new moodle_url($PAGE->url, ['id' => $parent->id]), get_string('edit')),
        html_writer::link(
            new moodle_url($PAGE->url, ['delete' => $parent->id, 'sesskey' => sesskey()]),
            get_string('delete')
        ),
    ];
    $accordion .= html_writer::start_tag('div', ['class' => 'accordion-item mb-2']);
    $accordion .= html_writer::tag(
        'h5',
        html_writer::span(format_string($parent->name), 'accordion-toggle') .
        html_writer::span(
            ' (' . s($parent->datatype) . ') ' . ($parent->required ? get_string('yes') : get_string('no')),
            'text-muted ms-2 small'
        ) .
        html_writer::span(implode(' | ', $parentactions), 'float-end small'),
        ['class' => 'accordion-header p-2 bg-light']
    );

        $accordion .= html_writer::start_div('accordion-body p-2');
    if (!empty($children[$parent->id])) {
        $accordion .= html_writer::start_tag('ul', ['class' => 'list-unstyled ms-3']);
        foreach ($children[$parent->id] as $child) {
            $childactions = [
                html_writer::link(new moodle_url($PAGE->url, ['id' => $child->id]), get_string('edit')),
                html_writer::link(
                    new moodle_url($PAGE->url, ['delete' => $child->id, 'sesskey' => sesskey()]),
                    get_string('delete')
                ),
            ];
            $accordion .= html_writer::tag(
                'li',
                format_string($child->name) . ' (' . s($child->datatype) . ') ' .
                ($child->required ? get_string('yes') : get_string('no')) .
                ' ' . html_writer::span(implode(' | ', $childactions), 'small text-muted'),
                ['class' => 'inventario-child-property']
            );
        }
        $accordion .= html_writer::end_tag('ul');
    } else {
        $accordion .= html_writer::div(get_string('nochildproperties', 'local_inventario'), 'text-muted small');
    }
    $accordion .= html_writer::end_div();
    $accordion .= html_writer::end_tag('div');
}

echo html_writer::div($accordion, 'inventario-accordion');

echo $OUTPUT->footer();
