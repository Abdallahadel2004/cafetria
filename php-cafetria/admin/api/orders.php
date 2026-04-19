<?php
/**
 * api/orders.php — AJAX endpoint for order actions
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

// Parse JSON body
$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';
$id     = isset($body['id']) ? (int)$body['id'] : 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit;
}

$transitions = [
    'deliver' => 'Delivered',
    'cancel'  => 'Cancelled',
];

if (!array_key_exists($action, $transitions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$newStatus = $transitions[$action];

$stmt = $pdo->prepare("
    UPDATE orders
    SET    status = :status, updated_at = NOW()
    WHERE  id = :id AND status = 'Processing'
");
$stmt->execute([':status' => $newStatus, ':id' => $id]);

if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'error' => 'Order not found or already updated']);
    exit;
}

echo json_encode(['success' => true, 'id' => $id, 'newStatus' => $newStatus]);
