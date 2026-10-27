<?php
// customers.php - Customer Directory & Dedicated Khata Form
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$action = $_GET['action'] ?? 'list';
$edit_id = intval($_GET['id'] ?? 0);

// Quick Add / Full Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $post_action = $_POST['action'];

    if ($post_action === 'quick_add') {
        header('Content-Type: application/json');
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $limit = floatval($_POST['credit_limit'] ?? 25000);
        $addr = trim($_POST['address'] ?? '');

        if (empty($name) || empty($phone)) {
            echo json_encode(['success' => false, 'error' => 'Name and phone required.']);
            exit;
        }

        $cust_code = 'CUST-' . str_pad(mt_rand(10, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO customers (customer_code, name, phone, address, credit_limit, opening_balance, current_balance) VALUES (?, ?, ?, ?, ?, 0.00, 0.00)");
        $stmt->execute([$cust_code, $name, $phone, $addr, $limit]);
        $new_id = $pdo->lastInsertId();

        echo json_encode([
            'success'  => true,
            'customer' => ['id' => $new_id, 'name' => $name, 'phone' => $phone]
        ]);
        exit;
    }

    if ($post_action === 'add_customer') {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $limit = floatval($_POST['credit_limit'] ?? 0);
        $opening = floatval($_POST['opening_balance'] ?? 0);
        $addr = trim($_POST['address'] ?? '');

        if (empty($name) || empty($phone)) {
            $_SESSION['flash_error'] = "Name and Phone number are required.";
        } else {
            $code = 'CUST-' . str_pad(mt_rand(10, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO customers (customer_code, name, phone, address, credit_limit, opening_balance, current_balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $phone, $addr, $limit, $opening, $opening]);
            $cid = $pdo->lastInsertId();

            if ($opening > 0) {
                $pdo->prepare("INSERT INTO customer_ledger (customer_id, entry_date, transaction_type, reference_no, debit, credit, balance, notes)
                               VALUES (?, NOW(), 'opening', 'INIT-BAL', ?, 0.00, ?, 'Opening Due Balance')")
                    ->execute([$cid, $opening, $opening]);
            }

            $_SESSION['flash_success'] = "Customer '$name' added to Khata records!";
        }
        header("Location: customers.php");
        exit;
    }

    if ($post_action === 'edit_customer') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $limit = floatval($_POST['credit_limit'] ?? 0);
        $addr = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (!empty($name) && !empty($phone) && $id > 0) {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ?, credit_limit = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $addr, $limit, $status, $id]);
            $_SESSION['flash_success'] = "Customer updated successfully!";
        }
        header("Location: customers.php");
        exit;
    }
}

$edit_customer = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_customer = $stmt->fetch();
    if (!$edit_customer) {
        $_SESSION['flash_error'] = "Customer #$edit_id not found.";
        header("Location: customers.php");
        exit;
    }
}

