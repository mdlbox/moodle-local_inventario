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
 * Settings for the Inventario plugin.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_inventario', get_string('pluginname', 'local_inventario'));

    $settings->add(new admin_setting_heading(
        'local_inventario/apilockinfo',
        get_string('apiendpoint', 'local_inventario'),
        get_string('apiendpoint_desc', 'local_inventario')
    ));
    $settings->add(new admin_setting_configtext(
        'local_inventario/checkinterval',
        get_string('checkinterval', 'local_inventario'),
        get_string('checkinterval_desc', 'local_inventario'),
        3600,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_inventario/allowperiodic',
        get_string('allowperiodic', 'local_inventario'),
        get_string('allowperiodic_desc', 'local_inventario'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_inventario/overduegrace',
        get_string('overduegrace', 'local_inventario'),
        get_string('overduegrace_desc', 'local_inventario'),
        60,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_inventario/confirmationtemplates',
        get_string('reservationconfirmationheading', 'local_inventario'),
        get_string('reservationconfirmation_desc', 'local_inventario')
    ));
    $settings->add(new admin_setting_configtext(
        'local_inventario/confirmation_subject_template',
        get_string('reservationconfirmation_subject', 'local_inventario'),
        get_string('reservationconfirmation_tokens', 'local_inventario'),
        'Prenotazione confermata: {object}'
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_inventario/confirmation_body_template',
        get_string('reservationconfirmation_body', 'local_inventario'),
        get_string('reservationconfirmation_tokens', 'local_inventario'),
        "Ciao {userfullname},\n\nLa tua prenotazione è stata registrata.\n\n- Oggetto: {object}\n- Tipo: {type}\n- Sede: {site}\n- Luogo di utilizzo: {location}\n- Periodo: {start} - {end}\n\nDettagli oggetto:\n{properties}\n\nGestisci prenotazione: {reservationurl}",
        PARAM_RAW,
        60,
        8
    ));

    $settings->add(new admin_setting_heading(
        'local_inventario/notificationtemplates',
        get_string('notificationtemplates', 'local_inventario'),
        get_string('notificationtemplates_desc', 'local_inventario')
    ));
    $settings->add(new admin_setting_configtext(
        'local_inventario/overdue_subject_template',
        get_string('overdue_subject_template', 'local_inventario'),
        get_string('notificationtokenshint', 'local_inventario'),
        'Reservation overdue: {object}'
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_inventario/overdue_body_template',
        get_string('overdue_body_template', 'local_inventario'),
        get_string('notificationtokenshint', 'local_inventario'),
        'Your reservation for "{object}" is overdue since {end}. Please return the object or update the reservation: {reservationurl}',
        PARAM_RAW,
        60,
        4
    ));
    $settings->add(new admin_setting_configtext(
        'local_inventario/expired_subject_template',
        get_string('expired_subject_template', 'local_inventario'),
        get_string('notificationtokenshint', 'local_inventario'),
        'Reservation expired: {object}'
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_inventario/expired_body_template',
        get_string('expired_body_template', 'local_inventario'),
        get_string('notificationtokenshint', 'local_inventario'),
        'Your reservation for "{object}" expired on {end}. Manage it here: {reservationurl}',
        PARAM_RAW,
        60,
        4
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_inventario/allowpublicpage',
        get_string('allowpublicpage', 'local_inventario'),
        get_string('allowpublicpage_desc', 'local_inventario'),
        0
    ));
    $ADMIN->add('localplugins', $settings);
}
