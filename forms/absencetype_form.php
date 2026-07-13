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
 * Form used to create or edit absence types.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Absence type editor form.
 */
class local_inventario_absencetype_form extends moodleform {
    /**
     * {@inheritDoc}
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('absencetypename', 'local_inventario'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $color = $mform->addElement('text', 'color', get_string('absencetypecolor', 'local_inventario'));
        $mform->setType('color', PARAM_TEXT);
        $color->updateAttributes(['type' => 'color']);
        $mform->setDefault('color', '#2563eb');

        $mform->addElement('advcheckbox', 'requiresubstitute', get_string('absencetyperequiresubstitute', 'local_inventario'), get_string('absencetyperequiresubstitute_desc', 'local_inventario'));
        $mform->setType('requiresubstitute', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * {@inheritDoc}
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (trim($data['name'] ?? '') === '') {
            $errors['name'] = get_string('invalidabsencetypename', 'local_inventario');
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', trim($data['color'] ?? ''))) {
            $errors['color'] = get_string('invalidabsencetypecolor', 'local_inventario');
        }
        return $errors;
    }
}
