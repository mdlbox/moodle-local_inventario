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
 * Calendar view of reservations.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:reserve', $context);

$license = local_inventario_license()->refresh();
if ($license->status !== 'pro' || empty($license->apikey)) {
    throw new moodle_exception('prorequired', 'local_inventario');
}

$month = optional_param('month', (int)date('n'), PARAM_INT);
$year = optional_param('year', (int)date('Y'), PARAM_INT);
$siteid = optional_param('siteid', 0, PARAM_INT);
$typeid = optional_param('typeid', 0, PARAM_INT);
$objectid = optional_param('objectid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$todayinfo = usergetdate(time());

$month = max(1, min(12, $month));
if ($year < 1970 || $year > 2100) {
    $year = (int)date('Y');
}

$PAGE->set_url('/local/inventario/reservations_calendar.php', [
    'month' => $month,
    'year' => $year,
    'siteid' => $siteid,
    'typeid' => $typeid,
    'objectid' => $objectid,
    'userid' => $userid,
]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reservationscalendar', 'local_inventario'));
$PAGE->set_heading(get_string('reservationscalendar', 'local_inventario'));
$PAGE->requires->css('/local/inventario/styles.css');

$includehidden = local_inventario_can_see_hidden();
$service = local_inventario_service();
$canmanageall = has_capability('local/inventario:deletereservations', $context);

$siteoptions = local_inventario_site_options();
$typeoptions = local_inventario_type_options();
$objectoptions = [];
foreach ($service->get_objects($includehidden, $siteid ?: null) as $object) {
    if ($typeid && $object->typeid != $typeid) {
        continue;
    }
    $objectoptions[$object->id] = format_string($object->name);
}

$useroptions = [];
$userselectoptions = [];
if ($canmanageall) {
    $allusers = get_users_by_capability(
        $context,
        'local/inventario:reserve',
        'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename'
    );
    usort($allusers, static function ($a, $b) {
        $lcmp = strcasecmp($a->lastname, $b->lastname);
        if ($lcmp !== 0) {
            return $lcmp;
        }
        return strcasecmp($a->firstname, $b->firstname);
    });
    $currentlabel = fullname($USER) . ' (' . get_string('you', 'moodle') . ')';
    $userselectoptions = [0 => get_string('all', 'local_inventario'), $USER->id => $currentlabel];
    foreach ($allusers as $u) {
        if ($u->id == $USER->id) {
            continue;
        }
        $userselectoptions[$u->id] = fullname($u);
    }
} else {
    $userid = $USER->id;
    $userselectoptions = [$USER->id => fullname($USER)];
}

$monthstart = make_timestamp($year, $month, 1, 0, 0, 0);
$daysinmonth = (int)date('t', $monthstart);
$monthend = make_timestamp($year, $month, $daysinmonth, 23, 59, 59);

$filters = [
    'overlapfrom' => $monthstart,
    'overlapto' => $monthend,
];
if ($siteid) {
    $filters['siteid'] = $siteid;
}
if ($typeid) {
    $filters['typeid'] = $typeid;
}
if ($objectid) {
    $filters['objectid'] = $objectid;
}
if (!$canmanageall) {
    $userid = $USER->id;
}
if ($userid) {
    $filters['userid'] = $userid;
}

$reservations = $service->get_reservations($filters, $includehidden);

$typemap = [];
foreach (local_inventario_typeservice()->get_types() as $type) {
    $color = '#2563eb';
    if (!empty($type->color) && preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $type->color)) {
        $color = strtolower($type->color);
    }
    $typemap[$type->id] = [
        'name' => format_string($type->name),
        'color' => $color,
    ];
}

$eventsbyday = [];
for ($day = 1; $day <= $daysinmonth; $day++) {
    $daystart = make_timestamp($year, $month, $day, 0, 0, 0);
    $dayend = make_timestamp($year, $month, $day, 23, 59, 59);
    foreach ($reservations as $reservation) {
        if ($reservation->timestart <= $dayend && $reservation->timeend >= $daystart) {
            $typeinfo = $typemap[$reservation->typeid] ?? [
                'name' => get_string('type', 'local_inventario'),
                'color' => '#2563eb',
            ];
            $eventsbyday[$day][] = [
                'title' => format_string($reservation->objectname),
                'timerange' => userdate($reservation->timestart, get_string('strftimetime', 'langconfig')) .
                    ' - ' . userdate($reservation->timeend, get_string('strftimetime', 'langconfig')),
                'user' => fullname($reservation),
                'color' => $typeinfo['color'],
            ];
        }
    }
}

$firstweekday = (int)date('N', $monthstart);
$weeks = [];
$week = [];
for ($i = 1; $i < $firstweekday; $i++) {
    $week[] = null;
}
for ($day = 1; $day <= $daysinmonth; $day++) {
    $week[] = [
        'day' => $day,
        'events' => $eventsbyday[$day] ?? [],
    ];
    if (count($week) === 7) {
        $weeks[] = $week;
        $week = [];
    }
}
if (!empty($week)) {
    while (count($week) < 7) {
        $week[] = null;
    }
    $weeks[] = $week;
}

$weekdaylabels = [];
$basemonday = strtotime('monday this week', $monthstart);
for ($i = 0; $i < 7; $i++) {
    $weekdaylabels[] = userdate(strtotime("+{$i} day", $basemonday), '%A');
}

$monthlabel = userdate($monthstart, get_string('strftimemonthyear'));
$prevmonth = $month - 1;
$prevyear = $year;
if ($prevmonth < 1) {
    $prevmonth = 12;
    $prevyear--;
}
$nextmonth = $month + 1;
$nextyear = $year;
if ($nextmonth > 12) {
    $nextmonth = 1;
    $nextyear++;
}
$baseparams = [
    'siteid' => $siteid,
    'typeid' => $typeid,
    'objectid' => $objectid,
    'userid' => $userid,
];
$prevurl = new moodle_url($PAGE->url, $baseparams + ['month' => $prevmonth, 'year' => $prevyear]);
$nexturl = new moodle_url($PAGE->url, $baseparams + ['month' => $nextmonth, 'year' => $nextyear]);

$legendtypes = [];
foreach ($reservations as $reservation) {
    $legendtypes[(int)$reservation->typeid] = true;
}

$todayurl = new moodle_url($PAGE->url, $baseparams + [
    'month' => $todayinfo['mon'],
    'year' => $todayinfo['year'],
]) . '#inventario-today';

$monthnav = html_writer::div(
    html_writer::link($prevurl, '&lt;&lt;', ['class' => 'btn btn-secondary']) .
    html_writer::span($monthlabel, 'mx-3 fw-bold') .
    html_writer::link($nexturl, '&gt;&gt;', ['class' => 'btn btn-secondary']),
    'd-flex align-items-center justify-content-center gap-3 mb-3 inventario-calendar-nav'
);

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

echo html_writer::start_div('card mb-3 shadow-sm');
echo html_writer::start_div('card-body');

$monthoptions = [];
for ($i = 1; $i <= 12; $i++) {
    $monthoptions[$i] = userdate(make_timestamp($year, $i, 1), '%B');
}
$yearoptions = [];
for ($i = $year - 2; $i <= $year + 2; $i++) {
    $yearoptions[$i] = $i;
}

$formurl = new moodle_url($PAGE->url);
$formattrs = ['method' => 'get', 'action' => $formurl->out(false), 'class' => 'mform'];
echo html_writer::start_tag('form', $formattrs);
echo html_writer::tag('legend', get_string('filters', 'local_inventario'));

echo html_writer::start_div('d-flex justify-content-end');
echo html_writer::start_tag('table', [
    'class' => 'generaltable boxaligncenter inventario-filter-table',
]);

echo html_writer::start_tag('tr', ['class' => 'inventario-filter-row']);
echo html_writer::tag('th', html_writer::label(get_string('month'), 'month'));
echo html_writer::tag('td', html_writer::select(
    $monthoptions,
    'month',
    $month,
    null,
    ['id' => 'month', 'class' => 'custom-select']
));
echo html_writer::tag('th', html_writer::label(get_string('year'), 'year'));
echo html_writer::tag('td', html_writer::select(
    $yearoptions,
    'year',
    $year,
    null,
    ['id' => 'year', 'class' => 'custom-select']
));
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr', ['class' => 'inventario-filter-row']);
echo html_writer::tag('th', html_writer::label(get_string('site', 'local_inventario'), 'siteid'));
echo html_writer::tag('td', html_writer::select(
    [0 => get_string('all', 'local_inventario')] + $siteoptions,
    'siteid',
    $siteid,
    null,
    ['id' => 'siteid', 'class' => 'custom-select']
));
echo html_writer::tag('th', html_writer::label(get_string('type', 'local_inventario'), 'typeid'));
echo html_writer::tag('td', html_writer::select(
    [0 => get_string('all', 'local_inventario')] + $typeoptions,
    'typeid',
    $typeid,
    null,
    ['id' => 'typeid', 'class' => 'custom-select']
));
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr', ['class' => 'inventario-filter-row']);
echo html_writer::tag('th', html_writer::label(get_string('user'), 'userid'));
$usersearchbox = html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'useridsearch',
    'class' => 'form-control mb-2',
    'placeholder' => get_string('search'),
]);
$userselect = html_writer::select(
    $userselectoptions,
    'userid',
    $userid,
    null,
    [
        'id' => 'userid',
        'class' => 'custom-select',
        'data-users' => json_encode($userselectoptions),
    ]
);
echo html_writer::tag('td', $usersearchbox . $userselect);
echo html_writer::tag('th', html_writer::label(get_string('object', 'local_inventario'), 'objectid'));
echo html_writer::tag('td', html_writer::select(
    [0 => get_string('all', 'local_inventario')] + $objectoptions,
    'objectid',
    $objectid,
    null,
    ['id' => 'objectid', 'class' => 'custom-select']
));
echo html_writer::end_tag('tr');

