<?php
require_once __DIR__ . '/functions.php';
if (current_user()) {
    redirect('dashboard.php');
}
$errors = [];
$email = '';
$loginType = (($_POST['login_type'] ?? $_GET['type'] ?? 'student') === 'teacher') ? 'teacher' : 'student';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $errors[] = 'Please enter your email and password.';
    } else {
        $st = db()->prepare('SELECT id, password_hash, account_status, role FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        // Same message for unknown email and wrong password (does not reveal which emails exist)
        $hash = $row['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $ok = password_verify($password, $hash) && $row;
        if (!$ok) {
            $errors[] = 'Invalid email or password.';
        } elseif ($row['account_status'] !== 'active') {
            $errors[] = ($loginType === 'teacher' && ($row['role'] ?? '') === 'faculty')
                ? 'Your teacher account is awaiting administrator approval. Please contact the administrator.'
                : 'Your account is inactive. Please contact the administrator.';
        } elseif ($loginType === 'teacher' && !in_array($row['role'], ['faculty', 'admin'], true)) {
            $errors[] = 'This account is not authorized for teacher login. Please contact the administrator.';
        } elseif ($loginType === 'student' && !in_array($row['role'], ['student', 'faculty', 'admin'], true)) {
            $errors[] = 'This account cannot log in.';
        } else {
            session_regenerate_id(true);           // prevents session fixation
            $_SESSION['user_id'] = (int)$row['id'];
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            redirect($loginType === 'teacher' ? 'teacher_sheet.php' : 'dashboard.php');
        }
    }
}
$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>&#127891; Project Portal</h1>
  <p class="muted">Log in to manage your academic projects.</p>
  <div class="actions" style="display:flex;gap:10px;margin:18px 0">
    <a class="btn btn-block" style="text-align:center;<?= $loginType === 'student' ? '' : 'background:var(--border);color:var(--text)' ?>" href="<?= e(url('login.php?type=student')) ?>">Student Login</a>
    <a class="btn btn-block" style="text-align:center;<?= $loginType === 'teacher' ? '' : 'background:var(--border);color:var(--text)' ?>" href="<?= e(url('login.php?type=teacher')) ?>">Teacher Login</a>
  </div>
  <?php if ($loginType === 'teacher'): ?><p class="muted small">Authorized faculty and administrators only.</p><?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="login_type" value="<?= e($loginType) ?>">
    <label>Email<input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn btn-block" type="submit">Log in</button>
  </form>
  <?php if ($loginType === 'student'): ?><p class="center muted">New student? <a href="<?= e(url('register.php')) ?>">Create an account</a></p><?php else: ?><p class="center muted">Need a teacher account? <a href="<?= e(url('teacher_register.php')) ?>">Request teacher access</a></p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
