<?php
// expenses.php - Store Operating Expenses & Vouchers
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_role('admin', 'manager', 'accountant');

// Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $category_id = intval($_POST['category_id']);
    $title = trim($_POST['title']);
    $amount = floatval($_POST['amount']);
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $ref_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $user = current_user();
    $active_reg = get_active_register($pdo, $user['id'] ?? 0);
    $reg_id = $active_reg ? $active_reg['id'] : null;

    if ($category_id > 0 && !empty($title) && $amount > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (category_id, expense_date, title, amount, payment_method, reference_no, register_id, notes, created_by)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$category_id, $expense_date, $title, $amount, $payment_method, $ref_no, $reg_id, $notes, $user['id'] ?? 1]);

            // Update cash register if cash expense
            if ($reg_id && $payment_method === 'cash') {
                $pdo->prepare("UPDATE cash_registers SET total_expenses_paid = total_expenses_paid + ? WHERE id = ?")
                    ->execute([$amount, $reg_id]);
            }

            $pdo->commit();
            log_audit($pdo, 'Record Expense', 'Finance', "Expense: $title, Amount: Rs. $amount ($payment_method)");
            $_SESSION['flash_success'] = "Expense of " . format_currency($amount) . " recorded successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = "Error recording expense: " . $e->getMessage();
        }
    }
    header("Location: expenses.php");
    exit;
}

// Add Expense Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description'] ?? '');
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            $_SESSION['flash_success'] = "Expense category created!";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Category already exists.";
        }
    }
    header("Location: expenses.php");
    exit;
}

$page_title = 'Store Operating Expenses';
require_once __DIR__ . '/includes/header.php';

// Filters
$category_id = intval($_GET['category_id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');

$where = ["1=1"];
$params = [];

if ($category_id > 0) {
    $where[] = "e.category_id = ?";
    $params[] = $category_id;
}
if (!empty($month)) {
    $where[] = "DATE_FORMAT(e.expense_date, '%Y-%m') = ?";
    $params[] = $month;
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT e.*, ec.name AS category_name, u.full_name AS created_by_name
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE $where_clause
        ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name ASC")->fetchAll();

// Month total
$stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?");
$stmtTotal->execute([$month]);
$monthTotal = floatval($stmtTotal->fetchColumn());
?>

<!-- KPI Ribbon -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Month Operating Expenses</span>
            <h3 class="fw-bold mb-1 text-danger"><?= format_currency($monthTotal) ?></h3>
            <small class="text-muted">Total expenses recorded for <?= date('F Y', strtotime($month . '-01')) ?></small>
        </div>
    </div>
    <div class="col-12 col-md-8">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 d-flex flex-column justify-content-center">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-0">Shop Operating Expenses</h5>
                    <small class="text-muted">Track rent, staff salaries, bills, packaging, and tea expenses</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-folder-plus me-1"></i> + Category
                    </button>
                    <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                        <i class="bi bi-plus-circle me-1"></i> Record Expense
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <form method="GET" action="expenses.php" class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
            <select name="category_id" class="form-select form-select-sm">
                <option value="0">All Expense Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($month) ?>">
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
            <a href="expenses.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Expenses Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Expense Title</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Receipt / Ref #</th>
                    <th>Recorded By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No expense records found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($expenses as $ex): ?>
                        <tr>
                            <td class="small"><?= date('d M Y', strtotime($ex['expense_date'])) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ex['category_name']) ?></span></td>
                            <td><strong class="text-dark"><?= htmlspecialchars($ex['title']) ?></strong></td>
                            <td><strong class="text-danger"><?= format_currency($ex['amount']) ?></strong></td>
                            <td><?= payment_badge($ex['payment_method']) ?></td>
                            <td><code class="small"><?= htmlspecialchars($ex['reference_no'] ?? '-') ?></code></td>
                            <td><small class="text-muted"><?= htmlspecialchars($ex['created_by_name'] ?? 'System') ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars($ex['notes'] ?? '-') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add Expense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Record Store Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="expenses.php">
                <input type="hidden" name="action" value="add_expense">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">EXPENSE CATEGORY *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">EXPENSE TITLE / REASON *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. 500 Shopping Bags Print, LESCO Electricity Bill">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">AMOUNT (RS.) *</label>
                        <input type="number" name="amount" class="form-control form-control-lg text-end fw-bold" required min="1" placeholder="0">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">PAYMENT METHOD</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash (from drawer)</option>
                                <option value="bank">Bank / Cheque</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">DATE</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">RECEIPT / BILL REFERENCE #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. Bill #88219">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">NOTES</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Add Expense Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="expenses.php">
                <input type="hidden" name="action" value="add_category">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">CATEGORY NAME *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Tailoring / Alteration Charges">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">DESCRIPTION</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
