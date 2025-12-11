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
 * Form to manage object types.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form per la gestione dei tipi di oggetto.
 */
class local_inventario_type_form extends moodleform {
    /** @var array */
    private $properties;

    public function __construct($action, array $properties) {
        $this->properties = $properties;
        parent::__construct($action);
    }

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('typename', 'local_inventario'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 3, 'cols' => 50]);
        $mform->setType('description', PARAM_RAW);

        // Color input using native HTML picker (fallback to text if not supported).
        $color = $mform->addElement('text', 'color', get_string('typecolor', 'local_inventario'), [
            'size' => 10,
            'maxlength' => 7,
        ]);
        $color->updateAttributes(['type' => 'color']);
        $mform->setDefault('color', '#2563eb');
        $mform->addRule('color', get_string('required'), 'required');
        $mform->addRule('color', get_string('invalidcolor', 'local_inventario'), 'regex', '/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/');
        $mform->setType('color', PARAM_TEXT);

        $options = [];
        foreach ($this->properties as $prop) {
            $options[$prop->id] = format_string($prop->name);
        }
        $mform->addElement('select', 'properties', get_string('typeproperties', 'local_inventario'), $options, ['multiple' => true, 'size' => 8]);
        $mform->setType('properties', PARAM_RAW);

        $this->add_action_buttons();
    }
}

