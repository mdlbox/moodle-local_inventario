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
 * Form to manage custom properties.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form per le proprietÃ  personalizzate.
 */
class local_inventario_property_form extends moodleform {
    /** @var array */
    private $parents;

    /**
     * Property form constructor.
     *
     * @param string $action
     */
    public function __construct($action) {
        $this->parents = local_inventario_service()->get_properties();
        parent::__construct($action);
    }

    /**
     * Define form elements.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('propertyname', 'local_inventario'), ['size' => 40]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_inventario'), ['size' => 25]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', get_string('required'), 'required');

        $mform->addElement('select', 'datatype', get_string('datatype', 'local_inventario'), [
            'text' => get_string('datatype_text', 'local_inventario'),
            'number' => get_string('datatype_number', 'local_inventario'),
            'bool' => get_string('datatype_bool', 'local_inventario'),
            'select' => get_string('datatype_select', 'local_inventario'),
            'group' => get_string('datatype_group', 'local_inventario'),
        ]);

        $parentoptions = ['0' => get_string('none')];
        foreach ($this->parents as $prop) {
            $parentoptions[$prop->id] = format_string($prop->name);
        }
        $mform->addElement('select', 'parentid', get_string('parentproperty', 'local_inventario'), $parentoptions);
        $mform->setDefault('parentid', 0);

        $mform->addElement(
            'textarea',
            'options',
            get_string('options', 'local_inventario'),
            ['rows' => 3, 'cols' => 50]
        );
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'options', 'local_inventario');

        $requiredel = $mform->createElement(
            'advcheckbox',
            'required',
            '',
            '',
            ['class' => 'inventario-switch']
        );
        $mform->addGroup(
            [$requiredel],
            'required_group',
            get_string('requiredfield', 'local_inventario'),
            '',
            false
        );

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_inventario'), ['size' => 5]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();
    }
}
