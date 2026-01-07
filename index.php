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
 * Main dashboard for the Inventario plugin.
 *
 * @package   local_inventario
 * @copyright 2026 mdlbox - https://mdlbox.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

require_login();

$context = context_system::instance();
require_capability('local/inventario:view', $context);

$siteid = optional_param('siteid', 0, PARAM_INT);
$onlyavailable = optional_param('available', 0, PARAM_BOOL);
$search = optional_param('search', '', PARAM_RAW_TRIMMED);
$typefilter = optional_param('typeid', 0, PARAM_INT);
$statusfilter = optional_param('status', '', PARAM_ALPHA);
$perpage = optional_param('perpage', 10, PARAM_INT);
$resperpage = optional_param('resperpage', 10, PARAM_INT);
$allowedperpage = [10, 20, 50, 100, 0];
if (!in_array($perpage, $allowedperpage, true)) {
    $perpage = 20;
}
if (!in_array($resperpage, $allowedperpage, true)) {
    $resperpage = 20;
}

$PAGE->set_url(new moodle_url('/local/inventario/index.php', [
    'siteid' => $siteid,
    'available' => $onlyavailable,
    'search' => $search,
    'typeid' => $typefilter,
    'status' => $statusfilter,
    'perpage' => $perpage,
    'resperpage' => $resperpage,
]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_inventario'));
$PAGE->set_heading(get_string('pluginname', 'local_inventario'));
$PAGE->requires->css('/local/inventario/styles.css');
$PAGE->requires->js_call_amd('local_inventario/dashboard', 'init');
$PAGE->requires->js_call_amd('local_inventario/objectmodal', 'init');

$licensemanager = local_inventario_license();
$license = $licensemanager->refresh();
$allowhidden = $licensemanager->is_feature_enabled('hidden');
$includehidden = local_inventario_can_see_hidden();

$service = local_inventario_service();
$siteoptionsraw = local_inventario_site_options();
$typeoptionsraw = local_inventario_type_options();
$siteoptions = [];
foreach ($siteoptionsraw as $id => $name) {
    $siteoptions[] = [
        'id' => $id,
        'name' => $name,
        'selected' => ((int)$siteid === (int)$id),
    ];
}

$objects = $service->get_objects($includehidden, $siteid ?: null, (bool)$onlyavailable);
$reservations = $service->get_reservations(['siteid' => $siteid ?: null], $includehidden);
$stats = $service->get_stats($includehidden);
$stats['userreservations'] = (int)$service->get_user_reservation_total((int)$USER->id);
if (!isset($stats['userreservations'])) {
    $stats['userreservations'] = 0;
}

// Apply additional filters for the objects list.
$filteredobjects = [];
foreach ($objects as $obj) {
    if (!empty($search) && stripos($obj->name, $search) === false) {
        continue;
    }
    if (!empty($typefilter) && (int)$obj->typeid !== (int)$typefilter) {
        continue;
    }
    if (!empty($statusfilter) && $obj->status !== $statusfilter) {
        continue;
    }
    $filteredobjects[] = $obj;
}
$objects = $filteredobjects;

$totalobjects = count($objects);
if ($perpage > 0 && $totalobjects > $perpage) {
    $objects = array_slice($objects, 0, $perpage);
}

$canmanage = has_capability('local/inventario:manageobjects', $context);
$canreserve = has_capability('local/inventario:reserve', $context);

$propertiesdefs = $service->get_properties();
$propertynames = [];
foreach ($propertiesdefs as $prop) {
    $propertynames[$prop->id] = format_string($prop->name);
}

$objectdata = [];
foreach ($objects as $object) {
    $hasactive = $service->has_active_reservation_now($object->id);
    $currentlyavailable = $service->is_available_now($object);
    if (!$currentlyavailable && empty($hasactive)) {
        $statuskey = 'unavailable';
        $statuslabel = get_string('status_unavailable', 'local_inventario');
        $statusclass = 'badge bg-secondary text-white';
    } else {
        $statuskey = $hasactive ? $object->status : 'available';
        $statuslabel = get_string('status_' . $statuskey, 'local_inventario');
        $statusclass = $statuskey === 'available' ? 'badge bg-success text-white' : 'badge bg-danger text-white';
    }
    $unreturned = (!$hasactive && $service->get_past_reservations_open($object->id) > 0);
    $statushtml = html_writer::span($statuslabel, $statusclass);
    if ($unreturned) {
        $statushtml .= ' ' . html_writer::span(
            get_string('unreturned', 'local_inventario'),
            'badge bg-warning text-white'
        );
    }
    $namebadge = format_string($object->name);
    $lastres = $service->get_last_reservation_summary($object->id);
    if ($lastres && $lastres['status'] === 'active') {
        $namebadge .= ' ' . html_writer::span(
            get_string('reservationactive', 'local_inventario'),
            'badge bg-success text-white ms-1'
        );
    }

    $propvalues = $service->get_property_values($object->id);
    $proplines = [];
    foreach ($propvalues as $propid => $val) {
        $label = $propertynames[$propid] ?? get_string('property', 'local_inventario') . ' #' . (int)$propid;
        if ($val === '1') {
            $valdisplay = get_string('yes');
        } else if ($val === '0') {
            $valdisplay = get_string('no');
        } else {
            $valdisplay = $val;
        }
        $proplines[] = $label . ': ' . s($valdisplay);
    }

    $detailparts = [];
    $detailparts[] = html_writer::div(
        html_writer::tag('strong', get_string('object', 'local_inventario') . ': ') .
        format_string($object->name)
    );
    $detailparts[] = html_writer::div(
        html_writer::tag('strong', get_string('site', 'local_inventario') . ': ') .
        format_string($siteoptionsraw[$object->siteid] ?? '')
    );
    $detailparts[] = html_writer::div(
        html_writer::tag('strong', get_string('type', 'local_inventario') . ': ') .
        format_string($typeoptionsraw[$object->typeid] ?? '')
    );
    $detailparts[] = html_writer::div(
        html_writer::tag('strong', get_string('status', 'local_inventario') . ': ') .
        s($statuslabel)
    );
    if (!empty($object->currentlocation)) {
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('location', 'local_inventario') . ': ') .
            format_string($object->currentlocation)
        );
    }
    if (!empty($object->description)) {
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('description') . ': ') .
            format_text($object->description, FORMAT_HTML),
            'mt-2'
        );
    }
    // Disponibilità estesa (solo Pro).
    if (!empty($object->availableperiodenabled)) {
        $availabilitylines = [];
        if (!empty($object->availablefrom)) {
            $availabilitylines[] = get_string('availability_from', 'local_inventario') . ': ' . userdate($object->availablefrom);
        }
        if (!empty($object->availableto)) {
            $availabilitylines[] = get_string('availability_to', 'local_inventario') . ': ' . userdate($object->availableto);
        }
        $slotsraw = trim((string)$object->availabletimes);
        if ($slotsraw !== '') {
            $slots = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $slotsraw)));
            if (!empty($slots)) {
                $availabilitylines[] = get_string('availability_times', 'local_inventario') . ': ' . implode(', ', $slots);
            }
        }
        $availabilitylines[] = get_string('status', 'local_inventario') . ': ' .
            ($currentlyavailable ? get_string('status_available', 'local_inventario') : get_string('status_unavailable', 'local_inventario'));
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('availability', 'local_inventario')) . ' ' .
            html_writer::alist($availabilitylines),
            'mt-2'
        );
    }
    if ($lastres) {
        $detailparts[] = html_writer::div(
            html_writer::tag('strong', get_string('lastreservation', 'local_inventario') . ': ') .
            s($lastres['user'] . ' (' . $lastres['period'] . ')')
        );
    }
    if (!empty($proplines)) {
        $detailparts[] = html_writer::div(html_writer::tag('strong', get_string('properties', 'local_inventario')), 'mt-2');
        $detailparts[] = html_writer::alist($proplines);
    }
    $detailhtml = implode('', $detailparts);

    $reserveurl = new moodle_url('/local/inventario/reservations.php', [
        'siteid' => $object->siteid,
        'objectid' => $object->id,
    ]);

    $objectdata[] = [
        'id' => $object->id,
        'name' => format_string($object->name),
        'name_badge' => $namebadge,
        'site' => $siteoptionsraw[$object->siteid] ?? '',
        'status' => $statushtml,
        'visible' => (bool)$object->visible,
        'canmanage' => $canmanage,
        'lastreservation' => $lastres,
        'detail_html' => $detailhtml,
        'detail_title' => format_string($object->name),
        'reserve_url' => $reserveurl->out(false),
    ];
}

