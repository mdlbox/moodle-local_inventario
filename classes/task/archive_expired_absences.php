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
 * Archive expired absences.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_inventario\task;

use local_inventario\local\absence_service;

/**
 * Scheduled task to move expired absences into archive.
 */
class archive_expired_absences extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_archiveabsences', 'local_inventario');
    }

    /**
     * Execute task.
     */
    public function execute(): void {
        $service = new absence_service();
        $count = $service->archive_expired_absences();
        if ($count > 0) {
            mtrace('[local_inventario] Archived absences: ' . $count);
        }
    }
}

