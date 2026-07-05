# 🌟 MEEDO Stall & Rental Monitoring System

**Odiongan Public Market MEEDO** — a PHP + MySQL web app for managing **stalls**, **tenants**, and **monthly rental payments** (including **overdue penalties** and **financial reports**).

---

## ✨ Features

### 👤 Authentication
- Login for two roles:
  - **Administrator**
  - **Treasury**

### 📊 Stall Monitoring
- View all stalls with:
  - Stall number
  - Tenant (if occupied)
  - Section name + icon
  - Payment status badge (**Paid / Pending / Overdue**)
- Overdue accounts table
- Search + section/status filters
- CSV export (client-side)

### 🏗️ Manage Stalls
- Create **Sections** (with icon + display order)
- Create **Stalls** under sections (auto-generated stall numbers)
- Vacate stalls / delete stalls/sections (with cascade behavior)

### 🧾 Tenant Management
- Register tenants
- Edit tenant information

### 💰 Financial Reports
- **Monthly report** (collections/payments)
- **Annual report** (monthly breakdown)
- **Overdue report**

---

## 🧰 Tech Stack

- **PHP** (procedural)
- **MySQL / MariaDB**
- **mysqli** extension
- **CSS**: project styles in `css/`
- **UI**: Font Awesome + Google Fonts

---

## 📁 Project Layout (important files)

- **Login / navigation**
  - `index.php` — login page
  - `homepage.php` — dashboard home
  - `includes/sidebar.php` — shared sidebar + page guard
- **Main modules**
  - `stall-monitoring.php` — monitoring, payment generation, overdue updates
  - `stall-details.php` — details page per stall + payment history
  - `manage-stalls.php` — sections/stalls management
  - `register-tenants.php` — tenant registration
  - `edit-tenant.php` — edit tenant
  - `vacate-stall.php` — vacate stall
  - `financial-reports.php` — monthly/annual/overdue reports
- **Backend helpers**
  - `includes/database.php` — database connection
  - `includes/auth.php` — session guard (`require_login()`)
  - `includes/login-utils.php`
- **SQL reference scripts**
  - `sql-inuse/table.txt` — schema for `sections`, `stalls`, `tenants`, `login`
  - `sql-inuse/account.txt` — seed login accounts

---

## ✅ Requirements

- PHP with **mysqli** enabled
- MySQL/MariaDB
- Web server (XAMPP recommended)

---

## 🗄️ Database Setup (MySQL)

### 1) Create the database
The application connects to database name:
- `meedo_system`

### 2) Create tables
Use:
- `sql-inuse/table.txt`

This creates:
- `sections`
- `stalls`
- `tenants`
- `login` (schema is in `sql-inuse/table.txt` and/or `sql-inuse/table.txt`)

### 3) Seed user accounts
Use:
- `sql-inuse/account.txt`

Seeded accounts:

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | `Administrator` |
| `treasury` | `treasury123` | `Treasury` |

### 4) Notes about `payments`
`payments` may be created at runtime by `stall-monitoring.php` (it includes a `CREATE TABLE IF NOT EXISTS payments ...`).

---

## 🚀 How to Run (Local / XAMPP)

1. Start **Apache** + **MySQL** in XAMPP.
2. Ensure project is located at:
   - `c:/xampp/htdocs/MEEDO-System/`
3. Open in browser:
   - `http://localhost/MEEDO-System/`
4. Login using the seeded accounts.

---

## 🧠 Usage Quick Guide

### Stall Monitoring
- Open **Stall Monitoring** to view stalls and payment status
- Overdue accounts are automatically updated based on date

### Manage Stalls
- Add sections and stalls
- Stall numbers are auto-generated using the section id

### Tenant + Vacate
- Assign/edit tenants when a stall is occupied
- Vacate removes tenant association and marks stall as vacant

### Financial Reports
- Use filters inside `financial-reports.php`
- Export generates CSV from the current table view

---

## 🔒 Security Notes (for production)

Current app uses session-based auth via:
- `includes/auth.php`

For production hardening, ensure:
- Consistent use of **prepared statements** across all queries
- **CSRF protection** for all POST actions
- Server-side validation for all inputs
- Prefer removing any legacy plain-text password support (if not needed)

---

## 🧾 Development Notes
- A roadmap exists in `TODO.md`.

---

## 📄 License

Not specified in repository. Add your preferred license text (e.g., MIT/Apache/GPL) if needed.

