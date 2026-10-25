<?php
// barcodes.php - Barcode & Price Tag Label Generator for Thermal & A4 Sheets
$page_title = 'Barcode & Price Label Generator';
require_once __DIR__ . '/includes/header.php';

// Fetch all active products
$products = $pdo->query("SELECT id, product_code, name FROM products WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$selected_product_id = intval($_GET['product_id'] ?? ($products[0]['id'] ?? 0));

// Fetch variants for selected product
$stmtVar = $pdo->prepare("SELECT pv.*, p.name AS product_name, p.product_code,
                                 s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex
                          FROM product_variants pv
                          JOIN products p ON pv.product_id = p.id
                          JOIN sizes s ON pv.size_id = s.id
                          JOIN colors c ON pv.color_id = c.id
                          WHERE pv.product_id = ? AND pv.status = 'active'
                          ORDER BY s.sort_order ASC");
$stmtVar->execute([$selected_product_id]);
$variants = $stmtVar->fetchAll();
?>

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4 no-print">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="fw-bold mb-0">Barcode & Price Tag Labels</h5>
            <small class="text-muted">Print barcode stickers with Size, Color and Retail Price for garments</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm px-3 shadow-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print Selected Labels
            </button>
        </div>
    </div>

    <!-- Product Selection Form -->
    <div class="row g-2 mt-3 pt-3 border-top align-items-center">
        <div class="col-12 col-md-5">
            <label class="form-label small fw-bold text-muted mb-1">SELECT GARMENT TO PRINT</label>
            <form method="GET" action="barcodes.php">
                <select name="product_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $selected_product_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="col-12 col-md-7">
            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 mt-md-0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="showPriceCheck" checked onchange="renderAllBarcodes()">
                    <label class="form-check-label small" for="showPriceCheck">Include Price</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="showColorCheck" checked onchange="renderAllBarcodes()">
                    <label class="form-check-label small" for="showColorCheck">Include Size & Color</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="showStoreCheck" checked onchange="renderAllBarcodes()">
                    <label class="form-check-label small" for="showStoreCheck">Include Store Name</label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Preview Area -->
<div class="card border-0 shadow-sm rounded-3 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-eye text-primary me-2"></i> Sticker Sheet Preview</h6>
        <span class="text-muted small">Select copies for each size/color variant below</span>
    </div>

    <!-- Copies Selection Table (Hidden in Print) -->
    <div class="table-responsive mb-4 no-print">
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light small">
                <tr>
                    <th>Variant Description</th>
                    <th>Size & Color</th>
                    <th>Barcode</th>
                    <th>Price</th>
                    <th style="width: 130px;" class="text-center">Sticker Copies</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($variants as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['product_name']) ?></strong></td>
                        <td>
                            <span class="size-badge">Size <?= htmlspecialchars($v['size_name']) ?></span>
                            <span class="color-dot ms-1" style="background-color: <?= htmlspecialchars($v['color_hex']) ?>;"></span>
                            <small><?= htmlspecialchars($v['color_name']) ?></small>
                        </td>
                        <td><code><?= htmlspecialchars($v['barcode']) ?></code></td>
                        <td><?= format_currency($v['selling_price']) ?></td>
                        <td class="text-center">
                            <input type="number" class="form-control form-control-sm text-center label-copies-input fw-bold" 
                                   value="2" min="0" max="100" 
                                   data-variant='<?= json_encode($v) ?>' 
                                   onchange="renderAllBarcodes()">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Live Rendered Barcode Labels Grid (Printed Area) -->
    <div id="barcodeStickersContainer" class="d-flex flex-wrap gap-3 justify-content-start print-area">
        <!-- Rendered Sticker Elements -->
    </div>
</div>

<style>
/* Barcode Label Sticky Tag CSS */
.barcode-sticker {
    width: 50mm;
    height: 32mm;
    border: 1px dashed #cbd5e1;
    border-radius: 4px;
    padding: 4px;
    background: #fff;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    font-family: -apple-system, BlinkMacSystemFont, Arial, sans-serif;
    box-sizing: border-box;
    page-break-inside: avoid;
}

@media print {
    body {
        background: #fff !important;
        padding: 0 !important;
    }
    .barcode-sticker {
        border: none !important;
        margin: 0 !important;
    }
    #barcodeStickersContainer {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 3mm !important;
    }
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    renderAllBarcodes();
});

function renderAllBarcodes() {
    const container = document.getElementById("barcodeStickersContainer");
    container.innerHTML = "";

    const showPrice = document.getElementById("showPriceCheck").checked;
    const showColor = document.getElementById("showColorCheck").checked;
    const showStore = document.getElementById("showStoreCheck").checked;
    const storeName = "<?= addslashes($settings['shop_name'] ?? 'Clothes Retail') ?>";

    let labelIndex = 0;

    document.querySelectorAll(".label-copies-input").forEach(input => {
        const copies = parseInt(input.value) || 0;
        const v = JSON.parse(input.dataset.variant);

        for (let i = 0; i < copies; i++) {
            const sticker = document.createElement("div");
            sticker.className = "barcode-sticker";

            const svgId = `barcodeSvg_${labelIndex++}`;

            sticker.innerHTML = `
                <div>
                    ${showStore ? `<div style="font-size: 9px; font-weight: bold; text-transform: uppercase; color: #333; line-height: 1;">${storeName}</div>` : ''}
                    <div style="font-size: 10px; font-weight: bold; color: #000; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        ${v.product_name}
                    </div>
                    ${showColor ? `
                    <div style="font-size: 9px; font-weight: 600; color: #444;">
                        Size: <strong>${v.size_name}</strong> | Col: ${v.color_name}
                    </div>` : ''}
                </div>

                <div style="margin: 1px 0;">
                    <svg id="${svgId}"></svg>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 4px; font-size: 9px;">
                    <span style="font-family: monospace; font-size: 8px;">${v.sku}</span>
                    ${showPrice ? `<strong style="font-size: 11px; color: #000;">Rs. ${parseFloat(v.selling_price).toLocaleString()}</strong>` : ''}
                </div>
            `;

            container.appendChild(sticker);

            // Render barcode via JsBarcode
            setTimeout(() => {
                try {
                    JsBarcode(`#${svgId}`, v.barcode, {
                        format: "CODE128",
                        width: 1.3,
                        height: 28,
                        displayValue: true,
                        fontSize: 9,
                        margin: 0
                    });
                } catch (e) {
                    console.error("Barcode render error:", e);
                }
            }, 10);
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
