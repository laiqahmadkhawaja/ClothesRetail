<?php
// print_receipt.php - Thermal 80mm / 58mm Receipt View
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= htmlspecialchars($sale['invoice_number']) ?></title>
    <style>
        @page {
            size: auto;
            margin: 0;
        }
        body {
            font-family: 'Courier New', Courier, 'Segoe UI', Tahoma, Arial, monospace;
            font-size: 12.5px;
            font-weight: bold;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 10px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .receipt-container {
            width: 78mm;
            margin: 0 auto;
            font-weight: bold;
            color: #000;
        }
        /* All thermal text rendered bold as requested to prevent faint printing */
        .receipt-container,
        .receipt-container * {
            font-weight: bold !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .border-top { border-top: 1.5px dashed #000; }
        .border-bottom { border-bottom: 1.5px dashed #000; }
        .my-1 { margin-top: 4px; margin-bottom: 4px; }
        .my-2 { margin-top: 7px; margin-bottom: 7px; }
        .py-1 { padding-top: 4px; padding-bottom: 4px; }
        .shop-title {
            font-size: 19px;
            line-height: 1.2;
            text-transform: uppercase;
            margin: 0 0 3px 0;
        }
        .sub-header-text {
            font-size: 11.5px;
            line-height: 1.35;
        }
        .meta-text {
            font-size: 12px;
            line-height: 1.4;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 3px 0;
        }
        .item-name {
            font-size: 13.5px;
            line-height: 1.3;
            padding-top: 5px;
        }
        .item-details {
            font-size: 11px;
            padding-left: 6px;
        }
        .item-values {
            font-size: 12.5px;
        }
        .summary-row {
            font-size: 12.5px;
            line-height: 1.4;
            display: flex;
            justify-content: space-between;
        }
        .net-total-row {
            font-size: 17px;
            line-height: 1.3;
            display: flex;
            justify-content: space-between;
            border-top: 1.5px dashed #000;
            border-bottom: 1.5px dashed #000;
            padding: 5px 0;
            margin: 5px 0;
        }
        .footer-text {
            font-size: 11.5px;
            line-height: 1.35;
        }
        .branding-text {
            font-size: 10px;
            margin-top: 4px;
        }
        .no-print {
            text-align: center;
            margin-bottom: 15px;
        }
        .btn {
            padding: 6px 14px;
            border: 1px solid #333;
            background: #f0f0f0;
            cursor: pointer;
            font-size: 13px;
            border-radius: 4px;
        }
        @media print {
            @page {
                size: auto;
                margin: 0;
            }
            body {
                padding: 0;
                margin: 0;
                background: #fff;
            }
            .receipt-container {
                width: 100%;
                max-width: 78mm;
                margin: 0 auto;
                padding: 2mm 3mm;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body <?= $autoprint ? 'onload="window.print()"' : '' ?>>

<div class="no-print">
    <button class="btn" onclick="window.print()">🖨️ Print Receipt</button>
    <button class="btn" onclick="window.close()">✖ Close Window</button>
</div>

<div class="receipt-container">
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

    <div class="border-top border-bottom my-2 py-1 meta-text">
        <div style="display: flex; justify-content: space-between;">
            <span>Inv: <?= htmlspecialchars($sale['invoice_number']) ?></span>
            <span><?= date('d-M-y h:i A', strtotime($sale['sale_date'])) ?></span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span>Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'POS') ?></span>
            <span>Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr class="border-bottom" style="font-size: 12px;">
                <th style="text-align: left;">Item [Size/Col]</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Price</th>
                <th style="text-align: right;">Total</th>
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
                    <td class="item-details">
                        [<?= htmlspecialchars($it['size_name']) ?> / <?= htmlspecialchars($it['color_name']) ?>]
                    </td>
                    <td style="text-align: center;"><?= rtrim(rtrim(number_format($it['quantity'], 2), '0'), '.') ?><?= !empty($it['unit_type']) && $it['unit_type'] !== 'piece' ? ' ' . $it['unit_type'] : '' ?></td>
                    <td style="text-align: right;"><?= number_format($it['unit_price'], 0) ?></td>
                    <td style="text-align: right;"><?= number_format($it['total_price'], 0) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="my-2">
        <div class="summary-row">
            <span>Subtotal:</span>
            <span><?= format_currency($sale['subtotal']) ?></span>
        </div>

        <?php if ($sale['bill_discount'] > 0): ?>
        <div class="summary-row">
            <span>Bill Discount:</span>
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
            <span>Paid Amount (<?= ucfirst($sale['payment_method']) ?>):</span>
            <span><?= format_currency($sale['paid_amount']) ?></span>
        </div>

        <?php if ($sale['change_returned'] > 0): ?>
        <div class="summary-row">
            <span>Change Returned:</span>
            <span><?= format_currency($sale['change_returned']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($sale['balance_due'] > 0): ?>
        <div class="summary-row">
            <span>Khata Balance Due:</span>
            <span><?= format_currency($sale['balance_due']) ?></span>
        </div>
        <?php if ($sale['customer_id'] > 1): ?>
        <div class="summary-row" style="font-size: 11.5px;">
            <span>Total Khata Balance:</span>
            <span><?= format_currency($sale['customer_khata_balance']) ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="text-center border-top pt-2 my-2 footer-text">
        <?= nl2br(htmlspecialchars($settings['footer_text'] ?? 'Thank you for shopping with us!')) ?>
        <div style="margin-top: 8px;">
            <svg id="receiptBarcode"></svg>
        </div>
        <div class="branding-text">Software by ClothesRetail ERP</div>
    </div>
</div>

<script src="assets/js/JsBarcode.all.min.js"></script>
<script>
JsBarcode("#receiptBarcode", "<?= $sale['invoice_number'] ?>", {
    format: "CODE128",
    width: 1.5,
    height: 35,
    displayValue: false
});
</script>

</body>
</html>
