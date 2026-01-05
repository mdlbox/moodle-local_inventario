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
 * Import CSV data (properties, types, objects) for Inventario.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/import_csv_form.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:manageobjects', $context);

$license = local_inventario_license()->refresh();
if ($license->status !== 'pro' || empty($license->apikey)) {
    throw new moodle_exception('prorequired', 'local_inventario');
}

$exportkind = optional_param('export', '', PARAM_ALPHA);
$kindtemplate = optional_param('template', '', PARAM_ALPHA);
if (!empty($kindtemplate)) {
    local_inventario_output_import_template($kindtemplate);
}
$service = local_inventario_service();
$typeservice = local_inventario_typeservice();
if (!empty($exportkind)) {
    local_inventario_output_export_csv($exportkind, $service, $typeservice);
}

$PAGE->set_url('/local/inventario/import.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('importcsv', 'local_inventario'));
$PAGE->set_heading(get_string('importcsv', 'local_inventario'));
$PAGE->requires->css('/local/inventario/styles.css');

$forms = [
    'properties' => new local_inventario_import_csv_form($PAGE->url, ['kind' => 'properties']),
    'types' => new local_inventario_import_csv_form($PAGE->url, ['kind' => 'types']),
    'objects' => new local_inventario_import_csv_form($PAGE->url, ['kind' => 'objects']),
];

$notifymessages = [];
foreach ($forms as $kind => $form) {
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/inventario/index.php'));
    }
    if ($data = $form->get_data()) {
        $tempfile = $form->save_temp_file('csvfile');
        if (!$tempfile) {
            throw new moodle_exception('nofile');
        }
        switch ($kind) {
            case 'properties':
                $result = $service->import_properties_from_csv($tempfile);
                break;
            case 'types':
                $result = $typeservice->import_types_from_csv($tempfile);
                break;
            case 'objects':
            default:
                $result = $service->import_objects_from_csv($tempfile, $USER->id);
                break;
        }
        $notifymessages[] = get_string('importcsvresult', 'local_inventario', (object)$result);
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                \core\notification::warning($error);
            }
        }
    }
}

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

foreach ($notifymessages as $msg) {
    echo $OUTPUT->notification($msg, 'notifysuccess');
}

echo html_writer::start_div('row g-4');

echo html_writer::start_div('col-md-4');
echo html_writer::tag('h3', get_string('importpropertiescsv', 'local_inventario'));
echo html_writer::div(html_writer::link(
    new moodle_url($PAGE->url, ['template' => 'properties']),
    get_string('downloadtemplate', 'local_inventario'),
    ['class' => 'btn btn-secondary me-2 mb-2']
));
echo html_writer::div(html_writer::link(
    new moodle_url($PAGE->url, ['export' => 'properties']),
    get_string('exportpropertiescsv', 'local_inventario'),
    ['class' => 'btn btn-secondary me-2 mb-2']
));
$forms['properties']->display();
echo html_writer::end_div();

echo html_writer::start_div('col-md-4');
echo html_writer::tag('h3', get_string('importtypescsv', 'local_inventario'));
echo html_writer::div(html_writer::link(
    new moodle_url($PAGE->url, ['template' => 'types']),
    get_string('downloadtemplate', 'local_inventario'),
    ['class' => 'btn btn-secondary me-2 mb-2']
));
echo html_writer::div(html_writer::link(
    new moodle_url($PAGE->url, ['export' => 'types']),
    get_string('exporttypescsv', 'local_inventario'),
    ['class' => 'btn btn-secondary me-2 mb-2']
));
$forms['types']->display();
echo html_writer::end_div();

echo html_writer::start_div('col-md-4');
echo html_writer::tag('h3', get_string('importobjectscsv', 'local_inventario'));
echo html_writer::div(html_writer::link(
    new moodle_url($PAGE->url, ['template' => 'objects']),
    get_string('downloadtemplate', 'local_inventario'),
    ['class' => 'btn btn-secondary me-2 mb-2']
));
echo html_writer::div(html_writer::link(
    new moodle_url($PAGE->url, ['export' => 'objects']),
    get_string('exportobjectscsv', 'local_inventario'),
    ['class' => 'btn btn-secondary me-2 mb-2']
));
$forms['objects']->display();
echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();

/**
 * Output a CSV template for the requested kind and exit.
 *
 * @param string $kind
 * @return void
 */
