<?php
// supplier_ledger.php - Supplier Payable Statement & Payment Voucher
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_role('admin', 'manager', 'accountant');

$supplier_id = intval($_GET['supplier_id'] ?? 0);
$action = $_GET['action'] ?? 'view';

// Record Payment to Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'record_payment') {
    $sid = intval($_POST['supplier_id']);
    $amount = floatval($_POST['amount']);
    $pay_date = $_POST['payment_date'] ?? date('Y-m-d');
    $pay_method = $_POST['payment_method'] ?? 'cash';
    $ref_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? 'Supplier Invoice Clearance');

    if ($sid > 0 && $amount > 0) {
        $pdo->beginTransaction();
        try {
            $stmtS = $pdo->prepare("SELECT current_balance FROM suppliers WHERE id = ? FOR UPDATE");
            $stmtS->execute([$sid]);
            $currentBal = floatval($stmtS->fetchColumn());

            // Debit decreases our payable debt to supplier
            $newBal = $currentBal - $amount;
            $pdo->prepare("UPDATE suppliers SET current_balance = ? WHERE id = ?")->execute([$newBal, $sid]);

            $voucherNo = 'PV-' . date('ymd') . '-' . mt_rand(100, 999);
            $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, entry_date, transaction_type, reference_no, debit, credit, balance, notes)
                           VALUES (?, ?, 'payment', ?, ?, 0.00, ?, ?)")
                ->execute([$sid, $pay_date, ($ref_no ?: $voucherNo), $amount, $newBal, "$notes ($pay_method)"]);

            $pdo->commit();
            log_audit($pdo, 'Supplier Payment', 'Finance', "Paid Rs. $amount to Supplier #$sid ($pay_method)");
            $_SESSION['flash_success'] = "Payment voucher of " . format_currency($amount) . " successfully recorded!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Payment failed: " . $e->getMessage();
        }
    }
    header("Location: supplier_ledger.php?supplier_id=$sid");
    exit;
}

$page_title = 'Supplier Statement & Ledger';
require_once __DIR__ . '/includes/header.php';

