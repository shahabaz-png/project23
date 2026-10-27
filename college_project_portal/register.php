<?php
/** register.php - public registration. Always creates a STUDENT account. */
require_once __DIR__ . '/functions.php';
if (current_user()) {
    redirect('dashboard.php');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim($_POST['name'] ?? '');
    $email   = strtolower(trim($_POST['email'] ?? ''));
    $number  = trim($_POST['student_number'] ?? '');
    $dept    = trim($_POST['department'] ?? '');
    $pw      = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must be 2-100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Enter a valid email address.';
    if ($number === '' || strlen($number) > 30 || !preg_match('/^[A-Za-z0-9\/-]+$/', $number)) $errors[] = 'Enter a valid student/roll number (letters, digits, - or /).';
    if ($dept === '' || mb_strlen($dept) > 100) $errors[] = 'Department is required (max 100 characters).';
    if (!valid_password($pw)) $errors[] = 'Password must be at least 8 characters and contain a letter and a number.';
    if ($pw !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $st = db()->prepare('SELECT email, student_number FROM users WHERE email = ? OR student_number = ?');
        $st->execute([$email, $number]);
        foreach ($st->fetchAll() as $dup) {
            if ($dup['email'] === $email) $errors[] = 'This email is already registered.';
            if ($dup['student_number'] === $number) $errors[] = 'This student number is already registered.';
        }
        $errors = array_unique($errors);
    }
    if (!$errors) {
        try {
            // role is fixed to 'student' here - it is never read from the form
            db()->prepare("INSERT INTO users (name, email, password_hash, role, student_number, department)
                           VALUES (?, ?, ?, 'student', ?, ?)")
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $number, $dept]);
            flash('success', 'Registration successful. You can now log in.');
            redirect('login.php');
        } catch (PDOException $ex) {
            $errors[] = ($ex->getCode() === '23000') ? 'This email or student number is already registered.' : 'Could not create the account. Please try again.';
            error_log($ex->getMessage());
        }
    }
}
$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Student Registration</h1>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>Full name<input type="text" name="name" value="<?= old('name') ?>" required maxlength="100"></label>
    <label>Email<input type="email" name="email" value="<?= old('email') ?>" required maxlength="150"></label>
    <div class="grid-2">
      <label>Student / roll number<input type="text" name="student_number" value="<?= old('student_number') ?>" required maxlength="30"></label>
      <label>Department<input type="text" name="department" value="<?= old('department', 'BCA') ?>" required maxlength="100"></label>
    </div>
    <div class="grid-2">
      <label>Password<input type="password" name="password" required minlength="8"></label>
      <label>Confirm password<input type="password" name="confirm_password" required></label>
    </div>
    <p class="muted small">At least 8 characters with a letter and a number.</p>
    <button class="btn btn-block" type="submit">Create account</button>
  </form>
  <p class="center muted">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
