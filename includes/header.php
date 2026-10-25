<?php
// includes/header.php - Global Header & Navigation
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();
$user = current_user();
$settings = get_settings($pdo);
$shop_name = $settings['shop_name'] ?? 'ClothesRetail POS';
$active_register = get_active_register($pdo, $user['id']);

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title ?? 'Dashboard') ?> - <?= htmlspecialchars($shop_name) ?></title>
    
    <!-- Bootstrap 5 CSS (Local) -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <!-- Bootstrap Icons (Local) -->
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <!-- Custom Clothes Retail CSS -->
    <link rel="stylesheet" href="assets/css/clothes-retail.css">
    
    <!-- Core JS Dependencies (Local) -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/chart.min.js"></script>
    <script src="assets/js/JsBarcode.all.min.js"></script>
</head>
<body>

<!-- Mobile Sidebar Backdrop -->
<div class="sidebar-backdrop no-print" id="sidebarBackdrop"></div>

<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div id="sidebar-wrapper" class="no-print">
        <div class="brand-logo">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shop-window text-primary fs-4"></i>
                <div class="lh-1">
                    <div style="font-size: 1.05rem;"><?= htmlspecialchars($shop_name) ?></div>
                    <small class="text-muted fw-normal" style="font-size: 0.68rem;">Retail & Khata ERP</small>
                </div>
            </div>
            <!-- Close Button for Mobile -->
            <button class="btn btn-sm btn-link text-white-50 d-lg-none p-0" id="sidebarCloseBtn">
                <i class="bi bi-x-lg fs-5"></i>
            </button>
        </div>

        <div class="overflow-auto" style="height: calc(100vh - 65px);">
            <div class="nav-section">Main</div>
            <a href="index.php" class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="pos.php" class="nav-link <?= $current_page == 'pos.php' ? 'active' : '' ?>">
                <i class="bi bi-upc-scan text-warning"></i> <strong>POS Checkout</strong>
            </a>

            <div class="nav-section">Catalog & Variants</div>
            <a href="products.php" class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i> Products Catalog
            </a>
            <a href="product_matrix.php" class="nav-link <?= $current_page == 'product_matrix.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap"></i> Size & Color Matrix
            </a>
            <a href="categories.php" class="nav-link <?= $current_page == 'categories.php' ? 'active' : '' ?>">
                <i class="bi bi-folder2"></i> Categories
            </a>
            <a href="brands.php" class="nav-link <?= $current_page == 'brands.php' ? 'active' : '' ?>">
                <i class="bi bi-patch-check"></i> Brands
            </a>
            <a href="sizes_colors.php" class="nav-link <?= $current_page == 'sizes_colors.php' ? 'active' : '' ?>">
                <i class="bi bi-palette"></i> Sizes & Colors
            </a>

            <div class="nav-section">Stock & Purchases</div>
            <a href="inventory.php" class="nav-link <?= $current_page == 'inventory.php' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i> Stock Inventory
            </a>
            <a href="purchases.php" class="nav-link <?= in_array($current_page, ['purchases.php', 'purchase_create.php']) ? 'active' : '' ?>">
                <i class="bi bi-cart-plus"></i> Supplier Purchases
            </a>
            <a href="suppliers.php" class="nav-link <?= in_array($current_page, ['suppliers.php', 'supplier_ledger.php']) ? 'active' : '' ?>">
                <i class="bi bi-truck"></i> Suppliers & Khata
            </a>

            <div class="nav-section">Sales & Customers</div>
            <a href="customers.php" class="nav-link <?= in_array($current_page, ['customers.php', 'customer_ledger.php']) ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Customers & Khata
            </a>
            <a href="returns.php" class="nav-link <?= $current_page == 'returns.php' ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i> Returns & Exchanges
            </a>
            <a href="barcodes.php" class="nav-link <?= $current_page == 'barcodes.php' ? 'active' : '' ?>">
                <i class="bi bi-barcode"></i> Barcode Labels
            </a>

            <div class="nav-section">Finance & Register</div>
            <a href="expenses.php" class="nav-link <?= $current_page == 'expenses.php' ? 'active' : '' ?>">
                <i class="bi bi-wallet2"></i> Store Expenses
            </a>
            <a href="cash_register.php" class="nav-link <?= $current_page == 'cash_register.php' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> Cash Register (Shifts)
            </a>
            <a href="reports.php" class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i> Financial Reports
            </a>

            <?php if (has_role('admin', 'manager')): ?>
            <div class="nav-section">Administration</div>
            <a href="users.php" class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i> Users & Roles
            </a>
            <a href="settings.php" class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Store Settings
            </a>
            <?php endif; ?>

            <div class="p-3">
                <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>
    <!-- /#sidebar-wrapper -->

    <!-- Page Content -->
    <div id="page-content-wrapper" class="flex-grow-1 d-flex flex-column" style="min-width: 0;">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand top-navbar no-print">
            <div class="container-fluid px-2 px-md-3">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary d-lg-none p-1 px-2" id="sidebarToggle" aria-label="Toggle Navigation">
                        <i class="bi bi-list fs-5"></i>
                    </button>
                    <h5 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 170px;"><?= htmlspecialchars($page_title ?? 'Overview') ?></h5>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto">
                    <!-- Cash Register Shift Pill -->
                    <?php if ($active_register): ?>
                        <a href="cash_register.php" class="badge bg-success-subtle text-success border border-success text-decoration-none px-2 py-1 rounded-pill small">
                            <i class="bi bi-circle-fill text-success" style="font-size: 7px;"></i> <span class="d-none d-sm-inline">Shift Active</span> #<?= $active_register['id'] ?>
                        </a>
                    <?php else: ?>
                        <a href="cash_register.php" class="badge bg-warning-subtle text-warning border border-warning text-decoration-none px-2 py-1 rounded-pill small">
                            <i class="bi bi-exclamation-circle text-warning"></i> <span class="d-none d-sm-inline">Open Register</span>
                        </a>
                    <?php endif; ?>

                    <!-- POS Shortcut Button -->
                    <a href="pos.php" class="btn btn-primary btn-sm px-2 px-sm-3 shadow-sm d-none d-md-inline-flex align-items-center gap-1">
                        <i class="bi bi-upc-scan"></i> Open POS
                    </a>

                    <?php 
                        $displayName = (string)($user['full_name'] ?? $user['username'] ?? 'User');
                        $avatarLetter = !empty($displayName) ? strtoupper(substr($displayName, 0, 1)) : 'U';
                    ?>
                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-1 border p-1 px-2" type="button" data-bs-toggle="dropdown">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; font-size: 12px;">
                                <?= $avatarLetter ?>
                            </div>
                            <span class="d-none d-sm-inline fw-semibold small"><?= htmlspecialchars($displayName) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            <li><span class="dropdown-item-text text-muted small">Signed in as <strong><?= htmlspecialchars((string)($user['username'] ?? 'user')) ?></strong></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="cash_register.php"><i class="bi bi-cash-stack me-2"></i> Register Shift</a></li>
                            <?php if (has_role('admin')): ?>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i> Store Settings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="p-2 p-sm-3 p-md-4 flex-grow-1">
            <?= render_alerts() ?>
