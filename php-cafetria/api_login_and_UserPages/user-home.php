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

// ── Available products ────────────────────────────────────────────────
$products = $pdo->query("
    SELECT p.id, p.name, c.name AS category, p.price, p.image
    FROM   products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE  p.status = 'available'
    ORDER  BY category, p.name
")->fetchAll();

// ── Distinct rooms for delivery dropdown ─────────────────────────────
$rooms = $pdo->query("
    SELECT DISTINCT room
    FROM   users
    WHERE  room IS NOT NULL AND room <> ''
    ORDER  BY room
")->fetchAll(PDO::FETCH_COLUMN);

// ── User's room and profile picture ──────────────────────────────────
$myRow   = $pdo->prepare("SELECT room, profile_picture FROM users WHERE id = ? LIMIT 1");
$myRow->execute([$userId]);
$myRow   = $myRow->fetch();
$myRoom  = (string)($myRow['room'] ?? '');
$myPhoto = $myRow['profile_picture'] ?? '';

if ($myRoom !== '' && !in_array($myRoom, $rooms, true)) {
    $rooms[] = $myRoom;
}

// ── Last order for Quick Reorder ─────────────────────────────────────
$lastStmt = $pdo->prepare("
    SELECT o.id, o.total, o.created_at,
           (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ')
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = o.id) AS items_summary
    FROM   orders o
    WHERE  o.user_id = ?
    ORDER  BY o.created_at DESC
    LIMIT  1
");
$lastStmt->execute([$userId]);
$lastOrder = $lastStmt->fetch();

// ── Distinct categories for filter tabs ──────────────────────────────
$categories = $pdo->query("
    SELECT DISTINCT c.name
    FROM categories c
    JOIN products p ON p.category_id = c.id
    WHERE p.status = 'available'
    ORDER BY c.name
")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria | Make an Order</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Be+Vietnam+Pro:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
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
                <li><a href="user-home.php" class="active">Home</a></li>
                <li><a href="user-orders.php">My Orders</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
            <div class="user-profile">
                <?php 
                    $avatarSrc = $myPhoto;
                    if ($avatarSrc && !str_starts_with($avatarSrc, 'http')) {
                        $avatarSrc = '../uploads/' . $avatarSrc;
                        if (!file_exists($avatarSrc)) $avatarSrc = '';
                    }
                    if ($avatarSrc): 
                ?>
                <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="<?= $userName ?>" class="avatar">
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
        <div class="order-layout">

            <!-- ── LEFT: Cart Panel ─────────────── -->
            <aside class="order-cart">

                <!-- ★ Toast container lives HERE — above the cart form ★ -->
                <div id="toast-container"></div>

                <h2>
                    <span class="material-symbols-outlined"
                        style="font-size:1.2rem;vertical-align:-3px;margin-right:.25rem;">shopping_cart</span>
                    Your Order
                </h2>

                <div id="cartItems">
                    <p id="cartEmpty" style="font-size:.8125rem;color:var(--on-surface-variant);padding:.5rem 0;">
                        Your cart is empty. Tap a product on the right to add it.
                    </p>
                </div>

                <div style="margin-top:var(--spacing-md);">
                    <label class="form-label">
                        <span class="material-symbols-outlined"
                            style="font-size:.875rem;vertical-align:-2px;">edit_note</span>
                        Notes
                    </label>
                    <textarea class="form-control" id="orderNotes" rows="2"
                        placeholder="Any special requests…"></textarea>
                </div>

                <div style="margin-top:var(--spacing-md);">
                    <label class="form-label">Delivery Room</label>
                    <select class="form-control" id="orderRoom">
                        <option value="">Select room…</option>
                        <?php foreach ($rooms as $room): ?>
                        <option value="<?= htmlspecialchars($room) ?>" <?= $room === $myRoom ? 'selected' : '' ?>>
                            <?= htmlspecialchars($room) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cart-total">
                    <div class="cart-total-row">
                        <span class="cart-total-label">Total</span>
                        <span class="cart-total-value" id="totalPrice">EGP 0</span>
                    </div>
                    <button class="btn btn-primary btn-lg full-width" id="confirmOrderBtn"
                        style="margin-top:var(--spacing-md);" onclick="submitOrder()">
                        <span class="material-symbols-outlined" style="font-size:1.125rem;">check_circle</span>
                        Confirm Order
                    </button>
                </div>
            </aside>

            <!-- ── RIGHT: Menu ──────────────────── -->
            <section>

                <!-- Quick Reorder -->
                <div class="quick-reorder">
                    <div>
                        <h3 style="font-size:.9375rem;font-weight:700;margin-bottom:.125rem;">
                            <span class="material-symbols-outlined"
                                style="font-size:1rem;vertical-align:-2px;color:var(--primary);">bolt</span>
                            Quick Reorder (Latest)
                        </h3>
                        <?php if ($lastOrder): ?>
                        <p style="font-size:.8125rem;color:var(--on-surface-variant);">
                            <?= htmlspecialchars($lastOrder['items_summary'] ?? '') ?>
                            &middot; EGP <?= number_format((float)$lastOrder['total']) ?>
                        </p>
                        <?php else: ?>
                        <p style="font-size:.8125rem;color:var(--on-surface-variant);">No recent orders yet.</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($lastOrder): ?>
                    <button class="btn btn-secondary btn-sm" onclick="reorderLast(<?= (int)$lastOrder['id'] ?>)">
                        <span class="material-symbols-outlined" style="font-size:.875rem;">replay</span>
                        Add to Cart
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Header + Category Tabs -->
                <div
                    style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--spacing-lg);">
                    <div>
                        <h2 style="font-size:1.5rem;font-weight:800;">Available Items</h2>
                        <p style="color:var(--on-surface-variant);font-size:.875rem;">Tap an item to add it to your cart
                        </p>
                    </div>
                    <div class="category-tabs">
                        <button class="category-tab active" onclick="filterCategory(this,'all')">All</button>
                        <?php foreach ($categories as $cat): ?>
                        <button class="category-tab" onclick="filterCategory(this,'<?= htmlspecialchars($cat) ?>')">
                            <?= htmlspecialchars($cat) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="product-grid" id="productGrid">
                    <?php if (empty($products)): ?>
                    <p style="grid-column:1/-1;text-align:center;color:var(--on-surface-variant);">
                        No products available right now.
                    </p>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <div class="product-card" data-id="<?= (int)$p['id'] ?>"
                        data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= (int)$p['price'] ?>"
                        data-category="<?= htmlspecialchars($p['category'] ?? '') ?>" onclick="addToCart(this)">
                        <div class="img-wrapper">
                            <?php 
                                $imgSrc = $p['image'];
                                $showPlaceholder = true;
                                if ($imgSrc) {
                                    if (str_starts_with($imgSrc, 'http')) {
                                        $showPlaceholder = false;
                                    } else {
                                        $localPath = '../uploads/' . $imgSrc;
                                        if (file_exists($localPath)) {
                                            $imgSrc = $localPath;
                                            $showPlaceholder = false;
                                        }
                                    }
                                }
                                
                                if (!$showPlaceholder): 
                            ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                alt="<?= htmlspecialchars($p['name']) ?>"
                                class="product-img">
                            <?php else: ?>
                            <span class="material-symbols-outlined"
                                style="font-size:3rem;color:var(--primary-container);">local_cafe</span>
                            <?php endif; ?>
                            <span class="price-badge"><?= (int)$p['price'] ?> LE</span>
                        </div>
                        <div class="product-body">
                            <div>
                                <h4 class="product-name"><?= htmlspecialchars($p['name']) ?></h4>
                                <p class="product-desc"><?= htmlspecialchars($p['category'] ?? '') ?></p>
                            </div>
                            <button class="add-btn" type="button" aria-label="Add to cart">
                                <span class="material-symbols-outlined">add</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

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
    const CAFETRIA_USER = {
        id: <?= $userId ?>,
        name: <?= json_encode($userName) ?>,
        room: <?= json_encode($myRoom) ?>
    };
    </script>
    <script src="user.js"></script>
</body>

</html>