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
 * Event fired when a reservation is returned/cancelled.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Reservation cancelled (returned) event.
 */
class reservation_cancelled extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_inventario_reserv';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:reservation_cancelled', 'local_inventario');
    }

    /**
     * Return non-localised description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' returned/cancelled the reservation with id '{$this->objectid}'.";
    }

    /**
     * Return relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/inventario/reservations_list.php');
    }
}
