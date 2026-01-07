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
 * License/API key management page.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/forms/license_form.php');

require_login();
$context = context_system::instance();
require_capability('local/inventario:managelicense', $context);

$refresh = optional_param('refresh', 0, PARAM_BOOL);

$PAGE->set_url('/local/inventario/license.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('license', 'local_inventario'));
$PAGE->set_heading(get_string('license', 'local_inventario'));

$license = local_inventario_license();
$ispro = $license->is_pro();

if ($refresh && confirm_sesskey()) {
    $license->refresh(true);
    redirect($PAGE->url, get_string('licenserefreshed', 'local_inventario'));
}

$form = new local_inventario_license_form($PAGE->url);
$status = $license->get_status();
$defaultkey = \local_inventario\local\license_manager::default_free_key();
if (empty($status->apikey)) {
    // Seed with bundled Free key on fresh installs.
    $license->save_apikey($defaultkey);
    $status = $license->get_status();
}
$form->set_data(['apikey' => $status->apikey]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/inventario/index.php'));
}

if ($data = $form->get_data()) {
    $license->save_apikey($data->apikey);
    $status = $license->refresh(true);

    $message = '';
    $type = 'success';
    if ($status->status === 'pro' && !empty($status->expiresat)) {
        $message = get_string('licensevaliduntil', 'local_inventario', userdate($status->expiresat));
        $type = 'success';
    } else if ($status->status === 'pro') {
        $message = get_string('licensevalid', 'local_inventario');
        $type = 'success';
    } else if (!empty($status->apikey) &&
        $status->apikey === \local_inventario\local\license_manager::default_free_key()) {
        // Special case: bundled Free key may appear expired in backend response.
        $message = get_string('licensefreekey', 'local_inventario');
        $type = 'warning';
    } else if (!empty($status->expiresat) && $status->expiresat < time()) {
        $message = get_string('licenseexpired', 'local_inventario', userdate($status->expiresat));
        $type = 'error';
    } else {
        $details = !empty($status->lastpayload) ? $status->lastpayload : '';
        $message = get_string('licenseerror', 'local_inventario', $details ?: get_string('licenseinvalid', 'local_inventario'));
        $type = 'error';
    }
    $SESSION->local_inventario_licnotice = ['type' => $type, 'text' => $message];
    redirect($PAGE->url);
}

$status = $license->get_status();
$ispro = $license->is_pro();

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

if (!empty($SESSION->local_inventario_licnotice)) {
    $note = $SESSION->local_inventario_licnotice;
    unset($SESSION->local_inventario_licnotice);
    echo $OUTPUT->notification($note['text'], $note['type']);
}

$form->display();

$items = [
    get_string('licensestatus', 'local_inventario', $ispro ? 'pro' : $status->status),
    get_string('licensedomain', 'local_inventario', $status->domain),
];

if (!empty($status->expiresat)) {
    $items[] = get_string('licenseexpires', 'local_inventario', userdate($status->expiresat));
}

echo html_writer::alist($items);
echo html_writer::div(
    get_string(
        'license_moreinfo',
        'local_inventario',
        html_writer::link('https://mdlbox.com', 'https://mdlbox.com', ['target' => '_blank', 'rel' => 'noopener'])
    )
);

echo html_writer::div(
    html_writer::tag('h3', get_string('freelimits_title', 'local_inventario'), ['class' => 'h5 mb-2']) .
    html_writer::tag('p', get_string('freelimits_body', 'local_inventario')) .
    html_writer::tag(
        'p',
        get_string(
            'freelimits_guide',
            'local_inventario',
            html_writer::link(
                'https://mdlbox.com/knowledge_center/',
                get_string('freelimits_guide_link', 'local_inventario'),
                ['target' => '_blank', 'rel' => 'noopener']
            )
        )
    ) .
    html_writer::div(
        html_writer::link(
            'https://mdlbox.com',
            get_string('freelimits_moreinfo', 'local_inventario'),
            ['target' => '_blank', 'rel' => 'noopener']
        ),
        'mt-2'
    ),
    'card shadow-sm mt-3 p-3 mb-3'
);
echo $OUTPUT->single_button(
    new moodle_url($PAGE->url, ['refresh' => 1, 'sesskey' => sesskey()]),
    get_string('refreshlicense', 'local_inventario')
);

echo $OUTPUT->footer();
