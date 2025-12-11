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
 * Form to create or edit reservations.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form per la prenotazione degli oggetti.
 */
class local_inventario_reservation_form extends moodleform {
    /** @var array */
    private $objects;
    /** @var array */
    private $sites;
    /** @var array */
    private $users;
    /** @var bool */
    private $canmanageall;
    /** @var bool */
    private $allowperiodic;

    public function __construct($action, array $objects, array $sites, array $users, bool $canmanageall, bool $allowperiodic) {
        $this->objects = $objects;
        $this->sites = $sites;
        $this->users = $users;
        $this->canmanageall = $canmanageall;
        $this->allowperiodic = $allowperiodic;
        parent::__construct($action);
    }

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'siteid', get_string('site', 'local_inventario'), $this->sites);
        $mform->setType('siteid', PARAM_INT);
        $mform->addRule('siteid', get_string('required'), 'required');

        $mform->addElement('select', 'objectid', get_string('object', 'local_inventario'), $this->objects);
        $mform->addRule('objectid', get_string('required'), 'required');
        $mform->setType('objectid', PARAM_INT);

        if ($this->canmanageall) {
            $mform->addElement('autocomplete', 'userid', get_string('user'), $this->users, ['multiple' => false]);
            $mform->setType('userid', PARAM_INT);
            $mform->addRule('userid', get_string('required'), 'required');
        }

        $mform->addElement('date_time_selector', 'timestart', get_string('starttime', 'local_inventario'));
        $mform->addElement('date_time_selector', 'timeend', get_string('endtime', 'local_inventario'));

        $mform->addElement('text', 'location', get_string('location', 'local_inventario'));
        $mform->setType('location', PARAM_TEXT);
        $mform->addRule('location', get_string('required'), 'required');

        $mform->addElement('static', 'info', '', get_string('reservationhelp', 'local_inventario'));

        if ($this->allowperiodic) {
            $mform->addElement('header', 'repeatheader', get_string('repeat', 'local_inventario'));
            $mform->addElement('advcheckbox', 'periodic', get_string('repeat', 'local_inventario'));
            $mform->addElement('text', 'repeatcount', get_string('repeatcount', 'local_inventario'), ['size' => 4]);
            $mform->setType('repeatcount', PARAM_INT);
            $mform->setDefault('repeatcount', 1);
            $mform->addElement('text', 'repeatdays', get_string('repeatdays', 'local_inventario'), ['size' => 4]);
            $mform->setType('repeatdays', PARAM_INT);
            $mform->setDefault('repeatdays', 7);
        }

        $this->add_action_buttons();
    }
}

