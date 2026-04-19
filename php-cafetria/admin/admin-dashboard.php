<?php
/**
 * admin-dashboard.php
 *
 * PHP responsibilities:
 *   - Auth guard
 *   - Query live stats from DB
 *   - Render the live orders table rows
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../db.php'; // Updated path to the new central db.php

$today = date('Y-m-d');

// ── Stats ─────────────────────────────────────────────────────────────────
$totalOrders = (int) $pdo->query("
    SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'
")->fetchColumn();

$processing = (int) $pdo->query("
    SELECT COUNT(*) FROM orders
    WHERE status = 'Processing' AND DATE(created_at) = '$today'
")->fetchColumn();

$revenue = (float) $pdo->query("
    SELECT COALESCE(SUM(total), 0) FROM orders
    WHERE status != 'Cancelled' AND DATE(created_at) = '$today'
")->fetchColumn();

$activeProducts = (int) $pdo->query("
    SELECT COUNT(*) FROM products WHERE status = 'Available'
")->fetchColumn();

$unavailableProducts = (int) $pdo->query("
    SELECT COUNT(*) FROM products WHERE status = 'Unavailable'
")->fetchColumn();

// ── Live processing orders (max 10, newest first) ─────────────────────────
$liveOrders = $pdo->query("
    SELECT o.id, o.created_at, u.name AS customer,
           o.items_summary, u.room, u.extension AS ext, o.total
    FROM   orders o
    JOIN   users  u ON u.id = o.user_id
    WHERE  o.status = 'Processing'
    ORDER  BY o.created_at DESC
    LIMIT  10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Top products (for AI context) ─────────────────────────────────────────
$topProducts = $pdo->query("
    SELECT name FROM products ORDER BY total_orders DESC LIMIT 3
")->fetchAll(PDO::FETCH_COLUMN);

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $w = floor($diff->d / 7);
    $d = $diff->d - ($w * 7);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'min',
        's' => 'sec',
    );
    $values = array(
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => (int)$w,
        'd' => (int)$d,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    );
    foreach ($string as $k => &$v) {
        if ($values[$k]) {
            $v = $values[$k] . ' ' . $v . ($values[$k] > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php include '_sidebar.php'; ?>

    <main class="main">

        <!-- Topbar -->
        <div class="topbar">
            <h2>Dashboard</h2>
            <div class="topbar-actions">
                <a href="admin-dashboard.php" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-outlined">refresh</span>Refresh
                </a>
            </div>
        </div>

        <div class="page-content">

            <!-- ── Stat Cards ─────────────────────────────── -->
            <div class="stats-grid">

                <div class="stat-card">
                    <div class="stat-icon"><span class="material-symbols-outlined">shopping_bag</span></div>
                    <div class="stat-value"><?= $totalOrders ?></div>
                    <div class="stat-label">Total Orders Today</div>
                    <span class="stat-change up">▲ live count</span>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><span class="material-symbols-outlined">pending_actions</span></div>
                    <div class="stat-value"><?= $processing ?></div>
                    <div class="stat-label">Orders Processing</div>
                    <span class="stat-change <?= $processing > 5 ? 'down' : 'up' ?>">
                        <?= $processing > 5 ? 'High queue' : 'Under control' ?>
                    </span>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><span class="material-symbols-outlined">payments</span></div>
                    <div class="stat-value">EGP <?= number_format($revenue) ?></div>
                    <div class="stat-label">Revenue Today</div>
                    <span class="stat-change up">▲ active</span>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><span class="material-symbols-outlined">restaurant_menu</span></div>
                    <div class="stat-value"><?= $activeProducts ?></div>
                    <div class="stat-label">Active Products</div>
                    <span class="stat-change <?= $unavailableProducts > 0 ? 'down' : 'up' ?>">
                        <?= $unavailableProducts ?> unavailable
                    </span>
                </div>

            </div>

            <!-- ── Live Incoming Orders ────────────────────── -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title">
                        <span class="live-dot"></span>
                        <span class="material-symbols-outlined">receipt_long</span>
                        Live Incoming Orders
                    </div>
                    <a href="admin-orders.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Room / Ext</th>
                                <th>Total</th>
                                <th>Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($liveOrders)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:2rem;color:var(--on-surface-variant)">
                                        No pending orders right now
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($liveOrders as $o): ?>
                                    <tr>
                                        <td><strong style="color:var(--primary)">#<?= htmlspecialchars($o['id']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($o['customer']) ?></td>
                                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                            <?= htmlspecialchars($o['items_summary']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($o['room']) ?> / <em><?= htmlspecialchars($o['ext']) ?></em>
                                        </td>
                                        <td><strong>EGP <?= number_format($o['total']) ?></strong></td>
                                        <td style="font-size:0.78rem">
                                            <div style="font-weight:600;color:var(--primary)"><?= time_elapsed_string($o['created_at']) ?></div>
                                            <div style="font-size:0.7rem;opacity:0.6"><?= date('g:i A', strtotime($o['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <!-- AJAX deliver — no page reload -->
                                            <button class="btn btn-primary btn-sm"
                                                onclick="deliverOrder(<?= (int) $o['id'] ?>, this)">
                                                <span class="material-symbols-outlined">check</span>Deliver
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /page-content -->
    </main>

    <script src="admin.js"></script>
</body>

</html>