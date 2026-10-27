<?php
/** functions.php - loads everything and provides small reusable helpers. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

/** Escape output (prevents XSS). Use for every value printed in HTML. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Remember a value typed in a form so it can be shown again after an error. */
function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function fmt_date(?string $date): string
{
    return $date ? date('d M Y, h:i A', strtotime($date)) : '-';
}

function fmt_size(int $bytes): string
{
    return $bytes >= 1048576 ? round($bytes / 1048576, 2) . ' MB' : max(1, round($bytes / 1024)) . ' KB';
}

function status_badge(string $status): string
{
    $class = strtolower(str_replace(' ', '-', $status));
    return '<span class="badge badge-' . e($class) . '">' . e($status) . '</span>';
}

function valid_password(string $pw): bool
{
    return strlen($pw) >= 8 && preg_match('/[A-Za-z]/', $pw) && preg_match('/\d/', $pw);
}

/** Validates an optional http/https URL. Returns an error message or null. */
function check_url(string $value, string $label): ?string
{
    if ($value === '') {
        return null;
    }
    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
    if (strlen($value) > 255 || !filter_var($value, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
        return "$label must be a valid http/https URL (max 255 characters).";
    }
    return null;
}

/** Random temporary password without confusing characters. */
function generate_password(int $length = 12): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out . random_int(2, 9) . 'a'; // guarantees a letter and a digit
}

/** Can this user see this project row? (student owner, assigned faculty, or admin) */
function can_view_project(array $user, array $project): bool
{
    return match ($user['role']) {
        'admin'   => true,
        'faculty' => (int)$project['faculty_id'] === (int)$user['id'],
        'student' => (int)$project['student_id'] === (int)$user['id'],
        default   => false,
    };
}

/** Validate and store an uploaded file. Returns file info or null (and sets $error). */
function process_upload(array $f, ?string &$error): ?array
{
    $error = null;
    if (!isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please choose a file to upload.';
        return null;
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'The file is too large (maximum ' . fmt_size(MAX_UPLOAD_BYTES) . ').';
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $error = 'The upload failed (error code ' . (int)$f['error'] . '). Please try again.';
        return null;
    }
    if ($f['size'] <= 0 || $f['size'] > MAX_UPLOAD_BYTES) {
        $error = 'The file must be between 1 byte and ' . fmt_size(MAX_UPLOAD_BYTES) . '.';
        return null;
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        $error = 'Invalid upload.';
        return null;
    }
    $original = basename(str_replace("\0", '', (string)$f['name']));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    // Block dangerous names such as shell.php.pdf
    if (preg_match('/\.(php\d?|phtml|phar|exe|bat|cmd|sh|js|jar|msi|com|scr|htaccess)(\.|$)/i', $original)) {
        $error = 'This file type is not allowed.';
        return null;
    }
    if (!isset(ALLOWED_TYPES[$ext])) {
        $error = 'Only PDF, DOCX and ZIP files are allowed.';
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES[$ext], true)) {
        $error = 'The file content does not match its extension.';
        return null;
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        $error = 'Upload folder is missing and could not be created.';
        return null;
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;   // never use the user's filename on disk
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $stored)) {
        $error = 'Could not save the file. Check the uploads folder permissions.';
        return null;
    }
    $clean = preg_replace('/[^A-Za-z0-9._ -]/', '_', $original);
    return ['stored' => $stored, 'original' => substr($clean, 0, 150), 'size' => (int)$f['size']];
}

function delete_stored_file(?string $storedName): void
{
    if ($storedName && preg_match('/^[a-f0-9]{32}\.(pdf|docx|zip)$/', $storedName)) {
        $path = UPLOAD_DIR . $storedName;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

function get_page(): int
{
    return max(1, (int)($_GET['page'] ?? 1));
}

/** Pagination links that keep the current search/filter parameters. */
function render_pagination(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination">';
    $link = function (int $p, string $label, bool $active = false, bool $disabled = false): string {
        if ($disabled) {
            return '<span class="disabled">' . $label . '</span>';
        }
        $qs = http_build_query(array_merge($_GET, ['page' => $p]));
        return '<a class="' . ($active ? 'active' : '') . '" href="?' . e($qs) . '">' . $label . '</a>';
    };
    $html .= $link($page - 1, '&laquo; Prev', false, $page <= 1);
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
        $html .= $link($p, (string)$p, $p === $page);
    }
    $html .= $link($page + 1, 'Next &raquo;', false, $page >= $pages);
    return $html . '</nav>';
}
