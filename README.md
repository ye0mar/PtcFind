# FoundU - Lost & Found Management System

A web-based Lost & Found Management System built with **PHP 8+**, **MySQL (PDO)**, **HTML5**, **CSS3**, **Vanilla JavaScript**, and **Bootstrap 5**. Designed specifically for campus and institution administration to catalog lost belongings, register found items in custody, match records, and track items until return.

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.0+ with PHP PDO (`mysql:host=localhost`)
- **Database**: MySQL 5.7+ / 8.0+ or MariaDB (InnoDB engine, utf8mb4)
- **Frontend**: HTML5, CSS3, Vanilla JavaScript (ES6+), Bootstrap 5.3, Font Awesome 6
- **Architecture**: Modular PHP (reusable headers, footers, sidebars, CSRF tokens, flash sessions)
- **Web Server**: Apache / XAMPP / LAMP / WAMP

---

## 🚀 XAMPP Installation & Setup Guide

### 1. Place the Project in XAMPP
Copy or move the project folder into your XAMPP web root:
- **Windows**: `C:\xampp\htdocs\foundu`
- **macOS**: `/Applications/XAMPP/htdocs/foundu`
- **Linux**: `/opt/lampp/htdocs/foundu`

### 2. Start Services
Open the **XAMPP Control Panel** and click **Start** for both:
1. **Apache**
2. **MySQL**

### 3. Import Database
1. Open your web browser and go to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click **New** in the left sidebar and create a database named:
   ```sql
   lost_and_found_db1
   ```
   (Collation: `utf8mb4_unicode_ci`)
3. Click on `lost_and_found_db1`, then click on the **Import** tab at the top.
4. Click **Choose File** and select:
   ```
   database/lost_and_found_db.sql
   ```
5. Click **Import** at the bottom of the page.

### 4. Database Configuration (Optional)
If your MySQL has a password or custom port, edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'lost_and_found_db1');
define('DB_USER', 'root');
define('DB_PASS', ''); // default in XAMPP is empty
```

### 5. Open Application
Navigate to your local project URL in your browser:
```
http://localhost/foundu/
```

---

## 🔑 Default Credentials

| Role | Username | Password | Privileges |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Full access (Users, Audit Logs, Settings, Backup, Items) |
| **Staff Operator** | `staff` | `staff123` | Log lost/found items, manage matches, view inventory |

*(You can change your password anytime under **My Profile** or seed a new admin at `setup/create_admin.php`)*

---

## 📁 Project Structure

```
foundu/
├── config/
│   └── database.php             # PDO database connection & error diagnostics
├── database/
│   └── lost_and_found_db.sql    # Complete SQL schema & initial seed data
├── includes/
│   ├── auth.php                 # PHP session authentication & role checks
│   ├── csrf.php                 # Cryptographic CSRF token generation & validation
│   ├── flash_messages.php       # Flash notification alerts
│   ├── functions.php            # Sanitization, activity logging, image upload
│   ├── header.php               # HTML5 head, Bootstrap 5 CDN, CSS imports
│   ├── navbar.php               # Top navigation bar & user menu
│   ├── sidebar.php              # Responsive sidebar navigation
│   └── footer.php               # Footer, Bootstrap JS bundle & vanilla JS
├── lost_items/
│   ├── index.php                # Directory with search, category & status filters
│   ├── report.php               # Report lost item form with image upload
│   ├── view.php                 # View lost item details & match suggestions
│   ├── edit.php                 # Edit lost item details & status
│   └── process.php              # CRUD backend processor (PDO prepared statements)
├── found_items/
│   ├── index.php                # Inventory list with storage locker filters
│   ├── add.php                  # Record found item form with storage location
│   ├── view.php                 # View found item details & storage info
│   ├── edit.php                 # Edit storage locker and custody notes
│   └── process.php              # Found item CRUD backend processor
├── matches/
│   ├── index.php                # Manage linked matches & claim handovers
│   └── process.php              # Link lost item <-> found item, return release
├── admin/
│   ├── manage_users.php         # Manage operator accounts (admin/staff)
│   ├── activity_log.php         # Immutable audit trail of system activities
│   ├── backup_recovery.php      # Live SQL dump export & backup logs
│   ├── system_monitor.php       # Server diagnostics (PHP, MySQL, disk, uploads)
│   └── settings.php             # Global configuration (system name, pagination)
├── profile/
│   └── index.php                # Operator profile management & password change
├── setup/
│   └── create_admin.php         # Standalone administrator seeding utility
├── assets/
│   ├── css/
│   │   ├── style.css            # Custom CSS3 enhancements
│   │   ├── auth.css             # Login page styling
│   │   ├── dashboard.css        # KPI cards & analytics
│   │   ├── forms.css            # Form validation styles
│   │   └── responsive.css       # Mobile & tablet layout adjustments
│   └── js/
│       └── main.js              # Vanilla JavaScript (validation, shortcuts)
├── uploads/
│   └── .htaccess                # Security policy preventing script execution
├── .htaccess                    # Apache web server configuration
├── index.php                    # Root entry point router
├── login.php                    # Secure authentication login portal
├── logout.php                   # Session teardown handler
└── dashboard.php                # Main dashboard with real-time KPI metrics
```

---

## 🔒 Security Features Implemented

- **Prepared Statements (PDO)**: Complete protection against SQL injection attacks across all endpoints.
- **CSRF Protection**: All POST operations require valid cryptographic tokens verified via `hash_equals()`.
- **Session Security**: Session regeneration on authentication to prevent session fixation.
- **Password Hashing**: `password_hash()` with `PASSWORD_DEFAULT` (Bcrypt) and `password_verify()`.
- **XSS Mitigation**: Strict sanitization and `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` on all dynamic outputs.
- **Upload Directory Hardening**: `.htaccess` denies execution of `.php`, `.cgi`, `.sh` files inside `uploads/`.