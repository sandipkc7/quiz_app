<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/../includes/functions.php';

// Destroy session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Start new session for flash message
session_start();
flash('success', 'You have been logged out successfully.');
redirect('/auth/login.php');
