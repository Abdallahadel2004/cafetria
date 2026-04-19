<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
require_once '../db.php';

// Stats
$totalOrders   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalRevenue  = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();

// Live incoming orders (processing)
$liveOrders = $pdo->query("
    SELECT o.id, o.created_at, o.total, o.room, o.notes,
           u.name AS user_name, u.ext
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.status = 'processing'
    ORDER BY o.created_at DESC
")->fetchAll();

// Recent all orders
$recentOrders = $pdo->query("
    SELECT o.id, o.created_at, o.total, o.status, o.room,
           u.name AS user_name
    FROM orders o
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Dashboard</title>
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
        --gold-d: #a07c32;
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

    /* ── Sidebar ── */
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

    /* ── Main ── */
    .main {
        margin-left: var(--sidebar);
        flex: 1;
        padding: 2rem 2.2rem;
        min-height: 100vh;
    }

    /* ── Page header ── */
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
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

    .page-date {
        font-size: .72rem;
        color: var(--muted);
        letter-spacing: .05em;
    }

    /* ── Stats grid ── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--surface);
        border: 1px solid var(--border);
        padding: 1.4rem 1.5rem;
        position: relative;
        overflow: hidden;
        transition: border-color .2s;
    }

    .stat-card:hover {
        border-color: rgba(201, 161, 74, .3);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(to right, transparent, var(--gold), transparent);
        opacity: 0;
        transition: opacity .2s;
    }

    .stat-card:hover::before {
        opacity: 1;
    }

    .stat-label {
        font-size: .62rem;
        letter-spacing: .3em;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: .6rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .stat-label svg {
        width: 13px;
        height: 13px;
        color: var(--gold);
        opacity: .6;
    }

    .stat-value {
        font-family: 'Cormorant Garamond', serif;
        font-weight: 300;
        font-size: 2.4rem;
        color: var(--cream);
        line-height: 1;
    }

    .stat-value span {
        font-size: 1rem;
        color: var(--muted);
        margin-left: .2rem;
    }

    .stat-sub {
        font-size: .68rem;
        color: rgba(201, 161, 74, .5);
        margin-top: .4rem;
    }

    /* ── Section ── */
    .section {
        margin-bottom: 2rem;
    }

    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .section-title {
        font-size: .7rem;
        letter-spacing: .3em;
        text-transform: uppercase;
        color: var(--gold);
        display: flex;
        align-items: center;
        gap: .5rem;
    }

    .live-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #5ec45e;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
            box-shadow: 0 0 0 0 rgba(94, 196, 94, .4);
        }

        50% {
            opacity: .7;
            box-shadow: 0 0 0 4px rgba(94, 196, 94, 0);
        }
    }

    .view-all {
        font-size: .68rem;
        letter-spacing: .15em;
        text-transform: uppercase;
        color: var(--muted);
        text-decoration: none;
        transition: color .2s;
    }

    .view-all:hover {
        color: var(--gold);
    }

    /* ── Tables ── */
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
        padding: .85rem 1.2rem;
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
        background: rgba(201, 161, 74, .04);
    }

    td {
        padding: .8rem 1.2rem;
        font-size: .82rem;
        color: var(--text);
        white-space: nowrap;
    }

    /* ── Badges ── */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .2rem .65rem;
        font-size: .6rem;
        letter-spacing: .1em;
        text-transform: uppercase;
        font-weight: 400;
    }

    .badge-processing {
        background: rgba(201, 161, 74, .12);
        color: var(--gold);
        border: 1px solid rgba(201, 161, 74, .25);
    }

    .badge-delivery {
        background: rgba(94, 130, 196, .12);
        color: #7aa0e0;
        border: 1px solid rgba(94, 130, 196, .25);
    }

    .badge-done {
        background: rgba(94, 196, 94, .1);
        color: #6ec87a;
        border: 1px solid rgba(94, 196, 94, .2);
    }

    .badge-cancelled {
        background: rgba(196, 94, 94, .1);
        color: #c87a7a;
        border: 1px solid rgba(196, 94, 94, .2);
    }

    /* ── Deliver button ── */
    .btn-deliver {
        padding: .3rem .9rem;
        background: rgba(201, 161, 74, .1);
        border: 1px solid rgba(201, 161, 74, .25);
        color: var(--gold);
        font-family: 'Jost', sans-serif;
        font-size: .68rem;
        letter-spacing: .1em;
        text-transform: uppercase;
        cursor: pointer;
        text-decoration: none;
        transition: background .2s;
    }

    .btn-deliver:hover {
        background: rgba(201, 161, 74, .2);
    }

    /* ── Empty state ── */
    .empty {
        padding: 3rem;
        text-align: center;
        color: var(--muted);
        font-size: .82rem;
    }

    .empty svg {
        width: 32px;
        height: 32px;
        color: rgba(201, 161, 74, .2);
        margin-bottom: .8rem;
    }

    @media (max-width: 900px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    </style>
</head>

<body>

    <!-- ── Sidebar ── -->
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

            <a href="admin-dashboard.php" class="nav-link active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>
                Dashboard
            </a>

            <a href="admin-orders.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                    <rect x="9" y="3" width="6" height="4" rx="1" />
                    <path d="M9 12h6M9 16h4" />
                </svg>
                Orders
            </a>

            <a href="admin-products.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                    <line x1="6" y1="1" x2="6" y2="4" />
                    <line x1="10" y1="1" x2="10" y2="4" />
                    <line x1="14" y1="1" x2="14" y2="4" />
                </svg>
                Products
            </a>

            <a href="admin-users.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                Users
            </a>

            <p class="nav-label">Operations</p>

            <a href="admin-manual-order.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1" />
                    <circle cx="20" cy="21" r="1" />
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                </svg>
                Manual Order
            </a>

            <a href="admin-checks.php" class="nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23" />
                    <path d="M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6" />
                </svg>
                Checks
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
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
                </svg>
                Sign Out
            </a>
        </div>
    </aside>

    <!-- ── Main Content ── -->
    <main class="main">

        <div class="page-header">
            <div>
                <h1 class="page-title">Good
                    <?= date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening') ?>,
                    <em><?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?></em></h1>
                <p class="page-date"><?= date('l, F j Y') ?></p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                        <rect x="9" y="3" width="6" height="4" rx="1" />
                    </svg>
                    Total Orders
                </div>
                <div class="stat-value"><?= $totalOrders ?></div>
                <div class="stat-sub">All time</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                    </svg>
                    Users
                </div>
                <div class="stat-value"><?= $totalUsers ?></div>
                <div class="stat-sub">Registered</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                    </svg>
                    Products
                </div>
                <div class="stat-value"><?= $totalProducts ?></div>
                <div class="stat-sub">On menu</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                        stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23" />
                        <path d="M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6" />
                    </svg>
                    Revenue
                </div>
                <div class="stat-value"><?= number_format($totalRevenue, 0) ?><span>EGP</span></div>
                <div class="stat-sub">Excl. cancelled</div>
            </div>
        </div>

        <!-- Live Orders -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">
                    <div class="live-dot"></div>
                    Live Incoming Orders
                    <?php if ($liveOrders): ?>
                    <span
                        style="background:rgba(201,161,74,.15);color:var(--gold);padding:.1rem .5rem;font-size:.58rem;border:1px solid rgba(201,161,74,.25);"><?= count($liveOrders) ?></span>
                    <?php endif; ?>
                </div>
                <a href="admin-orders.php" class="view-all">View all →</a>
            </div>
            <div class="table-wrap">
                <?php if ($liveOrders): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Time</th>
                            <th>Customer</th>
                            <th>Room</th>
                            <th>Ext.</th>
                            <th>Total</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($liveOrders as $o): ?>
                        <tr>
                            <td style="color:var(--muted)">#<?= $o['id'] ?></td>
                            <td style="color:var(--muted)"><?= date('H:i', strtotime($o['created_at'])) ?></td>
                            <td><?= htmlspecialchars($o['user_name']) ?></td>
                            <td><?= htmlspecialchars($o['room']) ?></td>
                            <td><?= htmlspecialchars($o['ext'] ?? '—') ?></td>
                            <td style="color:var(--gold)"><?= number_format($o['total'], 2) ?> EGP</td>
                            <td style="color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis">
                                <?= $o['notes'] ? htmlspecialchars($o['notes']) : '—' ?></td>
                            <td>
                                <a href="admin-orders.php?deliver=<?= $o['id'] ?>" class="btn-deliver">Deliver</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round"
                        stroke-linejoin="round" style="display:block;margin:0 auto .8rem">
                        <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                        <rect x="9" y="3" width="6" height="4" rx="1" />
                    </svg>
                    No pending orders right now
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">Recent Orders</div>
                <a href="admin-orders.php" class="view-all">View all →</a>
            </div>
            <div class="table-wrap">
                <?php if ($recentOrders): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Room</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $o): ?>
                        <?php
            $badgeClass = match($o['status']) {
              'processing'       => 'badge-processing',
              'out_for_delivery' => 'badge-delivery',
              'done'             => 'badge-done',
              'cancelled'        => 'badge-cancelled',
              default            => 'badge-processing',
            };
            $badgeLabel = match($o['status']) {
              'processing'       => 'Processing',
              'out_for_delivery' => 'Out for delivery',
              'done'             => 'Done',
              'cancelled'        => 'Cancelled',
              default            => $o['status'],
            };
          ?>
                        <tr>
                            <td style="color:var(--muted)">#<?= $o['id'] ?></td>
                            <td style="color:var(--muted)"><?= date('M j, H:i', strtotime($o['created_at'])) ?></td>
                            <td><?= htmlspecialchars($o['user_name']) ?></td>
                            <td><?= htmlspecialchars($o['room']) ?></td>
                            <td style="color:var(--gold)"><?= number_format($o['total'], 2) ?> EGP</td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">No orders yet</div>
                <?php endif; ?>
            </div>
        </div>

    </main>

</body>

</html>