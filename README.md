# 🌾 Lagonglong FARMS (Agriculture-System)

A PHP/MySQL web application for Municipal Agriculture Office operations: farmer registry, yield monitoring, input inventory and distribution, maritime records, and rich reporting with saved HTML exports.

## Overview

This repository contains the Lagonglong FARMS system used by the MAO to manage end‑to‑end agricultural operations. Core pages live at the project root (PHP) and shared UI/logic is under `includes/`. Assets (CSS/JS/fonts) are under `assets/`. Generated reports are saved to `reports/`, and uploaded farmer photos to `uploads/farmer_photos/`.

Key entrypoints:
- `login.php` — authentication for MAO staff with roles
- `index.php` — main dashboard (stats, charts, quick actions, activities)
- `farmers.php` — farmers registry and management
- `yield_monitoring.php` — record and analyze yields
- `mao_inventory.php` — manage inputs and inventory
- `distribute_input.php` / `input_distribution_records.php` — distribution workflow and history
- `reports.php` — generate and auto-save report HTML files; view recent via `get_saved_reports.php`
- `boat_records.php`, `ncfrs_records.php`, `fishr_records.php` — maritime/fisherfolk modules

Helper endpoints/components:
- `get_report_data.php` — JSON data for charts (yield, activities, distribution, etc.)
- `get_barangays.php`, `get_farmers.php`, `search_farmers.php` — data/ajax helpers
- `includes/layout_start.php` — authenticated layout shell (sidebar + topbar)
- `includes/sidebar.php` / `nav.php` — navigation components
- `includes/activity_logger.php` — audit logging helper
- `includes/assets.php` — asset loader (offline/CDN) + Chart.js helper

## Tech stack

- PHP 7.4+ (mysqli) with sessions
- MySQL/MariaDB
- Bootstrap 5 + Tailwind (via script) + Font Awesome
- jQuery and Chart.js (via `includeChartAssets()`)

Assets are loaded locally by default (`includes/assets.php` sets `$offline_mode = true`). Switch to CDN by setting `$offline_mode = false`.

## Setup (Windows + XAMPP)

Prerequisites:
- XAMPP with Apache and MySQL/MariaDB
- PHP 7.4+ (bundled with XAMPP)

Steps:
1) Place this folder under `C:\xampp\htdocs\Agriculture-System` (or clone to that path)
2) Configure database connection in `conn.php`:
   - Default values:
     - host: `localhost`
     - user: `root`
     - pass: `` (empty)
     - db: `agriculture-system`
   - Adjust to match your local DB and credentials.
3) Create/import the database schema for `agriculture-system`
   - Ensure required tables exist: `farmers`, `barangays`, `yield_monitoring`, `mao_inventory`, `mao_staff`, `roles`, `activity_logs`, `commodities`, any maritime tables you use, and `generated_reports` (auto-created if missing by `reports.php`).
4) Start Apache and MySQL in XAMPP Control Panel
5) Visit `http://localhost/Agriculture-System/login.php` and sign in

Directories that need write access by PHP:
- `reports/` — report HTML is saved here automatically
- `uploads/farmer_photos/` — uploaded images

## Authentication & Sessions

- `login.php` verifies credentials from `mao_staff` joined with `roles` and uses `password_verify()`
- `check_session.php` protects pages; inactive sessions expire after 30 minutes
- User info available via `$_SESSION` (`user_id`, `username`, `role`, `full_name`)

## Dashboard & Analytics

- `index.php` shows totals for farmers, RSBSA/NCFRS/Fisherfolk, boats, commodities, inventory, recent yields, and an activity list
- Charts fetch JSON from `get_report_data.php`, e.g. `type=yield_per_barangay`
- A modern layout is composed by `includes/layout_start.php` + `includes/sidebar.php` + `nav.php`

## Reports

- Generate in `reports.php` with date range and type; output is HTML styled for printing
- Every generated report is saved to `reports/` and recorded in `generated_reports`
- Recent/saved reports are listed via `get_saved_reports.php`

## Inventory & Distribution

- `mao_inventory.php` manages stock; totals are surfaced on dashboard
- `distribute_input.php` records distributions; history in `input_distribution_records.php`
- Distribution and yield data are available to charts via `get_report_data.php`

## Farmers & Maritime

- `farmers.php` handles registration and management; photo uploads go to `uploads/farmer_photos/`
- `boat_records.php`, `ncfrs_records.php`, `fishr_records.php` cover maritime data

## Activity Logging

- Use `includes/activity_logger.php::logActivity($conn, $action, $action_type, $details)`
- Activities appear on dashboard and `all_activities.php`

