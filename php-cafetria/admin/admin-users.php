<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
require_once '../db.php';

$formError   = '';
$formSuccess = '';
$editUser    = null;

// ── Delete ──
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id === (int)$_SESSION['user_id']) {
        $formError = 'You cannot delete your own account.';
    } else {
        $row = $pdo->query("SELECT profile_picture FROM users WHERE id=$id")->fetch();
        if ($row && $row['profile_picture'] && file_exists('../uploads/' . $row['profile_picture'])) {
            unlink('../uploads/' . $row['profile_picture']);
        }
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        $formSuccess = 'User deleted.';
    }
}

// ── Load for edit ──
if (isset($_GET['edit'])) {
    $editUser = $pdo->query("SELECT * FROM users WHERE id=" . (int)$_GET['edit'])->fetch();
}

// ── Add / Edit POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $editId   = (int)($_POST['edit_id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $room     = trim($_POST['room_no'] ?? '');
    $ext      = trim($_POST['ext'] ?? '');

    // ── Validation ──
    $errors = [];
    if ($name === '')                              $errors[] = 'Name is required.';
    if ($email === '')                             $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';

    // Check duplicate email (exclude current user on edit)
    $dupCheck = $pdo->prepare("SELECT id FROM users WHERE email=? AND id != ?");
    $dupCheck->execute([$email, $editId]);
    if ($dupCheck->fetch())                        $errors[] = 'That email is already taken.';

    if (!$editId) {
        // Adding new user — password required
        if ($password === '')                      $errors[] = 'Password is required.';
        elseif (strlen($password) < 6)             $errors[] = 'Password must be at least 6 characters.';
        elseif ($password !== $confirm)            $errors[] = 'Passwords do not match.';
    } elseif ($password !== '') {
        // Editing — only validate if they typed something
        if (strlen($password) < 6)                $errors[] = 'New password must be at least 6 characters.';
        elseif ($password !== $confirm)            $errors[] = 'Passwords do not match.';
    }

    if ($room !== '' && !preg_match('/^[\w\-]{1,20}$/', $room))
        $errors[] = 'Room number contains invalid characters.';
    if ($ext !== '' && !preg_match('/^\d{1,10}$/', $ext))
        $errors[] = 'Extension must be numeric.';

    // Image upload
    $imageName = '';
    if ($editId) {
        $imageName = $pdo->query("SELECT profile_picture FROM users WHERE id=$editId")->fetchColumn() ?: '';
    }
    if (!empty($_FILES['profile_picture']['name'])) {
        $ext_img = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext_img, $allowed)) {
            $errors[] = 'Profile picture must be JPG, PNG, GIF or WEBP.';
        } elseif ($_FILES['profile_picture']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Profile picture must be under 2 MB.';
        } else {
            $uploadDir = '../uploads/users/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newName = uniqid('user_') . '.' . $ext_img;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadDir . $newName)) {
                if ($imageName && file_exists('../uploads/' . $imageName)) unlink('../uploads/' . $imageName);
                $imageName = 'users/' . $newName;
            } else {
                $errors[] = 'Image upload failed.';
            }
        }
    }

    if ($errors) {
        $formError = implode('<br>', $errors);
        if ($editId) $editUser = $pdo->query("SELECT * FROM users WHERE id=$editId")->fetch();
    } else {
        if ($editId) {
            $sql    = "UPDATE users SET name=?, email=?, room_no=?, ext=?, profile_picture=? WHERE id=?";
            $params = [$name, $email, $room ?: null, $ext ?: null, $imageName ?: null, $editId];
            if ($password !== '') {
                $sql    = "UPDATE users SET name=?, email=?, password=?, room_no=?, ext=?, profile_picture=? WHERE id=?";
                $params = [$name, $email, password_hash($password, PASSWORD_DEFAULT), $room ?: null, $ext ?: null, $imageName ?: null, $editId];
            }
            $pdo->prepare($sql)->execute($params);
            $formSuccess = 'User updated successfully.';
            $editUser    = null;
        } else {
            $pdo->prepare("INSERT INTO users (name,email,password,room_no,ext,profile_picture,role) VALUES (?,?,?,?,?,?,'user')")
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $room ?: null, $ext ?: null, $imageName ?: null]);
            $formSuccess = 'User added successfully.';
        }
    }
}

