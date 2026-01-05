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
 * Create or edit reservations for inventory objects.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/reservation_form.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:reserve', $context);

$license = local_inventario_license()->refresh();
$id = optional_param('id', 0, PARAM_INT);
$returnid = optional_param('return', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$sitefilter = optional_param('siteid', 0, PARAM_INT);
$focus = optional_param('focus', 0, PARAM_INT);
$objectidparam = optional_param('objectid', 0, PARAM_INT);
$resperpage = optional_param('resperpage', 10, PARAM_INT);
$allowedperpage = [10, 20, 50, 100, 0];
if (!in_array($resperpage, $allowedperpage, true)) {
    $resperpage = 10;
}
$pageparams = [
    'id' => $id ?: null,
    'siteid' => $sitefilter ?: null,
    'objectid' => $objectidparam ?: null,
    'resperpage' => $resperpage,
    'focus' => $focus ?: null,
];
$pageparams = array_filter($pageparams, static function ($value) {
    return $value !== null && $value !== '';
});
$PAGE->set_url('/local/inventario/reservations.php', $pageparams);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reservations', 'local_inventario'));
$PAGE->set_heading(get_string('reservations', 'local_inventario'));
$PAGE->requires->css('/local/inventario/styles.css');

$canmanageall = has_capability('local/inventario:deletereservations', $context);
$includehidden = local_inventario_can_see_hidden();
$service = local_inventario_service();

// Periodic reservations allowed only if setting and Pro.
$allowperiodic = (bool)get_config('local_inventario', 'allowperiodic') && local_inventario_feature_enabled('periodic');
$overlaperror = '';
$expiredwarning = '';

if ($returnid && confirm_sesskey()) {
    $service->return_reservation($returnid, $USER->id, $canmanageall);
    redirect($PAGE->url, get_string('reservationreturned', 'local_inventario'));
}

if ($delete && confirm_sesskey()) {
    $service->delete_reservation($delete, $USER->id, $canmanageall);
    redirect($PAGE->url, get_string('reservationdeleted', 'local_inventario'));
}

$siteoptions = local_inventario_site_options();
$selectedsite = $sitefilter;
if (!$selectedsite) {
    $selectedsite = (int)get_user_preferences('local_inventario_last_siteid', 0);
}
$prefetchedobject = null;
$record = null;
if ($id) {
    $record = $DB->get_record('local_inventario_reserv', ['id' => $id], '*', MUST_EXIST);
    if (!$canmanageall && $record->userid != $USER->id) {
        throw new moodle_exception('notyours', 'local_inventario');
    }
    if ($record->timeend <= time()) {
        $expiredwarning = get_string('cannoteditexpiredreservation', 'local_inventario');
        $focus = $focus ?: $record->id;
        $selectedsite = $selectedsite ?: $record->siteid;
        $id = 0;
        $record = null;
        $pageparams['id'] = null;
        $pageparams['focus'] = $focus ?: null;
        if ($selectedsite) {
            $pageparams['siteid'] = $selectedsite;
        }
        $pageparams = array_filter($pageparams, static function ($value) {
            return $value !== null && $value !== '';
        });
        $PAGE->set_url('/local/inventario/reservations.php', $pageparams);
    } else if (!$selectedsite) {
        $selectedsite = $record->siteid;
    }
} else if ($objectidparam) {
    $prefetchedobject = $DB->get_record('local_inventario_objects', ['id' => $objectidparam], '*', IGNORE_MISSING);
    if ($prefetchedobject && ($includehidden || $prefetchedobject->visible)) {
        $selectedsite = $prefetchedobject->siteid;
    }
}
if (!$selectedsite && !empty($siteoptions)) {
    $selectedsite = array_key_first($siteoptions);
}

$objectoptions = [];
$objectmetadata = [];
$typesbyid = local_inventario_typeservice()->get_types();
if ($selectedsite) {
    $objs = $service->get_objects($includehidden, $selectedsite);
    foreach ($objs as $obj) {
        $objectoptions[$obj->id] = format_string($obj->name);
        $type = $typesbyid[$obj->typeid] ?? null;
        $objectmetadata[$obj->id] = [
            'typeid' => (int)$obj->typeid,
            'requireslocation' => $type && property_exists($type, 'requireslocation')
                ? (int)$type->requireslocation
                : 1,
        ];
    }
}
$unreturnedids = array_values(array_intersect(
    $service->get_unreturned_object_ids(),
    array_map('intval', array_keys($objectoptions))
));
$useroptions = $canmanageall ? local_inventario_user_options() : [];

$form = new local_inventario_reservation_form(
    $PAGE->url->out(false),
    $objectoptions,
    $siteoptions,
    $useroptions,
    $canmanageall,
    $allowperiodic,
    $objectmetadata
);
$PAGE->requires->js_call_amd('local_inventario/reservations', 'init', [
    $PAGE->url->out(false),
    $id,
    $unreturnedids,
    $objectmetadata,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/inventario/index.php'));
}

if ($data = $form->get_data()) {
    try {
        set_user_preference('local_inventario_last_siteid', (int)$data->siteid);
        $service->save_reservation($data, $USER->id, $canmanageall);
        redirect($PAGE->url, get_string('changessaved'), 0);
    } catch (moodle_exception $e) {
        if ($e->errorcode === 'overlap') {
            $overlaperror = $e->getMessage();
            $form->set_data($data);
        } else {
            throw $e;
        }
    }
}

if ($record) {
    $form->set_data($record);
} else {
    $form->set_data([
        'siteid' => $selectedsite,
        'objectid' => ($objectidparam && isset($objectoptions[$objectidparam])) ? $objectidparam : null,
    ]);
}

$selectedobject = 0;
if ($record) {
    $selectedobject = (int)$record->objectid;
} else if ($objectidparam && isset($objectoptions[$objectidparam])) {
    $selectedobject = $objectidparam;
} else if (!empty($objectoptions)) {
    $selectedobject = (int)array_key_first($objectoptions);
}
$hasactivereservationnow = $selectedobject ? $service->has_active_reservation_now($selectedobject) : false;
$filters = $canmanageall ? [] : ['userid' => $USER->id];
$reservations = $service->get_reservations($filters, $includehidden);
$now = time();

// Choose per-object candidate for the "return" action:
// - If there is an active (non-returned) reservation, it wins.
// - Otherwise, pick the latest expired (non-returned) reservation.
$returncandidates = [];
foreach ($reservations as $rescheck) {
    if ($rescheck->status === 'returned') {
        continue;
    }
    $requiresreturn = isset($rescheck->requiresreturn) ? (int)$rescheck->requiresreturn : 1;
    if (!$requiresreturn) {
        continue;
    }
    $objid = $rescheck->objectid;
    $current = $returncandidates[$objid] ?? null;
    $isactive = ($rescheck->timestart <= $now && $rescheck->timeend > $now);
    $isexpired = ($rescheck->timeend <= $now);

    if ($isactive) {
        // Active reservations always take precedence; pick the most recent start if multiple (should not overlap).
        if ($current === null || $current['type'] !== 'active' || $rescheck->timestart > $current['timestart']) {
            $returncandidates[$objid] = [
                'type' => 'active',
                'id' => $rescheck->id,
                'timestart' => $rescheck->timestart,
                'timeend' => $rescheck->timeend,
            ];
        }
    } else if ($isexpired) {
        // Only consider expired if no active selected; prefer latest timeend.
        if ($current === null || $current['type'] !== 'active') {
            if ($current === null || $rescheck->timeend > $current['timeend']) {
                $returncandidates[$objid] = [
                    'type' => 'expired',
                    'id' => $rescheck->id,
                    'timestart' => $rescheck->timestart,
                    'timeend' => $rescheck->timeend,
                ];
            }
        }
    }
}

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

if ($expiredwarning !== '') {
    echo $OUTPUT->notification($expiredwarning, 'warning');
}

// Inline warning toggled via JS when selecting objects not yet returned.
echo html_writer::div(
    get_string('unreturnednotice', 'local_inventario'),
    'alert alert-warning local-inventario-unreturned-warning' .
        ($selectedobject && in_array($selectedobject, $unreturnedids) ? '' : ' hidden')
);

if ($hasactivereservationnow) {
    echo html_writer::div(
        get_string('objectcurrentlyreserved', 'local_inventario'),
        'alert alert-warning'
    );
}

$form->display();
$headingkey = $canmanageall ? 'allreservations' : 'yourreservations';
echo html_writer::tag('h3', get_string($headingkey, 'local_inventario'), ['class' => 'mt-3 mb-2']);

$perpageoptionshtml = '';
foreach ($allowedperpage as $val) {
    $label = $val === 0 ? get_string('all', 'local_inventario') : $val;
    $attrs = ['value' => $val];
    if ((int)$val === (int)$resperpage) {
        $attrs['selected'] = 'selected';
    }
    $perpageoptionshtml .= html_writer::tag('option', $label, $attrs);
}
$formhidden = '';
if (!empty($selectedsite)) {
    $formhidden .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'siteid',
        'value' => $selectedsite,
    ]);
}
if (!empty($objectidparam)) {
    $formhidden .= html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'objectid',
        'value' => $objectidparam,
    ]);
}

