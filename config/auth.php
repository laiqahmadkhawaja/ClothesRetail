<?php
// config/auth.php - Authentication and Session Management
if (!ob_get_level()) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('CLOTHES_RETAIL_SESSID');
    session_start();
}

require_once __DIR__ . '/database.php';

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function current_user() {
    global $pdo;
    if (!is_logged_in()) {
        return null;
    }

    // Auto-hydrate user details from database if missing from session
    if (empty($_SESSION['username']) || empty($_SESSION['full_name']) || empty($_SESSION['role'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, full_name, role FROM users WHERE id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $dbUser = $stmt->fetch();
            if ($dbUser) {
                $_SESSION['username'] = $dbUser['username'];
                $_SESSION['full_name'] = $dbUser['full_name'];
                $_SESSION['role'] = $dbUser['role'];
            } else {
                unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['full_name'], $_SESSION['role']);
                return null;
            }
        } catch (Exception $e) {
            // fallback
        }
    }

    return [
        'id'        => $_SESSION['user_id'] ?? 0,
        'username'  => (string)($_SESSION['username'] ?? 'user'),
        'full_name' => (string)($_SESSION['full_name'] ?? 'Staff User'),
        'role'      => (string)($_SESSION['role'] ?? 'cashier'),
    ];
}

function require_login() {
    if (!is_logged_in() || current_user() === null) {
        header("Location: login.php");
        exit;
    }
}

function has_role(...$roles) {
    if (!is_logged_in()) return false;
    $current_role = $_SESSION['role'] ?? '';
    if ($current_role === 'admin') return true; // Admin has universal access
    return in_array($current_role, $roles);
}

function require_role(...$roles) {
    require_login();
    if (!has_role(...$roles)) {
        header("Location: index.php?error=" . urlencode("Access Denied: You do not have permission to view this section."));
        exit;
    }
}

function log_audit($pdo, $action, $module, $details = '') {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $module, $details, $ip]);
    } catch (Exception $e) {
        // silent fail on audit log so core transaction isn't broken
    }
}

function get_active_register($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE cashier_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}
