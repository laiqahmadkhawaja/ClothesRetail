<?php
// cash_register.php - Shift Open/Close & Drawer Reconciliation
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_role('admin', 'manager', 'cashier');

$user = current_user();
$action = $_GET['action'] ?? 'view';

// 1. Handle Open Shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'open_shift') {
    $opening_balance = floatval($_POST['opening_balance']);
    $notes = trim($_POST['notes'] ?? '');

    $active = get_active_register($pdo, $user['id']);
    if ($active) {
        $_SESSION['flash_error'] = "You already have an active shift (#{$active['id']}). Please close it first.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO cash_registers (cashier_id, opening_time, opening_balance, notes, status) VALUES (?, NOW(), ?, ?, 'open')");
        $stmt->execute([$user['id'], $opening_balance, $notes]);
        $new_id = $pdo->lastInsertId();

        log_audit($pdo, 'Open Shift', 'POS', "User opened shift #$new_id with Rs. $opening_balance float");
        $_SESSION['flash_success'] = "Register shift opened successfully with starting cash of " . format_currency($opening_balance);
    }
    header("Location: cash_register.php");
    exit;
}

// 2. Handle Close Shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'close_shift') {
    $reg_id = intval($_POST['register_id']);
    $closing_balance = floatval($_POST['closing_balance']);
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM cash_registers WHERE id = ? AND status = 'open'");
    $stmt->execute([$reg_id]);
    $reg = $stmt->fetch();

    if ($reg) {
        $expected_cash = ($reg['opening_balance'] + $reg['total_sales_cash'] + $reg['total_payments_received']) - ($reg['total_expenses_paid'] + $reg['total_supplier_paid'] + $reg['total_refunds_paid']);
        $diff = $closing_balance - $expected_cash;

        $stmtUpd = $pdo->prepare("UPDATE cash_registers SET closing_time = NOW(), closing_balance = ?, notes = ?, cash_difference = ?, status = 'closed' WHERE id = ?");
        $stmtUpd->execute([$closing_balance, $notes, $diff, $reg_id]);

        log_audit($pdo, 'Close Shift', 'POS', "Shift #$reg_id closed. Expected: Rs. $expected_cash, Counted: Rs. $closing_balance, Diff: Rs. $diff");
        $_SESSION['flash_success'] = "Register shift #$reg_id closed successfully! Cash Difference: " . format_currency($diff);
    }
    header("Location: cash_register.php");
    exit;
}

$page_title = ($action === 'open') ? 'Open Register Shift' : (($action === 'close') ? 'Close Register Shift' : 'Cash Drawer & Shifts');
require_once __DIR__ . '/includes/header.php';

$active_register = get_active_register($pdo, $user['id']);
$expected_cash = 0;
if ($active_register) {
    $expected_cash = ($active_register['opening_balance'] + $active_register['total_sales_cash'] + $active_register['total_payments_received'])
                   - ($active_register['total_expenses_paid'] + $active_register['total_supplier_paid'] + $active_register['total_refunds_paid']);
}
?>

<?php if ($action === 'open'): ?>
<!-- DEDICATED FORM: OPEN SHIFT -->
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-unlock-fill me-2"></i> Open Register Shift
                    </h5>
                    <small class="text-muted">Dedicated form to begin cashier shift</small>
                </div>
                <a href="cash_register.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <form method="POST" action="cash_register.php">
                <input type="hidden" name="form_action" value="open_shift">

                <div class="mb-3">
                    <label class="form-label small fw-bold">OPENING CASH FLOAT (RS.) *</label>
                    <input type="number" step="0.01" name="opening_balance" class="form-control form-control-lg text-end fw-bold" required min="0" value="5000" autofocus>
                    <small class="text-muted">Enter petty cash / small change placed into drawer</small>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">SHIFT NOTES</label>
                    <input type="text" name="notes" class="form-control" value="Morning Opening Shift">
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="cash_register.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Open Register & Begin Sales
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($action === 'close' && $active_register): ?>
<!-- DEDICATED FORM: CLOSE SHIFT -->
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-danger">
                        <i class="bi bi-door-closed me-2"></i> Close Register & Count Cash
                    </h5>
                    <small class="text-muted">Dedicated reconciliation form for closing shift</small>
                </div>
                <a href="cash_register.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <form method="POST" action="cash_register.php">
                <input type="hidden" name="form_action" value="close_shift">
                <input type="hidden" name="register_id" value="<?= $active_register['id'] ?>">

                <div class="p-3 bg-light rounded text-center mb-3">
                    <span class="small text-muted d-block">Expected Cash in Drawer:</span>
                    <h3 class="fw-bolder text-primary mb-0"><?= format_currency($expected_cash) ?></h3>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">ACTUAL PHYSICAL CASH COUNT (RS.) *</label>
                    <input type="number" step="0.01" name="closing_balance" id="closingCashCount" class="form-control form-control-lg text-end fw-bold" required min="0" value="<?= $expected_cash ?>" autofocus>
                    <small class="text-muted">Count physical banknotes and coins in drawer</small>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">CLOSING SHIFT NOTES</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. End of day reconciliation..."></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="cash_register.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="bi bi-door-closed me-1"></i> Reconcile & Close Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- MAIN REGISTER OVERVIEW & SHIFT BREAKDOWN -->
