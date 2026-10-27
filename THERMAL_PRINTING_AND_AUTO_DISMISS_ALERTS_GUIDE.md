# Implementation Guide: Thermal Receipt Clarity (58mm/80mm) & Auto-Dismiss Alerts

This guide documents the technical details and step-by-step code needed to replicate the **Thermal Receipt Printing (Sharp Bold & 58mm/80mm support)** and **5-Second Alert Auto-Dismissal** features in any other PHP / Web application.

---

## 1. Feature: Auto-Dismiss Flash Alerts After 5 Seconds

### Problem
Bootstrap alerts stay permanently on the screen until the user manually clicks the `[X]` button, cluttering the top bar and overlapping action buttons.

### Solution
1. Add `.auto-dismiss-alert` class to all flash alerts.
2. Trigger an automatic 5000ms (5-second) timer using `bootstrap.Alert.getOrCreateInstance(el).close()` with a smooth CSS fade-out fallback.
3. Add a global listener in the footer so any dismissible alert (static or dynamically generated) automatically auto-hides.

---

### Implementation Steps

#### A. In Your Global Helpers / Alert Function (`config/helpers.php`)
Replace or update your flash alert renderer function:

```php
function render_alerts() {
    $output = '';
    $hasAlert = false;

    if (isset($_GET['success'])) {
        $output .= '<div class="alert alert-success alert-dismissible fade show auto-dismiss-alert" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> ' . sanitize($_GET['success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        $hasAlert = true;
    }
    if (isset($_GET['error'])) {
        $output .= '<div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> ' . sanitize($_GET['error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        $hasAlert = true;
    }
    if (isset($_SESSION['flash_success'])) {
        $output .= '<div class="alert alert-success alert-dismissible fade show auto-dismiss-alert" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> ' . sanitize($_SESSION['flash_success']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        unset($_SESSION['flash_success']);
        $hasAlert = true;
    }
    if (isset($_SESSION['flash_error'])) {
        $output .= '<div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> ' . sanitize($_SESSION['flash_error']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
        unset($_SESSION['flash_error']);
        $hasAlert = true;
    }

    // Inline auto-dismiss trigger
    if ($hasAlert) {
        $output .= '<script>
        setTimeout(function() {
            document.querySelectorAll(".auto-dismiss-alert").forEach(function(el) {
                if (typeof bootstrap !== "undefined" && bootstrap.Alert) {
                    try {
                        var inst = bootstrap.Alert.getOrCreateInstance(el);
                        if (inst) { inst.close(); return; }
                    } catch (e) {}
                }
                el.classList.remove("show");
                setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 350);
            });
        }, 5000);
        </script>';
    }

    return $output;
}
```

#### B. In Global Footer (`includes/footer.php`)
Add this inside `document.addEventListener("DOMContentLoaded", function() { ... })` so that **all** alerts anywhere in the application are caught:

```javascript
// Auto-dismiss all flash alerts and dismissible alerts after 5 seconds (5000ms)
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert.alert-dismissible, .auto-dismiss-alert');
    alerts.forEach(function(el) {
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                if (bsAlert) {
                    bsAlert.close();
                    return;
                }
            }
        } catch (e) {}
        el.classList.remove('show');
        setTimeout(function() {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 350);
    });
}, 5000);
```

#### C. In Standalone Pages Without Global Footer (`login.php`)
For authentication or standalone pages that do not load Bootstrap JS:

```html
<?php if (!empty($error)): ?>
    <div id="loginAlert" class="alert alert-danger alert-dismissible fade show py-2 small mb-4 auto-dismiss-alert" role="alert">
        <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    </div>
    <script>
    setTimeout(function() {
        var a = document.getElementById('loginAlert');
        if (a) {
            a.style.transition = 'opacity 0.4s ease';
            a.style.opacity = '0';
            setTimeout(function() { if (a && a.parentNode) a.parentNode.removeChild(a); }, 400);
        }
    }, 5000);
    </script>
<?php endif; ?>
```

---

## 2. Feature: High-Clarity Thermal Receipt Printing (58mm / 80mm)

