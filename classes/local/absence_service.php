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
 * Service for staff absence management.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\local;

use context_system;
use core\message\message;
use core_user;
use dml_exception;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles business logic for absence data, settings and notifications.
 */
class absence_service {
    private const CONFIG_ALLOWED_USERS = 'absence_allowed_users';
    private const CONFIG_ALLOWED_SUBSTITUTES = 'absence_allowed_substitutes';
    private const CONFIG_COORDINATOR = 'absence_coordinator';
    private const CONFIG_PUBLIC_KEY = 'absence_public_key';
    private const CONFIG_NOTIFICATION_CC = 'absence_notification_cc';

    /** @var bool */
    private $archivechecked = false;

    /**
     * Return all absence type records.
     *
     * @return array
     */
    public function get_types(): array {
        global $DB;
        return $DB->get_records('local_inventario_abs_types', null, 'name ASC');
    }

    /**
     * Return a single type or null.
     *
     * @param int $id
     * @return stdClass|null
     */
    public function get_type(int $id): ?stdClass {
        global $DB;
        return $DB->get_record('local_inventario_abs_types', ['id' => $id], '*', IGNORE_MISSING);
    }

    /**
     * Create or update a type.
     *
     * @param stdClass $data
     * @return int
     * @throws moodle_exception
     * @throws dml_exception
     */
    public function save_type(stdClass $data): int {
        global $DB;

        $context = context_system::instance();
        require_capability('local/inventario:manageabsences', $context);

        $name = trim($data->name ?? '');
        if ($name === '') {
            throw new moodle_exception('invalidabsencetypename', 'local_inventario');
        }
        $color = trim($data->color ?? '#2563eb');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new moodle_exception('invalidabsencetypecolor', 'local_inventario');
        }