echo html_writer::start_tag('tr');
echo html_writer::tag('td', '', ['colspan' => 2]);
$submitcell = html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('filter', 'local_inventario'),
    'class' => 'btn btn-primary me-2',
]);
$reseturl = new moodle_url('/local/inventario/reservations_calendar.php', [
    'month' => $month,
    'year' => $year,
]);
$submitcell .= html_writer::link(
    $reseturl,
    get_string('resetfilters', 'local_inventario'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::tag('td', html_writer::div($submitcell, 'd-flex justify-content-end'), ['colspan' => 2]);
echo html_writer::end_tag('tr');

echo html_writer::end_tag('table');
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

$legendchips = [];
foreach (array_keys($legendtypes) as $typeidentifier) {
    if (!isset($typemap[$typeidentifier])) {
        continue;
    }
    $info = $typemap[$typeidentifier];
    $chip = html_writer::div('', 'inventario-color-chip', ['style' => 'background-color:' . s($info['color']) . ';']);
    $legendchips[] = html_writer::div($chip . ' ' . $info['name'], 'inventario-legend-item');
}
$legendleft = html_writer::div(
    html_writer::tag('div', get_string('calendarlegend', 'local_inventario'), ['class' => 'fw-bold me-2 mb-0']) .
    html_writer::div(implode('', $legendchips), 'd-flex align-items-center flex-wrap gap-2'),
    'd-flex align-items-center flex-wrap gap-2'
);
echo html_writer::div(
    $legendleft .
    html_writer::div(
        html_writer::link($todayurl, get_string('today'), ['class' => 'btn btn-secondary ms-auto']),
        'ms-auto'
    ),
    'inventario-calendar-legend d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3'
);

echo $monthnav;

echo html_writer::start_tag('table', ['class' => 'generaltable inventario-calendar-table']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
foreach ($weekdaylabels as $label) {
    echo html_writer::tag('th', $label, ['class' => 'text-center']);
}
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($weeks as $weekrow) {
    echo html_writer::start_tag('tr');
    foreach ($weekrow as $cell) {
        if ($cell === null) {
            echo html_writer::tag('td', '&nbsp;', ['class' => 'inventario-calendar-cell muted']);
            continue;
        }
        $istoday = ($year === (int)$todayinfo['year']
            && $month === (int)$todayinfo['mon']
            && $cell['day'] === (int)$todayinfo['mday']);
        $daycontent = html_writer::div($cell['day'], 'inventario-calendar-day text-muted');
        if (!empty($cell['events'])) {
            $eventblocks = [];
            $maxvisible = 2;
            $total = count($cell['events']);
            foreach ($cell['events'] as $idx => $event) {
                $title = html_writer::div($event['title'], 'inventario-calendar-event-title');
                $meta = html_writer::div(
                    $event['timerange'] . ' - ' . $event['user'],
                    'inventario-calendar-event-meta'
                );
                $classes = ['inventario-calendar-event'];
                if ($idx >= $maxvisible) {
                    $classes[] = 'inventario-calendar-event-hidden';
                }
                $eventblocks[] = html_writer::div(
                    $title . $meta,
                    implode(' ', $classes),
                    ['style' => '--event-color:' . s($event['color'])]
                );
            }
            if ($total > $maxvisible) {
                $remaining = $total - $maxvisible;
                $morelabel = get_string('calendarshowmore', 'local_inventario', $remaining);
                $morebutton = html_writer::tag('button', $morelabel, [
                    'type' => 'button',
                    'class' => 'btn btn-secondary btn-sm inventario-calendar-showmore',
                    'data-label' => $morelabel,
                ]);
                $eventblocks[] = html_writer::div($morebutton, 'inventario-calendar-more');
            }
            $daycontent .= html_writer::div(implode('', $eventblocks), 'inventario-calendar-events');
        }
        $classes = ['inventario-calendar-cell'];
        $attrs = [];
        if (empty($cell['events'])) {
            $classes[] = 'empty';
        }
        if ($istoday) {
            $classes[] = 'today';
            $attrs['id'] = 'inventario-today';
        }
        $attrs['class'] = implode(' ', $classes);
        echo html_writer::tag('td', $daycontent, $attrs);
    }
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

if (empty($reservations)) {
    echo $OUTPUT->notification(get_string('calendarnoreservations', 'local_inventario'), 'notifymessage');
}

$PAGE->requires->js_init_code("
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.inventario-calendar-showmore');
        if (!btn) {
            return;
        }
        e.preventDefault();
        var cell = btn.closest('.inventario-calendar-cell');
        if (!cell) {
            return;
        }
        var expanded = cell.classList.toggle('expanded');
        if (!btn.dataset.label) {
            btn.dataset.label = btn.textContent;
        }
        btn.textContent = expanded ? '" . get_string('calendarshowless', 'local_inventario') . "' : btn.dataset.label;
    });
    (function() {
        var search = document.getElementById('useridsearch');
        var select = document.getElementById('userid');
        if (!search || !select) {
            return;
        }
        var stored = select.dataset.users ? JSON.parse(select.dataset.users) : {};
        var allOptions = Object.keys(stored).map(function(key) {
            return {value: key, text: stored[key]};
        });
        function render(term) {
            var needle = term.trim().toLowerCase();
            var current = select.value;
            while (select.options.length) {
                select.remove(0);
            }
            allOptions.forEach(function(opt) {
                if (needle && opt.text.toLowerCase().indexOf(needle) === -1) {
                    return;
                }
                var option = new Option(opt.text, opt.value, false, opt.value === current);
                select.add(option);
            });
        }
        render(search.value);
        search.addEventListener('input', function() {
            render(search.value);
        });
    })();
");

echo $OUTPUT->footer();
