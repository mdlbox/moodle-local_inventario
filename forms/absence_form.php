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
 * Form to create or edit staff absences.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Absence entry form.
 */
class local_inventario_absence_form extends moodleform {
    /** @var array */
    private $types;
    /** @var array */
    private $typesmeta;
    /** @var array */
    private $users;
    /** @var array */
    private $substitutes;
    /** @var array */
    private $subjects;
    /** @var bool */
    private $showuserselect;
    /** @var int */
    private $currentuserid;

    /**
     * Constructor.
     *
     * @param string $action
     * @param array $types
     * @param array $users
     * @param array $substitutes
     * @param array $subjects
     * @param stdClass|null $data
     */
    public function __construct(
        $action,
        array $types,
        array $users,
        array $substitutes,
        array $subjects,
        ?stdClass $data = null,
        bool $showuserselect = true,
        int $currentuserid = 0
    ) {
        $this->typesmeta = [];
        $this->types = [];
        foreach ($types as $type) {
            $this->types[(int)$type->id] = format_string($type->name);
            $this->typesmeta[(int)$type->id] = $type;
        }
        $this->users = $users;
        $this->substitutes = $substitutes;
        $this->subjects = array_values(array_unique($subjects));
        $this->showuserselect = $showuserselect;
        $this->currentuserid = $currentuserid;
        parent::__construct($action);
        if ($data !== null) {
            $this->set_data($data);
        }
    }

    /**
     * Define form elements.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if ($this->showuserselect) {
            $mform->addElement('select', 'userid', get_string('absence_user', 'local_inventario'), $this->users);
            $mform->setType('userid', PARAM_INT);
            $mform->addRule('userid', null, 'required', null, 'client');
        } else {
            $mform->addElement('hidden', 'userid');
            $mform->setType('userid', PARAM_INT);
            $mform->addElement(
                'static',
                'useridinfo',
                get_string('absence_user', 'local_inventario'),
                $this->users[$this->currentuserid] ?? ''
            );
        }

        $mform->addElement('select', 'typeid', get_string('absencetype', 'local_inventario'), $this->types);
        $mform->setType('typeid', PARAM_INT);
        $mform->addRule('typeid', null, 'required', null, 'client');

        $subject = $mform->addElement('text', 'subject', get_string('absence_subject', 'local_inventario'));
        $mform->setType('subject', PARAM_TEXT);
        $subject->updateAttributes(['class' => 'w-100']);
        $mform->addRule('subject', null, 'required', null, 'client');
        if ($this->subjects) {
            $mform->addElement('html', $this->build_subject_datalist());
        }

        $mform->addElement('date_time_selector', 'timestart', get_string('starttime', 'local_inventario'));
        $mform->addElement('date_time_selector', 'timeend', get_string('endtime', 'local_inventario'));
        $mform->setDefault('timestart', time());
        $mform->setDefault('timeend', time() + HOURSECS);
        $mform->addRule('timestart', null, 'required', null, 'client');
        $mform->addRule('timeend', null, 'required', null, 'client');

        $options = ['0' => get_string('none')];
        foreach ($this->substitutes as $id => $name) {
            $options[$id] = $name;
        }
        $mform->addElement('autocomplete', 'substituteuserid', get_string('absence_substitute', 'local_inventario'), $options, ['multiple' => false]);
        $mform->setType('substituteuserid', PARAM_INT);

        $mform->addElement('text', 'substitutename', get_string('absence_substitutename', 'local_inventario'));
        $mform->setType('substitutename', PARAM_TEXT);
        $mform->addElement('textarea', 'comment', get_string('absence_comment', 'local_inventario'), ['rows' => 4, 'wrap' => 'virtual']);
        $mform->setType('comment', PARAM_TEXT);

        $mform->addElement('submit', 'submitbutton', get_string('savechanges'));
    }

    /**
     * Custom validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $type = $this->typesmeta[(int)($data['typeid'] ?? 0)] ?? null;
        if ($type && !empty($type->requiresubstitute)) {
            if (empty($data['substituteuserid']) && trim($data['substitutename'] ?? '') === '') {
                $errors['substitutename'] = get_string('absencerequiresup', 'local_inventario');
            }
        }
        if (!empty($data['timestart']) && !empty($data['timeend']) && $data['timestart'] >= $data['timeend']) {
            $errors['timeend'] = get_string('invalidtimerange', 'local_inventario');
        }
        if (trim($data['subject'] ?? '') === '') {
            $errors['subject'] = get_string('invalidabsencesubject', 'local_inventario');
        }
        return $errors;
    }

    /**
     * Render the cached datalist for subjects.
     *
     * @return string
     */
    private function build_subject_datalist(): string {
        $options = [];
        foreach ($this->subjects as $subject) {
            $options[] = \html_writer::tag('option', s($subject));
        }
        return \html_writer::tag('datalist', implode('', $options), ['id' => 'absence-subjects']);
    }
}
