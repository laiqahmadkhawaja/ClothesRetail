<?php
// ajax/get_product_variants.php - Fetch variants for a product modal
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$product_id = intval($_GET['product_id'] ?? 0);
if ($product_id <= 0) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$stmtP = $pdo->prepare("SELECT p.*, c.name AS category_name, b.name AS brand_name 
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        LEFT JOIN brands b ON p.brand_id = b.id
                        WHERE p.id = ? LIMIT 1");
$stmtP->execute([$product_id]);
$product = $stmtP->fetch();

if (!$product) {
    echo json_encode(['error' => 'Product not found']);
    exit;
}

$stmtV = $pdo->prepare("SELECT pv.*, s.name AS size_name, s.size_group, c.name AS color_name, c.hex_code AS color_hex
                        FROM product_variants pv
                        JOIN sizes s ON pv.size_id = s.id
                        JOIN colors c ON pv.color_id = c.id
                        WHERE pv.product_id = ? AND pv.status = 'active'
                        ORDER BY s.sort_order ASC, c.name ASC");
$stmtV->execute([$product_id]);
$variants = $stmtV->fetchAll();

echo json_encode([
    'product'  => $product,
    'variants' => $variants
]);
