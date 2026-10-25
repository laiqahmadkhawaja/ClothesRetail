<?php
// ajax/get_invoice.php - Fetch Invoice Details for Returns & Exchanges
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$invoice_number = trim($_GET['invoice_number'] ?? '');
if (empty($invoice_number)) {
    echo json_encode(['success' => false, 'error' => 'Please enter an invoice number']);
    exit;
}

$stmt = $pdo->prepare("SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, c.current_balance AS customer_balance
                       FROM sales s
                       LEFT JOIN customers c ON s.customer_id = c.id
                       WHERE s.invoice_number = ? AND s.sale_status = 'completed'
                       LIMIT 1");
$stmt->execute([$invoice_number]);
$sale = $stmt->fetch();

if (!$sale) {
    echo json_encode(['success' => false, 'error' => 'Invoice not found or not completed.']);
    exit;
}

// Fetch sale items with variant details and remaining returnable quantity
$stmtItems = $pdo->prepare("SELECT si.*, 
                                   p.name AS product_name, p.product_code,
                                   s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex,
                                   pv.sku, pv.barcode, pv.stock_quantity AS current_stock,
                                   (si.quantity - si.returned_quantity) AS returnable_qty
                            FROM sale_items si
                            JOIN products p ON si.product_id = p.id
                            JOIN product_variants pv ON si.variant_id = pv.id
                            JOIN sizes s ON pv.size_id = s.id
                            JOIN colors c ON pv.color_id = c.id
                            WHERE si.sale_id = ?");
$stmtItems->execute([$sale['id']]);
$items = $stmtItems->fetchAll();

echo json_encode([
    'success' => true,
    'sale'    => $sale,
    'items'   => $items
]);
