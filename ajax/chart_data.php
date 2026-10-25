<?php
// ajax/chart_data.php - Dynamic JSON for Chart.js Dashboard & Reports
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$chart = $_GET['chart'] ?? 'all';
$response = [];

// 1. Last 7 Days Daily Sales & Gross Profit Trend
if ($chart === 'all' || $chart === 'sales_trend') {
    $dates = [];
    $salesData = [];
    $profitData = [];

    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $dLabel = date('D (d M)', strtotime("-$i days"));
        $dates[] = $dLabel;

        // Total sales on date
        $stmtS = $pdo->prepare("SELECT COALESCE(SUM(net_total), 0) FROM sales WHERE DATE(sale_date) = ? AND sale_status = 'completed'");
        $stmtS->execute([$d]);
        $net_sales = floatval($stmtS->fetchColumn());
        $salesData[] = $net_sales;

        // COGS on date
        $stmtC = $pdo->prepare("SELECT COALESCE(SUM(si.quantity * si.cost_price), 0)
                                FROM sale_items si
                                JOIN sales s ON si.sale_id = s.id
                                WHERE DATE(s.sale_date) = ? AND s.sale_status = 'completed'");
        $stmtC->execute([$d]);
        $cogs = floatval($stmtC->fetchColumn());
        $gross_profit = $net_sales - $cogs;
        $profitData[] = max(0, $gross_profit);
    }

    $response['sales_trend'] = [
        'labels'  => $dates,
        'sales'   => $salesData,
        'profit'  => $profitData
    ];
}

// 2. Clothing Category Distribution
if ($chart === 'all' || $chart === 'category_distribution') {
    $stmtCat = $pdo->query("SELECT COALESCE(c.name, 'Uncategorized') as category_name, 
                                   SUM(si.total_price) as total_revenue
                            FROM sale_items si
                            JOIN products p ON si.product_id = p.id
                            LEFT JOIN categories c ON p.category_id = c.id
                            JOIN sales s ON si.sale_id = s.id
                            WHERE s.sale_status = 'completed'
                            GROUP BY c.id
                            ORDER BY total_revenue DESC
                            LIMIT 8");
    $cats = $stmtCat->fetchAll();
    $catLabels = [];
    $catData = [];
    foreach ($cats as $c) {
        $catLabels[] = $c['category_name'];
        $catData[] = floatval($c['total_revenue']);
    }

    $response['category_distribution'] = [
        'labels' => $catLabels,
        'data'   => $catData
    ];
}

// 3. Best Selling Sizes (Clothing Matrix Analytics)
if ($chart === 'all' || $chart === 'size_performance') {
    $stmtSize = $pdo->query("SELECT s.name as size_name, SUM(si.quantity) as total_qty
                             FROM sale_items si
                             JOIN product_variants pv ON si.variant_id = pv.id
                             JOIN sizes s ON pv.size_id = s.id
                             JOIN sales sa ON si.sale_id = sa.id
                             WHERE sa.sale_status = 'completed'
                             GROUP BY s.id
                             ORDER BY total_qty DESC
                             LIMIT 6");
    $sizes = $stmtSize->fetchAll();
    $sizeLabels = [];
    $sizeData = [];
    foreach ($sizes as $sz) {
        $sizeLabels[] = "Size " . $sz['size_name'];
        $sizeData[] = intval($sz['total_qty']);
    }
    $response['size_performance'] = [
        'labels' => $sizeLabels,
        'data'   => $sizeData
    ];
}

// 4. Payment Methods Breakdown
if ($chart === 'all' || $chart === 'payment_methods') {
    $stmtPay = $pdo->query("SELECT payment_method, SUM(amount) as total_amount 
                            FROM sale_payments 
                            GROUP BY payment_method");
    $payments = $stmtPay->fetchAll();
    $payLabels = [];
    $payData = [];
    foreach ($payments as $p) {
        $payLabels[] = ucfirst($p['payment_method']);
        $payData[] = floatval($p['total_amount']);
    }
    $response['payment_methods'] = [
        'labels' => $payLabels,
        'data'   => $payData
    ];
}

echo json_encode($response);
