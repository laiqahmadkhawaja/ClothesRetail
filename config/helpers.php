<?php
// config/helpers.php - General Helper Utilities

function format_currency($amount, $currency = null) {
    if ($currency === null) {
        $currency = 'Rs. ';
    }
    return $currency . number_format((float)$amount, 0);
}

function format_date($datetime, $include_time = true) {
    if (empty($datetime)) return '-';
    $time = strtotime($datetime);
    if (!$time) return '-';
    return $include_time ? date('d M Y, h:i A', $time) : date('d M Y', $time);
}

function sanitize($input) {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function status_badge($status) {
    $status = strtolower((string)$status);
    $map = [
        'active'    => 'bg-success',
        'completed' => 'bg-success',
        'paid'      => 'bg-success',
        'open'      => 'bg-success',
        'restock'   => 'bg-info',
        'partial'   => 'bg-warning text-dark',
        'held'      => 'bg-warning text-dark',
        'due'       => 'bg-danger',
        'inactive'  => 'bg-secondary',
        'closed'    => 'bg-secondary',
        'cancelled' => 'bg-danger',
        'returned'  => 'bg-danger',
        'defective' => 'bg-danger',
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . ucfirst($status) . '</span>';
}

function role_badge($role) {
    $role = strtolower((string)$role);
    $map = [
        'admin'      => 'bg-dark',
        'manager'    => 'bg-primary',
        'cashier'    => 'bg-success',
        'inventory'  => 'bg-info text-dark',
        'accountant' => 'bg-warning text-dark',
    ];
    $cls = $map[$role] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . ucfirst($role) . '</span>';
}

function payment_badge($method) {
    $method = strtolower((string)$method);
    $map = [
        'cash'      => '<span class="badge bg-success"><i class="bi bi-cash"></i> Cash</span>',
        'card'      => '<span class="badge bg-primary"><i class="bi bi-credit-card"></i> Card</span>',
        'bank'      => '<span class="badge bg-info text-dark"><i class="bi bi-bank"></i> Bank</span>',
        'jazzcash'  => '<span class="badge bg-danger"><i class="bi bi-phone"></i> JazzCash</span>',
        'easypaisa' => '<span class="badge bg-success"><i class="bi bi-wallet2"></i> Easypaisa</span>',
        'split'     => '<span class="badge bg-secondary"><i class="bi bi-pie-chart"></i> Split</span>',
    ];
    return $map[$method] ?? '<span class="badge bg-light text-dark">' . ucfirst($method) . '</span>';
}

function render_alerts() {
    $output = '';
    if (isset($_GET['success'])) {
        $output .= '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> ' . sanitize($_GET['success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
    if (isset($_GET['error'])) {
        $output .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> ' . sanitize($_GET['error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
    if (isset($_SESSION['flash_success'])) {
        $output .= '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> ' . sanitize($_SESSION['flash_success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        unset($_SESSION['flash_success']);
    }
    if (isset($_SESSION['flash_error'])) {
        $output .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> ' . sanitize($_SESSION['flash_error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        unset($_SESSION['flash_error']);
    }
    return $output;
}
