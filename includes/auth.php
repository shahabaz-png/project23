<?php
/** auth.php - login state and role checks. */

/** Returns the logged-in user (re-read from DB on each request) or null. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $st = db()->prepare('SELECT id, name, email, role, student_number, department, account_status, created_at
                             FROM users WHERE id = ?');
        $st->execute([(int)$_SESSION['user_id']]);
        $row = $st->fetch();
        if ($row && $row['account_status'] === 'active') {
            $user = $row;
        } else {
            unset($_SESSION['user_id']); // deactivated or deleted while logged in
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
    return $user;
}

/** Allows only the listed roles, otherwise shows a 403 page. */
function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<div class="card empty"><h2>403 - Access denied</h2><p>You do not have permission to view this page.</p>'
           . '<a class="btn" href="' . e(url('dashboard.php')) . '">Back to dashboard</a></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $user;
}
