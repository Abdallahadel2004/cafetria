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
        $prodRows = $pdo->query("SELECT * FROM products WHERE id IN ($in)")->fetchAll();
        $prodMap  = [];
        foreach ($prodRows as $pr) $prodMap[$pr['id']] = $pr;

        $total = 0;
        foreach ($validItems as $pid => $qty) {
            if (isset($prodMap[$pid])) {
                $total += $prodMap[$pid]['price'] * $qty;
            }
        }

        // Generate items summary string
        $summaryParts = [];
        foreach ($validItems as $pid => $qty) {
            if (isset($prodMap[$pid])) {
                $summaryParts[] = $qty . "x " . $prodMap[$pid]['name'];
            }
        }
        $items_summary = implode(", ", $summaryParts);

        // Insert order
        $pdo->prepare("INSERT INTO orders (user_id, room, notes, total, status, items_summary) VALUES (?,?,?,?,'Processing',?)")
            ->execute([$user_id, $room, $notes ?: null, $total, $items_summary]);
        $order_id = $pdo->lastInsertId();

        // Insert order items
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)");
        foreach ($validItems as $pid => $qty) {
            if (isset($prodMap[$pid])) {
                $stmt->execute([$order_id, $pid, $qty, $prodMap[$pid]['price']]);
            }
        }

        // Update total_orders in products
        $updateStmt = $pdo->prepare("UPDATE products SET total_orders = total_orders + ? WHERE id = ?");
        foreach ($validItems as $pid => $qty) {
            $updateStmt->execute([$qty, $pid]);
        }

        $formSuccess = "Order #$order_id placed successfully.";
    }
}

// Fetch data
$users      = $pdo->query("SELECT id, name, room, extension AS ext FROM users WHERE role='user' ORDER BY name")->fetchAll();
$products   = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.status = 'Available'
    ORDER BY c.name, p.name
")->fetchAll();

$byCategory = [];
foreach ($products as $p) {
    $byCategory[$p['category_name'] ?? 'Uncategorised'][] = $p;
}

$activePage = 'manual-order';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria — Manual Order</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .manual-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
            align-items: start;
        }
        .cart-card {
            position: sticky;
            top: 24px;
        }
        .cart-list {
            margin-bottom: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--outline-variant);
        }
        .cart-item:last-child { border-bottom: none; }
        
        .qty-ctrl {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface-variant);
            border-radius: 20px;
            padding: 2px 8px;
        }
        .qty-btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }
        .qty-btn span { font-size: 18px; }

        .prod-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }
        .prod-card {
            background: var(--surface);
            border: 1px solid var(--outline-variant);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .prod-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }
        .prod-img {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 12px;
            background: var(--surface-variant);
        }
        .prod-card .badge-qty {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--primary);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .prod-card.in-cart { border-color: var(--primary); background: rgba(var(--primary-rgb), 0.05); }
        .prod-card.in-cart .badge-qty { display: flex; }
    </style>
</head>
<body>

<?php include '_sidebar.php'; ?>

<main class="main">
    <div class="topbar">
        <h2>Manual Order</h2>
        <div class="topbar-actions">
             <div class="search-box" style="position:relative">
                <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--on-surface-variant); font-size:20px">search</span>
                <input type="text" id="prodSearch" class="form-control" placeholder="Search products..." style="padding-left:40px; width:250px">
             </div>
        </div>
    </div>

    <div class="page-content">
        <div class="manual-layout">
            
            <!-- LEFT: CART & FORM -->
            <div class="cart-card section-card">
                <div class="section-header">
                    <div class="section-title">
                        <span class="material-symbols-outlined">shopping_cart</span>
                        Current Order
                    </div>
                </div>
                
                <div style="padding:20px">
                    <?php if ($formSuccess): ?>
                        <div class="alert alert-success" style="margin-bottom:16px; padding:10px; border-radius:8px; background:#e6f4ea; color:#1e7e34; font-size:14px"><?= $formSuccess ?></div>
                    <?php endif; ?>
                    <?php if ($formError): ?>
                        <div class="alert alert-danger" style="margin-bottom:16px; padding:10px; border-radius:8px; background:#fce8e6; color:#d93025; font-size:14px"><?= $formError ?></div>
                    <?php endif; ?>

                    <div id="cartItems" class="cart-list">
                        <div style="text-align:center; color:var(--on-surface-variant); padding:20px; font-size:14px">Your cart is empty</div>
                    </div>

                    <div style="display:flex; justify-content:space-between; margin-bottom:20px; padding-top:10px; border-top:2px solid var(--outline-variant)">
                        <span style="font-weight:700">Total</span>
                        <span id="cartTotal" style="font-weight:800; color:var(--primary); font-size:1.1rem">0.00 EGP</span>
                    </div>

                    <form method="POST" id="orderForm">
                        <div id="hiddenItems"></div>
                        
                        <div class="form-group" style="margin-bottom:16px">
                            <label class="form-label">Customer</label>
                            <select name="user_id" id="f-user" class="form-control" required>
                                <option value="">Select a user</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" data-room="<?= htmlspecialchars($u['room'] ?? '') ?>"><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom:16px">
                            <label class="form-label">Room</label>
                            <input type="text" name="room" id="f-room" class="form-control" placeholder="Room Number" required>
                        </div>

                        <div class="form-group" style="margin-bottom:20px">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any special requests?"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%; padding:12px" id="placeBtn">Place Order</button>
                    </form>
                </div>
            </div>

            <!-- RIGHT: PRODUCTS -->
            <div class="products-col">
                <?php foreach ($byCategory as $catName => $prods): ?>
                    <div style="margin-bottom:32px">
                        <h3 class="category-title" style="margin-bottom:16px; font-size:1rem; color:var(--on-surface-variant); text-transform:uppercase; letter-spacing:1px"><?= htmlspecialchars($catName) ?></h3>
                        <div class="prod-grid">
                            <?php foreach ($prods as $p): ?>
                            <div class="prod-card" id="pc-<?= $p['id'] ?>" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['price'] ?>" data-search="<?= strtolower(htmlspecialchars($p['name'])) ?>" onclick="addToCart(this)">
                                <div class="badge-qty" id="badge-<?= $p['id'] ?>">0</div>
                                <?php 
                                $imgSrc = $p['image'];
                                if ($imgSrc && !str_starts_with($imgSrc, 'http')) {
                                    $imgSrc = "../uploads/" . $imgSrc;
                                }
                                if ($imgSrc): ?>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="prod-img">
                                <?php else: ?>
                                    <div class="prod-img" style="display:flex; align-items:center; justify-content:center">
                                        <span class="material-symbols-outlined" style="font-size:32px; color:var(--on-surface-variant)">coffee</span>
                                    </div>
                                <?php endif; ?>
                                <div style="font-weight:600; font-size:14px"><?= htmlspecialchars($p['name']) ?></div>
                                <div style="color:var(--primary); font-weight:700; margin-top:4px"><?= number_format($p['price'], 2) ?> EGP</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</main>

