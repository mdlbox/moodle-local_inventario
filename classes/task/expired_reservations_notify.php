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
 * Notify users when their reservation has expired.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\task;

use core\message\message;
use core_user;

defined('MOODLE_INTERNAL') || die();


class expired_reservations_notify extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_expiredreservations', 'local_inventario');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $DB, $CFG;
        require_once($CFG->libdir . '/messagelib.php');

        $now = time();
        $sql = "SELECT r.*, u.id AS userid, o.name AS objectname
                  FROM {local_inventario_reserv} r
                  JOIN {user} u ON u.id = r.userid
             LEFT JOIN {local_inventario_objects} o ON o.id = r.objectid
                 WHERE r.timeend < :now AND (r.expirednotified IS NULL OR r.expirednotified = 0)";
        $reservations = $DB->get_records_sql($sql, ['now' => $now]);

        if (empty($reservations)) {
            return;
        }

        foreach ($reservations as $reservation) {
            $user = $DB->get_record('user', ['id' => $reservation->userid, 'deleted' => 0, 'suspended' => 0], '*', IGNORE_MISSING);
            if (!$user) {
                $DB->set_field('local_inventario_reserv', 'expirednotified', 1, ['id' => $reservation->id]);
                continue;
            }

            $objectname = $reservation->objectname ?? get_string('object', 'local_inventario');
            $enddate = userdate($reservation->timeend);

            $link = new \moodle_url('/local/inventario/reservations.php', ['id' => $reservation->id]);
            $subject = get_string('reservationexpiredsubject', 'local_inventario', $objectname);
            $body = get_string('reservationexpiredbody', 'local_inventario', (object)[
                'object' => $objectname,
                'end' => $enddate,
                'link' => $link->out(false),
            ]);

            $eventdata = new message();
            $eventdata->component = 'local_inventario';
            $eventdata->name = 'reservation_expired';
            $eventdata->userfrom = core_user::get_noreply_user();
            $eventdata->userto = $user;
            $eventdata->subject = $subject;
            $eventdata->courseid = SITEID;
            $eventdata->contexturl = $link->out(false);
            $eventdata->contexturlname = get_string('reservationexpired', 'local_inventario');
            $eventdata->fullmessage = $body;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = text_to_html($body, false, false, true);
            $eventdata->smallmessage = $subject;
            $eventdata->notification = 1;
            $sent = message_send($eventdata);

            // Fallback to direct email if message API fails.
            if (empty($sent)) {
                email_to_user($user, core_user::get_noreply_user(), $subject, $body, $eventdata->fullmessagehtml, $eventdata->contexturl);
            }

            $DB->set_field('local_inventario_reserv', 'expirednotified', 1, ['id' => $reservation->id]);
        }
    }
}

