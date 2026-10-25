<?php
// print_invoice.php - Professional A4 Sales Tax Invoice View
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

$stmtItems = $pdo->prepare("SELECT si.*, p.name AS product_name, p.product_code,
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
    <title>A4 Invoice #<?= htmlspecialchars($sale['invoice_number']) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
        .invoice-box {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        @media print {
            body { background: #fff; }
            .invoice-box { border: none; box-shadow: none; padding: 0; margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body <?= $autoprint ? 'onload="window.print()"' : '' ?>>

<div class="container text-center py-3 no-print">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print A4 Invoice</button>
    <button class="btn btn-secondary ms-2" onclick="window.close()">Close</button>
</div>

<div class="invoice-box">
    <!-- Invoice Header -->
    <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
        <div>
            <h3 class="fw-bold text-primary mb-1"><?= htmlspecialchars($settings['shop_name'] ?? 'Clothes Retail') ?></h3>
            <p class="text-muted mb-0 small"><?= htmlspecialchars($settings['tagline'] ?? '') ?></p>
            <p class="text-muted mb-0 small"><?= htmlspecialchars($settings['address'] ?? '') ?></p>
            <p class="text-muted mb-0 small">Phone: <?= htmlspecialchars($settings['phone'] ?? '') ?> | Email: <?= htmlspecialchars($settings['email'] ?? '') ?></p>
        </div>
        <div class="text-end">
            <h4 class="fw-bolder text-dark mb-1">SALES INVOICE</h4>
            <div class="fw-bold text-primary">#<?= htmlspecialchars($sale['invoice_number']) ?></div>
            <div class="small text-muted">Date: <?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></div>
            <div class="small text-muted">Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'Counter') ?></div>
        </div>
    </div>

    <!-- Customer Details -->
    <div class="row mb-4">
        <div class="col-6">
            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Billed To:</span>
            <h6 class="fw-bold mb-1"><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?></h6>
            <?php if (!empty($sale['customer_phone'])): ?>
                <div class="small text-muted">Phone: <?= htmlspecialchars($sale['customer_phone']) ?></div>
            <?php endif; ?>
            <?php if (!empty($sale['customer_address'])): ?>
                <div class="small text-muted">Address: <?= htmlspecialchars($sale['customer_address']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Payment Status:</span>
            <span class="badge <?= $sale['payment_status'] == 'paid' ? 'bg-success' : 'bg-warning text-dark' ?> fs-6">
                <?= strtoupper($sale['payment_status']) ?>
            </span>
            <div class="small text-muted mt-1">Method: <?= ucfirst($sale['payment_method']) ?></div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;" class="text-center">#</th>
                    <th>Product Description</th>
                    <th style="width: 70px;" class="text-center">Size</th>
                    <th style="width: 90px;" class="text-center">Color</th>
                    <th style="width: 100px;">Barcode/SKU</th>
                    <th style="width: 60px;" class="text-center">Qty</th>
                    <th style="width: 100px;" class="text-end">Unit Price</th>
                    <th style="width: 110px;" class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $it): ?>
                    <tr>
                        <td class="text-center"><?= $idx + 1 ?></td>
                        <td>
                            <strong class="text-dark"><?= htmlspecialchars($it['product_name']) ?></strong>
                            <div class="text-muted small">Code: <?= htmlspecialchars($it['product_code']) ?></div>
                        </td>
                        <td class="text-center"><span class="badge bg-light text-dark border"><?= htmlspecialchars($it['size_name']) ?></span></td>
                        <td class="text-center"><?= htmlspecialchars($it['color_name']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($it['barcode']) ?></td>
                        <td class="text-center fw-bold"><?= $it['quantity'] ?></td>
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
            <div class="p-3 bg-light rounded border">
                <h6 class="fw-bold mb-1 small text-uppercase">Terms & Exchange Policy</h6>
                <p class="small text-muted mb-0">
                    <?= nl2br(htmlspecialchars($settings['footer_text'] ?? 'Goods once sold can be exchanged within 7 days with original invoice.')) ?>
                </p>
                <div class="mt-3">
                    <svg id="a4Barcode"></svg>
                </div>
            </div>
        </div>
        <div class="col-5">
            <table class="table table-sm table-borderless">
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
                    <td class="text-muted">Tax:</td>
                    <td class="text-end text-success">+<?= format_currency($sale['tax_amount']) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="border-top border-bottom">
                    <td class="fw-bold fs-6">Net Payable:</td>
                    <td class="text-end fw-bold fs-6 text-primary"><?= format_currency($sale['net_total']) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Amount Paid:</td>
                    <td class="text-end"><?= format_currency($sale['paid_amount']) ?></td>
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

    <div class="d-flex justify-content-between align-items-end mt-5 pt-4 border-top">
        <div class="text-center" style="width: 200px;">
            <div class="border-top pt-1 small text-muted">Customer Signature</div>
        </div>
        <div class="text-center" style="width: 200px;">
            <div class="border-top pt-1 small text-muted">Authorized Signature & Stamp</div>
        </div>
    </div>
</div>

<script src="assets/js/JsBarcode.all.min.js"></script>
<script>
JsBarcode("#a4Barcode", "<?= $sale['invoice_number'] ?>", {
    format: "CODE128",
    width: 1.6,
    height: 40,
    displayValue: true
});
</script>

</body>
</html>
