<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); exit;
}
if (($_SESSION['role'] ?? 'user') === 'admin') {
    header('Location: ../admin/admin-dashboard.php'); exit;
}

require_once '../db.php';

$userId   = (int)$_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['name'] ?? 'User');

// ── Date filters ─────────────────────────────────────────────────────
function clean_date(?string $s): ?string {
    if (!$s) return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
$dateFrom = clean_date($_GET['date_from'] ?? null);
$dateTo   = clean_date($_GET['date_to']   ?? null);

// Date validation
$dateError = '';
if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
    $dateError = '"Date from" cannot be after "Date to".';
    $dateTo = null;
}

// ── Query orders ─────────────────────────────────────────────────────
$sql    = "SELECT o.id, o.created_at, o.total, o.status, o.room, o.notes,
                  (SELECT GROUP_CONCAT(CONCAT(oi.quantity,'x ',p.name) SEPARATOR ', ')
                   FROM order_items oi
                   JOIN products p ON oi.product_id = p.id
                   WHERE oi.order_id = o.id) AS items_summary
           FROM orders o
           WHERE o.user_id = ?";
$params = [$userId];

if ($dateFrom) { $sql .= " AND DATE(o.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $sql .= " AND DATE(o.created_at) <= ?"; $params[] = $dateTo; }
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// ── Per-order items for expandable rows ──────────────────────────────
$orderIds = array_column($orders, 'id');
$itemsMap = [];
if ($orderIds) {
    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare("
        SELECT oi.order_id, oi.quantity AS qty, oi.unit_price, p.name
        FROM   order_items oi
        JOIN   products p ON oi.product_id = p.id
        WHERE  oi.order_id IN ($in)
    ");
    $itemStmt->execute($orderIds);
    foreach ($itemStmt->fetchAll() as $it) {
        $itemsMap[$it['order_id']][] = $it;
    }
}

// ── User profile picture ──────────────────────────────────────────────
$myRow   = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
$myRow->execute([$userId]);
$myPhoto = $myRow->fetchColumn() ?: '';

function status_badge_class(string $status): string {
    return match(strtolower($status)) {
        'processing'       => 'badge-processing',
        'out_for_delivery' => 'badge-processing',
        'done'             => 'badge-delivered',
        'cancelled'        => 'badge-cancelled',
        default            => 'badge-processing',
    };
}
function status_label(string $status): string {
    return match(strtolower($status)) {
        'processing'       => 'Processing',
        'out_for_delivery' => 'Out for Delivery',
        'done'             => 'Done',
        'cancelled'        => 'Cancelled',
        default            => ucfirst($status),
    };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria | My Orders</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <style>
    /* ── Page-specific overrides ── */
    body {
        display: block;
    }

    .table-container {
        background: var(--surface-lowest);
        border: 1px solid #e8c9b8;
        border-radius: 1.25rem;
        overflow: hidden;
    }
    </style>
</head>

<body>

    <!-- ─── Navbar ─────────────────────────────── -->
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <span class="material-symbols-outlined" style="color:var(--primary)">local_cafe</span>
                <span>Cafetria</span>
            </div>
            <ul class="nav-links">
                <li><a href="user-home.php">Home</a></li>
                <li><a href="user-orders.php" class="active">My Orders</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
            <div class="user-profile">
                <?php if ($myPhoto && file_exists('../uploads/' . $myPhoto)): ?>
                <img src="../uploads/<?= htmlspecialchars($myPhoto) ?>" alt="<?= $userName ?>" class="avatar">
                <?php else: ?>
                <div class="avatar"
                    style="display:flex;align-items:center;justify-content:center;background:var(--primary-container);color:#fff;font-weight:700;font-size:1rem;">
                    <?= mb_strtoupper(mb_substr($userName, 0, 1)) ?>
                </div>
                <?php endif; ?>
                <div class="user-info">
                    <p class="user-name"><?= $userName ?></p>
                    <p class="user-role">CUSTOMER</p>
                </div>
            </div>
        </div>
    </nav>

    <!-- ─── Main ───────────────────────────────── -->
    <main class="container" style="padding-top:var(--spacing-lg);padding-bottom:var(--spacing-xl);">

        <div class="page-header">
            <h1>My <span class="highlight">Orders</span></h1>
            <p>View your order history and track statuses.</p>
        </div>

        <!-- Date filter error -->
        <?php if ($dateError): ?>
        <div
            style="background:#fde8e9;border:1px solid #f5c9c9;color:var(--error);padding:.65rem 1rem;border-radius:.75rem;font-size:.82rem;margin-bottom:1rem;">
            <?= htmlspecialchars($dateError) ?>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <form class="filter-bar" method="GET" action="user-orders.php" id="filterForm" novalidate>
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" class="form-control" name="date_from" id="f-from"
                    value="<?= htmlspecialchars($dateFrom ?? '') ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" class="form-control" name="date_to" id="f-to"
                    value="<?= htmlspecialchars($dateTo ?? '') ?>">
            </div>
            <button class="btn btn-secondary" type="submit" style="align-self:flex-end;">
                <span class="material-symbols-outlined" style="font-size:1rem;">filter_alt</span>
                Filter
            </button>
            <?php if ($dateFrom || $dateTo): ?>
            <a class="btn btn-secondary" href="user-orders.php" style="align-self:flex-end;">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Orders Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Order Date</th>
                        <th>Items</th>
                        <th>Room</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:2.5rem;color:var(--on-surface-variant);">
                            You have no orders<?= ($dateFrom || $dateTo) ? ' in this date range' : ' yet' ?>.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $o):
                            $rowId  = 'order-' . (int)$o['id'];
                            $items  = $itemsMap[$o['id']] ?? [];
                            $notes  = trim((string)$o['notes']);
                            $status = (string)$o['status'];
                        ?>
                    <tr id="row-<?= (int)$o['id'] ?>">
                        <td class="expand-btn" onclick="toggleRow('<?= $rowId ?>')" id="btn-<?= $rowId ?>">+</td>
                        <td><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?></td>
                        <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($o['items_summary'] ?? '—') ?>
                        </td>
                        <td>Room <?= htmlspecialchars($o['room']) ?></td>
                        <td style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;">
                            EGP <?= number_format((float)$o['total']) ?>
                        </td>
                        <td>
                            <span class="badge <?= status_badge_class($status) ?>">
                                <?= status_label($status) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (strtolower($status) === 'processing'): ?>
                            <button class="btn btn-danger btn-sm" onclick="cancelMyOrder(<?= (int)$o['id'] ?>, this)">
                                Cancel
                            </button>
                            <?php else: ?>
                            <span style="color:var(--on-surface-variant);font-size:.8125rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Expandable detail row -->
                    <tr id="<?= $rowId ?>" class="expand-row">
                        <td colspan="7">
                            <div style="padding:1rem 1.25rem;">
                                <table class="sub-table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Qty</th>
                                            <th>Unit Price</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($it['name']) ?></td>
                                            <td><?= (int)$it['qty'] ?></td>
                                            <td><?= number_format((float)$it['unit_price'], 2) ?> EGP</td>
                                            <td style="font-weight:600;">
                                                <?= number_format($it['unit_price'] * $it['qty'], 2) ?> EGP</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if ($notes !== ''): ?>
                                <div class="notes-block" style="margin-top:.75rem;">
                                    <div class="notes-label">
                                        <span class="material-symbols-outlined"
                                            style="font-size:.875rem;">edit_note</span>
                                        Notes
                                    </div>
                                    <p class="notes-text">"<?= htmlspecialchars($notes) ?>"</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Toast container (bottom-center for orders page — no cart panel here) -->
    <div id="toast-container"
        style="position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;gap:.5rem;min-width:300px;pointer-events:none;">
    </div>

    <!-- Confirm Modal -->
    <div id="confirm-modal-overlay">
        <div class="confirm-modal">
            <div class="confirm-modal-icon">
                <span class="material-symbols-outlined">help_center</span>
            </div>
            <h3 id="confirm-title">Confirm Action</h3>
            <p id="confirm-message">Are you sure you want to proceed?</p>
            <div class="confirm-modal-actions">
                <button class="btn btn-secondary" id="confirm-cancel-btn">No, Cancel</button>
                <button class="btn btn-primary" id="confirm-ok-btn">Yes, Confirm</button>
            </div>
        </div>
    </div>

    <script>
    // Toggle expandable row
    function toggleRow(id) {
        const row = document.getElementById(id);
        const btn = document.getElementById('btn-' + id);
        if (!row) return;
        const open = row.classList.toggle('open');
        if (btn) btn.textContent = open ? '−' : '+';
    }

    // Client-side date filter validation
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        const from = document.getElementById('f-from').value;
        const to = document.getElementById('f-to').value;
        if (from && to && from > to) {
            alert('"Date from" cannot be after "Date to".');
            e.preventDefault();
        }
    });
    </script>
    <script src="user.js"></script>
</body>

</html>