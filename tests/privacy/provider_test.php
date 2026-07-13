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

namespace local_inventario\privacy;

use core_privacy\local\metadata\collection;

/**
 * Tests for the privacy provider.
 *
 * @package    local_inventario
 * @copyright  2026 mdlbox - https://mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_inventario\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * The provider describes the plugin's stored data.
     */
    public function test_get_metadata_describes_stored_data(): void {
        $collection = new collection('local_inventario');
        $result = provider::get_metadata($collection);

        $this->assertSame($collection, $result);
        $this->assertNotEmpty($result->get_collection());
    }

    /**
     * A user with no plugin data has no contexts.
     */
    public function test_no_contexts_for_user_without_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid((int)$user->id);

        $this->assertCount(0, $contextlist->get_contextids());
    }
}
