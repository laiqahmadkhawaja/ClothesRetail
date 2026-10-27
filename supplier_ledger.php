<?php
// supplier_ledger.php - Comprehensive Supplier Khata Statement & Payment Voucher
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_role('admin', 'manager', 'accountant');

$supplier_id = intval($_GET['supplier_id'] ?? 0);

// Record Supplier Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $sup_id = intval($_POST['supplier_id']);
    $amount = floatval($_POST['amount']);
    $pay_date = $_POST['payment_date'] ?? date('Y-m-d');
    $pay_method = $_POST['payment_method'] ?? 'cash';
    $ref_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? 'Supplier Khata Payment');

    if ($sup_id > 0 && $amount > 0) {
        $pdo->beginTransaction();
        try {
            $stmtSup = $pdo->prepare("SELECT current_balance FROM suppliers WHERE id = ? FOR UPDATE");
            $stmtSup->execute([$sup_id]);
            $currentBal = floatval($stmtSup->fetchColumn());

            // Debit reduces payable balance
            $newBal = $currentBal - $amount;
            $pdo->prepare("UPDATE suppliers SET current_balance = ? WHERE id = ?")->execute([$newBal, $sup_id]);

            $voucherNo = 'SPV-' . date('ymd') . '-' . mt_rand(100, 999);
            $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, entry_date, transaction_type, reference_no, debit, credit, balance, notes)
                           VALUES (?, ?, 'payment', ?, ?, 0.00, ?, ?)")
                ->execute([$sup_id, $pay_date, ($ref_no ?: $voucherNo), $amount, $newBal, "$notes ($pay_method)"]);

            // If cash payment and cash register open, record payout
            $user = current_user();
            $active_reg = get_active_register($pdo, $user['id'] ?? 0);
            if ($active_reg && $pay_method === 'cash') {
                $pdo->prepare("UPDATE cash_registers SET total_supplier_paid = total_supplier_paid + ? WHERE id = ?")
                    ->execute([$amount, $active_reg['id']]);
            }

            $pdo->commit();
            log_audit($pdo, 'Supplier Payment', 'Finance', "Paid Rs. $amount to Supplier #$sup_id");
            $_SESSION['flash_success'] = "Payment of " . format_currency($amount) . " successfully recorded!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Payment failed: " . $e->getMessage();
        }
    }
    header("Location: supplier_ledger.php?supplier_id=$sup_id");
    exit;
}

$page_title = 'Supplier Khata Statement';
require_once __DIR__ . '/includes/header.php';

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
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

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4 no-print">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-5">
            <label class="form-label small fw-bold text-muted mb-1">SELECT SUPPLIER</label>
            <form method="GET" action="supplier_ledger.php">
                <select name="supplier_id" class="form-select form-select-lg" onchange="this.form.submit()">
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
                <button class="btn btn-success btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#paymentVoucherModal">
                    <i class="bi bi-cash me-1"></i> + Record Payment Voucher
                </button>
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
            <h4 class="fw-bold text-primary mb-1"><?= htmlspecialchars($current_supplier['name']) ?></h4>
            <div class="text-muted small"><?= htmlspecialchars($current_supplier['company_name'] ?: '') ?></div>
            <div class="text-muted small"><i class="bi bi-telephone"></i> <?= htmlspecialchars($current_supplier['phone']) ?> | <?= htmlspecialchars($current_supplier['address'] ?: '') ?></div>
        </div>
        <div class="text-end">
            <h5 class="fw-bold mb-0">SUPPLIER ACCOUNT STATEMENT</h5>
            <small class="text-muted">Generated: <?= date('d M Y, h:i A') ?></small>
            <div class="mt-2">
                <span class="badge bg-danger fs-6">Current Due: <?= format_currency($current_supplier['current_balance']) ?></span>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th style="width: 130px;">Date</th>
                    <th style="width: 100px;">Type</th>
                    <th style="width: 120px;">Reference #</th>
                    <th>Description / Notes</th>
                    <th style="width: 130px;" class="text-end text-success">Paid / Debit (-)</th>
                    <th style="width: 130px;" class="text-end text-danger">Billed / Credit (+)</th>
                    <th style="width: 140px;" class="text-end bg-light">Running Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No transactions on this supplier statement yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td class="small"><?= date('d M Y', strtotime($e['entry_date'])) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= ucfirst($e['transaction_type']) ?></span>
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

<!-- ========================================== -->
<!-- MODAL: Record Supplier Payment Voucher -->
<!-- ========================================== -->
<div class="modal fade" id="paymentVoucherModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Supplier Payment Voucher</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="supplier_ledger.php">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="supplier_id" value="<?= $current_supplier['id'] ?>">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 text-center">
                        <small class="text-muted d-block">Current Payable to Supplier</small>
                        <h4 class="fw-bolder text-danger mb-0"><?= format_currency($current_supplier['current_balance']) ?></h4>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">PAYMENT AMOUNT (RS.) *</label>
                        <input type="number" name="amount" class="form-control form-control-lg text-end fw-bold" required min="1" max="<?= max(1, $current_supplier['current_balance']) ?>" value="<?= $current_supplier['current_balance'] ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">PAYMENT METHOD</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash in Hand</option>
                                <option value="bank">Bank Transfer / Cheque</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">PAYMENT DATE</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">CHEQUE / BANK REF #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. Chq #009182 / HBL Trx">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">REMARKS / NOTES</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Paid in cash against May cloth consignment"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Post Payment Voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
