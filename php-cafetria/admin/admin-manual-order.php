<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}
require_once '../db.php';

$formError   = '';
$formSuccess = '';

// ── Handle POST: place manual order ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id  = (int)($_POST['user_id'] ?? 0);
    $room     = trim($_POST['room'] ?? '');
    $notes    = trim($_POST['notes'] ?? '');
    $items    = $_POST['items'] ?? [];   // array of product_id => quantity

    $errors = [];
    if (!$user_id)   $errors[] = 'Please select a user.';
    if ($room === '') $errors[] = 'Room number is required.';
    elseif (!preg_match('/^[\w\-]{1,20}$/', $room)) $errors[] = 'Room number contains invalid characters.';

    // Filter items: remove qty <= 0
    $validItems = [];
    foreach ($items as $product_id => $qty) {
        $qty = (int)$qty;
        if ($qty > 0) $validItems[(int)$product_id] = $qty;
    }
    if (empty($validItems)) $errors[] = 'Please add at least one item to the order.';

    if ($errors) {
        $formError = implode('<br>', $errors);
    } else {
        // Fetch product prices
        $in    = implode(',', array_keys($validItems));
        $prods = $pdo->query("SELECT id, price, status FROM products WHERE id IN ($in)")->fetchAll(PDO::FETCH_KEY_PAIR + 0);
        // re-fetch as assoc by id
        $prodRows = $pdo->query("SELECT * FROM products WHERE id IN ($in)")->fetchAll();
        $prodMap  = [];
        foreach ($prodRows as $pr) $prodMap[$pr['id']] = $pr;

        $total = 0;
        foreach ($validItems as $pid => $qty) {
            if (isset($prodMap[$pid])) {
                $total += $prodMap[$pid]['price'] * $qty;
            }
        }

        // Insert order
        $pdo->prepare("INSERT INTO orders (user_id, room, notes, total, status) VALUES (?,?,?,?,'processing')")
            ->execute([$user_id, $room, $notes ?: null, $total]);
        $order_id = $pdo->lastInsertId();

        // Insert order items
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)");
        foreach ($validItems as $pid => $qty) {
            if (isset($prodMap[$pid])) {
                $stmt->execute([$order_id, $pid, $qty, $prodMap[$pid]['price']]);
            }
        }

        $formSuccess = "Order #$order_id placed successfully for " . htmlspecialchars($pdo->query("SELECT name FROM users WHERE id=$user_id")->fetchColumn()) . " — Total: " . number_format($total, 2) . " EGP.";
    }
}

