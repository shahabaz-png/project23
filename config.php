<?php
/**
 * config.php - central settings. Change the database values to match your XAMPP.
 * XAMPP default: user "root" with a blank password.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'college_project_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

// URL path of the project (no trailing slash). Use '' if the site is in the web root.
define('BASE_URL', '/college_project_portal');

define('UPLOAD_DIR', __DIR__ . '/uploads/');   // protected by uploads/.htaccess
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);   // 5 MB
define('PER_PAGE', 10);
define('APP_DEBUG', false);                    // keep false; true only while debugging

const CATEGORIES = ['Web Development', 'Artificial Intelligence', 'Data Analytics', 'Cloud Computing',
    'Cybersecurity', 'Mobile Application Development', 'Database Management', 'Other'];
const STATUSES = ['Pending', 'Approved', 'Rejected', 'Revision Required'];

// Allowed extensions => allowed MIME types (checked on the server with finfo)
const ALLOWED_TYPES = [
    'pdf'  => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'zip'  => ['application/zip', 'application/x-zip-compressed'],
];

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_name('CPMPSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // Cookie is sent only over HTTPS when the site runs on HTTPS (see README for production)
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}
