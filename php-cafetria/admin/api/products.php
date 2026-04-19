<?php
/**
 * api/products.php — AJAX endpoint for product CRUD
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

function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)));
}

if ($action === 'add') {
    $name     = clean($body['name']     ?? '');
    $category = clean($body['category'] ?? '');
    $price    = (int)($body['price']    ?? 0);
    $status   = in_array($body['status'] ?? '', ['Available','Unavailable'])
                ? $body['status'] : 'Available';
    $emoji    = mb_substr(clean($body['emoji'] ?? '🍽️'), 0, 2);
    $desc     = clean($body['desc']     ?? '');

    if (!$name || !$category || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Name, category and price are required']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO products (name, category, price, status, emoji, description, total_orders, created_at)
        VALUES (:name, :category, :price, :status, :emoji, :desc, 0, NOW())
    ");
    $stmt->execute([
        ':name'     => $name,
        ':category' => $category,
        ':price'    => $price,
        ':status'   => $status,
        ':emoji'    => $emoji,
        ':desc'     => $desc,
    ]);

    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($action === 'edit') {
    $id       = (int)($body['id']       ?? 0);
    $name     = clean($body['name']     ?? '');
    $category = clean($body['category'] ?? '');
    $price    = (int)($body['price']    ?? 0);
    $status   = in_array($body['status'] ?? '', ['Available','Unavailable'])
                ? $body['status'] : 'Available';
    $emoji    = mb_substr(clean($body['emoji'] ?? '🍽️'), 0, 2);
    $desc     = clean($body['desc']     ?? '');

    if (!$id || !$name || !$category || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE products
        SET    name = :name, category = :category, price = :price,
               status = :status, emoji = :emoji, description = :desc,
               updated_at = NOW()
        WHERE  id = :id
    ");
    $stmt->execute([
        ':name'     => $name,
        ':category' => $category,
        ':price'    => $price,
        ':status'   => $status,
        ':emoji'    => $emoji,
        ':desc'     => $desc,
        ':id'       => $id,
    ]);

    echo json_encode(['success' => true]);
    exit;
}

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

if ($action === 'toggle') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing ID']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE products
        SET    status = IF(status = 'Available', 'Unavailable', 'Available'),
               updated_at = NOW()
        WHERE  id = ?
    ");
    $stmt->execute([$id]);

    $newStatus = $pdo->query("SELECT status FROM products WHERE id = $id")->fetchColumn();
    echo json_encode(['success' => true, 'newStatus' => $newStatus]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action']);
