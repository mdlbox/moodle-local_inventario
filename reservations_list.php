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
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/reservations_list_filters_form.php');

require_login();
$context = context_system::instance();
$canmanageall = has_capability('local/inventario:manageobjects', $context)
    || has_capability('local/inventario:deletereservations', $context);
$canreserve = has_capability('local/inventario:reserve', $context);
if (!$canreserve && !$canmanageall) {
    throw new required_capability_exception($context, 'local/inventario:reserve', 'nopermissions', '');
}

$PAGE->set_url('/local/inventario/reservations_list.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('reservations', 'local_inventario'));
$PAGE->set_heading(get_string('reservations', 'local_inventario'));

$license = local_inventario_license()->refresh();
$service = local_inventario_service();
$includehidden = local_inventario_can_see_hidden();

// Filters (enabled only for PRO).
$search = optional_param('search', '', PARAM_TEXT);
$siteid = optional_param('siteid', 0, PARAM_INT);
$typeid = optional_param('typeid', 0, PARAM_INT);
$propertyid = optional_param('propertyid', 0, PARAM_INT);
$propvalue = optional_param('propvalue', '', PARAM_TEXT);
$objectfilter = optional_param('objectid', 0, PARAM_INT);
$filteruserid = optional_param('userid', 0, PARAM_INT);
$perpage = optional_param('perpage', 10, PARAM_INT);
$resetfilters = optional_param('resetfilter', '', PARAM_TEXT);
$startfrom = 0;
$endto = 0;
$allowedperpage = [10, 20, 50, 100, 0];
if (!in_array($perpage, $allowedperpage, true)) {
    $perpage = 10;
}
if (!$canmanageall) {
    $filteruserid = (int)$USER->id;
}
if ($resetfilters !== '') {
    redirect(new moodle_url('/local/inventario/reservations_list.php'));
}

$siteoptions = local_inventario_site_options();
$typeoptions = local_inventario_type_options();
$propertyoptions = array_map(static fn($p) => format_string($p->name), $service->get_properties());
$useroptions = $canmanageall ? local_inventario_user_options() : [(int)$USER->id => fullname($USER)];

$filterform = null;
if ($license->status === 'pro') {
    $perpageoptions = [];
    foreach ($allowedperpage as $opt) {
        $perpageoptions[$opt] = ($opt === 0) ? get_string('all', 'local_inventario') : (string)$opt;
    }

    $filterform = new local_inventario_reservations_list_filters_form(
        new moodle_url('/local/inventario/reservations_list.php'),
        [
            'siteoptions' => $siteoptions,
            'typeoptions' => $typeoptions,
            'propertyoptions' => $propertyoptions,
            'useroptions' => $useroptions,
            'canmanageall' => $canmanageall,
            'currentuserid' => (int)$USER->id,
            'perpageoptions' => $perpageoptions,
            'objectid' => $objectfilter,
        ],
        'get',
        '',
        ['class' => 'mform inventario-filters']
    );

    if ($formdata = $filterform->get_data()) {
        $search = trim((string)($formdata->search ?? ''));
        $siteid = (int)($formdata->siteid ?? 0);
        $typeid = (int)($formdata->typeid ?? 0);
        $propertyid = (int)($formdata->propertyid ?? 0);
        $propvalue = trim((string)($formdata->propvalue ?? ''));
        $objectfilter = (int)($formdata->objectid ?? $objectfilter);
        $perpage = (int)($formdata->perpage ?? $perpage);
        if (!in_array($perpage, $allowedperpage, true)) {
            $perpage = 10;
        }
        if ($canmanageall) {
            $filteruserid = (int)($formdata->userid ?? 0);
        } else {
            $filteruserid = (int)$USER->id;
        }

        $startfromday = !empty($formdata->startfrom) ? (int)$formdata->startfrom : 0;
        $endtody = !empty($formdata->endto) ? (int)$formdata->endto : 0;
        $startfrom = $startfromday;
        $endto = $endtody ? ($endtody + DAYSECS - 1) : 0;
    }

    $filterdefaults = (object)[
        'search' => $search,
        'siteid' => $siteid,
        'typeid' => $typeid,
        'propertyid' => $propertyid,
        'propvalue' => $propvalue,
        'userid' => $filteruserid,
        'perpage' => $perpage,
        'objectid' => $objectfilter,
    ];
    if ($startfrom > 0) {
        $filterdefaults->startfrom = $startfrom;
    }
    if ($endto > 0) {
        $filterdefaults->endto = $endto - DAYSECS + 1;
    }
    $filterform->set_data($filterdefaults);
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
if (!$canmanageall) {
    $filters['userid'] = (int)$USER->id;
}

$reservations = $service->get_reservations($filters, $includehidden);

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

// Action buttons.
$actionbuttons = [];
if ($canreserve) {
    $addurl = new moodle_url('/local/inventario/reservations.php');
    $actionbuttons[] = html_writer::link(
        $addurl,
        get_string('addreservation', 'local_inventario'),
        ['class' => 'btn btn-primary']
    );
}
if (!empty($actionbuttons)) {
    echo html_writer::div(implode(' ', $actionbuttons), 'd-flex justify-content-end gap-2 mb-3');
}

// Filters (only PRO).
if ($license->status === 'pro') {
    if ($filterform) {
        echo local_inventario_render_filter_card($filterform->render());
    }
}

// Prepare tooltip data for object properties.
$objectprops = [];
if (!empty($reservations)) {
    global $DB;
    $objectids = array_unique(array_map(static fn($r) => (int)$r->objectid, $reservations));
    if (!empty($objectids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($objectids, SQL_PARAMS_NAMED);
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
            $objectprops[$pv->objectid][] = s(format_string($pv->name)) . ': ' . s($val);
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
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('object', 'local_inventario') . ': ') . $objectcell
        );
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('site', 'local_inventario') . ': ') .
            format_string($reservation->sitename)
        );
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('type', 'local_inventario') . ': ') .
            format_string($typebyid[$reservation->typeid] ?? '')
        );
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('user') . ': ') . s(fullname($reservation))
        );
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('period', 'local_inventario') . ': ') .
            userdate($reservation->timestart) . ' â†’ ' . userdate($reservation->timeend)
        );
        if (!empty($reservation->location)) {
            $detailparts[] = html_writer::div(
                html_writer::tag('strong', get_string('location', 'local_inventario') . ': ') .
                format_string($reservation->location)
            );
        }
        if (!empty($proplines)) {
            $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('properties', 'local_inventario')));
            $detailparts[] = html_writer::alist($proplines);
        }
        $detailhtml = implode('', $detailparts);
        $detailblocks[$reservation->objectid] = html_writer::div(
            $detailhtml,
            'd-none',
            ['id' => 'inventario-res-detail-' . $reservation->objectid]
        );
        $seenobjects[$reservation->objectid] = true;
    }

    $actions = html_writer::link(
        '#',
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
        s(fullname($reservation)),
        format_string($reservation->sitename),
        userdate($reservation->timestart) . ' â†’ ' . userdate($reservation->timeend),
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
echo html_writer::tag(
    'div',
    html_writer::tag(
        'div',
        html_writer::tag(
            'div',
            html_writer::tag('div', '', ['class' => 'modal-header', 'id' => 'inventario-res-modal-title']) .
            html_writer::tag('div', '', ['class' => 'modal-body', 'id' => 'inventario-res-modal-body']) .
            html_writer::tag(
                'div',
                html_writer::tag('button', get_string('closebuttontitle'), [
                    'type' => 'button',
                    'class' => 'btn btn-secondary',
                    'data-bs-dismiss' => 'modal',
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

$PAGE->requires->js_call_amd('local_inventario/reslistmodal', 'init', [get_string('info')]);

echo $OUTPUT->footer();
