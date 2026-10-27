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

// Enforce Granular Page-Level Access Control
if (!can_access_page($current_page, $user)) {
    $target = get_first_accessible_page($user);
    $_SESSION['flash_error'] = "Access Restricted: You do not have permission to view '$current_page'. Please contact your administrator.";
    header("Location: $target");
    exit;
}
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

        <div class="sidebar-nav">
            <?php
            $system_sections = get_system_pages();
            foreach ($system_sections as $section_name => $section_pages):
                $accessible_items = [];
                foreach ($section_pages as $page_url => $page_info) {
                    if (can_access_page($page_url, $user)) {
                        $accessible_items[$page_url] = $page_info;
                    }
                }
                if (empty($accessible_items)) continue;
            ?>
                <div class="nav-section"><?= htmlspecialchars($section_name) ?></div>
                <?php foreach ($accessible_items as $page_url => $page_info): 
                    $isActive = ($current_page == $page_url || in_array($current_page, $page_info['aliases'] ?? []));
                ?>
                    <a href="<?= $page_url ?>" class="nav-link <?= $isActive ? 'active' : '' ?>">
                        <i class="bi <?= $page_info['icon'] ?> <?= $page_url === 'pos.php' ? 'text-warning' : '' ?>"></i>
                        <?= $page_url === 'pos.php' ? '<strong>' . htmlspecialchars($page_info['title']) . '</strong>' : htmlspecialchars($page_info['title']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-footer">
            <a href="logout.php" class="btn btn-outline-danger w-100 btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
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
                    <h5 class="mb-0 fw-bold text-dark text-nowrap"><?= htmlspecialchars($page_title ?? 'Overview') ?></h5>
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
