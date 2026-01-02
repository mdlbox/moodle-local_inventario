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
 * @copyright 2025 mdlbox - https://app.mdlbox.com
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
class provider implements plugin_provider, \core_privacy\local\metadata\provider, \core_privacy\local\request\userlist_provider {
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
        $reservations = $DB->get_records('local_inventario_reserv', ['userid' => $userid]);
        if (!$reservations) {
            return;
        }

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

        writer::with_context(context_system::instance())->export_data(
            [get_string('reservations', 'local_inventario')],
            (object)['reservations' => $data]
        );
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

        $sql = "SELECT userid FROM {local_inventario_reserv}";
        $userids = $DB->get_fieldset_sql($sql);
        $userlist->add_users($userids);
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
        if (empty($userlist->get_userids())) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_inventario_reserv', "userid $insql", $params);
    }
}
