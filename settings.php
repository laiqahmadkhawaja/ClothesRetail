<?php
// settings.php - Store Settings, Invoice Config & Database Backup
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_role('admin');

// Handle Backup Download (Process BEFORE header output)
if (isset($_GET['action']) && $_GET['action'] === 'backup_db') {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $backupSql = "-- ClothesRetail Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $t) {
        $stmtCreate = $pdo->query("SHOW CREATE TABLE `$t`")->fetch();
        $backupSql .= "DROP TABLE IF EXISTS `$t`;\n" . $stmtCreate['Create Table'] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll();
        foreach ($rows as $r) {
            $cols = array_map(function($k) { return "`$k`"; }, array_keys($r));
            $vals = array_map(function($v) use ($pdo) { 
                return $v === null ? "NULL" : $pdo->quote($v); 
            }, array_values($r));
            $backupSql .= "INSERT INTO `$t` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        $backupSql .= "\n";
    }

    $backupSql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="clothes_retail_backup_' . date('Ymd_His') . '.sql"');
    header('Content-Length: ' . strlen($backupSql));
    echo $backupSql;
    exit;
}

// Handle Settings Update (Process BEFORE header output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $fields = [
        'shop_name', 'tagline', 'phone', 'email', 'address', 'currency',
        'invoice_prefix', 'tax_percent', 'footer_text', 'low_stock_threshold', 'return_validity_days'
    ];

    $stmtUpdate = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");

    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $val = trim($_POST[$f]);
            $stmtUpdate->execute([$f, $val, $val]);
        }
    }

    log_audit($pdo, 'Update Settings', 'Settings', 'Updated store configuration preferences');
    $_SESSION['flash_success'] = "Store settings saved successfully!";
    header("Location: settings.php");
    exit;
}

$page_title = 'Store Settings & Backup';
require_once __DIR__ . '/includes/header.php';

$settings = get_settings($pdo);
?>

<div class="row g-4">
    <!-- Store Operational Settings -->
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm rounded-3 p-4">
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-gear-wide-connected text-primary me-2"></i> Store Operational Settings</h5>
            <p class="text-muted small">Configure boutique branding, currency, tax rates and receipt terms.</p>

            <form method="POST" action="settings.php" class="mt-3">
                <input type="hidden" name="action" value="save_settings">

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">SHOP / BOUTIQUE NAME *</label>
                        <input type="text" name="shop_name" class="form-control" required value="<?= htmlspecialchars($settings['shop_name'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">TAGLINE / SLOGAN</label>
                        <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($settings['tagline'] ?? '') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">CONTACT PHONE NUMBER *</label>
                        <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($settings['phone'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">EMAIL ADDRESS</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($settings['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">STORE ADDRESS (PRINTED ON INVOICES) *</label>
                    <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
                </div>

                <hr class="my-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-receipt-cutoff me-1 text-primary"></i> POS & Billing Configuration</h6>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold">CURRENCY SYMBOL</label>
                        <input type="text" name="currency" class="form-control" value="<?= htmlspecialchars($settings['currency'] ?? 'Rs.') ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold">INVOICE NUMBER PREFIX</label>
                        <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($settings['invoice_prefix'] ?? 'AMF-') ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold">SALES TAX RATE (%)</label>
                        <input type="number" step="0.1" name="tax_percent" class="form-control" value="<?= htmlspecialchars($settings['tax_percent'] ?? '0') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">DEFAULT LOW STOCK ALERT LEVEL</label>
                        <input type="number" name="low_stock_threshold" class="form-control" value="<?= htmlspecialchars($settings['low_stock_threshold'] ?? '5') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">RETURN & EXCHANGE VALIDITY (DAYS)</label>
                        <input type="number" name="return_validity_days" class="form-control" value="<?= htmlspecialchars($settings['return_validity_days'] ?? '7') ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">RECEIPT FOOTER & EXCHANGE POLICY NOTICE</label>
                    <textarea name="footer_text" class="form-control" rows="2"><?= htmlspecialchars($settings['footer_text'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                    <i class="bi bi-check2-circle me-1"></i> Save Store Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Database Backup & Health -->
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-database-down text-success me-2"></i> Database Backup</h6>
            <p class="text-muted small">Create an immediate, offline SQL backup of all clothes catalogue, sales, purchases, customer Khatas, and ledgers.</p>

            <a href="settings.php?action=backup_db" class="btn btn-outline-success w-100 py-2 fw-bold shadow-sm">
                <i class="bi bi-download me-1"></i> Download SQL Backup
            </a>
            <small class="text-muted d-block mt-2">Recommended: Download a backup at the end of each business week.</small>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-4 bg-light">
            <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-info-circle text-info me-2"></i> System Information</h6>
            <ul class="list-unstyled small text-muted mb-0">
                <li class="mb-1"><strong>ERP Version:</strong> 2.0 (Enterprise Retail)</li>
                <li class="mb-1"><strong>PHP Engine:</strong> <?= phpversion() ?></li>
                <li class="mb-1"><strong>Database:</strong> MySQL 8.4 via PDO</li>
                <li class="mb-1"><strong>Session User:</strong> <?= htmlspecialchars($user['full_name']) ?> (<?= ucfirst($user['role']) ?>)</li>
                <li class="mb-0"><strong>Server OS:</strong> <?= PHP_OS ?></li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
