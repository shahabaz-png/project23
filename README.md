# College Project Management Portal

A plain **PHP 8 + MySQL + HTML/CSS/JS** web application where students submit projects, faculty review them, and administrators manage users and assignments. No frameworks.

## 1. Architecture (short)
- **Pages** (`login.php`, `dashboard.php`, ...) handle one screen each. Every page starts with `require_once 'functions.php'`.
- **functions.php** loads `config.php`, `db.php`, `includes/csrf.php`, `includes/auth.php` and holds helpers (escaping, flash messages, uploads, pagination).
- **Security**: PDO prepared statements, `password_hash/verify`, session ID regeneration at login, CSRF token on every POST form, `htmlspecialchars` on all output, role checks on the server, protected uploads.
- **Workflow**: `Student selects faculty -> Pending -> faculty: Approved | Rejected | Revision Required -> student edits -> Pending`. Every change is a row in `project_reviews` (never overwritten).

## 2. Folder structure and file guide
```
college_project_portal/
├── index.php            redirects to login or dashboard
├── config.php           DB credentials, constants, session cookie settings
├── db.php               PDO connection (shows a friendly error if MySQL is down)
├── functions.php        helpers: e(), flash(), process_upload(), pagination ...
├── login.php / register.php / logout.php
├── dashboard.php        role-specific dashboard (student / faculty / admin)
├── projects.php         project list: search, filters, pagination (role-restricted)
├── project_form.php     student: submit new project / edit + resubmit after revision
├── project_details.php  details, review history, faculty review, admin assign/delete
├── users.php            admin: create faculty, search/filter users, activate/deactivate
├── profile.php          profile info + change password
├── download.php         authenticated file download (the only way to get uploads)
├── setup_admin.php      ONE-TIME admin creation (locks itself)
├── database.sql         creates database + 3 tables
├── includes/            header.php, footer.php, auth.php, csrf.php
├── assets/css/style.css, assets/js/app.js, assets/images/
└── uploads/             private files; .htaccess denies all direct access
```

## 3. XAMPP setup (Windows)
1. Install XAMPP (PHP 8.0 or newer) from apachefriends.org.
2. Open the XAMPP Control Panel and **Start Apache** and **MySQL**.
3. Create the folder `C:\xampp\htdocs\college_project_portal` and copy all project files into it (keep sub-folders).
4. Open `http://localhost/phpmyadmin`.
5. Click **Import**, choose `database.sql`, click **Import/Go**. Database `college_project_portal` appears.
6. Open `config.php`. XAMPP defaults are user `root` and a **blank password**; change `DB_USER`/`DB_PASS` if yours differ. If you use another folder name, change `BASE_URL`.
7. Open `http://localhost/college_project_portal/setup_admin.php` and create the administrator.
8. The setup page disables itself after use (admin exists + `uploads/setup.lock`). **Delete `setup_admin.php`** anyway.
9. Open `http://localhost/college_project_portal/` and log in as admin.
10. Go to **Users** and create faculty accounts (a temporary password is shown once - note it down).
11. Register a student at `register.php`, log in, and submit a project.
12. As admin open the project and **assign** a faculty member; log in as faculty and review it.

> Never expose a default XAMPP installation to the public internet (blank MySQL password, no hardening). Use it locally only.

### Upload size
`php.ini` (XAMPP: Config > PHP) defaults are usually fine for 5 MB (`upload_max_filesize=40M`, `post_max_size=40M`). The app enforces 5 MB itself (`MAX_UPLOAD_BYTES` in `config.php`). Make sure `extension=fileinfo` is enabled (it is by default).

### Production notes (not needed for local demo)
- Use HTTPS; the session cookie automatically gets the `Secure` flag when the request is HTTPS. Add `session.cookie_secure=1` in php.ini.
- Set `APP_DEBUG` to `false`, use a dedicated MySQL user with a strong password (not `root`), delete `setup_admin.php`.
- Ideally move `uploads/` outside the web root and change `UPLOAD_DIR` in `config.php`.

## 4. Test checklist
| Area | Test | Expected |
|---|---|---|
| Registration | Register with valid data | Redirect to login with success message |
| Duplicate | Register same email / student number again | Error message, no new row |
| Validation | Empty fields, short password, mismatched passwords | Clear error messages |
| Login | Wrong password / unknown email | "Invalid email or password." |
| Login | Correct credentials | Dashboard; session ID changes |
| Logout | Click Logout, press browser Back | Redirected to login |
| Unauthorized | Logged out, open `dashboard.php` | Redirect to login |
| Roles | Student opens `users.php` | 403 page |
| Roles | Student edits another student's `project_details.php?id=` | "Project not found" |
| Setup | Open `setup_admin.php` after admin exists | 403 "Setup already completed" |
| Submission | Submit project with PDF | Status Pending, appears in lists, history has "submitted" entry |
| Upload | Upload `.php`, `.exe`, renamed `.txt` as `.pdf`, file > 5 MB | Rejected with message |
| Assignment | Admin assigns faculty | Faculty sees project in Assigned Projects |
| Review | Faculty approves / rejects / requests revision with feedback | Status changes, history row added |
| Review | Faculty without feedback; faculty not assigned | Rejected by server |
| Revision | Student edits and resubmits | Status Pending again, history shows Revision Required -> Pending |
| History | Open details of reviewed project | Timeline with reviewer, statuses, feedback, time |
| Search | Search by title, student name; filter category/status | Correct results only |
| Pagination | Create >10 projects | Pages work, filters preserved |
| Download | Student A opens `download.php?id=` of student B's project | "File not found" |
| Direct access | Open `/uploads/somefile.pdf` in browser | 403 Forbidden |
| Admin | Deactivate faculty, then try to log in | "Account is deactivated" |
| Admin | Deactivate last admin / yourself | Blocked |
| Mobile | Browser width < 860 px | Hamburger menu, stacked layout, tables scroll |
| DB failure | Stop MySQL, open site | Friendly error page, no credentials shown |

## 5. Troubleshooting
- **Apache won't start**: port 80 used (Skype/IIS). Change Apache port in `httpd.conf` and use `http://localhost:PORT/...`.
- **MySQL won't start**: port 3306 in use or leftover process; stop other MySQL services.
- **"Database connection failed"**: MySQL not running, `database.sql` not imported, or wrong credentials in `config.php`.
- **404 / links go to the wrong place**: folder name differs from `college_project_portal`; update `BASE_URL`.
- **Upload fails**: check `upload_max_filesize` / `post_max_size`, `extension=fileinfo`, and write permission on `uploads/`. Restart Apache after editing php.ini.
- **`Class "finfo" not found`**: enable `extension=fileinfo` in php.ini.
- **Blank page**: set `APP_DEBUG` to `true` temporarily, or read `C:\xampp\apache\logs\error.log`.
- **Uploads folder not blocked**: make sure Apache `AllowOverride All` is set for htdocs (XAMPP default) so `uploads/.htaccess` works.

## 6. Demonstration steps
1. Create admin (`setup_admin.php`), log in, show dashboard.
2. Create faculty account on Users page.
3. Register a student; submit a project with a PDF.
4. Show student dashboard (Pending). Log in as admin -> assign faculty.
5. Log in as faculty -> request revision with feedback.
6. Student sees feedback -> edit and resubmit.
7. Faculty approves. Show review history timeline on the project page.
8. Show security: student trying admin pages (403), direct upload URL (403).