$suppliers = $pdo->query("SELECT * FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
if ($supplier_id <= 0 && !empty($suppliers)) {
    $supplier_id = $suppliers[0]['id'];
}

$stmtSup = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmtSup->execute([$supplier_id]);
$current_supplier = $stmtSup->fetch();

// Fetch ledger entries
$stmtLedger = $pdo->prepare("SELECT * FROM supplier_ledger WHERE supplier_id = ? ORDER BY entry_date ASC, id ASC");
$stmtLedger->execute([$supplier_id]);
$entries = $stmtLedger->fetchAll();
?>

<style>
@media print {
    body { font-size: 11px !important; color: #000 !important; background: #fff !important; }
    .no-print, nav, .sidebar, footer, .btn, .breadcrumb { display: none !important; }
    .print-area { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
    h4 { font-size: 15px !important; margin-bottom: 2px !important; font-weight: bold !important; }
    h5 { font-size: 13px !important; margin-bottom: 2px !important; font-weight: bold !important; }
    .fs-6, .fs-5 { font-size: 12px !important; }
    .badge { border: 1px solid #999 !important; color: #000 !important; background: transparent !important; padding: 2px 4px !important; font-size: 10px !important; }
    table { font-size: 11px !important; width: 100% !important; border-collapse: collapse !important; }
    th, td { padding: 4px 6px !important; border: 1px solid #ccc !important; }
    @page { size: A4; margin: 12mm 15mm; }
}
</style>

<?php if ($action === 'pay' && $current_supplier): ?>
<!-- DEDICATED PAYMENT VOUCHER FORM VIEW -->
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-success">
                        <i class="bi bi-cash-stack me-2"></i> Record Supplier Payment Voucher
                    </h5>
                    <small class="text-muted">Dedicated form to issue payment to cloth mills and vendors</small>
                </div>
                <a href="supplier_ledger.php?supplier_id=<?= $current_supplier['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Statement
                </a>
            </div>

            <form method="POST" action="supplier_ledger.php">
                <input type="hidden" name="form_action" value="record_payment">
                <input type="hidden" name="supplier_id" value="<?= $current_supplier['id'] ?>">

                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($current_supplier['name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($current_supplier['company_name'] ?: 'Wholesale Mill') ?></small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Current Payable</small>
                            <h5 class="fw-bolder text-danger mb-0"><?= format_currency($current_supplier['current_balance']) ?></h5>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">AMOUNT PAID (RS.) *</label>
                    <input type="number" step="0.01" name="amount" class="form-control form-control-lg text-end fw-bold text-success" required min="1" value="<?= max(1, $current_supplier['current_balance']) ?>" autofocus>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">PAYMENT METHOD</label>
                        <select name="payment_method" class="form-select">
                            <option value="bank">Bank Transfer / Cheque</option>
                            <option value="cash">Cash Counter</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">Easypaisa</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">PAYMENT DATE</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">CHEQUE / SLIP / REFERENCE #</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="e.g. HBL Cheque #991024 or IBFT Ref">
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">REMARKS / NOTES</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Cleared bill against Summer 2026 cotton lot"></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="supplier_ledger.php?supplier_id=<?= $current_supplier['id'] ?>" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-success px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Issue Payment Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- STATEMENT VIEW -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4 no-print">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-5">
            <form method="GET" action="supplier_ledger.php">
                <label class="form-label small fw-bold text-muted">SELECT SUPPLIER / MILL</label>
                <select name="supplier_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $supplier_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['company_name'] ?: 'Wholesale') ?>) - Payable: <?= format_currency($s['current_balance']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($current_supplier): ?>
        <div class="col-12 col-md-7">
            <div class="d-flex flex-wrap gap-2 justify-content-md-end align-items-center">
                <div class="p-2 border rounded bg-light text-center" style="min-width: 140px;">
                    <small class="text-muted d-block" style="font-size: 0.72rem;">Outstanding Khata Payable</small>
                    <strong class="fs-5 text-danger"><?= format_currency($current_supplier['current_balance']) ?></strong>
                </div>
                <a href="supplier_ledger.php?supplier_id=<?= $current_supplier['id'] ?>&action=pay" class="btn btn-success btn-sm px-3 shadow-sm">
                    <i class="bi bi-cash me-1"></i> + Record Payment Voucher
                </a>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Statement
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($current_supplier): ?>
<!-- Printable Statement Card -->
<div class="card border-0 shadow-sm rounded-3 p-4 print-area">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div>
            <h5 class="fw-bold text-primary mb-1"><?= htmlspecialchars($current_supplier['name']) ?></h5>
            <div class="text-muted small"><?= htmlspecialchars($current_supplier['company_name'] ?: '') ?></div>
            <div class="text-muted small"><i class="bi bi-telephone"></i> <?= htmlspecialchars($current_supplier['phone']) ?> | <?= htmlspecialchars($current_supplier['address'] ?: '') ?></div>
        </div>
        <div class="text-end">
            <h6 class="fw-bold mb-0">SUPPLIER ACCOUNT STATEMENT</h6>
            <small class="text-muted">Generated: <?= date('d M Y, h:i A') ?></small>
            <div class="mt-1">
                <span class="badge bg-danger" style="font-size: 11px;">Current Due: <?= format_currency($current_supplier['current_balance']) ?></span>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0" style="font-size: 12px;">
            <thead class="table-light text-muted">
                <tr>
                    <th style="width: 110px;">Date</th>
                    <th style="width: 100px;">Type</th>
                    <th style="width: 120px;">Reference #</th>
                    <th>Description / Notes</th>
                    <th style="width: 120px;" class="text-end text-success">Paid / Debit (-)</th>
                    <th style="width: 120px;" class="text-end text-danger">Billed / Credit (+)</th>
                    <th style="width: 130px;" class="text-end bg-light">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No transactions recorded for this supplier.</td></tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($e['entry_date'])) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $e['transaction_type'])) ?></span>
                            </td>
                            <td><code><?= htmlspecialchars($e['reference_no'] ?? '-') ?></code></td>
                            <td><?= htmlspecialchars($e['notes'] ?? '-') ?></td>
                            <td class="text-end fw-semibold text-success">
                                <?= $e['debit'] > 0 ? format_currency($e['debit']) : '-' ?>
                            </td>
                            <td class="text-end fw-semibold text-danger">
                                <?= $e['credit'] > 0 ? format_currency($e['credit']) : '-' ?>
                            </td>
                            <td class="text-end fw-bold text-dark bg-light">
                                <?= format_currency($e['balance']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
