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
 * Reservation list filters form.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native Moodle form for reservations list filters.
 */
class local_inventario_reservations_list_filters_form extends moodleform {
    /**
     * Define form elements.
     */
    public function definition(): void {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];

        $siteoptions = [0 => get_string('all', 'local_inventario')] + ($customdata['siteoptions'] ?? []);
        $typeoptions = [0 => get_string('all', 'local_inventario')] + ($customdata['typeoptions'] ?? []);
        $propertyoptions = [0 => get_string('all', 'local_inventario')] + ($customdata['propertyoptions'] ?? []);
        $useroptions = $customdata['useroptions'] ?? [];
        $canmanageall = !empty($customdata['canmanageall']);
        $currentuserid = (int)($customdata['currentuserid'] ?? 0);
        $perpageoptions = $customdata['perpageoptions'] ?? [10 => 10, 20 => 20, 50 => 50, 100 => 100, 0 => get_string('all', 'local_inventario')];
        $objectid = (int)($customdata['objectid'] ?? 0);

        $mform->addElement('text', 'search', get_string('search'));
        $mform->setType('search', PARAM_TEXT);

        $mform->addElement('autocomplete', 'siteid', get_string('site', 'local_inventario'), $siteoptions, ['multiple' => false]);
        $mform->setType('siteid', PARAM_INT);

        $mform->addElement('autocomplete', 'typeid', get_string('type', 'local_inventario'), $typeoptions, ['multiple' => false]);
        $mform->setType('typeid', PARAM_INT);

        $mform->addElement('autocomplete', 'propertyid', get_string('property', 'local_inventario'), $propertyoptions, ['multiple' => false]);
        $mform->setType('propertyid', PARAM_INT);

        $mform->addElement('text', 'propvalue', get_string('propertyvalue', 'local_inventario'));
        $mform->setType('propvalue', PARAM_TEXT);

        if ($canmanageall) {
            $userfieldoptions = [0 => get_string('all', 'local_inventario')] + $useroptions;
            $mform->addElement('autocomplete', 'userid', get_string('user'), $userfieldoptions, ['multiple' => false]);
            $mform->setType('userid', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'userid');
            $mform->setType('userid', PARAM_INT);
            $mform->addElement('static', 'useridinfo', get_string('user'), $useroptions[$currentuserid] ?? '');
        }

        $mform->addElement('date_selector', 'startfrom', get_string('starttime', 'local_inventario'), ['optional' => true]);
        $mform->addElement('date_selector', 'endto', get_string('endtime', 'local_inventario'), ['optional' => true]);

        $mform->addElement('select', 'perpage', get_string('itemsperpage', 'local_inventario'), $perpageoptions);
        $mform->setType('perpage', PARAM_INT);

        $mform->addElement('hidden', 'objectid', $objectid);
        $mform->setType('objectid', PARAM_INT);

        $actions = [];
        $actions[] = $mform->createElement('submit', 'submitfilter', get_string('filter', 'local_inventario'));
        $actions[] = $mform->createElement('submit', 'resetfilter', get_string('resetfilters', 'local_inventario'));
        $mform->addGroup($actions, 'filteractions', '', ' ', false);
    }
}