## Assets

- `includes/assets.php` loads Bootstrap, Tailwind (script), jQuery, FontAwesome, and optional Chart.js via `includeChartAssets()`
- Set `$offline_mode` to false to use CDN assets

## Security notes

- Sessions are enforced on all main pages (`includes/layout_start.php` includes `check_session.php`)
- Passwords should be hashed when inserted (login uses `password_verify()`)
- Prefer using prepared statements (most pages use mysqli prepared statements for mutations)
- Limit upload types/size for `uploads/farmer_photos/`

Tip: Do not commit real DB credentials. For production, create a least‑privileged DB user for the app and use a strong password.

## Operations & Endpoint Catalog

This section enumerates the system’s major operations, their purpose, main scripts, and side‑effects (DB changes, file writes, logs, sessions, JSON responses).

### 1. Authentication & Sessions
- Login (`login.php`): Verifies username/password (table: `mao_staff` + `roles`). On success sets `$_SESSION[user_id|username|role|full_name]` and logs activity (`activity_logs`).
- Session Guard (`check_session.php`): Redirects unauthenticated users to `login.php`; expires sessions after 30 minutes of inactivity.
- Logout (`logout.php`): Destroys session (not shown above but expected standard behavior) and may log an action.

### 2. Dashboard & Analytics
- Dashboard (`index.php`): Queries counts (farmers, program registrations, commodities, inventory sum, recent yields) and loads recent 10 activities.
- Chart Data (`get_report_data.php`): Returns JSON for chart types:
   - `yield_per_barangay`, `yield`, `activities`, `input_distribution`, `inventory`, `boat`, `farmer` (aggregations over related tables).
- Recent Activities Widget (`includes/recent_activities.php`): Fetches last 5 entries with staff/role join.

### 3. Farmer Registry
- Core Page (`farmers.php`): CRUD-style listing & forms (not fully listed here, inferred from usage in other scripts).
- Photo Upload (`upload_farmer_photo.php`): Validates farmer existence, file type (JPEG/PNG), size (<5MB), stores in `uploads/farmer_photos/`, inserts record into `farmer_photos`, logs activity, returns JSON.
- PDF/HTML Export (`pdf_export.php?action=export_pdf`): Generates styled HTML “export” for farmers based on filters (program flags, name/contact search, barangay). File is streamed (HTML attachment) not persisted in `reports/` by this script.

### 4. Programs & Maritime Modules
- `rsbsa_records.php`, `ncfrs_records.php`, `fishr_records.php`, `boat_records.php`: Present specialized filtered views (inferred) for each program / boat registry. Boat and program flags are stored on `farmers` table and/or related program-specific tables.

### 5. Inventory Management
- Inventory Page (`mao_inventory.php`): Displays current stock (joined across `input_categories` + `mao_inventory`).
- Add Stock / Initialize (`update_inventory.php` with `action=add_stock`): Increases `quantity_on_hand` or inserts new inventory row. Logs activity with action_type `input`.
- Adjust Stock (`update_inventory.php` with `action=update_stock`): Sets `quantity_on_hand` to exact value (correction). Logs change with before/after quantities.
- Input Visitation Requirement Check (`get_input_visitation_status.php?input_id=...`): Returns JSON `{input_name, requires_visitation}` from `input_categories`.

### 6. Distribution & Visitation Scheduling
- Distribute Input (`distribute_input.php`): Validates form fields; enforces positive quantity; requires visitation date for all distributions (logic forces `requires_visitation=1`). Transaction steps:
   1. Insert into `mao_distribution_log` (fields: farmer_id, input_id, quantity_distributed, date_given, visitation_date)
   2. Decrement `mao_inventory.quantity_on_hand`
   3. Log activity (action_type `input`)
   4. Commit or rollback on failures
   Sets session success/error message for redirect back to `mao_inventory.php`.
- Distribution Records (`input_distribution_records.php`):
   - Paginated listing with multi-field search (farmer name variations, contact, barangay, input). Exact farmer ID prioritization when provided.
   - PDF/HTML Export (`?action=export_pdf`): Streams styled HTML table of records based on filters.
   - Admin access enforced (`role=admin`).

### 7. Yield Monitoring
- Yield Monitoring (`yield_monitoring.php`): Records and lists yields; data source for `get_report_data.php` yield-related chart endpoints. (CRUD specifics inferred from dashboard and report aggregation queries.)

