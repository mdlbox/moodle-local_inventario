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
 * Public-facing view for staff absences.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
use local_inventario\local\absence_service;

$context = context_system::instance();
$license = local_inventario_license();
$license->require_pro();

$service = new absence_service();
$key = optional_param('k', '', PARAM_ALPHANUMEXT);
if ($key === '' || !hash_equals($service->get_public_key(), $key)) {
    throw new moodle_exception('invalidpublickey', 'local_inventario');
}

$today = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
$tomorrow = $today->modify('+1 day');
$dayafter = $today->modify('+2 days');
$now = time();

$format = '%e %B %Y';
$tomorrowlabel = trim(userdate($tomorrow->getTimestamp(), $format));
$dayafterlabel = trim(userdate($dayafter->getTimestamp(), $format));

$todayabsences = array_values($service->get_absences([
    'overlapstart' => $today->getTimestamp(),
    'overlapend' => $today->getTimestamp() + DAYSECS - 1,
]));
$tomorrowabsences = array_values($service->get_absences([
    'overlapstart' => $tomorrow->getTimestamp(),
    'overlapend' => $tomorrow->getTimestamp() + DAYSECS - 1,
]));
$dayafterabsences = array_values($service->get_absences([
    'overlapstart' => $dayafter->getTimestamp(),
    'overlapend' => $dayafter->getTimestamp() + DAYSECS - 1,
]));
sort_public_absences($todayabsences, $now);
sort_public_absences($tomorrowabsences, $now);
sort_public_absences($dayafterabsences, $now);