<script>
const cart = {};

function addToCart(el) {
    const id = el.dataset.id;
    const name = el.dataset.name;
    const price = parseFloat(el.dataset.price);
    if (cart[id]) {
        cart[id].qty++;
    } else {
        cart[id] = { name, price, qty: 1 };
    }
    renderCart();
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    cart[id].qty += delta;
    if (cart[id].qty <= 0) delete cart[id];
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    const totalEl = document.getElementById('cartTotal');
    const hidden = document.getElementById('hiddenItems');

    container.innerHTML = '';
    hidden.innerHTML = '';

    const ids = Object.keys(cart);
    if (ids.length === 0) {
        container.innerHTML = '<div style="text-align:center; color:var(--on-surface-variant); padding:20px; font-size:14px">Your cart is empty</div>';
        totalEl.textContent = '0.00 EGP';
        document.querySelectorAll('.prod-card').forEach(c => {
            c.classList.remove('in-cart');
            const b = document.getElementById('badge-' + c.dataset.id);
            if(b) b.textContent = '0';
        });
        return;
    }

    let total = 0;
    ids.forEach(id => {
        const item = cart[id];
        const lineTotal = item.price * item.qty;
        total += lineTotal;

        const row = document.createElement('div');
        row.className = 'cart-item';
        row.innerHTML = `
            <div style="flex:1">
                <div style="font-weight:600; font-size:14px">${item.name}</div>
                <div style="color:var(--primary); font-size:12px">${item.price.toFixed(2)} EGP</div>
            </div>
            <div class="qty-ctrl">
                <button type="button" class="qty-btn" onclick="changeQty('${id}',-1)"><span class="material-symbols-outlined">remove</span></button>
                <span style="font-weight:700; width:20px; text-align:center">${item.qty}</span>
                <button type="button" class="qty-btn" onclick="changeQty('${id}',1)"><span class="material-symbols-outlined">add</span></button>
            </div>
        `;
        container.appendChild(row);

        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = `items[${id}]`;
        inp.value = item.qty;
        hidden.appendChild(inp);

        const card = document.getElementById('pc-' + id);
        const badge = document.getElementById('badge-' + id);
        if (card) card.classList.add('in-cart');
        if (badge) badge.textContent = item.qty;
    });

    document.querySelectorAll('.prod-card').forEach(c => {
        if (!cart[c.dataset.id]) {
            c.classList.remove('in-cart');
            const b = document.getElementById('badge-' + c.dataset.id);
            if(b) b.textContent = '0';
        }
    });

    totalEl.textContent = total.toFixed(2) + ' EGP';
}

document.getElementById('f-user').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('f-room').value = opt.dataset.room || '';
});

document.getElementById('prodSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.prod-card').forEach(c => {
        c.style.display = c.dataset.search.includes(q) ? '' : 'none';
    });
});

document.getElementById('orderForm').addEventListener('submit', function(e) {
    if (Object.keys(cart).length === 0) {
        showToast('Please add at least one item.', 'error');
        e.preventDefault();
    }
});

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'check_circle' : (type === 'error' ? 'error' : 'info');
    
    toast.innerHTML = `
        <span class="material-symbols-outlined toast-icon">${icon}</span>
        <span class="toast-message">${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast-out');
        toast.addEventListener('animationend', () => toast.remove());
    }, 4000);
}
</script>
</body>
</html>