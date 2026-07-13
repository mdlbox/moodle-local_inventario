# Inventario — Inventory & Booking System (local_inventario)

**Inventario** is a Moodle local plugin to manage physical objects and their
reservations directly within the Moodle LMS. It also includes an optional
staff-absence and substitution board.

It lets institutions, schools, universities and organisations build a structured
inventory, define object types and custom properties, and manage bookings in a
simple, secure and controlled way.

---

## Features

- Manage an inventory of physical objects with photos and attachments
- Object condition and maintenance state (objects in maintenance are not bookable)
- Define custom object properties, group them and assign them to object types
- Sites (locations), object types and reservations
- List, month, week and day calendar views, with drag-to-select multi-object booking
- Availability windows and periodic (recurring) reservations
- Bulk actions on objects (show/hide, maintenance, delete)
- CSV import/export of properties, types and objects
- Automatic reminders for overdue reservations and confirmation emails
- Staff absence dashboard, substitute management and a public daily board
- Full integration with Moodle roles, capabilities, events log, Privacy API and
  the Moodle Mobile app

---

## Requirements

- Moodle **4.0 – 5.1** (see `version.php`)
- PHP/database versions supported by your Moodle release

---

## Installation

1. Copy this folder into `local/inventario`.
2. As an administrator, go to **Site administration → Notifications**
   (or run `php admin/cli/upgrade.php`) to complete the installation.

---

## Configuration

- After installing, define, in order: object **properties**, **property groups**,
  object **types**, **sites** (locations) and finally the **objects**.
- Plugin options are available at
  **Site administration → Plugins → Local plugins → Inventario**
  (public board, periodic reservations, calendar window, notification templates).

---

## Roles & permissions

During installation the plugin creates a system-level role named
**"Inventory & Booking"**. Only users granted the plugin capabilities can access
it and create reservations. Main capabilities include:

- `local/inventario:view`
- `local/inventario:reserve`
- `local/inventario:manageobjects`
- `local/inventario:manageproperties`
- `local/inventario:managesites`
- `local/inventario:deletereservations`
- `local/inventario:togglevisibility`
- `local/inventario:addabsence`
- `local/inventario:manageabsences`

---

## Scheduled tasks

The plugin uses Moodle scheduled tasks to automate key processes:

- `local_inventario\task\expired_reservations_notify`
- `local_inventario\task\overdue_reservations_reminder`
- `local_inventario\task\archive_expired_absences`

Ensure Moodle cron is configured correctly.

---

## Privacy

The plugin stores reservations, staff absences and the author of each inventory
object. All of this is declared through Moodle's Privacy API
(`classes/privacy/provider.php`) and is exported/deleted on request. The plugin
makes **no external calls** and requires **no external activation**.
