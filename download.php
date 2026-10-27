<?php
/** download.php - the ONLY way to get an uploaded file. Checks login and project permission. */
require_once __DIR__ . '/functions.php';
$user = require_login();

$st = db()->prepare('SELECT student_id, faculty_id, file_name, original_file_name FROM projects WHERE id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p || !can_view_project($user, $p)) {
    http_response_code(404);
    exit('File not found.');
}
// The stored name must look exactly like our random names -> blocks directory traversal
if (!preg_match('/^[a-f0-9]{32}\.(pdf|docx|zip)$/', $p['file_name'])) {
    http_response_code(404);
    exit('File not found.');
}
$path = realpath(UPLOAD_DIR . $p['file_name']);
$root = realpath(UPLOAD_DIR);
if ($path === false || $root === false || strncmp($path, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}
$ext = pathinfo($path, PATHINFO_EXTENSION);
$types = ['pdf' => 'application/pdf', 'zip' => 'application/zip',
          'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', $p['original_file_name']);

header('Content-Type: ' . $types[$ext]);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
