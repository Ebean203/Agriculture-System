


# Lagonglong FARMS (Agriculture-System)
## Tech Stack

- PHP 7.4+ (mysqli)
- MySQL/MariaDB
- Bootstrap 5
- jQuery
- Font Awesome


Lagonglong FARMS is a web-based management system for the Lagonglong Municipal Agriculture Office (MAO). It is designed to help MAO staff efficiently manage agricultural operations, including farmer registration, yield monitoring, inventory and input distribution, reporting, and analytics. The system is built with PHP (7.4+), MySQL/MariaDB, Bootstrap 5, jQuery, and Font Awesome.

## System Modules & Features

### 1. Authentication & User Management
- Secure staff login with session management
- Role-based access (admin, staff, etc.)
- Staff and roles management (add, edit, list staff)
- Session timeout and logout

### 2. Farmer Registry
- Register, view, and edit farmer records
- Upload and manage farmer photos (JPEG/PNG, max 5MB)
- Search and auto-suggest by name/contact
- Program flags and registries: RSBSA, NCFRS, FishR
- Boat registration records

### 3. Yield Monitoring
- Record and view yield data per farmer
- Analytics dashboard with yield charts and statistics

### 4. Inventory Management
- Add and manage agricultural input items
- View and adjust inventory stock
- Track expiring inputs and stock-out batches

### 5. Input Distribution & Visitation
- Distribute inputs to farmers with visitation scheduling
- Track and filter distribution history
- Mark visitation as completed or reschedule
- Export distribution records as HTML (for printing/PDF)

### 6. Activities & Notifications
- Create and manage MAO activities
- View all activities and update status
- Receive notifications for:
	- Upcoming/overdue activities
	- Visitation reminders (7-day window)
	- Low/critical inventory stock
	- Expiring input batches

### 7. Reports
- Generate and save HTML reports (date range, type)
- List and view saved reports
- Export farmer and distribution data as HTML

### 8. Logging & Auditing
- System-wide activity logging (who did what, when)
- Recent activities widget on dashboard

### 9. Dashboard & Analytics
- Main dashboard with KPIs (farmers, inventory, yields, activities)
- Analytics dashboard with interactive charts

## File & Directory Structure

- PHP scripts for each module are at the project root (e.g., `farmers.php`, `mao_inventory.php`)
- Shared UI and logic in `includes/` (e.g., `layout_start.php`, `sidebar.php`, `activity_logger.php`)
- Static assets (CSS, JS, fonts) in `assets/`
- Uploaded farmer photos in `uploads/farmer_photos/`
- Database schema in `agriculture-system.sql`

## Basic Setup (Windows + XAMPP)

1. Place the project folder in your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\Agriculture-System`).
2. Import `agriculture-system.sql` into your MySQL/MariaDB server to create the required tables.
3. Copy `conn.example.php` to `conn.php` and set your local database credentials (do not commit `conn.php`).
4. Ensure the `uploads/farmer_photos/` directory is writable by PHP.
5. Start Apache and MySQL in the XAMPP Control Panel.
6. Access the system at `http://localhost/Agriculture-System/login.php`.

## Security & Data Handling

- All main pages require login (session enforced)
- Passwords are securely hashed
- Database operations use prepared statements for security
- File uploads are validated for type and size
- No real database credentials are included in the repository

## Contact

For support and access, contact the system administrator.