// Fetch data for the form
$users      = $pdo->query("SELECT id, name, room_no, ext FROM users WHERE role='user' ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$products   = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'available'
    ORDER BY c.name, p.name
")->fetchAll();

// Group products by category
$byCategory = [];
foreach ($products as $p) {
    $byCategory[$p['category_name'] ?? 'Uncategorised'][] = $p;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Manual Order</title>
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

    /* Main layout */
    .main {
        margin-left: var(--sidebar);
        flex: 1;
        padding: 2rem 2.2rem;
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 2rem;
        align-items: start;
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

    /* Cart panel (left) */
    .cart-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        padding: 1.5rem;
        position: sticky;
        top: 2rem;
    }

    .cart-title {
        font-size: .65rem;
        letter-spacing: .3em;
        text-transform: uppercase;
        color: var(--gold);
        margin-bottom: 1.2rem;
    }

    /* Cart items */
    .cart-items {
        min-height: 80px;
        margin-bottom: 1rem;
    }

    .cart-empty {
        color: var(--muted);
        font-size: .78rem;
        text-align: center;
        padding: 1.5rem 0;
    }

    .cart-row {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .5rem 0;
        border-bottom: 1px solid var(--border);
    }

    .cart-row:last-child {
        border-bottom: none;
    }

    .cart-name {
        flex: 1;
        font-size: .82rem;
        color: var(--cream);
    }

    .cart-price {
        font-size: .78rem;
        color: var(--gold);
        white-space: nowrap;
    }

    .qty-ctrl {
        display: flex;
        align-items: center;
        gap: .3rem;
    }

    .qty-btn {
        width: 22px;
        height: 22px;
        background: rgba(201, 161, 74, .1);
        border: 1px solid rgba(201, 161, 74, .25);
        color: var(--gold);
        font-size: .9rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        transition: background .2s;
    }

    .qty-btn:hover {
        background: rgba(201, 161, 74, .25);
    }

    .qty-val {
        width: 28px;
        text-align: center;
        font-size: .82rem;
        color: var(--cream);
    }

    .remove-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: rgba(196, 94, 94, .5);
        font-size: 1rem;
        line-height: 1;
        transition: color .2s;
        padding: 0;
    }

    .remove-btn:hover {
        color: #e08080;
    }

    .cart-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .8rem 0;
        border-top: 1px solid rgba(201, 161, 74, .2);
        margin-top: .5rem;
        font-size: .9rem;
    }

    .cart-total-label {
        color: var(--muted);
        font-size: .65rem;
        letter-spacing: .2em;
        text-transform: uppercase;
    }

    .cart-total-val {
        color: var(--gold);
        font-weight: 400;
    }

    /* Form fields */
    .field {
        margin-bottom: 1rem;
    }

    label.field-label {
        display: block;
        font-size: .62rem;
        letter-spacing: .2em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, .7);
        margin-bottom: .45rem;
    }

    select,
    input[type="text"],
    textarea {
        width: 100%;
        background: rgba(255, 255, 255, .04);
        border: 1px solid var(--border);
        color: var(--cream);
        font-family: 'Jost', sans-serif;
        font-weight: 300;
        font-size: .88rem;
        padding: .65rem .85rem;
        outline: none;
        transition: border-color .2s;
        -webkit-appearance: none;
    }

    select:focus,
    input:focus,
    textarea:focus {
        border-color: rgba(201, 161, 74, .45);
    }

    select option {
        background: var(--brown);
        color: var(--cream);
    }

    textarea {
        resize: vertical;
        min-height: 70px;
    }

    input.invalid,
    select.invalid {
        border-color: rgba(224, 92, 92, .5);
    }

    .field-error {
        font-size: .65rem;
        color: #e08080;
        margin-top: .3rem;
        display: none;
    }

    .field-error.show {
        display: block;
    }

    .btn-place {
        width: 100%;
        padding: .9rem;
        background: var(--gold);
        color: var(--dark);
        font-family: 'Jost', sans-serif;
        font-weight: 400;
        font-size: .75rem;
        letter-spacing: .2em;
        text-transform: uppercase;
        border: none;
        cursor: pointer;
        transition: background .2s, transform .2s;
        margin-top: .5rem;
    }

    .btn-place:hover {
        background: var(--cream);
        transform: translateY(-1px);
    }

    .btn-place:disabled {
        opacity: .4;
        cursor: not-allowed;
        transform: none;
    }

    .alert {
        padding: .65rem .9rem;
        font-size: .78rem;
        margin-bottom: 1rem;
        line-height: 1.5;
    }

    .alert-error {
        background: rgba(224, 92, 92, .08);
        border: 1px solid rgba(224, 92, 92, .25);
        color: #e08080;
    }

    .alert-success {
        background: rgba(94, 196, 94, .08);
        border: 1px solid rgba(94, 196, 94, .2);
        color: #6ec87a;
    }

    /* Products grid (right) */
    .products-panel {
        min-width: 0;
    }

    .search-wrap {
        position: relative;
        margin-bottom: 1.2rem;
    }

    .search-wrap svg {
        position: absolute;
        left: .8rem;
        top: 50%;
        transform: translateY(-50%);
        width: 14px;
        height: 14px;
        color: var(--muted);
        pointer-events: none;
    }

    .search-wrap input {
        width: 100%;
        background: var(--surface);
        border: 1px solid var(--border);
        color: var(--cream);
        font-family: 'Jost', sans-serif;
        font-size: .82rem;
        font-weight: 300;
        padding: .55rem .9rem .55rem 2.2rem;
        outline: none;
        transition: border-color .2s;
    }

    .search-wrap input:focus {
        border-color: rgba(201, 161, 74, .4);
    }

    .search-wrap input::placeholder {
        color: var(--muted);
    }

    .category-label {
        font-size: .6rem;
        letter-spacing: .3em;
        text-transform: uppercase;
        color: rgba(201, 161, 74, .45);
        margin: 1rem 0 .5rem;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: .7rem;
        margin-bottom: 1rem;
    }

    .product-card {
        background: var(--surface);
        border: 1px solid var(--border);
        padding: .9rem;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        text-align: center;
        position: relative;
    }

    .product-card:hover {
        border-color: rgba(201, 161, 74, .35);
        background: rgba(201, 161, 74, .04);
    }

    .product-card.in-cart {
        border-color: rgba(201, 161, 74, .5);
        background: rgba(201, 161, 74, .07);
    }

    .prod-icon {
        width: 44px;
        height: 44px;
        margin: 0 auto .5rem;
        background: var(--mid);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .prod-icon svg {
        width: 22px;
        height: 22px;
        color: rgba(201, 161, 74, .5);
    }

    .prod-icon img {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
    }

    .prod-name {
        font-size: .8rem;
        color: var(--cream);
        margin-bottom: .2rem;
    }

    .prod-price {
        font-size: .72rem;
        color: var(--gold);
    }

    .prod-badge {
        position: absolute;
        top: .4rem;
        right: .4rem;
        width: 18px;
        height: 18px;
        background: var(--gold);
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: .6rem;
        color: var(--dark);
        font-weight: 500;
    }

    .product-card.in-cart .prod-badge {
        display: flex;
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
            <a href="admin-manual-order.php" class="nav-link active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"
                    stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1" />
                    <circle cx="20" cy="21" r="1" />
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                </svg>Manual Order
            </a>
            <a href="admin-checks.php" class="nav-link">
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

        <!-- LEFT: Cart + form -->
        <div>
            <h1 class="page-title">Manual <em>Order</em></h1>

            <?php if ($formError): ?>
            <div class="alert alert-error"><?= $formError ?></div>
            <?php endif; ?>
            <?php if ($formSuccess): ?>
            <div class="alert alert-success"><?= $formSuccess ?></div>
            <?php endif; ?>

            <div class="cart-panel">
                <div class="cart-title">Order Cart</div>

                <!-- Cart items injected by JS -->
                <div class="cart-items" id="cartItems">
                    <div class="cart-empty" id="cartEmpty">No items added yet. Click a product →</div>
                </div>

                <div class="cart-total">
                    <span class="cart-total-label">Total</span>
                    <span class="cart-total-val" id="cartTotal">0.00 EGP</span>
                </div>

                <!-- Hidden inputs will be injected here by JS -->
                <form method="POST" id="orderForm" novalidate>
                    <div id="hiddenItems"></div>

                    <div class="field">
                        <label class="field-label">Assign to User</label>
                        <select name="user_id" id="f-user">
                            <option value="">— Select user —</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" data-room="<?= htmlspecialchars($u['room_no'] ?? '') ?>"
                                <?= (isset($_POST['user_id']) && $_POST['user_id'] == $u['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-error" id="e-user">Please select a user.</div>
                    </div>

                    <div class="field">
                        <label class="field-label">Room No.</label>
                        <input type="text" name="room" id="f-room" placeholder="2010"
                            value="<?= htmlspecialchars($_POST['room'] ?? '') ?>">
                        <div class="field-error" id="e-room">Room number is required.</div>
                    </div>

                    <div class="field">
                        <label class="field-label">Notes (optional)</label>
                        <textarea name="notes"
                            placeholder="e.g. 1 tea extra sugar"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-place" id="placeBtn">Place Order</button>
                </form>
            </div>
        </div>

        <!-- RIGHT: Products -->
        <div class="products-panel">
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="text" id="prodSearch" placeholder="Search products…">
            </div>

            <?php foreach ($byCategory as $catName => $prods): ?>
            <div class="category-label"><?= htmlspecialchars($catName) ?></div>
            <div class="products-grid">
                <?php foreach ($prods as $p): ?>
                <div class="product-card" id="pc-<?= $p['id'] ?>" data-id="<?= $p['id'] ?>"
                    data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['price'] ?>"
                    data-search="<?= strtolower(htmlspecialchars($p['name'])) ?>" onclick="addToCart(this)">
                    <div class="prod-badge" id="badge-<?= $p['id'] ?>">0</div>
                    <div class="prod-icon">
                        <?php if ($p['image'] && file_exists('../uploads/'.$p['image'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($p['image']) ?>" alt="">
                        <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                            <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                            <line x1="6" y1="1" x2="6" y2="4" />
                            <line x1="10" y1="1" x2="10" y2="4" />
                            <line x1="14" y1="1" x2="14" y2="4" />
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="prod-price"><?= number_format($p['price'], 2) ?> EGP</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </main>

    <script>
    // Cart state: { productId: { name, price, qty } }
    const cart = {};

    function addToCart(el) {
        const id = el.dataset.id;
        const name = el.dataset.name;
        const price = parseFloat(el.dataset.price);
        if (cart[id]) {
            cart[id].qty++;
        } else {
            cart[id] = {
                name,
                price,
                qty: 1
            };
        }
        renderCart();
    }

    function changeQty(id, delta) {
        if (!cart[id]) return;
        cart[id].qty += delta;
        if (cart[id].qty <= 0) delete cart[id];
        renderCart();
    }

    function removeItem(id) {
        delete cart[id];
        renderCart();
    }

    function renderCart() {
        const container = document.getElementById('cartItems');
        const emptyMsg = document.getElementById('cartEmpty');
        const totalEl = document.getElementById('cartTotal');
        const hidden = document.getElementById('hiddenItems');

        // Clear
        container.innerHTML = '';
        hidden.innerHTML = '';

        const ids = Object.keys(cart);
        if (ids.length === 0) {
            container.innerHTML = '<div class="cart-empty">No items added yet. Click a product →</div>';
            totalEl.textContent = '0.00 EGP';
            // Reset all badges
            document.querySelectorAll('.product-card').forEach(c => {
                c.classList.remove('in-cart');
                document.getElementById('badge-' + c.dataset.id).textContent = '0';
            });
            return;
        }

        let total = 0;
        ids.forEach(id => {
            const item = cart[id];
            const lineTotal = item.price * item.qty;
            total += lineTotal;

            // Cart row
            const row = document.createElement('div');
            row.className = 'cart-row';
            row.innerHTML = `
      <div class="cart-name">${escHtml(item.name)}</div>
      <div class="qty-ctrl">
        <button type="button" class="qty-btn" onclick="changeQty('${id}',-1)">−</button>
        <span class="qty-val">${item.qty}</span>
        <button type="button" class="qty-btn" onclick="changeQty('${id}',1)">+</button>
      </div>
      <div class="cart-price">${lineTotal.toFixed(2)} EGP</div>
      <button type="button" class="remove-btn" onclick="removeItem('${id}')">×</button>
    `;
            container.appendChild(row);

            // Hidden input
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = `items[${id}]`;
            inp.value = item.qty;
            hidden.appendChild(inp);

            // Update badge
            const card = document.getElementById('pc-' + id);
            const badge = document.getElementById('badge-' + id);
            if (card) card.classList.add('in-cart');
            if (badge) badge.textContent = item.qty;
        });

        // Reset cards not in cart
        document.querySelectorAll('.product-card').forEach(c => {
            if (!cart[c.dataset.id]) {
                c.classList.remove('in-cart');
                document.getElementById('badge-' + c.dataset.id).textContent = '0';
            }
        });

        totalEl.textContent = total.toFixed(2) + ' EGP';
    }

    function escHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Auto-fill room when user is selected
    document.getElementById('f-user').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const room = opt.dataset.room || '';
        document.getElementById('f-room').value = room;
        clearErr('e-user');
    });

    // Search products
    document.getElementById('prodSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.product-card').forEach(c => {
            c.style.display = c.dataset.search.includes(q) ? '' : 'none';
        });
        // Hide empty category labels
        document.querySelectorAll('.products-grid').forEach(grid => {
            const visible = [...grid.querySelectorAll('.product-card')].some(c => c.style.display !==
                'none');
            const label = grid.previousElementSibling;
            if (label && label.classList.contains('category-label')) label.style.display = visible ?
                '' : 'none';
            grid.style.display = visible ? '' : 'none';
        });
    });

    // Client-side validation
    function showErr(id, msg) {
        const el = document.getElementById(id);
        if (msg) el.textContent = msg;
        el.classList.add('show');
        const field = document.getElementById(id.replace('e-', 'f-'));
        if (field) field.classList.add('invalid');
    }

    function clearErr(id) {
        document.getElementById(id).classList.remove('show');
        const field = document.getElementById(id.replace('e-', 'f-'));
        if (field) field.classList.remove('invalid');
    }

    document.getElementById('f-room').addEventListener('input', () => clearErr('e-room'));

    document.getElementById('orderForm').addEventListener('submit', function(e) {
        let ok = true;
        const user = document.getElementById('f-user').value;
        const room = document.getElementById('f-room').value.trim();

        if (!user) {
            showErr('e-user', 'Please select a user.');
            ok = false;
        }
        if (!room) {
            showErr('e-room', 'Room number is required.');
            ok = false;
        }

        if (Object.keys(cart).length === 0) {
            alert('Please add at least one item to the order.');
            ok = false;
        }

        if (!ok) e.preventDefault();
    });
    </script>
</body>

</html>