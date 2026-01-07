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

namespace local_inventario\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/inventario/locallib.php');

/**
 * Scheduled task to sync license and ping installation.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class license_sync extends \core\task\scheduled_task {
    /**
     * Task name shown in admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('license', 'local_inventario');
    }

    /**
     * Execute the task and refresh license status.
     */
    public function execute(): void {
        $license = local_inventario_license();
        // Force refresh to validate API key and update status (Pro/Free).
        $license->refresh(true);
        // Final safety: downgrade if expiry is in the past.
        $license->downgrade_if_expired();
    }
}
