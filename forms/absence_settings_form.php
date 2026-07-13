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
 * Form for absence system settings.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Absence settings editor.
 */
class local_inventario_absence_settings_form extends moodleform {
    /** @var array */
    private $useroptions;
    /** @var string */
    private $publickey;

    /**
     * Constructor.
     *
     * @param string $action
     * @param array $useroptions
     * @param stdClass|null $data
     */
    public function __construct($action, array $useroptions, string $publickey, ?stdClass $data = null) {
        $this->useroptions = $useroptions;
        $this->publickey = $publickey;
        parent::__construct($action);
        if ($data !== null) {
            $this->set_data($data);
        }
    }

    /**
     * Define form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'select',
            'allowedusers',
            get_string('absence_settings_allowedusers', 'local_inventario'),
            $this->useroptions,
            ['size' => 10, 'class' => 'inventario-select-multi w-100']
        );
        $mform->getElement('allowedusers')->setMultiple(true);
        $mform->setType('allowedusers', PARAM_INT);

        $mform->addElement(
            'select',
            'allowedsubstitutes',
            get_string('absence_settings_allowedsubstitutes', 'local_inventario'),
            $this->useroptions,
            ['size' => 10, 'class' => 'inventario-select-multi w-100']
        );
        $mform->getElement('allowedsubstitutes')->setMultiple(true);
        $mform->setType('allowedsubstitutes', PARAM_INT);

        $coordinatoroptions = ['0' => get_string('none')];
        foreach ($this->useroptions as $id => $name) {
            $coordinatoroptions[$id] = $name;
        }
        $mform->addElement('select', 'coordinatorid', get_string('absence_settings_coordinator', 'local_inventario'), $coordinatoroptions);
        $mform->setType('coordinatorid', PARAM_INT);

        $mform->addElement('text', 'notificationcc', get_string('absence_notification_cc', 'local_inventario'));
        $mform->setType('notificationcc', PARAM_TEXT);
        $mform->addRule('notificationcc', get_string('invalidemail'), 'email', null, 'client');

        $mform->addElement('static', 'publicpagekey', get_string('absence_public_key', 'local_inventario'), $this->publickey);

        $mform->addElement('submit', 'save', get_string('savechanges'));
    }
}
