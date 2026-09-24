<?php
/**
 * MarketLink - Logout Handler
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset and destroy all session data
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Start a fresh session for the flash notification
session_start();
set_flash('info', 'You have been successfully logged out. See you soon!');
redirect(BASE_URL . 'auth/login.php');
