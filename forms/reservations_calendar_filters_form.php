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
 * Reservation calendar filters form.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native Moodle form for reservations calendar filters.
 */
class local_inventario_reservations_calendar_filters_form extends moodleform {
    /**
     * Define form elements.
     */
    public function definition(): void {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];

        $monthoptions = $customdata['monthoptions'] ?? [];
        $yearoptions = $customdata['yearoptions'] ?? [];
        $siteoptions = [0 => get_string('all', 'local_inventario')] + ($customdata['siteoptions'] ?? []);
        $typeoptions = [0 => get_string('all', 'local_inventario')] + ($customdata['typeoptions'] ?? []);
        $objectoptions = [0 => get_string('all', 'local_inventario')] + ($customdata['objectoptions'] ?? []);
        $useroptions = $customdata['useroptions'] ?? [];
        $canmanageall = !empty($customdata['canmanageall']);
        $currentuserid = (int)($customdata['currentuserid'] ?? 0);

        // Preserve the active view and reference date across filter submits.
        $mform->addElement('hidden', 'view');
        $mform->setType('view', PARAM_ALPHA);
        $mform->addElement('hidden', 'date');
        $mform->setType('date', PARAM_TEXT);

        $mform->addElement('select', 'month', get_string('month'), $monthoptions);
        $mform->setType('month', PARAM_INT);

        $mform->addElement('select', 'year', get_string('year'), $yearoptions);
        $mform->setType('year', PARAM_INT);

        $mform->addElement('autocomplete', 'siteid', get_string('site', 'local_inventario'), $siteoptions, ['multiple' => false]);
        $mform->setType('siteid', PARAM_INT);

        $mform->addElement('autocomplete', 'typeid', get_string('type', 'local_inventario'), $typeoptions, ['multiple' => false]);
        $mform->setType('typeid', PARAM_INT);

        if ($canmanageall) {
            $userfieldoptions = [0 => get_string('all', 'local_inventario')] + $useroptions;
            $mform->addElement('autocomplete', 'userid', get_string('user'), $userfieldoptions, ['multiple' => false]);
            $mform->setType('userid', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'userid');
            $mform->setType('userid', PARAM_INT);
            $mform->addElement('static', 'useridinfo', get_string('user'), $useroptions[$currentuserid] ?? '');
        }

        $mform->addElement('autocomplete', 'objectid', get_string('object', 'local_inventario'), $objectoptions, ['multiple' => false]);
        $mform->setType('objectid', PARAM_INT);

        $actions = [];
        $actions[] = $mform->createElement('submit', 'submitfilter', get_string('filter', 'local_inventario'));
        $actions[] = $mform->createElement('submit', 'resetfilter', get_string('resetfilters', 'local_inventario'));
        $mform->addGroup($actions, 'filteractions', '', ' ', false);
    }
}

