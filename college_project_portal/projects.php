<?php
/** projects.php - project list with server-side search, filters and pagination (role-aware). */
require_once __DIR__ . '/functions.php';
$user = require_login();

echo '<pre>';
echo 'Logged-in user ID: ' . (int)$user['id'] . "\n";
echo 'Role: ' . htmlspecialchars($user['role']) . "\n";
echo '</pre>';

$pdo = db();

$q        = trim((string)($_GET['q'] ?? ''));
$category = (string)($_GET['category'] ?? '');
$status   = (string)($_GET['status'] ?? '');
$facultyF = (string)($_GET['faculty'] ?? '');   // admin only: faculty id or "none"
$studentF = (int)($_GET['student'] ?? 0);       // admin only
if (!in_array($category, CATEGORIES, true)) $category = '';
if (!in_array($status, STATUSES, true)) $status = '';

$where = [];
$params = [];
// 1) Role restriction - always applied first
if ($user['role'] === 'student') {
    $where[] = 'p.student_id = :uid';
    $params[':uid'] = $user['id'];
} elseif ($user['role'] === 'faculty') {
    $where[] = 'p.faculty_id = :uid';
    $params[':uid'] = $user['id'];
}
// 2) Optional filters
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    if ($user['role'] === 'student') {
        $where[] = 'p.title LIKE :q1';
    } else {
        $where[] = '(p.title LIKE :q1 OR s.name LIKE :q2)';
        $params[':q2'] = $like;
    }
    $params[':q1'] = $like;
}
if ($category !== '') { $where[] = 'p.category = :cat'; $params[':cat'] = $category; }
if ($status !== '')   { $where[] = 'p.status = :st';    $params[':st'] = $status; }
if ($user['role'] === 'admin') {
    if ($facultyF === 'none') {
        $where[] = 'p.faculty_id IS NULL';
    } elseif (ctype_digit($facultyF) && (int)$facultyF > 0) {
        $where[] = 'p.faculty_id = :fid';
        $params[':fid'] = (int)$facultyF;
    }
    if ($studentF > 0) { $where[] = 'p.student_id = :sid'; $params[':sid'] = $studentF; }
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = 'FROM projects p JOIN users s ON s.id = p.student_id LEFT JOIN users f ON f.id = p.faculty_id';

$cnt = $pdo->prepare("SELECT COUNT(*) $from $whereSql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / PER_PAGE));
$page = min(get_page(), $pages);
$offset = ($page - 1) * PER_PAGE;

$st = $pdo->prepare("SELECT p.id, p.title, p.category, p.status, p.submitted_at, s.name AS student_name, f.name AS faculty_name
    $from $whereSql ORDER BY p.submitted_at DESC, p.id DESC LIMIT " . (int)PER_PAGE . ' OFFSET ' . (int)$offset);
$st->execute($params);
$rows = $st->fetchAll();

$facultyList = $studentList = [];
if ($user['role'] === 'admin') {
    $facultyList = $pdo->query("SELECT id, name FROM users WHERE role = 'faculty' ORDER BY name")->fetchAll();
    $studentList = $pdo->query("SELECT id, name FROM users WHERE role = 'student' ORDER BY name")->fetchAll();
}
$pageTitle = 'Projects';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="page-title"><?= $user['role'] === 'student' ? 'My Projects' : ($user['role'] === 'faculty' ? 'Assigned Projects' : 'All Projects') ?></h1>
  <?php if ($user['role'] === 'student'): ?><a class="btn" href="<?= e(url('project_form.php')) ?>">+ Submit project</a><?php endif; ?>
</div>

<form class="card filters" method="get">
  <input type="search" name="q" placeholder="<?= $user['role'] === 'student' ? 'Search by title' : 'Search by title or student name' ?>" value="<?= e($q) ?>">
  <select name="category"><option value="">All categories</option>
    <?php foreach (CATEGORIES as $c): ?><option <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
  <select name="status"><option value="">All statuses</option>
    <?php foreach (STATUSES as $s): ?><option <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
  <?php if ($user['role'] === 'admin'): ?>
    <select name="faculty"><option value="">All faculty</option><option value="none" <?= $facultyF === 'none' ? 'selected' : '' ?>>Unassigned</option>
      <?php foreach ($facultyList as $f): ?><option value="<?= (int)$f['id'] ?>" <?= (string)$f['id'] === $facultyF ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?></select>
    <select name="student"><option value="">All students</option>
      <?php foreach ($studentList as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id'] === $studentF ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <button class="btn" type="submit">Filter</button>
  <a class="btn btn-outline" href="<?= e(url('projects.php')) ?>">Reset</a>
</form>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty"><p>No projects found.</p></div>
  <?php else: ?>
  <p class="muted small"><?= $total ?> project(s) found</p>
  <div class="table-wrap"><table>
    <thead><tr><th>Title</th><?php if ($user['role'] !== 'student'): ?><th>Student</th><?php endif; ?><th>Category</th>
      <?php if ($user['role'] !== 'faculty'): ?><th>Faculty</th><?php endif; ?><th>Status</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="<?= e(url('project_details.php?id=' . $r['id'])) ?>"><?= e($r['title']) ?></a></td>
        <?php if ($user['role'] !== 'student'): ?><td><?= e($r['student_name']) ?></td><?php endif; ?>
        <td><?= e($r['category']) ?></td>
        <?php if ($user['role'] !== 'faculty'): ?><td><?= $r['faculty_name'] ? e($r['faculty_name']) : '<span class="muted">Unassigned</span>' ?></td><?php endif; ?>
        <td><?= status_badge($r['status']) ?></td>
        <td><?= e(fmt_date($r['submitted_at'])) ?></td>
        <td><a class="btn btn-sm btn-outline" href="<?= e(url('project_details.php?id=' . $r['id'])) ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?= render_pagination($page, $pages) ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
