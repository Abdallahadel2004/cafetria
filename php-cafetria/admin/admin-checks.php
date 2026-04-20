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

// Fetch users with their order totals
$userStmt = $pdo->prepare("
    SELECT u.id, u.name, u.room, u.extension AS ext,
           COUNT(o.id) AS order_count,
           COALESCE(SUM(CASE WHEN o.status != 'Cancelled' THEN o.total ELSE 0 END), 0) AS grand_total
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id AND o.status != 'Cancelled'
    " . ($dateFrom || $dateTo || $userFilter ? str_replace("u.role = 'user'", "u.role = 'user'", $whereSQL) : "WHERE u.role = 'user'") . "
    GROUP BY u.id
    ORDER BY grand_total DESC
");
$userStmt->execute($params);
$userRows = $userStmt->fetchAll();

// Grand summary
$totalRevenue  = array_sum(array_column($userRows, 'grand_total'));
$totalOrders   = array_sum(array_column($userRows, 'order_count'));

// Functions
function getUserOrders($pdo, $userId, $dateFrom, $dateTo) {
    $where  = ['o.user_id = ?', "o.status != 'Cancelled'"];
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

$activePage = 'checks';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Checks</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .check-user-block {
            background: var(--surface);
            border: 1px solid var(--outline-variant);
            border-radius: 12px;
            margin-bottom: 12px;
            overflow: hidden;
        }
        .check-user-header {
            display: grid;
            grid-template-columns: 1fr 120px 120px 120px 40px;
            align-items: center;
            padding: 16px 20px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .check-user-header:hover { background: var(--surface-variant); }
        .check-user-header.active { border-bottom: 1px solid var(--outline-variant); background: rgba(var(--primary-rgb), 0.03); }
        
        .orders-container { display: none; background: #fafafa; }
        .orders-container.active { display: block; }
        
        .order-row-header {
            display: grid;
            grid-template-columns: 1fr 150px 40px;
            padding: 12px 40px;
            border-bottom: 1px solid var(--outline-variant);
            cursor: pointer;
            font-size: 14px;
        }
        .order-row-header:hover { background: #f0f0f0; }
        
        .items-detail { display: none; padding: 16px 60px; background: white; border-bottom: 1px solid var(--outline-variant); }
        .items-detail.active { display: block; }
        
        .item-card {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--surface);
            border: 1px solid var(--outline-variant);
            border-radius: 8px;
            margin: 4px;
        }
        .chevron { transition: transform 0.3s; color: var(--on-surface-variant); }
        .active .chevron { transform: rotate(180deg); }
    </style>
</head>
<body>

<?php include '_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <h2>Spending Checks</h2>
        <div class="topbar-actions">
             <div class="badge badge-available">Revenue: <?= number_format($totalRevenue, 2) ?> EGP</div>
        </div>
    </div>

    <div class="page-content">
        
        <!-- Filters Card -->
        <div class="section-card" style="margin-bottom:24px">
            <form method="GET" style="display:flex; gap:16px; align-items:flex-end; padding:20px">
                <div class="form-group" style="flex:1">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-control">
                        <option value="">All Users</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userFilter == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="height:44px">Filter Reports</button>
                <a href="admin-checks.php" class="btn btn-secondary" style="height:44px; display:flex; align-items:center">Reset</a>
            </form>
        </div>

        
        <!-- Summary Stats -->
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-bottom:24px">
            <div class="section-card" style="padding:20px; display:flex; align-items:center; gap:16px">
                <div style="width:48px; height:48px; border-radius:12px; background:rgba(201,161,74,0.1); color:var(--primary); display:flex; align-items:center; justify-content:center">
                    <span class="material-symbols-outlined">payments</span>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--on-surface-variant); font-weight:600; text-transform:uppercase">Grand Total</div>
                    <div style="font-size:1.25rem; font-weight:800"><?= number_format($totalRevenue, 2) ?> EGP</div>
                </div>
            </div>
            <div class="section-card" style="padding:20px; display:flex; align-items:center; gap:16px">
                <div style="width:48px; height:48px; border-radius:12px; background:rgba(30,126,52,0.1); color:#1e7e34; display:flex; align-items:center; justify-content:center">
                    <span class="material-symbols-outlined">shopping_bag</span>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--on-surface-variant); font-weight:600; text-transform:uppercase">Total Orders</div>
                    <div style="font-size:1.25rem; font-weight:800"><?= $totalOrders ?></div>
                </div>
            </div>
            <div class="section-card" style="padding:20px; display:flex; align-items:center; gap:16px">
                <div style="width:48px; height:48px; border-radius:12px; background:rgba(0,123,255,0.1); color:#007bff; display:flex; align-items:center; justify-content:center">
                    <span class="material-symbols-outlined">person</span>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--on-surface-variant); font-weight:600; text-transform:uppercase">Active Users</div>
                    <div style="font-size:1.25rem; font-weight:800"><?= count(array_filter($userRows, fn($r) => $r['order_count'] > 0)) ?></div>
                </div>
            </div>
        </div>

        <!-- Reports List -->
        <div style="margin-bottom:12px; padding:0 20px; display:grid; grid-template-columns: 1fr 120px 120px 120px 40px; color:var(--on-surface-variant); font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px">
            <span>Name</span>
            <span>Room</span>
            <span style="text-align:center">Orders</span>
            <span style="text-align:right">Total Spent</span>
            <span></span>
        </div>

        <?php if (!$userRows): ?>
            <div class="section-card" style="padding:40px; text-align:center; color:var(--on-surface-variant)">No records found for this period.</div>
        <?php endif; ?>

        <?php foreach ($userRows as $u): ?>
        <div class="check-user-block">
            <div class="check-user-header" onclick="toggleAccordion('orders-<?= $u['id'] ?>', this)">
                <div style="font-weight:700; color:var(--on-surface)"><?= htmlspecialchars($u['name']) ?></div>
                <div style="color:var(--on-surface-variant); font-size:14px">Room <?= htmlspecialchars($u['room'] ?? '—') ?></div>
                <div style="text-align:center"><span class="badge" style="background:var(--surface-variant)"><?= $u['order_count'] ?> orders</span></div>
                <div style="text-align:right; font-weight:800; color:var(--primary)"><?= number_format($u['grand_total'], 2) ?> EGP</div>
                <span class="material-symbols-outlined chevron">expand_more</span>
            </div>
            
            <div id="orders-<?= $u['id'] ?>" class="orders-container">
                <?php $userOrders = getUserOrders($pdo, $u['id'], $dateFrom, $dateTo); ?>
                <?php foreach ($userOrders as $o): ?>
                    <div class="order-row-header" onclick="toggleAccordion('items-<?= $o['id'] ?>', this)">
                        <div>
                            <span style="font-weight:600">Order #<?= $o['id'] ?></span>
                            <span style="color:var(--primary); font-weight:600; margin-left:12px"><?= time_elapsed_string($o['created_at']) ?></span>
                            <span style="color:var(--on-surface-variant); font-size:0.75rem; margin-left:8px">(<?= date('M j, g:i A', strtotime($o['created_at'])) ?>)</span>
                        </div>
                        <div style="text-align:right; font-weight:700"><?= number_format($o['total'], 2) ?> EGP</div>
                        <span class="material-symbols-outlined chevron" style="font-size:18px">expand_more</span>
                    </div>
                    <div id="items-<?= $o['id'] ?>" class="items-detail">
                        <div style="display:flex; flex-wrap:wrap; gap:10px">
                            <?php $items = getOrderItems($pdo, $o['id']); ?>
                            <?php foreach ($items as $it): ?>
                                <div class="item-card">
                                    <div style="width:32px; height:32px; border-radius:4px; background:var(--surface-variant); overflow:hidden">
                                        <?php 
                                        $imgSrc = $it['image'];
                                        if ($imgSrc && !str_starts_with($imgSrc, 'http')) {
                                            $imgSrc = "../uploads/" . $imgSrc;
                                        }
                                        if ($imgSrc): ?>
                                            <img src="<?= htmlspecialchars($imgSrc) ?>" style="width:100%; height:100%; object-fit:cover">
                                        <?php else: ?>
                                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center"><span class="material-symbols-outlined" style="font-size:16px">coffee</span></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:13px">
                                        <span style="font-weight:700"><?= $it['quantity'] ?>x</span> <?= htmlspecialchars($it['product_name']) ?>
                                    </div>
                                    <div style="font-size:12px; color:var(--primary); font-weight:600"><?= number_format($it['unit_price'] * $it['quantity'], 2) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($o['notes']): ?>
                            <div style="margin-top:12px; padding:10px; border-left:3px solid var(--primary); background:#f9f9f9; font-size:13px; font-style:italic">
                                "<?= htmlspecialchars($o['notes']) ?>"
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</main>

<script>
function toggleAccordion(id, el) {
    const target = document.getElementById(id);
    const isActive = target.classList.contains('active');
    
    // Close others if you want, but here we just toggle
    target.classList.toggle('active');
    el.classList.toggle('active');
}
</script>
</body>
</html>