<?php
/** Teacher signup requests are inactive until an administrator authorizes them. */
require_once __DIR__ . '/functions.php';
if (current_user()) redirect('dashboard.php');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $dept = trim($_POST['department'] ?? '');
    $pw = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must be 2-100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Enter a valid email address.';
    if ($dept === '' || mb_strlen($dept) > 100) $errors[] = 'Department is required (max 100 characters).';
    if (!valid_password($pw)) $errors[] = 'Password must be at least 8 characters and contain a letter and a number.';
    if ($pw !== $confirm) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) $errors[] = 'This email is already registered.';
    }
    if (!$errors) {
        try {
            db()->prepare("INSERT INTO users (name,email,password_hash,role,department,account_status) VALUES (?,?,?,'faculty',?,'inactive')")
                ->execute([$name,$email,password_hash($pw,PASSWORD_DEFAULT),$dept]);
            flash('success', 'Teacher account request submitted. An administrator must activate it before you can log in.');
            redirect('login.php?type=teacher');
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            $errors[] = 'Could not submit the request. This email may already be registered.';
        }
    }
}
$pageTitle = 'Teacher Account Request';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Teacher Account</h1>
  <p class="muted">Request teacher access to review student project submissions.</p>
  <div class="alert">For security, your account will remain pending until an administrator authorizes it.</div>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>Full name<input type="text" name="name" value="<?= old('name') ?>" required maxlength="100"></label>
    <label>Official email<input type="email" name="email" value="<?= old('email') ?>" required maxlength="150"></label>
    <label>Department<input type="text" name="department" value="<?= old('department', 'BCA') ?>" required maxlength="100"></label>
    <div class="grid-2">
      <label>Password<input type="password" name="password" required minlength="8"></label>
      <label>Confirm password<input type="password" name="confirm_password" required></label>
    </div>
    <p class="muted small">Use at least 8 characters, including a letter and a number.</p>
    <button class="btn btn-block" type="submit">Request Teacher Account</button>
  </form>
  <p class="center muted">Already have an authorized account? <a href="<?= e(url('login.php?type=teacher')) ?>">Teacher Login</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