### Problems Identified on Real Thermal Hardware
1. **Blurry / Grainy Text:**
   - Typewriter serif fonts (like `Courier New`) have fine curves and thin stems.
   - Low-DPI thermal printer heads (203 DPI) cannot print subpixel anti-aliasing. The Windows print driver turns gray anti-aliased pixels into dithered noise dots, making text look blurry and unreadable.
2. **Right-Side Text Truncation (`"Price Tota"`, `"1,45"`, `"Rs. 2,10"`):**
   - Receipts hardcoded to `width: 78mm` overflow on 58mm (2-inch) rolls.
   - Physical 58mm paper has only **48mm printable width**. Anything wider is permanently clipped by the printer roll.

---

### Thermal Optimization Rules

1. **Use Monoline Sans-Serif Fonts**:
   Use `Arial, "Helvetica Neue", Helvetica, "Segoe UI", Tahoma, sans-serif;`.
   Sans-serif fonts have uniform stroke geometry without serif feet, resulting in solid black thermal burns.
2. **Enforce Uniform Bold Weight (`font-weight: 700 !important;`)**:
   Thin or normal text looks faint on thermal heads. Bold weight ensures every letter has a 2-3 pixel burn thickness.
3. **Hierarchy via Font Size Only**:
   Differentiate sections by size (Heading big, details small), keeping the weight uniformly bold.
