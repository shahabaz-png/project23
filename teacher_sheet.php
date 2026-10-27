<?php
/** teacher_sheet.php - restricted spreadsheet-style project completion sheet. */
require_once __DIR__ . '/functions.php';
$user = require_role(['faculty', 'admin']);
$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
if ($status !== '' && !in_array($status, STATUSES, true)) $status = '';
$where = [];
$params = [];
// Faculty can see only projects assigned to them. Admins can see every project.
if ($user['role'] === 'faculty') {
    $where[] = 'p.faculty_id = :uid';
    $params[':uid'] = $user['id'];
}
if ($q !== '') {
    $where[] = '(p.title LIKE :q1 OR s.name LIKE :q2 OR s.email LIKE :q3)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}
if ($status !== '') {
    $where[] = 'p.status = :status';
    $params[':status'] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = 'FROM projects p JOIN users s ON s.id = p.student_id LEFT JOIN users f ON f.id = p.faculty_id';
$sql = "SELECT p.id, p.title, p.category, p.status, p.github_url, p.original_file_name,
               p.submitted_at, s.name AS student_name, s.email AS student_email,
               f.name AS faculty_name $from $whereSql ORDER BY s.name, p.submitted_at DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="project-submissions.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student Name', 'Student Email', 'Project Title', 'Category', 'GitHub URL', 'Uploaded File', 'Status', 'Submitted At', 'Faculty']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['student_name'], $r['student_email'], $r['title'], $r['category'], $r['github_url'], $r['original_file_name'], $r['status'], $r['submitted_at'], $r['faculty_name']]);
    }
    fclose($out);
    exit;
}

$counts = ['total' => count($rows), 'approved' => 0, 'pending' => 0, 'other' => 0];
foreach ($rows as $r) {
    if ($r['status'] === 'Approved') $counts['approved']++;
    elseif ($r['status'] === 'Pending') $counts['pending']++;
    else $counts['other']++;
}
$pageTitle = 'Teacher Submission Sheet';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1 class="page-title">Project Submission Sheet</h1><p class="muted">Restricted teacher view — approved projects count as completed.</p></div>
  <a class="btn" href="<?= e(url('teacher_sheet.php?' . http_build_query(array_merge($_GET, ['export' => 'csv'])))) ?>">Export CSV</a>
</div>
<div class="stats">
  <div class="stat"><span>Visible submissions</span><strong><?= $counts['total'] ?></strong></div>
  <div class="stat s-approved"><span>Completed (Approved)</span><strong><?= $counts['approved'] ?></strong></div>
  <div class="stat s-pending"><span>Pending review</span><strong><?= $counts['pending'] ?></strong></div>
  <div class="stat"><span>Other statuses</span><strong><?= $counts['other'] ?></strong></div>
</div>
<form class="card filters" method="get">
  <input type="search" name="q" placeholder="Search student, email, or project" value="<?= e($q) ?>">
  <select name="status"><option value="">All statuses</option>
    <?php foreach (STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
  <a class="btn btn-outline" href="<?= e(url('teacher_sheet.php')) ?>">Reset</a>
</form>
<div class="card">
  <?php if (!$rows): ?><div class="empty"><p>No submissions found for this filter.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Project</th><th>Category</th><th>GitHub</th><th>Uploaded file</th><th>Status</th><th>Submitted</th><th>Faculty</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['student_name']) ?></strong><br><span class="muted small"><?= e($r['student_email']) ?></span></td>
        <td><a href="<?= e(url('project_details.php?id=' . (int)$r['id'])) ?>"><?= e($r['title']) ?></a></td>
        <td><?= e($r['category']) ?></td>
        <td><?php if ($r['github_url']): ?><a href="<?= e($r['github_url']) ?>" target="_blank" rel="noopener noreferrer">Open GitHub</a><?php else: ?><span class="muted">Not provided</span><?php endif; ?></td>
        <td><a href="<?= e(url('download.php?id=' . (int)$r['id'])) ?>"><?= e($r['original_file_name']) ?></a></td>
        <td><?= status_badge($r['status']) ?></td>
        <td><?= e(fmt_date($r['submitted_at'])) ?></td>
        <td><?= $r['faculty_name'] ? e($r['faculty_name']) : '<span class="muted">Unassigned</span>' ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
