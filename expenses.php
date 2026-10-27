<?php
// expenses.php - Store Operating Expenses & Dedicated Forms
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_role('admin', 'manager', 'accountant');

$action = $_GET['action'] ?? 'list';

// Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_expense') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_category') {
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

$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name ASC")->fetchAll();

$page_title = ($action === 'create') ? 'Record Expense' : (($action === 'create_category') ? 'Add Expense Category' : 'Store Operating Expenses');
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'create'): ?>
<!-- DEDICATED FORM: RECORD EXPENSE -->
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-danger">
                        <i class="bi bi-cash-stack me-2"></i> Record Operating Expense
                    </h5>
                    <small class="text-muted">Dedicated form to record store expenditures</small>
                </div>
                <a href="expenses.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Expenses
                </a>
            </div>

            <form method="POST" action="expenses.php">
                <input type="hidden" name="form_action" value="add_expense">

                <div class="mb-3">
                    <label class="form-label small fw-bold">EXPENSE CATEGORY *</label>
                    <select name="category_id" class="form-select form-select-lg" required>
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">EXPENSE TITLE / PURPOSE *</label>
                    <input type="text" name="title" class="form-control form-control-lg fw-bold" required 
                           placeholder="e.g. Shop Electricity Bill / Packaging Poly Bags / Staff Lunch" autofocus>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">AMOUNT PAID (RS.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg text-end fw-bold text-danger" required min="1" placeholder="0.00">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">DATE</label>
                        <input type="date" name="expense_date" class="form-control form-control-lg" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">PAYMENT METHOD</label>
                        <select name="payment_method" class="form-select">
                            <option value="cash">Cash (Drawer)</option>
                            <option value="bank">Bank Account</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">Easypaisa</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">RECEIPT / BILL REFERENCE #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. LESCO Bill #12345">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">NOTES & REMARKS</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Additional details..."></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="expenses.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($action === 'create_category'): ?>
<!-- DEDICATED FORM: ADD EXPENSE CATEGORY -->
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-folder-plus text-primary me-2"></i> New Expense Category
                    </h5>
                    <small class="text-muted">Dedicated form to define expense category</small>
                </div>
                <a href="expenses.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <form method="POST" action="expenses.php">
                <input type="hidden" name="form_action" value="add_category">

                <div class="mb-3">
                    <label class="form-label small fw-bold">CATEGORY NAME *</label>
                    <input type="text" name="name" class="form-control form-control-lg fw-bold" required 
                           placeholder="e.g. Electricity, Shop Rent, Packaging, Refreshment" autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">DESCRIPTION</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Brief notes about this category..."></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="expenses.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<?php
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
                    <a href="expenses.php?action=create_category" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-folder-plus me-1"></i> + Category
                    </a>
                    <a href="expenses.php?action=create" class="btn btn-primary btn-sm px-3 shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i> Record Expense
                    </a>
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
        <div class="col-12 col-md-4">
            <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($month) ?>">
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
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
                    <th>Expense Title</th>
                    <th>Category</th>
                    <th>Method & Reference</th>
                    <th>Amount</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">No expenses recorded for this month.</td></tr>
                <?php else: ?>
                    <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($e['expense_date'])) ?></td>
                            <td>
                                <strong class="text-dark"><?= htmlspecialchars($e['title']) ?></strong>
                                <?php if ($e['notes']): ?><small class="d-block text-muted"><?= htmlspecialchars($e['notes']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($e['category_name']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary text-uppercase"><?= $e['payment_method'] ?></span>
                                <?php if ($e['reference_no']): ?>
                                    <small class="text-muted ms-1"><?= htmlspecialchars($e['reference_no']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="text-danger fs-6"><?= format_currency($e['amount']) ?></strong>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($e['created_by_name'] ?? 'System') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
