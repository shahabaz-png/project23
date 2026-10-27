<?php
require_once __DIR__ . '/functions.php';
if (current_user()) {
    redirect('dashboard.php');
}
$errors = [];
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $errors[] = 'Please enter your email and password.';
    } else {
        $st = db()->prepare('SELECT id, password_hash, account_status FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        // Same message for unknown email and wrong password (does not reveal which emails exist)
        $hash = $row['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $ok = password_verify($password, $hash) && $row;
        if (!$ok) {
            $errors[] = 'Invalid email or password.';
        } elseif ($row['account_status'] !== 'active') {
            $errors[] = 'Your account is deactivated. Please contact the administrator.';
        } else {
            session_regenerate_id(true);           // prevents session fixation
            $_SESSION['user_id'] = (int)$row['id'];
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            redirect('dashboard.php');
        }
    }
}
$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>&#127891; Project Portal</h1>
  <p class="muted">Log in to manage your academic projects.</p>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn btn-block" type="submit">Log in</button>
  </form>
  <p class="center muted">New student? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
