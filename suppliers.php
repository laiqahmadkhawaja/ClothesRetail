<?php
// suppliers.php - Supplier Directory & Balances
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

require_role('admin', 'manager', 'inventory', 'accountant');

// Add Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    $name = trim($_POST['name']);
    $company = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $opening_bal = floatval($_POST['opening_balance'] ?? 0);

    if (!empty($name) && !empty($phone)) {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, company_name, phone, email, address, opening_balance, current_balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $company, $phone, $email, $address, $opening_bal, $opening_bal]);
        $sup_id = $pdo->lastInsertId();

        if ($opening_bal > 0) {
            $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, entry_date, transaction_type, reference_no, debit, credit, balance, notes)
                           VALUES (?, NOW(), 'opening', 'SUP-OPENING', 0.00, ?, ?, 'Opening Balance Payable')")
                ->execute([$sup_id, $opening_bal, $opening_bal]);
        }

        $_SESSION['flash_success'] = "Supplier '$name' registered successfully!";
    }
    header("Location: suppliers.php");
    exit;
}

// Edit Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_supplier') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $company = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';

    $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, company_name = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $company, $phone, $email, $address, $status, $id]);
    $_SESSION['flash_success'] = "Supplier details updated!";
    header("Location: suppliers.php");
    exit;
}

$page_title = 'Suppliers & Mill Accounts';
require_once __DIR__ . '/includes/header.php';

$suppliers = $pdo->query("SELECT s.*, 
                                 COUNT(p.id) AS total_purchases,
                                 COALESCE(SUM(p.total_amount), 0) AS total_purchased_val
                          FROM suppliers s
                          LEFT JOIN purchases p ON s.id = p.supplier_id
                          GROUP BY s.id
                          ORDER BY s.current_balance DESC, s.name ASC")->fetchAll();
?>

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0">Suppliers & Fabric Mills</h5>
            <small class="text-muted">Manage cloth suppliers, wholesale vendors, and payable Khata balances</small>
        </div>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="bi bi-person-plus me-1"></i> Add New Supplier
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Supplier / Contact</th>
                    <th>Company / Mill</th>
                    <th>Phone / Email</th>
                    <th>Address</th>
                    <th>Purchases</th>
                    <th>Payable Balance (Khata)</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No suppliers recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $s): ?>
                        <tr>
                            <td><strong class="text-dark"><?= htmlspecialchars($s['name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['company_name'] ?: '-') ?></td>
                            <td>
                                <div><i class="bi bi-telephone text-muted"></i> <?= htmlspecialchars($s['phone']) ?></div>
                                <?php if ($s['email']): ?><small class="text-muted"><?= htmlspecialchars($s['email']) ?></small><?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($s['address'] ?: '-') ?></small></td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= $s['total_purchases'] ?> Orders</span>
                            </td>
                            <td>
                                <?php if ($s['current_balance'] > 0): ?>
                                    <strong class="text-danger fs-6"><?= format_currency($s['current_balance']) ?></strong>
                                    <small class="d-block text-muted">Store owes supplier</small>
                                <?php else: ?>
                                    <span class="text-success fw-bold">Cleared (Rs. 0)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= status_badge($s['status']) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="supplier_ledger.php?supplier_id=<?= $s['id'] ?>" class="btn btn-outline-primary" title="Supplier Statement / Khata">
                                        <i class="bi bi-journal-text"></i> Khata
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick='editSupplier(<?= json_encode($s) ?>)' title="Edit Details">
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

<!-- Modal Add Supplier -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Add Cloth Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="suppliers.php">
                <input type="hidden" name="action" value="add_supplier">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SUPPLIER CONTACT NAME *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Haji Farooq Ahmed">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">COMPANY / MILL NAME</label>
                        <input type="text" name="company_name" class="form-control" placeholder="e.g. Farooq Textile Mills Faisalabad">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">PHONE NUMBER *</label>
                            <input type="text" name="phone" class="form-control" required placeholder="0300-1234567">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">EMAIL</label>
                            <input type="email" name="email" class="form-control" placeholder="supplier@email.com">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">OPENING BALANCE (RS.)</label>
                        <input type="number" name="opening_balance" class="form-control" value="0">
                        <small class="text-muted">Enter any outstanding payable balance owed before today</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">MARKET / SHOP ADDRESS</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="e.g. Azam Cloth Market, Lahore"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Supplier -->
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit Supplier Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="suppliers.php">
                <input type="hidden" name="action" value="edit_supplier">
                <input type="hidden" name="id" id="editSupId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">NAME *</label>
                        <input type="text" name="name" id="editSupName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">COMPANY</label>
                        <input type="text" name="company_name" id="editSupCompany" class="form-control">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">PHONE *</label>
                            <input type="text" name="phone" id="editSupPhone" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">STATUS</label>
                            <select name="status" id="editSupStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">EMAIL</label>
                        <input type="email" name="email" id="editSupEmail" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">ADDRESS</label>
                        <textarea name="address" id="editSupAddress" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSupplier(s) {
    document.getElementById('editSupId').value = s.id;
    document.getElementById('editSupName').value = s.name;
    document.getElementById('editSupCompany').value = s.company_name || '';
    document.getElementById('editSupPhone').value = s.phone;
    document.getElementById('editSupEmail').value = s.email || '';
    document.getElementById('editSupAddress').value = s.address || '';
    document.getElementById('editSupStatus').value = s.status;
    new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
