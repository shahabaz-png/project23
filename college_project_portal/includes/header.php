<?php
/** header.php - top bar + sidebar. Set $pageTitle before including. */
$navUser = current_user();
$script  = basename($_SERVER['SCRIPT_NAME']);
$pageTitle = $pageTitle ?? 'College Project Portal';
$items = [];
if ($navUser) {
    $items[] = ['dashboard.php', 'Dashboard', '&#9638;'];
    $items[] = ['projects.php', $navUser['role'] === 'student' ? 'My Projects' : ($navUser['role'] === 'faculty' ? 'Assigned Projects' : 'All Projects'), '&#128193;'];
    if ($navUser['role'] === 'student') {
        $items[] = ['project_form.php', 'Submit Project', '&#10133;'];
    }
    if ($navUser['role'] === 'admin') {
        $items[] = ['users.php', 'Users', '&#128101;'];
    }
    $items[] = ['profile.php', 'Profile', '&#128100;'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | Project Portal</title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="<?= $navUser ? 'app' : 'guest' ?>">
<?php if ($navUser): ?>
<header class="topbar">
  <button class="menu-btn" type="button" id="menuBtn" aria-label="Toggle menu">&#9776;</button>
  <a class="brand" href="<?= e(url('dashboard.php')) ?>">&#127891; Project Portal</a>
  <div class="topbar-user">
    <span class="user-name"><?= e($navUser['name']) ?></span>
    <span class="role-tag"><?= e(ucfirst($navUser['role'])) ?></span>
    <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?>
      <button class="btn btn-sm btn-outline" type="submit">Logout</button></form>
  </div>
</header>
<div class="layout">
  <aside class="sidebar" id="sidebar">
    <nav>
      <?php foreach ($items as [$file, $label, $icon]): ?>
        <a href="<?= e(url($file)) ?>" class="<?= $script === $file ? 'active' : '' ?>"><span class="ico"><?= $icon ?></span><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <main class="content">
<?php else: ?>
<main class="guest-main">
<?php endif; ?>
<?php foreach (take_flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
<?php endforeach; ?>
