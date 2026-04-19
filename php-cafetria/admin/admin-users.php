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
    $room     = trim($_POST['room'] ?? '');
    $ext      = trim($_POST['extension'] ?? '');

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
        // ... (existing upload logic)
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
    } elseif (isset($_POST['remove_picture'])) {
        if ($imageName && file_exists('../uploads/' . $imageName)) unlink('../uploads/' . $imageName);
        $imageName = null;
    }

    if ($errors) {
        $formError = implode('<br>', $errors);
        if ($editId) $editUser = $pdo->query("SELECT * FROM users WHERE id=$editId")->fetch();
    } else {
        if ($editId) {
            $sql    = "UPDATE users SET name=?, email=?, room=?, extension=?, profile_picture=? WHERE id=?";
            $params = [$name, $email, $room ?: null, $ext ?: null, $imageName ?: null, $editId];
            if ($password !== '') {
                $sql    = "UPDATE users SET name=?, email=?, password=?, room=?, extension=?, profile_picture=? WHERE id=?";
                $params = [$name, $email, password_hash($password, PASSWORD_DEFAULT), $room ?: null, $ext ?: null, $imageName ?: null, $editId];
            }
            $pdo->prepare($sql)->execute($params);
            $formSuccess = 'User updated successfully.';
            $editUser    = null;
        } else {
            $pdo->prepare("INSERT INTO users (name,email,password,room,extension,profile_picture,role) VALUES (?,?,?,?,?,?,'user')")
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

$activePage = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Users</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <h2>Users</h2>
        <div class="topbar-actions">
            <span class="badge badge-available"><?= $total ?> total users</span>
        </div>
    </div>

    <div class="page-content" style="display: grid; grid-template-columns: 1fr 350px; gap: 24px; align-items: start;">
        
        <div class="left-col">
            <?php if ($formSuccess): ?>
                <div class="alert alert-success" style="margin-bottom:20px; padding:12px; border-radius:8px; background:#e6f4ea; color:#1e7e34; border:1px solid #c3e6cb;">
                    <?= $formSuccess ?>
                </div>
            <?php endif; ?>
            <?php if ($formError): ?>
                <div class="alert alert-danger" style="margin-bottom:20px; padding:12px; border-radius:8px; background:#fce8e6; color:#d93025; border:1px solid #f5c6cb;">
                    <?= $formError ?>
                </div>
            <?php endif; ?>

            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">
                        <span class="material-symbols-outlined">group</span>
                        User List
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Room / Ext</th>
                                <th>Orders</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px">
                                        <div style="width:40px; height:40px; border-radius:50%; background:var(--surface-variant); display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid var(--outline-variant)">
                                            <?php if ($u['profile_picture']): ?>
                                                <img src="../uploads/<?= htmlspecialchars($u['profile_picture']) ?>" style="width:100%; height:100%; object-fit:cover">
                                            <?php else: ?>
                                                <span style="font-weight:700; color:var(--primary)"><?= strtoupper(substr($u['name'],0,1)) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:600"><?= htmlspecialchars($u['name']) ?></div>
                                            <div style="font-size:0.75rem; color:var(--on-surface-variant)">Joined <?= date('M j, Y', strtotime($u['created_at'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <div style="font-weight:500">Room <?= htmlspecialchars($u['room'] ?? '—') ?></div>
                                    <div style="font-size:0.75rem; color:var(--on-surface-variant)">Ext. <?= htmlspecialchars($u['extension'] ?? '—') ?></div>
                                </td>
                                <td>
                                    <span class="badge" style="background:rgba(201, 161, 74, 0.1); color:var(--primary)">
                                        <?= $u['order_count'] ?> orders
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <div style="display:flex; gap:8px; justify-content:flex-end">
                                        <a href="?edit=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" style="padding:6px; min-width:unset">
                                            <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                                        </a>
                                        <a href="?delete=<?= $u['id'] ?>" class="btn btn-secondary btn-sm" style="padding:6px; min-width:unset; color:#d93025" onclick="return confirm('Delete this user?')">
                                            <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; gap:8px; margin-top:20px">
                <?php for ($i=1; $i<=$totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="btn <?= $i==$page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="min-width:32px">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="right-col">
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">
                        <span class="material-symbols-outlined"><?= $editUser ? 'edit' : 'person_add' ?></span>
                        <?= $editUser ? 'Edit User' : 'Add New User' ?>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:16px; padding:20px">
                    <input type="hidden" name="edit_id" value="<?= $editUser ? $editUser['id'] : 0 ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="John Doe" required value="<?= $editUser ? htmlspecialchars($editUser['name']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="john@example.com" required value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= $editUser ? 'New Password (optional)' : 'Password' ?></label>
                        <input type="password" name="password" class="form-control" <?= $editUser ? '' : 'required' ?>>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" <?= $editUser ? '' : 'required' ?>>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px">
                        <div class="form-group">
                            <label class="form-label">Room</label>
                            <input type="text" name="room" class="form-control" placeholder="2010" value="<?= $editUser ? htmlspecialchars($editUser['room']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Extension</label>
                            <input type="text" name="extension" class="form-control" placeholder="5501" value="<?= $editUser ? htmlspecialchars($editUser['extension']) : '' ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" name="profile_picture" class="form-control">
                        <?php if ($editUser && $editUser['profile_picture']): ?>
                            <div style="display:flex; align-items:center; gap:8px; margin-top:8px">
                                <input type="checkbox" name="remove_picture" id="remove_pic" style="width:16px; height:16px">
                                <label for="remove_pic" style="font-size:0.8rem; color:var(--on-surface-variant); cursor:pointer">Remove current photo</label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:8px">
                        <button type="submit" name="save_user" class="btn btn-primary" style="flex:1">
                            <?= $editUser ? 'Update' : 'Create' ?>
                        </button>
                        <?php if ($editUser): ?>
                            <a href="admin-users.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="admin.js"></script>
</body>
</html>