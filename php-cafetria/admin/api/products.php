<?php
/**
 * api/products.php — AJAX endpoint for product CRUD
 *
 * Called by admin.js via fetch().
 * Always returns JSON.
 *
 * Accepted actions (POST body JSON):
 *   { "action": "add",    "name":..., "category":..., "price":..., "status":..., "emoji":..., "desc":... }
 *   { "action": "edit",   "id":42, "name":..., "category":..., "price":..., "status":..., "emoji":..., "desc":... }
 *   { "action": "delete", "id": 42 }
 *   { "action": "toggle", "id": 42 }
 */
session_start();
header('Content-Type: application/json');

// Auth guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db.php';

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

// ── Helper: sanitize string ───────────────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)));
}

// ── ADD ──────────────────────────────────────────────────────────────────
if ($action === 'add') {
    $name        = clean($body['name']        ?? '');
    $category_id = (int)($body['category_id'] ?? 0);
    $price       = (int)($body['price']       ?? 0);
    $status      = in_array($body['status'] ?? '', ['Available','Unavailable'])
                    ? $body['status'] : 'Available';
    $image       = clean($body['image'] ?? '');
    $desc        = clean($body['desc']  ?? '');

    if (!$name || !$category_id || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name, category and price are required']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO products (name, category_id, price, status, image, description, total_orders, created_at)
        VALUES (:name, :category_id, :price, :status, :image, :desc, 0, NOW())
    ");
    $stmt->execute([
        ':name'        => $name,
        ':category_id' => $category_id,
        ':price'       => $price,
        ':status'      => $status,
        ':image'       => $image,
        ':desc'        => $desc,
    ]);

    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

// ── EDIT ─────────────────────────────────────────────────────────────────
if ($action === 'edit') {
    $id          = (int)($body['id']          ?? 0);
    $name        = clean($body['name']        ?? '');
    $category_id = (int)($body['category_id'] ?? 0);
    $price       = (int)($body['price']       ?? 0);
    $status      = in_array($body['status'] ?? '', ['Available','Unavailable'])
                    ? $body['status'] : 'Available';
    $image       = clean($body['image'] ?? '');
    $desc        = clean($body['desc']  ?? '');

    if (!$id || !$name || !$category_id || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE products
        SET    name = :name, category_id = :category_id, price = :price,
               status = :status, image = :image, description = :desc
        WHERE  id = :id
    ");
    $stmt->execute([
        ':name'        => $name,
        ':category_id' => $category_id,
        ':price'       => $price,
        ':status'      => $status,
        ':image'       => $image,
        ':desc'        => $desc,
        ':id'          => $id,
    ]);

    echo json_encode(['success' => true]);
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing ID']);
        exit;
    }
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// ── TOGGLE availability ───────────────────────────────────────────────────
if ($action === 'toggle') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing ID']);
        exit;
    }

    // Flip the status in one query
    $stmt = $pdo->prepare("
        UPDATE products
        SET    status = IF(status = 'Available', 'Unavailable', 'Available')
        WHERE  id = ?
    ");
    $stmt->execute([$id]);

    // Return the new status so JS can update the badge without reloading
    $newStatus = $pdo->query("SELECT status FROM products WHERE id = $id")->fetchColumn();
    echo json_encode(['success' => true, 'newStatus' => $newStatus]);
    exit;
}

// ── Unknown action ────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action']);
