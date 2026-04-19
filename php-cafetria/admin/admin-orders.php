<?php
/**
 * admin-orders.php
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}

require_once __DIR__ . '/../db.php';

$allowedStatuses = ['Processing', 'Delivered', 'Cancelled'];
$filterStatus    = $_GET['status'] ?? '';
if ($filterStatus && !in_array($filterStatus, $allowedStatuses)) {
    $filterStatus = '';
}

$whereClause = $filterStatus
    ? "WHERE o.status = " . $pdo->quote($filterStatus)
    : "";

$orders = $pdo->query("
    SELECT o.id, o.created_at, u.name AS customer,
           o.items_summary, u.room, u.extension AS ext,
           o.total, o.status
    FROM   orders o
    JOIN   users  u ON u.id = o.user_id
    $whereClause
    ORDER  BY o.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$activePage = 'orders';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Orders</title>
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
        <div class="topbar">
            <h2>Orders</h2>
            <div class="topbar-actions">
                <div class="search-wrap">
                    <span class="material-symbols-outlined search-icon">search</span>
                    <input class="search-input" type="text" id="orders-search" placeholder="Search orders..."
                        oninput="filterOrdersTable()">
                </div>
                <select class="filter-select" onchange="location='admin-orders.php?status='+this.value">
                    <option value="" <?= !$filterStatus ? 'selected' : '' ?>>All Statuses</option>
                    <option value="Processing" <?= $filterStatus==='Processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="Delivered" <?= $filterStatus==='Delivered'  ? 'selected' : '' ?>>Delivered</option>
                    <option value="Cancelled" <?= $filterStatus==='Cancelled'  ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <div class="view-toggle">
                    <button class="view-btn active" id="view-table-btn" onclick="setOrderView('table')">
                        <span class="material-symbols-outlined">table_rows</span>
                    </button>
                    <button class="view-btn" id="view-card-btn" onclick="setOrderView('card')">
                        <span class="material-symbols-outlined">grid_view</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="page-content">
            <div class="section-card" id="orders-table-view">
                <div class="section-header">
                    <div class="section-title">
                        <span class="material-symbols-outlined">receipt_long</span>
                        <?= $filterStatus ?: 'All' ?> Orders
                    </div>
                    <span class="badge badge-processing"><?= count($orders) ?> orders</span>
                </div>
                <div class="table-wrap">
                    <table id="orders-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date & Time</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Room</th>
                                <th>Ext</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="orders-table-body">
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center;padding:2rem;color:var(--on-surface-variant)">
                                    No orders found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                            <tr data-status="<?= htmlspecialchars($o['status']) ?>"
                                data-search="<?= strtolower(htmlspecialchars($o['customer'].' '.$o['items_summary'])) ?>">
                                <td><strong style="color:var(--primary)">#<?= htmlspecialchars($o['id']) ?></strong>
                                </td>
                                <td style="font-size:0.78rem"><?= htmlspecialchars($o['created_at']) ?></td>
                                <td><?= htmlspecialchars($o['customer']) ?></td>
                                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <?= htmlspecialchars($o['items_summary']) ?></td>
                                <td><?= htmlspecialchars($o['room']) ?></td>
                                <td><?= htmlspecialchars($o['ext']) ?></td>
                                <td><strong>EGP <?= number_format($o['total']) ?></strong></td>
                                <td><span
                                        class="badge badge-<?= strtolower(htmlspecialchars($o['status'])) ?>"><?= htmlspecialchars($o['status']) ?></span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <?php if ($o['status'] === 'Processing'): ?>
                                        <button class="btn btn-primary btn-sm"
                                            onclick="deliverOrder(<?= (int)$o['id'] ?>, this)">
                                            <span class="material-symbols-outlined">check</span>
                                        </button>
                                        <button class="btn btn-danger btn-sm"
                                            onclick="cancelOrder(<?= (int)$o['id'] ?>, this)">
                                            <span class="material-symbols-outlined">close</span>
                                        </button>
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

            <div id="orders-card-view" style="display:none">
                <div class="orders-grid">
                    <?php foreach ($orders as $o): ?>
                    <div class="order-card" data-status="<?= htmlspecialchars($o['status']) ?>"
                        data-search="<?= strtolower(htmlspecialchars($o['customer'].' '.$o['items_summary'])) ?>">
                        <div class="order-card-header">
                            <span class="order-number">#<?= htmlspecialchars($o['id']) ?></span>
                            <span
                                class="badge badge-<?= strtolower(htmlspecialchars($o['status'])) ?>"><?= htmlspecialchars($o['status']) ?></span>
                        </div>
                        <div class="order-card-body">
                            <div class="order-meta"><span
                                    class="material-symbols-outlined">person</span><?= htmlspecialchars($o['customer']) ?>
                            </div>
                            <div class="order-meta"><span class="material-symbols-outlined">meeting_room</span>Room
                                <?= htmlspecialchars($o['room']) ?> · Ext. <?= htmlspecialchars($o['ext']) ?></div>
                            <div class="order-meta"><span
                                    class="material-symbols-outlined">schedule</span><?= htmlspecialchars($o['created_at']) ?>
                            </div>
                            <div class="order-items"><?= htmlspecialchars($o['items_summary']) ?></div>
                            <div class="order-total">
                                <span>Total</span>
                                <span style="color:var(--primary)">EGP <?= number_format($o['total']) ?></span>
                            </div>
                            <?php if ($o['status'] === 'Processing'): ?>
                            <div style="display:flex;gap:8px;margin-top:0.75rem">
                                <button class="btn btn-primary btn-sm" style="flex:1"
                                    onclick="deliverOrder(<?= (int)$o['id'] ?>, this)">
                                    <span class="material-symbols-outlined">check</span>Deliver
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="cancelOrder(<?= (int)$o['id'] ?>, this)">
                                    <span class="material-symbols-outlined">close</span>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="admin.js"></script>
</body>

</html>