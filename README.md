# CHRAS — Community Health Reporting & Alert System
### Kiambu County | PHP + MySQL + Three.js

---

## 📁 Project Structure

```
chras/
├── index.php           ← Login / Register page
├── report.php          ← Resident: submit a report
├── my_reports.php      ← Resident: view own reports
├── dashboard.php       ← Officer/Admin: live dashboard
├── reports.php         ← Officer/Admin: all reports + manage
├── alerts.php          ← Officer/Admin: send email alerts
├── users.php           ← Admin: manage user accounts
├── logs.php            ← Admin: system activity logs
├── admin.php           ← Admin: control panel + settings
├── logout.php          ← Session destroyer
├── install.sql         ← Run ONCE to create the database
├── .htaccess           ← Apache security rules
├── includes/
│   ├── config.php      ← DB credentials, email, constants
│   ├── header.php      ← Top nav + Three.js canvas
│   └── footer.php      ← Toast + background.js loader
├── css/
│   └── style.css       ← Full dark-tech stylesheet
└── js/
    └── background.js   ← Three.js animated background
```

---

## ⚙️ Setup Instructions

### Step 1 — Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.4+
- Apache with mod_rewrite enabled
- XAMPP (local) or cPanel/VPS (live)

### Step 2 — Create the Database
```bash
mysql -u root -p < install.sql
```
Or paste `install.sql` into phpMyAdmin → SQL tab → Run.

### Step 3 — Configure the App
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'chras_db');
define('ADMIN_EMAIL', 'darwinanker27@gmail.com');
```

### Step 4 — Place files on server
- **XAMPP local:** copy `chras/` folder to `htdocs/chras/`
- **Live server:** upload via FTP to `public_html/chras/`
- Visit: `http://localhost/chras/` (local) or `https://yourdomain.com/chras/`

### Step 5 — First Login
| Role    | Email                        | Password   |
|---------|------------------------------|------------|
| Admin   | darwinanker27@gmail.com      | `password` |
| Officer | officer@chras.go.ke          | `password` |

**⚠ Change these passwords immediately after first login (Admin → Admin Panel → Change Password)**

---

## 📧 Email Setup

### On a Live Server (cPanel/VPS)
PHP `mail()` works automatically — no extra config needed.

### On XAMPP / Local Development
PHP `mail()` does NOT work on localhost by default.
Use **PHPMailer + SendGrid** or **Mailtrap** for testing:

1. Install PHPMailer:
```bash
composer require phpmailer/phpmailer
```

2. Replace `sendMail()` in `includes/config.php` with:
```php
use PHPMailer\PHPMailer\PHPMailer;
require 'vendor/autoload.php';

function sendMail(string $to, string $subject, string $body): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.sendgrid.net';   // or smtp.mailtrap.io
        $mail->SMTPAuth   = true;
        $mail->Username   = 'apikey';              // SendGrid
        $mail->Password   = 'YOUR_SENDGRID_API_KEY';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->setFrom(SYSTEM_FROM, SYSTEM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: " . $mail->ErrorInfo);
        return false;
    }
}
```

### Free Email Options
| Service    | Free Tier       | Notes                    |
|------------|-----------------|--------------------------|
| SendGrid   | 100 emails/day  | Best for production      |
| Mailtrap   | Dev inbox only  | Best for local testing   |
| Gmail SMTP | 500 emails/day  | Needs App Password       |

---

## 🔐 Security Notes
- All passwords are hashed with `password_hash()` (bcrypt)
- All user input is sanitised with `htmlspecialchars` + `strip_tags`
- Role-based access control on every page
- SQL prepared statements prevent SQL injection
- `.htaccess` blocks direct access to SQL and include files

---

## 🚨 Auto-Alert Logic
In `includes/config.php`:
```php
define('ALERT_COUNT',   5);   // Minimum reports to trigger auto-alert
define('ALERT_MINUTES', 6);   // Within this many minutes
```
If 5+ reports come from the same location within 6 minutes,
the system automatically emails the admin with a cluster alert.

---

## 👥 User Roles
| Role      | Can Do                                              |
|-----------|-----------------------------------------------------|
| Resident  | Submit reports, view own reports + feedback         |
| Officer   | View all reports, update status, send alerts        |
| Admin     | Everything above + manage users, view logs, settings|

---

## 🛠 Common Problems & Fixes

| Problem                          | Fix                                                  |
|----------------------------------|------------------------------------------------------|
| Blank white page                 | Enable PHP errors: `ini_set('display_errors', 1);`   |
| DB connection failed             | Check DB_USER/DB_PASS in config.php                  |
| Emails not sending               | Use PHPMailer + SMTP (see Email Setup above)         |
| Permission denied on .htaccess   | Enable `AllowOverride All` in Apache httpd.conf      |
| Session not persisting           | Check PHP session.save_path is writable              |
| 404 on subpages                  | Enable mod_rewrite in Apache                         |

---

Built for: JKUAT Diploma in Information Technology — 2025
Authors: Lucy Njoki Kara & Alex Nyaga Kariuki
Supervisor: Prof. Grace Mugambi
