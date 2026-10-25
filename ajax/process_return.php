<?php
// ajax/process_return.php - Atomic Sales Return & Product Exchange Handler
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['sale_id']) || empty($data['returned_items'])) {
    echo json_encode(['success' => false, 'error' => 'Missing return information.']);
    exit;
}

$user = current_user();
$sale_id = intval($data['sale_id']);
$return_type = ($data['return_type'] === 'exchange') ? 'exchange' : 'refund';
$returned_items = $data['returned_items'];
$exchange_items = $data['exchange_items'] ?? [];
$refund_method = $data['refund_method'] ?? 'cash';
$reason = trim($data['reason'] ?? 'Customer Return / Exchange');

$active_register = get_active_register($pdo, $user['id']);
$register_id = $active_register ? $active_register['id'] : null;

try {
    $pdo->beginTransaction();

    // 1. Fetch and verify original sale
    $stmtSale = $pdo->prepare("SELECT * FROM sales WHERE id = ? FOR UPDATE");
    $stmtSale->execute([$sale_id]);
    $sale = $stmtSale->fetch();

    if (!$sale) {
        throw new Exception("Original sale invoice not found.");
    }

    $customer_id = $sale['customer_id'];
    $total_return_value = 0;

    // 2. Validate return items
    $stmtItemCheck = $pdo->prepare("SELECT si.*, (si.quantity - si.returned_quantity) as returnable_qty, pv.purchase_price
                                    FROM sale_items si
                                    JOIN product_variants pv ON si.variant_id = pv.id
                                    WHERE si.id = ? AND si.sale_id = ? FOR UPDATE");

    $processed_return_items = [];

    foreach ($returned_items as $ret) {
        $sale_item_id = intval($ret['sale_item_id']);
        $ret_qty = intval($ret['qty']);
        if ($ret_qty <= 0) continue;

        $stmtItemCheck->execute([$sale_item_id, $sale_id]);
        $origItem = $stmtItemCheck->fetch();

        if (!$origItem) {
            throw new Exception("Item #$sale_item_id not found on this invoice.");
        }

        if ($ret_qty > $origItem['returnable_qty']) {
            throw new Exception("Cannot return $ret_qty units. Only {$origItem['returnable_qty']} units remain returnable.");
        }

        $unit_price = floatval($origItem['unit_price']);
        $refund_subtotal = $unit_price * $ret_qty;
        $total_return_value += $refund_subtotal;

        $condition = ($ret['condition'] === 'defective') ? 'defective' : 'restock';

        $processed_return_items[] = [
            'sale_item_id'    => $sale_item_id,
            'product_id'      => $origItem['product_id'],
            'variant_id'      => $origItem['variant_id'],
            'qty'             => $ret_qty,
            'unit_price'      => $unit_price,
            'refund_subtotal' => $refund_subtotal,
            'condition'       => $condition,
            'cost_price'      => $origItem['purchase_price']
        ];
    }

    if (empty($processed_return_items)) {
        throw new Exception("No valid items to return.");
    }

    // 3. Process exchange items if applicable
    $total_exchange_value = 0;
    $processed_exchange_items = [];

    if ($return_type === 'exchange') {
        if (empty($exchange_items)) {
            throw new Exception("Exchange requires selecting replacement items.");
        }

        $stmtExchCheck = $pdo->prepare("SELECT pv.*, p.name AS product_name 
                                        FROM product_variants pv 
                                        JOIN products p ON pv.product_id = p.id 
                                        WHERE pv.id = ? FOR UPDATE");

        foreach ($exchange_items as $ex) {
            $ex_variant_id = intval($ex['variant_id']);
            $ex_qty = intval($ex['qty']);
            if ($ex_qty <= 0) continue;

            $stmtExchCheck->execute([$ex_variant_id]);
            $exVariant = $stmtExchCheck->fetch();

            if (!$exVariant) {
                throw new Exception("Replacement variant #$ex_variant_id not found.");
            }

            if ($exVariant['stock_quantity'] < $ex_qty) {
                throw new Exception("Insufficient stock for replacement item {$exVariant['product_name']}. In stock: {$exVariant['stock_quantity']}.");
            }

            $ex_price = floatval($ex['unit_price'] ?? $exVariant['selling_price']);
            $ex_subtotal = $ex_price * $ex_qty;
            $total_exchange_value += $ex_subtotal;

            $processed_exchange_items[] = [
                'variant'    => $exVariant,
                'qty'        => $ex_qty,
                'unit_price' => $ex_price,
                'cost_price' => floatval($exVariant['purchase_price']),
                'subtotal'   => $ex_subtotal
            ];
        }
    }

    // Net financial difference:
    // If Return only: store owes customer $total_return_value
    // If Exchange: Net Difference = $total_exchange_value - $total_return_value
    $net_diff = $total_exchange_value - $total_return_value;

    // 4. Create Return Record
    $retNumber = 'RET-' . date('ymd') . '-' . mt_rand(100, 999);
    $stmtRet = $pdo->prepare("INSERT INTO sales_returns (return_number, sale_id, customer_id, return_date, refund_amount, refund_method, return_type, reason, created_by)
                             VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)");
    $stmtRet->execute([
        $retNumber, $sale_id, $customer_id, $total_return_value, $refund_method, $return_type, $reason, $user['id']
    ]);
    $return_id = $pdo->lastInsertId();

    // 5. Insert Return Items & Update Original Sale Items + Stock
    $stmtRetItem = $pdo->prepare("INSERT INTO sales_return_items (return_id, sale_item_id, product_id, variant_id, quantity, unit_price, refund_subtotal, condition_type)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtUpdSaleItem = $pdo->prepare("UPDATE sale_items SET returned_quantity = returned_quantity + ?, is_returned = IF(returned_quantity + ? >= quantity, 1, 0) WHERE id = ?");
    $stmtRestock = $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?");
    $stmtMoveIn = $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by)
                                VALUES (?, ?, NOW(), ?, ?, ?, 0, ?, ?, ?)");

    foreach ($processed_return_items as $pri) {
        $stmtRetItem->execute([
            $return_id, $pri['sale_item_id'], $pri['product_id'], $pri['variant_id'],
            $pri['qty'], $pri['unit_price'], $pri['refund_subtotal'], $pri['condition']
        ]);

        $stmtUpdSaleItem->execute([$pri['qty'], $pri['qty'], $pri['sale_item_id']]);

        if ($pri['condition'] === 'restock') {
            $stmtRestock->execute([$pri['qty'], $pri['variant_id']]);
            $stmtMoveIn->execute([$pri['product_id'], $pri['variant_id'], 'sale_return', $retNumber, $pri['qty'], $pri['cost_price'], "Return Restock from Invoice {$sale['invoice_number']}", $user['id']]);
        } else {
            $stmtMoveIn->execute([$pri['product_id'], $pri['variant_id'], 'damage', $retNumber, 0, $pri['cost_price'], "Defective Return from Invoice {$sale['invoice_number']}", $user['id']]);
        }
    }

    // 6. If Exchange, create linked exchange sale and decrement replacement stock
    $exchange_sale_id = null;
    if ($return_type === 'exchange') {
        $exInvNumber = 'EXCH-' . date('ymd') . '-' . mt_rand(100, 999);
        $stmtExSale = $pdo->prepare("INSERT INTO sales (invoice_number, customer_id, sale_date, subtotal, net_total, paid_amount, balance_due, payment_status, payment_method, sale_status, cashier_id, notes)
                                     VALUES (?, ?, NOW(), ?, ?, ?, 0.00, 'paid', 'cash', 'completed', ?, ?)");
        $stmtExSale->execute([
            $exInvNumber, $customer_id, $total_exchange_value, $total_exchange_value, $total_exchange_value,
            $user['id'], "Exchange for Return #$retNumber (Orig Invoice: {$sale['invoice_number']})"
        ]);
        $exchange_sale_id = $pdo->lastInsertId();

        $stmtExItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, quantity, unit_price, cost_price, discount, total_price)
                                     VALUES (?, ?, ?, ?, ?, ?, 0.00, ?)");
        $stmtExStockDec = $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $stmtExMoveOut = $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by)
                                       VALUES (?, ?, NOW(), 'exchange_out', ?, 0, ?, ?, ?, ?)");

        foreach ($processed_exchange_items as $pei) {
            $stmtExItem->execute([
                $exchange_sale_id, $pei['variant']['product_id'], $pei['variant']['id'],
                $pei['qty'], $pei['unit_price'], $pei['cost_price'], $pei['subtotal']
            ]);

            $stmtExStockDec->execute([$pei['qty'], $pei['variant']['id']]);

            $stmtExMoveOut->execute([
                $pei['variant']['product_id'], $pei['variant']['id'], $exInvNumber,
                $pei['qty'], $pei['cost_price'], "Product Exchange replacement for Return #$retNumber", $user['id']
            ]);
        }

        $pdo->prepare("UPDATE sales_returns SET exchange_sale_id = ? WHERE id = ?")->execute([$exchange_sale_id, $return_id]);
    }

    // 7. Cash register and Khata reconciliation
    if ($return_type === 'refund') {
        if ($refund_method === 'cash') {
            if ($register_id) {
                $pdo->prepare("UPDATE cash_registers SET total_refunds_paid = total_refunds_paid + ? WHERE id = ?")
                    ->execute([$total_return_value, $register_id]);
            }
        } elseif ($refund_method === 'credit' && $customer_id > 1) {
            // Credit to customer Khata (reduces what customer owes)
            $stmtCust = $pdo->prepare("SELECT current_balance FROM customers WHERE id = ? FOR UPDATE");
            $stmtCust->execute([$customer_id]);
            $currentBal = floatval($stmtCust->fetchColumn());
            $newBal = $currentBal - $total_return_value;

            $pdo->prepare("UPDATE customers SET current_balance = ? WHERE id = ?")->execute([$newBal, $customer_id]);

            $pdo->prepare("INSERT INTO customer_ledger (customer_id, entry_date, transaction_type, reference_id, reference_no, debit, credit, balance, notes)
                           VALUES (?, NOW(), 'return_refund', ?, ?, 0.00, ?, ?, ?)")
                ->execute([$customer_id, $return_id, $retNumber, $total_return_value, $newBal, "Refund credit for Return #$retNumber"]);
        }
    } else {
        // Exchange Net difference handling
        if ($net_diff > 0) {
            // Customer owes extra payment
            if ($register_id && $refund_method === 'cash') {
                $pdo->prepare("UPDATE cash_registers SET total_sales_cash = total_sales_cash + ? WHERE id = ?")
                    ->execute([$net_diff, $register_id]);
            } elseif ($customer_id > 1 && $refund_method === 'credit') {
                $stmtCust = $pdo->prepare("SELECT current_balance FROM customers WHERE id = ? FOR UPDATE");
                $stmtCust->execute([$customer_id]);
                $currentBal = floatval($stmtCust->fetchColumn());
                $newBal = $currentBal + $net_diff;
                $pdo->prepare("UPDATE customers SET current_balance = ? WHERE id = ?")->execute([$newBal, $customer_id]);

                $pdo->prepare("INSERT INTO customer_ledger (customer_id, entry_date, transaction_type, reference_id, reference_no, debit, credit, balance, notes)
                               VALUES (?, NOW(), 'exchange_diff', ?, ?, ?, 0.00, ?, ?)")
                    ->execute([$customer_id, $return_id, $retNumber, $net_diff, $newBal, "Exchange price difference owed for #$retNumber"]);
            }
        } elseif ($net_diff < 0) {
            // Store owes refund for excess returned value
            $refund_due = abs($net_diff);
            if ($register_id && $refund_method === 'cash') {
                $pdo->prepare("UPDATE cash_registers SET total_refunds_paid = total_refunds_paid + ? WHERE id = ?")
                    ->execute([$refund_due, $register_id]);
            } elseif ($customer_id > 1 && $refund_method === 'credit') {
                $stmtCust = $pdo->prepare("SELECT current_balance FROM customers WHERE id = ? FOR UPDATE");
                $stmtCust->execute([$customer_id]);
                $currentBal = floatval($stmtCust->fetchColumn());
                $newBal = $currentBal - $refund_due;
                $pdo->prepare("UPDATE customers SET current_balance = ? WHERE id = ?")->execute([$newBal, $customer_id]);

                $pdo->prepare("INSERT INTO customer_ledger (customer_id, entry_date, transaction_type, reference_id, reference_no, debit, credit, balance, notes)
                               VALUES (?, NOW(), 'exchange_diff', ?, ?, 0.00, ?, ?, ?)")
                    ->execute([$customer_id, $return_id, $retNumber, $refund_due, $newBal, "Exchange price difference refund for #$retNumber"]);
            }
        }
    }

    $pdo->commit();

    log_audit($pdo, 'Process ' . ucfirst($return_type), 'Sales', "Processed $return_type #$retNumber for Invoice {$sale['invoice_number']}");

    echo json_encode([
        'success'             => true,
        'return_number'       => $retNumber,
        'return_id'           => $return_id,
        'total_return_value'  => $total_return_value,
        'total_exchange_value'=> $total_exchange_value,
        'net_difference'      => $net_diff
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
