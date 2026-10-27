<?php
// suppliers.php - Supplier Directory & Dedicated Forms
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

require_role('admin', 'manager', 'inventory', 'accountant');

$action = $_GET['action'] ?? 'list';
$edit_id = intval($_GET['id'] ?? 0);

// Add Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'edit') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $company = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (!empty($name) && !empty($phone) && $id > 0) {
        $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, company_name = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $company, $phone, $email, $address, $status, $id]);
        $_SESSION['flash_success'] = "Supplier details updated!";
    }
    header("Location: suppliers.php");
    exit;
}

$edit_supplier = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_supplier = $stmt->fetch();
    if (!$edit_supplier) {
        $_SESSION['flash_error'] = "Supplier #$edit_id not found.";
        header("Location: suppliers.php");
        exit;
    }
}

$page_title = ($action === 'create') ? 'Add Supplier' : (($action === 'edit') ? 'Edit Supplier' : 'Suppliers & Mill Accounts');
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
                        <?= $action === 'edit' ? 'Edit Supplier: ' . htmlspecialchars($edit_supplier['name']) : 'Register Cloth Supplier / Mill' ?>
                    </h5>
                    <small class="text-muted">Dedicated form for cloth suppliers and mill payables</small>
                </div>
                <a href="suppliers.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <form method="POST" action="suppliers.php">
                <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $edit_supplier['id'] ?>">
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">CONTACT PERSON / REPRESENTATIVE *</label>
                        <input type="text" name="name" class="form-control form-control-lg fw-bold" required 
                               placeholder="e.g. Haji Abdul Rasheed" 
                               value="<?= htmlspecialchars($edit_supplier['name'] ?? '') ?>" autofocus>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">COMPANY / TEXTILE MILL NAME</label>
                        <input type="text" name="company_name" class="form-control form-control-lg" 
                               placeholder="e.g. Sitara Textile Mills Faisalabad" 
                               value="<?= htmlspecialchars($edit_supplier['company_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">PHONE NUMBER *</label>
                        <input type="text" name="phone" class="form-control" required 
                               placeholder="0300-1234567" 
                               value="<?= htmlspecialchars($edit_supplier['phone'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">EMAIL ADDRESS</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="supplier@textile.com" 
                               value="<?= htmlspecialchars($edit_supplier['email'] ?? '') ?>">
                    </div>
                </div>

                <?php if ($action === 'create'): ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold">OPENING BALANCE PAYABLE (RS.)</label>
                    <input type="number" step="0.01" name="opening_balance" class="form-control" value="0">
                    <small class="text-muted">Payable dues owed to supplier prior to system start.</small>
                </div>
                <?php else: ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold">STATUS</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($edit_supplier['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($edit_supplier['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mb-4">
                    <label class="form-label small fw-bold">MILL / WAREHOUSE / SHOP ADDRESS</label>
                    <textarea name="address" class="form-control" rows="3" 
                              placeholder="e.g. Circular Road, Cloth Market, Faisalabad"><?= htmlspecialchars($edit_supplier['address'] ?? '') ?></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="suppliers.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> <?= $action === 'edit' ? 'Update Supplier' : 'Save Supplier' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<?php
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
        <a href="suppliers.php?action=create" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-person-plus me-1"></i> Add New Supplier
        </a>
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
                                <div><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($s['phone']) ?></div>
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
                                    <a href="suppliers.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-outline-secondary" title="Edit Supplier Form">
                                        <i class="bi bi-pencil"></i> Edit
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
