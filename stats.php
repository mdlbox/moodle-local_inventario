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
 * Statistics page for the inventory plugin.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:view', $context);

$license = local_inventario_license()->refresh();
$objectfilter = optional_param('objectid', 0, PARAM_INT);
if ($license->status !== 'pro') {
    $objectfilter = 0;
}

$PAGE->set_url('/local/inventario/stats.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('stats', 'local_inventario'));
$PAGE->set_heading(get_string('stats', 'local_inventario'));

$includehidden = local_inventario_can_see_hidden();
$service = local_inventario_service();
$stats = $service->get_stats($includehidden);
$usage = $stats['usage'] ?? [];
$objectslist = $service->get_objects($includehidden);
if ($objectfilter && isset($objectslist[$objectfilter])) {
    $objectslist = [$objectfilter => $objectslist[$objectfilter]];
}

// Prepare properties per object.
$objectprops = [];
if (!empty($objectslist)) {
    global $DB;
    list($insql, $params) = $DB->get_in_or_equal(array_keys($objectslist), SQL_PARAMS_NAMED);
    $propsql = "SELECT pv.objectid, p.name, pv.value
                  FROM {local_inventario_propvals} pv
                  JOIN {local_inventario_properties} p ON p.id = pv.propertyid
                 WHERE pv.objectid {$insql}";
    $propvals = $DB->get_records_sql($propsql, $params);
    foreach ($propvals as $pv) {
        $value = $pv->value;
        if ($value === '1') {
            $value = get_string('yes');
        } else if ($value === '0') {
            $value = get_string('no');
        }
        $objectprops[$pv->objectid][] = format_string($pv->name) . ': ' . s($value);
    }
}

// Prepare history per object (only for Pro).
$objecthistory = [];
if ($license->status === 'pro' && !empty($objectslist)) {
    global $DB;
    list($insqlh, $paramsh) = $DB->get_in_or_equal(array_keys($objectslist), SQL_PARAMS_NAMED);
    // Select reservation id first to keep array keys unique per reservation.
    $hsql = "SELECT r.id AS reservationid, r.objectid, r.timestart, r.timeend,
                    u.firstname, u.lastname, u.middlename, u.alternatename, u.firstnamephonetic, u.lastnamephonetic
               FROM {local_inventario_reserv} r
               JOIN {user} u ON u.id = r.userid
              WHERE r.objectid {$insqlh}
           ORDER BY r.timestart DESC";
    $histories = $DB->get_records_sql($hsql, $paramsh);
    foreach ($histories as $h) {
        $objecthistory[$h->objectid][] = fullname($h) . ' (' . userdate($h->timestart) . ' - ' . userdate($h->timeend) . ')';
    }
}

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

echo html_writer::start_div('inventario-stats');
echo html_writer::div(
    html_writer::tag('h3', get_string('stats', 'local_inventario'), ['class' => 'h4 mb-3']) .
    html_writer::tag('p', get_string('stat_summary', 'local_inventario', [
        'objects' => $stats['objects'],
        'reservations' => $stats['reservations'],
    ])),
    'card shadow-sm p-3 mb-4'
);

echo html_writer::div(
    html_writer::tag('h3', get_string('topobjects', 'local_inventario'), ['class' => 'h4 mb-3']) .
    (empty($usage)
        ? html_writer::div(get_string('nostats', 'local_inventario'), 'alert alert-info mb-0')
        : html_writer::table((function() use ($usage) {
            $table = new html_table();
            $table->head = [
                get_string('object', 'local_inventario'),
                get_string('reservations', 'local_inventario'),
            ];
            foreach ($usage as $row) {
                $table->data[] = [
                    format_string($row->name),
                    $row->total,
                ];
            }
            $table->attributes['class'] = 'generaltable inventario-table';
            return $table;
        })())
    ),
    'card shadow-sm p-3'
);

// Objects details with properties and history (history only in Pro).
$objecttable = new html_table();
$objecttable->head = [
    get_string('object', 'local_inventario'),
    get_string('properties', 'local_inventario'),
];
if ($license->status === 'pro') {
    $objecttable->head[] = get_string('history', 'local_inventario');
}

foreach ($objectslist as $obj) {
    $props = $objectprops[$obj->id] ?? [];
    $propsstr = $props ? html_writer::alist($props) : '-';

    $row = [
        format_string($obj->name),
        $propsstr,
    ];
    if ($license->status === 'pro') {
        $hist = $objecthistory[$obj->id] ?? [];
        if (!empty($hist)) {
            $maxdisplay = 3;
            $histitems = [];
            foreach ($hist as $idx => $item) {
                $classes = 'mb-1 inventario-history-item';
                if ($idx >= $maxdisplay) {
                    $classes .= ' d-none inventario-history-extra';
                }
                $histitems[] = html_writer::div(s($item), $classes);
            }
            $historyid = 'inventario-history-' . $obj->id;
            $histcontent = html_writer::div(implode('', $histitems), 'd-flex flex-column', ['id' => $historyid]);
            if (count($hist) > $maxdisplay) {
                $histcontent .= html_writer::link(
                    '#',
                    get_string('historyshowmore', 'local_inventario'),
                    [
                        'class' => 'inventario-history-toggle mt-2',
                        'data-target' => $historyid,
                        'data-more' => get_string('historyshowmore', 'local_inventario'),
                        'data-less' => get_string('historyshowless', 'local_inventario'),
                    ]
                );
            }
            $histstr = html_writer::div($histcontent);
        } else {
            $histstr = get_string('noreservations', 'local_inventario');
        }
        $row[] = $histstr;
    }
    $objecttable->data[] = $row;
}

echo html_writer::div(
    html_writer::tag('h3', get_string('objects', 'local_inventario'), ['class' => 'h4 mb-3']) .
    html_writer::table($objecttable),
    'card shadow-sm p-3 mt-3',
    ['id' => 'inventario-objects-block']
);
echo html_writer::end_div();

$PAGE->requires->js_init_code("(function(){const toggles=document.querySelectorAll('.inventario-history-toggle');toggles.forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();const target=document.getElementById(btn.dataset.target);if(!target){return;}const extras=target.querySelectorAll('.inventario-history-extra');if(!extras.length){return;}const hidden=extras[0].classList.contains('d-none');extras.forEach(function(el){el.classList.toggle('d-none',!hidden);});btn.textContent=hidden?btn.dataset.less:btn.dataset.more;});});})();");
echo $OUTPUT->footer();



