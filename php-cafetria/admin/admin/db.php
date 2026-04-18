<?php
/**
 * db.php — PDO database connection
 *
 * Include with: require_once '../db.php';  (from admin/)
 *               require_once '../../db.php'; (from admin/api/)
 *
 * Provides: $pdo
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'cafetria');
define('DB_USER', 'root');       // ← change to your DB user
define('DB_PASS', '');           // ← change to your DB password
define('DB_CHAR', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // In production, log and show a friendly error instead
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}
