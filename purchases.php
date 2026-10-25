<?php
// purchases.php - Supplier Purchases & Receiving History
$page_title = 'Supplier Purchases';
require_once __DIR__ . '/includes/header.php';

require_role('admin', 'manager', 'inventory', 'accountant');

// Filters
$supplier_id = intval($_GET['supplier_id'] ?? 0);
$status = $_GET['payment_status'] ?? '';

$where = ["1=1"];
$params = [];

if ($supplier_id > 0) {
    $where[] = "p.supplier_id = ?";
    $params[] = $supplier_id;
}
if (!empty($status)) {
    $where[] = "p.payment_status = ?";
    $params[] = $status;
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT p.*, s.name AS supplier_name, s.company_name, u.full_name AS created_by_name,
               COUNT(pi.id) AS item_count
        FROM purchases p
        JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
        WHERE $where_clause
        GROUP BY p.id
        ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
?>

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="fw-bold mb-0">Supplier Purchases</h5>
            <small class="text-muted">Stock receiving invoices, freight costs and supplier billings</small>
        </div>
        <a href="purchase_create.php" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-cart-plus me-1"></i> New Stock Purchase
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="purchases.php" class="row g-2 mt-3 pt-3 border-top">
        <div class="col-12 col-md-4">
            <select name="supplier_id" class="form-select form-select-sm">
                <option value="0">All Suppliers</option>
                <?php foreach ($suppliers as $sup): ?>
                    <option value="<?= $sup['id'] ?>" <?= $supplier_id == $sup['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sup['name']) ?> (<?= htmlspecialchars($sup['company_name'] ?: 'Local') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="payment_status" class="form-select form-select-sm">
                <option value="">All Payment Statuses</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Fully Paid</option>
                <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial Paid</option>
                <option value="due" <?= $status === 'due' ? 'selected' : '' ?>>Unpaid / Due</option>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
            <a href="purchases.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Purchase #</th>
                    <th>Supplier</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Total Bill</th>
                    <th>Amount Paid</th>
                    <th>Balance Due</th>
                    <th>Payment Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($purchases)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No purchase records recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($purchases as $p): ?>
                        <tr>
                            <td>
                                <strong class="text-primary"><?= htmlspecialchars($p['purchase_number']) ?></strong>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['supplier_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['company_name'] ?: '') ?></small>
                            </td>
                            <td class="small text-muted"><?= format_date($p['purchase_date'], false) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $p['item_count'] ?> Line Items</span></td>
                            <td><strong><?= format_currency($p['total_amount']) ?></strong></td>
                            <td class="text-success"><?= format_currency($p['paid_amount']) ?></td>
                            <td>
                                <?php if ($p['balance_due'] > 0): ?>
                                    <strong class="text-danger"><?= format_currency($p['balance_due']) ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= status_badge($p['payment_status']) ?></td>
                            <td class="text-end">
                                <a href="supplier_ledger.php?supplier_id=<?= $p['supplier_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-journal-text"></i> Khata
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
