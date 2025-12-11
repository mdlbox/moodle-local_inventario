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
 * Form to manage inventory objects.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form per la creazione/modifica degli oggetti.
 */
class local_inventario_object_form extends moodleform {
    /** @var array */
    private $sites;
    /** @var array */
    private $types;
    /** @var bool */
    private $allowhidden;
    /** @var array */
    private $properties;
    /** @var string */
    private $baseurl;

    public function __construct($action, array $sites, array $types, bool $allowhidden, array $properties, string $baseurl) {
        $this->sites = $sites;
        $this->types = $types;
        $this->allowhidden = $allowhidden;
        $this->properties = $properties;
        $this->baseurl = $baseurl;
        parent::__construct($action);
    }

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Sede prima del tipo.
        $mform->addElement('select', 'siteid', get_string('site', 'local_inventario'), $this->sites);
        $mform->setType('siteid', PARAM_INT);
        $mform->addRule('siteid', get_string('required'), 'required');

        $mform->addElement('select', 'typeid', get_string('type', 'local_inventario'), $this->types, [
            'class' => 'inventario-type-select',
        ]);
        $mform->addRule('typeid', get_string('required'), 'required');
        $mform->setType('typeid', PARAM_INT);
        $separator = (strpos($this->baseurl, '?') !== false) ? '&' : '?';
        $mform->getElement('typeid')->updateAttributes([
            'onchange' => "window.location.href='" . $this->baseurl . $separator . "typeid=' + encodeURIComponent(this.value);",
        ]);

        $mform->addElement('text', 'name', get_string('objectname', 'local_inventario'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 4, 'cols' => 50]);
        $mform->setType('description', PARAM_RAW);

        $mform->addElement('select', 'status', get_string('status', 'local_inventario'), [
            'available' => get_string('status_available', 'local_inventario'),
            'reserved' => get_string('status_reserved', 'local_inventario'),
        ]);
        $mform->setDefault('status', 'available');

        if ($this->allowhidden) {
            $mform->addElement('advcheckbox', 'visible', get_string('visible', 'local_inventario'),
                get_string('visibilitytoggle', 'local_inventario'));
            $mform->setDefault('visible', 1);
        }

        if (!empty($this->properties)) {
            $mform->addElement('header', 'propertiesheader', get_string('property', 'local_inventario'));
        }

        foreach ($this->properties as $property) {
            $elementname = 'prop_' . $property->id;
            $label = format_string($property->name);
            if (!empty($property->parentid)) {
                $label = 'â†³ ' . $label;
            }
            switch ($property->datatype) {
                case 'group':
                    $mform->addElement('static', $elementname . '_group', $label, '');
                    break;
                case 'number':
                    $mform->addElement('text', $elementname, $label);
                    $mform->setType($elementname, PARAM_RAW);
                    break;
                case 'bool':
                    $mform->addElement('advcheckbox', $elementname, $label);
                    $mform->setType($elementname, PARAM_BOOL);
                    break;
                case 'select':
                    $options = array_map('trim', explode(',', (string)$property->options));
                    $options = array_combine($options, $options);
                    $mform->addElement('select', $elementname, $label, $options);
                    $mform->setType($elementname, PARAM_TEXT);
                    break;
                case 'text':
                default:
                    $mform->addElement('text', $elementname, $label);
                    $mform->setType($elementname, PARAM_TEXT);
                    break;
            }

            if ($property->required) {
                $mform->addRule($elementname, get_string('required'), 'required');
            }
        }

        $this->add_action_buttons();
    }
}