$reservationdata = [];
// Limit reservations for display.
$reservationslist = array_values($reservations);
usort($reservationslist, static function ($a, $b) {
    // Newer/upcoming reservations first.
    return $b->timestart <=> $a->timestart;
});
if ($resperpage > 0 && count($reservationslist) > $resperpage) {
    $reservationslist = array_slice($reservationslist, 0, $resperpage);
}

foreach ($reservationslist as $reservation) {
    $reservationdata[] = [
        'id' => $reservation->id,
        'object' => format_string($reservation->objectname),
        'user' => fullname($reservation),
        'site' => format_string($reservation->sitename),
        'period' => userdate($reservation->timestart) . ' → ' . userdate($reservation->timeend),
        'location' => format_string($reservation->location),
        'canmanage' => $canmanage || $reservation->userid == $USER->id,
    ];
}

$renderable = new \local_inventario\output\dashboard([
    'license' => [
        'status' => $license->status,
        'expires' => $license->expiresat ? userdate($license->expiresat) : '',
        'domain' => $license->domain,
        'allowhidden' => $allowhidden,
    ],
    'filters' => [
        'siteoptions' => $siteoptions,
        'selectedsite' => $siteid,
        'availableonly' => $onlyavailable,
    ],
    'objects' => $objectdata,
    'reservations' => $reservationdata,
        'stats' => [
            'objects' => $stats['objects'],
            'reservations' => $stats['reservations'],
            'userreservations' => $stats['userreservations'],
            'usage' => array_values(array_map(static function ($row) {
                return [
                    'name' => format_string($row->name),
                    'total' => $row->total,
                ];
            }, $stats['usage'] ?? [])),
            ],
        'objectfilters' => [
            'search' => $search,
            'selectedtype' => $typefilter,
            'selectedstatus' => $statusfilter,
            'selectedsite' => $siteid,
            'perpage' => $perpage,
            'perpageoptions' => array_map(
                static function ($val) use ($perpage) {
                    return [
                        'value' => $val,
                        'label' => $val === 0 ? get_string('all', 'local_inventario') : $val,
                        'selected' => ((int)$val === (int)$perpage),
                    ];
                },
                [10, 20, 50, 100, 0]
            ),
        'types' => array_map(static function ($id, $name) use ($typefilter) {
            return ['id' => $id, 'name' => format_string($name), 'selected' => ((int)$id === (int)$typefilter)];
        }, array_keys($typeoptionsraw), $typeoptionsraw),
        'sites' => array_map(static function ($id, $name) use ($siteid) {
            return ['id' => $id, 'name' => format_string($name), 'selected' => ((int)$id === (int)$siteid)];
        }, array_keys($siteoptionsraw), $siteoptionsraw),
        'statuses' => [
            [
                'value' => '',
                'label' => get_string('all', 'local_inventario'),
                'selected' => $statusfilter === '',
            ],
            [
                'value' => 'available',
                'label' => get_string('status_available', 'local_inventario'),
                'selected' => $statusfilter === 'available',
            ],
            [
                'value' => 'reserved',
                'label' => get_string('status_reserved', 'local_inventario'),
                'selected' => $statusfilter === 'reserved',
            ],
            [
                'value' => 'offsite',
                'label' => get_string('status_offsite', 'local_inventario'),
                'selected' => $statusfilter === 'offsite',
            ],
            ],
    ],
    'reservationfilters' => [
        'perpage' => $resperpage,
        'perpageoptions' => array_map(
            static function ($val) use ($resperpage) {
                return [
                    'value' => $val,
                    'label' => $val === 0 ? get_string('all', 'local_inventario') : $val,
                    'selected' => ((int)$val === (int)$resperpage),
                ];
            },
            [10, 20, 50, 100, 0]
        ),
    ],
    'links' => [
        // Reset filters: point back to dashboard without params to avoid capability issues.
        'objects' => (new moodle_url('/local/inventario/index.php'))->out(false),
        'types' => (new moodle_url('/local/inventario/types.php'))->out(false),
        'sites' => (new moodle_url('/local/inventario/sites.php'))->out(false),
        'properties' => (new moodle_url('/local/inventario/properties.php'))->out(false),
        'reservations' => (new moodle_url('/local/inventario/reservations.php'))->out(false),
        'stats' => (new moodle_url('/local/inventario/stats.php'))->out(false),
        'license' => (new moodle_url('/local/inventario/license.php'))->out(false),
    ],
    'canmanage' => $canmanage,
    'canreserve' => $canreserve,
]);

$renderer = $PAGE->get_renderer('local_inventario');

echo $OUTPUT->header();
echo local_inventario_render_nav($context);

if (!empty($license->expiresat) && $license->expiresat < time()) {
    echo $OUTPUT->notification(
        get_string('licenseexpired', 'local_inventario', userdate($license->expiresat)),
        'error'
    );
} else if ($license->status !== 'pro') {
    echo $OUTPUT->notification(
        get_string('licenseinvalid', 'local_inventario'),
        'warning'
    );
}

echo $renderer->render_dashboard($renderable);
echo $OUTPUT->footer();
