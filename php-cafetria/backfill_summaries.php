<?php
require_once 'db.php';

echo "Starting backfill of items_summary...\n";

$orders = $pdo->query("SELECT id FROM orders WHERE items_summary IS NULL OR items_summary = '' OR items_summary = '—'")->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders as $o) {
    $oid = $o['id'];
    $stmt = $pdo->prepare("
        SELECT oi.quantity, p.name
        FROM   order_items oi
        JOIN   products p ON oi.product_id = p.id
        WHERE  oi.order_id = ?
    ");
    $stmt->execute([$oid]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = [];
    foreach ($items as $it) {
        $summary[] = "{$it['quantity']}x {$it['name']}";
    }
    $itemsSummary = implode(', ', $summary);
    
    if ($itemsSummary) {
        $upd = $pdo->prepare("UPDATE orders SET items_summary = ? WHERE id = ?");
        $upd->execute([$itemsSummary, $oid]);
        echo "Updated Order #$oid: $itemsSummary\n";
    } else {
        echo "Order #$oid has no items.\n";
    }
}

echo "Backfill complete.\n";