<?php if ($active_register): ?>
<div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success fs-6 border border-success">
                    <i class="bi bi-circle-fill" style="font-size: 8px;"></i> Shift Active (#<?= $active_register['id'] ?>)
                </span>
                <span class="text-muted small">Started: <?= format_date($active_register['opening_time']) ?></span>
            </div>
            <h5 class="fw-bold mt-2 mb-0">Current Drawer Reconciliation</h5>
        </div>
        <div class="d-flex gap-2">
            <a href="pos.php" class="btn btn-primary btn-sm px-3 shadow-sm">
                <i class="bi bi-upc-scan me-1"></i> Go to POS
            </a>
            <a href="cash_register.php?action=close" class="btn btn-danger btn-sm px-3 shadow-sm">
                <i class="bi bi-door-closed me-1"></i> Close Register Shift
            </a>
        </div>
    </div>

    <!-- Live Cash Movements Breakdown -->
    <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="p-3 border rounded bg-light">
                <span class="text-muted small fw-bold d-block">1. OPENING FLOAT</span>
                <h4 class="fw-bold mb-0 text-dark"><?= format_currency($active_register['opening_balance']) ?></h4>
                <small class="text-muted">Drawer starting cash</small>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="p-3 border rounded bg-success-subtle">
                <span class="text-success small fw-bold d-block">(+) CASH SALES</span>
                <h4 class="fw-bold mb-0 text-success"><?= format_currency($active_register['total_sales_cash']) ?></h4>
                <small class="text-muted">Direct POS cash collected</small>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="p-3 border rounded bg-success-subtle">
                <span class="text-success small fw-bold d-block">(+) KHATA COLLECTIONS</span>
                <h4 class="fw-bold mb-0 text-success"><?= format_currency($active_register['total_payments_received']) ?></h4>
                <small class="text-muted">Customer due cash payments</small>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="p-3 border rounded bg-danger-subtle">
                <span class="text-danger small fw-bold d-block">(-) PAYOUTS & REFUNDS</span>
                <h4 class="fw-bold mb-0 text-danger">
                    <?= format_currency($active_register['total_expenses_paid'] + $active_register['total_supplier_paid'] + $active_register['total_refunds_paid']) ?>
                </h4>
                <small class="text-muted">Expenses + Supplier + Refunds</small>
            </div>
        </div>
    </div>

    <!-- Expected Cash Total Banner -->
    <div class="alert alert-primary mt-4 mb-0 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-cash-stack me-2"></i> Current Expected Drawer Cash</h5>
            <small class="text-muted">Formula: (Opening + Sales Cash + Khata) - (Expenses + Supplier Payouts + Refunds)</small>
        </div>
        <h2 class="fw-bolder mb-0 text-primary"><?= format_currency($expected_cash) ?></h2>
    </div>
</div>

<?php else: ?>
<!-- No Shift Open Warning & Open Shift Button -->
<div class="card border-0 shadow-sm rounded-3 p-4 mb-4 bg-light">
    <div class="row align-items-center">
        <div class="col-12 col-md-7">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning text-white rounded-circle p-3">
                    <i class="bi bi-lock-fill fs-3"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-1 text-dark">Register Shift is Currently Closed</h5>
                    <p class="text-muted small mb-0">Open a new shift and specify your opening float cash before starting retail billing.</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-5 mt-3 mt-md-0 text-md-end">
            <a href="cash_register.php?action=open" class="btn btn-primary btn-lg fw-bold shadow-sm">
                <i class="bi bi-unlock-fill me-1"></i> Open Register Shift
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Shift History Table -->
<?php
$shifts = $pdo->query("SELECT cr.*, u.full_name AS cashier_name 
                       FROM cash_registers cr 
                       JOIN users u ON cr.cashier_id = u.id 
                       ORDER BY cr.id DESC 
                       LIMIT 25")->fetchAll();
?>
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-0 py-3">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-primary me-2"></i> Cash Register Shifts History</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Shift #</th>
                    <th>Cashier</th>
                    <th>Opening Time</th>
                    <th>Closing Time</th>
                    <th class="text-end">Opening Cash</th>
                    <th class="text-end">Cash Sales</th>
                    <th class="text-end">Closing Cash</th>
                    <th>Difference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shifts as $s): ?>
                    <tr>
                        <td><strong>#<?= $s['id'] ?></strong></td>
                        <td><?= htmlspecialchars($s['cashier_name']) ?></td>
                        <td><?= format_date($s['opening_time']) ?></td>
                        <td><?= $s['closing_time'] ? format_date($s['closing_time']) : '<span class="text-success fw-bold">Active</span>' ?></td>
                        <td class="text-end"><?= format_currency($s['opening_balance']) ?></td>
                        <td class="text-end text-success fw-bold"><?= format_currency($s['total_sales_cash']) ?></td>
                        <td class="text-end"><?= $s['closing_balance'] !== null ? format_currency($s['closing_balance']) : '-' ?></td>
                        <td>
                            <?php if ($s['cash_difference'] !== null): ?>
                                <?php if ($s['cash_difference'] == 0): ?>
                                    <span class="badge bg-success">Exact Match</span>
                                <?php elseif ($s['cash_difference'] > 0): ?>
                                    <span class="badge bg-info text-dark">+<?= format_currency($s['cash_difference']) ?> Over</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><?= format_currency($s['cash_difference']) ?> Short</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge($s['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
