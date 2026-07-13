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
 * Privacy API implementation.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\privacy;

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_inventario.
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\userlist_provider, plugin_provider {
    /**
     * Describe stored data.
     *
     * @param collection $items
     * @return collection
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table('local_inventario_reserv', [
            'userid' => 'privacy:metadata:userid',
            'objectid' => 'privacy:metadata:objectid',
            'siteid' => 'privacy:metadata:siteid',
            'timestart' => 'privacy:metadata:timestart',
            'timeend' => 'privacy:metadata:timeend',
            'location' => 'privacy:metadata:location',
        ], 'privacy:metadata:reservations');

        $items->add_database_table('local_inventario_absences', [
            'userid' => 'privacy:metadata:absences:userid',
            'substituteuserid' => 'privacy:metadata:absences:substituteuserid',
            'substitutename' => 'privacy:metadata:absences:substitutename',
            'subject' => 'privacy:metadata:absences:subject',
            'comment' => 'privacy:metadata:absences:comment',
            'createdby' => 'privacy:metadata:absences:createdby',
            'timestart' => 'privacy:metadata:absences:timestart',
            'timeend' => 'privacy:metadata:absences:timeend',
        ], 'privacy:metadata:absences');

        $items->add_database_table('local_inventario_objects', [
            'createdby' => 'privacy:metadata:objects:createdby',
        ], 'privacy:metadata:objects');

        return $items;
    }

    /**
     * Return contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid($userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $hasdata = $DB->record_exists('local_inventario_reserv', ['userid' => $userid]);
        if (!$hasdata) {
            $hasdata = $DB->record_exists_select(
                'local_inventario_absences',
                'userid = :u1 OR substituteuserid = :u2 OR createdby = :u3',
                ['u1' => $userid, 'u2' => $userid, 'u3' => $userid]
            );
        }
        if (!$hasdata) {
            $hasdata = $DB->record_exists('local_inventario_objects', ['createdby' => $userid]);
        }

        if ($hasdata) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Export data for the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!in_array(context_system::instance()->id, $contextlist->get_contextids())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();

        // Reservations made by the user.
        $reservations = $DB->get_records('local_inventario_reserv', ['userid' => $userid]);
        if ($reservations) {
            $data = [];
            foreach ($reservations as $reservation) {
                $data[] = (object)[
                    'objectid' => $reservation->objectid,
                    'siteid' => $reservation->siteid,
                    'timestart' => transform::datetime($reservation->timestart),
                    'timeend' => transform::datetime($reservation->timeend),
                    'location' => $reservation->location,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('reservations', 'local_inventario')],
                (object)['reservations' => $data]
            );
        }

        // Absences linked to the user (as absent teacher, substitute or creator).
        $absences = $DB->get_records_select(
            'local_inventario_absences',
            'userid = :u1 OR substituteuserid = :u2 OR createdby = :u3',
            ['u1' => $userid, 'u2' => $userid, 'u3' => $userid],
            'timestart ASC'
        );
        if ($absences) {
            $data = [];
            foreach ($absences as $absence) {
                $roles = [];
                if ((int)$absence->userid === $userid) {
                    $roles[] = 'teacher';
                }
                if ((int)$absence->substituteuserid === $userid) {
                    $roles[] = 'substitute';
                }
                if ((int)$absence->createdby === $userid) {
                    $roles[] = 'createdby';
                }
                $data[] = (object)[
                    'role' => implode(', ', $roles),
                    'subject' => $absence->subject,
                    'comment' => $absence->comment,
                    'substitutename' => $absence->substitutename,
                    'timestart' => transform::datetime($absence->timestart),
                    'timeend' => transform::datetime($absence->timeend),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('absence', 'local_inventario')],
                (object)['absences' => $data]
            );
        }

        // Inventory objects created by the user (createdby audit field).
        $objects = $DB->get_records('local_inventario_objects', ['createdby' => $userid]);
        if ($objects) {
            $data = [];
            foreach ($objects as $object) {
                $data[] = (object)[
                    'name' => $object->name,
                    'timecreated' => transform::datetime($object->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('objectslist', 'local_inventario')],
                (object)['objects' => $data]
            );
        }
    }

    /**
     * Delete all data for the context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof context_system) {
            return;
        }
        $DB->delete_records('local_inventario_reserv');
        $DB->delete_records('local_inventario_absences');
        // Keep the inventory objects but drop the personal createdby reference.
        $DB->set_field('local_inventario_objects', 'createdby', 0, []);
    }

    /**
     * Delete data for a list of contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!in_array(context_system::instance()->id, $contextlist->get_contextids())) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        $DB->delete_records('local_inventario_reserv', ['userid' => $userid]);

        // Absence records that are fundamentally about this user are removed.
        $DB->delete_records('local_inventario_absences', ['userid' => $userid]);
        // Where the user only appears as substitute or creator of someone else's
        // absence, unlink the reference but keep the record for the other person.
        $DB->set_field('local_inventario_absences', 'substituteuserid', 0, ['substituteuserid' => $userid]);
        $DB->set_field('local_inventario_absences', 'createdby', 0, ['createdby' => $userid]);
        // The user only appears as the creator of inventory objects (institutional
        // data): keep the objects but unlink the personal reference.
        $DB->set_field('local_inventario_objects', 'createdby', 0, ['createdby' => $userid]);
    }

    /**
     * Get users in context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }

        $userlist->add_from_sql('userid', "SELECT userid FROM {local_inventario_reserv}", []);
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_inventario_absences}", []);
        $userlist->add_from_sql(
            'substituteuserid',
            "SELECT substituteuserid FROM {local_inventario_absences} WHERE substituteuserid > 0",
            []
        );
        $userlist->add_from_sql(
            'createdby',
            "SELECT createdby FROM {local_inventario_absences} WHERE createdby > 0",
            []
        );
        $userlist->add_from_sql(
            'createdby',
            "SELECT createdby FROM {local_inventario_objects} WHERE createdby > 0",
            []
        );
    }

    /**
     * Delete data for multiple users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_inventario_reserv', "userid $insql", $params);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_inventario_absences', "userid $insql", $params);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->set_field_select('local_inventario_absences', 'substituteuserid', 0, "substituteuserid $insql", $params);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->set_field_select('local_inventario_absences', 'createdby', 0, "createdby $insql", $params);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->set_field_select('local_inventario_objects', 'createdby', 0, "createdby $insql", $params);
    }
}