$perpageformcontent = $formhidden .
    html_writer::label(get_string('itemsperpage', 'local_inventario'), 'resperpage', ['class' => 'mb-0 me-2']) .
    html_writer::tag('select', $perpageoptionshtml, [
        'name' => 'resperpage',
        'id' => 'resperpage',
        'class' => 'custom-select w-auto me-2',
    ]) .
    html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
        'value' => get_string('apply', 'moodle'),
    ]);

$perpageform = html_writer::tag(
    'form',
    $perpageformcontent,
    ['method' => 'get', 'class' => 'd-flex justify-content-end align-items-center gap-2 mb-2 flex-wrap']
);
echo $perpageform;

$table = new html_table();
$table->head = [
    get_string('object', 'local_inventario'),
    get_string('user'),
    get_string('site', 'local_inventario'),
    get_string('period', 'local_inventario'),
    get_string('location', 'local_inventario'),
    get_string('actions'),
];
$table->colclasses = array_fill(0, count($table->head), '');
$table->colclasses[count($table->head) - 1] = 'inventario-actions-col';

$rows = [];
foreach ($reservations as $reservation) {
    $sortkey = ($reservation->timeend < $now) ? 1 : 0;
    $expired = $reservation->timeend <= $now;
    $isreturned = $reservation->status === 'returned';
    $isreturncandidate = isset($returncandidates[$reservation->objectid]) &&
        (int)$returncandidates[$reservation->objectid]['id'] === (int)$reservation->id;
    $requiresreturn = isset($reservation->requiresreturn) ? (int)$reservation->requiresreturn : 1;
    // Collect rows for later ordering: active/upcoming first, then expired.
    $actions = [];
    if ($canmanageall || $reservation->userid == $USER->id) {
        $editicon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
        $deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
        $returniconenabled = html_writer::span('', 'fa-solid fa-arrow-right-to-city text-warning', [
            'title' => get_string('returnobject', 'local_inventario'),
            'aria-hidden' => 'true',
        ]);
        $returnicondisabled = html_writer::span('', 'fa-solid fa-arrow-right-to-city text-muted', [
            'title' => get_string('returnobject', 'local_inventario'),
            'aria-hidden' => 'true',
        ]);
        $noreturntooltip = get_string('noreturnrequiredtooltip', 'local_inventario');

        // Edit.
        if ($expired) {
            $actions[] = html_writer::span($editicon, 'text-muted');
        } else {
            $actions[] = html_writer::link(
                new moodle_url($PAGE->url, ['id' => $reservation->id]),
                $editicon
            );
        }

        // Return (only for types that require it).
        if ($requiresreturn) {
            $shouldreturn = ($expired && !$isreturned && $isreturncandidate);
            if ($shouldreturn) {
                $returnurl = new moodle_url($PAGE->url, ['return' => $reservation->id, 'sesskey' => sesskey()]);
                $actions[] = html_writer::link(
                    $returnurl,
                    $returniconenabled,
                    [
                        'title' => get_string('returnobject', 'local_inventario'),
                        'onclick' => "return confirm('" . get_string('confirmreturn', 'local_inventario') . "');",
                    ]
                );
            } else {
                $actions[] = html_writer::span($returnicondisabled, 'text-muted');
            }
        } else {
            $actions[] = html_writer::span(
                html_writer::span('', 'fa-solid fa-arrow-right-to-city fa-stack-1x text-muted', ['aria-hidden' => 'true']) .
                html_writer::span('', 'fa-solid fa-ban fa-stack-2x text-danger', ['aria-hidden' => 'true']),
                'fa-stack fa-sm align-middle',
                [
                    'title' => $noreturntooltip,
                    'aria-label' => $noreturntooltip,
                    'role' => 'img',
                ]
            );
        }

        // Delete.
        if (!$expired) {
            $deleteurl = new moodle_url($PAGE->url, ['delete' => $reservation->id, 'sesskey' => sesskey()]);
            $actions[] = html_writer::link(
                $deleteurl,
                $deleteicon,
                ['onclick' => "return confirm('" . get_string('confirmdelete') . "');"]
            );
        } else {
            $actions[] = html_writer::span($deleteicon, 'text-muted');
        }
    }
    $statusbadge = '';
    if ($reservation->status === 'returned') {
        $label = $requiresreturn
            ? get_string('returned', 'local_inventario')
            : get_string('reservationexpiredshort', 'local_inventario');
        $statusbadge = html_writer::span(
            $label,
            'badge bg-info text-white ms-1'
        );
    } else if ($reservation->timeend < $now) {
        $statusbadge = html_writer::span(
            get_string('reservationexpired', 'local_inventario'),
            'badge bg-secondary text-white ms-1'
        );
    } else if ($reservation->timestart <= $now && $reservation->timeend > $now) {
        $statusbadge = html_writer::span(
            get_string('reservationactive', 'local_inventario'),
            'badge bg-success text-white ms-1'
        );
    }

    $rows[] = [
        'cells' => [
            html_writer::tag('a', '', ['id' => 'reservation-' . $reservation->id, 'class' => 'reservation-anchor']) .
            format_string($reservation->objectname) . $statusbadge,
            fullname($reservation),
            format_string($reservation->sitename),
            userdate($reservation->timestart) . ' → ' . userdate($reservation->timeend),
            format_string($reservation->location),
            implode(' | ', $actions),
        ],
        'class' => $reservation->id == $focus ? 'inventario-reservation-focus' : '',
        'sort' => $sortkey,
        'timestart' => (int)$reservation->timestart,
    ];
}

