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
 * List reservations with optional filters.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_login();
$context = context_system::instance();

$PAGE->set_url('/local/inventario/reservations_list.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('reservations', 'local_inventario'));
$PAGE->set_heading(get_string('reservations', 'local_inventario'));
$PAGE->requires->css('/local/inventario/styles.css');

$license = local_inventario_license()->refresh();
$service = local_inventario_service();
$includehidden = local_inventario_can_see_hidden();
$canreserve = has_capability('local/inventario:reserve', $context);
$canmanageobjects = has_capability('local/inventario:manageobjects', $context);

// Filters (enabled only for PRO).
$search = optional_param('search', '', PARAM_RAW_TRIMMED);
$siteid = optional_param('siteid', 0, PARAM_INT);
$typeid = optional_param('typeid', 0, PARAM_INT);
$propertyid = optional_param('propertyid', 0, PARAM_INT);
$propvalue = optional_param('propvalue', '', PARAM_RAW_TRIMMED);
$objectfilter = optional_param('objectid', 0, PARAM_INT);
$startfrom = optional_param('startfrom', 0, PARAM_INT);
$endto = optional_param('endto', 0, PARAM_INT);
$filteruserid = optional_param('userid', 0, PARAM_INT);
$perpage = optional_param('perpage', 10, PARAM_INT);
$allowedperpage = [10, 20, 50, 100, 0];
if (!in_array($perpage, $allowedperpage, true)) {
    $perpage = 10;
}

$filters = [];
if ($license->status === 'pro') {
    if ($search !== '') {
        $filters['search'] = $search;
    }
    if ($siteid) {
        $filters['siteid'] = $siteid;
    }
    if ($typeid) {
        $filters['typeid'] = $typeid;
    }
    if ($propertyid) {
        $filters['propertyid'] = $propertyid;
    }
    if ($propvalue !== '') {
        $filters['propvalue'] = $propvalue;
    }
    if ($filteruserid) {
        $filters['userid'] = $filteruserid;
    }
    if ($objectfilter) {
        $filters['objectid'] = $objectfilter;
    }
    if ($startfrom) {
        $filters['startfrom'] = $startfrom;
    }
    if ($endto) {
        $filters['endto'] = $endto;
    }
}

$reservations = $service->get_reservations($filters, $includehidden);
$siteoptions = local_inventario_site_options();
$typeoptions = local_inventario_type_options();
$propertyoptions = array_map(static fn($p) => format_string($p->name), $service->get_properties());
$useroptions = local_inventario_user_options();

echo $OUTPUT->header();
echo local_inventario_render_nav($context);
$PAGE->requires->js_call_amd('core/tooltip', 'init');

// Action buttons.
$actionbuttons = [];
if ($canreserve) {
    $addurl = new moodle_url('/local/inventario/reservations.php');
    $actionbuttons[] = html_writer::link($addurl, get_string('addreservation', 'local_inventario'),
        ['class' => 'btn btn-primary']);
}
if (!empty($actionbuttons)) {
    echo html_writer::div(implode(' ', $actionbuttons), 'd-flex justify-content-end gap-2 mb-3');
}

// Filters (only PRO).
if ($license->status === 'pro') {
    echo html_writer::start_div('card mb-3 shadow-sm');
    echo html_writer::start_div('card-body');
    $formurl = new moodle_url($PAGE->url);
    $formattrs = ['method' => 'get', 'action' => $formurl->out(false), 'class' => 'mform'];
    echo html_writer::start_tag('form', $formattrs);
    echo html_writer::tag('legend', get_string('filters', 'local_inventario'));

    echo html_writer::start_div('d-flex justify-content-end');
    echo html_writer::start_tag('table', [
        'class' => 'generaltable boxaligncenter inventario-filter-table',
    ]);
    // Row 1: search + site.
    echo html_writer::start_tag('tr', ['class' => 'inventario-filter-row']);
    echo html_writer::tag('th', html_writer::label(get_string('search'), 'search'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'search',
        'id' => 'search',
        'value' => $search,
        'placeholder' => get_string('search'),
        'class' => 'form-control',
    ]));
    echo html_writer::tag('th', html_writer::label(get_string('site', 'local_inventario'), 'siteid'));
    echo html_writer::tag('td', html_writer::select(
        [0 => get_string('all', 'local_inventario')] + $siteoptions,
        'siteid',
        $siteid,
        null,
        ['id' => 'siteid', 'class' => 'custom-select']
    ));
    echo html_writer::end_tag('tr');

    // Row 2: type + property.
    echo html_writer::start_tag('tr', ['class' => 'inventario-filter-row']);
    echo html_writer::tag('th', html_writer::label(get_string('type', 'local_inventario'), 'typeid'));
    echo html_writer::tag('td', html_writer::select(
        [0 => get_string('all', 'local_inventario')] + $typeoptions,
        'typeid',
        $typeid,
        null,
        ['id' => 'typeid', 'class' => 'custom-select']
    ));
    echo html_writer::tag('th', html_writer::label(get_string('property', 'local_inventario'), 'propertyid'));
    echo html_writer::tag('td', html_writer::select(
        [0 => get_string('all', 'local_inventario')] + $propertyoptions,
        'propertyid',
        $propertyid,
        null,
        ['id' => 'propertyid', 'class' => 'custom-select']
    ));
    echo html_writer::end_tag('tr');

    // Row 3: property value + user.
    echo html_writer::start_tag('tr', ['class' => 'inventario-filter-row']);
    echo html_writer::tag('th', html_writer::label(get_string('propertyvalue', 'local_inventario'), 'propvalue'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'propvalue',
        'id' => 'propvalue',
        'value' => $propvalue,
        'class' => 'form-control',
    ]));
    echo html_writer::tag('th', html_writer::label(get_string('user'), 'userid'));
    echo html_writer::tag('td', html_writer::select(
        [0 => get_string('all', 'local_inventario')] + $useroptions,
        'userid',
        $filteruserid,
        null,
        ['id' => 'userid', 'class' => 'custom-select']
    ));
    echo html_writer::end_tag('tr');

    // Row 4: date range.
    echo html_writer::start_tag('tr', ['class' => 'inventario-filter-row']);
    echo html_writer::tag('th', html_writer::label(get_string('starttime', 'local_inventario'), 'startfrom'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'date',
        'name' => 'startfrom',
        'id' => 'startfrom',
        'value' => $startfrom ? date('Y-m-d', $startfrom) : '',
        'class' => 'form-control',
    ]));
    echo html_writer::tag('th', html_writer::label(get_string('endtime', 'local_inventario'), 'endto'));
    echo html_writer::tag('td', html_writer::empty_tag('input', [
        'type' => 'date',
        'name' => 'endto',
        'id' => 'endto',
        'value' => $endto ? date('Y-m-d', $endto) : '',
        'class' => 'form-control',
    ]));
    echo html_writer::end_tag('tr');

    // Submit row.
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', '', ['colspan' => 2]);
    // Per-page selector inline with buttons.
    $optionshtml = '';
    foreach ($allowedperpage as $opt) {
        $attrs = ['value' => $opt];
        if ((int)$opt === (int)$perpage) {
            $attrs['selected'] = 'selected';
        }
        $label = $opt === 0 ? get_string('all', 'local_inventario') : $opt;
        $optionshtml .= html_writer::tag('option', $label, $attrs);
    }
    $controls = html_writer::tag('label', get_string('itemsperpage', 'local_inventario'), ['for' => 'perpage', 'class' => 'me-2 mb-0']) .
        html_writer::tag('select', $optionshtml, ['name' => 'perpage', 'id' => 'perpage', 'class' => 'custom-select w-auto me-2']);

    $submitcell = $controls;
    $submitcell .= html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('filter', 'local_inventario'),
        'class' => 'btn btn-primary me-2',
    ]);
    // Reset filters.
    $reseturl = new moodle_url($PAGE->url);
    $submitcell .= html_writer::link($reseturl, get_string('resetfilters', 'local_inventario'),
        ['class' => 'btn btn-secondary']);
    if ($objectfilter) {
        $submitcell .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'objectid',
            'value' => $objectfilter,
        ]);
    }
    if ($startfrom) {
        $submitcell .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'startfrom',
            'value' => $startfrom,
        ]);
    }
    if ($endto) {
        $submitcell .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'endto',
            'value' => $endto,
        ]);
    }
    echo html_writer::tag('td', $submitcell, ['colspan' => 2]);
    echo html_writer::end_tag('tr');

    echo html_writer::end_tag('table');
    echo html_writer::end_div(); // end align right wrapper.
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Prepare tooltip data for object properties.
$objectprops = [];
if (!empty($reservations)) {
    global $DB;
    $objectids = array_unique(array_map(static fn($r) => (int)$r->objectid, $reservations));
    if (!empty($objectids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($objectids, SQL_PARAMS_NAMED);
        $propvals = $DB->get_records_sql(
            "SELECT pv.objectid, p.name, pv.value
               FROM {local_inventario_propvals} pv
               JOIN {local_inventario_properties} p ON p.id = pv.propertyid
              WHERE pv.objectid {$insql}
           ORDER BY p.parentid ASC, p.sortorder ASC, p.name ASC",
            $inparams
        );
        foreach ($propvals as $pv) {
            $val = $pv->value;
            if ($val === '1') {
                $val = get_string('yes');
            } else if ($val === '0') {
                $val = get_string('no');
            }
            $objectprops[$pv->objectid][] = format_string($pv->name) . ': ' . s($val);
        }
    }
}

// Table.
$table = new html_table();
$table->head = [
    get_string('object', 'local_inventario'),
    get_string('type', 'local_inventario'),
    get_string('user'),
    get_string('site', 'local_inventario'),
    get_string('period', 'local_inventario'),
    get_string('location', 'local_inventario'),
    get_string('actions'),
];
$table->colclasses = array_fill(0, count($table->head), '');
$table->colclasses[count($table->head) - 1] = 'inventario-actions-col';

$typebyid = $typeoptions;
$propertynames = $service->get_properties();
$detailblocks = [];
$seenobjects = [];

foreach ($reservations as $reservation) {
    $objectcell = format_string($reservation->objectname);
    $propvals = $objectprops[$reservation->objectid] ?? [];
    // Build detail HTML once per object.
    if (!isset($seenobjects[$reservation->objectid])) {
        $proplines = [];
        foreach ($propvals as $prop) {
            $proplines[] = $prop;
        }
        $detailparts = [];
        $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('object', 'local_inventario') . ': ') . $objectcell);
        $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('site', 'local_inventario') . ': ') . format_string($reservation->sitename));
        $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('type', 'local_inventario') . ': ') . format_string($typebyid[$reservation->typeid] ?? ''));
        $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('user') . ': ') . fullname($reservation));
        $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('period', 'local_inventario') . ': ') .
            userdate($reservation->timestart) . ' - ' . userdate($reservation->timeend));
        if (!empty($reservation->location)) {
            $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('location', 'local_inventario') . ': ') .
                format_string($reservation->location));
        }
        if (!empty($proplines)) {
            $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('properties', 'local_inventario')));
            $detailparts[] = html_writer::alist($proplines);
        }
        $detailhtml = implode('', $detailparts);
        $detailblocks[$reservation->objectid] = html_writer::div($detailhtml, 'd-none', ['id' => 'inventario-res-detail-' . $reservation->objectid]);
        $seenobjects[$reservation->objectid] = true;
    }

    $actions = html_writer::link('#',
        html_writer::span('', 'fa fa-info-circle text-info', ['aria-hidden' => 'true']) .
        html_writer::span(get_string('info'), 'sr-only'),
        [
            'class' => 'inventario-res-info',
            'data-detail-id' => 'inventario-res-detail-' . $reservation->objectid,
            'data-detail-title' => $objectcell,
        ]
    );

    $table->data[] = [
        $objectcell,
        format_string($typebyid[$reservation->typeid] ?? ''),
        fullname($reservation),
        format_string($reservation->sitename),
        userdate($reservation->timestart) . ' - ' . userdate($reservation->timeend),
        format_string($reservation->location),
        $actions,
    ];
}