function local_inventario_output_import_template(string $kind): void {
    $templates = [
        'properties' => [
            ['name', 'shortname', 'datatype', 'options', 'required', 'sortorder', 'parentshortname'],
            ['Capienza', 'capacity', 'number', '', '0', '0', ''],
        ],
        'types' => [
            ['name', 'description', 'color', 'properties'],
            ['Aula Informatica', 'Laboratorio PC', '#2563eb', 'capacity'],
        ],
        'objects' => [
            ['name', 'description', 'type', 'site', 'status', 'visible', 'currentlocation'],
            ['Aula 101', 'Aula magna', 'Aula Informatica', 'Sede 1', 'available', '1', 'Piano terra'],
        ],
    ];

    if (!isset($templates[$kind])) {
        throw new moodle_exception('invalidaction', 'local_inventario');
    }

    $filename = "inventario_{$kind}_template.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    foreach ($templates[$kind] as $row) {
        fputcsv($output, $row, ',', '"', '\\');
    }
    fclose($output);
    exit;
}

/**
 * Export existing data to CSV and exit.
 *
 * @param string $kind
 * @param \local_inventario\local\inventory_service $service
 * @param \local_inventario\local\type_service $typeservice
 * @return void
 */
function local_inventario_output_export_csv(
    string $kind,
    \local_inventario\local\inventory_service $service,
    \local_inventario\local\type_service $typeservice
): void {
    global $DB;

    switch ($kind) {
        case 'properties':
            $rows = [];
            $props = $service->get_properties();
            $shortmap = [];
            foreach ($props as $prop) {
                $shortmap[$prop->id] = $prop->shortname;
            }
            foreach ($props as $prop) {
                $rows[] = [
                    'name' => $prop->name,
                    'shortname' => $prop->shortname,
                    'datatype' => $prop->datatype,
                    'options' => $prop->options,
                    'required' => $prop->required ? 1 : 0,
                    'sortorder' => $prop->sortorder,
                    'parentshortname' => $prop->parentid && isset($shortmap[$prop->parentid]) ? $shortmap[$prop->parentid] : '',
                ];
            }
            $headers = ['name', 'shortname', 'datatype', 'options', 'required', 'sortorder', 'parentshortname'];
            $filename = 'inventario_properties_export.csv';
            break;
        case 'types':
            $rows = [];
            $properties = $DB->get_records_menu('local_inventario_properties', null, '', 'id,shortname');
            $types = $DB->get_records('local_inventario_types', null, 'name ASC');
            $typeprops = $DB->get_records('local_inventario_typeprops', null, '', 'typeid,propertyid');
            $map = [];
            foreach ($typeprops as $tp) {
                $map[$tp->typeid][] = $tp->propertyid;
            }
            foreach ($types as $rec) {
                $propshort = [];
                foreach ($map[$rec->id] ?? [] as $pid) {
                    if (isset($properties[$pid])) {
                        $propshort[] = $properties[$pid];
                    }
                }
                $rows[] = [
                    'name' => $rec->name,
                    'description' => $rec->description,
                    'color' => $rec->color,
                    'properties' => implode(',', $propshort),
                ];
            }
            $headers = ['name', 'description', 'color', 'properties'];
            $filename = 'inventario_types_export.csv';
            break;
        case 'objects':
            $rows = [];
            $sql = "SELECT o.name, o.description, t.name AS typename, s.name AS sitename, o.status, o.visible,
                           o.currentlocation
                      FROM {local_inventario_objects} o
                      JOIN {local_inventario_sites} s ON s.id = o.siteid
                      JOIN {local_inventario_types} t ON t.id = o.typeid
                  ORDER BY o.name ASC";
            $records = $DB->get_records_sql($sql);
            foreach ($records as $rec) {
                $rows[] = [
                    'name' => $rec->name,
                    'description' => $rec->description,
                    'type' => $rec->typename,
                    'site' => $rec->sitename,
                    'status' => $rec->status,
                    'visible' => $rec->visible ? 1 : 0,
                    'currentlocation' => $rec->currentlocation,
                ];
            }
            $headers = ['name', 'description', 'type', 'site', 'status', 'visible', 'currentlocation'];
            $filename = 'inventario_objects_export.csv';
            break;
        default:
            throw new moodle_exception('invalidaction', 'local_inventario');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($output, $row, ',', '"', '\\');
    }
    fclose($output);
    exit;
}
