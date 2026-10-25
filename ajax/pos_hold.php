<?php
// ajax/pos_hold.php - Save or Retrieve Held Sales (Park Cart)
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $pdo->query("SELECT s.id, s.invoice_number, s.sale_date, s.net_total, s.notes, c.name as customer_name,
                                COUNT(si.id) as item_count
                         FROM sales s
                         LEFT JOIN customers c ON s.customer_id = c.id
                         LEFT JOIN sale_items si ON s.id = si.sale_id
                         WHERE s.sale_status = 'held'
                         GROUP BY s.id
                         ORDER BY s.id DESC");
    echo json_encode(['success' => true, 'held_sales' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'load') {
    $sale_id = intval($_GET['sale_id'] ?? 0);
    $stmtSale = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND sale_status = 'held'");
    $stmtSale->execute([$sale_id]);
    $sale = $stmtSale->fetch();

    if (!$sale) {
        echo json_encode(['success' => false, 'error' => 'Held sale not found']);
        exit;
    }

    $stmtItems = $pdo->prepare("SELECT si.*, pv.sku, pv.barcode, pv.stock_quantity,
                                       p.name as product_name, s.name as size_name, c.name as color_name, c.hex_code as color_hex
                                FROM sale_items si
                                JOIN product_variants pv ON si.variant_id = pv.id
                                JOIN products p ON si.product_id = p.id
                                JOIN sizes s ON pv.size_id = s.id
                                JOIN colors c ON pv.color_id = c.id
                                WHERE si.sale_id = ?");
    $stmtItems->execute([$sale_id]);
    $items = $stmtItems->fetchAll();

    // Delete held sale record so it can be re-checked out
    $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$sale_id]);

    echo json_encode([
        'success' => true,
        'sale'    => $sale,
        'items'   => $items
    ]);
    exit;
}

if ($action === 'hold' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || empty($data['items'])) {
        echo json_encode(['success' => false, 'error' => 'No items in cart to hold']);
        exit;
    }

    $user = current_user();
    $customer_id = intval($data['customer_id'] ?? 1);
    $items = $data['items'];
    $bill_discount = floatval($data['bill_discount'] ?? 0);
    $subtotal = floatval($data['subtotal'] ?? 0);
    $net_total = floatval($data['net_total'] ?? 0);
    $notes = trim($data['notes'] ?? 'Held Sale');

    $prefix = get_setting($pdo, 'invoice_prefix', 'AMF-');
    $invNumber = 'HOLD-' . date('His') . '-' . mt_rand(10, 99);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO sales (invoice_number, customer_id, sale_date, subtotal, bill_discount, net_total, sale_status, cashier_id, notes)
                               VALUES (?, ?, NOW(), ?, ?, ?, 'held', ?, ?)");
        $stmt->execute([$invNumber, $customer_id, $subtotal, $bill_discount, $net_total, $user['id'], $notes]);
        $sale_id = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, quantity, unit_price, cost_price, discount, total_price)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($items as $it) {
            $stmtItem->execute([
                $sale_id, $it['product_id'], $it['variant_id'], $it['qty'],
                $it['unit_price'], $it['cost_price'] ?? 0, $it['discount'] ?? 0, $it['total_price']
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'hold_number' => $invNumber]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
