<?php
// print_invoice.php - Normalized Clean Professional A4 Sales Tax Invoice View
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

$sale_id = intval($_GET['id'] ?? 0);
$autoprint = isset($_GET['autoprint']);

$stmtSale = $pdo->prepare("SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address,
                                  c.current_balance AS customer_khata_balance, u.full_name AS cashier_name
                           FROM sales s
                           LEFT JOIN customers c ON s.customer_id = c.id
                           LEFT JOIN users u ON s.cashier_id = u.id
                           WHERE s.id = ?");
$stmtSale->execute([$sale_id]);
$sale = $stmtSale->fetch();

if (!$sale) {
    die("Invoice not found.");
}

$stmtItems = $pdo->prepare("SELECT si.*, p.name AS product_name, p.product_code, p.item_type, p.meters_per_suit,
                                   s.name AS size_name, c.name AS color_name, pv.sku, pv.barcode
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
    <title>Invoice #<?= htmlspecialchars($sale['invoice_number']) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 11.5px;
            line-height: 1.4;
        }
        .invoice-box {
            max-width: 800px;
            margin: 15px auto;
            padding: 24px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }
        .inv-shop-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e3a8a;
            letter-spacing: -0.3px;
        }
        .inv-heading {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        .inv-table {
            font-size: 11px;
            border-color: #cbd5e1;
        }
        .inv-table th {
            background-color: #f8fafc !important;
            color: #334155;
            font-weight: 600;
            font-size: 10.5px;
            text-transform: uppercase;
            padding: 5px 8px !important;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        .inv-table td {
            padding: 5px 8px !important;
            border-color: #e2e8f0;
            vertical-align: middle;
        }
        .summary-table td {
            padding: 3px 6px !important;
            font-size: 11.5px;
        }
        @media print {
            body { background: #fff !important; font-size: 11px !important; }
            .invoice-box { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
            .no-print { display: none !important; }
            .badge { border: 1px solid #94a3b8 !important; color: #000 !important; background: transparent !important; }
        }
    </style>
</head>
<body <?= $autoprint ? 'onload="window.print()"' : '' ?>>

<div class="container text-center py-2 no-print">
    <button class="btn btn-primary btn-sm px-3 fw-bold" onclick="window.print()">🖨️ Print A4 Invoice</button>
    <button class="btn btn-outline-secondary btn-sm ms-2" onclick="window.close()">Close</button>
</div>

<div class="invoice-box">
    <!-- Invoice Header -->
    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
        <div>
            <div class="inv-shop-title mb-1"><?= htmlspecialchars($settings['shop_name'] ?? 'Clothes Retail') ?></div>
            <?php if (!empty($settings['tagline'])): ?>
                <div class="text-muted small" style="font-size: 11px;"><?= htmlspecialchars($settings['tagline']) ?></div>
            <?php endif; ?>
            <div class="text-muted small" style="font-size: 10.5px;"><?= htmlspecialchars($settings['address'] ?? '') ?></div>
            <div class="text-muted small" style="font-size: 10.5px;">Phone: <?= htmlspecialchars($settings['phone'] ?? '') ?> | Email: <?= htmlspecialchars($settings['email'] ?? '') ?></div>
        </div>
        <div class="text-end">
            <div class="inv-heading mb-0">SALES TAX INVOICE</div>
            <div class="fw-bold text-primary" style="font-size: 13px;">#<?= htmlspecialchars($sale['invoice_number']) ?></div>
            <div class="small text-muted" style="font-size: 10.5px;">Date: <?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></div>
            <div class="small text-muted" style="font-size: 10.5px;">Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'Counter') ?></div>
            <?php if (!empty($sale['pricing_tier'])): ?>
                <span class="badge <?= $sale['pricing_tier'] === 'wholesale' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' ?> mt-1" style="font-size: 10px;">
                    <?= strtoupper($sale['pricing_tier']) ?> RATE
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Customer & Payment Details -->
    <div class="row mb-3" style="font-size: 11.5px;">
        <div class="col-7">
            <span class="text-muted small fw-bold text-uppercase d-block" style="font-size: 9.5px;">Billed To:</span>
            <div class="fw-bold text-dark fs-6 mb-0"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
            <?php if (!empty($sale['customer_phone'])): ?>
                <div class="text-muted small">Phone: <?= htmlspecialchars($sale['customer_phone']) ?></div>
            <?php endif; ?>
            <?php if (!empty($sale['customer_address'])): ?>
                <div class="text-muted small">Address: <?= htmlspecialchars($sale['customer_address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-5 text-end">
            <span class="text-muted small fw-bold text-uppercase d-block" style="font-size: 9.5px;">Payment Details:</span>
            <span class="badge <?= $sale['payment_status'] == 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>" style="font-size: 11px;">
                STATUS: <?= strtoupper($sale['payment_status']) ?>
            </span>
            <div class="small text-muted mt-1">Payment Method: <strong><?= ucfirst($sale['payment_method']) ?></strong></div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="table-responsive mb-3">
        <table class="table table-bordered inv-table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 30px;" class="text-center">#</th>
                    <th>Product / Fabric Description</th>
                    <th style="width: 70px;" class="text-center">Size</th>
                    <th style="width: 75px;" class="text-center">Color</th>
                    <th style="width: 90px;" class="text-center">Rate Tier</th>
                    <th style="width: 75px;" class="text-center">Quantity</th>
                    <th style="width: 85px;" class="text-end">Unit Rate</th>
                    <th style="width: 95px;" class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                    <?php 
                    $isFabric = (($it['item_type'] ?? '') === 'fabric');
                    $unitStr = htmlspecialchars($it['unit_type'] ?? ($isFabric ? 'm' : 'pcs'));
                    ?>
                    <tr>
                        <td class="text-center text-muted"><?= $idx + 1 ?></td>
                        <td>
                            <strong class="text-dark"><?= htmlspecialchars($it['product_name']) ?></strong>
                            <div class="text-muted small" style="font-size: 9.5px;">Code: <?= htmlspecialchars($it['product_code']) ?> &bull; Barcode: <?= htmlspecialchars($it['barcode'] ?: $it['sku']) ?></div>
                        </td>
                        <td class="text-center"><span class="badge bg-light text-dark border py-1" style="font-size: 10px;"><?= htmlspecialchars($it['size_name']) ?></span></td>
                        <td class="text-center"><?= htmlspecialchars($it['color_name']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border" style="font-size: 9.5px; text-transform: uppercase;">
                                <?= htmlspecialchars($it['pricing_tier'] ?? 'Retail') ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold">
                            <?= rtrim(rtrim(number_format($it['quantity'], 2), '0'), '.') ?>
                            <small class="text-muted fw-normal"><?= $unitStr ?></small>
                        </td>
                        <td class="text-end"><?= number_format($it['unit_price'], 2) ?></td>
                        <td class="text-end fw-bold"><?= number_format($it['total_price'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Calculations Summary -->
    <div class="row">
        <div class="col-7">
            <div class="p-2 bg-light rounded border" style="font-size: 10.5px;">
                <div class="fw-bold mb-1 text-uppercase text-secondary" style="font-size: 10px;">Terms & Exchange Policy</div>
                <div class="text-muted">
                    <?= nl2br(htmlspecialchars($settings['footer_text'] ?? 'Goods once sold can be exchanged within 7 days with original sales invoice.')) ?>
                </div>
                <div class="mt-2 text-center">
                    <svg id="a4Barcode"></svg>
                </div>
            </div>
        </div>
        <div class="col-5">
            <table class="table table-borderless summary-table mb-0">
                <tr>
                    <td class="text-muted">Subtotal:</td>
                    <td class="text-end fw-bold"><?= format_currency($sale['subtotal']) ?></td>
                </tr>
                <?php if ($sale['bill_discount'] > 0): ?>
                <tr>
                    <td class="text-muted">Discount:</td>
                    <td class="text-end text-danger">-<?= format_currency($sale['bill_discount']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($sale['tax_amount'] > 0): ?>
                <tr>
                    <td class="text-muted">Sales Tax:</td>
                    <td class="text-end text-success">+<?= format_currency($sale['tax_amount']) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="border-top border-bottom">
                    <td class="fw-bold" style="font-size: 13px;">Net Payable:</td>
                    <td class="text-end fw-bold text-primary" style="font-size: 13px;"><?= format_currency($sale['net_total']) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Amount Paid:</td>
                    <td class="text-end fw-semibold"><?= format_currency($sale['paid_amount']) ?></td>
                </tr>
                <?php if ($sale['change_returned'] > 0): ?>
                <tr>
                    <td class="text-muted">Change Returned:</td>
                    <td class="text-end text-success"><?= format_currency($sale['change_returned']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($sale['balance_due'] > 0): ?>
                <tr class="table-danger">
                    <td class="fw-bold text-danger">Khata Balance Due:</td>
                    <td class="text-end fw-bold text-danger"><?= format_currency($sale['balance_due']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Signatures -->
    <div class="d-flex justify-content-between align-items-end mt-4 pt-3 border-top">
        <div class="text-center" style="width: 170px;">
            <div class="border-top pt-1 text-muted" style="font-size: 10px;">Customer Signature</div>
        </div>
        <div class="text-center" style="width: 170px;">
            <div class="border-top pt-1 text-muted" style="font-size: 10px;">Authorized Signature & Stamp</div>
        </div>
    </div>
</div>

<script src="assets/js/JsBarcode.all.min.js"></script>
<script>
JsBarcode("#a4Barcode", "<?= $sale['invoice_number'] ?>", {
    format: "CODE128",
    width: 1.4,
    height: 32,
    displayValue: true,
    fontSize: 10
});
</script>

</body>
</html>
