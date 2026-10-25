<?php
// returns.php - Sales Returns & Product Exchange Engine
$page_title = 'Sales Returns & Garment Exchanges';
require_once __DIR__ . '/includes/header.php';

// Fetch recent 15 returns & exchanges
$returns = $pdo->query("SELECT r.*, s.invoice_number AS orig_invoice, c.name AS customer_name, u.full_name AS cashier_name
                        FROM sales_returns r
                        JOIN sales s ON r.sale_id = s.id
                        LEFT JOIN customers c ON r.customer_id = c.id
                        LEFT JOIN users u ON r.created_by = u.id
                        ORDER BY r.id DESC
                        LIMIT 15")->fetchAll();

// All active variants for replacement selection in exchanges
$allVariants = $pdo->query("SELECT pv.id, pv.sku, pv.barcode, pv.selling_price, pv.stock_quantity,
                                  p.name AS product_name, s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex
                           FROM product_variants pv
                           JOIN products p ON pv.product_id = p.id
                           JOIN sizes s ON pv.size_id = s.id
                           JOIN colors c ON pv.color_id = c.id
                           WHERE pv.status = 'active' AND pv.stock_quantity > 0
                           ORDER BY p.name ASC, s.sort_order ASC")->fetchAll();
?>

<div class="row g-3">
    <!-- LEFT: Return & Exchange Processing Wizard -->
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-arrow-left-right text-primary me-2"></i> Process Return or Exchange</h5>
            <p class="text-muted small">Search the original customer invoice to initiate a size/color exchange or refund.</p>

            <!-- Search Original Invoice -->
            <div class="row g-2 mb-4">
                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="invoiceSearchInput" class="form-control" placeholder="Scan or enter Invoice # (e.g. AMF-10001)...">
                        <button class="btn btn-primary" type="button" id="lookupInvoiceBtn">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Load Invoice
                        </button>
                    </div>
                </div>
            </div>

            <!-- Invoice Found Container (Hidden until invoice loaded) -->
            <div id="invoiceLoadedSection" class="d-none">
                <!-- Invoice Summary Header -->
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="row g-2 align-items-center">
                        <div class="col-6 col-md-3">
                            <span class="text-muted small d-block">Invoice #:</span>
                            <strong class="text-primary fs-6" id="loadedInvNumber">-</strong>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted small d-block">Date:</span>
                            <span id="loadedInvDate">-</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted small d-block">Customer:</span>
                            <strong id="loadedCustomerName">-</strong>
                        </div>
                        <div class="col-6 col-md-3 text-md-end">
                            <span class="text-muted small d-block">Original Net Total:</span>
                            <strong class="text-dark fs-6" id="loadedNetTotal">-</strong>
                        </div>
                    </div>
                </div>

                <!-- Transaction Mode Switch: Refund vs Exchange -->
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="return_mode" id="modeExchange" value="exchange" checked autocomplete="off">
                    <label class="btn btn-outline-primary py-2 fw-bold" for="modeExchange">
                        <i class="bi bi-arrow-repeat me-1"></i> Product Exchange (Swap Size / Color / Garment)
                    </label>

                    <input type="radio" class="btn-check" name="return_mode" id="modeRefund" value="refund" autocomplete="off">
                    <label class="btn btn-outline-danger py-2 fw-bold" for="modeRefund">
                        <i class="bi bi-cash-stack me-1"></i> Sales Return & Refund (Cash or Khata)
                    </label>
                </div>

                <!-- Step 1: Returned Items Selection -->
                <h6 class="fw-bold text-danger mb-2"><i class="bi bi-dash-circle me-1"></i> 1. Select Garments Being Returned</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle" id="returnItemsTable">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Garment & Variant</th>
                                <th style="width: 80px;" class="text-center">Sold Qty</th>
                                <th style="width: 100px;" class="text-center">Returnable</th>
                                <th style="width: 100px;" class="text-center">Return Qty</th>
                                <th style="width: 120px;" class="text-center">Condition</th>
                                <th style="width: 110px;" class="text-end">Refund Value</th>
                            </tr>
                        </thead>
                        <tbody id="returnItemsBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>

                <!-- Step 2: Replacement Items Selection (For Exchange Mode) -->
                <div id="exchangeItemsSection" class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-success mb-0"><i class="bi bi-plus-circle me-1"></i> 2. Select Replacement Garments (New Size/Color)</h6>
                        <button type="button" class="btn btn-sm btn-outline-success" id="addExchangeRowBtn">
                            <i class="bi bi-plus"></i> Add Replacement Item
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="exchangeItemsTable">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th>Select Replacement Variant</th>
                                    <th style="width: 120px;" class="text-center">In Stock</th>
                                    <th style="width: 100px;" class="text-center">Qty</th>
                                    <th style="width: 110px;" class="text-end">Price</th>
                                    <th style="width: 110px;" class="text-end">Subtotal</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="exchangeItemsBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Financial Calculation & Settlement -->
                <div class="p-3 bg-light rounded-3 border mb-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-md-4">
                            <span class="small text-muted d-block">Returned Value (Credit):</span>
                            <strong class="text-danger fs-5" id="calcReturnValue">Rs. 0</strong>
                        </div>
                        <div class="col-12 col-md-4">
                            <span class="small text-muted d-block">Replacement Value (Debit):</span>
                            <strong class="text-success fs-5" id="calcExchangeValue">Rs. 0</strong>
                        </div>
                        <div class="col-12 col-md-4 text-md-end">
                            <span class="small text-muted d-block" id="calcDiffLabel">Net Difference:</span>
                            <h4 class="fw-bolder mb-0 text-dark" id="calcNetDiff">Rs. 0</h4>
                        </div>
                    </div>
                </div>

                <!-- Settlement Details & Notes -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold">SETTLEMENT METHOD</label>
                        <select id="settleMethodSelect" class="form-select">
                            <option value="cash">Cash (Counter)</option>
                            <option value="credit">Customer Khata (Credit/Debit Balance)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">REASON FOR RETURN / EXCHANGE *</label>
                        <select id="returnReasonSelect" class="form-select">
                            <option value="Wrong Size / Need Larger">Wrong Size / Need Larger</option>
                            <option value="Wrong Size / Need Smaller">Wrong Size / Need Smaller</option>
                            <option value="Color Change Request">Customer Wants Different Color</option>
                            <option value="Design Change / Swapping">Different Clothing Article</option>
                            <option value="Fabric Defect / Damage">Fabric or Stitching Defect</option>
                            <option value="Gift Return">Gift Return by Recipient</option>
                            <option value="Customer Dissatisfied">Customer Dissatisfied</option>
                        </select>
                    </div>
                </div>

                <button type="button" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm" id="processReturnBtn">
                    <i class="bi bi-check2-circle me-1"></i> Confirm & Complete Return / Exchange
                </button>
            </div>
        </div>
    </div>

    <!-- RIGHT: Recent Returns & Exchanges Ledger -->
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-secondary me-2"></i> Recent Returns & Exchanges</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Return #</th>
                            <th>Original Inv</th>
                            <th>Type</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($returns)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No returns recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($returns as $ret): ?>
                                <tr>
                                    <td>
                                        <strong class="text-dark"><?= htmlspecialchars($ret['return_number']) ?></strong>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?= format_date($ret['return_date'], false) ?></div>
                                    </td>
                                    <td><code><?= htmlspecialchars($ret['orig_invoice']) ?></code></td>
                                    <td>
                                        <span class="badge <?= $ret['return_type'] === 'exchange' ? 'bg-primary' : 'bg-danger' ?>">
                                            <?= ucfirst($ret['return_type']) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= format_currency($ret['refund_amount']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const activeVariants = <?= json_encode($allVariants) ?>;
let currentSale = null;
let currentItems = [];

document.addEventListener("DOMContentLoaded", function() {
    // Lookup Invoice
    document.getElementById("lookupInvoiceBtn").addEventListener("click", lookupInvoice);
    document.getElementById("invoiceSearchInput").addEventListener("keydown", function(e) {
        if (e.key === "Enter") {
            e.preventDefault();
            lookupInvoice();
        }
    });

    // Toggle Return vs Exchange mode
    document.querySelectorAll("input[name='return_mode']").forEach(radio => {
        radio.addEventListener("change", function() {
            const exSection = document.getElementById("exchangeItemsSection");
            if (this.value === "exchange") {
                exSection.classList.remove("d-none");
            } else {
                exSection.classList.add("d-none");
            }
            recalcReturnDiff();
        });
    });

    // Add Exchange Row
    document.getElementById("addExchangeRowBtn").addEventListener("click", addExchangeRow);

    // Process Return Button
    document.getElementById("processReturnBtn").addEventListener("click", submitProcessReturn);
});

function lookupInvoice() {
    const inv = document.getElementById("invoiceSearchInput").value.trim();
    if (!inv) {
        alert("Please enter an invoice number.");
        return;
    }

    fetch(`ajax/get_invoice.php?invoice_number=${encodeURIComponent(inv)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.error);
                return;
            }

            currentSale = data.sale;
            currentItems = data.items;

            document.getElementById("loadedInvNumber").innerText = data.sale.invoice_number;
            document.getElementById("loadedInvDate").innerText = data.sale.sale_date;
            document.getElementById("loadedCustomerName").innerText = data.sale.customer_name || 'Walk-in';
            document.getElementById("loadedNetTotal").innerText = `Rs. ${parseFloat(data.sale.net_total).toLocaleString()}`;

            // Populate Return Items Table
            const tbody = document.getElementById("returnItemsBody");
            tbody.innerHTML = "";

            data.items.forEach(it => {
                const tr = document.createElement("tr");
                const returnable = parseInt(it.returnable_qty);

                tr.innerHTML = `
                    <td>
                        <strong class="text-dark">${it.product_name}</strong>
                        <div class="small text-muted">[${it.size_name} / ${it.color_name}] - (Rs. ${parseFloat(it.unit_price).toLocaleString()})</div>
                    </td>
                    <td class="text-center">${it.quantity}</td>
                    <td class="text-center fw-bold ${returnable > 0 ? 'text-success' : 'text-danger'}">${returnable}</td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center ret-qty-input fw-bold" 
                               value="0" min="0" max="${returnable}" data-item-id="${it.id}" data-variant-id="${it.variant_id}" 
                               data-price="${it.unit_price}" ${returnable <= 0 ? 'disabled' : ''} oninput="recalcReturnDiff()">
                    </td>
                    <td class="text-center">
                        <select class="form-select form-select-sm ret-condition-select">
                            <option value="restock">Restock to Inventory</option>
                            <option value="defective">Damaged / Defective</option>
                        </select>
                    </td>
                    <td class="text-end fw-bold ret-line-total">Rs. 0</td>
                `;
                tbody.appendChild(tr);
            });

            // Reset exchange rows
            document.getElementById("exchangeItemsBody").innerHTML = "";
            addExchangeRow();

            document.getElementById("invoiceLoadedSection").classList.remove("d-none");
            recalcReturnDiff();
        })
        .catch(err => alert("Error: " + err.message));
}

function addExchangeRow() {
    const tbody = document.getElementById("exchangeItemsBody");
    const tr = document.createElement("tr");

    let optHtml = '<option value="">Select Replacement Garment Variant...</option>';
    activeVariants.forEach(v => {
        optHtml += `<option value="${v.id}" data-price="${v.selling_price}" data-stock="${v.stock_quantity}">
            ${v.product_name} [${v.size_name} / ${v.color_name}] - Rs. ${parseFloat(v.selling_price).toLocaleString()}
        </option>`;
    });

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm exch-variant-select" onchange="onExchVariantChange(this)">
                ${optHtml}
            </select>
        </td>
        <td class="text-center text-muted exch-stock-display">-</td>
        <td class="text-center">
            <input type="number" class="form-control form-control-sm text-center exch-qty-input fw-bold" value="1" min="1" oninput="recalcReturnDiff()">
        </td>
        <td class="text-end exch-unit-price">Rs. 0</td>
        <td class="text-end fw-bold exch-line-total">Rs. 0</td>
        <td class="text-center">
            <button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove(); recalcReturnDiff();">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function onExchVariantChange(sel) {
    const tr = sel.closest("tr");
    const opt = sel.options[sel.selectedIndex];
    const price = parseFloat(opt.dataset.price) || 0;
    const stock = opt.dataset.stock || 0;

    tr.querySelector(".exch-stock-display").innerText = `${stock} in hand`;
    tr.querySelector(".exch-unit-price").innerText = `Rs. ${price.toLocaleString()}`;
    recalcReturnDiff();
}

function recalcReturnDiff() {
    let returnVal = 0;
    document.querySelectorAll("#returnItemsBody tr").forEach(tr => {
        const input = tr.querySelector(".ret-qty-input");
        const qty = parseInt(input.value) || 0;
        const price = parseFloat(input.dataset.price) || 0;
        const line = qty * price;
        tr.querySelector(".ret-line-total").innerText = `Rs. ${line.toLocaleString()}`;
        returnVal += line;
    });

    const isExchange = document.getElementById("modeExchange").checked;
    let exchangeVal = 0;

    if (isExchange) {
        document.querySelectorAll("#exchangeItemsBody tr").forEach(tr => {
            const sel = tr.querySelector(".exch-variant-select");
            const opt = sel.options[sel.selectedIndex];
            const price = parseFloat(opt ? opt.dataset.price : 0) || 0;
            const qty = parseInt(tr.querySelector(".exch-qty-input").value) || 0;
            const line = price * qty;
            tr.querySelector(".exch-line-total").innerText = `Rs. ${line.toLocaleString()}`;
            exchangeVal += line;
        });
    }

    const netDiff = exchangeVal - returnVal;

    document.getElementById("calcReturnValue").innerText = `Rs. ${returnVal.toLocaleString()}`;
    document.getElementById("calcExchangeValue").innerText = `Rs. ${exchangeVal.toLocaleString()}`;

    const diffLabel = document.getElementById("calcDiffLabel");
    const diffEl = document.getElementById("calcNetDiff");

    if (isExchange) {
        if (netDiff > 0) {
            diffLabel.innerText = "Customer Pays Extra Difference:";
            diffEl.className = "fw-bolder mb-0 text-success";
            diffEl.innerText = `+ Rs. ${netDiff.toLocaleString()}`;
        } else if (netDiff < 0) {
            diffLabel.innerText = "Store Refunds Excess Value:";
            diffEl.className = "fw-bolder mb-0 text-danger";
            diffEl.innerText = `- Rs. ${Math.abs(netDiff).toLocaleString()}`;
        } else {
            diffLabel.innerText = "Even Exchange (No Difference):";
            diffEl.className = "fw-bolder mb-0 text-dark";
            diffEl.innerText = "Rs. 0";
        }
    } else {
        diffLabel.innerText = "Store Refunds Customer:";
        diffEl.className = "fw-bolder mb-0 text-danger";
        diffEl.innerText = `Rs. ${returnVal.toLocaleString()}`;
    }
}

function submitProcessReturn() {
    if (!currentSale) return;

    const isExchange = document.getElementById("modeExchange").checked;
    const returnType = isExchange ? 'exchange' : 'refund';

    const returnedItems = [];
    document.querySelectorAll("#returnItemsBody tr").forEach(tr => {
        const input = tr.querySelector(".ret-qty-input");
        const qty = parseInt(input.value) || 0;
        if (qty > 0) {
            returnedItems.push({
                sale_item_id: parseInt(input.dataset.itemId),
                variant_id: parseInt(input.dataset.variantId),
                qty: qty,
                unit_price: parseFloat(input.dataset.price),
                condition: tr.querySelector(".ret-condition-select").value
            });
        }
    });

    if (returnedItems.length === 0) {
        alert("Please enter a return quantity (> 0) for at least one garment.");
        return;
    }

    const exchangeItems = [];
    if (isExchange) {
        document.querySelectorAll("#exchangeItemsBody tr").forEach(tr => {
            const sel = tr.querySelector(".exch-variant-select");
            const varId = parseInt(sel.value);
            const qty = parseInt(tr.querySelector(".exch-qty-input").value) || 0;
            const opt = sel.options[sel.selectedIndex];
            const price = parseFloat(opt ? opt.dataset.price : 0) || 0;

            if (varId > 0 && qty > 0) {
                exchangeItems.push({
                    variant_id: varId,
                    qty: qty,
                    unit_price: price
                });
            }
        });

        if (exchangeItems.length === 0) {
            alert("Please select at least one replacement garment variant for this exchange.");
            return;
        }
    }

    const payload = {
        sale_id: currentSale.id,
        return_type: returnType,
        returned_items: returnedItems,
        exchange_items: exchangeItems,
        refund_method: document.getElementById("settleMethodSelect").value,
        reason: document.getElementById("returnReasonSelect").value
    };

    fetch('ajax/process_return.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert("Error: " + data.error);
            return;
        }

        alert(`Success! Transaction #${data.return_number} processed.`);
        window.location.reload();
    })
    .catch(err => alert("Error: " + err.message));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
