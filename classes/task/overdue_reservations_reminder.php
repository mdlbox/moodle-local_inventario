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
 * Send reminders for overdue (not returned) reservations.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\task;

use core\message\message;
use core_user;

/**
 * Scheduled task to remind users of overdue reservations.
 */
class overdue_reservations_reminder extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_overduereservations', 'local_inventario');
    }

    /**
     * Execute task.
     */
    public function execute(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/messagelib.php');

        $now = time();
        $grace = (int)get_config('local_inventario', 'overduegrace');
        if ($grace < 0) {
            $grace = 0;
        }
        $threshold = $now - ($grace * 60);
        $sql = "SELECT r.*, u.id AS userid, u.lang AS userlang, o.name AS objectname
                  FROM {local_inventario_reserv} r
                  JOIN {user} u ON u.id = r.userid
             LEFT JOIN {local_inventario_objects} o ON o.id = r.objectid
             LEFT JOIN {local_inventario_types} t ON t.id = o.typeid
                 WHERE r.status = 'active' AND r.timeend < :threshold AND COALESCE(t.requiresreturn, 1) = 1";
        $reservations = $DB->get_records_sql($sql, ['threshold' => $threshold]);

        if (empty($reservations)) {
            return;
        }

        $sm = get_string_manager();

        foreach ($reservations as $reservation) {
            $user = $DB->get_record('user', ['id' => $reservation->userid, 'deleted' => 0, 'suspended' => 0], '*', IGNORE_MISSING);
            if (!$user) {
                continue;
            }

            $lang = $user->lang ?: current_language();
            $objectname = $reservation->objectname ?? get_string('object', 'local_inventario');
            $enddate = userdate($reservation->timeend);

            $link = new \moodle_url('/local/inventario/reservations.php', ['focus' => $reservation->id]);
            $link->set_anchor('reservation-' . $reservation->id);

            $defaultsubject = $sm->get_string('reservationoverduesubject', 'local_inventario', '{object}', $lang);
            $defaultbody = $sm->get_string('reservationoverduebody', 'local_inventario', (object)[
                'object' => '{object}',
                'end' => '{end}',
                'link' => '{reservationurl}',
            ], $lang);

            $subjecttpl = $this->get_template('overdue_subject_template', $defaultsubject);
            $bodytpl = $this->get_template('overdue_body_template', $defaultbody);

            $replacements = [
                'object' => $objectname,
                'end' => $enddate,
                'reservationurl' => $link->out(false),
                'returnurl' => $link->out(false),
                'userfullname' => fullname($user),
            ];

            $subject = $this->replace_tokens($subjecttpl, $replacements);
            $body = $this->replace_tokens($bodytpl, $replacements);

            $eventdata = new message();
            $eventdata->component = 'local_inventario';
            $eventdata->name = 'reservation_overdue';
            $eventdata->userfrom = core_user::get_noreply_user();
            $eventdata->userto = $user;
            $eventdata->subject = $subject;
            $eventdata->courseid = SITEID;
            $eventdata->contexturl = $link->out(false);
            $eventdata->contexturlname = get_string_manager()->get_string('reservationoverdue', 'local_inventario', null, $lang);
            $eventdata->fullmessage = $body;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = text_to_html($body, false, false, true);
            $eventdata->smallmessage = $subject;
            $eventdata->notification = 1;
            $sent = message_send($eventdata);

            if (empty($sent)) {
                email_to_user(
                    $user,
                    core_user::get_noreply_user(),
                    $subject,
                    $body,
                    $eventdata->fullmessagehtml,
                    $eventdata->contexturl
                );
            }
        }
    }

    /**
     * Replace supported placeholders inside a template string.
     *
     * @param string $template
     * @param array $values
     * @return string
     */
    private function replace_tokens(string $template, array $values): string {
        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }
        return strtr($template, $replacements);
    }

    /**
     * Resolve configured template or fall back to the provided default.
     *
     * @param string $configname
     * @param string $default
     * @return string
     */
    private function get_template(string $configname, string $default): string {
        $value = (string)get_config('local_inventario', $configname);
        return $value !== '' ? $value : $default;
    }
}
