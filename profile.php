<?php
require_once __DIR__ . '/functions.php';
$user = require_login();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$user['id']]);
    if (!password_verify($current, (string)$st->fetchColumn())) $errors[] = 'Current password is incorrect.';
    if (!valid_password($new)) $errors[] = 'New password must be at least 8 characters with a letter and a number.';
    if ($new !== (string)($_POST['confirm_password'] ?? '')) $errors[] = 'New passwords do not match.';
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        flash('success', 'Password updated successfully.');
        redirect('profile.php');
    }
}
$pageTitle = 'Profile';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">My Profile</h1>
<div class="grid-2">
  <div class="card">
    <h2>Account information</h2>
    <dl class="details">
      <dt>Name</dt><dd><?= e($user['name']) ?></dd>
      <dt>Email</dt><dd><?= e($user['email']) ?></dd>
      <dt>Role</dt><dd><?= e(ucfirst($user['role'])) ?></dd>
      <?php if ($user['student_number']): ?><dt>Student number</dt><dd><?= e($user['student_number']) ?></dd><?php endif; ?>
      <dt>Department</dt><dd><?= e($user['department'] ?? '-') ?></dd>
      <dt>Member since</dt><dd><?= e(fmt_date($user['created_at'])) ?></dd>
    </dl>
  </div>
  <div class="card">
    <h2>Change password</h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <label>Current password<input type="password" name="current_password" required></label>
      <label>New password<input type="password" name="new_password" required minlength="8"></label>
      <label>Confirm new password<input type="password" name="confirm_password" required></label>
      <button class="btn" type="submit">Update password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
