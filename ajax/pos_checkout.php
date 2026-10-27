<?php
// ajax/pos_checkout.php - Process POS Sale with Generic Garments & Fabric Support (Wholesale & Retail Tiers)
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'User session expired. Please sign in again.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['success' => false, 'error' => 'Cart is empty or invalid data received.']);
    exit;
}

$user = current_user();
$customer_id = intval($data['customer_id'] ?? 1);
$items = $data['items'];
$bill_discount = floatval($data['bill_discount'] ?? 0);
$tax_amount = floatval($data['tax_amount'] ?? 0);
$payment_method = $data['payment_method'] ?? 'cash';
$split_payments = $data['split_payments'] ?? [];
$paid_amount = floatval($data['paid_amount'] ?? 0);
$notes = trim($data['notes'] ?? '');
$pricing_tier = in_array($data['pricing_tier'] ?? '', ['retail', 'wholesale']) ? $data['pricing_tier'] : 'retail';

$active_register = get_active_register($pdo, $user['id']);
$register_id = $active_register ? $active_register['id'] : null;

try {
    $pdo->beginTransaction();

    // 1. Calculate and verify items against stock
    $subtotal = 0;
    $item_discount_total = 0;
    $validated_items = [];

    $stmtVar = $pdo->prepare("SELECT pv.*, p.name AS product_name, p.item_type, p.unit_type AS prod_unit_type, p.meters_per_suit 
                             FROM product_variants pv 
                             JOIN products p ON pv.product_id = p.id 
                             WHERE pv.id = ? FOR UPDATE");

    foreach ($items as $item) {
        $variant_id = intval($item['variant_id']);
        $qty = floatval($item['qty']);
        if ($qty <= 0) continue;

        $stmtVar->execute([$variant_id]);
        $variant = $stmtVar->fetch();

        if (!$variant) {
            throw new Exception("Product variant #$variant_id no longer exists.");
        }

        $unit_type = trim($item['unit_type'] ?? ($variant['prod_unit_type'] ?: 'piece'));
        $item_tier = in_array($item['pricing_tier'] ?? '', ['retail', 'wholesale']) ? $item['pricing_tier'] : $pricing_tier;

        // Calculate actual stock deduction
        $deduct_qty = $qty;
        if ($unit_type === 'suit' && floatval($variant['meters_per_suit']) > 0) {
            $deduct_qty = $qty * floatval($variant['meters_per_suit']);
        }

        if ($variant['stock_quantity'] < $deduct_qty) {
            $unitLabel = ($variant['item_type'] === 'fabric') ? ($variant['prod_unit_type'] ?: 'm') : 'units';
            throw new Exception("Insufficient stock for {$variant['product_name']} ({$variant['sku']}). In stock: {$variant['stock_quantity']} $unitLabel, requested deduction: $deduct_qty $unitLabel.");
        }

        $default_price = ($item_tier === 'wholesale' && floatval($variant['wholesale_price']) > 0) ? floatval($variant['wholesale_price']) : floatval($variant['selling_price']);
        $unit_price = floatval($item['unit_price'] ?? $default_price);
        $cost_price = floatval($variant['purchase_price']);
        $item_disc = floatval($item['discount'] ?? 0);
        $line_total = ($unit_price * $qty) - $item_disc;

        $subtotal += ($unit_price * $qty);
        $item_discount_total += $item_disc;

        $validated_items[] = [
            'variant'      => $variant,
            'qty'          => $qty,
            'deduct_qty'   => $deduct_qty,
            'unit_type'    => $unit_type,
            'pricing_tier' => $item_tier,
            'unit_price'   => $unit_price,
            'cost_price'   => $cost_price,
            'discount'     => $item_disc,
            'line_total'   => $line_total
        ];
    }

    if (empty($validated_items)) {
        throw new Exception("No valid items in the cart to checkout.");
    }

    // 2. Compute final financial figures
    $net_total = ($subtotal - $item_discount_total - $bill_discount) + $tax_amount;
    if ($net_total < 0) $net_total = 0;

    $change_returned = 0;
    $balance_due = 0;

    if ($payment_method === 'credit') {
        $paid_amount = 0;
        $change_returned = 0;
        $balance_due = $net_total;
        $payment_status = 'due';
    } elseif ($paid_amount >= $net_total) {
        $change_returned = $paid_amount - $net_total;
        $balance_due = 0;
        $payment_status = 'paid';
    } else {
        $change_returned = 0;
        $balance_due = $net_total - $paid_amount;
        $payment_status = ($paid_amount > 0) ? 'partial' : 'due';
    }

    // Strictly verify customer - Walk-in customers CANNOT have credit, balance due, or partial payment!
    $stmtCustCheck = $pdo->prepare("SELECT id, name, customer_code FROM customers WHERE id = ?");
    $stmtCustCheck->execute([$customer_id]);
    $custInfo = $stmtCustCheck->fetch();

    $isWalkIn = (!$custInfo 
        || $custInfo['id'] == 1 
        || $custInfo['customer_code'] === 'CUST-001' 
        || stripos($custInfo['name'], 'walk-in') !== false
        || stripos($custInfo['name'], 'walkin') !== false
    );

    if ($isWalkIn && ($payment_method === 'credit' || $balance_due > 0 || $paid_amount < $net_total)) {
        throw new Exception("Credit / Khata sales are NOT allowed for Walk-in customers. Walk-in customers must pay the full bill (" . format_currency($net_total) . ") in cash or digital payment.");
    }

    // 3. Generate unique invoice number
    $prefix = get_setting($pdo, 'invoice_prefix', 'AMF-');
    $invNumber = $prefix . date('ymd') . '-' . str_pad(mt_rand(100, 9999), 4, '0', STR_PAD_LEFT);

    // 4. Insert into sales table
    $stmtSale = $pdo->prepare("INSERT INTO sales 
        (invoice_number, customer_id, sale_date, subtotal, item_discount_total, bill_discount, tax_amount, net_total, paid_amount, change_returned, balance_due, payment_status, payment_method, sale_status, cashier_id, register_id, notes, pricing_tier)
        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?)");
    
    $stmtSale->execute([
        $invNumber, $customer_id, $subtotal, $item_discount_total, $bill_discount,
        $tax_amount, $net_total, $paid_amount, $change_returned, $balance_due,
        $payment_status, $payment_method, $user['id'], $register_id, $notes, $pricing_tier
    ]);
    $sale_id = $pdo->lastInsertId();

    // 5. Insert sale items, decrement variant stock, insert stock movement
    $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, quantity, unit_type, pricing_tier, unit_price, cost_price, discount, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtStockUpdate = $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE id = ?");
    $stmtMove = $pdo->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by) VALUES (?, ?, NOW(), 'sale', ?, 0, ?, ?, ?, ?)");

    foreach ($validated_items as $v) {
        $stmtItem->execute([
            $sale_id, $v['variant']['product_id'], $v['variant']['id'],
            $v['qty'], $v['unit_type'], $v['pricing_tier'],
            $v['unit_price'], $v['cost_price'], $v['discount'], $v['line_total']
        ]);

        $stmtStockUpdate->execute([$v['deduct_qty'], $v['variant']['id']]);

        $stmtMove->execute([
            $v['variant']['product_id'], $v['variant']['id'], $invNumber,
            $v['deduct_qty'], $v['cost_price'], "POS Sale #$invNumber ({$v['qty']} {$v['unit_type']})", $user['id']
        ]);
    }

    // 6. Record Payments (Supports Split Payment)
    $cash_collected = 0;
    $stmtPay = $pdo->prepare("INSERT INTO sale_payments (sale_id, payment_method, amount, reference_note) VALUES (?, ?, ?, ?)");

    if ($payment_method === 'split' && !empty($split_payments)) {
        foreach ($split_payments as $sp) {
            $sp_method = $sp['method'];
            $sp_amount = floatval($sp['amount']);
            $sp_note = $sp['note'] ?? '';
            if ($sp_amount > 0) {
                $stmtPay->execute([$sale_id, $sp_method, $sp_amount, $sp_note]);
                if ($sp_method === 'cash') {
                    $cash_collected += $sp_amount;
                }
            }
        }
    } else {
        $effective_paid = min($paid_amount, $net_total);
        $stmtPay->execute([$sale_id, $payment_method, $effective_paid, "POS Single Payment ($payment_method)"]);
        if ($payment_method === 'cash') {
            $cash_collected += $effective_paid;
        }
    }

    // 7. Customer Khata ledger update if balance due exists
    if ($customer_id > 1 && $balance_due > 0) {
        $stmtCust = $pdo->prepare("SELECT current_balance FROM customers WHERE id = ? FOR UPDATE");
        $stmtCust->execute([$customer_id]);
        $custBalance = floatval($stmtCust->fetchColumn());

        $newCustBalance = $custBalance + $balance_due;

        $stmtCustUpd = $pdo->prepare("UPDATE customers SET current_balance = ?, loyalty_points = loyalty_points + ? WHERE id = ?");
        $loyaltyAdd = floor($net_total / 100);
        $stmtCustUpd->execute([$newCustBalance, $loyaltyAdd, $customer_id]);

        $stmtCustLedger = $pdo->prepare("INSERT INTO customer_ledger (customer_id, entry_date, transaction_type, reference_id, reference_no, debit, credit, balance, notes) VALUES (?, NOW(), 'sale', ?, ?, ?, 0.00, ?, ?)");
        $stmtCustLedger->execute([
            $customer_id, $sale_id, $invNumber, $balance_due, $newCustBalance, "Credit Sale #$invNumber (Bill: Rs. $net_total, Paid: Rs. $paid_amount)"
        ]);
    } elseif ($customer_id > 1) {
        // Just add loyalty points for fully paid customer
        $loyaltyAdd = floor($net_total / 100);
        $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?")->execute([$loyaltyAdd, $customer_id]);
    }

    // 8. Update Active Cash Register Shift if cash collected
    if ($register_id && $cash_collected > 0) {
        $pdo->prepare("UPDATE cash_registers SET total_sales_cash = total_sales_cash + ? WHERE id = ?")
            ->execute([$cash_collected, $register_id]);
    }

    $pdo->commit();

    log_audit($pdo, 'Sale Checkout', 'POS', "Created invoice #$invNumber, Tier: $pricing_tier, Total: Rs. $net_total");

    echo json_encode([
        'success'        => true,
        'sale_id'        => $sale_id,
        'invoice_number' => $invNumber,
        'net_total'      => $net_total,
        'paid_amount'    => $paid_amount,
        'change'         => $change_returned,
        'balance_due'    => $balance_due
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
