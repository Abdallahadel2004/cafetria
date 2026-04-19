<?php
/**
 * user-orders.php
 *
 * PHP responsibilities:
 *   - Auth guard (must be logged-in user; admins are redirected to their dashboard)
 *   - Read optional ?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD filters
 *   - Query the logged-in user's orders (newest first), filtered by date range
 *   - Render the table with an expandable detail row per order
 *
 * The expandable row breaks the items_summary string ("2x Coffee, 1x Tea")
 * into a sub-table. We don't have per-item pricing here, so the sub-table
 * shows item name + quantity only.
 *
 * JS responsibilities (loaded from ./user.js):
 *   - toggleRow() — show/hide expandable row (already in ../script.js)
 *   - cancelMyOrder() — POSTs to api/order.php with action: cancel
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if (($_SESSION['role'] ?? 'user') === 'admin') {
    header('Location: ../admin/admin/admin-dashboard.php');
    exit;
}

require_once __DIR__ . '/db.php';

$userId   = (int)$_SESSION['user_id'];
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'User');

// ── Sanitize date filters ────────────────────────────────────────────────
function clean_date(?string $s): ?string {
    if (!$s) return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

$dateFrom = clean_date($_GET['date_from'] ?? null);
$dateTo   = clean_date($_GET['date_to']   ?? null);

// ── Build query ──────────────────────────────────────────────────────────
$sql = "
    SELECT o.id, o.created_at, o.total, o.status, o.room, o.notes,
           (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ')
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = o.id) AS items_summary
    FROM   orders o
    WHERE  o.user_id = :uid
";
$params = [':uid' => $userId];

if ($dateFrom) {
    $sql .= " AND DATE(o.created_at) >= :df";
    $params[':df'] = $dateFrom;
}
if ($dateTo) {
    $sql .= " AND DATE(o.created_at) <= :dt";
    $params[':dt'] = $dateTo;
}
$sql .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// ── Fetch detailed items for the expandable rows ─────────────────────────
$orderIds = array_column($orders, 'id');
$itemsMap = [];
if ($orderIds) {
    $place = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare("
        SELECT oi.order_id, oi.quantity AS qty, p.name
        FROM   order_items oi
        JOIN   products p ON oi.product_id = p.id
        WHERE  oi.order_id IN ($place)
    ");
    $itemStmt->execute($orderIds);
    foreach ($itemStmt->fetchAll() as $it) {
        $itemsMap[$it['order_id']][] = $it;
    }
}


function status_badge_class(string $status): string {
    $status = strtolower($status);
    $map = [
        'processing'       => 'badge-processing',
        'out_for_delivery' => 'badge-processing',
        'done'             => 'badge-delivered',
        'cancelled'        => 'badge-unavailable',
    ];
    return $map[$status] ?? 'badge-processing';
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <!-- ─── User Top Navbar ─────────────────────── -->
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <span class="material-symbols-outlined" style="color: var(--primary);">local_cafe</span>
                <span>Cafetria</span>
            </div>
            <ul class="nav-links">
                <li><a href="user-home.php">Home</a></li>
                <li><a href="user-orders.php" class="active">My Orders</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
            <div class="user-profile">
                <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?= urlencode($userName) ?>" alt="User" class="avatar">
                <div class="user-info">
                    <p class="user-name"><?= $userName ?></p>
                    <p class="user-role"><?= ($_SESSION['role'] ?? 'user') === 'admin' ? 'ADMIN' : 'CUSTOMER' ?></p>
                </div>
            </div>
        </div>
    </nav>

    <!-- ─── Main Content ────────────────────────── -->
    <main class="container" style="padding-top: var(--spacing-lg); padding-bottom: var(--spacing-xl);">
        <header class="page-header">
            <div>
                <h1>My <span class="highlight">Orders</span></h1>
                <p>View your order history and track statuses.</p>
            </div>
        </header>

        <!-- Filter Bar (GET form so the URL is shareable / bookmarkable) -->
        <form class="filter-bar" method="GET" action="user-orders.php">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" class="form-control" name="date_from"
                       value="<?= htmlspecialchars($dateFrom ?? '') ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" class="form-control" name="date_to"
                       value="<?= htmlspecialchars($dateTo ?? '') ?>">
            </div>
            <button class="btn btn-secondary" type="submit">
                <span class="material-symbols-outlined" style="font-size:1rem;">filter_alt</span>
                Filter
            </button>
            <?php if ($dateFrom || $dateTo): ?>
                <a class="btn btn-secondary" href="user-orders.php">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Orders Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:50px;"></th>
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
                            <td colspan="7" style="text-align:center; padding:2rem; color: var(--on-surface-variant);">
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
                            <td class="expand-btn" onclick="toggleRow('<?= $rowId ?>')">+</td>
                            <td><?= htmlspecialchars($o['created_at']) ?></td>
                            <td><?= htmlspecialchars($o['items_summary']) ?></td>
                            <td>Room <?= htmlspecialchars($o['room']) ?></td>
                            <td>EGP <?= number_format((float)$o['total']) ?></td>
                            <td>
                                <span class="badge <?= status_badge_class($status) ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (strtolower($status) === 'processing'): ?>
                                    <button class="btn btn-danger btn-sm"
                                            onclick="cancelMyOrder(<?= (int)$o['id'] ?>, this)">
                                        Cancel
                                    </button>
                                <?php else: ?>
                                    <span style="color: var(--on-surface-variant); font-size:0.8125rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr id="<?= $rowId ?>" class="expand-row">
                            <td colspan="7">
                                <div style="padding: 1rem;">
                                    <table class="sub-table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $it): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($it['name']) ?></td>
                                                    <td><?= (int)$it['qty'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php if ($notes !== ''): ?>
                                    <div class="notes-block" style="margin-top: 0.75rem;">
                                        <div class="notes-label">
                                            <span class="material-symbols-outlined" style="font-size:0.875rem;">edit_note</span>
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
    <!-- Toast Notifications Container -->
    <div id="toast-container"></div>

    <!-- Custom Confirmation Modal -->
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

    <script src="../script.js"></script>
    <script src="user.js"></script>
</body>
</html>
