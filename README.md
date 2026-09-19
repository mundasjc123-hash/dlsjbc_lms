# Library Management System (Capstone)

A school library system built with plain PHP, MySQL, HTML, CSS, and JS —
no frameworks.

## What you need

- XAMPP (or similar) with PHP 8.2+ and MySQL/MariaDB
- A web browser

## Setup steps

1. Copy this whole `lms` folder into your `htdocs` folder.
2. Open phpMyAdmin (or your MySQL client) and create a new, empty database.
   Example name: `lms_db`.
3. Import `database/schema.sql` into that database. This creates all tables.
4. Open `config/config.php` and check that the database name, username, and
   password match your setup. Defaults: database `lms_db`, user `root`,
   no password (normal for a fresh XAMPP install).
5. Run the seeder. This adds starting data (roles, patron categories,
   material types, collections) and one admin account.
   - From a terminal, inside the project folder:
     ```
     php database/seed.php
     ```
   - This prints the admin login:
     **ID Number: `ADMIN-0001`  Password: `admin123`**
6. Point your web server's document root to the `public/` folder. This is
   what keeps `core/`, `config/`, `database/` etc. safe from being opened
   directly in a browser.
   - Recommended: set up a Virtual Host in XAMPP pointing to `.../lms/public`.
   - Fallback: every folder outside `public/` already has an `.htaccess`
     file that blocks direct browser access, even without a Virtual Host.
     (Note: if you skip the Virtual Host, the site will only work correctly
     under its own domain/subfolder — ask if you'd like the exact Virtual
     Host steps.)
7. Visit the site in your browser. You'll be redirected to `/login`.
   Log in with the admin account above.

## Folder guide

| Folder | What's in it |
|---|---|
| `public/` | The only folder the browser can reach. Router, CSS/JS, images. |
| `core/` | Shared code every feature uses: database connection, login, CSRF protection, audit logging. |
| `features/` | One folder per feature (auth, dashboard, and later: circulation, catalog, fines...). Each has its own `routes.php`, `pages/`, and logic files. |
| `templates/` | Shared page shell: header, footer, layout. |
| `config/` | Database and app settings. `config.php` is your real, local copy (not committed to git). |
| `database/` | `schema.sql` (creates all tables) and `seed.php` (adds starting data). |
| `storage/` | Logs and other files the app writes at runtime. |

## How routing works (no framework)

Every request goes through `public/index.php`. It reads the URL, checks
every feature's `routes.php` for a match, and runs that feature's page file
wrapped inside `templates/layout.php`. Adding a new page means adding one
line to a feature's `routes.php` — nothing else changes.

## Security notes

- Passwords are hashed with Argon2id (`password_hash()`), never stored in
  plain text.
- All database queries use PDO prepared statements — no raw SQL string
  building, which prevents SQL injection.
- Every state-changing form includes a CSRF token (`Csrf::field()`),
  checked with `Csrf::verify()` before anything happens.
- Every login, logout, and failed login attempt is written to `audit_log`
  via `Audit::log()`. Every future feature that changes data should do
  the same — see `features/auth/pages/login.php` for the pattern.

## What's built so far (Phase 1)

- Full database schema (all modules, so tables already connect correctly)
- Router, shared layout, login, logout, audit logging, CSRF protection
- A placeholder dashboard to confirm login works end-to-end

Next up: user management, then catalog + OPAC, then circulation — one
feature per session.
