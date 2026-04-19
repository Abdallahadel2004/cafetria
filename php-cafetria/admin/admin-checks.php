<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
require_once '../db.php';

// ── Filters ──
$dateFrom  = $_GET['date_from'] ?? '';
$dateTo    = $_GET['date_to']   ?? '';
$userFilter = (int)($_GET['user_id'] ?? 0);

// Validate dates
$errors = [];
if ($dateFrom !== '' && !DateTime::createFromFormat('Y-m-d', $dateFrom)) {
    $errors[] = 'Invalid "Date from" format.';
    $dateFrom = '';
}
if ($dateTo !== '' && !DateTime::createFromFormat('Y-m-d', $dateTo)) {
    $errors[] = 'Invalid "Date to" format.';
    $dateTo = '';
}
if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
    $errors[] = '"Date from" cannot be after "Date to".';
    $dateTo = '';
}

// Build WHERE
$where  = ["u.role = 'user'"];
$params = [];
if ($userFilter) { $where[] = 'u.id = ?'; $params[] = $userFilter; }
if ($dateFrom)   { $where[] = 'DATE(o.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)     { $where[] = 'DATE(o.created_at) <= ?'; $params[] = $dateTo; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Fetch users with their order totals (for the accordion)
$userStmt = $pdo->prepare("
    SELECT u.id, u.name, u.room_no, u.ext,
           COUNT(o.id)          AS order_count,
           COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total ELSE 0 END), 0) AS grand_total
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id AND o.status != 'cancelled'
    " . ($dateFrom || $dateTo || $userFilter ? str_replace("u.role = 'user'", "u.role = 'user'", $whereSQL) : "WHERE u.role = 'user'") . "
    GROUP BY u.id
    ORDER BY grand_total DESC
");
$userStmt->execute($params);
$userRows = $userStmt->fetchAll();

// Grand summary
$grandTotal  = array_sum(array_column($userRows, 'grand_total'));
$grandOrders = array_sum(array_column($userRows, 'order_count'));

