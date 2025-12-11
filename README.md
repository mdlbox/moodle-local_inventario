# Inventario (local_inventario)

Inventario is a Moodle local plugin to manage assets, their locations and reservations, with both list and calendar views.

## Requirements
- Moodle 4.0 – 5.1 (see `version.php`)
- PHP/MySQL versions supported by the target Moodle release

## Installation
1. Clone or copy this folder to `local/inventario`.
2. From the site as admin, visit `Site administration → Notifications` (or run `php admin/cli/upgrade.php`) to install.

## Configuration
- Set license/API key: `Site administration → Plugins → Local plugins → Inventario`.
- Define sites, types and properties before adding objects and reservations.

## Permissions
- `local/inventario:view` view the plugin UI.
- `local/inventario:reserve` create reservations.
- `local/inventario:manageobjects`, `local/inventario:manageproperties`, `local/inventario:managesites` manage catalog data.
- `local/inventario:deletereservations`, `local/inventario:togglevisibility` manage visibility and deletions.
- `local/inventario:managelicense` configure the license/API key.

## Scheduled tasks
- `local_inventario\task\license_sync` keeps the license status in sync.
- `local_inventario\task\expired_reservations_notify` notifies when reservations expire.
- `local_inventario\task\overdue_reservations_reminder` reminds users about overdue items.
Configure Moodle cron as usual (`admin/cli/cron.php` or system cron) to run these.

## Development
- Source JS lives in `amd/src`; after changes, rebuild with Moodle’s Grunt tasks (e.g. `npx grunt amd` from the Moodle root).
- Strings live under `lang/en/` and `lang/it/`; avoid hard-coded text in PHP/JS.

## License
GNU GPL v3 or later. See `license.php` and headers in each source file.
