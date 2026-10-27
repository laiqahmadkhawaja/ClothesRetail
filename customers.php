<?php
// customers.php - Customer Directory & Khata Overview
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Quick Add / Full Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'quick_add') {
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

    if ($action === 'add_customer') {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $limit = floatval($_POST['credit_limit'] ?? 0);
        $opening = floatval($_POST['opening_balance'] ?? 0);
        $addr = trim($_POST['address'] ?? '');

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
        header("Location: customers.php");
        exit;
    }

    if ($action === 'edit_customer') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $limit = floatval($_POST['credit_limit'] ?? 0);
        $addr = trim($_POST['address'] ?? '');
        $status = $_POST['status'] ?? 'active';

        $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ?, credit_limit = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $addr, $limit, $status, $id]);
        $_SESSION['flash_success'] = "Customer updated successfully!";
        header("Location: customers.php");
        exit;
    }
}

$page_title = 'Customers & Khata Accounts';
require_once __DIR__ . '/includes/header.php';

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

// Total Receivables
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
                    <small class="text-muted">Manage walk-in and credit retail customers</small>
                </div>
                <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="bi bi-person-plus me-1"></i> Add Khata Customer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Search Bar -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
    <form method="GET" action="customers.php" class="row g-2 align-items-center">
        <div class="col-12 col-md-6">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search customer name, mobile number, code..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-6 col-md-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="due_only" id="dueOnlyCheck" <?= $filter_due ? 'checked' : '' ?>>
                <label class="form-check-label small fw-semibold" for="dueOnlyCheck">
                    Customers with Dues Only
                </label>
            </div>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-search"></i> Search</button>
            <a href="customers.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Customer Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Code</th>
                    <th>Customer Name</th>
                    <th>Mobile</th>
                    <th>Address</th>
                    <th>Credit Limit</th>
                    <th>Khata Balance (Due)</th>
                    <th>Loyalty Points</th>
                    <th>Total Spent</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No customers found.</td></tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($c['customer_code']) ?></span></td>
                            <td>
                                <strong class="text-dark"><?= htmlspecialchars($c['name']) ?></strong>
                            </td>
                            <td><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($c['phone']) ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($c['address'] ?: 'Counter') ?></small></td>
                            <td><?= $c['credit_limit'] > 0 ? format_currency($c['credit_limit']) : '<span class="text-muted">No Limit</span>' ?></td>
                            <td>
                                <?php if ($c['current_balance'] > 0): ?>
                                    <strong class="text-danger fs-6"><?= format_currency($c['current_balance']) ?></strong>
                                <?php else: ?>
                                    <span class="text-success fw-bold">Cleared (Rs. 0)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-warning-subtle text-warning border border-warning">
                                    <i class="bi bi-star-fill"></i> <?= $c['loyalty_points'] ?> pts
                                </span>
                            </td>
                            <td><?= format_currency($c['total_spent']) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="customer_ledger.php?customer_id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Customer Khata Statement">
                                        <i class="bi bi-journal-text"></i> Khata
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick='editCustomer(<?= json_encode($c) ?>)' title="Edit Customer">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add Customer -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Register Khata Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="add_customer">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">FULL NAME *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Chaudhry Bilal Aslam">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">MOBILE PHONE NUMBER *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="0301-5556677">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">CREDIT LIMIT (RS.)</label>
                            <input type="number" name="credit_limit" class="form-control" value="50000">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">OPENING BALANCE</label>
                            <input type="number" name="opening_balance" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">RESIDENCE / SHOP ADDRESS</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="e.g. House 45, Model Town, Lahore"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Customer -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit Customer Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="action" value="edit_customer">
                <input type="hidden" name="id" id="editCustId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">NAME *</label>
                        <input type="text" name="name" id="editCustName" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">PHONE *</label>
                            <input type="text" name="phone" id="editCustPhone" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">STATUS</label>
                            <select name="status" id="editCustStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">CREDIT LIMIT (RS.)</label>
                        <input type="number" name="credit_limit" id="editCustLimit" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ADDRESS</label>
                        <textarea name="address" id="editCustAddress" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCustomer(c) {
    document.getElementById('editCustId').value = c.id;
    document.getElementById('editCustName').value = c.name;
    document.getElementById('editCustPhone').value = c.phone;
    document.getElementById('editCustLimit').value = c.credit_limit;
    document.getElementById('editCustAddress').value = c.address || '';
    document.getElementById('editCustStatus').value = c.status;
    new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