        $now = time();
        $record = (object)[
            'name' => $name,
            'color' => $color,
            'requiresubstitute' => !empty($data->requiresubstitute) ? 1 : 0,
            'timemodified' => $now,
        ];

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $existing = $this->get_type($record->id);
            if (!$existing) {
                throw new moodle_exception('invalidabsencetype', 'local_inventario');
            }
            $DB->update_record('local_inventario_abs_types', $record);
            return $record->id;
        }

        $record->timecreated = $now;
        $record->id = $DB->insert_record('local_inventario_abs_types', $record);
        return $record->id;
    }

    /**
     * Delete a type if unused.
     *
     * @param int $id
     * @throws moodle_exception
     */
    public function delete_type(int $id): void {
        global $DB;

        $context = context_system::instance();
        require_capability('local/inventario:manageabsences', $context);

        if ($DB->record_exists('local_inventario_absences', ['typeid' => $id])) {
            throw new moodle_exception('absencetypeinuse', 'local_inventario');
        }
        $DB->delete_records('local_inventario_abs_types', ['id' => $id]);
    }

    /**
     * Fetch a single absence.
     *
     * @param int $id
     * @return stdClass|null
     */
    public function get_absence(int $id): ?stdClass {
        global $DB;
        return $DB->get_record('local_inventario_absences', ['id' => $id], '*', IGNORE_MISSING);
    }

    /**
     * Archive expired absences.
     *
     * @param int|null $referencetime
     * @return int number of archived rows
     */
    public function archive_expired_absences(?int $referencetime = null): int {
        global $DB;

        $now = $referencetime ?? time();
        $selectparams = [
            'archivedno' => 0,
            'now' => $now,
        ];

        $count = (int)$DB->count_records_select(
            'local_inventario_absences',
            'archived = :archivedno AND timeend < :now',
            $selectparams
        );
        if ($count <= 0) {
            return 0;
        }

        $select = 'archived = :archivedno AND timeend < :timelimit';
        $selectparams = ['archivedno' => 0, 'timelimit' => $now];
        // Set the archived flag last so the selection still matches for the timestamps.
        $DB->set_field_select('local_inventario_absences', 'timearchived', $now, $select, $selectparams);
        $DB->set_field_select('local_inventario_absences', 'timemodified', $now, $select, $selectparams);
        $DB->set_field_select('local_inventario_absences', 'archived', 1, $select, $selectparams);
        return $count;
    }

    /**
     * Run auto-archive once per request.
     */
    private function archive_expired_absences_once(): void {
        if ($this->archivechecked) {
            return;
        }
        $this->archive_expired_absences();
        $this->archivechecked = true;
    }

    /**
     * Fetch absence entries with optional filters.
     *
     * @param array $filters
     * @return array
     */
    public function get_absences(array $filters = []): array {
        global $DB;

        $this->archive_expired_absences_once();

        $params = [];
        $conditions = [];
        if (!empty($filters['archived'])) {
            $conditions[] = 'a.archived = :archived';
            $params['archived'] = 1;
        } else {
            $conditions[] = 'a.archived = :archived';
            $params['archived'] = 0;
        }
        if (!empty($filters['userid'])) {
            $conditions[] = 'a.userid = :userid';
            $params['userid'] = (int)$filters['userid'];
        }
        if (!empty($filters['typeid'])) {
            $conditions[] = 'a.typeid = :typeid';
            $params['typeid'] = (int)$filters['typeid'];
        }
        if (!empty($filters['periodstart'])) {
            $conditions[] = 'a.timestart >= :periodstart';
            $params['periodstart'] = (int)$filters['periodstart'];
        }
        if (!empty($filters['periodend'])) {
            $conditions[] = 'a.timeend <= :periodend';
            $params['periodend'] = (int)$filters['periodend'];
        }
        if (!empty($filters['overlapstart'])) {
            $conditions[] = 'a.timeend >= :overlapstart';
            $params['overlapstart'] = (int)$filters['overlapstart'];
        }
        if (!empty($filters['overlapend'])) {
            $conditions[] = 'a.timestart <= :overlapend';
            $params['overlapend'] = (int)$filters['overlapend'];
        }

        $sql = "SELECT
                    a.*,
                    t.name AS typename,
                    t.color AS typecolor,
                    t.requiresubstitute,
                    u.firstname AS teacherfirstname,
                    u.lastname AS teacherlastname,
                    su.firstname AS substitutefirstname,
                    su.lastname AS substitutefamilyname,
                    su.email AS substituteemail
                FROM {local_inventario_absences} a
                JOIN {local_inventario_abs_types} t ON t.id = a.typeid
                JOIN {user} u ON u.id = a.userid
                LEFT JOIN {user} su ON su.id = a.substituteuserid";

        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY a.timestart DESC';

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Persist an absence record.
     *
     * @param stdClass $data
     * @param int $currentuserid
     * @return int
     * @throws moodle_exception
     */
    public function save_absence(stdClass $data, int $currentuserid): int {
        global $DB;

        $now = time();
        $isadmin = $this->is_absence_admin($currentuserid);
        $existing = !empty($data->id) ? $this->get_absence((int)$data->id) : null;

        if ($existing && $existing->timeend < $now) {
            throw new moodle_exception('absencepastedit', 'local_inventario');
        }

        $targetuserid = $isadmin ? ((int)($data->userid ?? $existing->userid ?? 0)) : $currentuserid;
        if ($targetuserid <= 0) {
            throw new moodle_exception('invalidabsenceuser', 'local_inventario');
        }

        if (!$isadmin && $targetuserid !== $currentuserid) {
            throw new moodle_exception('invalidabsenceuser', 'local_inventario');
        }

        if (!$this->is_allowed_absence_user($currentuserid)) {
            throw new moodle_exception('usernotallowedabsence', 'local_inventario');
        }

        $typeid = (int)($data->typeid ?? 0);
        $type = $this->get_type($typeid);
        if (!$type) {
            throw new moodle_exception('invalidabsencetype', 'local_inventario');
        }

        $subject = trim($data->subject ?? '');
        if ($subject === '') {
            throw new moodle_exception('invalidabsencesubject', 'local_inventario');
        }

        $timestart = (int)($data->timestart ?? 0);
        $timeend = (int)($data->timeend ?? 0);
        if ($timestart <= 0 || $timeend <= 0 || $timeend <= $timestart) {
            throw new moodle_exception('invalidtimerange', 'local_inventario');
        }

        $substitutename = trim($data->substitutename ?? '');
        $substituteid = (int)($data->substituteuserid ?? 0);
        if (!empty($substituteid) && !$this->is_allowed_substitute_user($substituteid)) {
            throw new moodle_exception('invalidabsencesubstitute', 'local_inventario');
        }

        if ($type->requiresubstitute && $substituteid === 0 && $substitutename === '') {
            throw new moodle_exception('absencerequiresup', 'local_inventario');
        }

        $record = (object)[
            'userid' => $targetuserid,
            'typeid' => $typeid,
            'subject' => $subject,
            'timestart' => $timestart,
            'timeend' => $timeend,
            'substituteuserid' => $substituteid,
            'substitutename' => $substitutename,
            'comment' => trim($data->comment ?? ''),
            'createdby' => $existing ? $existing->createdby : $currentuserid,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_inventario_absences', $record);
            $saved = (object)$record;
        } else {
            $record->timecreated = $now;
            $record->archived = 0;
            $record->timearchived = 0;
            $record->id = $DB->insert_record('local_inventario_absences', $record);
            $saved = (object)$DB->get_record('local_inventario_absences', ['id' => $record->id]);
            \local_inventario\event\absence_created::create([
                'objectid' => $record->id,
                'context' => context_system::instance(),
            ])->trigger();
        }

        $saved->type = $type;
        $saved->isnew = empty($existing);
        $this->notify_absence($saved);

        return $record->id;
    }

    /**
     * Delete an absence entry.
     *
     * @param int $id
     * @param int $currentuserid
     * @throws moodle_exception
     */
    public function delete_absence(int $id, int $currentuserid, bool $allowarchived = false): void {
        global $DB;

        $absence = $this->get_absence($id);
        if (!$absence) {
            throw new moodle_exception('invalidabsencedata', 'local_inventario');
        }

        if (!empty($absence->archived)) {
            if (!$allowarchived || !$this->can_delete_archived_absences($currentuserid)) {
                throw new moodle_exception('absencearchivenodelete', 'local_inventario');
            }
            $DB->delete_records('local_inventario_absences', ['id' => $id]);
            \local_inventario\event\absence_deleted::create([
                'objectid' => $id,
                'context' => context_system::instance(),
            ])->trigger();
            return;
        }

        if ($absence->timeend < time()) {
            throw new moodle_exception('absencepastnodelete', 'local_inventario');
        }

        if (!$this->can_manage_entry($absence, $currentuserid)) {
            throw new moodle_exception('invalidabsencedata', 'local_inventario');
        }

        $DB->delete_records('local_inventario_absences', ['id' => $id]);
        \local_inventario\event\absence_deleted::create([
            'objectid' => $id,
            'context' => context_system::instance(),
        ])->trigger();
    }

    /**
     * Return previously used subjects.
     *
     * @return array
     */
    public function get_subject_suggestions(): array {
        global $DB;
        $records = $DB->get_records_sql(
            'SELECT DISTINCT subject
               FROM {local_inventario_absences}
              WHERE subject <> \'\' AND archived = 0
           ORDER BY subject ASC'
        );
        return array_map(static function ($record) {
            return $record->subject;
        }, $records);
    }

    /**
     * Return user options for absence creation.
     *
     * @return array
     */
    public function get_booking_user_options(): array {
        $context = context_system::instance();
        $users = get_users_by_capability(
            $context,
            'local/inventario:addabsence',
            'u.id, u.firstname, u.lastname',
            'u.lastname ASC, u.firstname ASC',
            '',
            '',
            '',
            '',
            false
        );
        $options = [];
        foreach ($users as $user) {
            $options[$user->id] = fullname($user);
        }
        return $options;
    }

    /**
     * Return substitute options filtered by allowed list.
     *
     * @return array
     */
    public function get_substitute_user_options(): array {
        $options = $this->get_booking_user_options();
        $allowed = $this->get_allowed_substitute_user_ids();
        if (empty($allowed)) {
            return $options;
        }
        return array_filter($options, static function ($name, $id) use ($allowed) {
            return in_array((int)$id, $allowed, true);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Determine if a user is allowed to add absences.
     *
     * @param int $userid
     * @return bool
     */
    public function is_allowed_absence_user(int $userid): bool {
        if ($this->is_absence_admin($userid)) {
            return true;
        }
        $allowed = $this->get_allowed_absence_user_ids();
        if (empty($allowed)) {
            $options = array_keys($this->get_booking_user_options());
            return in_array($userid, $options, true);
        }
        return in_array($userid, $allowed, true);
    }

    /**
     * Determine if a substitute user is allowed.
     *
     * @param int $userid
     * @return bool
     */
    public function is_allowed_substitute_user(int $userid): bool {
        $allowed = $this->get_allowed_substitute_user_ids();
        if (empty($allowed)) {
            $options = array_keys($this->get_booking_user_options());
            return in_array($userid, $options, true);
        }
        return in_array($userid, $allowed, true);
    }

    /**
     * Get configured coordinator id.
     *
     * @return int
     */
    public function get_coordinator_userid(): int {
        return (int)get_config('local_inventario', self::CONFIG_COORDINATOR, 0);
    }

    /**
     * Set coordinator.
     *
     * @param int $userid
     */
    public function set_coordinator_userid(int $userid): void {
        set_config(self::CONFIG_COORDINATOR, max(0, $userid), 'local_inventario');
    }

    /**
     * Store allowed absence users.
     *
     * @param array $ids
     */
    public function set_allowed_absence_user_ids(array $ids): void {
        $this->save_config_array(self::CONFIG_ALLOWED_USERS, $ids);
    }

    /**
     * Store allowed substitute users.
     *
     * @param array $ids
     */
    public function set_allowed_substitute_user_ids(array $ids): void {
        $this->save_config_array(self::CONFIG_ALLOWED_SUBSTITUTES, $ids);
    }

    /**
     * Get allowed absence user ids.
     *
     * @return array
     */
    public function get_allowed_absence_user_ids(): array {
        return $this->load_config_array(self::CONFIG_ALLOWED_USERS);
    }

    /**
     * Get allowed substitute user ids.
     *
     * @return array
     */
    public function get_allowed_substitute_user_ids(): array {
        return $this->load_config_array(self::CONFIG_ALLOWED_SUBSTITUTES);
    }

    /**
     * Get or generate the public key for the embed page.
     *
     * @return string
     */
    public function get_public_key(): string {
        $key = trim((string)get_config('local_inventario', self::CONFIG_PUBLIC_KEY));
        if ($key === '') {
            $key = $this->regenerate_public_key();
        }
        return $key;
    }

    /**
     * Set the public key.
     *
     * @param string $key
     */
    public function set_public_key(string $key): void {
        set_config(self::CONFIG_PUBLIC_KEY, trim($key), 'local_inventario');
    }

    /**
     * Regenerate the public key.
     *
     * @return string
     */
    public function regenerate_public_key(): string {
        $key = bin2hex(random_bytes(16));
        $this->set_public_key($key);
        return $key;
    }

    /**
     * Get notification CC email.
     *
     * @return string
     */
    public function get_notification_cc_email(): string {
        return trim((string)get_config('local_inventario', self::CONFIG_NOTIFICATION_CC));
    }

    /**
     * Set notification CC email.
     *
     * @param string $email
     */
    public function set_notification_cc_email(string $email): void {
        $email = trim($email);
        if ($email !== '' && !validate_email($email)) {
            $email = '';
        }
        set_config(self::CONFIG_NOTIFICATION_CC, $email, 'local_inventario');
    }

    /**
     * Determine if user may manage absences.
     *
     * @param int $userid
     * @return bool
     */
    public function is_absence_admin(int $userid): bool {
        return $this->can_delete_archived_absences($userid)
            || $userid === $this->get_coordinator_userid();
    }

    /**
     * Determine if a user can delete archived absences.
     *
     * Only users with manage capability (e.g. Manager/Admin) may purge archive rows.
     *
     * @param int $userid
     * @return bool
     */
    public function can_delete_archived_absences(int $userid): bool {
        $context = context_system::instance();
        return has_capability('local/inventario:manageabsences', $context, $userid);
    }

    /**
     * Check whether user may manage an entry.
     *
     * @param stdClass $absence
     * @param int $userid
     * @return bool
     */
    public function can_manage_entry(stdClass $absence, int $userid): bool {
        if (!empty($absence->archived)) {
            return false;
        }
        if ($absence->timeend < time()) {
            return false;
        }
        if ($absence->userid === $userid) {
            return true;
        }
        return $this->is_absence_admin($userid);
    }

    /**
     * Send notifications when an absence is created or updated.
     *
     * @param stdClass $absence
     */
    private function notify_absence(stdClass $absence): void {
        global $CFG;

        $teacher = core_user::get_user($absence->userid);
        $type = $absence->type ?? $this->get_type($absence->typeid);
        $comment = $absence->comment ?? '';
        $link = new moodle_url('/local/inventario/absence.php', ['id' => $absence->id]);
        $data = (object)[
            'teacher' => fullname($teacher),
            'subject' => $absence->subject,
            'type' => $type ? format_string($type->name) : '',
            'start' => userdate($absence->timestart),
            'end' => userdate($absence->timeend),
            'link' => $link->out(false),
            'substitute' => $this->resolve_substitute_name($absence),
            'comment' => $comment ?: get_string('nocomment', 'local_inventario'),
        ];

        $userfrom = core_user::get_support_user();

        $subject = get_string('absencenotificationsubject', 'local_inventario', $data);
        $plain = get_string('absencenotificationbody', 'local_inventario', $data);
        $html = format_text($plain, FORMAT_PLAIN);

        $message = new message();
        $message->component = 'local_inventario';
        $message->name = 'absence_notification';
        $message->userfrom = $userfrom;
        $message->userto = $teacher;
        $message->subject = $subject;
        $message->fullmessage = $plain;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $subject;
        $message->contexturl = $link->out(false);
        $message->contexturlname = get_string('absence', 'local_inventario');
        message_send($message);

        if (!empty($absence->substituteuserid)) {
            $substitute = core_user::get_user($absence->substituteuserid);
            $message->userto = $substitute;
            message_send($message);
        }

        $ccemail = $this->get_notification_cc_email();
        if ($ccemail !== '') {
            $recipient = (object)[
                'id' => -1,
                'email' => $ccemail,
                'firstname' => 'Inventario',
                'lastname' => 'Assenze',
                'emailstop' => 0,
                'mailformat' => 1,
            ];
            email_to_user($recipient, $userfrom, $subject, $plain, $html);
        }
    }

    /**
     * Resolve substitute label.
     *
     * @param stdClass $absence
     * @return string
     */
    private function resolve_substitute_name(stdClass $absence): string {
        if (!empty($absence->substitutename)) {
            return $absence->substitutename;
        }
        if (!empty($absence->substituteuserid)) {
            $user = core_user::get_user($absence->substituteuserid);
            return fullname($user);
        }
        return get_string('notavailable', 'core');
    }

    /**
     * Save comma-free config values.
     *
     * @param string $key
     * @param array $ids
     */
    private function save_config_array(string $key, array $ids): void {
        $ids = array_unique(array_values(array_map('intval', $ids)));
        set_config($key, json_encode(array_values($ids)), 'local_inventario');
    }

    /**
     * Load config array.
     *
     * @param string $key
     * @return array
     */
    private function load_config_array(string $key): array {
        $value = (string)get_config('local_inventario', $key);
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_unique(array_values(array_map('intval', $decoded)));
    }
}
