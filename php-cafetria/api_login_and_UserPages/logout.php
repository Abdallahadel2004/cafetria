<?php
/**
 * logout.php — destroys the session and returns to login.
 *
 * Usage: <a href="api_login_and_UserPages/logout.php">Logout</a>
 */

session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ../login.php');
exit;
