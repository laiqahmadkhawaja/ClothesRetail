<?php
// inventory.php - Complete Stock Inventory, Valuation & Movements Ledger
$page_title = 'Inventory & Stock Management';
require_once __DIR__ . '/includes/header.php';

$tab = $_GET['tab'] ?? 'stock';

// Handle Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    require_role('admin', 'manager', 'inventory');

    $variant_id = intval($_POST['variant_id']);
    $adj_type = $_POST['adj_type'] === 'subtract' ? 'subtract' : 'add';
    $qty = intval($_POST['quantity']);
    $reason = trim($_POST['reason'] ?? 'Physical Count');
    $notes = trim($_POST['notes'] ?? '');

    if ($variant_id > 0 && $qty > 0) {
        $stmtVar = $pdo->prepare("SELECT pv.*, p.id as p_id, p.name as p_name FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.id = ?");
        $stmtVar->execute([$variant_id]);
        $variant = $stmtVar->fetch();

        if ($variant) {
            $pdo->beginTransaction();
            try {
                $adjNum = 'ADJ-' . date('ymd') . '-' . mt_rand(100, 999);
                $stmtAdj = $pdo->prepare("INSERT INTO stock_adjustments (adjustment_number, adjustment_date, reason, notes, created_by) VALUES (?, NOW(), ?, ?, ?)");
                $stmtAdj->execute([$adjNum, $reason, $notes, $user['id']]);
                $adj_id = $pdo->lastInsertId();

                $stmtAdjItem = $pdo->prepare("INSERT INTO stock_adjustment_items (adjustment_id, product_id, variant_id, adjustment_type, quantity, unit_cost) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtAdjItem->execute([$adj_id, $variant['product_id'], $variant_id, $adj_type, $qty, $variant['purchase_price']]);

                if ($adj_type === 'add') {
                    $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$qty, $variant_id]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by) VALUES (?, ?, NOW(), 'adjustment_plus', ?, ?, 0, ?, ?, ?)")
                        ->execute([$variant['product_id'], $variant_id, $adjNum, $qty, $variant['purchase_price'], "$reason: $notes", $user['id']]);
                } else {
                    $pdo->prepare("UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?")->execute([$qty, $variant_id]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by) VALUES (?, ?, NOW(), 'adjustment_minus', ?, 0, ?, ?, ?, ?)")
                        ->execute([$variant['product_id'], $variant_id, $adjNum, $qty, $variant['purchase_price'], "$reason: $notes", $user['id']]);
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

// Valuation Summary
$stockValuation = $pdo->query("SELECT 
    COUNT(pv.id) AS total_variants,
    COALESCE(SUM(pv.stock_quantity), 0) AS total_units,
    COALESCE(SUM(pv.stock_quantity * pv.purchase_price), 0) AS total_cost_value,
    COALESCE(SUM(pv.stock_quantity * pv.selling_price), 0) AS total_retail_value
    FROM product_variants pv WHERE pv.status = 'active'")->fetch();

$potentialProfit = $stockValuation['total_retail_value'] - $stockValuation['total_cost_value'];

// Stock List Filters
$q = trim($_GET['q'] ?? '');
$filter_status = $_GET['stock_filter'] ?? 'all';

$where = ["pv.status = 'active'"];
$params = [];

if (!empty($q)) {
    $where[] = "(p.name LIKE ? OR pv.sku LIKE ? OR pv.barcode LIKE ?)";
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}
if ($filter_status === 'low') {
    $where[] = "pv.stock_quantity <= p.alert_quantity AND pv.stock_quantity > 0";
} elseif ($filter_status === 'out') {
    $where[] = "pv.stock_quantity <= 0";
}

$where_clause = implode(' AND ', $where);

// Fetch Variants Table
$sqlVariants = "SELECT pv.*, p.name AS product_name, p.product_code, p.rack_location, p.season, p.alert_quantity,
                       s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex,
                       cat.name AS category_name, b.name AS brand_name
                FROM product_variants pv
                JOIN products p ON pv.product_id = p.id
                JOIN sizes s ON pv.size_id = s.id
                JOIN colors c ON pv.color_id = c.id
                LEFT JOIN categories cat ON p.category_id = cat.id
                LEFT JOIN brands b ON p.brand_id = b.id
                WHERE $where_clause
                ORDER BY p.name ASC, s.sort_order ASC";
$stmtVar = $pdo->prepare($sqlVariants);
$stmtVar->execute($params);
$variants = $stmtVar->fetchAll();

// Fetch Stock Movements Audit Trail
$movements = $pdo->query("SELECT sm.*, p.name AS product_name, s.name AS size_name, c.name AS color_name, u.full_name AS user_name
                         FROM stock_movements sm
                         JOIN products p ON sm.product_id = p.id
                         JOIN product_variants pv ON sm.variant_id = pv.id
                         JOIN sizes s ON pv.size_id = s.id
                         JOIN colors c ON pv.color_id = c.id
                         LEFT JOIN users u ON sm.created_by = u.id
                         ORDER BY sm.id DESC
                         LIMIT 50")->fetchAll();
?>

<!-- Stock Valuation Summary Ribbon -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Total In-Stock Garments</span>
            <h3 class="fw-bold mb-1 text-primary"><?= number_format($stockValuation['total_units']) ?></h3>
            <small class="text-muted"><?= number_format($stockValuation['total_variants']) ?> Active Variants (Size/Color)</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Stock Valuation (Cost)</span>
            <h3 class="fw-bold mb-1 text-dark"><?= format_currency($stockValuation['total_cost_value']) ?></h3>
            <small class="text-muted">Total capital invested in inventory</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Stock Valuation (Retail)</span>
            <h3 class="fw-bold mb-1 text-info"><?= format_currency($stockValuation['total_retail_value']) ?></h3>
            <small class="text-muted">Total expected retail sales</small>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <span class="text-muted small fw-bold text-uppercase">Potential Gross Margin</span>
            <h3 class="fw-bold mb-1 text-success"><?= format_currency($potentialProfit) ?></h3>
            <small class="text-muted">
                <?= $stockValuation['total_retail_value'] > 0 ? round(($potentialProfit / $stockValuation['total_retail_value']) * 100, 1) . '% Gross Margin' : '0%' ?>
            </small>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<div class="card border-0 shadow-sm rounded-3 p-2 mb-3 bg-white">
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'stock' ? 'active' : '' ?>" href="inventory.php?tab=stock">
                <i class="bi bi-box-seam me-1"></i> Current Stock by Variant
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'movements' ? 'active' : '' ?>" href="inventory.php?tab=movements">
                <i class="bi bi-clock-history me-1"></i> Stock Movement Ledger (Audit Trail)
            </a>
        </li>
    </ul>
</div>

<?php if ($tab === 'stock'): ?>
<!-- Stock Table & Search Filters -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="p-3 border-bottom">
        <form method="GET" action="inventory.php" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="stock">
            <div class="col-12 col-md-5">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search product name, SKU, barcode..." value="<?= htmlspecialchars($q) ?>">
            </div>
            <div class="col-6 col-md-3">
                <select name="stock_filter" class="form-select form-select-sm">
                    <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Inventory</option>
                    <option value="low" <?= $filter_status === 'low' ? 'selected' : '' ?>>Low Stock Only (<= Alert Limit)</option>
                    <option value="out" <?= $filter_status === 'out' ? 'selected' : '' ?>>Out of Stock (0 Units)</option>
                </select>
            </div>
            <div class="col-6 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
                <a href="inventory.php?tab=stock" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Garment</th>
                    <th>Size & Color</th>
                    <th>SKU / Barcode</th>
                    <th>Rack Location</th>
                    <th>Cost Price</th>
                    <th>Retail Price</th>
                    <th>Current Stock</th>
                    <th>Stock Value (Cost)</th>
                    <th class="text-end">Adjust</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($variants)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No stock records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($variants as $v): 
                        $isLow = ($v['stock_quantity'] <= $v['alert_quantity']);
                        $isOut = ($v['stock_quantity'] <= 0);
                    ?>
                        <tr class="<?= $isOut ? 'table-danger-subtle' : ($isLow ? 'table-warning-subtle' : '') ?>">
                            <td>
                                <strong class="text-dark"><?= htmlspecialchars($v['product_name']) ?></strong>
                                <div class="text-muted small"><?= htmlspecialchars($v['category_name'] ?? '') ?> &bull; <?= htmlspecialchars($v['brand_name'] ?? '') ?></div>
                            </td>
                            <td>
                                <span class="size-badge me-1">Size <?= htmlspecialchars($v['size_name']) ?></span>
                                <span class="color-dot" style="background-color: <?= htmlspecialchars($v['color_hex']) ?>;"></span>
                                <small class="fw-semibold"><?= htmlspecialchars($v['color_name']) ?></small>
                            </td>
                            <td>
                                <div><code class="small"><?= htmlspecialchars($v['sku']) ?></code></div>
                                <small class="text-muted"><i class="bi bi-upc"></i> <?= htmlspecialchars($v['barcode']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($v['rack_location'] ?: '-') ?></td>
                            <td><?= format_currency($v['purchase_price']) ?></td>
                            <td><strong class="text-dark"><?= format_currency($v['selling_price']) ?></strong></td>
                            <td>
                                <span class="badge <?= $isOut ? 'bg-danger' : ($isLow ? 'bg-warning text-dark' : 'bg-success') ?> fs-6">
                                    <?= $v['stock_quantity'] ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= format_currency($v['stock_quantity'] * $v['purchase_price']) ?></strong>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-dark" onclick='openAdjustModal(<?= json_encode($v) ?>)' title="Adjust Physical Stock">
                                    <i class="bi bi-sliders"></i> Adjust
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- Stock Movements Audit Ledger -->
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
                            <?= $m['qty_in'] > 0 ? '+' . $m['qty_in'] : '-' ?>
                        </td>
                        <td class="text-center fw-bold text-danger">
                            <?= $m['qty_out'] > 0 ? '-' . $m['qty_out'] : '-' ?>
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

<!-- ========================================== -->
<!-- MODAL: Adjust Stock -->
<!-- ========================================== -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Stock Reconciliation & Damage</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="inventory.php?tab=stock">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="variant_id" id="adjVariantId">
                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded mb-3">
                        <div class="fw-bold" id="adjProductName">-</div>
                        <div class="small text-muted" id="adjVariantDetails">-</div>
                        <div class="small mt-1">Current In-Stock: <strong class="text-primary" id="adjCurrentStock">0</strong> units</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">ACTION</label>
                            <select name="adj_type" class="form-select">
                                <option value="subtract">Subtract (-)</option>
                                <option value="add">Add (+)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">QUANTITY *</label>
                            <input type="number" name="quantity" class="form-control" required min="1" value="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">REASON FOR ADJUSTMENT *</label>
                        <select name="reason" class="form-select" required>
                            <option value="Damaged">Damaged Garment / Tear</option>
                            <option value="Defective">Defective Stitching / Fabric Defect</option>
                            <option value="Lost">Lost / Missing Stock</option>
                            <option value="Found">Found / Extra Stock</option>
                            <option value="Physical Count">Physical Audit Count Difference</option>
                            <option value="Other">Other Adjustment</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">REMARKS / AUDIT NOTES</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Found 2 pieces damaged during shelf rearrangement..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAdjustModal(v) {
    document.getElementById('adjVariantId').value = v.id;
    document.getElementById('adjProductName').innerText = v.product_name;
    document.getElementById('adjVariantDetails').innerText = `Size: ${v.size_name} | Color: ${v.color_name} | SKU: ${v.sku}`;
    document.getElementById('adjCurrentStock').innerText = v.stock_quantity;
    new bootstrap.Modal(document.getElementById('adjustStockModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
