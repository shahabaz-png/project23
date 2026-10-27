<?php
/** users.php - administrator user management. */
require_once __DIR__ . '/functions.php';
$admin = require_role(['admin']);
$pdo = db();
$errors = [];
$newCreds = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_faculty') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $dept  = trim($_POST['department'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must be 2-100 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Enter a valid email address.';
        if ($dept === '' || mb_strlen($dept) > 100) $errors[] = 'Department is required.';
        if (!$errors) {
            $temp = generate_password();
            try {
                $pdo->prepare("INSERT INTO users (name, email, password_hash, role, department) VALUES (?, ?, ?, 'faculty', ?)")
                    ->execute([$name, $email, password_hash($temp, PASSWORD_DEFAULT), $dept]);
                $newCreds = ['email' => $email, 'password' => $temp];  // shown once, never stored in plain text
            } catch (PDOException $ex) {
                $errors[] = ($ex->getCode() === '23000') ? 'This email is already registered.' : 'Could not create the account.';
            }
        }
    } elseif ($action === 'toggle_status') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $st = $pdo->prepare('SELECT id, role, account_status FROM users WHERE id = ?');
        $st->execute([$uid]);
        $target = $st->fetch();
        if (!$target) {
            $errors[] = 'User not found.';
        } elseif ($target['id'] === $admin['id'] || (int)$target['id'] === (int)$admin['id']) {
            $errors[] = 'You cannot deactivate your own account.';
        } else {
            $new = $target['account_status'] === 'active' ? 'inactive' : 'active';
            $activeAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND account_status = 'active'")->fetchColumn();
            if ($new === 'inactive' && $target['role'] === 'admin' && $activeAdmins <= 1) {
                $errors[] = 'The last active administrator cannot be deactivated.';
            } else {
                $pdo->prepare('UPDATE users SET account_status = ? WHERE id = ?')->execute([$new, $uid]);
                flash('success', 'Account ' . ($new === 'active' ? 'activated.' : 'deactivated.'));
                redirect('users.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'role', 'page']))));
            }
        }
    }
}

$q    = trim((string)($_GET['q'] ?? ''));
$role = (string)($_GET['role'] ?? '');
if (!in_array($role, ['student', 'faculty', 'admin'], true)) $role = '';
$where = [];
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(name LIKE :q1 OR email LIKE :q2 OR student_number LIKE :q3)';
    $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
}
if ($role !== '') { $where[] = 'role = :role'; $params[':role'] = $role; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / PER_PAGE));
$page = min(get_page(), $pages);
$st = $pdo->prepare("SELECT id, name, email, role, student_number, department, account_status, created_at FROM users $whereSql
                     ORDER BY created_at DESC, id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)(($page - 1) * PER_PAGE));
$st->execute($params);
$users = $st->fetchAll();

// Optional "view details" panel
$detail = null;
if (isset($_GET['view'])) {
    $d = $pdo->prepare('SELECT id, name, email, role, student_number, department, account_status, created_at FROM users WHERE id = ?');
    $d->execute([(int)$_GET['view']]);
    $detail = $d->fetch() ?: null;
    if ($detail) {
        $c = $pdo->prepare($detail['role'] === 'faculty' ? 'SELECT COUNT(*) FROM projects WHERE faculty_id = ?' : 'SELECT COUNT(*) FROM projects WHERE student_id = ?');
        $c->execute([$detail['id']]);
        $detail['project_count'] = (int)$c->fetchColumn();
    }
}
$pageTitle = 'User management';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title">User Management</h1>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($newCreds): ?>
  <div class="alert alert-success"><strong>Faculty account created.</strong> Give these credentials to the faculty member (shown only once):<br>
    Email: <code><?= e($newCreds['email']) ?></code> &nbsp; Temporary password: <code><?= e($newCreds['password']) ?></code><br>
    They should change it from the Profile page after logging in.</div>
<?php endif; ?>

<?php if ($detail): ?>
<div class="card">
  <div class="card-head"><h2>User details</h2><a href="<?= e(url('users.php')) ?>">Close</a></div>
  <dl class="details">
    <dt>Name</dt><dd><?= e($detail['name']) ?></dd>
    <dt>Email</dt><dd><?= e($detail['email']) ?></dd>
    <dt>Role</dt><dd><?= e(ucfirst($detail['role'])) ?></dd>
    <?php if ($detail['student_number']): ?><dt>Student number</dt><dd><?= e($detail['student_number']) ?></dd><?php endif; ?>
    <dt>Department</dt><dd><?= e($detail['department'] ?? '-') ?></dd>
    <dt>Status</dt><dd><?= e(ucfirst($detail['account_status'])) ?></dd>
    <dt>Joined</dt><dd><?= e(fmt_date($detail['created_at'])) ?></dd>
    <?php if ($detail['role'] !== 'admin'): ?><dt><?= $detail['role'] === 'faculty' ? 'Assigned projects' : 'Submitted projects' ?></dt><dd><?= (int)$detail['project_count'] ?></dd><?php endif; ?>
  </dl>
</div>
<?php endif; ?>

<div class="card">
  <h2>Create faculty account</h2>
  <form method="post" class="inline-form" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="create_faculty">
    <label>Full name<input type="text" name="name" value="<?= $newCreds ? '' : old('name') ?>" required maxlength="100"></label>
    <label>Email<input type="email" name="email" value="<?= $newCreds ? '' : old('email') ?>" required maxlength="150"></label>
    <label>Department<input type="text" name="department" value="<?= $newCreds ? '' : old('department', 'BCA') ?>" required maxlength="100"></label>
    <button class="btn" type="submit">Create &amp; generate password</button>
  </form>
</div>

<form class="card filters" method="get">
  <input type="search" name="q" placeholder="Search name, email or student number" value="<?= e($q) ?>">
  <select name="role"><option value="">All roles</option>
    <?php foreach (['student', 'faculty', 'admin'] as $r): ?><option value="<?= $r ?>" <?= $r === $role ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select>
  <button class="btn" type="submit">Filter</button>
  <a class="btn btn-outline" href="<?= e(url('users.php')) ?>">Reset</a>
</form>

<div class="card">
  <?php if (!$users): ?><div class="empty"><p>No users found.</p></div>
  <?php else: ?>
  <p class="muted small"><?= $total ?> user(s) found</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><?= e(ucfirst($u['role'])) ?></td>
        <td><?= e($u['department'] ?? '-') ?></td>
        <td><span class="badge badge-<?= $u['account_status'] === 'active' ? 'approved' : 'rejected' ?>"><?= e(ucfirst($u['account_status'])) ?></span></td>
        <td class="actions">
          <a class="btn btn-sm btn-outline" href="?<?= e(http_build_query(array_merge($_GET, ['view' => $u['id']]))) ?>">View</a>
          <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
          <form method="post" data-confirm="<?= $u['account_status'] === 'active' ? 'Deactivate this account? The user will not be able to log in.' : 'Activate this account?' ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button class="btn btn-sm <?= $u['account_status'] === 'active' ? 'btn-danger' : '' ?>" type="submit"><?= $u['account_status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?= render_pagination($page, $pages) ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
