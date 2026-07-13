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
 * Event fired when an inventory object is deleted.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Inventory object deleted event.
 */
class object_deleted extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_inventario_objects';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:object_deleted', 'local_inventario');
    }

    /**
     * Return non-localised description.
     *
     * @return string
     */
    public function get_description() {
        $name = isset($this->other['name']) ? $this->other['name'] : '';
        return "The user with id '{$this->userid}' deleted the inventory object '{$name}' (id '{$this->objectid}').";
    }
}
