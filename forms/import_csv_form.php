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
 * Simple CSV import form for Inventario.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * CSV upload form parametrized by import kind.
 */
class local_inventario_import_csv_form extends moodleform {
    /** @var string */
    private $kind;

    /**
     * Initialise form with the target import kind.
     *
     * @param string $action
     * @param array $customdata
     */
    public function __construct($action, array $customdata) {
        $this->kind = $customdata['kind'] ?? '';
        parent::__construct($action, $customdata);
    }

    /**
     * Define form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'kind', $this->kind);
        $mform->setType('kind', PARAM_ALPHA);

        $mform->addElement('filepicker', 'csvfile', get_string('importcsvfile', 'local_inventario'), null, [
            'accepted_types' => ['.csv'],
        ]);
        $mform->addRule('csvfile', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('importcsv', 'local_inventario'));
    }
}
