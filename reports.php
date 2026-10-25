<?php
// reports.php - Comprehensive Financial & Accounting Reports Engine
$page_title = 'Accounting & Financial Reports';
require_once __DIR__ . '/includes/header.php';

require_role('admin', 'manager', 'accountant');

// Date Range Filtering
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // 1st of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$active_tab = $_GET['tab'] ?? 'pnl';

// ==========================================
// 1. PROFIT & LOSS (INCOME STATEMENT) DATA
// ==========================================

// Gross Sales & Discounts
$stmtSales = $pdo->prepare("SELECT 
    COALESCE(SUM(subtotal), 0) AS gross_sales,
    COALESCE(SUM(item_discount_total + bill_discount), 0) AS total_discounts,
    COALESCE(SUM(net_total), 0) AS net_sales
    FROM sales 
    WHERE DATE(sale_date) BETWEEN ? AND ? AND sale_status = 'completed'");
$stmtSales->execute([$start_date, $end_date]);
$salesSummary = $stmtSales->fetch();

// Sales Returns
$stmtReturns = $pdo->prepare("SELECT COALESCE(SUM(refund_amount), 0) FROM sales_returns WHERE DATE(return_date) BETWEEN ? AND ?");
$stmtReturns->execute([$start_date, $end_date]);
$totalReturns = floatval($stmtReturns->fetchColumn());

$adjustedNetSales = $salesSummary['net_sales'] - $totalReturns;

// Cost of Goods Sold (COGS)
$stmtCOGS = $pdo->prepare("SELECT COALESCE(SUM(si.quantity * si.cost_price), 0)
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.sale_status = 'completed'");
$stmtCOGS->execute([$start_date, $end_date]);
$totalCOGS = floatval($stmtCOGS->fetchColumn());

// Gross Profit
$grossProfit = $adjustedNetSales - $totalCOGS;
$grossMargin = $adjustedNetSales > 0 ? ($grossProfit / $adjustedNetSales) * 100 : 0;

// Operating Expenses by Category
$stmtExp = $pdo->prepare("SELECT ec.name AS category_name, COALESCE(SUM(e.amount), 0) AS cat_total
    FROM expense_categories ec
    LEFT JOIN expenses e ON ec.id = e.category_id AND DATE(e.expense_date) BETWEEN ? AND ?
    GROUP BY ec.id
    ORDER BY cat_total DESC");
$stmtExp->execute([$start_date, $end_date]);
$expenseBreakdown = $stmtExp->fetchAll();

$totalExpenses = 0;
foreach ($expenseBreakdown as $eb) {
    $totalExpenses += floatval($eb['cat_total']);
}

// Net Operating Profit
$netProfit = $grossProfit - $totalExpenses;
$netMargin = $adjustedNetSales > 0 ? ($netProfit / $adjustedNetSales) * 100 : 0;

// ==========================================
// 2. BALANCE SHEET (FINANCIAL POSITION)
// ==========================================

// Current Cash in open register + historical closed cash
$currentCash = $pdo->query("SELECT COALESCE(SUM(opening_balance + total_sales_cash + total_payments_received - total_expenses_paid - total_supplier_paid - total_refunds_paid), 0)
                            FROM cash_registers WHERE status = 'open'")->fetchColumn();

// Accounts Receivable (Customer Khata)
$accountsReceivable = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE status = 'active'")->fetchColumn();

// Inventory Value at Cost
$inventoryCostValue = $pdo->query("SELECT COALESCE(SUM(stock_quantity * purchase_price), 0) FROM product_variants WHERE status = 'active'")->fetchColumn();

$totalAssets = floatval($currentCash) + floatval($accountsReceivable) + floatval($inventoryCostValue);

// Accounts Payable (Supplier Khata)
$accountsPayable = $pdo->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE status = 'active'")->fetchColumn();
$totalLiabilities = floatval($accountsPayable);

// Net Equity
$ownersEquity = $totalAssets - $totalLiabilities;

// ==========================================
// 3. CATEGORY & BRAND PROFITABILITY
// ==========================================
$stmtCatProf = $pdo->prepare("SELECT 
    COALESCE(c.name, 'Uncategorized') AS category_name,
    COUNT(DISTINCT si.product_id) AS products_count,
    SUM(si.quantity) AS total_qty_sold,
    SUM(si.total_price) AS total_revenue,
    SUM(si.quantity * si.cost_price) AS total_cogs,
    (SUM(si.total_price) - SUM(si.quantity * si.cost_price)) AS gross_profit
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.sale_status = 'completed'
    GROUP BY c.id
    ORDER BY total_revenue DESC");
$stmtCatProf->execute([$start_date, $end_date]);
$categoryProfits = $stmtCatProf->fetchAll();

// ==========================================
// 4. CASH FLOW STATEMENT
// ==========================================
$stmtCashIn = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM sale_payments sp
    JOIN sales s ON sp.sale_id = s.id
    WHERE sp.payment_method = 'cash' AND DATE(s.sale_date) BETWEEN ? AND ?");
$stmtCashIn->execute([$start_date, $end_date]);
$cashSalesIn = floatval($stmtCashIn->fetchColumn());

$stmtKhataIn = $pdo->prepare("SELECT COALESCE(SUM(credit), 0) FROM customer_ledger 
    WHERE transaction_type = 'payment' AND DATE(entry_date) BETWEEN ? AND ?");
$stmtKhataIn->execute([$start_date, $end_date]);
$khataCashIn = floatval($stmtKhataIn->fetchColumn());

$totalCashInflow = $cashSalesIn + $khataCashIn;

$stmtExpOut = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses 
    WHERE payment_method = 'cash' AND DATE(expense_date) BETWEEN ? AND ?");
$stmtExpOut->execute([$start_date, $end_date]);
$expenseCashOut = floatval($stmtExpOut->fetchColumn());

$stmtSupOut = $pdo->prepare("SELECT COALESCE(SUM(debit), 0) FROM supplier_ledger 
    WHERE transaction_type = 'payment' AND DATE(entry_date) BETWEEN ? AND ?");
$stmtSupOut->execute([$start_date, $end_date]);
$supplierCashOut = floatval($stmtSupOut->fetchColumn());

$stmtRefundOut = $pdo->prepare("SELECT COALESCE(SUM(refund_amount), 0) FROM sales_returns 
    WHERE refund_method = 'cash' AND DATE(return_date) BETWEEN ? AND ?");
$stmtRefundOut->execute([$start_date, $end_date]);
$refundCashOut = floatval($stmtRefundOut->fetchColumn());

$totalCashOutflow = $expenseCashOut + $supplierCashOut + $refundCashOut;
$netCashFlow = $totalCashInflow - $totalCashOutflow;
?>

<!-- Header & Date Filter Controls -->
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4 no-print">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="fw-bold mb-0">Financial & Accounting Intelligence</h5>
            <small class="text-muted">Standard GAAP / IAS retail accounting reports</small>
        </div>
        <form method="GET" action="reports.php" class="d-flex flex-wrap align-items-center gap-2">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
            <div class="input-group input-group-sm" style="width: 170px;">
                <span class="input-group-text">From</span>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="input-group input-group-sm" style="width: 170px;">
                <span class="input-group-text">To</span>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-filter"></i> Apply Period</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </form>
    </div>
</div>

<!-- Report Navigation Tabs -->
<div class="card border-0 shadow-sm rounded-3 p-2 mb-4 bg-white no-print">
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'pnl' ? 'active' : '' ?>" href="reports.php?tab=pnl&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="bi bi-graph-up me-1"></i> Profit & Loss (Income Statement)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'balance' ? 'active' : '' ?>" href="reports.php?tab=balance&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="bi bi-bank me-1"></i> Balance Sheet (Financial Position)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'cashflow' ? 'active' : '' ?>" href="reports.php?tab=cashflow&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="bi bi-arrow-down-up me-1"></i> Cash Flow Statement
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'categories' ? 'active' : '' ?>" href="reports.php?tab=categories&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="bi bi-pie-chart me-1"></i> Category Profitability
            </a>
        </li>
    </ul>
</div>

<!-- ========================================== -->
<!-- 1. PROFIT & LOSS STATEMENT TAB -->
<!-- ========================================== -->
<?php if ($active_tab === 'pnl'): ?>
<div class="card border-0 shadow-sm rounded-3 p-4 print-area">
    <div class="text-center border-bottom pb-3 mb-4">
        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($settings['shop_name'] ?? 'Clothes Retail ERP') ?></h4>
        <h5 class="fw-semibold text-primary mb-1">PROFIT & LOSS STATEMENT (INCOME STATEMENT)</h5>
        <div class="text-muted small">For the period: <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?></div>
    </div>

    <div class="table-responsive">
        <table class="table table-borderless align-middle mb-0" style="font-size: 14px;">
            <tbody>
                <!-- REVENUE SECTION -->
                <tr class="table-light">
                    <td colspan="2"><strong class="text-uppercase text-dark"><i class="bi bi-cash me-1 text-primary"></i> 1. Operating Revenue (Sales)</strong></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Gross Sales (Retail Billing)</td>
                    <td class="text-end"><?= format_currency($salesSummary['gross_sales']) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4 text-muted">Less: Item & Bill Discounts Granted</td>
                    <td class="text-end text-danger">-<?= format_currency($salesSummary['total_discounts']) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4 text-muted">Less: Sales Returns & Refunds</td>
                    <td class="text-end text-danger">-<?= format_currency($totalReturns) ?></td>
                    <td></td>
                </tr>
                <tr class="border-top fw-bold">
                    <td class="ps-3 text-dark">NET OPERATING REVENUE</td>
                    <td></td>
                    <td class="text-end fs-6 text-primary"><?= format_currency($adjustedNetSales) ?></td>
                </tr>

                <!-- COGS SECTION -->
                <tr class="table-light">
                    <td colspan="2"><strong class="text-uppercase text-dark mt-3 d-inline-block"><i class="bi bi-box-seam me-1 text-info"></i> 2. Cost of Goods Sold (COGS)</strong></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ps-4">Cost of Garments Sold (Variant Purchase Basis)</td>
                    <td class="text-end text-danger"><?= format_currency($totalCOGS) ?></td>
                    <td></td>
                </tr>
                <tr class="border-top fw-bold">
                    <td class="ps-3 text-dark">TOTAL COST OF GOODS SOLD</td>
                    <td></td>
                    <td class="text-end text-danger fs-6"><?= format_currency($totalCOGS) ?></td>
                </tr>

                <!-- GROSS PROFIT -->
                <tr class="table-primary fw-bolder fs-5 border-top border-bottom">
                    <td>GROSS OPERATING PROFIT</td>
                    <td class="text-center text-muted fs-6 fw-normal"><?= round($grossMargin, 1) ?>% Margin</td>
                    <td class="text-end text-success"><?= format_currency($grossProfit) ?></td>
                </tr>

                <!-- OPERATING EXPENSES -->
                <tr class="table-light">
                    <td colspan="2"><strong class="text-uppercase text-dark mt-3 d-inline-block"><i class="bi bi-wallet2 me-1 text-danger"></i> 3. Operating Expenses</strong></td>
                    <td></td>
                </tr>
                <?php foreach ($expenseBreakdown as $eb): ?>
                    <?php if ($eb['cat_total'] > 0): ?>
                    <tr>
                        <td class="ps-4"><?= htmlspecialchars($eb['category_name']) ?></td>
                        <td class="text-end text-muted"><?= format_currency($eb['cat_total']) ?></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <tr class="border-top fw-bold">
                    <td class="ps-3 text-dark">TOTAL OPERATING EXPENSES</td>
                    <td></td>
                    <td class="text-end text-danger fs-6">-<?= format_currency($totalExpenses) ?></td>
                </tr>

                <!-- NET PROFIT -->
                <tr class="<?= $netProfit >= 0 ? 'table-success' : 'table-danger' ?> fw-bolder fs-4 border-top border-bottom">
                    <td>NET OPERATING PROFIT (EBITDA)</td>
                    <td class="text-center text-muted fs-6 fw-normal"><?= round($netMargin, 1) ?>% Net Margin</td>
                    <td class="text-end <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= format_currency($netProfit) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================== -->
<!-- 2. BALANCE SHEET TAB -->
<!-- ========================================== -->
<?php elseif ($active_tab === 'balance'): ?>
<div class="card border-0 shadow-sm rounded-3 p-4 print-area">
    <div class="text-center border-bottom pb-3 mb-4">
        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($settings['shop_name'] ?? 'Clothes Retail ERP') ?></h4>
        <h5 class="fw-semibold text-primary mb-1">BALANCE SHEET (STATEMENT OF FINANCIAL POSITION)</h5>
        <div class="text-muted small">As of: <?= date('d M Y') ?></div>
    </div>

    <div class="row g-4">
        <!-- ASSETS -->
        <div class="col-12 col-md-6 border-end">
            <h6 class="fw-bold text-success border-bottom pb-2 mb-3 text-uppercase"><i class="bi bi-plus-circle me-1"></i> Current Assets</h6>
            <table class="table table-borderless">
                <tr>
                    <td>Cash in Drawers & Registers</td>
                    <td class="text-end fw-semibold"><?= format_currency($currentCash) ?></td>
                </tr>
                <tr>
                    <td>Accounts Receivable (Customer Khata Dues)</td>
                    <td class="text-end fw-semibold"><?= format_currency($accountsReceivable) ?></td>
                </tr>
                <tr>
                    <td>Merchandise Inventory (at Cost Value)</td>
                    <td class="text-end fw-semibold"><?= format_currency($inventoryCostValue) ?></td>
                </tr>
                <tr class="border-top border-bottom table-light fw-bold fs-6">
                    <td>TOTAL ASSETS</td>
                    <td class="text-end text-success"><?= format_currency($totalAssets) ?></td>
                </tr>
            </table>
        </div>

        <!-- LIABILITIES & EQUITY -->
        <div class="col-12 col-md-6">
            <h6 class="fw-bold text-danger border-bottom pb-2 mb-3 text-uppercase"><i class="bi bi-dash-circle me-1"></i> Liabilities & Owner's Equity</h6>
            <table class="table table-borderless">
                <tr>
                    <td>Accounts Payable (Supplier Khata Dues)</td>
                    <td class="text-end fw-semibold text-danger"><?= format_currency($accountsPayable) ?></td>
                </tr>
                <tr class="border-top table-light fw-bold">
                    <td>TOTAL LIABILITIES</td>
                    <td class="text-end text-danger"><?= format_currency($totalLiabilities) ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="pt-3">
                        <h6 class="fw-bold text-dark text-uppercase">Owner's Equity</h6>
                    </td>
                </tr>
                <tr>
                    <td>Retained Capital & Net Worth (Assets - Liabilities)</td>
                    <td class="text-end fw-bold text-primary"><?= format_currency($ownersEquity) ?></td>
                </tr>
                <tr class="border-top border-bottom table-light fw-bold fs-6">
                    <td>TOTAL LIABILITIES & EQUITY</td>
                    <td class="text-end text-primary"><?= format_currency($totalLiabilities + $ownersEquity) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 3. CASH FLOW STATEMENT TAB -->
<!-- ========================================== -->
<?php elseif ($active_tab === 'cashflow'): ?>
<div class="card border-0 shadow-sm rounded-3 p-4 print-area">
    <div class="text-center border-bottom pb-3 mb-4">
        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($settings['shop_name'] ?? 'Clothes Retail ERP') ?></h4>
        <h5 class="fw-semibold text-primary mb-1">CASH FLOW STATEMENT</h5>
        <div class="text-muted small">Period: <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?></div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Cash Flow Stream</th>
                    <th style="width: 200px;" class="text-end">Inflow (+)</th>
                    <th style="width: 200px;" class="text-end">Outflow (-)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Direct POS Cash Sales</td>
                    <td class="text-end text-success fw-bold"><?= format_currency($cashSalesIn) ?></td>
                    <td>-</td>
                </tr>
                <tr>
                    <td>Customer Khata Due Payments Collected</td>
                    <td class="text-end text-success fw-bold"><?= format_currency($khataCashIn) ?></td>
                    <td>-</td>
                </tr>
                <tr>
                    <td>Store Operating Expenses Paid in Cash</td>
                    <td>-</td>
                    <td class="text-end text-danger fw-bold"><?= format_currency($expenseCashOut) ?></td>
                </tr>
                <tr>
                    <td>Supplier Khata Payments Paid in Cash</td>
                    <td>-</td>
                    <td class="text-end text-danger fw-bold"><?= format_currency($supplierCashOut) ?></td>
                </tr>
                <tr>
                    <td>Customer Returns Refunded in Cash</td>
                    <td>-</td>
                    <td class="text-end text-danger fw-bold"><?= format_currency($refundCashOut) ?></td>
                </tr>
                <tr class="table-light fw-bold">
                    <td>TOTAL CASH MOVEMENTS</td>
                    <td class="text-end text-success fs-6"><?= format_currency($totalCashInflow) ?></td>
                    <td class="text-end text-danger fs-6"><?= format_currency($totalCashOutflow) ?></td>
                </tr>
                <tr class="<?= $netCashFlow >= 0 ? 'table-success' : 'table-danger' ?> fw-bolder fs-5">
                    <td>NET CASH POSITION CHANGE</td>
                    <td colspan="2" class="text-end <?= $netCashFlow >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $netCashFlow >= 0 ? '+' : '' ?><?= format_currency($netCashFlow) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================== -->
<!-- 4. CATEGORY PROFITABILITY TAB -->
<!-- ========================================== -->
<?php elseif ($active_tab === 'categories'): ?>
<div class="card border-0 shadow-sm rounded-3 p-4 print-area">
    <div class="text-center border-bottom pb-3 mb-4">
        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($settings['shop_name'] ?? 'Clothes Retail ERP') ?></h4>
        <h5 class="fw-semibold text-primary mb-1">CLOTHING CATEGORY PROFITABILITY MATRIX</h5>
        <div class="text-muted small">Period: <?= date('d M Y', strtotime($start_date)) ?> to <?= date('d M Y', strtotime($end_date)) ?></div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Clothing Category</th>
                    <th class="text-center">Units Sold</th>
                    <th class="text-end">Total Revenue</th>
                    <th class="text-end">COGS (Cost)</th>
                    <th class="text-end">Gross Profit</th>
                    <th class="text-center">Gross Margin</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categoryProfits)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No category sales in this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($categoryProfits as $cp): 
                        $margin = $cp['total_revenue'] > 0 ? ($cp['gross_profit'] / $cp['total_revenue']) * 100 : 0;
                    ?>
                        <tr>
                            <td><strong class="text-dark"><?= htmlspecialchars($cp['category_name']) ?></strong></td>
                            <td class="text-center fw-bold"><?= number_format($cp['total_qty_sold']) ?></td>
                            <td class="text-end fw-bold"><?= format_currency($cp['total_revenue']) ?></td>
                            <td class="text-end text-muted"><?= format_currency($cp['total_cogs']) ?></td>
                            <td class="text-end text-success fw-bold"><?= format_currency($cp['gross_profit']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success border border-success">
                                    <?= round($margin, 1) ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
