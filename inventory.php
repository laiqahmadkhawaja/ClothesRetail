<?php
// inventory.php - Complete Stock Inventory, Valuation & Dedicated Adjustment Form
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$tab = $_GET['tab'] ?? 'stock';
$action = $_GET['action'] ?? 'view';
$variant_id = intval($_GET['variant_id'] ?? 0);

// Handle Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'adjust_stock') {
    require_role('admin', 'manager', 'inventory');
    $user = current_user();

    $var_id = intval($_POST['variant_id']);
    $adj_type = $_POST['adj_type'] === 'subtract' ? 'subtract' : 'add';
    $qty = floatval($_POST['quantity']);
    $reason = trim($_POST['reason'] ?? 'Physical Count');
    $notes = trim($_POST['notes'] ?? '');

    if ($var_id > 0 && $qty > 0) {
        $stmtVar = $pdo->prepare("SELECT pv.*, p.id as p_id, p.name as p_name, p.item_type, p.unit_type FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.id = ?");
        $stmtVar->execute([$var_id]);
        $variant = $stmtVar->fetch();

        if ($variant) {
            $pdo->beginTransaction();
            try {
                $adjNum = 'ADJ-' . date('ymd') . '-' . mt_rand(100, 999);
                $stmtAdj = $pdo->prepare("INSERT INTO stock_adjustments (adjustment_number, adjustment_date, reason, notes, created_by) VALUES (?, NOW(), ?, ?, ?)");
                $stmtAdj->execute([$adjNum, $reason, $notes, $user['id'] ?? 1]);
                $adj_id = $pdo->lastInsertId();

                $stmtAdjItem = $pdo->prepare("INSERT INTO stock_adjustment_items (adjustment_id, product_id, variant_id, adjustment_type, quantity, unit_cost) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtAdjItem->execute([$adj_id, $variant['product_id'], $var_id, $adj_type, $qty, $variant['purchase_price']]);

                if ($adj_type === 'add') {
                    $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$qty, $var_id]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by) VALUES (?, ?, NOW(), 'adjustment_plus', ?, ?, 0, ?, ?, ?)")
                        ->execute([$variant['product_id'], $var_id, $adjNum, $qty, $variant['purchase_price'], "$reason: $notes", $user['id'] ?? 1]);
                } else {
                    $pdo->prepare("UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?")->execute([$qty, $var_id]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by) VALUES (?, ?, NOW(), 'adjustment_minus', ?, 0, ?, ?, ?, ?)")
                        ->execute([$variant['product_id'], $var_id, $adjNum, $qty, $variant['purchase_price'], "$reason: $notes", $user['id'] ?? 1]);
                }

                $pdo->commit();
                log_audit($pdo, 'Stock Adjustment', 'Inventory', "$adj_type $qty units for {$variant['p_name']}");
                $_SESSION['flash_success'] = "Stock adjustment #$adjNum applied successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "Adjustment failed: " . $e->getMessage();
            }
        }
    }
    header("Location: inventory.php?tab=$tab");
    exit;
}

$page_title = ($action === 'adjust') ? 'Stock Reconciliation & Adjustment' : 'Inventory & Stock Management';
require_once __DIR__ . '/includes/header.php';