// ── Pagination ──
$perPage    = 10;
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;
$total      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$users = $pdo->query("
    SELECT u.*, COUNT(o.id) AS order_count
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Users</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;1,400&family=Jost:wght@200;300;400;500&display=swap"
        rel="stylesheet">
    <style>
    *,
    *::before,
    *::after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --gold: #c9a14a;
        --cream: #f0e6d0;
        --dark: #0e0a06;
        --brown: #1c1108;
        --mid: #2a1a09;
        --surface: #18100a;
        --border: rgba(201, 161, 74, .13);
        --text: rgba(240, 230, 208, .85);
        --muted: rgba(240, 230, 208, .38);
        --sidebar: 240px;
    }

    body {
        background: var(--dark);
        color: var(--text);
        font-family: 'Jost', sans-serif;
        font-weight: 300;
        display: flex;
        min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
        width: var(--sidebar);
        background: var(--brown);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 100;
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: 1.6rem 1.4rem;
        border-bottom: 1px solid var(--border);
        text-decoration: none;
    }

    .logo-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--mid);
        border: 1px solid rgba(201, 161, 74, .3);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .logo-icon svg {
        width: 18px;
        height: 18px;
        color: var(--gold);
    }

    .logo-text {
        font-family: 'Cormorant Garamond', serif;
        font-weight: 300;
        font-size: 1.4rem;
        color: var(--cream);
        line-height: 1;
    }

    .logo-text em {
        font-style: italic;
        color: var(--gold);
    }

    .sidebar-nav {
        flex: 1;
        padding: 1rem 0;
        overflow-y: auto;
    }

    .nav-label {
        font-size: .58rem;
        letter-spacing: .35em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, .35);
        padding: 1rem 1.4rem .4rem;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .7rem 1.4rem;
        color: var(--muted);
        text-decoration: none;
        font-size: .82rem;
        font-weight: 300;
        letter-spacing: .02em;
        transition: color .2s, background .2s;
        position: relative;
    }

    .nav-link svg {
        width: 17px;
        height: 17px;
        flex-shrink: 0;
    }

    .nav-link:hover {
        color: var(--cream);
        background: rgba(201, 161, 74, .05);
    }

    .nav-link.active {
        color: var(--gold);
        background: rgba(201, 161, 74, .08);
    }

    .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--gold);
    }

    .sidebar-footer {
        padding: 1.2rem 1.4rem;
        border-top: 1px solid var(--border);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: .7rem;
        margin-bottom: .9rem;
    }

    .avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--mid);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: .75rem;
        color: var(--gold);
        font-weight: 500;
        flex-shrink: 0;
    }

    .user-name {
        font-size: .82rem;
        color: var(--cream);
    }

    .user-role {
        font-size: .65rem;
        color: var(--muted);
        letter-spacing: .05em;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: .5rem;
        width: 100%;
        padding: .55rem .9rem;
        background: rgba(201, 161, 74, .06);
        border: 1px solid var(--border);
        color: var(--muted);
        font-family: 'Jost', sans-serif;
        font-size: .75rem;
        font-weight: 300;
        letter-spacing: .1em;
        text-transform: uppercase;
        cursor: pointer;
        text-decoration: none;
        transition: background .2s, color .2s;
    }

    .logout-btn:hover {
        background: rgba(201, 161, 74, .12);
        color: var(--gold);
    }

    .logout-btn svg {
        width: 14px;
        height: 14px;
    }

    /* Main two-column */
    .main {
        margin-left: var(--sidebar);
        flex: 1;
        padding: 2rem 2.2rem;
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 2rem;
        align-items: start;
    }

    .left {
        min-width: 0;
    }

    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }

    .page-title {
        font-family: 'Cormorant Garamond', serif;
        font-weight: 300;
        font-size: 2rem;
        color: var(--cream);
        line-height: 1;
    }

    .page-title em {
        font-style: italic;
        color: var(--gold);
    }

    /* Table */
    .table-wrap {
        background: var(--surface);
        border: 1px solid var(--border);
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead th {
        font-size: .6rem;
        letter-spacing: .25em;
        text-transform: uppercase;
        color: var(--muted);
        font-weight: 400;
        padding: .85rem 1rem;
        text-align: left;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    tbody tr {
        border-bottom: 1px solid rgba(201, 161, 74, .05);
        transition: background .15s;
    }

    tbody tr:last-child {
        border-bottom: none;
    }

    tbody tr:hover {
        background: rgba(201, 161, 74, .03);
    }

    td {
        padding: .75rem 1rem;
        font-size: .82rem;
        color: var(--text);
        white-space: nowrap;
    }

    .user-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--mid);
        border: 1px solid var(--border);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .75rem;
        color: var(--gold);
        font-weight: 500;
        vertical-align: middle;
        overflow: hidden;
    }

    .user-avatar img {
        width: 34px;
        height: 34px;
        object-fit: cover;
    }

    .action-link {
        font-size: .68rem;
        letter-spacing: .05em;
        text-decoration: none;
        color: var(--muted);
        transition: color .2s;
        margin-right: .55rem;
    }

    .action-link:hover {
        color: var(--gold);
    }

    .action-link.danger:hover {
        color: #e08080;
    }

    /* Pagination */
    .pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        margin-top: 1.2rem;
    }

    .page-btn {
        padding: .38rem .7rem;
        font-size: .68rem;
        font-family: 'Jost', sans-serif;
        background: var(--surface);
        border: 1px solid var(--border);
        color: var(--muted);
        text-decoration: none;
        transition: all .2s;
    }

    .page-btn:hover {
        border-color: rgba(201, 161, 74, .3);
        color: var(--cream);
    }

    .page-btn.active {
        background: rgba(201, 161, 74, .12);
        border-color: rgba(201, 161, 74, .35);
        color: var(--gold);
    }

    .page-btn.disabled {
        opacity: .3;
        pointer-events: none;
    }

    .empty {
        padding: 3rem;
        text-align: center;
        color: var(--muted);
        font-size: .82rem;
    }

    /* Panel */
    .panel {
        background: var(--surface);
        border: 1px solid var(--border);
        padding: 1.6rem;
        position: sticky;
        top: 2rem;
    }

    .panel-title {
        font-family: 'Cormorant Garamond', serif;
        font-weight: 300;
        font-size: 1.4rem;
        color: var(--cream);
        margin-bottom: 1.4rem;
    }

    .panel-title em {
        font-style: italic;
        color: var(--gold);
    }

    .field {
        margin-bottom: 1rem;
    }

    label {
        display: block;
        font-size: .62rem;
        letter-spacing: .2em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, .7);
        margin-bottom: .45rem;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="number"] {
        width: 100%;
        background: rgba(255, 255, 255, .04);
        border: 1px solid var(--border);
        color: var(--cream);
        font-family: 'Jost', sans-serif;
        font-weight: 300;
        font-size: .88rem;
        padding: .65rem .85rem;
        outline: none;
        transition: border-color .2s;
        -webkit-appearance: none;
    }

    input:focus {
        border-color: rgba(201, 161, 74, .45);
    }

    input::placeholder {
        color: var(--muted);
    }

    input.invalid {
        border-color: rgba(224, 92, 92, .5);
    }

    .field-error {
        font-size: .65rem;
        color: #e08080;
        margin-top: .3rem;
        display: none;
    }

    .field-error.show {
        display: block;
    }

    .pw-wrap {
        position: relative;
    }

    .pw-wrap input {
        padding-right: 2.4rem;
    }

    .eye-btn {
        position: absolute;
        right: .7rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: rgba(201, 161, 74, .35);
        display: flex;
        align-items: center;
        transition: color .2s;
        padding: 0;
    }

    .eye-btn:hover {
        color: var(--gold);
    }

    .eye-btn svg {
        width: 15px;
        height: 15px;
    }

    .file-label {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .6rem .85rem;
        background: rgba(255, 255, 255, .04);
        border: 1px dashed rgba(201, 161, 74, .2);
        cursor: pointer;
        color: var(--muted);
        font-size: .78rem;
        transition: border-color .2s;
    }

    .file-label:hover {
        border-color: rgba(201, 161, 74, .45);
        color: var(--cream);
    }

    .file-label svg {
        width: 14px;
        height: 14px;
        flex-shrink: 0;
    }

    input[type="file"] {
        display: none;
    }

    .btn-submit {
        width: 100%;
        padding: .85rem;
        background: var(--gold);
        color: var(--dark);
        font-family: 'Jost', sans-serif;
        font-weight: 400;
        font-size: .75rem;
        letter-spacing: .2em;
        text-transform: uppercase;
        border: none;
        cursor: pointer;
        transition: background .2s, transform .2s;
        margin-top: .5rem;
    }

    .btn-submit:hover {
        background: var(--cream);
        transform: translateY(-1px);
    }

    .btn-reset {
        width: 100%;
        padding: .65rem;
        background: transparent;
        border: 1px solid var(--border);
        color: var(--muted);
        font-family: 'Jost', sans-serif;
        font-size: .72rem;
        letter-spacing: .15em;
        text-transform: uppercase;
        cursor: pointer;
        margin-top: .4rem;
        transition: all .2s;
        text-align: center;
        text-decoration: none;
        display: block;
    }

    .btn-reset:hover {
        border-color: rgba(201, 161, 74, .25);
        color: var(--cream);
    }

    .alert {
        padding: .65rem .9rem;
        font-size: .78rem;
        margin-bottom: 1rem;
        line-height: 1.5;
    }

    .alert-error {
        background: rgba(224, 92, 92, .08);
        border: 1px solid rgba(224, 92, 92, .25);
        color: #e08080;
    }

    .alert-success {
        background: rgba(94, 196, 94, .08);
        border: 1px solid rgba(94, 196, 94, .2);
        color: #6ec87a;
    }

    #img-preview {
        max-width: 100%;
        max-height: 80px;
        display: none;
        margin-top: .5rem;
        border: 1px solid var(--border);
    }

    .hint {
        font-size: .62rem;
        color: var(--muted);
        margin-top: .25rem;
    }
    </style>
