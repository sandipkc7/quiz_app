<?php
/**
 * Entry Point — Redirect to dashboard or login
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
} else {
    redirect('/auth/login.php');
}
