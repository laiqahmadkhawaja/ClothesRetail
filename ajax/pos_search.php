<?php
// ajax/pos_search.php - Fast Product & Barcode Search for POS (Garments & Fabric Support)
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$category_id = intval($_GET['category_id'] ?? 0);

if (empty($query) && $category_id <= 0) {
    // Return top 24 active products by default for POS grid
    $sql = "SELECT p.id, p.product_code, p.name, p.rack_location, p.season,
                   p.item_type, p.unit_type, p.meters_per_suit, p.thaan_length,
                   p.selling_price AS default_selling_price, p.wholesale_price AS default_wholesale_price,
                   c.name AS category_name, b.name AS brand_name,
                   COUNT(pv.id) AS total_variants,
                   COALESCE(SUM(pv.stock_quantity), 0) AS total_stock,
                   MIN(pv.selling_price) AS min_price,
                   MAX(pv.selling_price) AS max_price,
                   MIN(pv.wholesale_price) AS min_wholesale_price,
                   MAX(pv.wholesale_price) AS max_wholesale_price
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN product_variants pv ON p.id = pv.product_id AND pv.status = 'active'
            WHERE p.status = 'active'
            GROUP BY p.id
            ORDER BY p.id DESC
            LIMIT 24";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();

    echo json_encode(['exact_match' => false, 'products' => $products]);
    exit;
}

// 1. Check for Exact Barcode / SKU match first (Immediate POS Scanner Trigger)
if (!empty($query)) {
    // A. Check variant barcode & SKU
    $stmt = $pdo->prepare("SELECT pv.*, p.name AS product_name, p.product_code,
                                  p.item_type, p.unit_type, p.meters_per_suit, p.thaan_length,
                                  p.selling_price AS default_selling_price, p.wholesale_price AS default_wholesale_price,
                                  s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex
                           FROM product_variants pv
                           JOIN products p ON pv.product_id = p.id
                           JOIN sizes s ON pv.size_id = s.id
                           JOIN colors c ON pv.color_id = c.id
                           WHERE (pv.barcode = ? OR pv.sku = ?) AND pv.status = 'active' AND p.status = 'active'
                           LIMIT 1");
    $stmt->execute([$query, $query]);
    $variant = $stmt->fetch();

    if ($variant) {
        echo json_encode([
            'exact_match' => true,
            'variant'     => $variant
        ]);
        exit;
    }

    // B. Check master product barcode or product_code
    $stmtP = $pdo->prepare("SELECT id FROM products WHERE (barcode = ? OR product_code = ?) AND status = 'active' LIMIT 1");
    $stmtP->execute([$query, $query]);
    $masterProd = $stmtP->fetch();

    if ($masterProd) {
        $stmtVars = $pdo->prepare("SELECT pv.*, p.name AS product_name, p.product_code,
                                          p.item_type, p.unit_type, p.meters_per_suit, p.thaan_length,
                                          p.selling_price AS default_selling_price, p.wholesale_price AS default_wholesale_price,
                                          s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex
                                   FROM product_variants pv
                                   JOIN products p ON pv.product_id = p.id
                                   JOIN sizes s ON pv.size_id = s.id
                                   JOIN colors c ON pv.color_id = c.id
                                   WHERE pv.product_id = ? AND pv.status = 'active'
                                   ORDER BY pv.stock_quantity DESC");
        $stmtVars->execute([$masterProd['id']]);
        $prodVariants = $stmtVars->fetchAll();

        if (count($prodVariants) === 1) {
            echo json_encode([
                'exact_match' => true,
                'variant'     => $prodVariants[0]
            ]);
            exit;
        } elseif (count($prodVariants) > 1) {
            $stmtProdDetail = $pdo->prepare("SELECT p.id, p.product_code, p.name, p.rack_location, p.season,
                                                    p.item_type, p.unit_type, p.meters_per_suit, p.thaan_length,
                                                    p.selling_price AS default_selling_price, p.wholesale_price AS default_wholesale_price,
                                                    c.name AS category_name, b.name AS brand_name,
                                                    COUNT(pv.id) AS total_variants,
                                                    COALESCE(SUM(pv.stock_quantity), 0) AS total_stock,
                                                    MIN(pv.selling_price) AS min_price,
                                                    MAX(pv.selling_price) AS max_price,
                                                    MIN(pv.wholesale_price) AS min_wholesale_price,
                                                    MAX(pv.wholesale_price) AS max_wholesale_price
                                             FROM products p
                                             LEFT JOIN categories c ON p.category_id = c.id
                                             LEFT JOIN brands b ON p.brand_id = b.id
                                             LEFT JOIN product_variants pv ON p.id = pv.product_id AND pv.status = 'active'
                                             WHERE p.id = ?
                                             GROUP BY p.id");
            $stmtProdDetail->execute([$masterProd['id']]);
            $pObj = $stmtProdDetail->fetch();
            echo json_encode([
                'exact_match' => false,
                'products'    => [$pObj]
            ]);
            exit;
        }
    }
}

// 2. Otherwise search by text (name, code, brand, category, size, color)
$params = [];
$where = ["p.status = 'active'"];

if (!empty($query)) {
    $where[] = "(p.name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ? OR b.name LIKE ? OR c.name LIKE ? OR pv.sku LIKE ? OR pv.barcode LIKE ?)";
    $like = "%$query%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}

if ($category_id > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_id;
}

$where_clause = implode(' AND ', $where);

$sql = "SELECT p.id, p.product_code, p.name, p.rack_location, p.season,
               p.item_type, p.unit_type, p.meters_per_suit, p.thaan_length,
               p.selling_price AS default_selling_price, p.wholesale_price AS default_wholesale_price,
               c.name AS category_name, b.name AS brand_name,
               COUNT(pv.id) AS total_variants,
               COALESCE(SUM(pv.stock_quantity), 0) AS total_stock,
               MIN(pv.selling_price) AS min_price,
               MAX(pv.selling_price) AS max_price,
               MIN(pv.wholesale_price) AS min_wholesale_price,
               MAX(pv.wholesale_price) AS max_wholesale_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_variants pv ON p.id = pv.product_id AND pv.status = 'active'
        WHERE $where_clause
        GROUP BY p.id
        ORDER BY p.name ASC
        LIMIT 40";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

echo json_encode(['exact_match' => false, 'products' => $products]);
