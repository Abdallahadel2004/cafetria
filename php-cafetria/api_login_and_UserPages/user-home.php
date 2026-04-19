<?php
/**
 * user-home.php
 *
 * PHP responsibilities:
 *   - Auth guard (must be logged-in user; admins are redirected to their dashboard)
 *   - Load logged-in user info for the navbar
 *   - Query available products (Available status only) for the menu grid
 *   - Build the rooms dropdown from the distinct rooms in the users table
 *   - Pull this user's most recent order for the "Quick Reorder" panel
 *
 * JS responsibilities (loaded from ./user.js):
 *   - Manage cart state in the browser
 *   - Submit confirmed orders / cancel orders / reorder via api/order.php
 *
 * Mirrors the markup of user/user-home.html exactly so ../style.css still applies.
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

// ── Available products (the cards in the right-hand grid) ────────────────
$products = $pdo->query("
    SELECT p.id, p.name, c.name AS category, p.price, p.description, p.image
    FROM   products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE  p.status = 'available'
    ORDER  BY category, p.name
")->fetchAll();

// ── Distinct rooms for the delivery dropdown ─────────────────────────────
$rooms = $pdo->query("
    SELECT DISTINCT room
    FROM   users
    WHERE  room IS NOT NULL AND room <> ''
    ORDER  BY room
")->fetchAll(PDO::FETCH_COLUMN);

// Also include the user's own room as the default (and make sure it's in the list).
$myRoomStmt = $pdo->prepare("SELECT room FROM users WHERE id = :id LIMIT 1");
$myRoomStmt->execute([':id' => $userId]);
$myRoom = (string)($myRoomStmt->fetchColumn() ?: '');
if ($myRoom !== '' && !in_array($myRoom, $rooms, true)) {
    $rooms[] = $myRoom;
}

// ── This user's most recent order for the "Quick Reorder" panel ──────────
$lastOrderStmt = $pdo->prepare("
    SELECT o.id, o.total, o.created_at,
           (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name) SEPARATOR ', ')
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = o.id) AS items_summary
    FROM   orders o
    WHERE  o.user_id = :uid
    ORDER  BY o.created_at DESC
    LIMIT  1
");
$lastOrderStmt->execute([':uid' => $userId]);
$lastOrder = $lastOrderStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafetria | Make an Order</title>
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
                <li><a href="user-home.php" class="active">Home</a></li>
                <li><a href="user-orders.php">My Orders</a></li>
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
        <div class="order-layout">
            <!-- ── Left: Cart Panel ─────────────── -->
            <aside class="order-cart">
                <h2>
                    <span class="material-symbols-outlined" style="font-size:1.25rem; vertical-align:-3px; margin-right:0.25rem;">shopping_cart</span>
                    Your Order
                </h2>

                <!-- Cart Items (populated by user.js as the user clicks products) -->
                <div id="cartItems">
                    <p id="cartEmpty" style="font-size:0.8125rem; color: var(--on-surface-variant);">
                        Your cart is empty. Tap a product on the right to add it.
                    </p>
                </div>

                <!-- Notes -->
                <div style="margin-top: var(--spacing-md);">
                    <label class="form-label">
                        <span class="material-symbols-outlined" style="font-size:0.875rem; vertical-align:-2px;">edit_note</span>
                        Notes
                    </label>
                    <textarea class="form-control" id="orderNotes" name="notes" rows="2" placeholder="Any special requests..."></textarea>
                </div>

                <!-- Room -->
                <div style="margin-top: var(--spacing-md);">
                    <label class="form-label">Delivery Room</label>
                    <select class="form-control" id="orderRoom" name="room">
                        <option value="">Select room...</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= htmlspecialchars($room) ?>"
                                <?= $room === $myRoom ? 'selected' : '' ?>>
                                <?= htmlspecialchars($room) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Total + Submit -->
                <div class="cart-total">
                    <div class="cart-total-row">
                        <span class="cart-total-label">Total</span>
                        <span class="cart-total-value" id="totalPrice">EGP 0</span>
                    </div>
                    <button class="btn btn-primary btn-lg full-width" id="confirmOrderBtn"
                            style="margin-top: var(--spacing-md);" onclick="submitOrder()">
                        <span class="material-symbols-outlined" style="font-size:1.125rem;">check_circle</span>
                        Confirm Order
                    </button>
                </div>
            </aside>

            <!-- ── Right: Menu ──────────────────── -->
            <section>
                <!-- Quick Reorder -->
                <div class="quick-reorder">
                    <div>
                        <h3 style="font-size: 0.9375rem; font-weight: 700; margin-bottom: 0.125rem;">
                            <span class="material-symbols-outlined" style="font-size:1rem; vertical-align:-2px; color: var(--primary);">bolt</span>
                            Quick Reorder (Latest)
                        </h3>
                        <?php if ($lastOrder): ?>
                            <p style="font-size: 0.8125rem; color: var(--on-surface-variant);">
                                <?= htmlspecialchars($lastOrder['items_summary']) ?>
                                · EGP <?= number_format((float)$lastOrder['total']) ?>
                            </p>
                        <?php else: ?>
                            <p style="font-size: 0.8125rem; color: var(--on-surface-variant);">
                                No recent orders yet.
                            </p>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-secondary btn-sm"
                            onclick="reorderLast(<?= $lastOrder ? (int)$lastOrder['id'] : 0 ?>)"
                            <?= $lastOrder ? '' : 'style="display:none;"' ?>>
                        <span class="material-symbols-outlined" style="font-size:0.875rem;">replay</span>
                        Add to Cart
                    </button>
                </div>

                <!-- Category Tabs -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 800;">Available Items</h2>
                        <p style="color: var(--on-surface-variant); font-size: 0.875rem;">Tap an item to add it to your cart</p>
                    </div>
                    <div class="category-tabs">
                        <button class="category-tab active" onclick="filterCategory(this, 'all')">All</button>
                        <button class="category-tab" onclick="filterCategory(this, 'Hot')">Hot</button>
                        <button class="category-tab" onclick="filterCategory(this, 'Cold')">Cold</button>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="product-grid" id="productGrid">
                    <?php if (empty($products)): ?>
                        <p style="grid-column: 1 / -1; text-align:center; color: var(--on-surface-variant);">
                            No products available right now. Please check back later.
                        </p>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <div class="product-card"
                                 data-id="<?= (int)$p['id'] ?>"
                                 data-name="<?= htmlspecialchars($p['name']) ?>"
                                 data-price="<?= (int)$p['price'] ?>"
                                 data-category="<?= htmlspecialchars($p['category']) ?>"
                                 onclick="addToCart(this)">
                                <div class="img-wrapper">
                                    <span class="product-img-placeholder" style="font-size:3rem;display:flex;align-items:center;justify-content:center;height:120px;">
                                        <?php if ($p['image']): ?>
                                            <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="width:100%;height:100%;object-fit:cover;">
                                        <?php else: ?>
                                            <span class="material-symbols-outlined" style="font-size:3rem;color:var(--primary-light);">local_cafe</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="price-badge"><?= (int)$p['price'] ?> LE</span>
                                </div>
                                <div class="product-body">
                                    <div>
                                        <h4 class="product-name"><?= htmlspecialchars($p['name']) ?></h4>
                                        <p class="product-desc"><?= htmlspecialchars($p['description'] ?: $p['category']) ?></p>
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

    <!-- Hand the user.js script some PHP-derived bootstrap data -->
    <script>
        const CAFETRIA_USER = {
            id:   <?= $userId ?>,
            name: <?= json_encode($userName) ?>,
            room: <?= json_encode($myRoom) ?>
        };
    </script>
    <script src="../script.js"></script>
    <script src="user.js"></script>
</body>
</html>