if ($perpage > 0 && !empty($table->data) && count($table->data) > $perpage) {
    $table->data = array_slice($table->data, 0, $perpage);
}

echo html_writer::table($table);

// Hidden detail blocks for modals.
if (!empty($detailblocks)) {
    echo implode('', $detailblocks);
}

// Info modal + JS (reuse simple jQuery logic).
echo html_writer::tag('div',
    html_writer::tag('div',
        html_writer::tag('div',
            html_writer::tag('div', '', ['class' => 'modal-header', 'id' => 'inventario-res-modal-title']) .
            html_writer::tag('div', '', ['class' => 'modal-body', 'id' => 'inventario-res-modal-body']) .
            html_writer::tag('div',
                html_writer::tag('button', get_string('closebuttontitle'), [
                    'type' => 'button',
                    'class' => 'btn btn-secondary',
                    'data-dismiss' => 'modal',
                ]),
                ['class' => 'modal-footer']
            ),
            ['class' => 'modal-content']
        ),
        ['class' => 'modal-dialog modal-lg', 'role' => 'document']
    ),
    ['class' => 'modal fade', 'id' => 'inventario-res-modal', 'tabindex' => '-1', 'role' => 'dialog', 'aria-hidden' => 'true']
);

$PAGE->requires->js_init_code("
require(['jquery'], function($) {
    $(document).on('click', '.inventario-res-info', function(e) {
        e.preventDefault();
        const targetId = $(this).data('detail-id');
        const title = $(this).data('detail-title') || '';
        const content = $('#' + targetId).html() || '';
        $('#inventario-res-modal-title').text(title || '" . get_string('info') . "');
        $('#inventario-res-modal-body').html(content);
        $('#inventario-res-modal').modal('show');
    });
});
");

echo $OUTPUT->footer();

