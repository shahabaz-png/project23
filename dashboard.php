<?php
require_once __DIR__ . '/functions.php';
$user = require_login();
$pdo = db();

/** Counts projects by status. $where/$params limit which projects are counted. */
function project_counts(PDO $pdo, string $where = '1=1', array $params = []): array
{
    $st = $pdo->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(status = 'Pending'), 0) AS pending,
        COALESCE(SUM(status = 'Approved'), 0) AS approved,
        COALESCE(SUM(status = 'Rejected'), 0) AS rejected,
        COALESCE(SUM(status = 'Revision Required'), 0) AS revision
        FROM projects WHERE $where");
    $st->execute($params);
    return array_map('intval', $st->fetch());
}

$latestFeedback = "(SELECT r.feedback FROM project_reviews r WHERE r.project_id = p.id AND r.new_status <> 'Pending'
                    ORDER BY r.id DESC LIMIT 1)";
$base = "FROM projects p JOIN users s ON s.id = p.student_id LEFT JOIN users f ON f.id = p.faculty_id";

if ($user['role'] === 'student') {
    $counts = project_counts($pdo, 'student_id = ?', [$user['id']]);
    $st = $pdo->prepare("SELECT p.id, p.title, p.category, p.status, p.submitted_at, $latestFeedback AS feedback
                         $base WHERE p.student_id = ? ORDER BY p.submitted_at DESC, p.id DESC LIMIT 5");
    $st->execute([$user['id']]);
} elseif ($user['role'] === 'faculty') {
    $counts = project_counts($pdo, 'faculty_id = ?', [$user['id']]);
    $st = $pdo->prepare("SELECT p.id, p.title, p.category, p.status, p.submitted_at, s.name AS student_name
                         $base WHERE p.faculty_id = ? ORDER BY p.submitted_at DESC, p.id DESC LIMIT 5");
    $st->execute([$user['id']]);
} else {
    $counts = project_counts($pdo);
    $st = $pdo->query("SELECT p.id, p.title, p.category, p.status, p.submitted_at, s.name AS student_name, f.name AS faculty_name
                       $base ORDER BY p.submitted_at DESC, p.id DESC LIMIT 5");
}
$recent = $st->fetchAll();

$totals = ['students' => 0, 'faculty' => 0, 'unassigned' => 0];
if ($user['role'] === 'admin') {
    $totals['students']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $totals['faculty']    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'faculty'")->fetchColumn();
    $totals['unassigned'] = (int)$pdo->query('SELECT COUNT(*) FROM projects WHERE faculty_id IS NULL')->fetchColumn();
}
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="page-title">Welcome, <?= e($user['name']) ?> &#128075;</h1>
  <?php if ($user['role'] === 'student'): ?>
    <a class="btn" href="<?= e(url('project_form.php')) ?>">+ Submit new project</a>
  <?php endif; ?>
</div>

<div class="stats">
  <?php if ($user['role'] === 'admin'): ?>
    <div class="stat"><span>Total students</span><strong><?= $totals['students'] ?></strong></div>
    <div class="stat"><span>Total faculty</span><strong><?= $totals['faculty'] ?></strong></div>
  <?php endif; ?>
  <div class="stat"><span><?= $user['role'] === 'faculty' ? 'Assigned projects' : ($user['role'] === 'student' ? 'Submitted projects' : 'Total projects') ?></span><strong><?= $counts['total'] ?></strong></div>
  <div class="stat s-pending"><span><?= $user['role'] === 'faculty' ? 'Pending reviews' : 'Pending' ?></span><strong><?= $counts['pending'] ?></strong></div>
  <div class="stat s-approved"><span>Approved</span><strong><?= $counts['approved'] ?></strong></div>
  <div class="stat s-rejected"><span>Rejected</span><strong><?= $counts['rejected'] ?></strong></div>
  <div class="stat s-revision"><span>Revision requests</span><strong><?= $counts['revision'] ?></strong></div>
  <?php if ($user['role'] === 'admin'): ?>
    <div class="stat"><span>Unassigned projects</span><strong><?= $totals['unassigned'] ?></strong></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head"><h2><?= $user['role'] === 'student' ? 'Recent submissions' : 'Recently submitted projects' ?></h2>
    <a href="<?= e(url('projects.php')) ?>">View all</a></div>
  <?php if (!$recent): ?>
    <div class="empty"><p>No projects yet.</p>
      <?php if ($user['role'] === 'student'): ?><a class="btn" href="<?= e(url('project_form.php')) ?>">Submit your first project</a><?php endif; ?></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Title</th>
      <?php if ($user['role'] !== 'student'): ?><th>Student</th><?php endif; ?>
      <th>Category</th><?php if ($user['role'] === 'admin'): ?><th>Faculty</th><?php endif; ?>
      <th>Status</th><th>Submitted</th><?php if ($user['role'] === 'student'): ?><th>Faculty feedback</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><a href="<?= e(url('project_details.php?id=' . $r['id'])) ?>"><?= e($r['title']) ?></a></td>
        <?php if ($user['role'] !== 'student'): ?><td><?= e($r['student_name']) ?></td><?php endif; ?>
        <td><?= e($r['category']) ?></td>
        <?php if ($user['role'] === 'admin'): ?><td><?= $r['faculty_name'] ? e($r['faculty_name']) : '<span class="muted">Unassigned</span>' ?></td><?php endif; ?>
        <td><?= status_badge($r['status']) ?></td>
        <td><?= e(fmt_date($r['submitted_at'])) ?></td>
        <?php if ($user['role'] === 'student'): ?><td class="clip"><?= $r['feedback'] ? e(mb_strimwidth($r['feedback'], 0, 80, '...')) : '<span class="muted">No feedback yet</span>' ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<?php if ($user['role'] === 'student'): ?>
<div class="card">
  <h2>Profile information</h2>
  <dl class="details">
    <dt>Name</dt><dd><?= e($user['name']) ?></dd>
    <dt>Email</dt><dd><?= e($user['email']) ?></dd>
    <dt>Student number</dt><dd><?= e($user['student_number']) ?></dd>
    <dt>Department</dt><dd><?= e($user['department']) ?></dd>
  </dl>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
