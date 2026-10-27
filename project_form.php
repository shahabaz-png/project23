<?php
/** project_form.php - students submit a new project, or edit/resubmit one that needs revision. */
require_once __DIR__ . '/functions.php';
$user = require_role(['student']);
$pdo = db();

// Only active faculty accounts can be selected by students.
$facultyList = $pdo->query("SELECT id, name, department FROM users WHERE role = 'faculty' AND account_status = 'active' ORDER BY name")->fetchAll();

$editId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$project = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM projects WHERE id = ? AND student_id = ?');
    $st->execute([$editId, $user['id']]);
    $project = $st->fetch();
    if (!$project) {
        flash('error', 'Project not found.');
        redirect('projects.php');
    }
    if ($project['status'] !== 'Revision Required') {
        flash('warning', 'Only projects with "Revision Required" status can be edited.');
        redirect('project_details.php?id=' . $editId);
    }
}

$errors = [];
$f = [
    'title' => $project['title'] ?? '', 'category' => $project['category'] ?? '',
    'description' => $project['description'] ?? '', 'technology_stack' => $project['technology_stack'] ?? '',
    'github_url' => $project['github_url'] ?? '', 'live_demo_url' => $project['live_demo_url'] ?? '',
    'faculty_id' => (string)($project['faculty_id'] ?? ''),
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($f as $k => $_) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $facultyId = (int)($f['faculty_id'] ?? 0);
    $facultyCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'faculty' AND account_status = 'active'");
    $facultyCheck->execute([$facultyId]);
    if ($facultyId < 1 || !$facultyCheck->fetch()) {
        $errors[] = 'Please select an active faculty member.';
    }
    if (mb_strlen($f['title']) < 3 || mb_strlen($f['title']) > 150) $errors[] = 'Title must be 3-150 characters.';
    if (!in_array($f['category'], CATEGORIES, true)) $errors[] = 'Please choose a valid category.';
    if (mb_strlen($f['description']) < 20 || mb_strlen($f['description']) > 5000) $errors[] = 'Description must be 20-5000 characters.';
    if ($f['technology_stack'] === '' || mb_strlen($f['technology_stack']) > 255) $errors[] = 'Technology stack is required (max 255 characters).';
    if ($e1 = check_url($f['github_url'], 'GitHub URL')) $errors[] = $e1;
    if ($e2 = check_url($f['live_demo_url'], 'Live project URL')) $errors[] = $e2;

    $hasFile = isset($_FILES['project_file']) && $_FILES['project_file']['error'] !== UPLOAD_ERR_NO_FILE;
    $file = null;
    if (!$errors && ($hasFile || !$project)) {          // file required for new projects, optional on edit
        $file = process_upload($_FILES['project_file'] ?? [], $uploadError);
        if (!$file) $errors[] = $uploadError;
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            if (!$project) {
                $pdo->prepare('INSERT INTO projects (student_id, faculty_id, title, category, description, technology_stack, github_url, live_demo_url,
                               file_name, original_file_name, file_size, status, submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())')
                    ->execute([$user['id'], $facultyId, $f['title'], $f['category'], $f['description'], $f['technology_stack'],
                        $f['github_url'] ?: null, $f['live_demo_url'] ?: null,
                        $file['stored'], $file['original'], $file['size'], 'Pending']);
                $newId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO project_reviews (project_id, reviewer_id, previous_status, new_status, feedback) VALUES (?,?,NULL,?,?)')
                    ->execute([$newId, $user['id'], 'Pending', 'Project submitted by student.']);
            } else {
                $sql = 'UPDATE projects SET faculty_id=?, title=?, category=?, description=?, technology_stack=?, github_url=?, live_demo_url=?, status=?, submitted_at=NOW()';
                $args = [$facultyId, $f['title'], $f['category'], $f['description'], $f['technology_stack'], $f['github_url'] ?: null, $f['live_demo_url'] ?: null, 'Pending'];
                if ($file) {
                    $sql .= ', file_name=?, original_file_name=?, file_size=?';
                    array_push($args, $file['stored'], $file['original'], $file['size']);
                }
                $pdo->prepare($sql . ' WHERE id=? AND student_id=? AND status=?')
                    ->execute(array_merge($args, [$project['id'], $user['id'], 'Revision Required']));
                $pdo->prepare('INSERT INTO project_reviews (project_id, reviewer_id, previous_status, new_status, feedback) VALUES (?,?,?,?,?)')
                    ->execute([$project['id'], $user['id'], 'Revision Required', 'Pending', 'Project updated and resubmitted by student.']);
                $newId = (int)$project['id'];
            }
            $pdo->commit();
            if ($project && $file) delete_stored_file($project['file_name']);   // remove replaced file
            flash('success', $project ? 'Project resubmitted for review.' : 'Project submitted successfully.');
            redirect('project_details.php?id=' . $newId);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($file) delete_stored_file($file['stored']);
            error_log($ex->getMessage());
            $errors[] = 'Could not save the project. Please try again.';
        }
    }
}
$pageTitle = $project ? 'Update project' : 'Submit project';
require __DIR__ . '/includes/header.php';
?>
<h1 class="page-title"><?= $project ? 'Update &amp; resubmit project' : 'Submit a new project' ?></h1>
<div class="card">
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <?php if ($project): ?><input type="hidden" name="id" value="<?= (int)$project['id'] ?>"><?php endif; ?>
    <label>Project title<input type="text" name="title" value="<?= e($f['title']) ?>" required maxlength="150"></label>
    <div class="grid-2">
      <label>Category
        <select name="category" required><option value="">Choose a category</option>
          <?php foreach (CATEGORIES as $c): ?><option <?= $c === $f['category'] ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select></label>
      <label>Technology stack<input type="text" name="technology_stack" value="<?= e($f['technology_stack']) ?>" placeholder="e.g. PHP, MySQL, JavaScript" required maxlength="255"></label>
    </div>
    <label>Choose faculty member
      <select name="faculty_id" required>
        <option value="">Select faculty to review your project</option>
        <?php foreach ($facultyList as $fa): ?>
          <option value="<?= (int)$fa['id'] ?>" <?= (string)$fa['id'] === (string)$f['faculty_id'] ? 'selected' : '' ?>>
            <?= e($fa['name']) ?><?= !empty($fa['department']) ? ' — ' . e($fa['department']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if (!$facultyList): ?>
      <div class="alert alert-error">No active faculty accounts are available. Please contact your administrator before submitting.</div>
    <?php else: ?>
      <p class="muted small">The faculty member you choose will receive this submission in their assigned-project list.</p>
    <?php endif; ?>
    <label>Project description<textarea name="description" rows="6" required maxlength="5000"><?= e($f['description']) ?></textarea></label>
    <div class="grid-2">
      <label>GitHub repository URL (optional)<input type="url" name="github_url" value="<?= e($f['github_url']) ?>" placeholder="https://github.com/..."></label>
      <label>Live project URL (optional)<input type="url" name="live_demo_url" value="<?= e($f['live_demo_url']) ?>" placeholder="https://..."></label>
    </div>
    <label>Project document or ZIP (PDF, DOCX, ZIP - max <?= e(fmt_size(MAX_UPLOAD_BYTES)) ?>)
      <input type="file" name="project_file" accept=".pdf,.docx,.zip" data-max="<?= (int)MAX_UPLOAD_BYTES ?>" <?= $project ? '' : 'required' ?>></label>
    <?php if ($project): ?><p class="muted small">Current file: <?= e($project['original_file_name']) ?>. Leave empty to keep it.</p><?php endif; ?>
    <p class="muted small">Submission date: <?= e(date('d M Y')) ?> (recorded automatically)</p>
    <div class="actions">
      <button class="btn" type="submit" <?= !$facultyList ? 'disabled' : '' ?>><?= $project ? 'Resubmit project' : 'Submit project' ?></button>
      <a class="btn btn-outline" href="<?= e(url($project ? 'project_details.php?id=' . $project['id'] : 'projects.php')) ?>">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
