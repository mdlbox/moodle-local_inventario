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

namespace local_inventario;

use local_inventario\local\inventory_service;
use local_inventario\local\type_service;

/**
 * Unit tests for the inventory service.
 *
 * @package    local_inventario
 * @copyright  2026 mdlbox - https://mdlbox.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_inventario\local\inventory_service
 */
final class inventory_service_test extends \advanced_testcase {
    /** @var inventory_service */
    private $service;

    /** @var int */
    private $objectid;

    /**
     * Create a site, a type and an object shared by the tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->redirectEmails();

        global $USER;
        $this->service = new inventory_service();
        $siteid = $this->service->save_site((object)['name' => 'Main site']);
        $typeid = (new type_service())->save_type((object)[
            'name' => 'Room',
            'requiresreturn' => 0,
            'requireslocation' => 0,
        ]);
        $this->objectid = $this->service->save_object((object)[
            'name' => 'Room 1',
            'typeid' => $typeid,
            'siteid' => $siteid,
            'status' => 'available',
            'visible' => 1,
        ], (int)$USER->id);
    }

    /**
     * A site must have a non-empty name.
     */
    public function test_save_site_requires_a_name(): void {
        $this->expectException(\moodle_exception::class);
        $this->service->save_site((object)['name' => '  ']);
    }

    /**
     * The shared object is persisted.
     */
    public function test_object_is_created(): void {
        global $DB;
        $this->assertTrue($DB->record_exists('local_inventario_objects', ['id' => $this->objectid]));
    }

    /**
     * A valid reservation is created.
     */
    public function test_reservation_is_created(): void {
        global $USER;
        $id = $this->service->save_reservation($this->reservation_data(), (int)$USER->id, true);
        $this->assertGreaterThan(0, $id);
    }

    /**
     * Overlapping reservations on the same object are rejected.
     */
    public function test_overlapping_reservation_is_rejected(): void {
        global $USER;
        $this->service->save_reservation($this->reservation_data(), (int)$USER->id, true);
        $this->expectException(\moodle_exception::class);
        $this->service->save_reservation($this->reservation_data(), (int)$USER->id, true);
    }

    /**
     * Bulk creation reports per-object conflicts instead of aborting.
     */
    public function test_bulk_create_reports_conflicts(): void {
        global $USER;
        $this->service->save_reservation($this->reservation_data(), (int)$USER->id, true);

        $slot = $this->reservation_data();
        $base = (object)[
            'siteid' => 0,
            'timestart' => $slot->timestart,
            'timeend' => $slot->timeend,
            'location' => '',
            'status' => 'active',
        ];
        $result = $this->service->create_reservations_bulk([$this->objectid], $base, (int)$USER->id, true);

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Build a reservation payload for a fixed time slot.
     *
     * @return \stdClass
     */
    private function reservation_data(): \stdClass {
        return (object)[
            'objectid' => $this->objectid,
            'timestart' => strtotime('tomorrow 10:00'),
            'timeend' => strtotime('tomorrow 12:00'),
            'location' => '',
            'status' => 'active',
        ];
    }
}