### 8. Reporting Engine
- Report Generation (`reports.php` POST `action=generate_report`): Builds HTML string via `generateReportContent()` for a `report_type` and date range. Always:
   - Saves file to `reports/report_<type>_<timestamp>.html`
   - Ensures table `generated_reports` exists (auto-creates if absent)
   - Inserts metadata row (report_type, dates, file_path, staff_id)
   - Logs activity (action_type `farmer`, message describes saved report)
   - Outputs HTML to browser (for view/print) / prints success message or error fallback.
- Report Save (`reports.php` POST `action=save_generated_report`): Persists HTML from client (if already rendered) following similar steps.
- Saved Reports List (`get_saved_reports.php`): Returns HTML snippet list of previously generated reports (recent-first) including author and timestamp.

### 9. Notifications System
- Fetch Notifications (`get_notifications.php`): Returns either counts (when `count_only=true`) or full notification list plus unread count using functions from `includes/notification_system.php` (not shown). JSON structure includes `success`, `notifications[]`, `unread_count`, `total_count`.
- Notification UI integrated in `nav.php` (bell icon toggles dynamic panel with scrollable list).

Categories currently surfaced in the app (no sensitive data exposed):
- Visitation reminders for distributions due in the next 7 days (and overdue)
- Low/Critical stock levels from MAO inventory
- Upcoming/Overdue MAO activities (today, tomorrow, next 14 days)
- Expiring input batches (already expired and up to 10 days ahead)

Clicking a notification opens the related page filtered to the specific item (e.g., a particular activity or batch).

### 10. Staff & Roles Management
- Add Staff (`staff_actions.php` POST `action=add_staff`): Admin-only. Validates uniqueness of username; validates role_id; hashes password (bcrypt via `password_hash`); inserts into `mao_staff`; logs activity (action_type `staff`). Returns status via session messages.
- Listing/Management (`staff.php`): Interface (inferred) for staff listing, referencing roles and actions script.

### 11. Activity Logging
- Utility (`includes/activity_logger.php::logActivity`): Inserts entry into `activity_logs` capturing `staff_id`, `action`, `action_type`, `details`, plus timestamp.
- Consumption: Dashboard, recent activities widget, auditing of inventory/distribution/staff/report operations.

### 12. File & Report Storage
- Farmer Photos: `uploads/farmer_photos/<farmerId>_<timestamp>.ext`
- Generated Reports: `reports/report_<type>_<timestamp>.html` accessible directly and indexed in `generated_reports`.
- Exported “PDF” pages are HTML downloads (use browser / external tool to convert to actual PDF if needed).

### 13. PDF/HTML Export Utilities
- Farmers Export: `pdf_export.php?action=export_pdf` builds HTML with custom `SimplePDF` class (misnamed; outputs HTML attachment) including summary stats and styling.
- Distribution Export: `input_distribution_records.php?action=export_pdf` streams filtered distribution dataset.

- Dedicated distribution export endpoint: `export_distribution_pdf.php` for generating distribution summaries.

### 14. Validation & Error Handling Patterns
- Server-side validation for required fields, numeric constraints, dates (`DateTime::createFromFormat`), file type/size for uploads.
- Transactions for critical multi-step operations (distribution) to maintain consistency.
- Session-based flash messaging (`$_SESSION['success']`, `$_SESSION['error']`, etc.) consumed by pages after redirect.

### 15. Security Considerations (Current State)
- Session enforcement via `check_session.php` (all major pages include layout which includes the check).
- Mixed use of direct queries and prepared statements; sensitive inserts (login, staff add, farmer checks) use prepared statements; some read queries still rely on escaped strings.
- Password storage: secure hashing for staff accounts with `password_hash` / `password_verify`.
- File upload sanitation limited to MIME type + size; consider adding extension whitelist and image reprocessing for stricter control.
- Inventory and distribution guarded by server-side validations and transactions.

### 16. Frontend Interactions
- AJAX/Fetch: Charts (`fetch(get_report_data.php?type=...)`), notifications (`get_notifications.php`), saved reports listing (`get_saved_reports.php`), possibly farmer search endpoints (e.g., `search_farmers.php`, `get_farmers.php`).
- Dynamic UI: Sidebar collapse state saved to `localStorage`; dropdown menus and notification panel managed via JS in `nav.php` / layout.

### 17. Planned Hardening (Not Yet Applied)
- Database credential separation (admin vs app user)
- Enforce least privilege and password in `conn.php`
- Expand prepared statement coverage
- Rate limiting or throttling for login and critical endpoints

