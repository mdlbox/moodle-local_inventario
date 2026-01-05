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
 * Upgrade script for local_inventario.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade steps for local_inventario.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_inventario_upgrade(int $oldversion): bool {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025112301) {
        $table = new xmldb_table('local_inventario_properties');
        $field = new xmldb_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0, 'shortname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025112301, 'local', 'inventario');
    }

    if ($oldversion < 2025112302) {
        if (function_exists('local_inventario_create_booking_role')) {
            local_inventario_create_booking_role();
        }
        upgrade_plugin_savepoint(true, 2025112302, 'local', 'inventario');
    }
    if ($oldversion < 2025112303) {
        if (function_exists('local_inventario_create_booking_role')) {
            local_inventario_create_booking_role();
        }
        upgrade_plugin_savepoint(true, 2025112303, 'local', 'inventario');
    }
    if ($oldversion < 2025112304) {
        if (function_exists('local_inventario_create_booking_role')) {
            local_inventario_create_booking_role();
        }
        upgrade_plugin_savepoint(true, 2025112304, 'local', 'inventario');
    }
    if ($oldversion < 2025112305) {
        if (function_exists('local_inventario_create_booking_role')) {
            local_inventario_create_booking_role();
        }
        upgrade_plugin_savepoint(true, 2025112305, 'local', 'inventario');
    }
    if ($oldversion < 2025112306) {
        // No DB changes; bump to pick native cURL client for license calls.
        upgrade_plugin_savepoint(true, 2025112306, 'local', 'inventario');
    }
    if ($oldversion < 2025112307) {
        // No DB changes; enforce native HTTP client usage.
        upgrade_plugin_savepoint(true, 2025112307, 'local', 'inventario');
    }
    if ($oldversion < 2025112308) {
        // No DB changes; retry creation of booking role after client fixes.
        if (function_exists('local_inventario_create_booking_role')) {
            local_inventario_create_booking_role();
        }
        upgrade_plugin_savepoint(true, 2025112308, 'local', 'inventario');
    }
    if ($oldversion < 2025112309) {
        if (function_exists('local_inventario_create_booking_role')) {
            local_inventario_create_booking_role();
        }
        upgrade_plugin_savepoint(true, 2025112309, 'local', 'inventario');
    }
    if ($oldversion < 2025112310) {
        // Add typeid to objects.
        $table = new xmldb_table('local_inventario_objects');
        $field = new xmldb_field('typeid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0, 'description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Create types table.
        $typestable = new xmldb_table('local_inventario_types');
        if (!$dbman->table_exists($typestable)) {
            $typestable->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $typestable->add_field('name', XMLDB_TYPE_CHAR, '255', XMLDB_NOTNULL, null, null, '');
            $typestable->add_field('description', XMLDB_TYPE_TEXT, null, null);
            $typestable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, 0);
            $typestable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, 0);
            $typestable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($typestable);
        }
        // Create typeprops table.
        $typepropstable = new xmldb_table('local_inventario_typeprops');
        if (!$dbman->table_exists($typepropstable)) {
            $typepropstable->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $typepropstable->add_field('typeid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, 0);
            $typepropstable->add_field('propertyid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, 0);
            $typepropstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $typepropstable->add_key('typefk', XMLDB_KEY_FOREIGN, ['typeid'], 'local_inventario_types', ['id']);
            $typepropstable->add_key('propfk', XMLDB_KEY_FOREIGN, ['propertyid'], 'local_inventario_properties', ['id']);
            $typepropstable->add_key('typepropuniq', XMLDB_KEY_UNIQUE, ['typeid', 'propertyid']);
            $dbman->create_table($typepropstable);
        }
        upgrade_plugin_savepoint(true, 2025112310, 'local', 'inventario');
    }
    if ($oldversion < 2025112312) {
        // Ensure types table has auto-increment id.
        $table = new xmldb_table('local_inventario_types');
        $field = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $dbman->change_field_type($table, $field);

        // Ensure typeprops table has auto-increment id.
        $table = new xmldb_table('local_inventario_typeprops');
        $field = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $dbman->change_field_type($table, $field);

        upgrade_plugin_savepoint(true, 2025112312, 'local', 'inventario');
    }

    if ($oldversion < 2025112313) {
        // Enforce sequence/auto increment using XMLDB (cross-DB).
        $typestable = new xmldb_table('local_inventario_types');
        $typeidfield = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
        if ($dbman->field_exists($typestable, $typeidfield)) {
            $dbman->change_field_type($typestable, $typeidfield);
        }

        $typepropstable = new xmldb_table('local_inventario_typeprops');
        $typepropidfield = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
        if ($dbman->field_exists($typepropstable, $typepropidfield)) {
            $dbman->change_field_type($typepropstable, $typepropidfield);
        }
        upgrade_plugin_savepoint(true, 2025112313, 'local', 'inventario');
    }
    if ($oldversion < 2025112314) {
        // Ensure correct primary keys and auto-increment on type tables.
        // Types.
        $typestable = new xmldb_table('local_inventario_types');
        $idfield = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
        if ($dbman->field_exists($typestable, $idfield)) {
            $dbman->change_field_type($typestable, $idfield);
        }
        if (!$dbman->table_exists($typestable)) {
            $typestable->add_field('name', XMLDB_TYPE_CHAR, '255', XMLDB_NOTNULL, null, null, '');
            $typestable->add_field('description', XMLDB_TYPE_TEXT, null, null);
            $typestable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, 0);
            $typestable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, 0);
            $typestable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($typestable);
        }
        if ($driver === 'mysql') {
            // No driver-specific SQL required; XMLDB handles sequences/identity.
        }

        // Typeprops.
        $typepropstable = new xmldb_table('local_inventario_typeprops');
        $idfieldtp = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, XMLDB_SEQUENCE);
        if ($dbman->field_exists($typepropstable, $idfieldtp)) {
            $dbman->change_field_type($typepropstable, $idfieldtp);
        }
        if (!$dbman->table_exists($typepropstable)) {
            $typepropstable->add_field('typeid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, 0);
            $typepropstable->add_field('propertyid', XMLDB_TYPE_INTEGER, '10', XMLDB_NOTNULL, null, null, 0);
            $typepropstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $typepropstable->add_key('typefk', XMLDB_KEY_FOREIGN, ['typeid'], 'local_inventario_types', ['id']);
            $typepropstable->add_key('propfk', XMLDB_KEY_FOREIGN, ['propertyid'], 'local_inventario_properties', ['id']);
            $typepropstable->add_key('typepropuniq', XMLDB_KEY_UNIQUE, ['typeid', 'propertyid']);
            $dbman->create_table($typepropstable);
        }
        if ($driver === 'mysql') {
            // No driver-specific SQL required; XMLDB handles sequences/identity.
        }

        upgrade_plugin_savepoint(true, 2025112314, 'local', 'inventario');
    }

    if ($oldversion < 2025112401) {
        // Legacy defaults removed to avoid shipping public tokens.
        unset_config('endpoint', 'local_inventario');
        unset_config('apitoken', 'local_inventario');
        upgrade_plugin_savepoint(true, 2025112401, 'local', 'inventario');
    }

    if ($oldversion < 2025112402) {
        // Clean up legacy defaults.
        unset_config('endpoint', 'local_inventario');
        unset_config('apitoken', 'local_inventario');
        upgrade_plugin_savepoint(true, 2025112402, 'local', 'inventario');
    }

    if ($oldversion < 2025112409) {
        unset_config('endpoint', 'local_inventario');
        unset_config('apitoken', 'local_inventario');
        upgrade_plugin_savepoint(true, 2025112409, 'local', 'inventario');
    }

    if ($oldversion < 2025112800) {
        // Add expirednotified flag to reservations.
        $table = new xmldb_table('local_inventario_reserv');
        $field = new xmldb_field('expirednotified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025112800, 'local', 'inventario');
    }

    if ($oldversion < 2025120200) {
        // Add color to object types for calendar legend.
        $table = new xmldb_table('local_inventario_types');
        $field = new xmldb_field('color', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '#2563eb', 'description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025120200, 'local', 'inventario');
    }

    if ($oldversion < 2025120800) {
        // Add signed payload fields for license validation.
        $table = new xmldb_table('local_inventario_license');
        $issued = new xmldb_field('issuedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'installid');
        if (!$dbman->field_exists($table, $issued)) {
            $dbman->add_field($table, $issued);
        }
        $protoken = new xmldb_field('protoken', XMLDB_TYPE_TEXT, null, null, null, null, null, 'issuedat');
        if (!$dbman->field_exists($table, $protoken)) {
            $dbman->add_field($table, $protoken);
        }
        $protokenexpires = new xmldb_field('protokenexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'protoken');
        if (!$dbman->field_exists($table, $protokenexpires)) {
            $dbman->add_field($table, $protokenexpires);
        }
        $limitsjson = new xmldb_field('limitsjson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'protokenexpires');
        if (!$dbman->field_exists($table, $limitsjson)) {
            $dbman->add_field($table, $limitsjson);
        }

        // Drop public default tokens/endpoints shipped in older versions.
        $currenttoken = (string)get_config('local_inventario', 'apitoken');
        $defaulttokens = [
            'f4b3c52e9d7a4c550fb6e8a1d2c1220a6d7e8c9b0a1f2d3c4e5b6a1008980c1d2e3f',
            'f4b3c52e9d7a4c550fb6e8a1d21008',
        ];
        if (in_array($currenttoken, $defaulttokens, true)) {
            unset_config('apitoken', 'local_inventario');
        }
        $endpoint = (string)get_config('local_inventario', 'endpoint');
        $defaulteps = ['https://api.mdlbox.com/api.php'];
        if (in_array($endpoint, $defaulteps, true)) {
            unset_config('endpoint', 'local_inventario');
        }
        upgrade_plugin_savepoint(true, 2025120800, 'local', 'inventario');
    }

    if ($oldversion < 2025121500) {
        // Flag object types that require a physical return (default: yes to keep existing behaviour).
        $table = new xmldb_table('local_inventario_types');
        $field = new xmldb_field('requiresreturn', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1, 'color');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025121500, 'local', 'inventario');
    }

    if ($oldversion < 2025122000) {
        // Flag object types that need a usage location (default yes to preserve current behaviour).
        $table = new xmldb_table('local_inventario_types');
        $field = new xmldb_field('requireslocation', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 1, 'requiresreturn');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025122000, 'local', 'inventario');
    }

    if ($oldversion < 2025122200) {
        $table = new xmldb_table('local_inventario_objects');
        $enabled = new xmldb_field('availableperiodenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'status');
        $from = new xmldb_field('availablefrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'availableperiodenabled');
        $to = new xmldb_field('availableto', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'availablefrom');
        $times = new xmldb_field('availabletimes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'availableto');
        if (!$dbman->field_exists($table, $enabled)) {
            $dbman->add_field($table, $enabled);
        }
        if (!$dbman->field_exists($table, $from)) {
            $dbman->add_field($table, $from);
        }
        if (!$dbman->field_exists($table, $to)) {
            $dbman->add_field($table, $to);
        }
        if (!$dbman->field_exists($table, $times)) {
            $dbman->add_field($table, $times);
        }
        upgrade_plugin_savepoint(true, 2025122200, 'local', 'inventario');
    }

    if ($oldversion < 2025122209) {
        if (function_exists('local_inventario_create_booking_role')) {
            local_inventario_create_booking_role();
        }
        upgrade_plugin_savepoint(true, 2025122209, 'local', 'inventario');
    }

    if ($oldversion < 2025122211) {
        require_once(__DIR__ . '/../classes/local/license_manager.php');
        $defaultkey = \local_inventario\local\license_manager::default_free_key();
        $records = $DB->get_records_select('local_inventario_license', "apikey IS NULL OR apikey = ''");
        $now = time();
        foreach ($records as $record) {
            $record->apikey = $defaultkey;
            $record->status = 'free';
            $record->timemodified = $now;
            $DB->update_record('local_inventario_license', $record);
        }
        upgrade_plugin_savepoint(true, 2025122211, 'local', 'inventario');
    }
    return true;
}
