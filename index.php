<?php
// index.php - Main Dashboard & Business Analytics
$page_title = 'Dashboard & Business Intelligence';
require_once __DIR__ . '/includes/header.php';

// Today's KPIs
$today = date('Y-m-d');
$stmtToday = $pdo->prepare("SELECT 
    COUNT(id) AS total_tx,
    COALESCE(SUM(net_total), 0) AS total_sales,
    COALESCE(SUM(paid_amount), 0) AS total_collected
    FROM sales 
    WHERE DATE(sale_date) = ? AND sale_status = 'completed'");
$stmtToday->execute([$today]);
$todayStats = $stmtToday->fetch();

// Today COGS for Gross Profit
$stmtTodayCOGS = $pdo->prepare("SELECT COALESCE(SUM(si.quantity * si.cost_price), 0)
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) = ? AND s.sale_status = 'completed'");
$stmtTodayCOGS->execute([$today]);
$todayCOGS = floatval($stmtTodayCOGS->fetchColumn());
$todayGrossProfit = $todayStats['total_sales'] - $todayCOGS;

// Total Stock Valuation
$stmtStock = $pdo->query("SELECT 
    COUNT(id) AS total_variants,
    COALESCE(SUM(stock_quantity), 0) AS total_units,
    COALESCE(SUM(stock_quantity * purchase_price), 0) AS stock_cost_val,
    COALESCE(SUM(stock_quantity * selling_price), 0) AS stock_retail_val
    FROM product_variants WHERE status = 'active'");
$stockStats = $stmtStock->fetch();

// Customer Khata Receivables
$totalReceivables = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE status = 'active'")->fetchColumn();

// Supplier Payables
$totalPayables = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE status = 'active'")->fetchColumn();

// Today Expenses
$stmtTodayExp = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = ?");
$stmtTodayExp->execute([$today]);
$todayExpenses = floatval($stmtTodayExp->fetchColumn());

// Low Stock Alerts (Stock <= alert_quantity)
$stmtLowStock = $pdo->query("SELECT pv.id, pv.sku, pv.stock_quantity, pv.selling_price,
                                    p.name AS product_name, p.alert_quantity, p.rack_location,
                                    s.name AS size_name, c.name AS color_name, c.hex_code AS color_hex
                             FROM product_variants pv
                             JOIN products p ON pv.product_id = p.id
                             JOIN sizes s ON pv.size_id = s.id
                             JOIN colors c ON pv.color_id = c.id
                             WHERE pv.stock_quantity <= p.alert_quantity AND pv.status = 'active'
                             ORDER BY pv.stock_quantity ASC
                             LIMIT 6");
$lowStockItems = $stmtLowStock->fetchAll();

// Recent 6 Sales
$stmtRecentSales = $pdo->query("SELECT s.*, c.name AS customer_name, u.full_name AS cashier_name
                                FROM sales s
                                LEFT JOIN customers c ON s.customer_id = c.id
                                LEFT JOIN users u ON s.cashier_id = u.id
                                WHERE s.sale_status = 'completed'
                                ORDER BY s.id DESC
                                LIMIT 6");
$recentSales = $stmtRecentSales->fetchAll();
?>

<!-- Quick Action Strip -->
<div class="row g-2 mb-4 no-print">
    <div class="col-6 col-md-auto">
        <a href="pos.php" class="btn btn-primary px-3 py-2 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-upc-scan fs-5"></i> <strong>New POS Sale (F2)</strong>
        </a>
    </div>
    <div class="col-6 col-md-auto">
        <a href="product_matrix.php" class="btn btn-outline-primary px-3 py-2 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-grid-3x3-gap"></i> Size & Color Matrix
        </a>
    </div>
    <div class="col-6 col-md-auto">
        <a href="purchase_create.php" class="btn btn-outline-dark px-3 py-2 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-cart-plus"></i> New Stock Purchase
        </a>
    </div>
    <div class="col-6 col-md-auto">
        <a href="returns.php" class="btn btn-outline-danger px-3 py-2 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-arrow-left-right"></i> Return / Exchange
        </a>
    </div>
    <div class="col-6 col-md-auto">
        <a href="customer_ledger.php" class="btn btn-outline-success px-3 py-2 w-100 shadow-sm d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-cash"></i> Customer Khata
        </a>
    </div>
</div>

<!-- Primary KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Today's Net Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Today's Net Sales</span>
                <div class="kpi-icon bg-primary-subtle text-primary">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 text-dark"><?= format_currency($todayStats['total_sales']) ?></h3>
            <div class="d-flex justify-content-between align-items-center small text-muted">
                <span><?= $todayStats['total_tx'] ?> Transactions</span>
                <span class="text-success fw-semibold"><i class="bi bi-check2"></i> Completed</span>
            </div>
        </div>
    </div>

    <!-- Today's Gross Profit -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Today's Gross Profit</span>
                <div class="kpi-icon bg-success-subtle text-success">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 text-success"><?= format_currency($todayGrossProfit) ?></h3>
            <div class="d-flex justify-content-between align-items-center small text-muted">
                <span>COGS: <?= format_currency($todayCOGS) ?></span>
                <?php if ($todayStats['total_sales'] > 0): ?>
                    <span class="badge bg-success-subtle text-success"><?= round(($todayGrossProfit / $todayStats['total_sales']) * 100, 1) ?>% Margin</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Customer Khata Receivables -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Khata Receivables</span>
                <div class="kpi-icon bg-danger-subtle text-danger">
                    <i class="bi bi-person-exclamation"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 text-danger"><?= format_currency($totalReceivables) ?></h3>
            <div class="d-flex justify-content-between align-items-center small text-muted">
                <a href="customers.php" class="text-decoration-none small">View Khata Customers <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Stock Valuation at Cost -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card kpi-card p-3 h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-bold text-uppercase">Stock Value (Cost)</span>
                <div class="kpi-icon bg-info-subtle text-info">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
            <h3 class="fw-bold mb-1 text-info"><?= format_currency($stockStats['stock_cost_val']) ?></h3>
            <div class="d-flex justify-content-between align-items-center small text-muted">
                <span><?= number_format($stockStats['total_units']) ?> Total Garments</span>
                <span class="badge bg-info-subtle text-info">Retail: <?= format_currency($stockStats['stock_retail_val']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Proper Charts & Graphs Row -->
<div class="row g-3 mb-4">
    <!-- 7-Day Sales & Gross Profit Trend -->
    <div class="col-12 col-xl-8">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-graph-up text-primary me-2"></i> 7-Day Revenue & Gross Profit Trend</h6>
                    <small class="text-muted">Daily performance comparison (Sales vs Profit)</small>
                </div>
                <span class="badge bg-primary-subtle text-primary">Live Trend</span>
            </div>
            <div style="height: 310px; position: relative;">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Sales Breakdown -->
    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-pie-chart text-success me-2"></i> Category Share</h6>
                    <small class="text-muted">Revenue by clothing category</small>
                </div>
            </div>
            <div style="height: 310px; position: relative;" class="d-flex align-items-center justify-content-center">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Charts & Clothing Analytics -->
<div class="row g-3 mb-4">
    <!-- Size Performance (Matrix Analytics) -->
    <div class="col-12 col-md-6 col-xl-6">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bar-chart-steps text-info me-2"></i> Best-Selling Clothing Sizes</h6>
                    <small class="text-muted">Total units sold per size</small>
                </div>
            </div>
            <div style="height: 250px; position: relative;">
                <canvas id="sizeChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Payment Methods Breakdown -->
    <div class="col-12 col-md-6 col-xl-6">
        <div class="card border-0 shadow-sm rounded-3 p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-wallet2 text-warning me-2"></i> Payment Methods Mix</h6>
                    <small class="text-muted">Cash vs JazzCash / Easypaisa / Bank / Card</small>
                </div>
            </div>
            <div style="height: 250px; position: relative;" class="d-flex align-items-center justify-content-center">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tables: Low Stock Warnings & Recent Sales -->
<div class="row g-3 mb-4">
    <!-- Low Stock Alerts -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Low Stock Matrix Warnings</h6>
                <a href="inventory.php" class="btn btn-sm btn-outline-danger">All Inventory</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Garment</th>
                            <th>Variant</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lowStockItems)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">All clothing variants have healthy stock levels!</td></tr>
                        <?php else: ?>
                            <?php foreach ($lowStockItems as $lsi): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($lsi['product_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($lsi['rack_location'] ?? 'Store') ?></small>
                                    </td>
                                    <td>
                                        <span class="size-badge me-1"><?= htmlspecialchars($lsi['size_name']) ?></span>
                                        <span class="color-dot" style="background-color: <?= htmlspecialchars($lsi['color_hex']) ?>;"></span>
                                        <small><?= htmlspecialchars($lsi['color_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $lsi['stock_quantity'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?> fw-bold">
                                            <?= $lsi['stock_quantity'] ?> left
                                        </span>
                                    </td>
                                    <td>
                                        <a href="purchase_create.php" class="btn btn-sm btn-outline-primary" title="Reorder from Supplier">
                                            <i class="bi bi-cart-plus"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-primary me-2"></i> Recent Sales Invoices</h6>
                <a href="reports.php" class="btn btn-sm btn-outline-primary">View Full Ledger</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Net Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-end">Print</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSales)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No sales recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentSales as $rs): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary"><?= htmlspecialchars($rs['invoice_number']) ?></span>
                                        <div class="text-muted" style="font-size: 0.72rem;"><?= format_date($rs['sale_date']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($rs['customer_name'] ?? 'Walk-in') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($rs['cashier_name'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <strong class="text-dark"><?= format_currency($rs['net_total']) ?></strong>
                                    </td>
                                    <td>
                                        <?= payment_badge($rs['payment_method']) ?>
                                    </td>
                                    <td>
                                        <?= status_badge($rs['payment_status']) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="print_receipt.php?id=<?= $rs['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Thermal Receipt">
                                                <i class="bi bi-receipt"></i>
                                            </a>
                                            <a href="print_invoice.php?id=<?= $rs['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="A4 Invoice">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Chart.js Initializer -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    fetch('ajax/chart_data.php?chart=all')
        .then(res => res.json())
        .then(data => {
            // 1. Sales & Profit Trend (Line + Bar combo)
            if (data.sales_trend) {
                const ctxTrend = document.getElementById('salesTrendChart').getContext('2d');
                new Chart(ctxTrend, {
                    type: 'line',
                    data: {
                        labels: data.sales_trend.labels,
                        datasets: [
                            {
                                label: 'Net Sales (Rs)',
                                data: data.sales_trend.sales,
                                borderColor: '#4f46e5',
                                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                fill: true,
                                tension: 0.35,
                                borderWidth: 3,
                                pointBackgroundColor: '#4f46e5',
                                pointRadius: 4
                            },
                            {
                                label: 'Gross Profit (Rs)',
                                data: data.sales_trend.profit,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                fill: true,
                                tension: 0.35,
                                borderWidth: 2,
                                borderDash: [4, 4],
                                pointBackgroundColor: '#10b981',
                                pointRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { position: 'top', labels: { boxWidth: 12 } }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: v => 'Rs ' + v.toLocaleString() }
                            }
                        }
                    }
                });
            }

            // 2. Category Share (Donut Chart)
            if (data.category_distribution) {
                const ctxCat = document.getElementById('categoryChart').getContext('2d');
                new Chart(ctxCat, {
                    type: 'doughnut',
                    data: {
                        labels: data.category_distribution.labels,
                        datasets: [{
                            data: data.category_distribution.data,
                            backgroundColor: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#64748b', '#14b8a6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { boxWidth: 10, font: { size: 11 } } }
                        },
                        cutout: '65%'
                    }
                });
            }

            // 3. Size Performance (Bar Chart)
            if (data.size_performance) {
                const ctxSize = document.getElementById('sizeChart').getContext('2d');
                new Chart(ctxSize, {
                    type: 'bar',
                    data: {
                        labels: data.size_performance.labels,
                        datasets: [{
                            label: 'Units Sold',
                            data: data.size_performance.data,
                            backgroundColor: '#0ea5e9',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            // 4. Payment Methods (Pie Chart)
            if (data.payment_methods) {
                const ctxPay = document.getElementById('paymentChart').getContext('2d');
                new Chart(ctxPay, {
                    type: 'pie',
                    data: {
                        labels: data.payment_methods.labels,
                        datasets: [{
                            data: data.payment_methods.data,
                            backgroundColor: ['#10b981', '#4f46e5', '#ef4444', '#0ea5e9', '#f59e0b', '#6b7280']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { boxWidth: 10, font: { size: 11 } } }
                        }
                    }
                });
            }
        })
        .catch(err => console.error("Error loading dashboard charts:", err));
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
