<?php
/**
 * db.php — PDO database connection for the user-side API.
 *
 * Include from this folder:    require_once __DIR__ . '/db.php';
 * Include from api/ subfolder: require_once __DIR__ . '/../db.php';
 *
 * Provides: $pdo (PDO instance, exception mode, FETCH_ASSOC default)
 *
 * NOTE: Credentials match php-cafetria/admin/admin/db.php so both
 * sides of the app talk to the same `cafetria` database.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'cafetria');
define('DB_USER', 'mahmoud');       // ← change to your DB user
define('DB_PASS', '123456');           // ← change to your DB password
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
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database connection faddiled']));
}