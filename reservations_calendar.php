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
 * Calendar view of reservations (month, week and day views).
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/reservations_calendar_filters_form.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:reserve', $context);


$defaultview = get_config('local_inventario', 'calendar_defaultview');
if (!in_array($defaultview, ['month', 'week', 'day'], true)) {
    $defaultview = 'month';
}
$view = optional_param('view', $defaultview, PARAM_ALPHA);
if (!in_array($view, ['month', 'week', 'day'], true)) {
    $view = $defaultview;
}
$month = optional_param('month', (int)date('n'), PARAM_INT);
$year = optional_param('year', (int)date('Y'), PARAM_INT);
$datestr = optional_param('date', '', PARAM_TEXT);
$siteid = optional_param('siteid', 0, PARAM_INT);
$typeid = optional_param('typeid', 0, PARAM_INT);
$objectid = optional_param('objectid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$resetfilters = optional_param('resetfilter', '', PARAM_TEXT);
$todayinfo = usergetdate(time());

$month = max(1, min(12, $month));
if ($year < 1970 || $year > 2100) {
    $year = (int)date('Y');
}

// Reference day (midnight) used by the week and day views.
$refts = time();
if ($datestr !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $datestr)) {
    $parsed = strtotime($datestr . ' 12:00:00');
    if ($parsed) {
        $refts = $parsed;
    }
}
$refmid = make_timestamp((int)date('Y', $refts), (int)date('n', $refts), (int)date('j', $refts), 0, 0, 0);
$refdatestr = date('Y-m-d', $refmid);

if ($resetfilters !== '') {
    redirect(new moodle_url('/local/inventario/reservations_calendar.php', [
        'view' => $view,
        'month' => $month,
        'year' => $year,
        'date' => $refdatestr,
    ]));
}

$PAGE->set_url('/local/inventario/reservations_calendar.php', [
    'view' => $view,
    'month' => $month,
    'year' => $year,
    'date' => $refdatestr,
    'siteid' => $siteid,
    'typeid' => $typeid,
    'objectid' => $objectid,
    'userid' => $userid,
]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reservationscalendar', 'local_inventario'));
$PAGE->set_heading(get_string('reservationscalendar', 'local_inventario'));

$includehidden = local_inventario_can_see_hidden();
$service = local_inventario_service();
$canmanageall = has_capability('local/inventario:deletereservations', $context);

$siteoptions = local_inventario_site_options();
$typeoptions = local_inventario_type_options();
$typesbyid = local_inventario_typeservice()->get_types();
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

$monthoptions = [];
for ($i = 1; $i <= 12; $i++) {
    $monthoptions[$i] = userdate(make_timestamp($year, $i, 1), '%B');
}
$yearoptions = [];
for ($i = $year - 2; $i <= $year + 2; $i++) {
    $yearoptions[$i] = $i;
}

$filterform = new local_inventario_reservations_calendar_filters_form(
    new moodle_url('/local/inventario/reservations_calendar.php'),
    [
        'monthoptions' => $monthoptions,
        'yearoptions' => $yearoptions,
        'siteoptions' => $siteoptions,
        'typeoptions' => $typeoptions,
        'objectoptions' => $objectoptions,
        'useroptions' => $userselectoptions,
        'canmanageall' => $canmanageall,
        'currentuserid' => (int)$USER->id,
    ],
    'get',
    '',
    ['class' => 'mform inventario-filters']
);

if ($formdata = $filterform->get_data()) {
    $month = max(1, min(12, (int)($formdata->month ?? $month)));
    $year = (int)($formdata->year ?? $year);
    if ($year < 1970 || $year > 2100) {
        $year = (int)date('Y');
    }
    $siteid = (int)($formdata->siteid ?? 0);
    $typeid = (int)($formdata->typeid ?? 0);
    $objectid = (int)($formdata->objectid ?? 0);
    if ($canmanageall) {
        $userid = (int)($formdata->userid ?? 0);
    } else {
        $userid = (int)$USER->id;
    }
    if (!empty($formdata->view) && in_array($formdata->view, ['month', 'week', 'day'], true)) {
        $view = $formdata->view;
    }
    if (!empty($formdata->date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $formdata->date)) {
        $refmid = make_timestamp(
            (int)substr($formdata->date, 0, 4),
            (int)substr($formdata->date, 5, 2),
            (int)substr($formdata->date, 8, 2),
            0,
            0,
            0
        );
        $refdatestr = date('Y-m-d', $refmid);
    }
}

$filterform->set_data((object)[
    'view' => $view,
    'date' => $refdatestr,
    'month' => $month,
    'year' => $year,
    'siteid' => $siteid,
    'typeid' => $typeid,
    'objectid' => $objectid,
    'userid' => $userid,
]);

if (!$canmanageall) {
    $userid = $USER->id;
}

// Shared filter set (range is added per view below).
$basefilters = [];
if ($siteid) {
    $basefilters['siteid'] = $siteid;
}
if ($typeid) {
    $basefilters['typeid'] = $typeid;
}
if ($objectid) {
    $basefilters['objectid'] = $objectid;
}
if ($userid) {
    $basefilters['userid'] = $userid;
}

// Type colour map shared by all views.
$typemap = [];
foreach ($typesbyid as $type) {
    $color = '#2563eb';
    if (!empty($type->color) && preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $type->color)) {
        $color = strtolower($type->color);
    }
    $typemap[$type->id] = [
        'name' => format_string($type->name),
        'color' => $color,
    ];
}