## Suggested Improvements (Roadmap)
- Replace remaining raw `mysqli_query` with prepared queries for uniform security.
- Implement role-based authorization middleware (centralize checks beyond page-level conditions).
- Add CSRF tokens for all POST forms performing state changes.
- True PDF generation (e.g., DOMPDF, TCPDF) instead of HTML attachment naming.
- Central configuration file for environment (credentials, offline/CDN switch, feature flags).
- Add queue or cron for visitation reminders (email/SMS integration).

## Troubleshooting

- Blank/redirect to login: ensure session is working and you’re accessing via `http://localhost/...`
- Database connection failed: verify `conn.php` host/user/pass/db and that MySQL is running in XAMPP
- Charts not loading: check browser console and that `get_report_data.php` returns JSON
- Reports not saving: confirm `reports/` directory is writable
- Images not uploading: confirm `uploads/farmer_photos/` exists and is writable

## License & Contact

This system is for the Lagonglong Municipal Agriculture Office. Contact the system administrator for support and access.

## Transactions (complete list)

This is a concise index of user-facing transactions/features available in the system, grouped by module. File names are provided for easy reference. No sensitive information is included.

- Authentication & Sessions
   - Login, logout, and session timeout (login.php, logout.php, check_session.php)

- Farmers Registry
   - Register, view, edit farmer records (farmers.php, farmer_regmodal.php, farmer_editmodal.php)
   - Search and auto-suggest by name/contact (search_farmers.php, get_farmers.php)
   - Upload and manage farmer photos (upload_farmer_photo.php; uploads/farmer_photos/)
   - Program flags and registries: RSBSA, NCFRS, FishR (rsbsa_records.php, ncfrs_records.php, fishr_records.php)
   - Boat registration records (boat_records.php)
   - Geotagging capture (geotagging_modal.php)

- Commodities & Categories
   - Manage commodity list (commodities.php)
   - Category and suggestion endpoints (get_commodity_categories.php, get_commodity_suggestions.php)
   - Yield unit lookup and farmer-commodity linking (get_yield_unit.php, get_farmer_commodities.php)

- Inventory Management
   - Create new input items (add_new_input.php)
   - View and adjust MAO inventory (mao_inventory.php, update_inventory.php)
   - Mark stock-out batches and related flows (stockout_batch.php)
   - Expiring inputs view (expiring_inputs.php)
   - Input visitation requirement check (get_input_visitation_status.php)

- Input Distribution & Visitation
   - Distribute inputs to farmers (distribute_input.php)
   - View history and filter/paginate (input_distribution_records.php)
   - Mark visitation as completed (mark_distribution_complete.php)
   - Reschedule visitation and view history (mark_distribution_complete.php with reschedule flags; get_distribution_reschedules.php)
   - Distribution exports (input_distribution_records.php?action=export_pdf, export_distribution_pdf.php)

- MAO Activities
   - Create and manage activities (mao_activities.php, includes/mao_activities_modals.php)
   - View all activities (all_activities.php)
   - Activity status updates and filtering by activity_id via URL

- Analytics & Dashboards
   - Main dashboard with KPIs (index.php)
   - Analytics dashboard with charts (analytics_dashboard.php)
   - Chart data services (get_report_data.php, get_top_producers.php)

- Notifications
   - Aggregated notifications feed and badge counts (includes/notification_system.php, get_notifications.php, includes/notification_complete.php)
   - Categories: visitation reminders (7‑day), low/critical stock, MAO activities (today/tomorrow/next 14 days, overdue), expiring input batches (10‑day window)
   - Click-through navigation to filtered pages (nav.php wiring)

- Reports
   - Generate and save HTML reports (reports.php)
   - List and count saved reports (get_saved_reports.php, get_saved_reports_count.php)
   - Report count endpoints for dashboard widgets (get_report_count.php)

- Staff & Roles
   - Staff listing/management and actions (staff.php, staff_actions.php)
   - Role association and enforcement in-page (via includes/layout_start.php + session checks)

- Activity Logging & Utilities
   - System-wide activity logging (includes/activity_logger.php)
   - Recent activities widget and icons (includes/recent_activities.php, includes/activity_icons.php)
   - Helpers: visitation, yield, naming, PDF utilities (includes/visitation_helpers.php, includes/yield_helpers.php, includes/name_helpers.php, includes/pdf_utilities.php)

- UI & Layout
   - Shared layout, sidebar, navigation (includes/layout_start.php, includes/sidebar.php, nav.php)
   - Asset management (includes/assets.php; loads Bootstrap, Tailwind, jQuery, Chart.js, FontAwesome)

Notes:
- Database credentials and any sensitive configuration are intentionally excluded here. Configure those locally in `conn.php` without committing secrets.
