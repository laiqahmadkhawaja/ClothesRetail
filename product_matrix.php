<?php
// product_matrix.php - Dedicated Clothing Size x Color Variant Matrix Editor
$page_title = 'Size & Color Variant Matrix';
require_once __DIR__ . '/includes/header.php';

// Fetch all active products for the picker
$products = $pdo->query("SELECT id, product_code, name FROM products WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$selected_product_id = intval($_GET['product_id'] ?? ($products[0]['id'] ?? 0));

// Fetch selected product details
$stmtProduct = $pdo->prepare("SELECT p.*, c.name AS category_name, b.name AS brand_name 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             LEFT JOIN brands b ON p.brand_id = b.id 
                             WHERE p.id = ?");
$stmtProduct->execute([$selected_product_id]);
$current_product = $stmtProduct->fetch();

if (!$current_product && !empty($products)) {
    $selected_product_id = $products[0]['id'];
    $stmtProduct->execute([$selected_product_id]);
    $current_product = $stmtProduct->fetch();
}

// Fetch all available Sizes and Colors
$sizes = $pdo->query("SELECT * FROM sizes ORDER BY sort_order ASC, id ASC")->fetchAll();
$colors = $pdo->query("SELECT * FROM colors ORDER BY name ASC")->fetchAll();

// Fetch existing variants for this product
$stmtVariants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ?");
$stmtVariants->execute([$selected_product_id]);
$rawVariants = $stmtVariants->fetchAll();

// Index existing variants by [size_id][color_id]
$matrix = [];
$total_matrix_stock = 0;
$total_matrix_value = 0;

foreach ($rawVariants as $v) {
    $matrix[$v['size_id']][$v['color_id']] = $v;
    $total_matrix_stock += $v['stock_quantity'];
    $total_matrix_value += ($v['stock_quantity'] * $v['purchase_price']);
}
?>

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="row g-3 align-items-center">
        <!-- Product Selector Dropdown -->
        <div class="col-12 col-md-6 col-lg-5">
            <label class="form-label small fw-bold text-muted mb-1">SELECT CLOTHING ITEM</label>
            <form method="GET" action="product_matrix.php">
                <select name="product_id" class="form-select form-select-lg" onchange="this.form.submit()">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $selected_product_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Product Summary Info Badges -->
        <?php if ($current_product): ?>
        <div class="col-12 col-md-6 col-lg-7">
            <div class="d-flex flex-wrap gap-2 justify-content-md-end align-items-center">
                <div class="p-2 border rounded bg-light text-center" style="min-width: 110px;">
                    <small class="text-muted d-block" style="font-size: 0.72rem;">Category / Brand</small>
                    <strong class="small"><?= htmlspecialchars($current_product['category_name'] ?? '-') ?></strong>
                </div>
                <div class="p-2 border rounded bg-light text-center" style="min-width: 100px;">
                    <small class="text-muted d-block" style="font-size: 0.72rem;">Total Units</small>
                    <strong class="text-primary fs-6" id="grandStockDisplay"><?= number_format($total_matrix_stock) ?></strong>
                </div>
                <div class="p-2 border rounded bg-light text-center" style="min-width: 120px;">
                    <small class="text-muted d-block" style="font-size: 0.72rem;">Inventory Cost</small>
                    <strong class="text-success fs-6"><?= format_currency($total_matrix_value) ?></strong>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-success btn-sm px-3 shadow-sm" id="saveMatrixBtn">
                        <i class="bi bi-check2-circle me-1"></i> <strong>Save Matrix Updates</strong>
                    </button>
                    <a href="barcodes.php?product_id=<?= $current_product['id'] ?>" class="btn btn-outline-dark btn-sm" title="Print Barcodes for this Matrix">
                        <i class="bi bi-barcode"></i> Labels
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$current_product): ?>
    <div class="alert alert-warning">No products available. Please create a product first in Garments Catalog.</div>
<?php else: 
    $defaultCost = floatval($current_product['cost_price'] ?? 850) ?: 850;
    $defaultSell = floatval($current_product['selling_price'] ?? 1450) ?: 1450;
    $defaultWhole = floatval($current_product['wholesale_price'] ?? 1200) ?: 1200;
?>

<!-- Quick Batch Values Applier -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-light">
    <div class="row g-2 align-items-center">
        <div class="col-12 col-md-auto">
            <span class="small fw-bold text-dark"><i class="bi bi-magic me-1 text-primary"></i> Batch Price Fill:</span>
        </div>
        <div class="col-6 col-md-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text">Cost:</span>
                <input type="number" id="batchCostPrice" class="form-control" placeholder="Purchase Price" value="<?= $defaultCost ?>">
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text">Sell:</span>
                <input type="number" id="batchSellingPrice" class="form-control" placeholder="Retail Price" value="<?= $defaultSell ?>">
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text">Wholesale:</span>
                <input type="number" id="batchWholesalePrice" class="form-control" placeholder="Wholesale" value="<?= $defaultWhole ?>">
            </div>
        </div>
        <div class="col-6 col-md-3">
            <button type="button" class="btn btn-sm btn-outline-primary w-100" id="applyBatchPricingBtn">
                Apply to All Enabled Cells
            </button>
        </div>
    </div>
</div>

<!-- Interactive Clothing Matrix Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-grid-3x3 me-2 text-primary"></i> <?= htmlspecialchars($current_product['name']) ?> &mdash; Stock Grid</h6>
            <small class="text-muted">Enter stock quantities by Size (rows) and Color (columns). Price and SKU can be tailored per variant.</small>
        </div>
        <span class="badge bg-primary-subtle text-primary">Live Inventory Matrix</span>
    </div>

    <div class="table-responsive" style="max-height: 650px;">
        <table class="table table-bordered table-hover align-middle mb-0 matrix-table" id="clothingMatrixTable">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 90px; text-align: center; vertical-align: middle; z-index: 3;" class="bg-light">
                        <span class="text-muted small">SIZE \ COLOR</span>
                    </th>
                    <?php foreach ($colors as $c): ?>
                        <th style="min-width: 140px; text-align: center;">
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                <span class="color-dot" style="background-color: <?= htmlspecialchars($c['hex_code']) ?>;"></span>
                                <span class="fw-bold small"><?= htmlspecialchars($c['name']) ?></span>
                            </div>
                        </th>
                    <?php endforeach; ?>
                    <th style="min-width: 85px; text-align: center; background-color: #e2e8f0;">Total by Size</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sizes as $s): ?>
                    <tr data-size-id="<?= $s['id'] ?>">
                        <!-- Size Row Header -->
                        <td class="text-center bg-light fw-bold">
                            <span class="badge bg-dark text-white px-2 py-1" style="font-size: 0.85rem;"><?= htmlspecialchars($s['name']) ?></span>
                            <div class="text-muted" style="font-size: 0.65rem;"><?= htmlspecialchars($s['size_group']) ?></div>
                        </td>

                        <!-- Cells per Color -->
                        <?php foreach ($colors as $c): 
                            $v = $matrix[$s['id']][$c['id']] ?? null;
                            $stock = $v ? $v['stock_quantity'] : 0;
                            $cost = $v ? $v['purchase_price'] : $defaultCost;
                            $sell = $v ? $v['selling_price'] : $defaultSell;
                            $wholesale = $v ? $v['wholesale_price'] : $defaultWhole;
                            $sku = $v ? $v['sku'] : '';
                            $barcode = $v ? $v['barcode'] : '';
                            $hasVariant = ($v !== null);
                        ?>
                            <td class="p-2 text-center matrix-cell <?= $hasVariant ? 'bg-white' : 'bg-light-subtle' ?>" 
                                data-size-id="<?= $s['id'] ?>" 
                                data-color-id="<?= $c['id'] ?>">
                                
                                <!-- Stock Input -->
                                <div class="d-flex align-items-center justify-content-center gap-1 mb-1">
                                    <span class="small text-muted fw-semibold">Stock:</span>
                                    <input type="number" 
                                           class="form-control form-control-sm matrix-stock-input text-center fw-bold <?= $stock > 0 ? 'text-primary border-primary' : 'text-muted' ?>" 
                                           value="<?= $stock ?>" 
                                           min="0" 
                                           style="width: 65px;">
                                </div>

                                <!-- Selling & Cost Price -->
                                <div class="row g-1 small">
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm matrix-cost-input text-muted text-center px-1" 
                                               placeholder="Cost" value="<?= $cost ?>" title="Purchase Cost (Rs.)" style="font-size: 0.72rem;">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm matrix-price-input fw-semibold text-center px-1" 
                                               placeholder="Sell" value="<?= $sell ?>" title="Selling Price (Rs.)" style="font-size: 0.72rem;">
                                    </div>
                                </div>

                                <!-- SKU & Barcode identifiers -->
                                <input type="hidden" class="matrix-sku-input" value="<?= htmlspecialchars($sku) ?>">
                                <input type="hidden" class="matrix-barcode-input" value="<?= htmlspecialchars($barcode) ?>">
                                <input type="hidden" class="matrix-wholesale-input" value="<?= $wholesale ?>">

                                <?php if ($barcode): ?>
                                    <div class="text-muted mt-1" style="font-size: 0.65rem;">
                                        <i class="bi bi-upc"></i> <?= htmlspecialchars($barcode) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>

                        <!-- Row Total (Total units for this Size) -->
                        <td class="text-center fw-bold fs-6 bg-light text-dark row-total-cell" data-size-id="<?= $s['id'] ?>">
                            0
                        </td>
                    </tr>
                <?php endforeach; ?>

                <!-- Column Totals Footer -->
                <tr class="table-light fw-bold">
                    <td class="text-center bg-secondary text-white">Total by Color</td>
                    <?php foreach ($colors as $c): ?>
                        <td class="text-center col-total-cell text-primary" data-color-id="<?= $c['id'] ?>">0</td>
                    <?php endforeach; ?>
                    <td class="text-center fs-5 text-success bg-secondary-subtle" id="grandMatrixTotalDisplay">0</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    recalcMatrixTotals();

    // Recalculate on stock change
    document.querySelectorAll(".matrix-stock-input").forEach(input => {
        input.addEventListener("input", function() {
            if (parseInt(this.value) > 0) {
                this.classList.add("text-primary", "border-primary");
                this.classList.remove("text-muted");
            } else {
                this.classList.remove("text-primary", "border-primary");
                this.classList.add("text-muted");
            }
            recalcMatrixTotals();
        });
    });

    // Apply Batch Pricing
    document.getElementById("applyBatchPricingBtn").addEventListener("click", function() {
        const cost = document.getElementById("batchCostPrice").value;
        const sell = document.getElementById("batchSellingPrice").value;
        const wholesale = document.getElementById("batchWholesalePrice").value;

        document.querySelectorAll(".matrix-cost-input").forEach(el => el.value = cost);
        document.querySelectorAll(".matrix-price-input").forEach(el => el.value = sell);
        document.querySelectorAll(".matrix-wholesale-input").forEach(el => el.value = wholesale);

        showToast("Batch prices applied to matrix cells!", "info");
    });

    // Save Matrix via AJAX
    document.getElementById("saveMatrixBtn").addEventListener("click", function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving Matrix...';

        const cells = [];
        document.querySelectorAll(".matrix-cell").forEach(cell => {
            const sizeId = cell.dataset.sizeId;
            const colorId = cell.dataset.colorId;
            const stock = cell.querySelector(".matrix-stock-input").value;
            const cost = cell.querySelector(".matrix-cost-input").value;
            const price = cell.querySelector(".matrix-price-input").value;
            const wholesale = cell.querySelector(".matrix-wholesale-input").value;
            const sku = cell.querySelector(".matrix-sku-input").value;
            const barcode = cell.querySelector(".matrix-barcode-input").value;

            cells.push({
                size_id: sizeId,
                color_id: colorId,
                stock: stock,
                purchase_price: cost,
                selling_price: price,
                wholesale_price: wholesale,
                sku: sku,
                barcode: barcode
            });
        });

        fetch('ajax/matrix_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: <?= $current_product['id'] ?>,
                cells: cells
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> <strong>Save Matrix Updates</strong>';

            if (data.success) {
                showToast(data.message, "success");
            } else {
                alert("Error saving matrix: " + data.error);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> <strong>Save Matrix Updates</strong>';
            alert("Error: " + err.message);
        });
    });
});

// Calculate Row, Column and Grand Totals
function recalcMatrixTotals() {
    let grandTotal = 0;
    const colTotals = {};

    // Calculate Row Totals
    document.querySelectorAll("tr[data-size-id]").forEach(tr => {
        let rowSum = 0;
        tr.querySelectorAll(".matrix-cell").forEach(cell => {
            const colorId = cell.dataset.colorId;
            const val = parseInt(cell.querySelector(".matrix-stock-input").value) || 0;
            rowSum += val;
            colTotals[colorId] = (colTotals[colorId] || 0) + val;
        });
        tr.querySelector(".row-total-cell").innerText = rowSum;
        grandTotal += rowSum;
    });

    // Populate Column Totals
    document.querySelectorAll(".col-total-cell").forEach(td => {
        const colorId = td.dataset.colorId;
        td.innerText = colTotals[colorId] || 0;
    });

    document.getElementById("grandMatrixTotalDisplay").innerText = grandTotal;
    const grandStockDisplay = document.getElementById("grandStockDisplay");
    if (grandStockDisplay) grandStockDisplay.innerText = grandTotal.toLocaleString();
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
