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
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$PAGE->set_url('/local/inventario/types.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('managetypes', 'local_inventario'));
$PAGE->set_heading(get_string('managetypes', 'local_inventario'));
$PAGE->requires->css('/local/inventario/styles.css');

$typeservice = local_inventario_typeservice();
$service = local_inventario_service();
$properties = $service->get_properties();
$form = new local_inventario_type_form($PAGE->url, $properties);
$form->set_data((object)['color' => '#2563eb', 'requiresreturn' => 1, 'requireslocation' => 1]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/inventario/index.php'));
}

if ($delete) {
    require_sesskey();
    $type = $DB->get_record('local_inventario_types', ['id' => $delete], '*', MUST_EXIST);
    if ($confirm) {
        $typeservice->delete_type($delete);
        redirect($PAGE->url, get_string('typedeleted', 'local_inventario'));
    }
    $yesurl = new moodle_url($PAGE->url, ['delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()]);
    $nourl = new moodle_url($PAGE->url);
    echo $OUTPUT->header();
    echo local_inventario_render_nav($context);
    echo $OUTPUT->confirm(
        get_string('confirmdeletetype', 'local_inventario', format_string($type->name)),
        $yesurl,
        $nourl
    );
    echo $OUTPUT->footer();
    exit;
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
    if (!isset($type->requiresreturn)) {
        $type->requiresreturn = 1;
    }
    if (!isset($type->requireslocation)) {
        $type->requireslocation = 1;
    }
    $form->set_data($type);
}

$types = $typeservice->get_types();
$siteoptions = local_inventario_site_options();
$includehidden = local_inventario_can_see_hidden();
$objectslist = $service->get_objects($includehidden);

$propnames = [];
foreach ($properties as $prop) {
    $propnames[$prop->id] = format_string($prop->name);
}
$objectsbytype = [];
foreach ($objectslist as $obj) {
    $propvaluesraw = $service->get_property_values($obj->id);
    $propvalues = [];
    foreach ($propvaluesraw as $propid => $val) {
        $label = $propnames[$propid] ?? ('Property #' . (int)$propid);
        if ($val === '1') {
            $valdisplay = get_string('yes');
        } else if ($val === '0') {
            $valdisplay = get_string('no');
        } else {
            $valdisplay = $val;
        }
        $propvalues[] = $label . ': ' . s($valdisplay);
    }
    $objectsbytype[$obj->typeid][] = [
        'id' => (int)$obj->id,
        'name' => format_string($obj->name),
        'site' => format_string($siteoptions[$obj->siteid] ?? ''),
        'properties' => $propvalues,
        'statsurl' => (new moodle_url('/local/inventario/stats.php', ['objectid' => $obj->id]))->out(false),
        'reservationsurl' => (new moodle_url('/local/inventario/reservations_list.php', ['objectid' => $obj->id]))->out(false),
    ];
}

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

$form->display();

$headers = [
    get_string('typename', 'local_inventario'),
    get_string('typecolor', 'local_inventario'),
    get_string('requiresreturn', 'local_inventario'),
    get_string('requireslocation', 'local_inventario'),
    get_string('typeproperties', 'local_inventario'),
    get_string('objects', 'local_inventario'),
    get_string('actions'),
];
$editicon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
$deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
$chevron = html_writer::span('', 'fa fa-chevron-down ms-2 text-muted');

$rowshtml = '';
foreach ($types as $type) {
    $props = $typeservice->get_type_properties($type->id);
    $proplabels = array_map(static function ($p) {
        return format_string($p->name);
    }, $props);
    $color = !empty($type->color) ? $type->color : '#2563eb';
    $requiresreturn = !empty($type->requiresreturn);
    $requireslocation = !isset($type->requireslocation) ? 1 : (int)$type->requireslocation;
    $requireslabel = $requiresreturn ? get_string('yes') : get_string('no');
    $locationlabel = $requireslocation ? get_string('yes') : get_string('no');
    $colorchip = html_writer::div('', '', [
        'class' => 'inventario-color-chip',
        'style' => 'background-color:' . s($color) . ';',
        'title' => s($color),
    ]);
    $objectlist = $objectsbytype[$type->id] ?? [];
    $objectcount = count($objectlist);

    $rowshtml .= html_writer::start_tag('tr', [
        'class' => 'inventario-type-row',
        'data-typeid' => $type->id,
    ]);
    $rowshtml .= html_writer::tag('td', format_string($type->name) . $chevron);
    $rowshtml .= html_writer::tag('td', $colorchip . ' ' . s($color));
    $rowshtml .= html_writer::tag('td', $requireslabel);
    $rowshtml .= html_writer::tag('td', $locationlabel);
    $rowshtml .= html_writer::tag('td', implode(', ', $proplabels));
    $rowshtml .= html_writer::tag('td', $objectcount);
    $rowshtml .= html_writer::tag(
        'td',
        html_writer::link(new moodle_url($PAGE->url, ['id' => $type->id]), $editicon) . ' ' .
        html_writer::link(new moodle_url($PAGE->url, ['delete' => $type->id, 'sesskey' => sesskey()]), $deleteicon),
        ['class' => 'text-nowrap']
    );
    $rowshtml .= html_writer::end_tag('tr');

    $detailcontent = '';
    if (empty($objectlist)) {
        $detailcontent = html_writer::div(get_string('noobjects', 'local_inventario'), 'text-muted');
    } else {
        $objectcards = [];
        foreach ($objectlist as $obj) {
            $propshtml = empty($obj['properties'])
                ? html_writer::div(get_string('nopropertiesassigned', 'local_inventario'), 'text-muted small')
                : html_writer::alist($obj['properties'], [], 'ul', ['class' => 'small mb-2 text-muted']);
            $infoicon = html_writer::span('', 'fa fa-info-circle text-info', ['aria-hidden' => 'true']);
            $historyicon = $OUTPUT->pix_icon('i/log', get_string('viewhistory', 'local_inventario'));
            $reservationsicon = $OUTPUT->pix_icon('i/calendar', get_string('reservationslist', 'local_inventario'));
            $actions = html_writer::div(
                html_writer::tag('button', $infoicon, [
                    'class' => 'btn btn-secondary btn-sm inventario-object-toggle',
                    'data-target' => 'obj-details-' . $obj['id'],
                    'type' => 'button',
                    'aria-label' => get_string('info'),
                ]) .
                html_writer::link(
                    $obj['statsurl'],
                    $historyicon,
                    ['class' => 'inventario-object-action', 'title' => get_string('viewhistory', 'local_inventario')]
                ) .
                html_writer::link(
                    $obj['reservationsurl'],
                    $reservationsicon,
                    ['class' => 'inventario-object-action', 'title' => get_string('reservationslist', 'local_inventario')]
                ),
                'inventario-object-actions'
            );
            $objectcards[] = html_writer::div(
                html_writer::div(
                    html_writer::div(
                        html_writer::span(format_string($obj['name']), 'fw-semibold') .
                        html_writer::div($obj['site'], 'text-muted small'),
                        'flex-grow-1'
                    ) . $actions,
                    'd-flex justify-content-between align-items-start gap-2'
                ) .
                html_writer::div(
                    $propshtml,
                    'inventario-object-details',
                    ['id' => 'obj-details-' . $obj['id'], 'style' => 'display:none']
                ),
                'inventario-object-card border rounded p-3 mb-2'
            );
        }
        $detailcontent = implode('', $objectcards);
    }

    $rowshtml .= html_writer::tag(
        'tr',
        html_writer::tag('td', $detailcontent, ['colspan' => count($headers)]),
        [
            'class' => 'inventario-type-objects',
            'data-typeid' => $type->id,
            'style' => 'display:none',
        ]
    );
}

$tablehtml = html_writer::start_tag('table', ['class' => 'generaltable fullwidth']);
$tablehtml .= html_writer::start_tag('thead');
$tablehtml .= html_writer::start_tag('tr');
foreach ($headers as $head) {
    $tablehtml .= html_writer::tag('th', $head);
}
$tablehtml .= html_writer::end_tag('tr');
$tablehtml .= html_writer::end_tag('thead');
$tablehtml .= html_writer::tag('tbody', $rowshtml);
$tablehtml .= html_writer::end_tag('table');

echo $tablehtml;

$PAGE->requires->js_init_code("
    const typeRows = document.querySelectorAll('.inventario-type-row');
    const detailRows = document.querySelectorAll('.inventario-type-objects');

    typeRows.forEach(row => {
        row.addEventListener('click', () => {
            const tid = row.dataset.typeid;
            const target = document.querySelector('.inventario-type-objects[data-typeid=\"' + tid + '\"]');
            const isOpen = target && target.style.display === 'table-row';

            detailRows.forEach(r => r.style.display = 'none');
            typeRows.forEach(r => r.classList.remove('open'));

            if (!isOpen && target) {
                target.style.display = 'table-row';
                row.classList.add('open');
            }
        });
    });

    document.querySelectorAll('.inventario-object-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const targetId = btn.dataset.target;
            const panel = document.getElementById(targetId);
            if (!panel) {
                return;
            }
            const isVisible = panel.style.display !== 'none';
            panel.style.display = isVisible ? 'none' : 'block';
        });
    });
");

echo $OUTPUT->footer();