</head>

<body>

    <aside class="sidebar">
        <a href="admin-dashboard.php" class="sidebar-logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                    <line x1="6" y1="1" x2="6" y2="4" />
                    <line x1="10" y1="1" x2="10" y2="4" />
                    <line x1="14" y1="1" x2="14" y2="4" />
                </svg>
            </div>
            <span class="logo-text">Cafe<em>tria</em></span>
        </a>
        <nav class="sidebar-nav">
            <p class="nav-label">Main</p>
            <a href="admin-dashboard.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>Dashboard
            </a>
            <a href="admin-orders.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                    <rect x="9" y="3" width="6" height="4" rx="1" />
                    <path d="M9 12h6M9 16h4" />
                </svg>Orders
            </a>
            <a href="admin-products.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                    <line x1="6" y1="1" x2="6" y2="4" />
                    <line x1="10" y1="1" x2="10" y2="4" />
                    <line x1="14" y1="1" x2="14" y2="4" />
                </svg>Products
            </a>
            <a href="admin-users.php" class="nav-link active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>Users
            </a>
            <p class="nav-label">Operations</p>
            <a href="admin-manual-order.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1" />
                    <circle cx="20" cy="21" r="1" />
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                </svg>Manual Order
            </a>
            <a href="admin-checks.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23" />
                    <path d="M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6" />
                </svg>Checks
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'],0,1)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>Sign Out
            </a>
        </div>
    </aside>

    <main class="main">

        <!-- Left: user list -->
        <div class="left">
            <div class="page-header">
                <h1 class="page-title">All <em>Users</em></h1>
                <span style="font-size:.72rem;color:var(--muted)"><?= $total ?> user<?= $total!=1?'s':'' ?></span>
            </div>

            <div class="table-wrap">
                <?php if ($users): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Room</th>
                            <th>Ext.</th>
                            <th>Orders</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:.65rem;">
                                    <div class="user-avatar">
                                        <?php if ($u['profile_picture'] && file_exists('../uploads/'.$u['profile_picture'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($u['profile_picture']) ?>" alt="">
                                        <?php else: ?>
                                        <?= strtoupper(substr($u['name'],0,1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <span style="color:var(--cream)"><?= htmlspecialchars($u['name']) ?></span>
                                </div>
                            </td>
                            <td style="color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['room_no'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($u['ext'] ?? '—') ?></td>
                            <td style="color:var(--gold)"><?= $u['order_count'] ?></td>
                            <td style="color:var(--muted)"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <a href="?edit=<?= $u['id'] ?>" class="action-link">Edit</a>
                                <a href="?delete=<?= $u['id'] ?>" class="action-link danger"
                                    onclick="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">No users yet.</div>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <a href="?page=1" class="page-btn <?= $page==1?'disabled':'' ?>">«</a>
                <a href="?page=<?= max(1,$page-1) ?>" class="page-btn <?= $page==1?'disabled':'' ?>">‹</a>
                <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
                <a href="?page=<?= $i ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= min($totalPages,$page+1) ?>"
                    class="page-btn <?= $page==$totalPages?'disabled':'' ?>">›</a>
                <a href="?page=<?= $totalPages ?>" class="page-btn <?= $page==$totalPages?'disabled':'' ?>">»</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Add / Edit panel -->
        <div class="panel">
            <div class="panel-title"><?= $editUser ? '<em>Edit</em> User' : 'Add <em>User</em>' ?></div>

            <?php if ($formError): ?>
            <div class="alert alert-error"><?= $formError ?></div>
            <?php endif; ?>
            <?php if ($formSuccess): ?>
            <div class="alert alert-success"><?= htmlspecialchars($formSuccess) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="userForm" novalidate>
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="edit_id" value="<?= $editUser ? $editUser['id'] : 0 ?>">

                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="name" id="f-name" placeholder="Islam Askar"
                        value="<?= htmlspecialchars($editUser['name'] ?? '') ?>">
                    <div class="field-error" id="e-name">Name is required.</div>
                </div>

                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" id="f-email" placeholder="user@company.com"
                        value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                    <div class="field-error" id="e-email">Enter a valid email.</div>
                </div>

                <div class="field">
                    <label><?= $editUser ? 'New Password (leave blank to keep)' : 'Password' ?></label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="f-password" placeholder="••••••••">
                        <button type="button" class="eye-btn" onclick="togglePw('f-password',this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="field-error" id="e-password">Min 6 characters.</div>
                </div>

                <div class="field">
                    <label>Confirm Password</label>
                    <div class="pw-wrap">
                        <input type="password" name="confirm_password" id="f-confirm" placeholder="••••••••">
                        <button type="button" class="eye-btn" onclick="togglePw('f-confirm',this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                    <div class="field-error" id="e-confirm">Passwords do not match.</div>
                </div>

                <div class="field">
                    <label>Room No.</label>
                    <input type="text" name="room_no" id="f-room" placeholder="2010"
                        value="<?= htmlspecialchars($editUser['room_no'] ?? '') ?>">
                    <div class="field-error" id="e-room">Invalid room number.</div>
                </div>

                <div class="field">
                    <label>Extension</label>
                    <input type="text" name="ext" id="f-ext" placeholder="5605"
                        value="<?= htmlspecialchars($editUser['ext'] ?? '') ?>">
                    <div class="field-error" id="e-ext">Extension must be numeric.</div>
                </div>

                <div class="field">
                    <label>Profile Picture <span class="hint" style="display:inline">(max 2 MB)</span></label>
                    <?php if ($editUser && $editUser['profile_picture'] && file_exists('../uploads/'.$editUser['profile_picture'])): ?>
                    <img src="../uploads/<?= htmlspecialchars($editUser['profile_picture']) ?>" id="img-preview"
                        style="display:block;margin-bottom:.4rem;">
                    <?php else: ?>
                    <img id="img-preview">
                    <?php endif; ?>
                    <label class="file-label" for="pic-input">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <polyline points="21 15 16 10 5 21" />
                        </svg>
                        <span id="pic-label">Browse image…</span>
                    </label>
                    <input type="file" name="profile_picture" id="pic-input" accept="image/*"
                        onchange="previewImg(this)">
                </div>

                <button type="submit" class="btn-submit"><?= $editUser ? 'Save Changes' : 'Add User' ?></button>
                <?php if ($editUser): ?>
                <a href="admin-users.php" class="btn-reset">Cancel</a>
                <?php else: ?>
                <button type="reset" class="btn-reset" onclick="resetForm()">Reset</button>
                <?php endif; ?>
            </form>
        </div>

    </main>

    <script>
    // Eye toggle
    function togglePw(inputId, btn) {
        const inp = document.getElementById(inputId);
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        btn.querySelector('svg').innerHTML = show ?
            `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>` :
            `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
    }

    // Image preview
    function previewImg(input) {
        if (input.files && input.files[0]) {
            const r = new FileReader();
            r.onload = e => {
                const p = document.getElementById('img-preview');
                p.src = e.target.result;
                p.style.display = 'block';
            };
            r.readAsDataURL(input.files[0]);
            document.getElementById('pic-label').textContent = input.files[0].name;
        }
    }

    function resetForm() {
        document.getElementById('img-preview').style.display = 'none';
        document.getElementById('pic-label').textContent = 'Browse image…';
    }

    // Client-side validation
    const isEditing = <?= $editUser ? 'true' : 'false' ?>;
    const form = document.getElementById('userForm');

    function showErr(id, msg) {
        const el = document.getElementById(id);
        if (msg) el.textContent = msg;
        el.classList.add('show');
        document.getElementById(id.replace('e-', 'f-')).classList.add('invalid');
    }

    function clearErr(id) {
        document.getElementById(id).classList.remove('show');
        document.getElementById(id.replace('e-', 'f-')).classList.remove('invalid');
    }

    // Live clear on input
    ['name', 'email', 'password', 'confirm', 'room', 'ext'].forEach(f => {
        const inp = document.getElementById('f-' + f);
        if (inp) inp.addEventListener('input', () => clearErr('e-' + f));
    });

    form.addEventListener('submit', e => {
        let ok = true;
        const name = document.getElementById('f-name').value.trim();
        const email = document.getElementById('f-email').value.trim();
        const pw = document.getElementById('f-password').value;
        const conf = document.getElementById('f-confirm').value;
        const room = document.getElementById('f-room').value.trim();
        const ext = document.getElementById('f-ext').value.trim();

        if (!name) {
            showErr('e-name', 'Name is required.');
            ok = false;
        }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showErr('e-email', 'Enter a valid email.');
            ok = false;
        }
        if (!isEditing && !pw) {
            showErr('e-password', 'Password is required.');
            ok = false;
        } else if (pw && pw.length < 6) {
            showErr('e-password', 'Min 6 characters.');
            ok = false;
        }
        if (pw && pw !== conf) {
            showErr('e-confirm', 'Passwords do not match.');
            ok = false;
        }
        if (room && !/^[\w\-]{1,20}$/.test(room)) {
            showErr('e-room', 'Invalid room number.');
            ok = false;
        }
        if (ext && !/^\d{1,10}$/.test(ext)) {
            showErr('e-ext', 'Extension must be numeric.');
            ok = false;
        }

        if (!ok) e.preventDefault();
    });
    </script>
</body>

</html>