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
 * Absence feature settings page.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/absence_settings_form.php');
require_once(__DIR__ . '/forms/absencetype_form.php');

use local_inventario\local\absence_service;

require_login();

$context = context_system::instance();
$license = local_inventario_license();
$license->require_pro();
require_capability('local/inventario:manageabsences', $context);

$service = new absence_service();
$baseurl = new moodle_url('/local/inventario/absence_settings.php');
$action = optional_param('action', '', PARAM_ALPHA);
$typeid = optional_param('typeid', 0, PARAM_INT);

if ($action === 'regenerate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $service->regenerate_public_key();
    redirect($baseurl, get_string('absence_public_key_regenerated', 'local_inventario'));
}

if ($action === 'deletetype' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $deleteid = required_param('typeid', PARAM_INT);
    $service->delete_type($deleteid);
    redirect($baseurl, get_string('absencetype_deleted', 'local_inventario'));
}

$useroptions = $service->get_booking_user_options();
$formdata = new stdClass();
$formdata->allowedusers = $service->get_allowed_absence_user_ids();
$formdata->allowedsubstitutes = $service->get_allowed_substitute_user_ids();
$formdata->coordinatorid = $service->get_coordinator_userid();
$formdata->notificationcc = $service->get_notification_cc_email();

$publickey = $service->get_public_key();
$publicurl = (new moodle_url('/local/inventario/publicabsences.php', ['k' => $publickey]))->out(true);
$settingsform = new local_inventario_absence_settings_form($baseurl, $useroptions, $publickey, $formdata);
$types = $service->get_types();

$typeform = new local_inventario_absencetype_form(
    new moodle_url('/local/inventario/absence_settings.php', ['typeid' => $typeid])
);
$typenotice = null;
$settingnotice = null;

// Some themes/plugins interfere with QuickForm submit markers; force a safe fallback for type saving.
$manualtypesubmit = $_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($action)
    && optional_param('name', null, PARAM_TEXT) !== null
    && optional_param('color', null, PARAM_TEXT) !== null
    && optional_param('save', '', PARAM_TEXT) === '';
$typehandled = false;