$PAGE->set_url(new moodle_url('/local/inventario/publicabsences.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('absence_public_title', 'local_inventario'));
$PAGE->set_heading('');

echo $OUTPUT->header();
?>
<div class="inventario-public container-fluid">
    <div class="inventario-public-shell">
        <div class="inventario-public-header mb-4">
            <div>
                <h1 class="h3 mb-1"><?php echo get_string('absence_public_title', 'local_inventario'); ?></h1>
                <p class="inventario-public-subtitle mb-0"><?php echo get_string('absence_public_subtitle', 'local_inventario'); ?></p>
            </div>
            <div class="inventario-public-clockbox" aria-live="polite">
                <div id="inventario-public-clock-date" class="inventario-public-clock-date">
                    <?php echo userdate($now, get_string('strftimedate', 'langconfig')); ?>
                </div>
                <div id="inventario-public-clock-time" class="inventario-public-clock-time">
                    <?php echo userdate($now, get_string('strftimetime', 'langconfig')); ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-9 inventario-public-left">
                <div class="inventario-public-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h4 mb-0"><?php echo get_string('absence_public_today_label', 'local_inventario'); ?></h2>
                        <span class="badge bg-primary"><?php echo count($todayabsences); ?></span>
                    </div>
                    <?php if (empty($todayabsences)): ?>
                        <p class="text-muted"><?php echo get_string('absence_public_empty', 'local_inventario'); ?></p>
                    <?php else: ?>
                        <?php foreach ($todayabsences as $absence): ?>
                            <?php echo render_public_entry($absence, $now); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-3 inventario-public-right d-flex flex-column gap-3">
                <div class="inventario-public-mini">
                    <h4 class="h6 text-uppercase mb-2">
                        <?php echo get_string('absence_public_day_label', 'local_inventario', $tomorrowlabel); ?>
                    </h4>
                    <?php if (empty($tomorrowabsences)): ?>
                        <p class="text-muted small"><?php echo get_string('absence_public_empty', 'local_inventario'); ?></p>
                    <?php else: ?>
                        <?php foreach ($tomorrowabsences as $absence): ?>
                            <?php echo render_public_entry($absence, $now); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="inventario-public-mini">
                    <h4 class="h6 text-uppercase mb-2">
                        <?php echo get_string('absence_public_day_label', 'local_inventario', $dayafterlabel); ?>
                    </h4>
                    <?php if (empty($dayafterabsences)): ?>
                        <p class="text-muted small"><?php echo get_string('absence_public_empty', 'local_inventario'); ?></p>
                    <?php else: ?>
                        <?php foreach ($dayafterabsences as $absence): ?>
                            <?php echo render_public_entry($absence, $now); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var datenode = document.getElementById('inventario-public-clock-date');
    var timenode = document.getElementById('inventario-public-clock-time');
    if (!datenode || !timenode) {
        return;
    }
    function updatetime() {
        var now = new Date();
        datenode.textContent = now.toLocaleDateString(undefined, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: '2-digit'
        });
        timenode.textContent = now.toLocaleTimeString(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
    updatetime();
    window.setInterval(updatetime, 1000);
})();
</script>
<?php

echo $OUTPUT->footer();

/**
 * Render a single absence card for the public view.
 *
 * @param stdClass $absence
 * @param int $now
 * @return string
 */
function render_public_entry(stdClass $absence, int $now): string {
    $teacher = (object)['firstname' => $absence->teacherfirstname, 'lastname' => $absence->teacherlastname];
    $teachername = fullname($teacher);
    $initials = strtoupper(substr($absence->teacherfirstname ?? '', 0, 1) . substr($absence->teacherlastname ?? '', 0, 1));
    if (trim($initials) === '') {
        $initials = strtoupper(substr($teachername, 0, 2));
    }
    $substitute = $absence->substitutename ?: trim(($absence->substitutefirstname ?? '') . ' ' . ($absence->substitutefamilyname ?? ''));
    $isactive = $absence->timestart <= $now && $absence->timeend >= $now;
    $period = userdate($absence->timestart, get_string('strftimedatetime', 'langconfig')) .
        ' - ' .
        userdate($absence->timeend, get_string('strftimedatetime', 'langconfig'));

    $entryclasses = 'inventario-public-entry mb-3' . ($isactive ? ' inventario-public-entry-active' : '');
    $item = html_writer::start_div($entryclasses);
    $item .= html_writer::tag('div', s($initials), ['class' => 'inventario-public-initials']);
    $content = html_writer::start_div('inventario-public-entry-body');
    $content .= html_writer::start_div('inventario-public-entry-head');
    $content .= html_writer::tag('h5', s($teachername), ['class' => 'h6 mb-0']);
    if ($isactive) {
        $content .= html_writer::tag('span', get_string('absence_active', 'local_inventario'), ['class' => 'badge inventario-public-active-badge']);
    }
    $content .= html_writer::end_div();
    $content .= html_writer::tag('div', s($absence->subject), ['class' => 'inventario-public-subject']);
    $type = html_writer::tag('span', format_string($absence->typename), [
        'class' => 'badge inventario-type-badge',
        'style' => 'background-color: ' . s($absence->typecolor) . ';',
    ]);
    $meta = $type;
    $meta .= html_writer::tag('span', s($period), ['class' => 'inventario-public-period']);
    $content .= html_writer::div($meta, 'inventario-public-meta');
    if ($substitute) {
        $content .= html_writer::tag('div', get_string('absence_public_substitute', 'local_inventario', s($substitute)), ['class' => 'inventario-public-line']);
    }
    // The free-text comment may contain sensitive personal data and is intentionally
    // not rendered on this public (potentially unauthenticated) board.
    $content .= html_writer::end_div();
    $item .= $content;
    $item .= html_writer::end_div();
    return $item;
}

/**
 * Sort public absence list with active entries first, then by start time.
 *
 * @param array $absences
 * @param int $now
 */
function sort_public_absences(array &$absences, int $now): void {
    usort($absences, static function(stdClass $a, stdClass $b) use ($now): int {
        $aactive = ((int)$a->timestart <= $now) && ((int)$a->timeend >= $now);
        $bactive = ((int)$b->timestart <= $now) && ((int)$b->timeend >= $now);
        if ($aactive !== $bactive) {
            return $aactive ? -1 : 1;
        }
        if ((int)$a->timestart === (int)$b->timestart) {
            return ((int)$a->timeend <=> (int)$b->timeend);
        }
        return ((int)$a->timestart <=> (int)$b->timestart);
    });
}
