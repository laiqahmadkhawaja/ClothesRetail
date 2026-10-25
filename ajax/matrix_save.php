<?php
// ajax/matrix_save.php - Bulk Save Clothing Variant Matrix
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_role('admin', 'manager', 'inventory');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['product_id']) || empty($data['cells'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid matrix data.']);
    exit;
}

$user = current_user();
$product_id = intval($data['product_id']);
$cells = $data['cells']; // array of { size_id, color_id, stock, purchase_price, selling_price, wholesale_price, sku, barcode }

try {
    $pdo->beginTransaction();

    $stmtCheck = $pdo->prepare("SELECT id, stock_quantity, purchase_price, selling_price FROM product_variants WHERE product_id = ? AND size_id = ? AND color_id = ? LIMIT 1");
    $stmtInsert = $pdo->prepare("INSERT INTO product_variants (product_id, size_id, color_id, sku, barcode, purchase_price, selling_price, wholesale_price, stock_quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmtUpdate = $pdo->prepare("UPDATE product_variants SET stock_quantity = ?, purchase_price = ?, selling_price = ?, wholesale_price = ?, sku = ?, barcode = ? WHERE id = ?");
    $stmtMovement = $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by) VALUES (?, ?, NOW(), ?, 'MATRIX-ADJ', ?, ?, ?, ?, ?)");

    $updated_count = 0;
    $created_count = 0;

    foreach ($cells as $cell) {
        $size_id = intval($cell['size_id']);
        $color_id = intval($cell['color_id']);
        $new_stock = intval($cell['stock']);
        $cost = floatval($cell['purchase_price'] ?? 0);
        $price = floatval($cell['selling_price'] ?? 0);
        $wholesale = floatval($cell['wholesale_price'] ?? 0);
        $sku = trim($cell['sku'] ?? '');
        $barcode = trim($cell['barcode'] ?? '');

        // If sku or barcode empty, auto-generate sensible defaults
        if (empty($sku)) {
            $sku = "PRD{$product_id}-S{$size_id}-C{$color_id}";
        }
        if (empty($barcode)) {
            $barcode = "89" . str_pad($product_id, 3, '0', STR_PAD_LEFT) . str_pad($size_id, 2, '0', STR_PAD_LEFT) . str_pad($color_id, 2, '0', STR_PAD_LEFT);
        }

        $stmtCheck->execute([$product_id, $size_id, $color_id]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $variant_id = $existing['id'];
            $old_stock = intval($existing['stock_quantity']);
            $diff = $new_stock - $old_stock;

            $stmtUpdate->execute([$new_stock, $cost, $price, $wholesale, $sku, $barcode, $variant_id]);
            $updated_count++;

            // Record movement if stock changed
            if ($diff > 0) {
                $stmtMovement->execute([$product_id, $variant_id, 'adjustment_plus', $diff, 0, $cost, 'Matrix Stock Addition', $user['id']]);
            } elseif ($diff < 0) {
                $stmtMovement->execute([$product_id, $variant_id, 'adjustment_minus', 0, abs($diff), $cost, 'Matrix Stock Reduction', $user['id']]);
            }
        } else {
            // Create new variant
            $stmtInsert->execute([$product_id, $size_id, $color_id, $sku, $barcode, $cost, $price, $wholesale, $new_stock]);
            $variant_id = $pdo->lastInsertId();
            $created_count++;

            if ($new_stock > 0) {
                $stmtMovement->execute([$product_id, $variant_id, 'opening', $new_stock, 0, $cost, 'Matrix Initial Variant Setup', $user['id']]);
            }
        }
    }

    $pdo->commit();
    log_audit($pdo, 'Update Matrix', 'Catalog', "Updated matrix for product #$product_id: $updated_count updated, $created_count created.");

    echo json_encode([
        'success' => true,
        'message' => "Matrix successfully saved! ($updated_count variants updated, $created_count created)"
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
