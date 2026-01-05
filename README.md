# Inventario — Inventory & Booking System (local_inventario)

**Inventario** is a Moodle local plugin designed to manage physical objects and their reservations directly within the Moodle LMS.

It allows institutions, schools, universities, and organizations to create a structured inventory, define object types and properties, and manage bookings in a simple, secure, and controlled way.

The plugin is based on real needs observed during Moodle consulting projects.

---

## Features

- Manage an inventory of physical objects
- Define custom object properties
- Group properties and assign them to object types
- Create and manage object types
- Allow bookings only to authorized users
- List and calendar views for reservations
- Track upcoming, ongoing, and past bookings
- Automatic reminders for overdue reservations
- Full integration with Moodle roles and permissions
- Free version available, with optional PRO license

---

## Free version

The plugin is distributed **for free** and can be used without any cost.

The Free version is fully functional but includes some limitations:
- Maximum **15 objects**
- Maximum **5 properties**
- No access to advanced features

It is ideal for testing, small inventories, or for users who want to manage a limited number of objects at no cost.

---

## PRO version

The **PRO license** unlocks the full potential of the plugin.

With the PRO version you get:
- Removal of all limits on objects and properties
- Advanced inventory and booking features
- Additional views and filtering options
- Email support
- Continuous improvements driven by user feedback
- and more...

The PRO version is activated using an **API key**, sent automatically by email after purchase.

📌 **All detailed information about the plugin and the PRO version is available at:**
https://mdlbox.com

🛒 **To purchase a PRO license, please visit:**
https://api.mdlbox.com/purchase.php?lang=en

---

## 7-day trial

Not sure yet?

We offer a **7-day trial license** that allows you to test all PRO features without limits.

License purchases are **non-refundable**, as a refund system has not yet been implemented.
This policy may change in the future.

---

## Requirements

- Moodle **4.0 – 5.1** (see `version.php`)
- PHP/MySQL versions supported by your Moodle release

---

## Installation

1. Clone or copy this folder into `local/`
2. As an administrator, go to
   **Site administration → Notifications**
   or run `php admin/cli/upgrade.php`

---

## Configuration

- The plugin works immediately in Free mode
- To unlock PRO features, set the license/API key at:
  **Site administration → Plugins → Local plugins → Inventario**
- Before creating objects and bookings, define:
  - Object properties
  -- Property groups
  - Object types
  - Sites (locations)
  - Create Objects

---

## Roles & permissions

During installation, the plugin automatically creates a system-level role named **“Inventory & Booking”**.

Only users assigned to this role can access the plugin and create reservations.

Main capabilities include:
- `local/inventario:view`
- `local/inventario:reserve`
- `local/inventario:manageobjects`
- `local/inventario:manageproperties`
- `local/inventario:managesites`
- `local/inventario:deletereservations`
- `local/inventario:togglevisibility`
- `local/inventario:managelicense`

---

## Scheduled tasks

The plugin uses Moodle scheduled tasks to automate key processes:

- `local_inventario\task\license_sync`
- `local_inventario\task\expired_reservations_notify`
- `local_inventario\task\overdue_reservations_reminder`

Ensure Moodle cron is configured correctly.

---

## Support & feedback

- Free users: basic support via GitHub
- PRO users: email support

We are two developers and Moodle consultants, not a software house.
Feedback, suggestions, and bug reports are always welcome and help us improve the plugin.