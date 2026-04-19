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
        if ($p['status'] !== 'Available') {
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

    // ── Insert (transaction so we also bump products.total_orders atomically) ──
    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare("
            INSERT INTO orders (user_id, items_summary, total, status, notes, created_at)
            VALUES             (:uid,    :summary,      :total, 'Processing', :notes, NOW())
        ");
        $ins->execute([
            ':uid'     => $userId,
            ':summary' => $itemsSummary,
            ':total'   => $total,
            ':notes'   => $notes,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Bump the per-product order counter.
        $bump = $pdo->prepare("
            UPDATE products
            SET    total_orders = total_orders + :qty
            WHERE  id = :id
        ");
        foreach ($cart as $pid => $qty) {
            $bump->execute([':qty' => $qty, ':id' => $pid]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not place order']);
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
        SET    status = 'Cancelled', updated_at = NOW()
        WHERE  id = :id
          AND  user_id = :uid
          AND  status  = 'Processing'
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

    // Verify the order belongs to this user.
    $stmt = $pdo->prepare("
        SELECT items_summary
        FROM   orders
        WHERE  id = :id AND user_id = :uid
        LIMIT  1
    ");
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    // Parse "2x Coffee, 1x Tea" -> [name => qty]
    $wanted = [];
    foreach (preg_split('/\s*,\s*/', trim((string)$row['items_summary'])) as $part) {
        if ($part === '') continue;
        if (preg_match('/^(\d+)\s*x\s*(.+)$/i', $part, $m)) {
            $wanted[trim($m[2])] = (int)$m[1];
        }
    }
    if (!$wanted) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }

    // Look the names back up against the live (still-Available) catalog.
    $place = implode(',', array_fill(0, count($wanted), '?'));
    $stmt  = $pdo->prepare("
        SELECT id, name, price
        FROM   products
        WHERE  status = 'Available' AND name IN ($place)
    ");
    $stmt->execute(array_keys($wanted));

    $cartItems = [];
    foreach ($stmt->fetchAll() as $p) {
        $cartItems[] = [
            'id'    => (int)$p['id'],
            'name'  => $p['name'],
            'price' => (int)$p['price'],
            'qty'   => $wanted[$p['name']] ?? 1,
        ];
    }

    echo json_encode(['success' => true, 'items' => $cartItems]);
    exit;
}

// ── Unknown action ───────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action']);
