# Automated Business Listing System (PHP + MySQL)

A lightweight BrightLocal-style operations app for manual directory submissions.

## Features in this MVP

- Tailwind CSS dashboard UI (clean, responsive, modern)
- Homepage login entry (`index.php`) for both admin and regular users
- Role-based access control:
   - Admin: full control, including system directory settings
   - Regular users: add/edit businesses, add/update citations
- Business Manager table with `Location Manager` action on each row
- Location Manager detail page with:
   - full business information
   - associated citations
   - citation add/update workflow
- Human-readable business hours input (no JSON required)
- Reusable bootstrap/RBAC/layout structure for better maintainability

## Project Structure

- `app/` - config, DB, auth, RBAC, bootstrap, shared layout/helpers
- `database/schema.sql` - MySQL schema
- `public/` - application pages (dashboard, businesses, location manager, setup)


## Requirements

- XAMPP (Apache + MySQL + PHP)
- PHP 8.0+ recommended
- MySQL 8+ or MariaDB equivalent

## Deploy on NGINX (Paid Hosting)

This project can run on NGINX + PHP-FPM. The app code is server-agnostic, but Apache `.htaccess` rules must be translated to NGINX `location` blocks.

1. Upload the project to your hosting account.
2. Point your domain/subdomain document root to the project root (the folder containing `index.php`, `client_portal.php`, and `public/`).
3. Apply an NGINX server config based on `deploy/nginx.conf.example`.
4. Update `app/config.php`:
   - `db_host`, `db_name`, `db_user`, `db_pass`
   - `base_url` (examples: `''`, `/`, or `/Citeflow_Manager` depending on your hosting path)
5. Confirm writable permissions for `public/uploads/`.
6. Run setup once via `/setup.php` if your database is not initialized.

Notes:
- NGINX must route clean URLs to files in `public/` (equivalent to Apache rewrite behavior).
- Access to internal folders (`app/`, `database/`, `memories/`, `.git/`, etc.) should be denied at web-server level.
- PHP execution inside `public/uploads/` should be blocked.

## Quick Start (XAMPP)

1. Place project in: `c:/xampp/htdocs/Citeflow_Manager`
2. Start Apache and MySQL from XAMPP control panel.
3. Create database in phpMyAdmin:
   - DB name: `business_listing`
4. Open config and adjust if needed:
   - `app/config.php`
   - set database credentials, database name, and base URL
5. Run web setup:
   - `http://localhost/Citeflow_Manager/public/setup.php`
6. Create admin account via setup form.
7. Login:
   - `http://localhost/Citeflow_Manager/public/index.php`

## Suggested Workflow

1. Add Businesses.
2. Open `Location Manager` for a selected business.
3. Add citations from available directories.
4. Update citation status/URL/notes as progress changes.
5. Use dashboard metrics and table for daily operations.
6. Admin users maintain directory settings as needed.

## Security Notes (MVP)

- This is a practical MVP and should be hardened before production:
  - Add CSRF protection
  - Restrict upload MIME types and extensions
  - Add stricter role-based permissions per action
  - Add password reset and session hardening
  - Add rate limiting and audit views

## Next Phase Enhancements

- Bulk task generation (one business -> many directories)
- Directory field templates and one-click copy packs
- Rejection reason taxonomy and rework queue
- Client report export (CSV/PDF)
- API endpoints and optional browser-assist automation
- Exportable Playwright scripts for deeper assisted automation on machines with Node.js
