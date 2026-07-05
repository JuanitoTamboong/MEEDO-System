# MEEDO System — Stall & Rental Monitoring

A PHP/MySQL web application for managing market **stalls**, **tenants**, and **rental payments** for **Odiongan Public Market MEEDO**.

## Features

- **Authentication** (Administrator / Treasury)
- **Dashboard & monitoring**
  - List stalls with tenant + section info
  - Payment status badges (Paid / Pending / Overdue)
  - Search/filter and CSV export (client-side)
- **Stall management**
  - Create sections
  - Create stalls under sections
  - Vacate stalls
- **Tenant management**
  - Register and edit tenant records
- **Financial reports**
  - Monthly report (collections/payments)
  - Annual report (monthly breakdown)
  - Overdue report

## Tech Stack

- **PHP** (procedural)
- **MySQL / MariaDB**
- **Bootstrap-style custom CSS** (project CSS files)
- **Font Awesome** + **Google Fonts**

## Project Structure (high level)

- Entry pages:
  - `index.php` (login page)
  - `homepage.php`
  - `stall-monitoring.php`
  - `manage-stalls.php`
  - `register-tenants.php`
  - `edit-tenant.php`
  - `vacate-stall.php`
  - `financial-reports.php`
  - `stall-details.php`
- Shared:
  - `includes/database.php` (DB connection)
  - `includes/auth.php` (session guard)
  - `includes/login-utils.php`
  - `includes/sidebar.php`
- Assets:
  - `assets/` (logo/background)
  - `css/` (page styles)
  - `js/` (utility JS)
- SQL references:
  - `sql-inuse/table.txt` (schema for sections/stalls/tenants)
  - `sql-inuse/account.txt` (seed login accounts)

## Requirements

- PHP (with mysqli enabled)
- MySQL/MariaDB
- Web server (XAMPP recommended given `c:/xampp/htdocs` layout)

## Database Setup

1. **Create the database**
   - Database name used in `includes/database.php`:
     - `meedo_system`

2. **Import schema**
   - Use `sql-inuse/table.txt` to create:
     - `sections`
     - `stalls`
     - `tenants`

3. **Seed accounts**
   - Import `sql-inuse/account.txt` into `login` table.
   - The `login` table definition is in `sql-inuse/table.txt`.

4. **Login credentials** (from `sql-inuse/account.txt`)

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | `Administrator` |
| `treasury` | `treasury123` | `Treasury` |

> Note: The application may attempt to create the `payments` table at runtime (see `stall-monitoring.php`).

## How to Run (Local with XAMPP)

1. Start **Apache** and **MySQL** in XAMPP.
2. Copy/ensure this project is in:
   - `c:/xampp/htdocs/MEEDO-System/`
3. Open the app in browser:
   - `http://localhost/MEEDO-System/`
4. Login using the seeded accounts.

## Usage Guide

### 1) Stall Monitoring

- View all stalls and current tenant details.
- Payment status is shown per stall.
- Overdue accounts are displayed in a separate table.

### 2) Manage Stalls

- Add sections (icon + display order).
- Add stalls to sections (stall number is auto-generated based on section id).
- Delete sections/stalls (section deletion cascades to stalls).

### 3) Register / Edit Tenant

- Assign a tenant to an occupied stall.
- Edit tenant details while stall remains occupied.

### 4) Vacate Stall

- Removes tenant association and marks stall as vacant.

### 5) Financial Reports

- Monthly collections based on paid payments.
- Annual breakdown by month.
- Overdue accounts report.

## CSV Export

Exports are generated on the client-side by reading the HTML table and generating a CSV file for download.

## Security Notes (Important)

- The app uses session-based auth via `includes/auth.php`.
- Some pages use prepared statements, but not all SQL is fully parameterized.
- For production use, harden:
  - SQL queries (use prepared statements consistently)
  - Input validation and output escaping
  - CSRF protection for POST actions

## Development / Testing

- There is an existing `TODO.md` tracking planned upgrades.
- Validate flows:
  - Login (Administrator + Treasury)
  - Add section / add stall
  - Register tenant and edit tenant
  - Vacate stall
  - Payment generation + overdue updates
  - Financial reports filters and exports

## License

Add your preferred license header here (not specified in repository).

