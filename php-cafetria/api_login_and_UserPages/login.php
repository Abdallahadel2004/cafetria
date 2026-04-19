<?php
/**
 * login.php — Handles the POST submission from ../login.html
 *
 * Flow:
 *   1. If already logged in, redirect to the correct landing page.
 *   2. Validate POST email + password.
 *   3. Look up the user by email.
 *   4. Verify password with password_verify() (bcrypt hash in DB).
 *   5. On success: store id/name/role in session and redirect by role.
 *   6. On failure: redirect back to login.html with ?error=1.
 *
 * The HTML form must POST to this file:
 *   <form method="POST" action="api_login_and_UserPages/login.php">
 */

session_start();
require_once __DIR__ . '/db.php';

// ── Helper ───────────────────────────────────────────────────────────────
function go_home(string $role): void {
    if ($role === 'admin') {
        header('Location: ../admin/admin-dashboard.php');
    } else {
        header('Location: ../api_login_and_UserPages/user-home.php');
    }
    exit;
}

function back_to_login(string $code): void {
    header('Location: ../login.html?error=' . urlencode($code));
    exit;
}

// ── Already logged in?  Skip straight to the right page. ────────────────
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    go_home($_SESSION['role']);
}

// ── Only accept POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back_to_login('method');
}

$email    = trim($_POST['email']    ?? '');
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    back_to_login('empty');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back_to_login('email');
}

// ── Look up the user ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT id, name, email, password, role
    FROM   users
    WHERE  email = :email
    LIMIT  1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// Same generic message for missing user OR wrong password — don't leak which.
if (!$user || !password_verify($password, (string)$user['password'])) {
    back_to_login('credentials');
}

// ── Success — populate session ──────────────────────────────────────────
session_regenerate_id(true);
$_SESSION['user_id']   = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['email']     = $user['email'];
$_SESSION['role']      = $user['role'] ?: 'user';

go_home($_SESSION['role']);
