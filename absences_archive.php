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
 * Archive dashboard for past staff absences.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/absence_filters_form.php');

use local_inventario\local\absence_service;

require_login();

$context = context_system::instance();
$license = local_inventario_license();
$license->require_pro();

$canview = has_capability('local/inventario:addabsence', $context)
    || has_capability('local/inventario:manageabsences', $context);
if (!$canview) {
    throw new required_capability_exception($context, 'local/inventario:addabsence', 'nopermissions', '');
}

$service = new absence_service();
$baseurl = new moodle_url('/local/inventario/absences_archive.php');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $service->delete_absence($id, $USER->id, true);
    redirect($baseurl, get_string('absencedeleted', 'local_inventario'));
}

$userid = optional_param('userid', 0, PARAM_INT);
$typeid = optional_param('typeid', 0, PARAM_INT);
$periodstart = optional_param('periodstart', '', PARAM_TEXT);
$periodend = optional_param('periodend', '', PARAM_TEXT);
$resetfilters = optional_param('resetfilter', '', PARAM_TEXT);
if ($resetfilters !== '') {
    redirect($baseurl);
}

$datepattern = '/^\d{4}-\d{2}-\d{2}$/';
if ($periodstart !== '' && !preg_match($datepattern, $periodstart)) {
    $periodstart = '';
}
if ($periodend !== '' && !preg_match($datepattern, $periodend)) {
    $periodend = '';
}

$periodstartts = $periodstart ? strtotime($periodstart) : 0;
$periodendts = $periodend ? strtotime($periodend . ' 23:59:59') : 0;

$types = $service->get_types();
$isabsenceadmin = $service->is_absence_admin($USER->id);
$users = $isabsenceadmin ? $service->get_booking_user_options() : [];
$filterform = new local_inventario_absence_filters_form(
    $baseurl,
    ['users' => $users, 'types' => $types],
    'get',
    '',
    ['class' => 'mform inventario-filters']
);

if ($filterdata = $filterform->get_data()) {
    $userid = (int)($filterdata->userid ?? 0);
    $typeid = (int)($filterdata->typeid ?? 0);

    $periodstartday = !empty($filterdata->periodstart) ? (int)$filterdata->periodstart : 0;
    $periodendday = !empty($filterdata->periodend) ? (int)$filterdata->periodend : 0;

    $periodstartts = $periodstartday;
    $periodendts = $periodendday ? ($periodendday + DAYSECS - 1) : 0;

    $periodstart = $periodstartday ? userdate($periodstartday, '%Y-%m-%d') : '';
    $periodend = $periodendday ? userdate($periodendday, '%Y-%m-%d') : '';
}

$filterdefaults = (object)[
    'userid' => $userid,
    'typeid' => $typeid,
];
if ($periodstart !== '') {
    $filterdefaults->periodstart = strtotime($periodstart . ' 00:00:00');
}
if ($periodend !== '') {
    $filterdefaults->periodend = strtotime($periodend . ' 00:00:00');
}
$filterform->set_data($filterdefaults);

$filters = [
    'archived' => 1,
];
if ($userid > 0) {
    $filters['userid'] = $userid;
}
if ($typeid > 0) {
    $filters['typeid'] = $typeid;
}
if ($periodstartts > 0) {
    $filters['periodstart'] = $periodstartts;
}
if ($periodendts > 0) {
    $filters['periodend'] = $periodendts;
}

// Non-administrators may only view their own absences.
if (!$isabsenceadmin) {
    $filters['userid'] = $USER->id;
}

$absences = array_values($service->get_absences($filters));
usort($absences, static function($a, $b) {
    $archivedcmp = ((int)$b->timearchived <=> (int)$a->timearchived);
    if ($archivedcmp !== 0) {
        return $archivedcmp;
    }
    return ((int)$b->timeend <=> (int)$a->timeend);
});

$publicpageurl = new moodle_url('/local/inventario/publicabsences.php');
$publickey = $service->get_public_key();
$publicpageurl->param('k', $publickey);

$PAGE->set_url(new moodle_url('/local/inventario/absences_archive.php', array_filter([
    'userid' => $userid,
    'typeid' => $typeid,
    'periodstart' => $periodstart,
    'periodend' => $periodend,
])));
$PAGE->set_context($context);
$PAGE->set_title(get_string('absence_archive', 'local_inventario'));
$PAGE->set_heading(get_string('absence_archive', 'local_inventario'));

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

$canaddabsence = has_capability('local/inventario:addabsence', $context)
    || has_capability('local/inventario:manageabsences', $context);
