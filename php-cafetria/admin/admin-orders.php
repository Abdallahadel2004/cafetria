<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
require_once '../db.php';

// ── Filters ──
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$statusF  = $_GET['status']    ?? '';

$where  = ["1=1"];
$params = [];

if ($dateFrom) { $where[] = "DATE(o.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "DATE(o.created_at) <= ?"; $params[] = $dateTo; }
if ($statusF)  { $where[] = "o.status = ?"; $params[] = $statusF; }

$whereSQL = implode(' AND ', $where);

// Fetch orders
$orders = $pdo->prepare("
    SELECT o.*, u.name AS customer, u.extension AS u_ext
    FROM   orders o
    JOIN   users  u ON u.id = o.user_id
    WHERE  $whereSQL
    ORDER  BY o.created_at DESC
");
$orders->execute($params);
$ordersList = $orders->fetchAll(PDO::FETCH_ASSOC);

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
    
    // Map the diff properties to our string keys
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

$activePage = 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Orders History</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .order-items-list {
            font-size: 0.85rem;
            color: var(--on-surface-variant);
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>

<?php include '_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <h2>Orders History</h2>
        <div class="topbar-actions">
             <div class="search-box" style="position:relative">
                <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--on-surface-variant); font-size:20px">search</span>
                <input type="text" id="orderSearch" class="form-control" placeholder="Search orders..." style="padding-left:40px; width:250px" oninput="filterOrders()">
             </div>
        </div>
    </div>

    <div class="page-content">
        
        <!-- Filters Card -->
        <div class="section-card" style="margin-bottom:24px">
            <form method="GET" style="display:flex; gap:16px; align-items:flex-end; padding:20px">
                <div class="form-group" style="flex:1">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Processing" <?= $statusF==='Processing'?'selected':'' ?>>Processing</option>
                        <option value="Out for delivery" <?= $statusF==='Out for delivery'?'selected':'' ?>>Out for delivery</option>
                        <option value="Delivered" <?= $statusF==='Delivered'?'selected':'' ?>>Delivered</option>
                        <option value="Cancelled" <?= $statusF==='Cancelled'?'selected':'' ?>>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="height:44px">Filter</button>
                <a href="admin-orders.php" class="btn btn-secondary" style="height:44px; display:flex; align-items:center">Reset</a>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <span class="material-symbols-outlined">history</span>
                    All Orders
                </div>
                <span class="badge badge-category"><?= count($ordersList) ?> Total</span>
            </div>
            <div class="table-wrap">
                <table id="ordersTable">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Location</th>
                            <th>Total</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ordersList)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:40px; color:var(--on-surface-variant)">No orders found</td></tr>
                        <?php else: ?>
                            <?php foreach ($ordersList as $o): ?>
                                <tr data-search="<?= strtolower(htmlspecialchars($o['customer'] . ' ' . $o['items_summary'])) ?>">
                                    <td><strong style="color:var(--primary)">#<?= $o['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($o['customer']) ?></td>
                                    <td><div class="order-items-list" title="<?= htmlspecialchars($o['items_summary']) ?>"><?= htmlspecialchars($o['items_summary'] ?: '—') ?></div></td>
                                    <td>
                                        <div style="font-weight:600">Room <?= htmlspecialchars($o['room']) ?></div>
                                        <div style="font-size:0.7rem; opacity:0.6">Ext. <?= htmlspecialchars($o['u_ext']) ?></div>
                                    </td>
                                    <td><strong><?= number_format($o['total'], 2) ?> EGP</strong></td>
                                    <td>
                                        <div style="font-weight:600; color:var(--primary)"><?= time_elapsed_string($o['created_at']) ?></div>
                                        <div style="font-size:0.7rem; opacity:0.6"><?= date('M j, g:i A', strtotime($o['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                            $s = strtolower(str_replace(' ', '-', $o['status']));
                                            $badgeClass = 'badge-available'; // default
                                            if ($s === 'processing') $badgeClass = 'badge-available';
                                            if ($s === 'delivered') $badgeClass = 'badge-available';
                                            if ($s === 'cancelled') $badgeClass = 'badge-unavailable';
                                            if ($s === 'out-for-delivery') $badgeClass = 'badge-category';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= $o['status'] ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px">
                                            <?php if ($o['status'] === 'Processing'): ?>
                                                <button class="btn btn-primary btn-sm" onclick="deliverOrder(<?= $o['id'] ?>, this)">Deliver</button>
                                                <button class="btn btn-danger btn-sm" onclick="cancelOrder(<?= $o['id'] ?>, this)">Cancel</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="admin.js"></script>
<script>
function filterOrders() {
    const q = document.getElementById('orderSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#ordersTable tbody tr');
    rows.forEach(row => {
        if (!row.dataset.search) return;
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
}
</script>
</body>
</html>