// Calendar grid configuration (week/day views).
$daystart = (int)get_config('local_inventario', 'calendar_daystart');
$dayend = (int)get_config('local_inventario', 'calendar_dayend');
$slotminutes = (int)get_config('local_inventario', 'calendar_slotminutes');
$daystart = max(0, min(23, $daystart ?: 7));
$dayend = max($daystart + 1, min(24, $dayend ?: 20));
$slotminutes = max(5, min(240, $slotminutes ?: 45));

$baseparams = [
    'siteid' => $siteid,
    'typeid' => $typeid,
    'objectid' => $objectid,
    'userid' => $userid,
];

/**
 * Build a view-switch button group preserving filters.
 *
 * @param string $active
 * @param array $baseparams
 * @param int $month
 * @param int $year
 * @param string $refdatestr
 * @return string
 */
function local_inventario_calendar_view_switch(string $active, array $baseparams, int $month, int $year, string $refdatestr): string {
    $views = [
        'month' => get_string('calendarview_month', 'local_inventario'),
        'week' => get_string('calendarview_week', 'local_inventario'),
        'day' => get_string('calendarview_day', 'local_inventario'),
    ];
    $buttons = '';
    foreach ($views as $key => $label) {
        $params = $baseparams + ['view' => $key, 'month' => $month, 'year' => $year, 'date' => $refdatestr];
        $class = 'btn ' . ($key === $active ? 'btn-primary active' : 'btn-secondary');
        $buttons .= html_writer::link(
            new moodle_url('/local/inventario/reservations_calendar.php', $params),
            $label,
            ['class' => $class]
        );
    }
    return html_writer::div($buttons, 'btn-group inventario-calendar-viewswitch', ['role' => 'group']);
}

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

echo local_inventario_render_filter_card($filterform->render());

echo html_writer::div(
    local_inventario_calendar_view_switch($view, $baseparams, $month, $year, $refdatestr),
    'd-flex justify-content-center mb-3'
);

