<?php
// customer_ledger.php - Customer Credit (Khata) Account Statement & Collection
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$customer_id = intval($_GET['customer_id'] ?? 0);

// Record Payment Collection from Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_payment') {
    $cid = intval($_POST['customer_id']);
    $amount = floatval($_POST['amount']);
    $pay_date = $_POST['payment_date'] ?? date('Y-m-d');
    $pay_method = $_POST['payment_method'] ?? 'cash';
    $ref_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? 'Khata Dues Payment');

    if ($cid > 0 && $amount > 0) {
        $pdo->beginTransaction();
        try {
            $stmtC = $pdo->prepare("SELECT current_balance FROM customers WHERE id = ? FOR UPDATE");
            $stmtC->execute([$cid]);
            $currentBal = floatval($stmtC->fetchColumn());

            // Credit decreases customer dues
            $newBal = $currentBal - $amount;
            $pdo->prepare("UPDATE customers SET current_balance = ? WHERE id = ?")->execute([$newBal, $cid]);

            $receiptNo = 'KPR-' . date('ymd') . '-' . mt_rand(100, 999);
            $pdo->prepare("INSERT INTO customer_ledger (customer_id, entry_date, transaction_type, reference_no, debit, credit, balance, notes)
                           VALUES (?, ?, 'payment', ?, 0.00, ?, ?, ?)")
                ->execute([$cid, $pay_date, ($ref_no ?: $receiptNo), $amount, $newBal, "$notes ($pay_method)"]);

            // If cash received and register open, record cash received
            $user = current_user();
            $active_reg = get_active_register($pdo, $user['id'] ?? 0);
            if ($active_reg && $pay_method === 'cash') {
                $pdo->prepare("UPDATE cash_registers SET total_payments_received = total_payments_received + ? WHERE id = ?")
                    ->execute([$amount, $active_reg['id']]);
            }

            $pdo->commit();
            log_audit($pdo, 'Customer Khata Collection', 'Finance', "Collected Rs. $amount from Customer #$cid ($pay_method)");
            $_SESSION['flash_success'] = "Payment of " . format_currency($amount) . " received and credited to Khata!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Payment collection failed: " . $e->getMessage();
        }
    }
    header("Location: customer_ledger.php?customer_id=$cid");
    exit;
}

$page_title = 'Customer Khata Statement';
require_once __DIR__ . '/includes/header.php';

$customers = $pdo->query("SELECT * FROM customers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
if ($customer_id <= 0 && !empty($customers)) {
    // default to first customer with dues or first customer
    $customer_id = 2; // Chaudhry Bilal Aslam
}

$stmtCust = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmtCust->execute([$customer_id]);
$current_customer = $stmtCust->fetch();

// Fetch ledger entries
$stmtLedger = $pdo->prepare("SELECT * FROM customer_ledger WHERE customer_id = ? ORDER BY entry_date ASC, id ASC");
$stmtLedger->execute([$customer_id]);
$entries = $stmtLedger->fetchAll();
?>

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4 no-print">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-5">
            <label class="form-label small fw-bold text-muted mb-1">SELECT KHATA CUSTOMER</label>
            <form method="GET" action="customer_ledger.php">
                <select name="customer_id" class="form-select form-select-lg" onchange="this.form.submit()">
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $customer_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['phone']) ?>) - Due: <?= format_currency($c['current_balance']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($current_customer): ?>
        <div class="col-12 col-md-7">
            <div class="d-flex flex-wrap gap-2 justify-content-md-end align-items-center">
                <div class="p-2 border rounded bg-light text-center" style="min-width: 140px;">
                    <small class="text-muted d-block" style="font-size: 0.72rem;">Outstanding Khata Due</small>
                    <strong class="fs-5 text-danger"><?= format_currency($current_customer['current_balance']) ?></strong>
                </div>
                <button class="btn btn-success btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#collectPaymentModal">
                    <i class="bi bi-cash-coin me-1"></i> + Receive Khata Payment
                </button>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print Statement
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($current_customer): ?>
<!-- Printable Statement Card -->
<div class="card border-0 shadow-sm rounded-3 p-4 print-area">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div>
            <h4 class="fw-bold text-primary mb-1"><?= htmlspecialchars($current_customer['name']) ?></h4>
            <div class="text-muted small"><i class="bi bi-telephone"></i> <?= htmlspecialchars($current_customer['phone']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($current_customer['address'] ?: 'Counter Client') ?></div>
            <div class="small mt-1 text-muted">Credit Limit: <?= format_currency($current_customer['credit_limit']) ?> | Loyalty Points: <strong><?= $current_customer['loyalty_points'] ?></strong></div>
        </div>
        <div class="text-end">
            <h5 class="fw-bold mb-0">CUSTOMER KHATA STATEMENT</h5>
            <small class="text-muted">Statement as of: <?= date('d M Y, h:i A') ?></small>
            <div class="mt-2">
                <span class="badge <?= $current_customer['current_balance'] > 0 ? 'bg-danger' : 'bg-success' ?> fs-6">
                    Balance Due: <?= format_currency($current_customer['current_balance']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th style="width: 130px;">Date</th>
                    <th style="width: 110px;">Type</th>
                    <th style="width: 130px;">Reference #</th>
                    <th>Details & Notes</th>
                    <th style="width: 130px;" class="text-end text-danger">Billed / Debit (+)</th>
                    <th style="width: 130px;" class="text-end text-success">Received / Credit (-)</th>
                    <th style="width: 140px;" class="text-end bg-light">Running Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No Khata transactions for this customer.</td></tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td class="small"><?= date('d M Y', strtotime($e['entry_date'])) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $e['transaction_type'])) ?></span>
                            </td>
                            <td><code><?= htmlspecialchars($e['reference_no'] ?? '-') ?></code></td>
                            <td><?= htmlspecialchars($e['notes'] ?? '-') ?></td>
                            <td class="text-end fw-semibold text-danger">
                                <?= $e['debit'] > 0 ? format_currency($e['debit']) : '-' ?>
                            </td>
                            <td class="text-end fw-semibold text-success">
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
<!-- MODAL: Receive Payment Collection -->
<!-- ========================================== -->
<div class="modal fade" id="collectPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">Receive Khata Due Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="customer_ledger.php">
                <input type="hidden" name="action" value="collect_payment">
                <input type="hidden" name="customer_id" value="<?= $current_customer['id'] ?>">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 text-center">
                        <small class="text-muted d-block">Outstanding Balance Due</small>
                        <h4 class="fw-bolder text-danger mb-0"><?= format_currency($current_customer['current_balance']) ?></h4>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">AMOUNT RECEIVED (RS.) *</label>
                        <input type="number" name="amount" class="form-control form-control-lg text-end fw-bold text-success" required min="1" value="<?= max(1, $current_customer['current_balance']) ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">PAYMENT METHOD</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">Easypaisa</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">DATE</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">TRANSACTION / SLIP REFERENCE #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. JazzCash Trx #88129">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">REMARKS / NOTES</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Received partial cash against Eid shopping"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Post Payment Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
