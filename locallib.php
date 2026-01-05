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
 * Local helper functions for the Inventario plugin.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Helper functions for local_inventario.
 *
 * @package   local_inventario
 * @copyright 2025 mdlbox - https://app.mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/local/license_manager.php');
require_once(__DIR__ . '/classes/local/inventory_service.php');
require_once(__DIR__ . '/classes/local/type_service.php');

use local_inventario\local\inventory_service;
use local_inventario\local\license_manager;
use local_inventario\local\type_service;

/**
 * Single license manager instance.
 *
 * @return license_manager
 */
function local_inventario_license(): license_manager {
    static $license = null;
    if ($license === null) {
        $license = new license_manager();
    }
    return $license;
}

/**
 * Normalize a domain (keeps optional path for subfolder installs).
 *
 * @param string $domain
 * @return string
 */
function local_inventario_normalize_domain(string $domain): string {
    $domain = trim($domain);
    $domain = preg_replace('#^https?://#i', '', $domain);
    $domain = trim($domain, '/');
    return core_text::strtolower($domain);
}

/**
 * Return current Moodle domain including subfolder (if any), normalized.
 *
 * @return string
 */
function local_inventario_current_domain(): string {
    global $CFG;
    $host = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: '';
    $path = parse_url($CFG->wwwroot, PHP_URL_PATH) ?: '';
    $combined = $host . ($path ? '/' . ltrim($path, '/') : '');
    return local_inventario_normalize_domain($combined);
}

/**
 * Shared service instance.
 *
 * @return inventory_service
 */
function local_inventario_service(): inventory_service {
    static $service = null;
    if ($service === null) {
        $service = new inventory_service(local_inventario_license());
    }
    return $service;
}

/**
 * Render common navigation buttons based on capabilities.
 *
 * @param \context_system $context
 * @return string
 */
function local_inventario_render_nav(\context_system $context): string {
    global $PAGE;

    $manager = has_capability('local/inventario:manageobjects', $context);
    $canreserve = has_capability('local/inventario:reserve', $context);
    $licensemanager = local_inventario_license();
    $licensestatus = $licensemanager->get_status();
    $ispro = $licensemanager->is_pro();
    $hasapikey = !empty($licensestatus->apikey);
    $activeurl = (!empty($PAGE) && $PAGE->url instanceof moodle_url) ? $PAGE->url->out_as_local_url(false) : null;

    // Define nav items in the required order.
    $items = [
        [
            'path' => '/local/inventario/index.php',
            'label' => html_writer::span('', 'fa-solid fa-warehouse', ['aria-hidden' => 'true']) .
                html_writer::span(get_string('pluginname', 'local_inventario'), 'sr-only'),
            'show' => ($canreserve || $manager),
        ],
        [
            'path' => '/local/inventario/properties.php',
            'label' => html_writer::span('', 'fa-solid fa-list-check me-1', ['aria-hidden' => 'true']) .
                get_string('nav_props', 'local_inventario'),
            'show' => $manager,
        ],
        [
            'path' => '/local/inventario/types.php',
            'label' => html_writer::span('', 'fa-solid fa-layer-group me-1', ['aria-hidden' => 'true']) .
                get_string('nav_types', 'local_inventario'),
            'show' => $manager,
        ],
        [
            'path' => '/local/inventario/objects.php',
            'label' => html_writer::span('', 'fa-solid fa-boxes-stacked me-1', ['aria-hidden' => 'true']) .
                get_string('nav_objects', 'local_inventario'),
            'show' => $manager,
        ],
        [
            'path' => '/local/inventario/sites.php',
            'label' => html_writer::span('', 'fa-solid fa-location-dot me-1', ['aria-hidden' => 'true']) .
                get_string('nav_sites', 'local_inventario'),
            'show' => $manager,
        ],
        [
            'path' => '/local/inventario/reservations.php',
            'label' => html_writer::span('', 'fa-solid fa-calendar-check me-1', ['aria-hidden' => 'true']) .
                get_string('nav_booking', 'local_inventario'),
            'show' => ($canreserve || $manager),
        ],
        [
            'path' => '/local/inventario/reservations_list.php',
            'label' => html_writer::span('', 'fa fa-list-ul me-1', ['aria-hidden' => 'true']) .
                get_string('nav_list', 'local_inventario'),
            'show' => ($canreserve || $manager),
        ],
        [
            'path' => '/local/inventario/reservations_calendar.php',
            'label' => html_writer::span('', 'fa fa-calendar me-1', ['aria-hidden' => 'true']) .
                get_string('nav_calendar', 'local_inventario'),
            'show' => ($canreserve || $manager) && $ispro,
        ],
        [
            'path' => '/local/inventario/import.php',
            'label' => html_writer::span('', 'fa fa-file-import me-1', ['aria-hidden' => 'true']) .
                html_writer::span('', 'fa fa-file-export me-1', ['aria-hidden' => 'true']) .
                get_string('nav_csv', 'local_inventario'),
            'show' => $manager && $ispro && $hasapikey,
        ],
        [
            'path' => '/local/inventario/license.php',
            'label' => html_writer::span('', 'fa-solid fa-key me-1', ['aria-hidden' => 'true']) .
                get_string('nav_license', 'local_inventario'),
            'show' => $manager,
        ],
    ];

    $buttonsdata = array_filter($items, static function ($item) {
        return !empty($item['show']);
    });

    if (empty($buttonsdata)) {
        return '';
    }

    $buttons = [];
    foreach ($buttonsdata as $item) {
        $url = new moodle_url($item['path']);
        $label = $item['label'];
        $targeturl = $url->out_as_local_url(false);
        $isactive = $activeurl && (strpos($activeurl, $targeturl) === 0);
        $classes = 'btn me-2 mb-2 ' . ($isactive ? 'btn-primary active' : 'btn-secondary');
        $attrs = ['class' => $classes];
        if ($isactive) {
            $attrs['aria-current'] = 'page';
        }
        $buttons[] = html_writer::link($url, $label, $attrs);
    }

    return html_writer::div(implode('', $buttons), 'mb-3 inventario-nav');
}