if ($view === 'month') {
    $monthstart = make_timestamp($year, $month, 1, 0, 0, 0);
    $daysinmonth = (int)date('t', $monthstart);
    $monthend = make_timestamp($year, $month, $daysinmonth, 23, 59, 59);

    $filters = $basefilters + ['overlapfrom' => $monthstart, 'overlapto' => $monthend];
    $reservations = $service->get_reservations($filters, $includehidden);

    $eventsbyday = [];
    for ($day = 1; $day <= $daysinmonth; $day++) {
        $daystartts = make_timestamp($year, $month, $day, 0, 0, 0);
        $dayendts = make_timestamp($year, $month, $day, 23, 59, 59);
        foreach ($reservations as $reservation) {
            if ($reservation->timestart <= $dayendts && $reservation->timeend >= $daystartts) {
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
        $week[] = ['day' => $day, 'events' => $eventsbyday[$day] ?? []];
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
    $prevurl = new moodle_url($PAGE->url, $baseparams + ['view' => 'month', 'month' => $prevmonth, 'year' => $prevyear]);
    $nexturl = new moodle_url($PAGE->url, $baseparams + ['view' => 'month', 'month' => $nextmonth, 'year' => $nextyear]);
    $todayurl = new moodle_url($PAGE->url, $baseparams + [
        'view' => 'month',
        'month' => $todayinfo['mon'],
        'year' => $todayinfo['year'],
    ]) . '#inventario-today';

    $legendtypes = [];
    foreach ($reservations as $reservation) {
        $legendtypes[(int)$reservation->typeid] = true;
    }
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

    echo html_writer::div(
        html_writer::link($prevurl, '&lt;&lt;', ['class' => 'btn btn-secondary']) .
        html_writer::span($monthlabel, 'mx-3 fw-bold') .
        html_writer::link($nexturl, '&gt;&gt;', ['class' => 'btn btn-secondary']),
        'd-flex align-items-center justify-content-center gap-3 mb-3 inventario-calendar-nav'
    );

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
                    $meta = html_writer::div($event['timerange'] . ' - ' . $event['user'], 'inventario-calendar-event-meta');
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

    $PAGE->requires->js_call_amd('local_inventario/calendarmonth', 'init', [
        get_string('calendarshowless', 'local_inventario'),
    ]);
} else {
    // Week / day views: build the day columns and the visible range.
    if ($view === 'week') {
        $dow = (int)date('N', $refmid);
        $weekstart = strtotime('-' . ($dow - 1) . ' days', $refmid);
        $numdays = 7;
    } else {
        $weekstart = $refmid;
        $numdays = 1;
    }

    $days = [];
    for ($i = 0; $i < $numdays; $i++) {
        $dayts = strtotime("+{$i} days", $weekstart);
        $days[] = [
            'ts' => $dayts,
            'date' => date('Y-m-d', $dayts),
            'label' => userdate($dayts, '%a %e/%m'),
            'istoday' => ($dayts === make_timestamp(
                (int)$todayinfo['year'],
                (int)$todayinfo['mon'],
                (int)$todayinfo['mday'],
                0,
                0,
                0
            )) ? 1 : 0,
        ];
    }

    $rangestart = $weekstart;
    $rangeend = strtotime("+{$numdays} days", $weekstart) - 1;
    $filters = $basefilters + ['overlapfrom' => $rangestart, 'overlapto' => $rangeend];
    $reservations = $service->get_reservations($filters, $includehidden);

    $resjson = [];
    foreach ($reservations as $reservation) {
        $typeinfo = $typemap[$reservation->typeid] ?? ['name' => '', 'color' => '#2563eb'];
        $resjson[] = [
            'id' => (int)$reservation->id,
            'objectid' => (int)$reservation->objectid,
            'name' => format_string($reservation->objectname),
            'user' => fullname($reservation),
            'color' => $typeinfo['color'],
            'start' => (int)$reservation->timestart,
            'end' => (int)$reservation->timeend,
        ];
    }

    $objjson = [];
    foreach ($service->get_objects($includehidden, $siteid ?: null) as $object) {
        if ($typeid && $object->typeid != $typeid) {
            continue;
        }
        if (!empty($object->inmaintenance)) {
            continue;
        }
        $type = $typesbyid[$object->typeid] ?? null;
        $objjson[] = [
            'id' => (int)$object->id,
            'name' => format_string($object->name),
            'typeid' => (int)$object->typeid,
            'requireslocation' => ($type && property_exists($type, 'requireslocation')) ? (int)$type->requireslocation : 1,
            'siteid' => (int)$object->siteid,
            'availenabled' => (int)($object->availableperiodenabled ?? 0),
            'availfrom' => (int)($object->availablefrom ?? 0),
            'availto' => (int)($object->availableto ?? 0),
            'availtimes' => (string)($object->availabletimes ?? ''),
        ];
    }

    $usersjson = [];
    if ($canmanageall) {
        foreach ($userselectoptions as $uid => $uname) {
            if ((int)$uid === 0) {
                continue;
            }
            $usersjson[] = ['id' => (int)$uid, 'name' => $uname];
        }
    }

    // Period label and navigation.
    if ($view === 'week') {
        $weekend = strtotime('+6 days', $weekstart);
        $periodlabel = userdate($weekstart, '%e %B') . ' - ' . userdate($weekend, '%e %B %Y');
        $step = 7;
    } else {
        $periodlabel = userdate($weekstart, '%A %e %B %Y');
        $step = 1;
    }
    $prevdate = date('Y-m-d', strtotime("-{$step} days", $weekstart));
    $nextdate = date('Y-m-d', strtotime("+{$step} days", $weekstart));
    $todaydate = date('Y-m-d', time());

    $prevurl = new moodle_url($PAGE->url, $baseparams + ['view' => $view, 'date' => $prevdate]);
    $nexturl = new moodle_url($PAGE->url, $baseparams + ['view' => $view, 'date' => $nextdate]);
    $todayurl = new moodle_url($PAGE->url, $baseparams + ['view' => $view, 'date' => $todaydate]);

    echo html_writer::div(
        html_writer::link($prevurl, '&lt;&lt;', ['class' => 'btn btn-secondary']) .
        html_writer::span($periodlabel, 'mx-3 fw-bold') .
        html_writer::link($nexturl, '&gt;&gt;', ['class' => 'btn btn-secondary']) .
        html_writer::link($todayurl, get_string('today'), ['class' => 'btn btn-secondary ms-3']),
        'd-flex align-items-center justify-content-center flex-wrap gap-2 mb-3 inventario-calendar-nav'
    );

    echo html_writer::div(
        get_string('calendardraghint', 'local_inventario'),
        'alert alert-info inventario-calendar-draghint'
    );

    echo html_writer::div('', 'inventario-cal-grid', ['id' => 'inventario-cal-grid']);

    $config = [
        'view' => $view,
        'daystart' => $daystart,
        'dayend' => $dayend,
        'slot' => $slotminutes,
        'days' => $days,
        'reservations' => $resjson,
        'objects' => $objjson,
        'users' => $usersjson,
        'canmanageall' => $canmanageall ? 1 : 0,
        'reserveurl' => (new moodle_url('/local/inventario/reservations.php'))->out(false),
        'strings' => [
            'newreservation' => get_string('calendarnewreservation', 'local_inventario'),
            'selectobjects' => get_string('calendarselectobjects', 'local_inventario'),
            'searchobjects' => get_string('calendarsearchobjects', 'local_inventario'),
            'noobjects' => get_string('calendarnoobjects', 'local_inventario'),
            'location' => get_string('location', 'local_inventario'),
            'locationrequired' => get_string('calendarlocationrequired', 'local_inventario'),
            'user' => get_string('user'),
            'create' => get_string('calendarcreate', 'local_inventario'),
            'cancel' => get_string('cancel'),
            'nothingselected' => get_string('calendarnothingselected', 'local_inventario'),
            'created' => get_string('calendarcreated', 'local_inventario'),
            'conflicts' => get_string('calendarconflicts', 'local_inventario'),
            'period' => get_string('period', 'local_inventario'),
            'allday' => get_string('calendarallday', 'local_inventario'),
        ],
    ];
    $PAGE->requires->js_call_amd('local_inventario/reservations_calendar', 'init', [$config]);
}

echo $OUTPUT->footer();
