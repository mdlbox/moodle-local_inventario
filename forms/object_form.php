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
 * @copyright 2026 mdlbox - https://mdlbox.com
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
    /** @var bool */
    private $ispro;

    /**
     * Build the object form with required options.
     *
     * @param string $action
     * @param array $sites
     * @param array $types
     * @param bool $allowhidden
     * @param array $properties
     * @param string $baseurl
     * @param bool $ispro
     */
    public function __construct(
        $action,
        array $sites,
        array $types,
        bool $allowhidden,
        array $properties,
        string $baseurl,
        bool $ispro
    ) {
        $this->sites = $sites;
        $this->types = $types;
        $this->allowhidden = $allowhidden;
        $this->properties = $properties;
        $this->baseurl = $baseurl;
        $this->ispro = $ispro;
        parent::__construct($action);
    }

    /**
     * Define the form fields.
     */
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
        // Stato mantenuto come hidden: gli oggetti nuovi partono da "available".
        $mform->addElement('hidden', 'status', 'available');
        $mform->setType('status', PARAM_ALPHA);
        $mform->setDefault('status', 'available');

        if ($this->ispro) {
            $mform->addElement('header', 'availabilityheader', get_string('availability', 'local_inventario'));
            $availabilityel = $mform->createElement(
                'advcheckbox',
                'availableperiodenabled',
                '',
                '',
                ['class' => 'inventario-switch']
            );
            $mform->addGroup(
                [$availabilityel],
                'availableperiodenabled_group',
                get_string('availability_enabled', 'local_inventario'),
                '',
                false
            );
            $mform->setDefault('availableperiodenabled', 0);
            $mform->setType('availableperiodenabled', PARAM_BOOL);
            $mform->addElement('date_time_selector', 'availablefrom', get_string('availability_from', 'local_inventario'));
            $mform->addElement('date_time_selector', 'availableto', get_string('availability_to', 'local_inventario'));
            $mform->addElement(
                'textarea',
                'availabletimes',
                get_string('availability_times', 'local_inventario'),
                ['rows' => 3, 'cols' => 40, 'placeholder' => "09:00-12:00\n14:00-18:00"]
            );
            $mform->addHelpButton('availabletimes', 'availability_times', 'local_inventario');
            $mform->setType('availabletimes', PARAM_RAW);
            $mform->hideIf('availablefrom', 'availableperiodenabled', 'notchecked');
            $mform->hideIf('availableto', 'availableperiodenabled', 'notchecked');
            $mform->hideIf('availabletimes', 'availableperiodenabled', 'notchecked');
        }

        if ($this->allowhidden) {
            $visibleel = $mform->createElement(
                'advcheckbox',
                'visible',
                '',
                '',
                ['class' => 'inventario-switch']
            );
            $mform->addGroup([$visibleel], 'visible_group', get_string('visible', 'local_inventario'), '', false);
            $mform->setDefault('visible', 1);
            $mform->setType('visible', PARAM_BOOL);
        }

        if (!empty($this->properties)) {
            $mform->addElement('header', 'propertiesheader', get_string('property', 'local_inventario'));
        }

        foreach ($this->properties as $property) {
            $elementname = 'prop_' . $property->id;
            $label = format_string($property->name);
            if (!empty($property->parentid)) {
                $icon = html_writer::span('', 'fa fa-level-down text-muted me-1', ['aria-hidden' => 'true']);
                $label = $icon . $label;
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
                    $propel = $mform->createElement(
                        'advcheckbox',
                        $elementname,
                        '',
                        '',
                        ['class' => 'inventario-switch']
                    );
                    $mform->addGroup([$propel], $elementname . '_group', $label, '', false);
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

    /**
     * Custom validation for availability time window.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($this->ispro && !empty($data['availableperiodenabled'])) {
            $from = (int)($data['availablefrom'] ?? 0);
            $to = (int)($data['availableto'] ?? 0);
            if ($from <= 0 || $to <= 0 || $to <= $from) {
                $errors['availableto'] = get_string('availabilityerror_range', 'local_inventario');
            }
            $slots = $this->parse_time_slots($data['availabletimes'] ?? '');
            if ($slots === false) {
                $errors['availabletimes'] = get_string('availabilityerror_format', 'local_inventario');
            }
        }

        return $errors;
    }

    /**
     * Parse time slots from textarea input.
     *
     * @param string $raw
     * @return array|false
     */
    private function parse_time_slots(string $raw) {
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
        if (empty($lines)) {
            return [];
        }
        $slots = [];
        foreach ($lines as $line) {
            if (!preg_match('/^([0-2][0-9]):([0-5][0-9])\\-([0-2][0-9]):([0-5][0-9])$/', $line, $m)) {
                return false;
            }
            $start = ((int)$m[1]) * 60 + (int)$m[2];
            $end = ((int)$m[3]) * 60 + (int)$m[4];
            if ($end <= $start) {
                return false;
            }
            $slots[] = ['start' => $start, 'end' => $end];
        }
        return $slots;
    }
}
