<?php
// customer_ledger.php - Customer Credit (Khata) Account Statement & Collection
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$customer_id = intval($_GET['customer_id'] ?? 0);
$action = $_GET['action'] ?? 'view';

// Record Payment Collection from Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'collect_payment') {
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
    $customer_id = 2; // Default customer
}

$stmtCust = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmtCust->execute([$customer_id]);
$current_customer = $stmtCust->fetch();

// Fetch ledger entries
$stmtLedger = $pdo->prepare("SELECT * FROM customer_ledger WHERE customer_id = ? ORDER BY entry_date ASC, id ASC");
$stmtLedger->execute([$customer_id]);
$entries = $stmtLedger->fetchAll();
?>

<style>
@media print {
    @page { size: A4 portrait; margin: 10mm; }
    html, body, #wrapper, #page-content-wrapper, .main-content {
        height: auto !important;
        min-height: auto !important;
        overflow: visible !important;
        position: static !important;
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #000 !important;
        font-size: 11px !important;
    }
    #sidebar-wrapper, .sidebar-backdrop, .top-navbar, .no-print, nav, .sidebar, footer, .btn, .breadcrumb {
        display: none !important;
    }
    .print-area {
        position: static !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
    }
    h4 { font-size: 16px !important; margin-bottom: 2px !important; font-weight: bold !important; }
    h5 { font-size: 14px !important; margin-bottom: 2px !important; font-weight: bold !important; }
    h6 { font-size: 12px !important; margin-bottom: 2px !important; font-weight: bold !important; }
    .fs-6, .fs-5 { font-size: 11.5px !important; }
    .badge { border: 1px solid #777 !important; color: #000 !important; background: transparent !important; padding: 2px 4px !important; font-size: 10px !important; }
    table { font-size: 11px !important; width: 100% !important; min-width: 100% !important; border-collapse: collapse !important; margin-top: 10px !important; }
    th, td { padding: 5px 8px !important; border: 1px solid #bbb !important; }
    thead th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<?php if ($action === 'collect' && $current_customer): ?>
<!-- DEDICATED RECEIVE PAYMENT FORM VIEW -->
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-success">
                        <i class="bi bi-cash-coin me-2"></i> Receive Khata Payment
                    </h5>
                    <small class="text-muted">Dedicated form to record customer payment receipt</small>
                </div>
                <a href="customer_ledger.php?customer_id=<?= $current_customer['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Ledger
                </a>
            </div>

            <form method="POST" action="customer_ledger.php">
                <input type="hidden" name="form_action" value="collect_payment">
                <input type="hidden" name="customer_id" value="<?= $current_customer['id'] ?>">

                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($current_customer['name']) ?></div>
                            <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($current_customer['phone']) ?></small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Current Due</small>
                            <h5 class="fw-bolder text-danger mb-0"><?= format_currency($current_customer['current_balance']) ?></h5>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">AMOUNT RECEIVED (RS.) *</label>
                    <input type="number" step="0.01" name="amount" class="form-control form-control-lg text-end fw-bold text-success" required min="1" value="<?= max(1, $current_customer['current_balance']) ?>" autofocus>
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
                        <label class="form-label small fw-bold">PAYMENT DATE</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">TRANSACTION / SLIP REFERENCE #</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="e.g. JazzCash Trx #88129">
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">REMARKS / NOTES</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Received partial cash against Eid shopping"></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="customer_ledger.php?customer_id=<?= $current_customer['id'] ?>" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-success px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Post Payment Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- LEDGER STATEMENT VIEW -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4 no-print">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-5">
            <form method="GET" action="customer_ledger.php">
                <label class="form-label small fw-bold text-muted">SELECT CUSTOMER KHATA</label>
                <select name="customer_id" class="form-select" onchange="this.form.submit()">
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
                <a href="customer_ledger.php?customer_id=<?= $current_customer['id'] ?>&action=collect" class="btn btn-success btn-sm px-3 shadow-sm">
                    <i class="bi bi-cash-coin me-1"></i> + Receive Khata Payment
                </a>
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
            <h5 class="fw-bold text-primary mb-1"><?= htmlspecialchars($current_customer['name']) ?></h5>
            <div class="text-muted small"><i class="bi bi-telephone"></i> <?= htmlspecialchars($current_customer['phone']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($current_customer['address'] ?: 'Counter Client') ?></div>
            <div class="small mt-1 text-muted">Credit Limit: <?= format_currency($current_customer['credit_limit']) ?> | Loyalty Points: <strong><?= $current_customer['loyalty_points'] ?></strong></div>
        </div>
        <div class="text-end">
            <h6 class="fw-bold mb-0">CUSTOMER KHATA STATEMENT</h6>
            <small class="text-muted">Date: <?= date('d M Y, h:i A') ?></small>
            <div class="mt-1">
                <span class="badge <?= $current_customer['current_balance'] > 0 ? 'bg-danger' : 'bg-success' ?>" style="font-size: 11px;">
                    Balance Due: <?= format_currency($current_customer['current_balance']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb-0" style="font-size: 12px;">
            <thead class="table-light text-muted">
                <tr>
                    <th style="width: 110px;">Date</th>
                    <th style="width: 100px;">Type</th>
                    <th style="width: 130px;">Reference #</th>
                    <th>Details & Notes</th>
                    <th style="width: 120px;" class="text-end text-danger">Billed / Debit (+)</th>
                    <th style="width: 120px;" class="text-end text-success">Received / Credit (-)</th>
                    <th style="width: 130px;" class="text-end bg-light">Running Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No Khata transactions for this customer.</td></tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($e['entry_date'])) ?></td>
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
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
