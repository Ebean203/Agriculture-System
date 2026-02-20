


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

## Installation Guide

### Prerequisites

| Requirement | Version |
|---|---|
| XAMPP (or equivalent AMP stack) | 8.x recommended |
| PHP | 7.4 or higher (8.2+ recommended) |
| MySQL / MariaDB | 10.4 or higher |
| Web Browser | Chrome, Firefox, Edge (latest) |

> **Note:** The instructions below use XAMPP on Windows. Adjust paths accordingly for Linux/macOS or other LAMP stacks.

---

### Step 1 — Copy the Project Files

Place the entire project folder inside the XAMPP web root:

```
C:\xampp\htdocs\Agriculture-System\
```

---

### Step 2 — Create the Database

1. Open your browser and go to **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar.
3. Enter the database name: `agriculture-system`
4. Set collation to `utf8mb4_general_ci`, then click **Create**.
5. Select the newly created database, click the **Import** tab.
6. Click **Choose File** and select **`agriculture-system-deploy.sql`** from the project folder.
7. Click **Go** to import. All tables and default data will be created.

> **Alternatively**, import via command line:
> ```
> mysql -u root -p agriculture-system < agriculture-system-deploy.sql
> ```

---

### Step 3 — Configure the Database Connection

Open `conn.php` in the project root and update the credentials to match your environment:

```php
<?php
$host     = "localhost";
$db_user  = "root";        // Your MySQL username
$db_pass  = "";            // Your MySQL password (blank by default in XAMPP)
$db_name  = "agriculture-system";
$conn     = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
```

---

### Step 4 — Set Directory Permissions

Ensure the uploads directory is writable by the web server:

```
C:\xampp\htdocs\Agriculture-System\uploads\farmer_photos\
```

On Windows with XAMPP this is usually writable by default. On Linux/macOS, run:

```bash
chmod -R 775 uploads/farmer_photos/
```

---

### Step 5 — Start the Server

1. Open the **XAMPP Control Panel**.
2. Start **Apache** and **MySQL**.
3. Open your browser and navigate to:

```
http://localhost/Agriculture-System/login.php
```

---

### Step 6 — First Login

Use the default administrator credentials:

| Field    | Value              |
|----------|--------------------|
| Username | `admin`            |
| Password | `adminpassword321` |

> ⚠️ **Security Warning:** Change the default password immediately after your first login via the Staff Management page.

---

### Step 7 — Post-Installation Checklist

- [ ] Change the default admin password
- [ ] Add staff accounts for each MAO user
- [ ] Verify all barangays are correctly listed (editable via the database if needed)
- [ ] Add commodity types specific to the municipality
- [ ] Add agricultural input types and initial stock
- [ ] Register farmers and assign commodities

---

## Database Files Reference

| File | Purpose |
|------|---------|
| `agriculture-system-deploy.sql` | **Use this for deployment.** Clean schema with no sample data and the default admin account. |
| `agriculture-system.sql` | Development/backup dump with sample records. Do **not** use for client deployment. |

---

## Security & Data Handling

- All main pages require login (session enforced)
- Passwords are securely hashed using PHP `password_hash()` (bcrypt)
- Database operations use prepared statements to prevent SQL injection
- File uploads are validated for type (JPEG/PNG) and size (max 5 MB)
- Session timeout is enforced on inactivity

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page or PHP errors | Enable error reporting or check Apache error log in XAMPP |
| Cannot connect to database | Verify credentials in `conn.php` and ensure MySQL is running |
| Photos not uploading | Check that `uploads/farmer_photos/` exists and is writable |
| Login fails with correct credentials | Re-import `agriculture-system-deploy.sql` to reset the admin account |
| Port conflict (80/3306) | Change Apache/MySQL ports in XAMPP Config or stop conflicting services |

---

## Contact

For support and access, contact the system administrator.
