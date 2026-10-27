<?php
/** project_details.php - details, review history, faculty review, admin assign/delete. */
require_once __DIR__ . '/functions.php';
$user = require_login();
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$st = $pdo->prepare('SELECT p.*, s.name AS student_name, s.email AS student_email, s.student_number, s.department,
                            f.name AS faculty_name
                     FROM projects p JOIN users s ON s.id = p.student_id LEFT JOIN users f ON f.id = p.faculty_id
                     WHERE p.id = ?');
$st->execute([$id]);
$project = $st->fetch();
// Same message whether the project is missing or not yours (do not reveal which IDs exist)
if (!$project || !can_view_project($user, $project)) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="card empty"><h2>Project not found</h2><a class="btn" href="' . e(url('projects.php')) . '">Back to projects</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'assign' && $user['role'] === 'admin') {
        $fid = (int)($_POST['faculty_id'] ?? 0);
        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'faculty' AND account_status = 'active'");
        $chk->execute([$fid]);
        if (!$chk->fetch()) {
            $errors[] = 'Please choose an active faculty member.';
        } else {
            $pdo->prepare('UPDATE projects SET faculty_id = ? WHERE id = ?')->execute([$fid, $id]);
            flash('success', 'Faculty member assigned.');
            redirect('project_details.php?id=' . $id);
        }
    } elseif ($action === 'review' && $user['role'] === 'faculty') {
        $newStatus = (string)($_POST['new_status'] ?? '');
        $feedback  = trim((string)($_POST['feedback'] ?? ''));
        if ((int)$project['faculty_id'] !== (int)$user['id']) {
            $errors[] = 'This project is not assigned to you.';
        } elseif ($project['status'] !== 'Pending') {
            $errors[] = 'Only projects with Pending status can be reviewed.';
        } elseif (!in_array($newStatus, ['Approved', 'Rejected', 'Revision Required'], true)) {
            $errors[] = 'Choose a valid decision.';
        } elseif (mb_strlen($feedback) < 5 || mb_strlen($feedback) > 3000) {
            $errors[] = 'Feedback is required (5-3000 characters).';
        } else {
            try {
                $pdo->beginTransaction();
                // "AND status = 'Pending'" stops two simultaneous reviews from both succeeding
                $up = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ? AND faculty_id = ? AND status = 'Pending'");
                $up->execute([$newStatus, $id, $user['id']]);
                if ($up->rowCount() === 1) {
                    $pdo->prepare('INSERT INTO project_reviews (project_id, reviewer_id, previous_status, new_status, feedback) VALUES (?,?,?,?,?)')
                        ->execute([$id, $user['id'], 'Pending', $newStatus, $feedback]);
                    $pdo->commit();
                    flash('success', 'Review saved: ' . $newStatus . '.');
                    redirect('project_details.php?id=' . $id);
                }
                $pdo->rollBack();
                $errors[] = 'The project status changed. Please reload the page.';
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($ex->getMessage());
                $errors[] = 'Could not save the review.';
            }
        }
    } elseif ($action === 'delete' && $user['role'] === 'admin') {
        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);   // reviews are removed by ON DELETE CASCADE
        delete_stored_file($project['file_name']);
        flash('success', 'Project deleted.');
        redirect('projects.php');
    } else {
        http_response_code(403);
        $errors[] = 'You are not allowed to perform this action.';
    }
}

