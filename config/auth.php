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

// Auto-ensure allowed_pages column exists in users table
static $allowedPagesColChecked = false;
if (!$allowedPagesColChecked) {
    try {
        $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'allowed_pages'")->fetch();
        if (!$chk) {
            $pdo->exec("ALTER TABLE users ADD COLUMN allowed_pages TEXT NULL AFTER phone");
        }
        $allowedPagesColChecked = true;
    } catch (Exception $e) {}
}

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

// Full catalogue of system pages grouped by module
function get_system_pages() {
    return [
        'Main' => [
            'index.php' => ['title' => 'Dashboard', 'icon' => 'bi-speedometer2', 'aliases' => []],
            'pos.php' => ['title' => 'POS Checkout', 'icon' => 'bi-upc-scan', 'aliases' => []],
        ],
        'Catalog & Variants' => [
            'products.php' => ['title' => 'Products & Fabrics', 'icon' => 'bi-tags', 'aliases' => ['product_form.php', 'product_create.php', 'product_edit.php']],
            'product_matrix.php' => ['title' => 'Size & Color Matrix', 'icon' => 'bi-grid-3x3-gap', 'aliases' => []],
            'categories.php' => ['title' => 'Categories', 'icon' => 'bi-folder2', 'aliases' => []],
            'brands.php' => ['title' => 'Brands', 'icon' => 'bi-patch-check', 'aliases' => []],
            'sizes_colors.php' => ['title' => 'Sizes & Colors', 'icon' => 'bi-palette', 'aliases' => []],
        ],
        'Stock & Purchases' => [
            'inventory.php' => ['title' => 'Stock Inventory', 'icon' => 'bi-box-seam', 'aliases' => []],
            'purchases.php' => ['title' => 'Supplier Purchases', 'icon' => 'bi-cart-plus', 'aliases' => ['purchase_create.php']],
            'suppliers.php' => ['title' => 'Suppliers & Khata', 'icon' => 'bi-truck', 'aliases' => ['supplier_ledger.php']],
        ],
        'Sales & Customers' => [
            'customers.php' => ['title' => 'Customers & Khata', 'icon' => 'bi-people', 'aliases' => ['customer_ledger.php']],
            'returns.php' => ['title' => 'Returns & Exchanges', 'icon' => 'bi-arrow-left-right', 'aliases' => []],
            'barcodes.php' => ['title' => 'Barcode Labels', 'icon' => 'bi-barcode', 'aliases' => []],
        ],
        'Finance & Register' => [
            'expenses.php' => ['title' => 'Store Expenses', 'icon' => 'bi-wallet2', 'aliases' => []],
            'cash_register.php' => ['title' => 'Cash Register (Shifts)', 'icon' => 'bi-cash-stack', 'aliases' => []],
            'reports.php' => ['title' => 'Financial Reports', 'icon' => 'bi-bar-chart-line', 'aliases' => []],
        ],
        'Administration' => [
            'users.php' => ['title' => 'Users & Permissions', 'icon' => 'bi-person-badge', 'aliases' => []],
            'settings.php' => ['title' => 'Store Settings', 'icon' => 'bi-gear', 'aliases' => []],
        ],
    ];
}

// Fetch allowed pages array for a user (or 'all' for admin)
function get_user_allowed_pages($user_id) {
    global $pdo;
    static $cache = [];
    if (isset($cache[$user_id])) {
        return $cache[$user_id];
    }

    try {
        $stmt = $pdo->prepare("SELECT role, allowed_pages FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        if ($row['role'] === 'admin') {
            $cache[$user_id] = 'all';
            return 'all';
        }

        if (!empty($row['allowed_pages'])) {
            $decoded = json_decode($row['allowed_pages'], true);
            if (is_array($decoded)) {
                $cache[$user_id] = $decoded;
                return $decoded;
            }
        }
    } catch (Exception $e) {}

    $cache[$user_id] = null;
    return null;
}

// Check if user has granular access to a specific page
function can_access_page($page, $user = null) {
    if (!is_logged_in()) return false;
    if ($user === null) $user = current_user();
    if (!$user) return false;

    // Admin always has universal access to all pages
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    $cleanPage = basename($page);

    // Whitelist utility endpoints
    $publicPages = ['login.php', 'logout.php', 'print_receipt.php', 'print_invoice.php'];
    if (in_array($cleanPage, $publicPages)) {
        return true;
    }

    $allowed = get_user_allowed_pages($user['id']);

    // If assigned 'all' (admin)
    if ($allowed === 'all') {
        return true;
    }

    // If explicit manual page permissions are saved for this user
    if (is_array($allowed)) {
        if (in_array($cleanPage, $allowed)) {
            return true;
        }

        // Check aliases (e.g. purchase_create.php is an alias for purchases.php)
        $allSections = get_system_pages();
        foreach ($allSections as $section => $pages) {
            foreach ($pages as $mainPage => $info) {
                if (in_array($cleanPage, $info['aliases'] ?? [])) {
                    return in_array($mainPage, $allowed);
                }
            }
        }
        return false;
    }

    // Fallback default role presets if admin has not manually configured user yet
    $role = $user['role'] ?? 'cashier';
    if ($role === 'manager') {
        return !in_array($cleanPage, ['settings.php', 'users.php']);
    } elseif ($role === 'cashier') {
        return in_array($cleanPage, ['pos.php', 'cash_register.php', 'customers.php', 'customer_ledger.php', 'returns.php']);
    } elseif ($role === 'inventory') {
        return in_array($cleanPage, ['products.php', 'product_form.php', 'product_matrix.php', 'categories.php', 'brands.php', 'sizes_colors.php', 'inventory.php', 'purchases.php', 'purchase_create.php', 'suppliers.php', 'supplier_ledger.php', 'barcodes.php']);
    } elseif ($role === 'accountant') {
        return in_array($cleanPage, ['expenses.php', 'reports.php', 'suppliers.php', 'supplier_ledger.php', 'customers.php', 'customer_ledger.php', 'cash_register.php']);
    }

    return false;
}

// Resolve the first accessible landing page for user redirect
function get_first_accessible_page($user) {
    if (can_access_page('index.php', $user)) return 'index.php';
    if (can_access_page('pos.php', $user)) return 'pos.php';

    $sections = get_system_pages();
    foreach ($sections as $cat => $pages) {
        foreach ($pages as $p => $info) {
            if (can_access_page($p, $user)) {
                return $p;
            }
        }
    }
    return 'login.php';
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