$page_title = ($action === 'create') ? 'Register Customer' : (($action === 'edit') ? 'Edit Customer' : 'Customers & Khata Accounts');
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<!-- DEDICATED FORM VIEW -->
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0">
                        <i class="bi <?= $action === 'edit' ? 'bi-pencil-square text-warning' : 'bi-person-plus text-primary' ?> me-2"></i>
                        <?= $action === 'edit' ? 'Edit Customer: ' . htmlspecialchars($edit_customer['name']) : 'Register New Khata Customer' ?>
                    </h5>
                    <small class="text-muted">Dedicated form for customer credit accounts and contact info</small>
                </div>
                <a href="customers.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit_customer' : 'add_customer' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $edit_customer['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label small fw-bold">CUSTOMER FULL NAME *</label>
                    <input type="text" name="name" class="form-control form-control-lg fw-bold" required 
                           placeholder="e.g. Chaudhry Bilal Aslam" 
                           value="<?= htmlspecialchars($edit_customer['name'] ?? '') ?>" autofocus>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">MOBILE PHONE NUMBER *</label>
                        <input type="text" name="phone" class="form-control" required 
                               placeholder="e.g. 0300-1234567" 
                               value="<?= htmlspecialchars($edit_customer['phone'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">CREDIT LIMIT (RS.)</label>
                        <input type="number" name="credit_limit" class="form-control" 
                               value="<?= $action === 'edit' ? floatval($edit_customer['credit_limit']) : 50000 ?>">
                    </div>
                </div>

                <?php if ($action === 'create'): ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold">OPENING BALANCE DUE (RS.)</label>
                    <input type="number" step="0.01" name="opening_balance" class="form-control" value="0">
                    <small class="text-muted">Prior unpaid dues to import into Khata statement.</small>
                </div>
                <?php else: ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold">STATUS</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($edit_customer['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($edit_customer['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mb-4">
                    <label class="form-label small fw-bold">RESIDENCE / SHOP / DELIVERY ADDRESS</label>
                    <textarea name="address" class="form-control" rows="3" 
                              placeholder="e.g. Shop #14, Main Anarkali Bazaar, Lahore"><?= htmlspecialchars($edit_customer['address'] ?? '') ?></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="customers.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> <?= $action === 'edit' ? 'Update Customer' : 'Save Customer' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<?php
// Search & Filter
$q = trim($_GET['q'] ?? '');
$filter_due = isset($_GET['due_only']);

$where = ["1=1"];
$params = [];

if (!empty($q)) {
    $where[] = "(c.name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ?)";
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}
if ($filter_due) {
    $where[] = "c.current_balance > 0";
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT c.*, 
               COUNT(s.id) AS total_invoices,
               COALESCE(SUM(s.net_total), 0) AS total_spent
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id AND s.sale_status = 'completed'
        WHERE $where_clause
        GROUP BY c.id
        ORDER BY c.current_balance DESC, c.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totalReceivables = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE status = 'active'")->fetchColumn();
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Total Khata Receivables</span>
            <h3 class="fw-bold mb-1 text-danger"><?= format_currency($totalReceivables) ?></h3>
            <small class="text-muted">Total balance due from credit customers</small>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-8">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100 d-flex flex-column justify-content-center">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold mb-0">Customer & Credit (Khata) Records</h5>
                    <small class="text-muted">Manage walk-in and credit retail & wholesale customers</small>
                </div>
                <a href="customers.php?action=create" class="btn btn-primary btn-sm px-3 shadow-sm">
                    <i class="bi bi-person-plus me-1"></i> Register New Customer
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <form method="GET" action="customers.php" class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search customer name, phone, code..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-6 col-md-3">
            <div class="form-check form-switch pt-1">
                <input class="form-check-input" type="checkbox" name="due_only" id="dueOnly" value="1" <?= $filter_due ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label small fw-bold text-danger" for="dueOnly">Show Outstanding Dues Only</label>
            </div>
        </div>
        <div class="col-6 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-filter"></i> Search</button>
            <a href="customers.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Customers Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Customer Name</th>
                    <th>Phone / Contact</th>
                    <th>Address</th>
                    <th>Credit Limit</th>
                    <th>Invoices</th>
                    <th>Khata Balance Due</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No customers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                <code class="small text-muted"><?= htmlspecialchars($c['customer_code']) ?></code>
                            </td>
                            <td>
                                <div><i class="bi bi-telephone text-primary small me-1"></i><?= htmlspecialchars($c['phone']) ?></div>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars($c['address'] ?: 'Counter Client') ?></small>
                            </td>
                            <td>
                                <?= format_currency($c['credit_limit']) ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= $c['total_invoices'] ?> bills</span>
                            </td>
                            <td>
                                <strong class="<?= $c['current_balance'] > 0 ? 'text-danger' : 'text-success' ?> fs-6">
                                    <?= format_currency($c['current_balance']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= status_badge($c['status']) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="customer_ledger.php?customer_id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="View Khata Ledger Statement">
                                        <i class="bi bi-journal-text"></i> Khata
                                    </a>
                                    <a href="customers.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Edit Customer Details">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