$rv = $pdo->prepare('SELECT r.*, u.name AS reviewer_name, u.role AS reviewer_role FROM project_reviews r
                     JOIN users u ON u.id = r.reviewer_id WHERE r.project_id = ? ORDER BY r.id DESC');
$rv->execute([$id]);
$history = $rv->fetchAll();

$facultyList = [];
if ($user['role'] === 'admin') {
    $facultyList = $pdo->query("SELECT id, name FROM users WHERE role = 'faculty' AND account_status = 'active' ORDER BY name")->fetchAll();
}
$canReview = $user['role'] === 'faculty' && (int)$project['faculty_id'] === (int)$user['id'] && $project['status'] === 'Pending';
$pageTitle = $project['title'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <h1 class="page-title"><?= e($project['title']) ?> <?= status_badge($project['status']) ?></h1>
  <div class="actions">
    <?php if ($user['role'] === 'student' && $project['status'] === 'Revision Required'): ?>
      <a class="btn" href="<?= e(url('project_form.php?id=' . $project['id'])) ?>">Edit &amp; resubmit</a>
    <?php endif; ?>
    <?php if ($user['role'] === 'admin'): ?>
      <form method="post" data-confirm="Delete this project and its review history permanently?">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="delete">
        <button class="btn btn-danger" type="submit">Delete project</button></form>
    <?php endif; ?>
    <a class="btn btn-outline" href="<?= e(url('projects.php')) ?>">Back</a>
  </div>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="grid-2 align-start">
  <div class="card">
    <h2>Project details</h2>
    <dl class="details">
      <dt>Category</dt><dd><?= e($project['category']) ?></dd>
      <dt>Technology stack</dt><dd><?= e($project['technology_stack']) ?></dd>
      <dt>Submitted</dt><dd><?= e(fmt_date($project['submitted_at'])) ?></dd>
      <dt>Student</dt><dd><?= e($project['student_name']) ?> (<?= e($project['student_number']) ?>) - <?= e($project['department']) ?></dd>
      <dt>Assigned faculty</dt><dd><?= $project['faculty_name'] ? e($project['faculty_name']) : '<span class="muted">Not assigned yet</span>' ?></dd>
      <?php if ($project['github_url']): ?><dt>GitHub</dt><dd><a href="<?= e($project['github_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($project['github_url']) ?></a></dd><?php endif; ?>
      <?php if ($project['live_demo_url']): ?><dt>Live URL</dt><dd><a href="<?= e($project['live_demo_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($project['live_demo_url']) ?></a></dd><?php endif; ?>
      <dt>File</dt><dd><a class="btn btn-sm btn-outline" href="<?= e(url('download.php?id=' . $project['id'])) ?>">&#11015; <?= e($project['original_file_name']) ?></a>
        <span class="muted small">(<?= e(fmt_size((int)$project['file_size'])) ?>)</span></dd>
    </dl>
    <h3>Description</h3>
    <p class="pre"><?= e($project['description']) ?></p>
  </div>

  <div>
    <?php if ($user['role'] === 'admin'): ?>
    <div class="card">
      <h2>Assign faculty</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="assign">
        <label>Faculty member
          <select name="faculty_id" required><option value="">Choose faculty</option>
            <?php foreach ($facultyList as $fa): ?><option value="<?= (int)$fa['id'] ?>" <?= (int)$fa['id'] === (int)$project['faculty_id'] ? 'selected' : '' ?>><?= e($fa['name']) ?></option><?php endforeach; ?>
          </select></label>
        <?php if (!$facultyList): ?><p class="muted small">No active faculty accounts yet. Create one on the Users page.</p><?php endif; ?>
        <button class="btn" type="submit"><?= $project['faculty_id'] ? 'Reassign' : 'Assign' ?></button>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($canReview): ?>
    <div class="card">
      <h2>Review this project</h2>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="review">
        <label>Decision
          <select name="new_status" required><option value="">Choose a decision</option>
            <option>Approved</option><option>Rejected</option><option>Revision Required</option></select></label>
        <label>Feedback<textarea name="feedback" rows="5" required maxlength="3000"><?= old('feedback') ?></textarea></label>
        <button class="btn" type="submit">Submit review</button>
      </form>
    </div>
    <?php elseif ($user['role'] === 'faculty'): ?>
      <div class="card"><p class="muted">This project is not awaiting review (current status: <?= e($project['status']) ?>).</p></div>
    <?php endif; ?>

    <?php if ($user['role'] === 'student' && $project['status'] === 'Revision Required'): ?>
      <div class="card"><p>Your faculty reviewer asked for changes. Read the feedback below, then update and resubmit.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Review history</h2>
  <?php if (!$history): ?>
    <div class="empty"><p>No history yet.</p></div>
  <?php else: ?>
  <ol class="timeline">
    <?php foreach ($history as $h): ?>
      <li>
        <div class="tl-head"><strong><?= e($h['reviewer_name']) ?></strong> <span class="muted small">(<?= e($h['reviewer_role']) ?>)</span>
          <span class="muted small"><?= e(fmt_date($h['created_at'])) ?></span></div>
        <div><?= $h['previous_status'] ? status_badge($h['previous_status']) . ' &rarr; ' : '' ?><?= status_badge($h['new_status']) ?></div>
        <?php if ($h['feedback']): ?><p class="pre"><?= e($h['feedback']) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