/**
 * Shared type service instance.
 *
 * @return type_service
 */
function local_inventario_typeservice(): type_service {
    static $service = null;
    if ($service === null) {
        $service = new type_service();
    }
    return $service;
}

/**
 * Create or update the booking-only global role.
 */
function local_inventario_create_booking_role(): void {
    global $DB;

    // Ensure capabilities are available.
    if (function_exists('update_capabilities')) {
        update_capabilities('local_inventario');
    }

    $shortname = 'inventario_booking';
    $name = get_string('role_inventario_booking', 'local_inventario');
    $description = get_string('role_inventario_booking_desc', 'local_inventario');

    if (!$role = $DB->get_record('role', ['shortname' => $shortname])) {
        $roleid = create_role($name, $shortname, $description);
    } else {
        $roleid = $role->id;
        // Keep name/description in sync with language strings.
        $DB->set_field('role', 'name', $name, ['id' => $roleid]);
        $DB->set_field('role', 'description', $description, ['id' => $roleid]);
    }

    // Restrict role to system context.
    set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

    // Allow managers to assign this role.
    if ($manager = $DB->get_record('role', ['shortname' => 'manager'])) {
        if (function_exists('role_allow_assign')) {
            role_allow_assign($manager->id, $roleid);
        } else {
            $exists = $DB->record_exists('role_allow_assign', ['roleid' => $manager->id, 'allowassign' => $roleid]);
            if (!$exists) {
                $DB->insert_record('role_allow_assign', (object)[
                    'roleid' => $manager->id,
                    'allowassign' => $roleid,
                ]);
            }
        }
    }

    // Allowed capabilities for booking role.
    $caps = [
        'local/inventario:view' => CAP_ALLOW,
        'local/inventario:reserve' => CAP_ALLOW,
    ];
    $syscontextid = context_system::instance()->id;
    foreach ($caps as $cap => $perm) {
        // Skip if capability is not defined yet.
        if (!get_capability_info($cap)) {
            continue;
        }
        assign_capability($cap, $perm, $roleid, $syscontextid, true);
    }
}

/**
 * Returns site options for form select.
 *
 * @return array
 */
function local_inventario_site_options(): array {
    $options = [];
    foreach (local_inventario_service()->get_sites() as $site) {
        $options[$site->id] = format_string($site->name);
    }
    return $options;
}

/**
 * Returns object options filtered by visibility.
 *
 * @param bool $includehidden
 * @return array
 */
function local_inventario_object_options(bool $includehidden): array {
    $options = [];
    foreach (local_inventario_service()->get_objects($includehidden) as $object) {
        $options[$object->id] = format_string($object->name);
    }
    return $options;
}

/**
 * Type select options.
 *
 * @return array
 */
function local_inventario_type_options(): array {
    $options = [];
    foreach (local_inventario_typeservice()->get_types() as $type) {
        $options[$type->id] = format_string($type->name);
    }
    return $options;
}

/**
 * Returns users allowed to reserve as select options.
 *
 * @return array
 */
function local_inventario_user_options(): array {
    $context = context_system::instance();
    $users = get_users_by_capability(
        $context,
        'local/inventario:reserve',
        'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename'
    );
    $options = [];
    foreach ($users as $user) {
        $options[$user->id] = fullname($user);
    }
    return $options;
}

/**
 * Determine if the current user may see hidden objects.
 */
function local_inventario_can_see_hidden(): bool {
    $context = context_system::instance();
    return has_capability('local/inventario:togglevisibility', $context)
        || has_capability('local/inventario:manageobjects', $context);
}

/**
 * Wrapper to check if a feature is enabled based on license.
 *
 * @param string $feature
 * @return bool
 */
function local_inventario_feature_enabled(string $feature): bool {
    try {
        return local_inventario_license()->is_feature_enabled($feature);
    } catch (\Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}