// Reorder rows: active/upcoming first, then expired; within each group newest timestart first.
usort($rows, static function ($a, $b) {
    if ($a['sort'] === $b['sort']) {
        return $b['timestart'] <=> $a['timestart'];
    }
    return $a['sort'] <=> $b['sort'];
});
$rows = $resperpage > 0 ? array_slice($rows, 0, $resperpage) : $rows;
$table->data = array_map(static fn($row) => $row['cells'], $rows);
$table->rowclasses = array_map(static fn($row) => $row['class'], $rows);

echo html_writer::table($table);

// Overlap error modal.
echo html_writer::tag(
    'div',
    html_writer::tag(
        'div',
        html_writer::tag(
            'div',
            html_writer::tag(
                'div',
                get_string('error') .
                html_writer::tag('button', '', [
                    'type' => 'button',
                    'class' => 'btn-close',
                    'data-bs-dismiss' => 'modal',
                    'data-dismiss' => 'modal',
                    'aria-label' => get_string('closebuttontitle'),
                ]),
                ['class' => 'modal-header d-flex justify-content-between align-items-center']
            ) .
            html_writer::tag('div', '', ['class' => 'modal-body', 'id' => 'inventario-overlap-modal-body']) .
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
        ['class' => 'modal-dialog', 'role' => 'document']
    ),
    ['class' => 'modal fade', 'id' => 'inventario-overlap-modal', 'tabindex' => '-1', 'role' => 'dialog', 'aria-hidden' => 'true']
);

if (!empty($overlaperror)) {
    $escaped = json_encode($overlaperror, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $PAGE->requires->js_init_code(
        "require(['jquery'], function($) {
            $('#inventario-overlap-modal-body').text({$escaped});
            $('#inventario-overlap-modal').modal('show');
        });"
    );
}

echo $OUTPUT->footer();