$canconfigure = has_capability('local/inventario:manageabsences', $context);
$candeletearchived = $service->can_delete_archived_absences($USER->id);

?>
<div class="inventario-absence-dashboard mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="h4 mb-0"><?php echo get_string('absence_archive', 'local_inventario'); ?></h2>
            <p class="text-muted mb-0 small"><?php echo get_string('absence_archive_description', 'local_inventario'); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($canaddabsence): ?>
                <a class="btn btn-primary" href="<?php echo (new moodle_url('/local/inventario/absence.php'))->out(); ?>">
                    <?php echo get_string('absence_add', 'local_inventario'); ?>
                </a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?php echo (new moodle_url('/local/inventario/absences.php'))->out(); ?>">
                <?php echo get_string('absence_dashboard', 'local_inventario'); ?>
            </a>
            <?php if ($canconfigure): ?>
                <a class="btn btn-outline-secondary" href="<?php echo (new moodle_url('/local/inventario/absence_settings.php'))->out(); ?>">
                    <?php echo get_string('absencesettings', 'local_inventario'); ?>
                </a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" target="_blank" rel="noreferrer noopener" href="<?php echo $publicpageurl->out(false); ?>">
                <?php echo get_string('absence_public_page', 'local_inventario'); ?>
            </a>
        </div>
    </div>

    <?php echo local_inventario_render_filter_card($filterform->render()); ?>

    <div class="table-responsive">
        <table class="generaltable table table-striped inventario-absence-table w-100">
            <thead>
                <tr>
                    <th><?php echo get_string('teacher', 'local_inventario'); ?></th>
                    <th><?php echo get_string('period', 'local_inventario'); ?></th>
                    <th><?php echo get_string('absence_subject', 'local_inventario'); ?></th>
                    <th><?php echo get_string('absencetype', 'local_inventario'); ?></th>
                    <th><?php echo get_string('absence_substitute', 'local_inventario'); ?></th>
                    <th><?php echo get_string('absence_comment', 'local_inventario'); ?></th>
                    <th><?php echo get_string('absence_archivedon', 'local_inventario'); ?></th>
                    <th><?php echo get_string('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($absences)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <?php echo get_string('absence_archive_empty', 'local_inventario'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($absences as $absence): ?>
                        <?php
                        $teacher = (object)['firstname' => $absence->teacherfirstname, 'lastname' => $absence->teacherlastname];
                        $teachername = fullname($teacher);
                        $substitutename = $absence->substitutename
                            ?: trim(($absence->substitutefirstname ?? '') . ' ' . ($absence->substitutefamilyname ?? ''));
                        $period = userdate($absence->timestart) . ' - ' . userdate($absence->timeend);
                        $archivedon = !empty($absence->timearchived) ? userdate((int)$absence->timearchived) : '-';
                        ?>
                        <tr>
                            <td><?php echo s($teachername); ?></td>
                            <td><?php echo s($period); ?></td>
                            <td><?php echo s($absence->subject); ?></td>
                            <td>
                                <span class="badge inventario-type-badge" style="background-color: <?php echo s($absence->typecolor); ?>;">
                                    <?php echo format_string($absence->typename); ?>
                                </span>
                            </td>
                            <td><?php echo $substitutename ? s($substitutename) : '-'; ?></td>
                            <td>
                                <?php if (!empty($absence->comment)): ?>
                                    <?php echo format_text($absence->comment, FORMAT_PLAIN); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo s($archivedon); ?></td>
                            <td>
                                <?php if ($candeletearchived): ?>
                                    <?php
                                    $deletelabel = get_string('delete');
                                    $deleteicon = $OUTPUT->pix_icon('t/delete', $deletelabel);
                                    ?>
                                    <div class="inventario-object-actions inventario-absence-actions">
                                        <form method="post" action="<?php echo $baseurl->out(); ?>" class="d-inline mb-0">
                                            <input type="hidden" name="id" value="<?php echo (int)$absence->id; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <?php echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]); ?>
                                            <button type="submit"
                                                    class="inventario-object-action"
                                                    title="<?php echo s($deletelabel); ?>"
                                                    aria-label="<?php echo s($deletelabel); ?>"
                                                    onclick="return confirm('<?php echo get_string('confirmdeletearchivedabsence', 'local_inventario'); ?>');">
                                                <?php echo $deleteicon; ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small"><?php echo get_string('notallowed', 'core'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php

echo $OUTPUT->footer();
