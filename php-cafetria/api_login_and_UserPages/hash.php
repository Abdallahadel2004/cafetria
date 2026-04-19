<?php
/**
 * hash.php — tiny helper for generating bcrypt password hashes.
 *
 * Run from the command line:
 *     php hash.php mypassword
 *
 * Or open in a browser (development only — DELETE before deploying!):
 *     http://localhost/php-cafetria/api_login_and_UserPages/hash.php?p=mypassword
 *
 * Then paste the printed hash into the `password` column of the `users`
 * table (or use it inside an INSERT statement).
 */

// CLI usage
if (PHP_SAPI === 'cli') {
    if ($argc < 2) {
        echo "Usage: php hash.php <password>\n";
        exit(1);
    }
    echo password_hash($argv[1], PASSWORD_DEFAULT) . PHP_EOL;
    exit(0);
}

// Browser usage (dev only)
header('Content-Type: text/plain; charset=utf-8');
$pw = $_GET['p'] ?? '';
if ($pw === '') {
    echo "Add ?p=yourpassword to the URL.\nExample: hash.php?p=hello123\n";
    exit;
}
echo password_hash($pw, PASSWORD_DEFAULT) . "\n";
echo "\nSQL snippet:\n";
echo "  UPDATE users SET password = '" . password_hash($pw, PASSWORD_DEFAULT) . "' WHERE email = 'someone@cafetria.com';\n";
