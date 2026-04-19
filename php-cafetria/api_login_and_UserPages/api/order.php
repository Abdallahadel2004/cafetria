<?php
/**
 * api/order.php — JSON endpoint for user-side order actions
 *
 * Always returns JSON. Called from user.js via fetch().
 *
 * Accepted actions (POST body JSON):
 *
 *   { "action": "place",
 *     "items":  [ { "id": 1, "qty": 2 }, { "id": 4, "qty": 1 } ],
 *     "room":   "201",
 *     "notes":  "Extra sugar" }
 *
 *   { "action": "cancel", "id": 42 }   // Only if order belongs to user AND is Processing
 *
 *   { "action": "reorder", "id": 42 }  // Returns the items of a previous order
 *                                      // so the front-end can refill the cart.
 */

session_start();
header('Content-Type: application/json');

// ── Auth guard ───────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
// Only normal users may use this endpoint. Admins have their own.
if (($_SESSION['role'] ?? 'user') === 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Use the admin API']);
    exit;
}

require_once __DIR__ . '/../db.php';

$userId = (int)$_SESSION['user_id'];
$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

// ════════════════════════════════════════════════════════════════════════
//  PLACE — create a new order from a cart
// ════════════════════════════════════════════════════════════════════════
if ($action === 'place') {
    $items = $body['items'] ?? [];
    $room  = trim((string)($body['room']  ?? ''));
    $notes = trim((string)($body['notes'] ?? ''));

    if (!is_array($items) || count($items) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cart is empty']);
        exit;
    }
    if ($room === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please choose a delivery room']);
        exit;
    }

    // Normalize cart (id => qty) and look the products up server-side so
    // we never trust the price / availability sent by the browser.
    $cart = [];
    foreach ($items as $it) {
        $pid = (int)($it['id']  ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $cart[$pid] = ($cart[$pid] ?? 0) + $qty;
        }
    }
    if (!$cart) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cart is empty']);
        exit;
    }

    $ids   = array_keys($cart);
    $place = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT id, name, price, status
        FROM   products
        WHERE  id IN ($place)
    ");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    if (count($products) !== count($ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'One or more products no longer exist']);
        exit;
    }

    $total      = 0;
    $summary    = [];
    $linePrices = [];     // pid => unit_price (for stats / order_items if you add it)
    foreach ($products as $p) {
        if (strtolower($p['status']) !== 'available') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "'{$p['name']}' is no longer available"]);
            exit;
        }
        $pid       = (int)$p['id'];
        $qty       = $cart[$pid];
        $linePrice = (int)$p['price'];

        $total              += $linePrice * $qty;
        $summary[]           = "{$qty}x {$p['name']}";
        $linePrices[$pid]    = $linePrice;
    }
    $itemsSummary = implode(', ', $summary);

    // ── Insert (transaction to insert order and items) ──
    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare("
            INSERT INTO orders (user_id, notes, room, total, status, items_summary)
            VALUES             (:uid,    :notes, :room, :total, 'processing', :summary)
        ");
        $ins->execute([
            ':uid'     => $userId,
            ':notes'   => $notes,
            ':room'    => $room,
            ':total'   => $total,
            ':summary' => $itemsSummary,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Insert individual items into order_items
        $insItem = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, unit_price)
            VALUES                 (:oid,     :pid,        :qty,     :price)
        ");
        foreach ($products as $p) {
            $pid = (int)$p['id'];
            $insItem->execute([
                ':oid'   => $orderId,
                ':pid'   => $pid,
                ':qty'   => $cart[$pid],
                ':price' => $p['price']
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not place order: ' . $e->getMessage()]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'id'      => $orderId,
        'total'   => $total,
        'summary' => $itemsSummary,
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════
//  CANCEL — user cancels their own Processing order
// ════════════════════════════════════════════════════════════════════════
if ($action === 'cancel') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing order ID']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE orders
        SET    status = 'cancelled'
        WHERE  id = :id
          AND  user_id = :uid
          AND  status  = 'processing'
    ");
    $stmt->execute([':id' => $id, ':uid' => $userId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'Order not found or no longer cancellable']);
        exit;
    }
    echo json_encode(['success' => true, 'id' => $id, 'newStatus' => 'Cancelled']);
    exit;
}

// ════════════════════════════════════════════════════════════════════════
//  REORDER — fetch a previous order's items so JS can prefill the cart.
//  Items the user no longer recognizes (deleted / unavailable products)
//  are silently skipped; the front-end shows whatever's still orderable.
// ════════════════════════════════════════════════════════════════════════
if ($action === 'reorder') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing order ID']);
        exit;
    }

    // Verify the order belongs to this user and get items
    $stmt = $pdo->prepare("
        SELECT oi.product_id AS id, p.name, oi.unit_price AS price, oi.quantity AS qty
        FROM   order_items oi
        JOIN   products p ON oi.product_id = p.id
        JOIN   orders o ON oi.order_id = o.id
        WHERE  o.id = :id AND o.user_id = :uid
    ");
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $cartItems = $stmt->fetchAll();

    if (!$cartItems) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found or empty']);
        exit;
    }

    echo json_encode(['success' => true, 'items' => $cartItems]);
    exit;
}

// ── Unknown action ───────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action']);