4. **Pure `#000` Black**:
   Thermal heads cannot print colors or grays (#444, #666, red). Colors cause dot dithering. Force pure `#000`.
5. **Disable Gray Fringe Anti-Aliasing**:
   ```css
   -webkit-font-smoothing: antialiased;
   -moz-osx-font-smoothing: grayscale;
   text-rendering: geometricPrecision;
   ```
6. **Support Both 58mm (2-inch) and 80mm (3-inch) Roll Widths**:
   - 58mm roll printable width: **`48mm`**
   - 80mm roll printable width: **`72mm`**

---

### Complete File Implementation (`print_receipt.php`)

```php
<?php
// print_receipt.php - High-Clarity Thermal 58mm / 80mm Receipt View
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

$sale_id = intval($_GET['id'] ?? 0);
$autoprint = isset($_GET['autoprint']);

$stmtSale = $pdo->prepare("SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, c.current_balance AS customer_khata_balance,
                                  u.full_name AS cashier_name
                           FROM sales s
                           LEFT JOIN customers c ON s.customer_id = c.id
                           LEFT JOIN users u ON s.cashier_id = u.id
                           WHERE s.id = ?");
$stmtSale->execute([$sale_id]);
$sale = $stmtSale->fetch();

if (!$sale) {
    die("Receipt not found.");
}

$stmtItems = $pdo->prepare("SELECT si.*, p.name AS product_name, s.name AS size_name, c.name AS color_name, pv.barcode
                            FROM sale_items si
                            JOIN products p ON si.product_id = p.id
                            JOIN product_variants pv ON si.variant_id = pv.id
                            JOIN sizes s ON pv.size_id = s.id
                            JOIN colors c ON pv.color_id = c.id
                            WHERE si.sale_id = ?");
$stmtItems->execute([$sale_id]);
$items = $stmtItems->fetchAll();

$settings = get_settings($pdo);
$defaultPaper = $_GET['paper'] ?? $settings['thermal_paper_width'] ?? '58mm';
$defaultPaper = in_array($defaultPaper, ['58mm', '80mm']) ? $defaultPaper : '58mm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($sale['invoice_number']) ?></title>
    <style>
        @page {
            size: auto;
            margin: 0mm;
        }
        * {
            box-sizing: border-box;
            color: #000 !important;
            font-weight: 700 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            /* Pure clean Sans-Serif font produces sharp, non-blurry print on thermal heads */
            font-family: Arial, "Helvetica Neue", Helvetica, "Segoe UI", Roboto, Tahoma, sans-serif;
            background: #e2e8f0;
            margin: 0;
            padding: 16px 8px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: geometricPrecision;
        }

        /* Screen Preview Card */
        .receipt-container {
            background: #ffffff;
            margin: 0 auto;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
            padding: 12px 10px;
            border-radius: 4px;
        }

        /* 58mm (2-inch roll) - Printable width ~48mm */
        .receipt-container.paper-58mm {
            width: 48mm;
            max-width: 48mm;
            padding: 8px 4px;
            font-size: 10.5px;
            line-height: 1.3;
        }
        .paper-58mm .shop-title {
            font-size: 15px;
            line-height: 1.2;
            letter-spacing: 0.2px;
        }
        .paper-58mm .sub-header-text {
            font-size: 9.5px;
            line-height: 1.25;
        }
        .paper-58mm .meta-text {
            font-size: 10px;
            line-height: 1.35;
        }
        .paper-58mm table {
            font-size: 10px;
        }
        .paper-58mm .item-name {
            font-size: 11.5px;
            line-height: 1.25;
            padding-top: 4px;
        }
        .paper-58mm .item-details {
            font-size: 9.5px;
            padding-left: 2px;
        }
        .paper-58mm .item-values {
            font-size: 10.5px;
        }
        .paper-58mm .summary-row {
            font-size: 10.5px;
            line-height: 1.35;
        }
        .paper-58mm .net-total-row {
            font-size: 14.5px;
            line-height: 1.25;
            padding: 4px 0;
            margin: 4px 0;
        }
        .paper-58mm .footer-text {
            font-size: 9.5px;
            line-height: 1.3;
        }
        .paper-58mm .branding-text {
            font-size: 8.5px;
        }

        /* 80mm (3-inch roll) - Printable width ~72mm */
        .receipt-container.paper-80mm {
            width: 72mm;
            max-width: 72mm;
            padding: 12px 8px;
            font-size: 12px;
            line-height: 1.35;
        }
        .paper-80mm .shop-title {
            font-size: 18.5px;
            line-height: 1.2;
            letter-spacing: 0.3px;
        }
        .paper-80mm .sub-header-text {
            font-size: 11px;
            line-height: 1.3;
        }
        .paper-80mm .meta-text {
            font-size: 11.5px;
            line-height: 1.4;
        }
        .paper-80mm table {
            font-size: 11.5px;
        }
        .paper-80mm .item-name {
            font-size: 13px;
            line-height: 1.3;
            padding-top: 5px;
        }
        .paper-80mm .item-details {
            font-size: 10.5px;
            padding-left: 4px;
        }
        .paper-80mm .item-values {
            font-size: 11.5px;
        }
        .paper-80mm .summary-row {
            font-size: 12px;
            line-height: 1.4;
        }
        .paper-80mm .net-total-row {
            font-size: 16.5px;
            line-height: 1.3;
            padding: 5px 0;
            margin: 5px 0;
        }
        .paper-80mm .footer-text {
            font-size: 11px;
            line-height: 1.35;
        }
        .paper-80mm .branding-text {
            font-size: 9.5px;
        }

        /* Shared Element Styles */
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .border-top { border-top: 1.5px dashed #000; }
        .border-bottom { border-bottom: 1.5px dashed #000; }
        .my-1 { margin-top: 3px; margin-bottom: 3px; }
        .my-2 { margin-top: 6px; margin-bottom: 6px; }
        .py-1 { padding-top: 3px; padding-bottom: 3px; }

        .shop-title {
            text-transform: uppercase;
            margin: 0 0 2px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            padding: 2.5px 1px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .col-item { width: 44%; text-align: left; }
        .col-qty { width: 14%; text-align: center; }
        .col-price { width: 20%; text-align: right; }
        .col-total { width: 22%; text-align: right; }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .net-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1.5px dashed #000;
            border-bottom: 1.5px dashed #000;
        }

        /* Toolbar */
        .no-print {
            text-align: center;
            margin-bottom: 14px;
        }
        .toolbar-box {
            display: inline-block;
            background: #ffffff;
            padding: 10px 14px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .roll-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 8px;
        }
        .btn-roll {
            background: #f1f5f9;
            border: 1.5px solid #cbd5e1;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            color: #334155 !important;
        }
        .btn-roll.active {
            background: #0284c7;
            border-color: #0284c7;
            color: #ffffff !important;
        }
        .action-btns {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn-action {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            border: none;
        }
        .btn-print {
            background: #16a34a;
            color: #ffffff !important;
        }
        .btn-close-win {
            background: #64748b;
            color: #ffffff !important;
        }

        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .receipt-container {
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                padding: 0 1mm !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <div class="toolbar-box">
        <div class="roll-selector">
            <span style="font-size: 11px; margin-right: 4px;">Paper Roll:</span>
            <button type="button" class="btn-roll" id="roll58" onclick="selectPaper('58mm')">58mm (2" Mini)</button>
            <button type="button" class="btn-roll" id="roll80" onclick="selectPaper('80mm')">80mm (3" Standard)</button>
        </div>
        <div class="action-btns">
            <button type="button" class="btn-action btn-print" onclick="window.print()">🖨️ Print Receipt</button>
            <button type="button" class="btn-action btn-close-win" onclick="window.close()">✖ Close Window</button>
        </div>
    </div>
</div>

<div class="receipt-container paper-<?= htmlspecialchars($defaultPaper) ?>" id="receiptContainer">
    <!-- Store Header -->
    <div class="text-center">
        <h2 class="shop-title"><?= htmlspecialchars($settings['shop_name'] ?? 'Clothes Retail') ?></h2>
        <?php if (!empty($settings['tagline'])): ?>
            <div class="sub-header-text"><?= htmlspecialchars($settings['tagline']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['address'])): ?>
            <div class="sub-header-text"><?= htmlspecialchars($settings['address']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['phone'])): ?>
            <div class="sub-header-text">Tel: <?= htmlspecialchars($settings['phone']) ?></div>
        <?php endif; ?>
    </div>

    <!-- Invoice Meta Data -->
    <div class="border-top border-bottom my-2 py-1 meta-text">
        <div style="display: flex; justify-content: space-between;">
            <span>Inv: <?= htmlspecialchars($sale['invoice_number']) ?></span>
            <span><?= date('d-M-y h:i A', strtotime($sale['sale_date'])) ?></span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'POS') ?></span>
            <span>Cust: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></span>
        </div>
    </div>

    <!-- Items Table: 2-Row Design avoids truncation -->
    <table>
        <thead>
            <tr class="border-bottom">
                <th class="col-item">Item</th>
                <th class="col-qty">Qty</th>
                <th class="col-price">Price</th>
                <th class="col-total">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td colspan="4" class="item-name">
                        <?= htmlspecialchars($it['product_name']) ?>
                    </td>
                </tr>
                <tr class="border-bottom item-values">
                    <td class="col-item item-details">
                        [<?= htmlspecialchars($it['size_name']) ?>/<?= htmlspecialchars($it['color_name']) ?>]
                    </td>
                    <td class="col-qty"><?= rtrim(rtrim(number_format($it['quantity'], 2), '0'), '.') ?><?= !empty($it['unit_type']) && $it['unit_type'] !== 'piece' ? ' ' . $it['unit_type'] : '' ?></td>
                    <td class="col-price"><?= number_format($it['unit_price'], 0) ?></td>
                    <td class="col-total"><?= number_format($it['total_price'], 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Billing Summary -->
    <div class="my-2">
        <div class="summary-row">
            <span>Subtotal:</span>
            <span><?= format_currency($sale['subtotal']) ?></span>
        </div>

        <?php if ($sale['bill_discount'] > 0): ?>
        <div class="summary-row">
            <span>Discount:</span>
            <span>-<?= format_currency($sale['bill_discount']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($sale['tax_amount'] > 0): ?>
        <div class="summary-row">
            <span>Tax:</span>
            <span>+<?= format_currency($sale['tax_amount']) ?></span>
        </div>
        <?php endif; ?>

        <div class="net-total-row">
            <span>NET TOTAL:</span>
            <span><?= format_currency($sale['net_total']) ?></span>
        </div>

        <div class="summary-row">
            <span>Paid (<?= ucfirst($sale['payment_method']) ?>):</span>
            <span><?= format_currency($sale['paid_amount']) ?></span>
        </div>

        <?php if ($sale['change_returned'] > 0): ?>
        <div class="summary-row">
            <span>Change:</span>
            <span><?= format_currency($sale['change_returned']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($sale['balance_due'] > 0): ?>
        <div class="summary-row">
            <span>Khata Due:</span>
            <span><?= format_currency($sale['balance_due']) ?></span>
        </div>
        <?php if ($sale['customer_id'] > 1): ?>
        <div class="summary-row">
            <span>Total Khata Bal:</span>
            <span><?= format_currency($sale['customer_khata_balance']) ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Receipt Footer & Barcode -->
    <div class="text-center border-top pt-2 my-2 footer-text">
        <?= nl2br(htmlspecialchars($settings['footer_text'] ?? 'Thank you for shopping with us!')) ?>
        <div style="margin-top: 6px;">
            <svg id="receiptBarcode"></svg>
        </div>
        <div class="branding-text" style="margin-top: 3px;">Software by ClothesRetail ERP</div>
    </div>
</div>

<!-- JsBarcode script for SVG barcode generation -->
<script src="assets/js/JsBarcode.all.min.js"></script>
<script>
let currentPaper = "<?= $defaultPaper ?>";

function renderBarcode(size) {
    const is58 = size === '58mm';
    try {
        JsBarcode("#receiptBarcode", "<?= $sale['invoice_number'] ?>", {
            format: "CODE128",
            width: is58 ? 1.15 : 1.5,
            height: is58 ? 26 : 34,
            displayValue: false,
            margin: 0
        });
    } catch(e) {}
}

function selectPaper(size, skipStore) {
    currentPaper = size;
    const rc = document.getElementById('receiptContainer');
    if (rc) {
        rc.classList.remove('paper-58mm', 'paper-80mm');
        rc.classList.add('paper-' + size);
    }

    const r58 = document.getElementById('roll58');
    const r80 = document.getElementById('roll80');
    if (r58 && r80) {
        r58.classList.toggle('active', size === '58mm');
        r80.classList.toggle('active', size === '80mm');
    }

    if (!skipStore) {
        try {
            localStorage.setItem('thermal_paper_roll', size);
        } catch(e) {}
    }

    renderBarcode(size);
}

// On load: restore paper choice from localStorage if present
document.addEventListener("DOMContentLoaded", function() {
    let saved = null;
    try {
        saved = localStorage.getItem('thermal_paper_roll');
    } catch(e) {}

    const activeSize = (saved === '58mm' || saved === '80mm') ? saved : currentPaper;
    selectPaper(activeSize, true);

    <?php if ($autoprint): ?>
    setTimeout(function() {
        window.print();
    }, 400);
    <?php endif; ?>
});
</script>

</body>
</html>
```

#### Settings Integration (`settings.php`)
To let the admin select the default paper size store-wide:

1. Add `'thermal_paper_width'` to your settings save list:
```php
$fields = [
    'shop_name', 'tagline', 'phone', 'email', 'address', 'currency',
    'invoice_prefix', 'tax_percent', 'footer_text', 'low_stock_threshold', 'return_validity_days',
    'thermal_paper_width'
];
```

2. Add the dropdown in your settings form:
```html
<div class="col-12 col-md-6">
    <label class="form-label small fw-bold">THERMAL RECEIPT PAPER ROLL</label>
    <select name="thermal_paper_width" class="form-select">
        <option value="58mm" <?= ($settings['thermal_paper_width'] ?? '58mm') === '58mm' ? 'selected' : '' ?>>58mm (2-Inch / Standard Mini POS) [Recommended]</option>
        <option value="80mm" <?= ($settings['thermal_paper_width'] ?? '') === '80mm' ? 'selected' : '' ?>>80mm (3-Inch / Wide Thermal POS)</option>
    </select>
    <div class="text-muted small mt-1">Controls receipt width to avoid right-side text clipping.</div>
</div>
```
