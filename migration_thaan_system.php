<?php
require_once __DIR__ . '/config/database.php';

try {
    // 1. Update products table for generic garment + fabric (thaan) support
    $prodCols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('item_type', $prodCols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN item_type ENUM('readymade', 'fabric') NOT NULL DEFAULT 'readymade' AFTER category_id");
        echo "Added item_type to products\n";
    }
    if (!in_array('unit_type', $prodCols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN unit_type VARCHAR(20) NOT NULL DEFAULT 'piece' AFTER item_type");
        echo "Added unit_type to products\n";
    }
    if (!in_array('meters_per_suit', $prodCols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN meters_per_suit DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER unit_type");
        echo "Added meters_per_suit to products\n";
    }
    if (!in_array('thaan_length', $prodCols)) {
        $pdo->exec("ALTER TABLE products ADD COLUMN thaan_length DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER meters_per_suit");
        echo "Added thaan_length to products\n";
    }

    // 2. Change stock_quantity in product_variants to DECIMAL(10,2) for meters & suits
    $pdo->exec("ALTER TABLE product_variants MODIFY COLUMN stock_quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    echo "Modified product_variants stock_quantity to DECIMAL\n";

    // 3. Change quantity in sale_items to DECIMAL(10,2)
    $saleItemCols = $pdo->query("SHOW COLUMNS FROM sale_items")->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec("ALTER TABLE sale_items MODIFY COLUMN quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00");
    echo "Modified sale_items quantity to DECIMAL\n";

    if (!in_array('unit_type', $saleItemCols)) {
        $pdo->exec("ALTER TABLE sale_items ADD COLUMN unit_type VARCHAR(20) NOT NULL DEFAULT 'piece' AFTER quantity");
        echo "Added unit_type to sale_items\n";
    }
    if (!in_array('pricing_tier', $saleItemCols)) {
        $pdo->exec("ALTER TABLE sale_items ADD COLUMN pricing_tier ENUM('retail', 'wholesale') NOT NULL DEFAULT 'retail' AFTER unit_price");
        echo "Added pricing_tier to sale_items\n";
    }

    // 4. Change quantity in purchase_items to DECIMAL(10,2)
    $pdo->exec("ALTER TABLE purchase_items MODIFY COLUMN quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00");
    echo "Modified purchase_items quantity to DECIMAL\n";

    // 5. Change quantities in stock_movements & stock_adjustment_items
    $pdo->exec("ALTER TABLE stock_movements MODIFY COLUMN qty_in DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    $pdo->exec("ALTER TABLE stock_movements MODIFY COLUMN qty_out DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    $pdo->exec("ALTER TABLE stock_adjustment_items MODIFY COLUMN quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    echo "Modified stock_movements quantities to DECIMAL\n";

    // 6. Add pricing_tier to sales table if not exists
    $saleCols = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('pricing_tier', $saleCols)) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN pricing_tier ENUM('retail', 'wholesale') NOT NULL DEFAULT 'retail' AFTER net_total");
        echo "Added pricing_tier to sales\n";
    }

    echo "ALL MIGRATIONS APPLIED SUCCESSFULLY!\n";
} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
}
