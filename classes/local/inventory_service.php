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
 * Core inventory operations.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\local;

use context_system;
use dml_exception;
use moodle_exception;
use stdClass;
use csv_import_reader;

defined('MOODLE_INTERNAL') || die();


class inventory_service {
    /** @var license_manager */
    private $license;

    public function __construct(?license_manager $license = null) {
        $this->license = $license ?? new license_manager();
    }

    // Sites.

    public function get_sites(): array {
        global $DB;
        return $DB->get_records('local_inventario_sites', null, 'name ASC');
    }

    public function save_site(stdClass $data): int {
        global $DB;
        require_capability('local/inventario:managesites', context_system::instance());
        $now = time();
        $record = (object)[
            'name' => trim($data->name ?? ''),
            'timemodified' => $now,
        ];
        if (empty($record->name)) {
            throw new moodle_exception('invalidsite', 'local_inventario');
        }
        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_inventario_sites', $record);
            return $record->id;
        }
        $record->timecreated = $now;
        return $DB->insert_record('local_inventario_sites', $record);
    }

    public function delete_site(int $id): void {
        global $DB;
        require_capability('local/inventario:managesites', context_system::instance());
        if ($DB->record_exists('local_inventario_objects', ['siteid' => $id])) {
            throw new moodle_exception('sitenotempty', 'local_inventario');
        }
        $DB->delete_records('local_inventario_sites', ['id' => $id]);
    }

    // Object types helper.
    public function get_type_properties(int $typeid): array {
        $typeservice = new type_service();
        return $typeservice->get_type_properties($typeid);
    }

    // Objects.

    public function get_objects(bool $includehidden, ?int $siteid = null, bool $onlyavailable = false): array {
        global $DB;
        $params = [];
        $where = [];
        if (!$includehidden) {
            $where[] = 'visible = 1';
        }
        if (!empty($siteid)) {
            $where[] = 'siteid = :siteid';
            $params['siteid'] = $siteid;
        }
        if ($onlyavailable) {
            $where[] = "status = 'available'";
        }
        $sql = 'SELECT * FROM {local_inventario_objects}';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY name ASC';
        return $DB->get_records_sql($sql, $params);
    }

    public function save_object(stdClass $data, int $userid): int {
        global $DB;
        require_capability('local/inventario:manageobjects', context_system::instance());
        $now = time();

        if (empty($data->id)) {
            $count = $DB->count_records('local_inventario_objects');
            $this->license->enforce_limit($count, 'objects');
        }

        $record = (object)[
            'name' => trim($data->name ?? ''),
            'description' => $data->description ?? '',
            'typeid' => (int)($data->typeid ?? 0),
            'siteid' => (int)($data->siteid ?? 0),
            'status' => $data->status ?? 'available',
            'visible' => isset($data->visible) ? (int)$data->visible : 1,
            'timemodified' => $now,
        ];

        if (!$this->license->get_limits()['allowhidden'] && empty($record->visible)) {
            $record->visible = 1;
        }
        if (empty($record->name)) {
            throw new moodle_exception('invalidobject', 'local_inventario');
        }
        if (empty($record->typeid)) {
            throw new moodle_exception('invalidtype', 'local_inventario');
        }

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_inventario_objects', $record);
            return $record->id;
        }

        $record->timecreated = $now;
        $record->createdby = $userid;
        return $DB->insert_record('local_inventario_objects', $record);
    }

    public function delete_object(int $id): void {
        global $DB;
        require_capability('local/inventario:manageobjects', context_system::instance());
        $DB->delete_records('local_inventario_reserv', ['objectid' => $id]);
        $DB->delete_records('local_inventario_propvals', ['objectid' => $id]);
        $DB->delete_records('local_inventario_objects', ['id' => $id]);
    }

    public function toggle_visibility(int $objectid, bool $visible): void {
        global $DB;
        require_capability('local/inventario:togglevisibility', context_system::instance());
        $this->license->require_pro();
        $record = $DB->get_record('local_inventario_objects', ['id' => $objectid], '*', MUST_EXIST);
        $record->visible = $visible ? 1 : 0;
        $record->timemodified = time();
        $DB->update_record('local_inventario_objects', $record);
    }

    // Properties.

    public function get_properties(): array {
        global $DB;
        return $DB->get_records('local_inventario_properties', null, 'parentid ASC, sortorder ASC, name ASC');
    }

    private function get_properties_indexed(): array {
        return $this->get_properties();
    }

    public function save_property(stdClass $data): int {
        global $DB;
        require_capability('local/inventario:manageproperties', context_system::instance());
        $now = time();
        if (empty($data->id)) {
            $count = $DB->count_records('local_inventario_properties');
            $this->license->enforce_limit($count, 'properties');
        }

        $record = (object)[
            'name' => trim($data->name ?? ''),
            'shortname' => trim($data->shortname ?? ''),
            'parentid' => isset($data->parentid) ? (int)$data->parentid : 0,
            'datatype' => $data->datatype ?? 'text',
            'options' => $data->options ?? '',
            'required' => !empty($data->required) ? 1 : 0,
            'sortorder' => (int)($data->sortorder ?? 0),
            'timemodified' => $now,
        ];
        if (!empty($data->id) && $record->parentid === (int)$data->id) {
            $record->parentid = 0;
        }
        if (empty($record->name) || empty($record->shortname)) {
            throw new moodle_exception('invalidproperty', 'local_inventario');
        }
        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_inventario_properties', $record);
            return $record->id;
        }
        $record->timecreated = $now;
        return $DB->insert_record('local_inventario_properties', $record);
    }

    public function delete_property(int $id): void {
        global $DB;
        require_capability('local/inventario:manageproperties', context_system::instance());

        if ($DB->record_exists('local_inventario_properties', ['parentid' => $id])) {
            throw new moodle_exception('childexists', 'local_inventario');
        }

        // Clean orphan values (oggetti eliminati).
        $DB->execute("DELETE FROM {local_inventario_propvals}
                       WHERE propertyid = :pid
                         AND objectid NOT IN (SELECT id FROM {local_inventario_objects})",
            ['pid' => $id]);

        // Block if still in use by existing objects.
        $inuse = $DB->record_exists_sql(
            "SELECT 1
               FROM {local_inventario_propvals} pv
               JOIN {local_inventario_objects} o ON o.id = pv.objectid
              WHERE pv.propertyid = :pid",
            ['pid' => $id]
        );
        if ($inuse) {
            throw new moodle_exception('propinuse', 'local_inventario');
        }

        $DB->delete_records('local_inventario_propvals', ['propertyid' => $id]);
        $DB->delete_records('local_inventario_typeprops', ['propertyid' => $id]);
        $DB->delete_records('local_inventario_properties', ['id' => $id]);
    }

    public function get_property_values(int $objectid): array {
        global $DB;
        $values = [];
        $records = $DB->get_records('local_inventario_propvals', ['objectid' => $objectid]);
        foreach ($records as $record) {
            $values[$record->propertyid] = $record->value;
        }
        return $values;
    }

    public function save_property_values(int $objectid, array $values): void {
        global $DB;
        require_capability('local/inventario:manageobjects', context_system::instance());

        $properties = $this->get_properties_indexed();
        $now = time();

        $DB->delete_records('local_inventario_propvals', ['objectid' => $objectid]);

        foreach ($values as $propertyid => $value) {
            if (!isset($properties[$propertyid])) {
                continue;
            }
            $property = $properties[$propertyid];
            $cleanvalue = is_array($value) ? implode(',', $value) : $value;
            if ($property->required && $cleanvalue === '' && $cleanvalue !== '0') {
                throw new moodle_exception('invalidproperty', 'local_inventario');
            }

            $record = (object)[
                'objectid' => $objectid,
                'propertyid' => $propertyid,
                'value' => $cleanvalue,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_inventario_propvals', $record);
        }
    }

    /**
     * Get last reservation summary (user + period) for an object.
     *
     * @param int $objectid
     * @return array|null
     */
    public function get_last_reservation_summary(int $objectid): ?array {
        global $DB;
        $rec = $DB->get_record_sql(
            "SELECT r.id, r.timestart, r.timeend,
                    u.firstname, u.lastname, u.middlename, u.alternatename, u.firstnamephonetic, u.lastnamephonetic
               FROM {local_inventario_reserv} r
               JOIN {user} u ON u.id = r.userid
              WHERE r.objectid = :objectid
           ORDER BY r.timestart DESC",
            ['objectid' => $objectid],
            IGNORE_MULTIPLE
        );
        if (!$rec) {
            return null;
        }
        $status = '';
        $now = time();
        if ($rec->timestart <= $now && $rec->timeend > $now) {
            $status = 'active';
        } else if ($rec->timeend < $now) {
            $status = 'expired';
        }
        return [
            'user' => fullname($rec),
            'period' => userdate($rec->timestart) . ' â†’ ' . userdate($rec->timeend),
            'status' => $status,
        ];
    }

    /**
     * Return object ids that have past reservations not yet returned.
     *
     * @return int[]
     */
    public function get_unreturned_object_ids(): array {
        global $DB;
        $sql = "SELECT DISTINCT objectid
                  FROM {local_inventario_reserv}
                 WHERE status = 'active' AND timeend < :now";
        $ids = $DB->get_records_sql($sql, ['now' => time()]);
        return array_map(static fn($rec) => (int)$rec->objectid, $ids);
    }

    // Reservations.

    public function save_reservation(stdClass $data, int $userid, bool $canmanageall): int {
        global $DB;

        $object = $DB->get_record('local_inventario_objects', ['id' => $data->objectid], '*', MUST_EXIST);
        if (!$object->visible && !$canmanageall) {
            throw new moodle_exception('hiddenobject', 'local_inventario');
        }

        $timestart = (int)$data->timestart;
        $timeend = (int)$data->timeend;
        if ($timeend <= $timestart) {
            throw new moodle_exception('invalidtimerange', 'local_inventario');
        }

        if (!$canmanageall && !empty($data->userid) && $data->userid != $userid) {
            throw new moodle_exception('notyours', 'local_inventario');
        }

        $useridtarget = $canmanageall && !empty($data->userid) ? (int)$data->userid : $userid;
        $this->enforce_overlap($object->id, $timestart, $timeend, (int)($data->id ?? 0));

        // Periodic reservations.
        $limits = $this->license->get_limits();
        $isperiodic = !empty($data->periodic) && empty($data->id);
        $repeatcount = max(1, (int)($data->repeatcount ?? 1));
        $repeatdays = max(1, (int)($data->repeatdays ?? 1));
        if ($isperiodic) {
            $allowperiodic = (bool)get_config('local_inventario', 'allowperiodic');
            if (!$allowperiodic || !$this->license->is_pro()) {
                throw new moodle_exception('periodicnotallowed', 'local_inventario');
            }
            // Limits come from the signed backend response.
            $maxrepeat = (int)$limits['periodicmax'];
            if ($maxrepeat > 0 && $repeatcount > $maxrepeat) {
                $repeatcount = $maxrepeat;
            }
            $maxdays = max(1, (int)$limits['periodicgapdays']);
            if ($repeatdays > $maxdays) {
                $repeatdays = $maxdays;
            }
        }

        if ($isperiodic) {
            $firstid = null;
            for ($i = 0; $i < $repeatcount; $i++) {
                $offset = $i * 86400 * $repeatdays;
                $ts = $timestart + $offset;
                $te = $timeend + $offset;
                $this->enforce_overlap($object->id, $ts, $te, 0);
                $rec = (object)[
                    'objectid' => $object->id,
                    'userid' => $useridtarget,
                    'siteid' => (int)($data->siteid ?: $object->siteid),
                    'timestart' => $ts,
                    'timeend' => $te,
                    'location' => $data->location ?? '',
                    'status' => $data->status ?? 'active',
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];
                $newid = $DB->insert_record('local_inventario_reserv', $rec);
                if ($firstid === null) {
                    $firstid = $newid;
                }
            }
            $this->mark_object_reserved($object->id);
            return $firstid ?? 0;
        }

        $record = (object)[
            'objectid' => $object->id,
            'userid' => $useridtarget,
            'siteid' => (int)($data->siteid ?: $object->siteid),
            'timestart' => $timestart,
            'timeend' => $timeend,
            'location' => $data->location ?? '',
            'status' => $data->status ?? 'active',
            'timemodified' => time(),
        ];

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $this->enforce_overlap($object->id, $timestart, $timeend, $record->id);
            $DB->update_record('local_inventario_reserv', $record);
            $this->mark_object_reserved($object->id);
            return $record->id;
        }

        $record->timecreated = time();
        $reservationid = $DB->insert_record('local_inventario_reserv', $record);
        $this->mark_object_reserved($object->id);
        return $reservationid;
    }

    public function delete_reservation(int $id, int $userid, bool $canmanageall): void {
        $reservation = $this->get_reservation_for_user($id, $userid, $canmanageall);
        if ($reservation->timeend <= time()) {
            throw new moodle_exception('cannotdeleteexpiredreservation', 'local_inventario');
        }
        // Keep history: mark returned instead of physical delete.
        $this->mark_reservation_returned($reservation);
    }

    /**
     * Mark reservation as returned (keeps history).
     *
     * @param int $id
     * @param int $userid
     * @param bool $canmanageall
     * @return void
     */
    public function return_reservation(int $id, int $userid, bool $canmanageall): void {
        $reservation = $this->get_reservation_for_user($id, $userid, $canmanageall);
        $this->mark_reservation_returned($reservation);
    }

    public function get_reservations(array $filters, bool $includehidden): array {
        global $DB;

        $params = [];
        $where = [];

        $rangeapplied = false;
        if (!empty($filters['overlapfrom']) && !empty($filters['overlapto'])) {
            $where[] = '(r.timestart <= :overlapto AND r.timeend >= :overlapfrom)';
            $params['overlapfrom'] = (int)$filters['overlapfrom'];
            $params['overlapto'] = (int)$filters['overlapto'];
            $rangeapplied = true;
        }

        if (!empty($filters['siteid'])) {
            $where[] = 'r.siteid = :siteid';
            $params['siteid'] = $filters['siteid'];
        }
        if (!empty($filters['objectid'])) {
            $where[] = 'r.objectid = :objectid';
            $params['objectid'] = $filters['objectid'];
        }
        if (!$rangeapplied) {
            if (!empty($filters['startfrom'])) {
                $where[] = 'r.timestart >= :startfrom';
                $params['startfrom'] = (int)$filters['startfrom'];
            }
            if (!empty($filters['endto'])) {
                $where[] = 'r.timeend <= :endto';
                $params['endto'] = (int)$filters['endto'];
            }
        }
        if (!empty($filters['userid'])) {
            $where[] = 'r.userid = :userid';
            $params['userid'] = $filters['userid'];
        }
        if (!empty($filters['typeid'])) {
            $where[] = 'o.typeid = :typeid';
            $params['typeid'] = $filters['typeid'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(o.name LIKE :search OR r.location LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!$includehidden) {
            $where[] = 'o.visible = 1';
        }

        $join = '';
        if (!empty($filters['propertyid'])) {
            $join .= " JOIN {local_inventario_propvals} pv ON pv.objectid = r.objectid AND pv.propertyid = :propid";
            $params['propid'] = $filters['propertyid'];
            if (!empty($filters['propvalue'])) {
                $where[] = 'pv.value LIKE :propval';
                $params['propval'] = '%' . $filters['propvalue'] . '%';
            }
        }

        $sql = "SELECT r.*,
                       u.firstname, u.lastname, u.middlename, u.alternatename, u.firstnamephonetic, u.lastnamephonetic,
                       o.name AS objectname, s.name AS sitename, o.typeid,
                       t.name AS typename, t.color AS typecolor
                  FROM {local_inventario_reserv} r
                  JOIN {local_inventario_objects} o ON o.id = r.objectid
                  JOIN {user} u ON u.id = r.userid
                  JOIN {local_inventario_sites} s ON s.id = r.siteid
             LEFT JOIN {local_inventario_types} t ON t.id = o.typeid
                  $join";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY r.timestart ASC';

        return $DB->get_records_sql($sql, $params);
    }

    public function get_stats(bool $includehidden): array {
        global $DB;

        $params = [];
        $where = [];
        if (!$includehidden) {
            $where[] = 'o.visible = 1';
        }
        $condition = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $usage = $DB->get_records_sql("SELECT o.id, o.name, COUNT(r.id) AS total
                                         FROM {local_inventario_objects} o
                                    LEFT JOIN {local_inventario_reserv} r ON r.objectid = o.id
                                         $condition
                                     GROUP BY o.id, o.name
                                     ORDER BY total DESC");

        // Count objects currently reserved (overlap with now) respecting visibility filter when needed.
        $reservationparams = [];
        $reservationwhere = [];
        if (!$includehidden) {
            $reservationwhere[] = 'o.visible = :visible';
            $reservationparams['visible'] = 1;
        }
        $reservationwhere[] = 'r.status = :status';
        $reservationparams['status'] = 'active';
        $reservationwhere[] = 'r.timestart <= :nowstart';
        $reservationwhere[] = 'r.timeend > :nowend';
        $reservationparams['nowstart'] = time();
        $reservationparams['nowend'] = $reservationparams['nowstart'];
        $reservationsql = "SELECT COUNT(DISTINCT r.objectid)
                             FROM {local_inventario_reserv} r
                             JOIN {local_inventario_objects} o ON o.id = r.objectid";
        if ($reservationwhere) {
            $reservationsql .= ' WHERE ' . implode(' AND ', $reservationwhere);
        }
        $reservations = (int)$DB->count_records_sql($reservationsql, $reservationparams);
        $objects = $DB->count_records('local_inventario_objects', $includehidden ? [] : ['visible' => 1]);

        return [
            'objects' => $objects,
            'reservations' => $reservations,
            'usage' => $usage,
        ];
    }

    /**
     * Count total reservations made by a specific user.
     *
     * @param int $userid
     * @return int
     */
    public function get_user_reservation_total(int $userid): int {
        global $DB;
        return (int)$DB->count_records('local_inventario_reserv', ['userid' => $userid]);
    }

    /**
     * Import properties from CSV (Pro only).
     *
     * @param string $filepath
     * @return array{created:int,updated:int,errors:array}
     */
    public function import_properties_from_csv(string $filepath): array {
        global $CFG, $DB;
        require_capability('local/inventario:manageproperties', context_system::instance());
        $this->license->require_pro();

        require_once($CFG->libdir . '/csvlib.class.php');
        $iid = csv_import_reader::get_new_iid('local_inventario_import');
        $cir = new csv_import_reader($iid, 'local_inventario_import');
        $content = file_get_contents($filepath);
        $delimiter = $this->detect_csv_delimiter($content);
        $cir->load_csv_content($content, 'utf-8', $delimiter);
        $columns = array_map('trim', $cir->get_columns() ?? []);

        $required = ['name', 'shortname', 'datatype'];
        foreach ($required as $req) {
            if (!in_array($req, $columns, true)) {
                $cir->cleanup(true);
                throw new moodle_exception('missingfield', 'error', '', $req);
            }
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $rownum = 1;
        $existing = $DB->get_records_menu('local_inventario_properties', null, '', 'shortname,id');

        while ($data = $cir->next()) {
            $row = array_combine($columns, $data);
            $rownum++;
            if (!$row) {
                $errors[] = get_string('invalidcsvrow', 'error', $rownum);
                continue;
            }
            $record = (object)[
                'name' => trim($row['name'] ?? ''),
                'shortname' => trim($row['shortname'] ?? ''),
                'datatype' => trim($row['datatype'] ?? 'text'),
                'options' => trim($row['options'] ?? ''),
                'required' => !empty($row['required']) ? 1 : 0,
                'sortorder' => (int)($row['sortorder'] ?? 0),
            ];
            if (empty($record->name) || empty($record->shortname)) {
                $errors[] = get_string('invalidproperty', 'local_inventario') . " (row {$rownum})";
                continue;
            }
            if (!in_array($record->datatype, ['text', 'number', 'bool', 'select', 'group'], true)) {
                $errors[] = get_string('invalidproperty', 'local_inventario') . " (row {$rownum})";
                continue;
            }
            $parentshortname = trim($row['parentshortname'] ?? '');
            $record->parentid = 0;
            if ($parentshortname !== '' && isset($existing[$parentshortname])) {
                $record->parentid = (int)$existing[$parentshortname];
            }
            try {
                if (!empty($existing[$record->shortname])) {
                    $record->id = (int)$existing[$record->shortname];
                    $this->save_property($record);
                    $updated++;
                } else {
                    $this->save_property($record);
                    $created++;
                }
                $existing = $DB->get_records_menu('local_inventario_properties', null, '', 'shortname,id');
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage() . " (row {$rownum})";
            }
        }
        $cir->cleanup(true);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Import objects from CSV (Pro only).
     *
     * @param string $filepath
     * @param int $userid
     * @return array{created:int,updated:int,errors:array}
     */
    public function import_objects_from_csv(string $filepath, int $userid): array {
        global $CFG, $DB;
        require_capability('local/inventario:manageobjects', context_system::instance());
        $this->license->require_pro();

        require_once($CFG->libdir . '/csvlib.class.php');
        $iid = csv_import_reader::get_new_iid('local_inventario_import_objs');
        $cir = new csv_import_reader($iid, 'local_inventario_import_objs');
        $content = file_get_contents($filepath);
        $delimiter = $this->detect_csv_delimiter($content);
        $cir->load_csv_content($content, 'utf-8', $delimiter);
        $columns = array_map('trim', $cir->get_columns() ?? []);
        $lowercolumns = array_map('strtolower', $columns);
        $types = $DB->get_records_menu('local_inventario_types', null, '', 'name,id');
        $sites = $DB->get_records_menu('local_inventario_sites', null, '', 'name,id');
        $defaultsitename = count($sites) === 1 ? array_keys($sites)[0] : null;
        $objectsbyname = $DB->get_records_menu('local_inventario_objects', null, '', 'name,id');

        $required = ['name', 'type', 'site'];
        $aliases = [
            'type' => ['type', 'datatype'], // Accept legacy "datatype" header.
            'site' => ['site', 'sede'],     // Accept localized column name.
        ];
        foreach ($required as $req) {
            $candidates = $aliases[$req] ?? [$req];
            $found = false;
            foreach ($candidates as $candidate) {
                if (in_array(strtolower($candidate), $lowercolumns, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                if ($req === 'site' && $defaultsitename !== null) {
                    // Allow missing site column when only one site exists; fallback to that site.
                    continue;
                }
                $cir->cleanup(true);
                throw new moodle_exception('missingfield', 'error', '', $req);
            }
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $rownum = 1;

        while ($data = $cir->next()) {
            $rowraw = array_combine($columns, $data);
            $rownum++;
            if (!$rowraw) {
                $errors[] = get_string('invalidcsvrow', 'error', $rownum);
                continue;
            }
            // Normalize column keys to lowercase for case-insensitive access.
            $row = [];
            foreach ($rowraw as $col => $value) {
                $row[strtolower($col)] = $value;
            }
            if (!isset($row['type']) && isset($row['datatype'])) {
                // Legacy column name support.
                $row['type'] = $row['datatype'];
            }
            if (!isset($row['site']) && isset($row['sede'])) {
                $row['site'] = $row['sede'];
            }
            if (!isset($row['site']) && $defaultsitename !== null) {
                $row['site'] = $defaultsitename;
            }
            $name = trim($row['name'] ?? '');
            $typename = trim($row['type'] ?? '');
            $sitename = trim($row['site'] ?? '');
            if ($name === '' || $typename === '' || $sitename === '') {
                $errors[] = get_string('invalidobject', 'local_inventario') . " (row {$rownum})";
                continue;
            }
            if (!isset($types[$typename])) {
                $errors[] = get_string('invalidtype', 'local_inventario') . " (row {$rownum})";
                continue;
            }
            if (!isset($sites[$sitename])) {
                $errors[] = get_string('invalidsite', 'local_inventario') . " (row {$rownum})";
                continue;
            }
            $record = (object)[
                'name' => $name,
                'description' => $row['description'] ?? '',
                'typeid' => (int)$types[$typename],
                'siteid' => (int)$sites[$sitename],
                'status' => in_array($row['status'] ?? 'available', ['available', 'reserved', 'offsite'], true)
                    ? $row['status']
                    : 'available',
                'visible' => isset($row['visible']) ? (!empty($row['visible']) ? 1 : 0) : 1,
                'currentlocation' => $row['currentlocation'] ?? '',
            ];
            try {
                if (!empty($objectsbyname[$name])) {
                    $record->id = (int)$objectsbyname[$name];
                    $this->save_object($record, $userid);
                    $updated++;
                } else {
                    $this->save_object($record, $userid);
                    $created++;
                }
                $objectsbyname = $DB->get_records_menu('local_inventario_objects', null, '', 'name,id');
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage() . " (row {$rownum})";
            }
        }
        $cir->cleanup(true);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    // Helpers.

    /**
     * Best-effort detection of delimiter (comma vs semicolon) used in CSV content.
     *
     * @param string $content
     * @return string
     */
    private function detect_csv_delimiter(string $content): string {
        $firstline = strtok($content, "\n");
        if ($firstline === false) {
            return 'comma';
        }
        $commacount = substr_count($firstline, ',');
        $semicoloncount = substr_count($firstline, ';');
        return $semicoloncount > $commacount ? 'semicolon' : 'comma';
    }

    private function mark_object_reserved(int $objectid): void {
        global $DB;
        if ($object = $DB->get_record('local_inventario_objects', ['id' => $objectid])) {
            $object->status = 'reserved';
            $object->timemodified = time();
            $DB->update_record('local_inventario_objects', $object);
        }
    }

    private function mark_object_available(int $objectid): void {
        global $DB;
        $hasactive = $DB->record_exists('local_inventario_reserv', ['objectid' => $objectid, 'status' => 'active']);
        if (!$hasactive && $object = $DB->get_record('local_inventario_objects', ['id' => $objectid])) {
            $object->status = 'available';
            $object->timemodified = time();
            $DB->update_record('local_inventario_objects', $object);
        }
    }

    /**
     * Check if an object has an active reservation right now.
     *
     * @param int $objectid
     * @return bool
     */
    public function has_active_reservation_now(int $objectid): bool {
        global $DB;
        $params = [
            'objectid' => $objectid,
            'status' => 'active',
            'nowstart' => time(),
            'nowend' => time(),
        ];
        return $DB->record_exists_select('local_inventario_reserv',
            'objectid = :objectid AND status = :status AND timestart <= :nowstart AND timeend > :nowend',
            $params);
    }

    /**
     * Return count of ended reservations not deleted for an object.
     *
     * @param int $objectid
     * @return int
     */
    public function get_past_reservations_open(int $objectid): int {
        global $DB;
        $params = [
            'objectid' => $objectid,
            'status' => 'active',
            'now' => time(),
        ];
        return $DB->count_records_select('local_inventario_reserv',
            'objectid = :objectid AND status = :status AND timeend < :now',
            $params);
    }

    private function enforce_overlap(int $objectid, int $timestart, int $timeend, int $excludeid = 0): void {
        global $DB;
        $overlap = $DB->record_exists_select('local_inventario_reserv',
            'objectid = :objectid AND status = :status AND id <> :id AND ' .
            '((timestart <= :ts_a AND timeend > :ts_b) OR (timestart < :te_a AND timeend >= :te_b) OR (:ts_c <= timestart AND :te_c >= timeend))',
            [
                'objectid' => $objectid,
                'status' => 'active',
                'id' => $excludeid,
                'ts_a' => $timestart,
                'ts_b' => $timestart,
                'te_a' => $timeend,
                'te_b' => $timeend,
                'ts_c' => $timestart,
                'te_c' => $timeend,
            ]);

        if ($overlap) {
            throw new moodle_exception('overlap', 'local_inventario');
        }
    }

    /**
     * Fetch reservation and check user permissions.
     *
     * @param int $id
     * @param int $userid
     * @param bool $canmanageall
     * @return stdClass
     */
    private function get_reservation_for_user(int $id, int $userid, bool $canmanageall): stdClass {
        global $DB;
        $reservation = $DB->get_record('local_inventario_reserv', ['id' => $id], '*', MUST_EXIST);
        if (!$canmanageall && $reservation->userid != $userid) {
            throw new moodle_exception('notyours', 'local_inventario');
        }
        if ($canmanageall) {
            require_capability('local/inventario:deletereservations', context_system::instance());
        }
        return $reservation;
    }

    /**
     * Mark reservation as returned and free object.
     *
     * @param stdClass $reservation
     * @return void
     */
    private function mark_reservation_returned(stdClass $reservation): void {
        global $DB;
        $now = time();
        // Mark this reservation as returned.
        $reservation->status = 'returned';
        $reservation->timeend = min($reservation->timeend, $now);
        $reservation->timemodified = $now;
        $DB->update_record('local_inventario_reserv', $reservation);

        // Also mark all previous reservations for the same object as returned to avoid multiple pending returns.
        $DB->execute(
            "UPDATE {local_inventario_reserv}
                SET status = 'returned', timemodified = :now
              WHERE objectid = :objectid
                AND status <> 'returned'
                AND id <> :id
                AND timestart <= :timestart",
            [
                'now' => $now,
                'objectid' => $reservation->objectid,
                'id' => $reservation->id,
                'timestart' => $reservation->timestart,
            ]
        );

        $this->mark_object_available($reservation->objectid);
    }
}