if ($manualtypesubmit) {
    $typehandled = true;
    require_sesskey();
    $manualtypedata = (object)[
        'id' => optional_param('id', 0, PARAM_INT),
        'name' => optional_param('name', '', PARAM_TEXT),
        'color' => optional_param('color', '#2563eb', PARAM_TEXT),
        'requiresubstitute' => optional_param('requiresubstitute', 0, PARAM_INT),
    ];
    try {
        $service->save_type($manualtypedata);
        redirect($baseurl, get_string('absencetype_saved', 'local_inventario'));
    } catch (Throwable $e) {
        if ($e instanceof moodle_exception && !($e instanceof dml_exception)) {
            $typenotice = $e->getMessage();
        } else {
            debugging('local_inventario manual absence type save failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $typenotice = get_string('absencetype_save_failed', 'local_inventario');
        }
        $typeform->set_data($manualtypedata);
    }
}

if ($typeid && !$manualtypesubmit) {
    $existingtype = $service->get_type($typeid);
    if ($existingtype) {
        $typeform->set_data($existingtype);
    }
}

if (!$typehandled && $typeform->is_cancelled()) {
    redirect($baseurl);
}
if (!$typehandled && ($typeformdata = $typeform->get_data())) {
    try {
        $service->save_type($typeformdata);
        redirect($baseurl, get_string('absencetype_saved', 'local_inventario'));
    } catch (Throwable $e) {
        if ($e instanceof moodle_exception && !($e instanceof dml_exception)) {
            $typenotice = $e->getMessage();
        } else {
            debugging('local_inventario absence type save failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $typenotice = get_string('absencetype_save_failed', 'local_inventario');
        }
        $typeform->set_data($typeformdata);
    }
}

if ($settingsform->is_cancelled()) {
    redirect($baseurl);
}
if ($settingsformdata = $settingsform->get_data()) {
    try {
        $service->set_allowed_absence_user_ids((array)($settingsformdata->allowedusers ?? []));
        $service->set_allowed_substitute_user_ids((array)($settingsformdata->allowedsubstitutes ?? []));
        $service->set_coordinator_userid((int)($settingsformdata->coordinatorid ?? 0));
        $service->set_notification_cc_email((string)($settingsformdata->notificationcc ?? ''));
        redirect($baseurl, get_string('absencesettingssaved', 'local_inventario'));
    } catch (Throwable $e) {
        if ($e instanceof moodle_exception && !($e instanceof dml_exception)) {
            $settingnotice = $e->getMessage();
        } else {
            debugging('local_inventario absence settings save failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $settingnotice = get_string('absencesettingssavefailed', 'local_inventario');
        }
    }
}

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('absencesettings', 'local_inventario'));
$PAGE->set_heading(get_string('absencesettings', 'local_inventario'));

echo $OUTPUT->header();
echo local_inventario_render_nav($context);
if ($settingnotice !== null) {
    echo $OUTPUT->notification($settingnotice, 'error');
}
if ($typenotice !== null) {
    echo $OUTPUT->notification($typenotice, 'error');
}

?>
<div class="inventario-absence-settings">
    <div class="row g-4">
        <div class="col-md-6">
            <h2 class="h4"><?php echo get_string('absence_settings', 'local_inventario'); ?></h2>
            <?php $settingsform->display(); ?>
            <div class="mt-2">
                <form method="post" class="d-inline" action="<?php echo $baseurl->out(false); ?>">
                    <?php echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]); ?>
                    <?php echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'regenerate']); ?>
                    <button type="submit" class="btn btn-outline-secondary">
                        <?php echo get_string('absence_public_regenerate', 'local_inventario'); ?>
                    </button>
                </form>
            </div>
            <div class="mt-3">
                <p class="text-muted mb-1 small">
                    <?php echo get_string('absence_public_key_note', 'local_inventario'); ?>
                </p>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" readonly value="<?php echo s($publicurl); ?>">
                    <a class="btn btn-outline-secondary" target="_blank" rel="noreferrer noopener" href="<?php echo $publicurl; ?>">
                        <?php echo get_string('open', 'core'); ?>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <h2 class="h4 mb-3"><?php echo get_string('absencetypes', 'local_inventario'); ?></h2>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th><?php echo get_string('name'); ?></th>
                            <th><?php echo get_string('color', 'core'); ?></th>
                            <th><?php echo get_string('absencetyperequiresubstitute', 'local_inventario'); ?></th>
                            <th><?php echo get_string('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($types)): ?>
                            <tr>
                                <td colspan="4" class="text-muted text-center"><?php echo get_string('absencetypes_empty', 'local_inventario'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($types as $type): ?>
                                <tr>
                                    <td><?php echo format_string($type->name); ?></td>
                                    <td>
                                        <span class="badge inventario-type-badge" style="background-color: <?php echo s($type->color); ?>;">
                                            <?php echo s($type->color); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $type->requiresubstitute ? get_string('yes') : get_string('no'); ?></td>
                                    <td>
                                        <a href="<?php echo (new moodle_url($baseurl, ['typeid' => (int)$type->id]))->out(false); ?>"
                                           class="btn btn-sm btn-outline-primary me-1">
                                            <?php echo get_string('edit'); ?>
                                        </a>
                                        <form method="post" class="d-inline" action="<?php echo $baseurl->out(false); ?>">
                                            <?php echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]); ?>
                                            <?php echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'typeid', 'value' => $type->id]); ?>
                                            <?php echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deletetype']); ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('<?php echo get_string('absencetype_delete_confirm', 'local_inventario'); ?>');">
                                                <?php echo get_string('delete'); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <h3 class="h5 mt-4"><?php echo get_string('absencetype_add', 'local_inventario'); ?></h3>
            <?php $typeform->display(); ?>
        </div>
    </div>
</div>
<?php

echo $OUTPUT->footer();
