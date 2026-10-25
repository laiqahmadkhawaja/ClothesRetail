<?php
// logout.php - Session Destroy & Logout
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    log_audit($pdo, 'Logout', 'Auth', 'User logged out');
}

session_unset();
session_destroy();

header("Location: login.php");
exit;
