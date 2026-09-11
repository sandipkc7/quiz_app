<?php
/**
 * Auth Guard — include this at the top of any protected page
 */
require_once __DIR__ . '/functions.php';
require_auth();
$current_user = current_user();
