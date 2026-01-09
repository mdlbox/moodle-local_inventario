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
 * Public embeddable view of current and future reservations (Pro only).
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_login(null, true);
$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/inventario/public.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('publictitle', 'local_inventario'));
$PAGE->set_heading(get_string('publictitle', 'local_inventario'));

$configenabled = (bool)get_config('local_inventario', 'allowpublicpage');
$licensemanager = local_inventario_license();
$licensemanager->refresh(true); // Force fresh status to avoid stale Pro checks.
$publicfeature = $licensemanager->is_feature_enabled('publicpage');
$ispro = $licensemanager->is_pro();

// Hard block when not explicitly enabled or license is not Pro.
if (!$configenabled || !$publicfeature || !$ispro) {
    throw new \moodle_exception('publicdisabled', 'local_inventario');
}

$service = local_inventario_service();
$reservations = $service->get_reservations([], false);
$now = time();
$endofday = usergetmidnight($now) + DAYSECS;

$currenttoday = [];
$unreturned = [];
$upcoming = [];

foreach ($reservations as $reservation) {
    if ($reservation->status === 'returned') {
        continue;
    }
    $record = (object)[
        'object' => format_string($reservation->objectname),
        'site' => format_string($reservation->sitename),
        'timestart' => $reservation->timestart,
        'timeend' => $reservation->timeend,
        'status' => $reservation->status,
        'location' => format_string($reservation->location ?? ''),
    ];

    if ($reservation->status === 'active' && $reservation->timeend < $now) {
        $unreturned[] = $record;
        continue;
    }

    if ($reservation->timestart <= $now && $reservation->timeend > $now) {
        $currenttoday[] = $record;
        continue;
    }

    if ($reservation->timestart >= $now && $reservation->timestart < $endofday) {
        $currenttoday[] = $record;
        continue;
    }

    if ($reservation->timestart >= $endofday) {
        $upcoming[] = $record;
    }
}

echo $OUTPUT->header();
echo html_writer::div(get_string('publichint', 'local_inventario'), 'alert alert-info mb-4');

// Render a simple table with badge status.
$rendertable = function (string $title, array $rows): void {
    echo html_writer::tag('h3', $title, ['class' => 'h4 mb-3 mt-4']);
    if (empty($rows)) {
        echo html_writer::div(get_string('nostats', 'local_inventario'), 'alert alert-secondary');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('object', 'local_inventario'),
        get_string('site', 'local_inventario'),
        get_string('period', 'local_inventario'),
        get_string('status'),
        get_string('location', 'local_inventario'),
    ];
    $table->data = [];
    foreach ($rows as $row) {
        $statuslabel = get_string('status_' . $row->status, 'local_inventario');
        $class = 'badge bg-secondary text-white';
        if ($row->status === 'available') {
            $class = 'badge bg-success text-white';
        } else if ($row->status === 'reserved' || $row->status === 'active') {
            $class = 'badge bg-danger text-white';
        } else if ($row->status === 'offsite') {
            $class = 'badge bg-warning text-dark';
        }
        $badge = html_writer::span($statuslabel, $class);
        $period = userdate($row->timestart) . ' - ' . userdate($row->timeend);
        $table->data[] = [
            $row->object,
            $row->site,
            $period,
            $badge,
            $row->location,
        ];
    }

    echo html_writer::table($table);
};

$rendertable(get_string('publiccurrent', 'local_inventario'), $currenttoday);
$rendertable(get_string('publicunreturned', 'local_inventario'), $unreturned);
$rendertable(get_string('publicupcoming', 'local_inventario'), $upcoming);

echo $OUTPUT->footer();
