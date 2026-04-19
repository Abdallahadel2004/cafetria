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
    if ($name === '')                                      $errors[] = 'Name is required.';
    elseif (strlen($name) < 2)                            $errors[] = 'Name must be at least 2 characters.';

    if ($email === '')                                     $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'Enter a valid email address.';

    $dupCheck = $pdo->prepare("SELECT id FROM users WHERE email=? AND id != ?");
    $dupCheck->execute([$email, $editId]);
    if ($dupCheck->fetch())                                $errors[] = 'That email is already taken.';

    if (!$editId) {
        if ($password === '')                              $errors[] = 'Password is required.';
        elseif (strlen($password) < 6)                    $errors[] = 'Password must be at least 6 characters.';
        elseif ($password !== $confirm)                   $errors[] = 'Passwords do not match.';
    } elseif ($password !== '') {
        if (strlen($password) < 6)                        $errors[] = 'New password must be at least 6 characters.';
        elseif ($password !== $confirm)                   $errors[] = 'Passwords do not match.';
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
$isEdit     = (bool)$editUser;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Users</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
    .field-error {
        color: #d93025;
        font-size: 0.78rem;
        margin-top: 4px;
        display: none;
    }

    .form-control.is-invalid {
        border-color: #d93025;
        background-color: #fff8f7;
    }

    .form-control.is-invalid:focus {
        outline-color: #d93025;
    }
    </style>
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

        <div class="page-content"
            style="display: grid; grid-template-columns: 1fr 350px; gap: 24px; align-items: start;">

            <!-- ── LEFT: User Table ── -->
            <div class="left-col">
                <?php if ($formSuccess): ?>
                <div class="alert alert-success"
                    style="margin-bottom:20px; padding:12px; border-radius:8px; background:#e6f4ea; color:#1e7e34; border:1px solid #c3e6cb;">
                    <?= $formSuccess ?>
                </div>
                <?php endif; ?>
                <?php if ($formError): ?>
                <div class="alert alert-danger"
                    style="margin-bottom:20px; padding:12px; border-radius:8px; background:#fce8e6; color:#d93025; border:1px solid #f5c6cb;">
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
                                            <div
                                                style="width:40px; height:40px; border-radius:50%; background:var(--surface-variant); display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid var(--outline-variant)">
                                                <?php if ($u['profile_picture']): ?>
                                                <img src="../uploads/<?= htmlspecialchars($u['profile_picture']) ?>"
                                                    style="width:100%; height:100%; object-fit:cover">
                                                <?php else: ?>
                                                <span
                                                    style="font-weight:700; color:var(--primary)"><?= strtoupper(substr($u['name'], 0, 1)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600"><?= htmlspecialchars($u['name']) ?></div>
                                                <div style="font-size:0.75rem; color:var(--on-surface-variant)">Joined
                                                    <?= date('M j, Y', strtotime($u['created_at'])) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <div style="font-weight:500">Room <?= htmlspecialchars($u['room'] ?? '—') ?>
                                        </div>
                                        <div style="font-size:0.75rem; color:var(--on-surface-variant)">Ext.
                                            <?= htmlspecialchars($u['extension'] ?? '—') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge"
                                            style="background:rgba(201, 161, 74, 0.1); color:var(--primary)">
                                            <?= $u['order_count'] ?> orders
                                        </span>
                                    </td>
                                    <td style="text-align:right">
                                        <div style="display:flex; gap:8px; justify-content:flex-end">
                                            <a href="?edit=<?= $u['id'] ?>" class="btn btn-secondary btn-sm"
                                                style="padding:6px; min-width:unset">
                                                <span class="material-symbols-outlined"
                                                    style="font-size:18px">edit</span>
                                            </a>
                                            <a href="?delete=<?= $u['id'] ?>" class="btn btn-secondary btn-sm"
                                                style="padding:6px; min-width:unset; color:#d93025"
                                                onclick="return confirm('Delete this user?')">
                                                <span class="material-symbols-outlined"
                                                    style="font-size:18px">delete</span>
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
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
                        style="min-width:32px">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── RIGHT: Add / Edit Form ── -->
            <div class="right-col">
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-title">
                            <span class="material-symbols-outlined"><?= $editUser ? 'edit' : 'person_add' ?></span>
                            <?= $editUser ? 'Edit User' : 'Add New User' ?>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="userForm" novalidate
                        style="display:flex; flex-direction:column; gap:16px; padding:20px">

                        <input type="hidden" name="edit_id" value="<?= $editUser ? $editUser['id'] : 0 ?>">

                        <!-- Name -->
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" id="f-name" class="form-control" placeholder="John Doe"
                                value="<?= $editUser ? htmlspecialchars($editUser['name']) : '' ?>">
                            <div class="field-error" id="err-name">Name is required (min 2 characters).</div>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="text" name="email" id="f-email" class="form-control"
                                placeholder="john@example.com"
                                value="<?= $editUser ? htmlspecialchars($editUser['email']) : '' ?>">
                            <div class="field-error" id="err-email">Enter a valid email address.</div>
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label class="form-label"><?= $editUser ? 'New Password (optional)' : 'Password' ?></label>
                            <input type="password" name="password" id="f-password" class="form-control"
                                placeholder="<?= $editUser ? 'Leave blank to keep current' : 'Min 6 characters' ?>">
                            <div class="field-error" id="err-password">Password must be at least 6 characters.</div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" id="f-confirm" class="form-control"
                                placeholder="Repeat password">
                            <div class="field-error" id="err-confirm">Passwords do not match.</div>
                        </div>

                        <!-- Room & Extension -->
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px">
                            <div class="form-group">
                                <label class="form-label">Room</label>
                                <input type="text" name="room" id="f-room" class="form-control" placeholder="2010"
                                    value="<?= $editUser ? htmlspecialchars($editUser['room']) : '' ?>">
                                <div class="field-error" id="err-room">Invalid room format.</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Extension</label>
                                <input type="text" name="extension" id="f-ext" class="form-control" placeholder="5501"
                                    value="<?= $editUser ? htmlspecialchars($editUser['extension']) : '' ?>">
                                <div class="field-error" id="err-ext">Extension must be numeric.</div>
                            </div>
                        </div>

                        <!-- Profile Picture -->
                        <div class="form-group">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_picture" id="f-picture" class="form-control"
                                accept=".jpg,.jpeg,.png,.gif,.webp">
                            <div class="field-error" id="err-picture">Only JPG, PNG, GIF, WEBP under 2 MB.</div>
                            <?php if ($editUser && $editUser['profile_picture']): ?>
                            <div style="display:flex; align-items:center; gap:8px; margin-top:8px">
                                <img src="../uploads/<?= htmlspecialchars($editUser['profile_picture']) ?>"
                                    style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:1px solid var(--outline-variant)">
                                <input type="checkbox" name="remove_picture" id="remove_pic"
                                    style="width:16px; height:16px">
                                <label for="remove_pic"
                                    style="font-size:0.8rem; color:var(--on-surface-variant); cursor:pointer">
                                    Remove current photo
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Submit -->
                        <div style="display:flex; gap:10px; margin-top:8px">
                            <button type="submit" name="save_user" class="btn btn-primary" style="flex:1">
                                <?= $editUser ? 'Update User' : 'Create User' ?>
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
    <script>
    const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;

    // ── Helpers ───────────────────────────────────────────────────────────────
    function showError(errId, fieldId) {
        const errEl = document.getElementById(errId);
        if (errEl) errEl.style.display = 'block';
        if (fieldId) {
            const field = document.getElementById(fieldId);
            if (field) field.classList.add('is-invalid');
        }
    }

    function clearError(errId, fieldId) {
        const errEl = document.getElementById(errId);
        if (errEl) errEl.style.display = 'none';
        if (fieldId) {
            const field = document.getElementById(fieldId);
            if (field) field.classList.remove('is-invalid');
        }
    }

    function isValidEmail(val) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    }

    // ── Live clear-on-fix listeners ───────────────────────────────────────────
    document.getElementById('f-name').addEventListener('input', function() {
        if (this.value.trim().length >= 2) clearError('err-name', 'f-name');
    });
    document.getElementById('f-email').addEventListener('input', function() {
        if (isValidEmail(this.value.trim())) clearError('err-email', 'f-email');
    });
    document.getElementById('f-password').addEventListener('input', function() {
        if (this.value === '' || this.value.length >= 6) clearError('err-password', 'f-password');
        // re-check confirm match
        const confirm = document.getElementById('f-confirm').value;
        if (confirm === this.value) clearError('err-confirm', 'f-confirm');
    });
    document.getElementById('f-confirm').addEventListener('input', function() {
        const pass = document.getElementById('f-password').value;
        if (this.value === pass) clearError('err-confirm', 'f-confirm');
    });
    document.getElementById('f-room').addEventListener('input', function() {
        if (this.value === '' || /^[\w\-]{1,20}$/.test(this.value.trim())) clearError('err-room', 'f-room');
    });
    document.getElementById('f-ext').addEventListener('input', function() {
        if (this.value === '' || /^\d{1,10}$/.test(this.value.trim())) clearError('err-ext', 'f-ext');
    });
    document.getElementById('f-picture').addEventListener('change', function() {
        if (!this.files.length) return;
        const file = this.files[0];
        const allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        const ext = file.name.split('.').pop().toLowerCase();
        if (allowed.includes(ext) && file.size <= 2 * 1024 * 1024) {
            clearError('err-picture', 'f-picture');
        }
    });

    // ── Submit validation ─────────────────────────────────────────────────────
    document.getElementById('userForm').addEventListener('submit', function(e) {
        let valid = true;

        // Name
        const name = document.getElementById('f-name').value.trim();
        if (name.length < 2) {
            showError('err-name', 'f-name');
            valid = false;
        }

        // Email
        const email = document.getElementById('f-email').value.trim();
        if (!isValidEmail(email)) {
            showError('err-email', 'f-email');
            valid = false;
        }

        // Password
        const password = document.getElementById('f-password').value;
        const confirm = document.getElementById('f-confirm').value;

        if (!IS_EDIT && password === '') {
            // new user — password required
            document.getElementById('err-password').textContent = 'Password is required.';
            showError('err-password', 'f-password');
            valid = false;
        } else if (password !== '') {
            if (password.length < 6) {
                document.getElementById('err-password').textContent = 'Password must be at least 6 characters.';
                showError('err-password', 'f-password');
                valid = false;
            } else if (password !== confirm) {
                showError('err-confirm', 'f-confirm');
                valid = false;
            }
        }

        // Room (optional but must match pattern if filled)
        const room = document.getElementById('f-room').value.trim();
        if (room !== '' && !/^[\w\-]{1,20}$/.test(room)) {
            showError('err-room', 'f-room');
            valid = false;
        }

        // Extension (optional but must be numeric if filled)
        const ext = document.getElementById('f-ext').value.trim();
        if (ext !== '' && !/^\d{1,10}$/.test(ext)) {
            showError('err-ext', 'f-ext');
            valid = false;
        }

        // Profile picture (only validate if a file is chosen)
        const picInput = document.getElementById('f-picture');
        if (picInput.files.length > 0) {
            const file = picInput.files[0];
            const allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const fileExt = file.name.split('.').pop().toLowerCase();
            if (!allowed.includes(fileExt) || file.size > 2 * 1024 * 1024) {
                showError('err-picture', 'f-picture');
                valid = false;
            }
        }

        if (!valid) e.preventDefault();
    });
    </script>
</body>

</html>