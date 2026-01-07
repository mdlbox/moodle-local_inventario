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
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\local;

use context_system;
use moodle_exception;
use stdClass;
use csv_import_reader;
use local_inventario\local\license_manager;

/**
 * Manage object types and their properties.
 */
class type_service {
    /**
     * Get all types.
     *
     * @return stdClass[]
     */
    public function get_types(): array {
        global $DB;
        return $DB->get_records('local_inventario_types', null, 'name ASC');
    }

    /**
     * Save a type with assigned properties.
     *
     * @param stdClass $data
     * @return int
     */
    public function save_type(stdClass $data): int {
        global $DB;
        require_capability('local/inventario:manageobjects', context_system::instance());

        $now = time();
        $color = $this->normalise_color((string)($data->color ?? ''));
        $requiresreturn = property_exists($data, 'requiresreturn')
            ? (!empty($data->requiresreturn) ? 1 : 0)
            : 1;
        $requireslocation = property_exists($data, 'requireslocation')
            ? (!empty($data->requireslocation) ? 1 : 0)
            : 1;
        $record = (object)[
            'name' => trim($data->name ?? ''),
            'description' => $data->description ?? '',
            'color' => $color,
            'requiresreturn' => $requiresreturn,
            'requireslocation' => $requireslocation,
            'timemodified' => $now,
        ];
        if (empty($record->name)) {
            throw new moodle_exception('invalidtype', 'local_inventario');
        }

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_inventario_types', $record);
            $typeid = $record->id;
        } else {
            $record->timecreated = $now;
            $typeid = $DB->insert_record('local_inventario_types', $record);
        }

        // Assign properties.
        $DB->delete_records('local_inventario_typeprops', ['typeid' => $typeid]);
        $props = $data->properties ?? [];
        if (!is_array($props)) {
            $props = array_filter(array_map('intval', explode(',', (string)$props)));
        }
        if (!empty($props)) {
            foreach ($props as $propid) {
                if (empty($propid)) {
                    continue;
                }
                $DB->insert_record('local_inventario_typeprops', [
                    'typeid' => $typeid,
                    'propertyid' => (int)$propid,
                ]);
            }
        }

        return $typeid;
    }

    /**
     * Normalize a hex color and fallback to default palette.
     *
     * @param string $color
     * @return string
     */
    private function normalise_color(string $color): string {
        $candidate = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $candidate)) {
            return strtolower($candidate);
        }
        return '#2563eb';
    }

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

    /**
     * Import types from CSV (Pro only).
     *
     * @param string $filepath
     * @return array{created:int,updated:int,errors:array}
     */
    public function import_types_from_csv(string $filepath): array {
        global $CFG, $DB;
        require_capability('local/inventario:manageobjects', context_system::instance());
        $license = new license_manager();
        $license->require_pro();

        require_once($CFG->libdir . '/csvlib.class.php');
        $iid = csv_import_reader::get_new_iid('local_inventario_import_types');
        $cir = new csv_import_reader($iid, 'local_inventario_import_types');
        $content = file_get_contents($filepath);
        $delimiter = $this->detect_csv_delimiter($content);
        $cir->load_csv_content($content, 'utf-8', $delimiter);
        $columns = array_map('trim', $cir->get_columns() ?? []);
        $required = ['name'];
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
        $properties = $DB->get_records_menu('local_inventario_properties', null, '', 'shortname,id');
        $existing = $DB->get_records_menu('local_inventario_types', null, '', 'name,id');

        while ($data = $cir->next()) {
            $row = array_combine($columns, $data);
            $rownum++;
            if (!$row) {
                $errors[] = get_string('invalidcsvrow', 'error', $rownum);
                continue;
            }
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                $errors[] = get_string('invalidtype', 'local_inventario') . " (row {$rownum})";
                continue;
            }
            $propshortnames = array_filter(array_map('trim', explode(',', (string)($row['properties'] ?? ''))));
            $propids = [];
            foreach ($propshortnames as $short) {
                if (!isset($properties[$short])) {
                    $errors[] = get_string('invalidproperty', 'local_inventario') . " (row {$rownum})";
                    continue 2;
                }
                $propids[] = (int)$properties[$short];
            }
            $requiresreturnraw = $row['requiresreturn'] ?? '';
            $requireslocationraw = $row['requireslocation'] ?? '';
            $payload = (object)[
                'name' => $name,
                'description' => $row['description'] ?? '',
                'color' => $this->normalise_color((string)($row['color'] ?? '#2563eb')),
                'properties' => $propids,
                'requiresreturn' => $requiresreturnraw === '' ? 1 : (!empty($requiresreturnraw) ? 1 : 0),
                'requireslocation' => $requireslocationraw === '' ? 1 : (!empty($requireslocationraw) ? 1 : 0),
            ];
            try {
                if (!empty($existing[$name])) {
                    $payload->id = (int)$existing[$name];
                    $this->save_type($payload);
                    $updated++;
                } else {
                    $this->save_type($payload);
                    $created++;
                }
                $existing = $DB->get_records_menu('local_inventario_types', null, '', 'name,id');
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage() . " (row {$rownum})";
            }
        }
        $cir->cleanup(true);

        return ['created' => $created, 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * Delete type if not used by objects.
     *
     * @param int $id
     * @throws moodle_exception
     */
    public function delete_type(int $id): void {
        global $DB;
        require_capability('local/inventario:manageobjects', context_system::instance());

        if ($DB->record_exists('local_inventario_objects', ['typeid' => $id])) {
            throw new moodle_exception('typeinuse', 'local_inventario');
        }
        $DB->delete_records('local_inventario_typeprops', ['typeid' => $id]);
        $DB->delete_records('local_inventario_types', ['id' => $id]);
    }

    /**
     * Get property ids assigned to a type.
     *
     * @param int $typeid
     * @return int[]
     */
    public function get_type_property_ids(int $typeid): array {
        global $DB;
        $records = $DB->get_records('local_inventario_typeprops', ['typeid' => $typeid], '', 'propertyid');
        return array_map(static function ($rec) {
            return (int)$rec->propertyid;
        }, $records);
    }

    /**
     * Get property objects for a type.
     *
     * @param int $typeid
     * @return stdClass[]
     */
    public function get_type_properties(int $typeid): array {
        global $DB;
        if (empty($typeid)) {
            return [];
        }

        // Start from properties assigned to the type.
        $assignedids = $this->get_type_property_ids($typeid);
        if (empty($assignedids)) {
            return [];
        }

        // Collect descendants (children, grandchildren, ...).
        $allids = $assignedids;
        $queue = $assignedids;
        while (!empty($queue)) {
            [$insql, $params] = $DB->get_in_or_equal($queue, SQL_PARAMS_NAMED);
            $children = $DB->get_records_select('local_inventario_properties', "parentid {$insql}", $params, '', 'id');
            $queue = [];
            foreach ($children as $child) {
                if (!in_array((int)$child->id, $allids, true)) {
                    $allids[] = (int)$child->id;
                    $queue[] = (int)$child->id;
                }
            }
        }

        [$insqlall, $paramsall] = $DB->get_in_or_equal($allids, SQL_PARAMS_NAMED);
        $sql = "SELECT *
                  FROM {local_inventario_properties}
                 WHERE id {$insqlall}
              ORDER BY parentid ASC, sortorder ASC, name ASC";
        return $DB->get_records_sql($sql, $paramsall);
    }
}
