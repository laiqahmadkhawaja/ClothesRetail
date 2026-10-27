<?php
// purchase_create.php - Record New Stock Consignment from Supplier
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_role('admin', 'manager', 'inventory');
$user = current_user();

// Handle Form Submission (Process BEFORE header to avoid headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_purchase') {
    $supplier_id = intval($_POST['supplier_id']);
    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
    $purchase_number = trim($_POST['purchase_number']);
    $discount = floatval($_POST['discount'] ?? 0);
    $freight = floatval($_POST['freight_charges'] ?? 0);
    $paid_amount = floatval($_POST['paid_amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');

    $item_variants = $_POST['item_variant_id'] ?? [];
    $item_quantities = $_POST['item_qty'] ?? [];
    $item_costs = $_POST['item_cost'] ?? [];

    if ($supplier_id <= 0 || empty($item_variants)) {
        $_SESSION['flash_error'] = "Please select a supplier and add at least one garment variant.";
        header("Location: purchase_create.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $subtotal = 0;
        $validated_items = [];

        foreach ($item_variants as $idx => $v_id) {
            $vid = intval($v_id);
            $qty = floatval($item_quantities[$idx] ?? 0);
            $cost = floatval($item_costs[$idx] ?? 0);
            if ($vid > 0 && $qty > 0) {
                $line_cost = $qty * $cost;
                $subtotal += $line_cost;

                // get product_id
                $stmtP = $pdo->prepare("SELECT product_id FROM product_variants WHERE id = ?");
                $stmtP->execute([$vid]);
                $pid = $stmtP->fetchColumn();

                $validated_items[] = [
                    'product_id' => $pid,
                    'variant_id' => $vid,
                    'qty'        => $qty,
                    'cost'       => $cost,
                    'line_cost'  => $line_cost
                ];
            }
        }

        if (empty($validated_items)) {
            throw new Exception("No valid items to purchase.");
        }

        $total_amount = ($subtotal - $discount) + $freight;
        $balance_due = max(0, $total_amount - $paid_amount);

        if ($paid_amount >= $total_amount) {
            $payment_status = 'paid';
        } elseif ($paid_amount > 0) {
            $payment_status = 'partial';
        } else {
            $payment_status = 'due';
        }

        if (empty($purchase_number)) {
            $purchase_number = 'PO-' . date('ymd') . '-' . mt_rand(100, 999);
        }

        // Insert Purchase
        $stmtPurch = $pdo->prepare("INSERT INTO purchases 
            (purchase_number, supplier_id, purchase_date, subtotal, discount, freight_charges, total_amount, paid_amount, balance_due, payment_status, payment_method, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtPurch->execute([
            $purchase_number, $supplier_id, $purchase_date, $subtotal, $discount, $freight,
            $total_amount, $paid_amount, $balance_due, $payment_status, $payment_method, $notes, $user['id'] ?? 1
        ]);
        $purchase_id = $pdo->lastInsertId();

        // Prepare statements for purchase items, variant update with Weighted Average Cost, and stock movement
        $stmtItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, variant_id, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtGetVar = $pdo->prepare("SELECT stock_quantity, purchase_price FROM product_variants WHERE id = ? FOR UPDATE");
        $stmtStockUpd = $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ?, purchase_price = ? WHERE id = ?");
        $stmtMove = $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by) VALUES (?, ?, NOW(), 'purchase', ?, ?, 0, ?, ?, ?)");
        $stmtProdUpd = $pdo->prepare("UPDATE products SET cost_price = (
            SELECT IFNULL(
                SUM(pv.stock_quantity * pv.purchase_price) / NULLIF(SUM(pv.stock_quantity), 0),
                AVG(pv.purchase_price)
            )
            FROM product_variants pv 
            WHERE pv.product_id = ? AND pv.status = 'active'
        ) WHERE id = ?");

        $affected_products = [];

        foreach ($validated_items as $vi) {
            // Lock and fetch current stock and current cost of variant
            $stmtGetVar->execute([$vi['variant_id']]);
            $curVar = $stmtGetVar->fetch();

            $oldStock = floatval($curVar['stock_quantity'] ?? 0);
            $oldCost = floatval($curVar['purchase_price'] ?? 0);
            $newQty = floatval($vi['qty']);
            $newCost = floatval($vi['cost']);

            // Weighted Average Cost Formula:
            // If existing inventory > 0: Avg Cost = ((oldStock * oldCost) + (newQty * newCost)) / (oldStock + newQty)
            // If existing inventory <= 0: Avg Cost = newCost
            if ($oldStock > 0 && ($oldStock + $newQty) > 0) {
                $avgCost = (($oldStock * $oldCost) + ($newQty * $newCost)) / ($oldStock + $newQty);
            } else {
                $avgCost = $newCost;
            }
            $avgCost = round($avgCost, 2);

            // Record purchase line item with actual purchase cost
            $stmtItem->execute([$purchase_id, $vi['product_id'], $vi['variant_id'], $vi['qty'], $vi['cost'], $vi['line_cost']]);
            
            // Increment stock and update variant purchase_price to the new Weighted Average Cost
            $stmtStockUpd->execute([$vi['qty'], $avgCost, $vi['variant_id']]);
            
            // Record stock movement showing old stock/cost, purchased cost, and new average cost
            $moveNote = "Supplier Purchase #$purchase_number [Old: {$oldStock} @ Rs. {$oldCost} | Bought: {$newQty} @ Rs. {$newCost} | New Avg Cost: Rs. {$avgCost}]";
            $stmtMove->execute([$vi['product_id'], $vi['variant_id'], $purchase_number, $vi['qty'], $vi['cost'], $moveNote, $user['id'] ?? 1]);

            $affected_products[$vi['product_id']] = true;
        }

        // Update each affected product's cost_price to match the weighted average across its active variants
        foreach (array_keys($affected_products) as $pid) {
            $stmtProdUpd->execute([$pid, $pid]);
        }

        // Supplier Ledger Update
        $stmtSup = $pdo->prepare("SELECT current_balance FROM suppliers WHERE id = ? FOR UPDATE");
        $stmtSup->execute([$supplier_id]);
        $currentSupBal = floatval($stmtSup->fetchColumn());

        // Purchase increases payable by total_amount, and immediate payment decreases payable
        $newSupBal = $currentSupBal + $total_amount - $paid_amount;
        $pdo->prepare("UPDATE suppliers SET current_balance = ? WHERE id = ?")->execute([$newSupBal, $supplier_id]);

        // Record credit in ledger (bill from supplier)
        $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, entry_date, transaction_type, reference_id, reference_no, debit, credit, balance, notes)
                       VALUES (?, ?, 'purchase', ?, ?, 0.00, ?, ?, ?)")
            ->execute([$supplier_id, $purchase_date, $purchase_id, $purchase_number, $total_amount, ($currentSupBal + $total_amount), "Stock Purchase Invoice #$purchase_number"]);

        // If paid amount > 0, record debit in ledger (payout to supplier)
        if ($paid_amount > 0) {
            $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, entry_date, transaction_type, reference_id, reference_no, debit, credit, balance, notes)
                           VALUES (?, ?, 'payment', ?, ?, ?, 0.00, ?, ?)")
                ->execute([$supplier_id, $purchase_date, $purchase_id, $purchase_number, $paid_amount, $newSupBal, "Payment for Purchase #$purchase_number ($payment_method)"]);

            // If cash paid and cash register active, update register payout
            $active_reg = get_active_register($pdo, $user['id'] ?? 0);
            if ($active_reg && $payment_method === 'cash') {
                $pdo->prepare("UPDATE cash_registers SET total_supplier_paid = total_supplier_paid + ? WHERE id = ?")
                    ->execute([$paid_amount, $active_reg['id']]);
            }
        }

        $pdo->commit();
        log_audit($pdo, 'Create Purchase', 'Purchases', "Created purchase #$purchase_number, total: Rs. $total_amount (Updated Weighted Average Cost)");

        $_SESSION['flash_success'] = "Purchase #$purchase_number saved successfully! Stock quantities and Weighted Average Costs have been updated.";
        header("Location: purchases.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_error'] = "Purchase error: " . $e->getMessage();
        header("Location: purchase_create.php");
        exit;
    }
}

$page_title = 'New Stock Purchase';
require_once __DIR__ . '/includes/header.php';

$suppliers = $pdo->query("SELECT * FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Fetch all active variants for selection
$variants = $pdo->query("SELECT pv.id, pv.product_id, pv.sku, pv.barcode, pv.purchase_price, pv.selling_price, pv.stock_quantity,
                                p.name AS product_name, p.product_code, s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         JOIN sizes s ON pv.size_id = s.id
                         JOIN colors c ON pv.color_id = c.id
                         WHERE pv.status = 'active' AND p.status = 'active'
                         ORDER BY p.name ASC, s.sort_order ASC")->fetchAll();
?>

<div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="fw-bold mb-0">Record Supplier Stock Purchase</h5>
            <small class="text-muted">Receive clothing variants into store inventory and record supplier Khata billing</small>
        </div>
        <a href="purchases.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Purchases
        </a>
    </div>

    <form method="POST" action="purchase_create.php" id="purchaseForm">
        <input type="hidden" name="action" value="save_purchase">

        <!-- Header Information -->
        <div class="row g-3 p-3 bg-light rounded-3 mb-4">
            <div class="col-12 col-md-4">
                <label class="form-label small fw-bold">SUPPLIER / TEXTILE MILL *</label>
                <select name="supplier_id" id="supplierSelect" class="form-select" required>
                    <option value="">Choose Supplier...</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>" data-balance="<?= $sup['current_balance'] ?>">
                            <?= htmlspecialchars($sup['name']) ?> (<?= htmlspecialchars($sup['company_name'] ?: 'Wholesale') ?>) - Balance: <?= format_currency($sup['current_balance']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-bold">PURCHASE / INVOICE NUMBER</label>
                <input type="text" name="purchase_number" class="form-control" placeholder="e.g. INV-8891" value="PO-<?= date('ymd') ?>-<?= rand(100, 999) ?>">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-bold">PURCHASE DATE</label>
                <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <!-- Line Items Table with Weighted Average Cost System -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-box-arrow-in-down me-1 text-primary"></i> Received Clothing Variants</h6>
            <span class="badge bg-primary-subtle text-primary border small"><i class="bi bi-calculator me-1"></i> Weighted Average Cost (WAC) Auto-Updated</span>
        </div>
        <div class="table-responsive mb-3">
            <table class="table table-bordered align-middle" id="purchaseItemsTable">
                <thead class="table-light small text-muted">
                    <tr>
                        <th style="min-width: 280px;">Garment & Size/Color Variant *</th>
                        <th style="width: 140px;" class="text-center">Current Stock & Cost</th>
                        <th style="width: 120px;" class="text-center">Receive Qty *</th>
                        <th style="width: 140px;" class="text-center">Purchase Cost (Rs) *</th>
                        <th style="width: 140px;" class="text-center">New Avg Cost</th>
                        <th style="width: 130px;" class="text-end">Line Total (Rs)</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody id="purchaseItemsBody">
                    <!-- Dynamic Rows -->
                </tbody>
            </table>
        </div>

        <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="addRowBtn">
            <i class="bi bi-plus-circle me-1"></i> Add Another Garment Variant
        </button>

        <!-- Totals & Payment Settlement -->
        <div class="row g-3 justify-content-end">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="p-3 bg-light rounded-3 border">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Subtotal:</span>
                        <strong id="purchaseSubtotal">Rs. 0</strong>
                    </div>
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-6 text-muted small">Supplier Discount:</div>
                        <div class="col-6">
                            <input type="number" name="discount" id="purchaseDiscount" class="form-control form-control-sm text-end" value="0" min="0">
                        </div>
                    </div>
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-6 text-muted small">Freight / Shipping:</div>
                        <div class="col-6">
                            <input type="number" name="freight_charges" id="purchaseFreight" class="form-control form-control-sm text-end" value="0" min="0">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top border-bottom py-2 my-2">
                        <span class="fw-bold fs-6">Grand Total Bill:</span>
                        <span class="fw-bolder fs-5 text-primary" id="purchaseTotalBill">Rs. 0</span>
                    </div>

                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-6 text-muted small">Payment Method:</div>
                        <div class="col-6">
                            <select name="payment_method" class="form-select form-select-sm">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-6 text-muted small">Amount Paid Now:</div>
                        <div class="col-6">
                            <input type="number" name="paid_amount" id="purchasePaid" class="form-control form-control-sm text-end fw-bold" value="0" min="0">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center text-danger fw-bold border-top pt-2">
                        <span>Balance Due to Supplier:</span>
                        <span id="purchaseBalanceDue">Rs. 0</span>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label small fw-bold text-muted">PURCHASE NOTES</label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Summer lawn unstitched consignment received via Faisalabad cargo"></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm mt-3">
                    <i class="bi bi-check2-circle me-1"></i> Save Purchase & Update Stock
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Variant Options Data Template for JS -->
<script>
const variantsList = <?= json_encode($variants) ?>;

document.addEventListener("DOMContentLoaded", function() {
    // Add initial row
    addPurchaseRow();

    document.getElementById("addRowBtn").addEventListener("click", addPurchaseRow);

    document.getElementById("purchaseDiscount").addEventListener("input", recalcPurchaseTotals);
    document.getElementById("purchaseFreight").addEventListener("input", recalcPurchaseTotals);
    document.getElementById("purchasePaid").addEventListener("input", recalcPurchaseTotals);
});

function addPurchaseRow() {
    const tbody = document.getElementById("purchaseItemsBody");
    const tr = document.createElement("tr");

    let optionsHtml = '<option value="">Select Garment Variant...</option>';
    variantsList.forEach(v => {
        optionsHtml += `<option value="${v.id}" data-cost="${v.purchase_price}" data-stock="${v.stock_quantity}">
            ${v.product_name} [${v.size_name} / ${v.color_name}] - (SKU: ${v.sku})
        </option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="item_variant_id[]" class="form-select form-select-sm variant-picker" required onchange="onVariantChange(this)">
                ${optionsHtml}
            </select>
        </td>
        <td class="text-center text-muted stock-display small">-</td>
        <td>
            <input type="number" name="item_qty[]" class="form-control form-control-sm text-center qty-input fw-bold" value="1" min="0.01" step="any" required oninput="recalcPurchaseTotals()">
        </td>
        <td>
            <input type="number" name="item_cost[]" class="form-control form-control-sm text-end cost-input" value="0" min="0" step="any" required oninput="recalcPurchaseTotals()">
        </td>
        <td class="text-center avg-cost-display small">
            <span class="text-muted">-</span>
        </td>
        <td class="text-end fw-bold line-total-display">Rs. 0</td>
        <td class="text-center">
            <button type="button" class="btn btn-link text-danger p-0" onclick="removeRow(this)">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function onVariantChange(selectEl) {
    const tr = selectEl.closest("tr");
    const opt = selectEl.options[selectEl.selectedIndex];
    const cost = parseFloat(opt?.dataset?.cost) || 0;
    const stock = parseFloat(opt?.dataset?.stock) || 0;

    tr.querySelector(".cost-input").value = cost;
    tr.querySelector(".stock-display").innerHTML = `<span class="fw-semibold text-dark">${stock}</span> <small class="text-muted">@ Rs.${cost}</small>`;
    recalcPurchaseTotals();
}

function removeRow(btn) {
    const tbody = document.getElementById("purchaseItemsBody");
    if (tbody.querySelectorAll("tr").length > 1) {
        btn.closest("tr").remove();
        recalcPurchaseTotals();
    } else {
        alert("At least one item row is required.");
    }
}

function recalcPurchaseTotals() {
    let subtotal = 0;
    document.querySelectorAll("#purchaseItemsBody tr").forEach(tr => {
        const selectEl = tr.querySelector(".variant-picker");
        const opt = selectEl?.options[selectEl.selectedIndex];
        const oldCost = parseFloat(opt?.dataset?.cost) || 0;
        const oldStock = parseFloat(opt?.dataset?.stock) || 0;

        const cost = parseFloat(tr.querySelector(".cost-input")?.value) || 0;
        const qty = parseFloat(tr.querySelector(".qty-input")?.value) || 0;
        const line = cost * qty;
        tr.querySelector(".line-total-display").innerText = `Rs. ${Math.round(line).toLocaleString()}`;
        subtotal += line;

        // Compute preview of Weighted Average Cost
        const avgDisplay = tr.querySelector(".avg-cost-display");
        if (avgDisplay) {
            if (selectEl && selectEl.value && qty > 0) {
                let newAvg = cost;
                if (oldStock > 0 && (oldStock + qty) > 0) {
                    newAvg = ((oldStock * oldCost) + (qty * cost)) / (oldStock + qty);
                }
                avgDisplay.innerHTML = `<span class="badge bg-success-subtle text-success border border-success fw-bold">Rs. ${newAvg.toFixed(2)}</span>`;
            } else {
                avgDisplay.innerHTML = `<span class="text-muted">-</span>`;
            }
        }
    });

    const disc = parseFloat(document.getElementById("purchaseDiscount").value) || 0;
    const freight = parseFloat(document.getElementById("purchaseFreight").value) || 0;
    const paid = parseFloat(document.getElementById("purchasePaid").value) || 0;

    const grandTotal = Math.max(0, (subtotal - disc) + freight);
    const balance = Math.max(0, grandTotal - paid);

    document.getElementById("purchaseSubtotal").innerText = `Rs. ${Math.round(subtotal).toLocaleString()}`;
    document.getElementById("purchaseTotalBill").innerText = `Rs. ${Math.round(grandTotal).toLocaleString()}`;
    document.getElementById("purchaseBalanceDue").innerText = `Rs. ${Math.round(balance).toLocaleString()}`;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