// Fetch orders per user
function getUserOrders($pdo, $userId, $dateFrom, $dateTo) {
    $where  = ['o.user_id = ?', "o.status != 'cancelled'"];
    $params = [$userId];
    if ($dateFrom) { $where[] = 'DATE(o.created_at) >= ?'; $params[] = $dateFrom; }
    if ($dateTo)   { $where[] = 'DATE(o.created_at) <= ?'; $params[] = $dateTo; }
    $sql = "SELECT * FROM orders o WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getOrderItems($pdo, $orderId) {
    return $pdo->query("
        SELECT oi.*, p.name AS product_name, p.image
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = $orderId
    ")->fetchAll();
}

$allUsers = $pdo->query("SELECT id, name FROM users WHERE role='user' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Checks</title>
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

    /* Main */
    .main {
        margin-left: var(--sidebar);
        flex: 1;
        padding: 2rem 2.2rem;
    }

    .page-title {
        font-family: 'Cormorant Garamond', serif;
        font-weight: 300;
        font-size: 2rem;
        color: var(--cream);
        line-height: 1;
        margin-bottom: 1.5rem;
    }

    .page-title em {
        font-style: italic;
        color: var(--gold);
    }

    /* Filters */
    .filter-bar {
        display: flex;
        gap: .75rem;
        align-items: flex-end;
        flex-wrap: wrap;
        margin-bottom: 1.8rem;
        background: var(--surface);
        border: 1px solid var(--border);
        padding: 1.2rem 1.4rem;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: .35rem;
    }

    .filter-group label {
        font-size: .58rem;
        letter-spacing: .25em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, .6);
    }

    .filter-group input[type="date"],
    .filter-group select {
        background: rgba(255, 255, 255, .04);
        border: 1px solid var(--border);
        color: var(--cream);
        font-family: 'Jost', sans-serif;
        font-weight: 300;
        font-size: .82rem;
        padding: .5rem .8rem;
        outline: none;
        transition: border-color .2s;
        -webkit-appearance: none;
        color-scheme: dark;
    }

    .filter-group input:focus,
    .filter-group select:focus {
        border-color: rgba(201, 161, 74, .4);
    }

    .filter-group select option {
        background: var(--brown);
    }

    .filter-group input.invalid {
        border-color: rgba(224, 92, 92, .5);
    }

    .btn-filter {
        padding: .52rem 1.2rem;
        background: rgba(201, 161, 74, .1);
        border: 1px solid rgba(201, 161, 74, .3);
        color: var(--gold);
        font-family: 'Jost', sans-serif;
        font-size: .72rem;
        letter-spacing: .15em;
        text-transform: uppercase;
        cursor: pointer;
        transition: background .2s;
        white-space: nowrap;
    }

    .btn-filter:hover {
        background: rgba(201, 161, 74, .2);
    }

    .btn-clear {
        padding: .52rem .9rem;
        background: transparent;
        border: 1px solid var(--border);
        color: var(--muted);
        font-family: 'Jost', sans-serif;
        font-size: .72rem;
        letter-spacing: .12em;
        text-transform: uppercase;
        cursor: pointer;
        transition: all .2s;
        text-decoration: none;
        white-space: nowrap;
    }

    .btn-clear:hover {
        color: var(--cream);
        border-color: rgba(201, 161, 74, .2);
    }

    .alert-error {
        padding: .6rem .9rem;
        font-size: .78rem;
        background: rgba(224, 92, 92, .08);
        border: 1px solid rgba(224, 92, 92, .25);
        color: #e08080;
        margin-bottom: 1rem;
    }

    /* Summary cards */
    .summary-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.8rem;
    }

    .summary-card {
        background: var(--surface);
        border: 1px solid var(--border);
        padding: 1.2rem 1.4rem;
    }

    .summary-label {
        font-size: .6rem;
        letter-spacing: .25em;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: .5rem;
    }

    .summary-val {
        font-family: 'Cormorant Garamond', serif;
        font-weight: 300;
        font-size: 2rem;
        color: var(--cream);
        line-height: 1;
    }

    .summary-val span {
        font-size: .9rem;
        color: var(--muted);
        margin-left: .2rem;
    }

    /* User accordion */
    .user-block {
        background: var(--surface);
        border: 1px solid var(--border);
        margin-bottom: .6rem;
    }

    .user-row {
        display: grid;
        grid-template-columns: 1fr 100px 80px 120px 36px;
        align-items: center;
        gap: 1rem;
        padding: .9rem 1.2rem;
        cursor: pointer;
        transition: background .15s;
    }

    .user-row:hover {
        background: rgba(201, 161, 74, .03);
    }

    .user-row-name {
        color: var(--cream);
        font-size: .88rem;
    }

    .user-row-room {
        color: var(--muted);
        font-size: .78rem;
    }

    .user-row-orders {
        color: var(--muted);
        font-size: .78rem;
        text-align: center;
    }

    .user-row-total {
        color: var(--gold);
        font-size: .92rem;
        font-weight: 400;
        text-align: right;
    }

    .chevron {
        color: var(--muted);
        transition: transform .25s;
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .user-block.open .chevron {
        transform: rotate(180deg);
    }

    /* Order sub-rows */
    .orders-panel {
        display: none;
        border-top: 1px solid var(--border);
    }

    .user-block.open .orders-panel {
        display: block;
    }

    .order-sub-row {
        display: grid;
        grid-template-columns: 1fr 100px 36px;
        align-items: center;
        gap: 1rem;
        padding: .7rem 1.6rem;
        border-bottom: 1px solid rgba(201, 161, 74, .05);
        cursor: pointer;
        transition: background .15s;
    }

    .order-sub-row:last-child {
        border-bottom: none;
    }

    .order-sub-row:hover {
        background: rgba(201, 161, 74, .03);
    }

    .order-sub-date {
        font-size: .78rem;
        color: var(--muted);
    }

    .order-sub-total {
        font-size: .82rem;
        color: var(--gold);
        text-align: right;
    }

    .sub-chevron {
        color: var(--muted);
        transition: transform .25s;
        width: 14px;
        height: 14px;
        justify-self: center;
    }

    .order-sub-row.open .sub-chevron {
        transform: rotate(180deg);
    }

    /* Order items detail */
    .items-panel {
        display: none;
        background: rgba(201, 161, 74, .03);
        border-top: 1px solid rgba(201, 161, 74, .06);
        padding: 1rem 2rem;
    }

    .order-sub-row.open+.items-panel {
        display: block;
    }

    .items-grid {
        display: flex;
        flex-wrap: wrap;
        gap: .7rem;
        margin-bottom: .6rem;
    }

    .item-chip {
        display: flex;
        align-items: center;
        gap: .5rem;
        background: var(--surface);
        border: 1px solid var(--border);
        padding: .45rem .7rem;
        font-size: .78rem;
    }

    .item-chip-name {
        color: var(--cream);
    }

    .item-chip-meta {
        color: var(--muted);
        font-size: .68rem;
    }

    .order-notes-txt {
        font-size: .72rem;
        color: var(--muted);
        font-style: italic;
        margin-top: .4rem;
    }

    /* Table header */
    .tbl-header {
        display: grid;
        grid-template-columns: 1fr 100px 80px 120px 36px;
        gap: 1rem;
        padding: .5rem 1.2rem;
        margin-bottom: .3rem;
    }

    .tbl-header span {
        font-size: .58rem;
        letter-spacing: .25em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .empty {
        padding: 3rem;
        text-align: center;
        color: var(--muted);
        font-size: .82rem;
        background: var(--surface);
        border: 1px solid var(--border);
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
            <a href="admin-users.php" class="nav-link">
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
            <a href="admin-checks.php" class="nav-link active">
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
        <h1 class="page-title">Spending <em>Checks</em></h1>

        <!-- Filter errors -->
        <?php if ($errors): ?>
        <div class="alert-error"><?= implode('<br>', $errors) ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="GET" id="filterForm" novalidate>
            <div class="filter-bar">
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" id="f-from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" id="f-to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="filter-group">
                    <label>User</label>
                    <select name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userFilter == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">Apply</button>
                <a href="admin-checks.php" class="btn-clear">Clear</a>
            </div>
        </form>

        <!-- Summary -->
        <div class="summary-row">
            <div class="summary-card">
                <div class="summary-label">Total Revenue</div>
                <div class="summary-val"><?= number_format($grandTotal, 0) ?><span>EGP</span></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Orders</div>
                <div class="summary-val"><?= $grandOrders ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Users with Orders</div>
                <div class="summary-val"><?= count(array_filter($userRows, fn($r) => $r['order_count'] > 0)) ?></div>
            </div>
        </div>

        <!-- Table header -->
        <?php if ($userRows): ?>
        <div class="tbl-header">
            <span>User</span>
            <span>Room</span>
            <span style="text-align:center">Orders</span>
            <span style="text-align:right">Total Spent</span>
            <span></span>
        </div>

        <?php foreach ($userRows as $idx => $u): ?>
        <?php $userOrders = getUserOrders($pdo, $u['id'], $dateFrom, $dateTo); ?>
        <div class="user-block" id="ub-<?= $u['id'] ?>">
            <div class="user-row" onclick="toggleUser(<?= $u['id'] ?>)">
                <div>
                    <div class="user-row-name"><?= htmlspecialchars($u['name']) ?></div>
                    <?php if ($u['ext']): ?>
                    <div class="user-row-room">Ext. <?= htmlspecialchars($u['ext']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-row-room"><?= htmlspecialchars($u['room_no'] ?? '—') ?></div>
                <div class="user-row-orders"><?= $u['order_count'] ?></div>
                <div class="user-row-total"><?= number_format($u['grand_total'], 2) ?> EGP</div>
                <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9" />
                </svg>
            </div>

            <div class="orders-panel">
                <?php if ($userOrders): ?>
                <?php foreach ($userOrders as $oi => $order): ?>
                <?php $items = getOrderItems($pdo, $order['id']); ?>
                <div class="order-sub-row" id="osr-<?= $order['id'] ?>" onclick="toggleOrder(<?= $order['id'] ?>)">
                    <div class="order-sub-date">
                        #<?= $order['id'] ?> &mdash; <?= date('Y/m/d H:i', strtotime($order['created_at'])) ?>
                    </div>
                    <div class="order-sub-total"><?= number_format($order['total'], 2) ?> EGP</div>
                    <svg class="sub-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </div>
                <div class="items-panel" id="ip-<?= $order['id'] ?>">
                    <div class="items-grid">
                        <?php foreach ($items as $item): ?>
                        <div class="item-chip">
                            <div>
                                <div class="item-chip-name"><?= htmlspecialchars($item['product_name'] ?? 'Deleted') ?>
                                </div>
                                <div class="item-chip-meta">×<?= $item['quantity'] ?> —
                                    <?= number_format($item['unit_price'] * $item['quantity'], 2) ?> EGP</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($order['notes']): ?>
                    <div class="order-notes-txt">📝 <?= htmlspecialchars($order['notes']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div style="padding:1rem 1.6rem;font-size:.78rem;color:var(--muted);">No orders in this period.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php else: ?>
        <div class="empty">No data found for the selected filters.</div>
        <?php endif; ?>

    </main>

    <script>
    // User accordion
    function toggleUser(id) {
        const block = document.getElementById('ub-' + id);
        block.classList.toggle('open');
    }

    // Order detail toggle
    function toggleOrder(id) {
        const row = document.getElementById('osr-' + id);
        const panel = document.getElementById('ip-' + id);
        const open = row.classList.toggle('open');
        panel.style.display = open ? 'block' : 'none';
    }

    // Filter validation
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        const from = document.getElementById('f-from').value;
        const to = document.getElementById('f-to').value;
        let ok = true;

        document.getElementById('f-from').classList.remove('invalid');
        document.getElementById('f-to').classList.remove('invalid');

        if (from && to && from > to) {
            alert('"Date from" cannot be after "Date to".');
            document.getElementById('f-from').classList.add('invalid');
            document.getElementById('f-to').classList.add('invalid');
            ok = false;
        }
        if (!ok) e.preventDefault();
    });
    </script>
</body>

</html>