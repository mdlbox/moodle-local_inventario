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
 * Filter form for absence dashboard.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Native Moodle form for absence list filters.
 */
class local_inventario_absence_filters_form extends moodleform {
    /**
     * Define form elements.
     */
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];

        $users = $customdata['users'] ?? [];
        $types = $customdata['types'] ?? [];

        $useroptions = [0 => get_string('all', 'local_inventario')] + $users;
        $typeoptions = [0 => get_string('all', 'local_inventario')];
        foreach ($types as $type) {
            $typeoptions[(int)$type->id] = format_string($type->name);
        }

        $mform->addElement('autocomplete', 'userid', get_string('absence_filter_user', 'local_inventario'), $useroptions, [
            'multiple' => false,
        ]);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('autocomplete', 'typeid', get_string('absencetype', 'local_inventario'), $typeoptions, [
            'multiple' => false,
        ]);
        $mform->setType('typeid', PARAM_INT);

        $mform->addElement('date_selector', 'periodstart', get_string('starttime', 'local_inventario'), ['optional' => true]);
        $mform->addElement('date_selector', 'periodend', get_string('endtime', 'local_inventario'), ['optional' => true]);

        $actions = [];
        $actions[] = $mform->createElement('submit', 'submitfilter', get_string('filter', 'local_inventario'));
        $actions[] = $mform->createElement('submit', 'resetfilter', get_string('resetfilters', 'local_inventario'));
        $mform->addGroup($actions, 'filteractions', '', ' ', false);
    }
}
