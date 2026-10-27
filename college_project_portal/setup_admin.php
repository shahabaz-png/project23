<?php
/**
 * setup_admin.php - ONE-TIME creation of the first administrator.
 * It refuses to run if an admin already exists or if uploads/setup.lock exists.
 * After use: delete this file (recommended).
 */
require_once __DIR__ . '/functions.php';

$lock = UPLOAD_DIR . 'setup.lock';
$adminExists = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
$pageTitle = 'Admin setup';

if ($adminExists || is_file($lock)) {
    http_response_code(403);
    require __DIR__ . '/includes/header.php';
    echo '<div class="auth-card"><h1>Setup already completed</h1><p class="muted">An administrator already exists, '
       . 'so this page is disabled. Please delete <code>setup_admin.php</code> from the project folder.</p>'
       . '<a class="btn btn-block" href="' . e(url('login.php')) . '">Go to login</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pw    = (string)($_POST['password'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must be 2-100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!valid_password($pw)) $errors[] = 'Password must be at least 8 characters with a letter and a number.';
    if ($pw !== (string)($_POST['confirm_password'] ?? '')) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        try {
            db()->prepare("INSERT INTO users (name, email, password_hash, role, department) VALUES (?, ?, ?, 'admin', 'Administration')")
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
            @file_put_contents($lock, 'Admin created on ' . date('c'));
            flash('success', 'Administrator created. Log in, then delete setup_admin.php.');
            redirect('login.php');
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Could not create the administrator (is the email already used?).';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Create Administrator</h1>
  <p class="muted">One-time setup. This page disables itself after the first admin is created.</p>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>Full name<input type="text" name="name" value="<?= old('name') ?>" required></label>
    <label>Email<input type="email" name="email" value="<?= old('email') ?>" required></label>
    <label>Password<input type="password" name="password" required minlength="8"></label>
    <label>Confirm password<input type="password" name="confirm_password" required></label>
    <button class="btn btn-block" type="submit">Create administrator</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
