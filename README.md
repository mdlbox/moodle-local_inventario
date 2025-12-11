# Inventario (local_inventario)

Inventario is a Moodle local plugin to manage any kind of objects, their locations and their reservations. It lets you run an inventory and distribute items via reservations to teachers assigned the dedicated Moodle role “Prenotazione da inventario”. Reservations can be browsed in list or calendar view, showing upcoming, ongoing and past bookings. The plugin also sends reminders when an item has not been returned at the end of its reservation.

## Requirements
- Moodle 4.0 – 5.1 (see `version.php`)
- PHP/MySQL versions supported by your Moodle release

## Installation
1. Clone or copy this folder into `local/inventario`.
2. As an admin, go to `Site administration → Notifications` (or run `php admin/cli/upgrade.php`) to install.

## Configuration
To unlock all features you need a license from `api.mdlbox.com`. The plugin works for free with some limitations.
- Set the license/API key received by email after purchase: `Site administration → Plugins → Local plugins → Inventario`.
- Define sites, object types and object properties before adding objects and creating reservations.

## Permissions
The plugin UI and the Inventario block (`moodle-block_inventario`) are visible only if the user has the role “Prenotazione da inventario” assigned in `/admin/roles/manage.php`.
- `local/inventario:view` view the plugin UI.
- `local/inventario:reserve` create reservations.
- `local/inventario:manageobjects`, `local/inventario:manageproperties`, `local/inventario:managesites` manage catalog data.
- `local/inventario:deletereservations`, `local/inventario:togglevisibility` manage visibility and deletions.
- `local/inventario:managelicense` configure the license/API key.

## Scheduled tasks
- `local_inventario\task\license_sync` keeps the license status in sync.
- `local_inventario\task\expired_reservations_notify` sends notifications for expired reservations.
- `local_inventario\task\overdue_reservations_reminder` reminds users about items not returned.
Configure Moodle cron as usual (`admin/cli/cron.php` or system cron) to run these tasks.

## License
GNU GPL v3 or later. See `license.php` and the headers in each source file.