// Valuation Summary
$stockValuation = $pdo->query("SELECT 
    COUNT(pv.id) AS total_variants,
    COALESCE(SUM(pv.stock_quantity), 0) AS total_units,
    COALESCE(SUM(pv.stock_quantity * pv.purchase_price), 0) AS total_cost_value,
    COALESCE(SUM(pv.stock_quantity * pv.selling_price), 0) AS total_retail_value
    FROM product_variants pv WHERE pv.status = 'active'")->fetch();

$potentialProfit = $stockValuation['total_retail_value'] - $stockValuation['total_cost_value'];

$adjust_variant = null;
if ($action === 'adjust' && $variant_id > 0) {
    $stmtV = $pdo->prepare("SELECT pv.*, p.name AS product_name, p.product_code, p.item_type, p.unit_type,
                                   s.name AS size_name, c.name AS color_name
                            FROM product_variants pv
                            JOIN products p ON pv.product_id = p.id
                            JOIN sizes s ON pv.size_id = s.id
                            JOIN colors c ON pv.color_id = c.id
                            WHERE pv.id = ?");
    $stmtV->execute([$variant_id]);
    $adjust_variant = $stmtV->fetch();
}
?>

<?php if ($action === 'adjust' && $adjust_variant): ?>
<!-- DEDICATED STOCK ADJUSTMENT FORM VIEW -->
<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-sliders text-primary me-2"></i> Stock Adjustment
                    </h5>
                    <small class="text-muted">Dedicated form for physical audit counts and damages</small>
                </div>
                <a href="inventory.php?tab=stock" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Inventory
                </a>
            </div>

            <form method="POST" action="inventory.php?tab=stock">
                <input type="hidden" name="form_action" value="adjust_stock">
                <input type="hidden" name="variant_id" value="<?= $adjust_variant['id'] ?>">

                <div class="p-3 bg-light rounded-3 mb-3">
                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($adjust_variant['product_name']) ?></div>
                    <div class="small text-muted">[<?= htmlspecialchars($adjust_variant['size_name']) ?> / <?= htmlspecialchars($adjust_variant['color_name']) ?>] &bull; SKU: <?= htmlspecialchars($adjust_variant['sku']) ?></div>
                    <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Current System Stock:</span>
                        <strong class="text-primary fs-5">
                            <?= rtrim(rtrim(number_format($adjust_variant['stock_quantity'], 2), '0'), '.') ?>
                            <?= ($adjust_variant['item_type'] === 'fabric') ? ($adjust_variant['unit_type'] ?? 'm') : 'Pcs' ?>
                        </strong>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">ADJUSTMENT ACTION</label>
                        <select name="adj_type" class="form-select form-select-lg">
                            <option value="subtract">Subtract (-)</option>
                            <option value="add">Add (+)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">QUANTITY *</label>
                        <input type="number" step="0.1" name="quantity" class="form-control form-control-lg fw-bold" required min="0.1" value="1" autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">REASON FOR ADJUSTMENT *</label>
                    <select name="reason" class="form-select" required>
                        <option value="Physical Count">Physical Audit Count Difference</option>
                        <option value="Damaged">Damaged Cloth / Cut Defect / Torn</option>
                        <option value="Defective">Defective Weaving / Fabric Flaw</option>
                        <option value="Lost">Lost / Missing Roll / Inventory Shrinkage</option>
                        <option value="Found">Found / Extra Unrecorded Stock</option>
                        <option value="Other">Other Operational Adjustment</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">AUDIT NOTES / REMARKS</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Found 2.5 meters cut piece damaged during stock audit..."></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="inventory.php?tab=stock" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Apply Stock Adjustment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- MAIN INVENTORY VIEW -->
<!-- KPI Valuation Ribbon -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Total Items in Store</span>
            <h3 class="fw-bold mb-1 text-dark"><?= number_format($stockValuation['total_units']) ?></h3>
            <small class="text-muted"><?= $stockValuation['total_variants'] ?> Active SKUs</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Inventory Cost Valuation</span>
            <h3 class="fw-bold mb-1 text-primary"><?= format_currency($stockValuation['total_cost_value']) ?></h3>
            <small class="text-muted">Total capital tied in stock</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Retail Sales Value</span>
            <h3 class="fw-bold mb-1 text-success"><?= format_currency($stockValuation['total_retail_value']) ?></h3>
            <small class="text-muted">Expected retail turnover</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Potential Gross Margin</span>
            <h3 class="fw-bold mb-1 text-info"><?= format_currency($potentialProfit) ?></h3>
            <small class="text-muted">Gross unrealized profit</small>
        </div>
    </div>
</div>

<!-- Tabs & Filters -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'stock' ? 'active' : '' ?>" href="inventory.php?tab=stock">
                    <i class="bi bi-box-seam me-1"></i> Current Stock Balances
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'movements' ? 'active' : '' ?>" href="inventory.php?tab=movements">
                    <i class="bi bi-clock-history me-1"></i> Stock Audit Movements
                </a>
            </li>
        </ul>
        <div class="d-flex gap-2">
            <a href="product_form.php" class="btn btn-primary btn-sm px-3 shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Add Product / Fabric
            </a>
            <a href="product_matrix.php" class="btn btn-outline-primary btn-sm px-3">
                <i class="bi bi-grid-3x3-gap me-1"></i> Matrix
            </a>
        </div>
    </div>
</div>

<?php if ($tab === 'stock'): ?>
<?php
// Fetch Stock table
$q = trim($_GET['q'] ?? '');
$filter_alert = isset($_GET['alert_only']);

$where = ["pv.status = 'active'"];
$params = [];

if (!empty($q)) {
    $where[] = "(p.name LIKE ? OR pv.sku LIKE ? OR pv.barcode LIKE ? OR s.name LIKE ? OR c.name LIKE ?)";
    $like = "%$q%";
    $params = [$like, $like, $like, $like, $like];
}
if ($filter_alert) {
    $where[] = "pv.stock_quantity <= p.alert_quantity";
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT pv.*, p.name AS product_name, p.product_code, p.alert_quantity, p.item_type, p.unit_type,
               s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex,
               cat.name AS category_name
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        JOIN sizes s ON pv.size_id = s.id
        JOIN colors c ON pv.color_id = c.id
        LEFT JOIN categories cat ON p.category_id = cat.id
        WHERE $where_clause
        ORDER BY pv.stock_quantity ASC, p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$variants = $stmt->fetchAll();
?>

<!-- Stock Search Bar -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <form method="GET" action="inventory.php" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="stock">
        <div class="col-12 col-md-6">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search item, SKU, size, color, barcode..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-6 col-md-3">
            <div class="form-check form-switch pt-1">
                <input class="form-check-input" type="checkbox" name="alert_only" id="alertOnly" value="1" <?= $filter_alert ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label small fw-bold text-danger" for="alertOnly">Low Stock Alerts Only</label>
            </div>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-filter"></i> Search</button>
            <a href="inventory.php?tab=stock" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Stock Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>SKU / Barcode</th>
                    <th>Product & Variant</th>
                    <th>Category</th>
                    <th>Purchase Cost</th>
                    <th>Retail Price</th>
                    <th>In-Stock</th>
                    <th>Stock Value</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($variants)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">No variants match the criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($variants as $v): ?>
                        <?php 
                        $isLow = $v['stock_quantity'] <= $v['alert_quantity'];
                        $isOut = $v['stock_quantity'] <= 0;
                        $unitLabel = ($v['item_type'] === 'fabric') ? ($v['unit_type'] ?? 'm') : 'Pcs';
                        ?>
                        <tr>
                            <td>
                                <code class="small fw-bold text-dark"><?= htmlspecialchars($v['sku']) ?></code>
                                <?php if ($v['barcode']): ?>
                                    <div class="text-muted small" style="font-size: 0.72rem;"><i class="bi bi-upc"></i> <?= htmlspecialchars($v['barcode']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($v['product_name']) ?></div>
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    <span class="size-badge"><?= htmlspecialchars($v['size_name']) ?></span>
                                    <span class="color-dot" style="background-color: <?= $v['color_hex'] ?>;"></span>
                                    <small class="text-muted"><?= htmlspecialchars($v['color_name']) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($v['category_name'] ?? 'General') ?></td>
                            <td><?= format_currency($v['purchase_price']) ?></td>
                            <td><strong class="text-dark"><?= format_currency($v['selling_price']) ?></strong></td>
                            <td>
                                <span class="badge <?= $isOut ? 'bg-danger' : ($isLow ? 'bg-warning text-dark' : 'bg-success') ?> fs-6">
                                    <?= rtrim(rtrim(number_format($v['stock_quantity'], 2), '0'), '.') ?> <?= $unitLabel ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= format_currency($v['stock_quantity'] * $v['purchase_price']) ?></strong>
                            </td>
                            <td class="text-end">
                                <a href="inventory.php?action=adjust&variant_id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-dark" title="Adjust Physical Stock">
                                    <i class="bi bi-sliders"></i> Adjust
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- Movements Ledger -->
<?php
$movements = $pdo->query("SELECT sm.*, p.name AS product_name, s.name AS size_name, c.name AS color_name, u.full_name AS user_name
                          FROM stock_movements sm
                          JOIN products p ON sm.product_id = p.id
                          JOIN product_variants pv ON sm.variant_id = pv.id
                          JOIN sizes s ON pv.size_id = s.id
                          JOIN colors c ON pv.color_id = c.id
                          LEFT JOIN users u ON sm.created_by = u.id
                          ORDER BY sm.movement_date DESC, sm.id DESC
                          LIMIT 50")->fetchAll();
?>
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text me-2 text-primary"></i> Stock Audit Ledger</h6>
            <small class="text-muted">Chronological trail of every stock change: sales, purchases, returns, damages</small>
        </div>
        <span class="badge bg-secondary-subtle text-secondary">Last 50 Entries</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Date & Time</th>
                    <th>Garment & Variant</th>
                    <th>Movement Type</th>
                    <th>Reference #</th>
                    <th class="text-center text-success">In (+Qty)</th>
                    <th class="text-center text-danger">Out (-Qty)</th>
                    <th>Unit Cost</th>
                    <th>Recorded By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td class="small text-muted"><?= format_date($m['movement_date']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($m['product_name']) ?></strong>
                            <div class="small text-muted">[<?= htmlspecialchars($m['size_name']) ?> / <?= htmlspecialchars($m['color_name']) ?>]</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border"><?= ucfirst(str_replace('_', ' ', $m['movement_type'])) ?></span>
                        </td>
                        <td><code><?= htmlspecialchars($m['reference_no'] ?? '-') ?></code></td>
                        <td class="text-center fw-bold text-success">
                            <?= $m['qty_in'] > 0 ? '+' . rtrim(rtrim(number_format($m['qty_in'], 2), '0'), '.') : '-' ?>
                        </td>
                        <td class="text-center fw-bold text-danger">
                            <?= $m['qty_out'] > 0 ? '-' . rtrim(rtrim(number_format($m['qty_out'], 2), '0'), '.') : '-' ?>
                        </td>
                        <td><?= format_currency($m['unit_cost']) ?></td>
                        <td><small><?= htmlspecialchars($m['user_name'] ?? 'System') ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars($m['notes'] ?? '-') ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
