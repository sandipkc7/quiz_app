<?php
/**
 * Utility Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

/**
 * Sanitize output for HTML display
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect(string $path): void {
    header("Location: " . BASE_URL . $path);
    exit;
}

/**
 * Set a flash message
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages
 */
function get_flash(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function is_admin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Get current user info
 */
function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['user_role']
    ];
}

/**
 * Require authentication — redirect to login if not logged in
 */
function require_auth(): void {
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('/auth/login.php');
    }
}

/**
 * Require admin role
 */
function require_admin(): void {
    require_auth();
    if (!is_admin()) {
        flash('error', 'Access denied. Admin privileges required.');
        redirect('/dashboard.php');
    }
}
