<?php
// pos.php - High-Speed POS & Billing Engine with Mobile First UI
$page_title = 'POS Checkout & Billing';
require_once __DIR__ . '/includes/header.php';

// Fetch Categories for quick filter buttons
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Fetch Active Customers for Khata selection
$customers = $pdo->query("SELECT id, customer_code, name, phone, current_balance, credit_limit FROM customers WHERE status = 'active' ORDER BY id ASC")->fetchAll();

// Fetch tax rate setting
$tax_percent = floatval(get_setting($pdo, 'tax_percent', '0'));
?>

<!-- Mobile View Switcher Tabs (Visible on screens < 992px) -->
<div class="d-lg-none mb-3 no-print">
    <ul class="nav nav-pills nav-fill pos-mobile-nav-tabs bg-white p-1 rounded-3 shadow-sm border" id="posMobileTabs">
        <li class="nav-item">
            <button class="nav-link active" id="tabMobileProducts" onclick="switchMobileView('products')">
                <i class="bi bi-grid me-1"></i> Garments
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link position-relative" id="tabMobileCart" onclick="switchMobileView('cart')">
                <i class="bi bi-cart3 me-1"></i> Cart (<span id="mobileCartBadge">0</span>)
                <span class="badge bg-primary text-white ms-1" id="mobileCartTotalBadge">Rs. 0</span>
            </button>
        </li>
    </ul>
</div>

<div class="row g-3">
    <!-- LEFT: Product Search, Category Filter & Product Grid -->
    <div class="col-12 col-lg-7 col-xl-7" id="posProductsColumn">
        <!-- VIEW 1: Main Product Catalogue (Search, Categories & Grid) -->
        <div id="posCatalogueMainView">
            <div class="card border-0 shadow-sm rounded-3 p-2 p-md-3 mb-3">
                <div class="row g-2 align-items-stretch">
                    <!-- Barcode & Product Search Input with Camera Scanner Button -->
                    <div class="col-12 col-md-7 col-xl-8">
                        <div class="input-group h-100">
                            <span class="input-group-text bg-white border-end-0 text-primary">
                                <i class="bi bi-upc-scan fs-5"></i>
                            </span>
                            <input type="text" id="posSearchInput" class="form-control border-start-0 ps-0" 
                                   placeholder="Scan Barcode or Search Garment... [F2]" autofocus autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Clear Search">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <button class="btn btn-primary px-3 d-flex align-items-center gap-1 shadow-sm text-nowrap" type="button" id="openScannerBtn" title="Scan Barcode via Camera [F3]">
                                <i class="bi bi-camera-fill"></i>
                                <span class="fw-semibold">Scan</span>
                            </button>
                        </div>
                    </div>

                    <!-- Hold & Resume Button -->
                    <div class="col-12 col-md-5 col-xl-4">
                        <div class="d-flex gap-2 h-100">
                            <button class="btn btn-warning flex-fill d-flex align-items-center justify-content-center gap-1 text-nowrap fw-semibold shadow-sm py-2" id="holdSaleBtn" title="Hold Current Sale [F4]">
                                <i class="bi bi-pause-circle"></i> <span>Hold</span>
                            </button>
                            <button class="btn btn-outline-dark flex-fill d-flex align-items-center justify-content-center gap-1 text-nowrap fw-semibold shadow-sm py-2" id="resumeSaleBtn" data-bs-toggle="modal" data-bs-target="#heldSalesModal" title="View Held Sales">
                                <i class="bi bi-collection"></i> <span>Held</span> <span class="badge bg-dark text-white rounded-pill px-2 ms-1" id="heldCount">0</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Category Filter Pills (Horizontal scrollable touch container) -->
                <div class="d-flex gap-1 gap-md-2 overflow-auto py-2 mt-2" style="white-space: nowrap; -webkit-overflow-scrolling: touch;">
                    <button class="btn btn-sm btn-primary category-filter-btn active" data-id="0">All Items</button>
                    <?php foreach ($categories as $cat): ?>
                        <button class="btn btn-sm btn-outline-secondary category-filter-btn" data-id="<?= $cat['id'] ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Products Grid Container -->
            <div class="card border-0 shadow-sm rounded-3 p-2 p-md-3">
                <div id="productGridLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="text-muted small mt-2">Searching catalogue...</div>
                </div>

                <div class="row g-2 g-md-3" id="posProductsContainer" style="max-height: calc(100vh - 290px); overflow-y: auto;">
                    <!-- Product cards dynamically loaded here via AJAX -->
                </div>
            </div>
        </div>

        <!-- VIEW 2: Dedicated Variant & Color Picker Panel (Replaces variantSelectModal) -->
        <div id="posVariantPickerPanel" class="card border-0 shadow-sm rounded-3 p-3 bg-white" style="display: none;">
            <!-- Header with Back Button -->
            <div class="d-flex justify-content-between align-items-center pb-2 mb-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" id="closeVariantPanelBtn" onclick="closeVariantPickerPanel()">
                        <i class="bi bi-arrow-left me-1"></i> Back to Products (Esc)
                    </button>
                    <div id="variantPanelHeaderTitle" class="d-flex align-items-center gap-2">
                        <!-- Title, SKU, Rate badges populated dynamically -->
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-light border-0" onclick="closeVariantPickerPanel()" title="Back to Products">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Variant & Color Cards Grid -->
            <div class="row g-2 g-md-3" id="variantGridContainer" style="max-height: calc(100vh - 260px); overflow-y: auto;">
                <!-- Size & Color cards dynamically populated here -->
            </div>
        </div>
    </div>

    <!-- RIGHT: POS Cart, Customer Selection & Dedicated Checkout Panel -->
    <div class="col-12 col-lg-5 col-xl-5" id="posCartColumn">
        <div class="pos-cart-panel shadow-sm">
            <!-- VIEW 1: CART VIEW -->
            <div id="posCartView" class="d-flex flex-column h-100">
                <!-- Rate Tier Selector: Wholesale vs Retail -->
                <div class="px-3 py-2 bg-white border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-1">
                        <span class="small fw-bold text-dark"><i class="bi bi-tag-fill text-primary me-1"></i> RATE TIER:</span>
                    </div>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="globalPricingTier" id="tierRetail" value="retail" checked autocomplete="off" onchange="setGlobalPricingTier('retail')">
                        <label class="btn btn-outline-primary fw-bold px-3 py-1" for="tierRetail" title="Retail Rates [F6]">
                            🛒 Retail (F6)
                        </label>

                        <input type="radio" class="btn-check" name="globalPricingTier" id="tierWholesale" value="wholesale" autocomplete="off" onchange="setGlobalPricingTier('wholesale')">
                        <label class="btn btn-outline-success fw-bold px-3 py-1" for="tierWholesale" title="Wholesale Rates [F7]">
                            📦 Wholesale (F7)
                        </label>
                    </div>
                </div>

                <!-- Customer Select Header -->
                <div class="p-2 p-md-3 border-bottom bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold text-muted mb-0">CUSTOMER (KHATA / CASH)</label>
                        <button class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#quickCustomerPanel" aria-expanded="false">
                            <i class="bi bi-person-plus"></i> + Quick Add
                        </button>
                    </div>
                    <select id="posCustomerSelect" class="form-select form-select-sm">
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-balance="<?= $c['current_balance'] ?>" data-limit="<?= $c['credit_limit'] ?>">
                                <?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>) <?= $c['current_balance'] > 0 ? ' [Khata: Rs. ' . number_format($c['current_balance']) . ']' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="customerCreditAlert" class="mt-1 small text-danger d-none fw-semibold"></div>

                    <!-- Inline Dedicated Quick Add Customer Form -->
                    <div class="collapse mt-2" id="quickCustomerPanel">
                        <div class="card card-body p-2 border bg-white shadow-sm">
                            <h6 class="small fw-bold text-primary mb-2"><i class="bi bi-person-plus me-1"></i> Quick Register Khata Customer</h6>
                            <form id="quickCustomerForm" class="row g-2">
                                <div class="col-12">
                                    <input type="text" id="quickCustName" class="form-control form-control-sm" required placeholder="Full Name (e.g. Haji Munir)">
                                </div>
                                <div class="col-6">
                                    <input type="text" id="quickCustPhone" class="form-control form-control-sm" required placeholder="Mobile 0300-1234567">
                                </div>
                                <div class="col-6">
                                    <input type="number" id="quickCustLimit" class="form-control form-control-sm" value="25000" placeholder="Credit Limit">
                                </div>
                                <div class="col-12 d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#quickCustomerPanel">Cancel</button>
                                    <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold">Save & Select</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- LIVE INLINE CAMERA SCANNER ELEMENT (ABOVE CART WITH ANIMATION) -->
                <div id="posCameraScannerContainer" class="scanner-inline-container border-bottom bg-dark text-white" style="display: none;">
                    <div class="p-2 d-flex justify-content-between align-items-center border-bottom border-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <span class="spinner-grow spinner-grow-sm text-danger" role="status"></span>
                            <span class="small fw-bold text-warning"><i class="bi bi-camera-video me-1"></i> Live Barcode Camera Scanner</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-outline-light btn-sm py-0 px-2" id="switchCameraBtn" title="Switch Front/Back Camera">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger py-0 px-2 fw-bold" id="closeScannerBtn" title="Close Camera (Esc)">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="p-2 bg-black position-relative">
                        <div class="scanner-viewport-wrapper">
                            <video id="scannerVideo" autoplay playsinline muted></video>
                            <canvas id="scannerCanvas" style="display: none;"></canvas>

                            <span class="badge bg-dark bg-opacity-75 text-white scanner-floating-badge" id="scannerStatusBadge">
                                <i class="bi bi-record-circle text-danger me-1"></i> Initializing camera...
                            </span>
                            <span class="badge bg-primary scanner-zoom-badge" id="scannerZoomBadge">
                                1.0x Zoom
                            </span>

                            <div class="scanner-reticle" id="scannerReticle">
                                <div class="scanner-corner corner-tl"></div>
                                <div class="scanner-corner corner-tr"></div>
                                <div class="scanner-laser" id="scannerLaser"></div>
                                <div class="scanner-corner corner-bl"></div>
                                <div class="scanner-corner corner-br"></div>
                            </div>
                        </div>

                        <!-- Last Scanned Banner -->
                        <div id="lastScannedBanner" class="alert alert-success d-none py-1 px-2 small mt-2 mb-1 d-flex justify-content-between align-items-center">
                            <div class="text-truncate me-2">
                                <i class="bi bi-check-circle-fill me-1 text-success"></i> 
                                <strong id="lastScannedName">Garment</strong>
                                <span class="text-muted ms-1" id="lastScannedVariant"></span>
                            </div>
                            <strong class="text-success text-nowrap" id="lastScannedPrice">Rs. 0</strong>
                        </div>

                        <!-- Quick Scanner Controls Toolbar -->
                        <div class="d-flex justify-content-between align-items-center pt-2 px-1 text-white small">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="autoZoomSwitch" checked>
                                <label class="form-check-label text-white-50" for="autoZoomSwitch" style="font-size: 0.72rem;">
                                    Auto-Zoom & Align
                                </label>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-outline-light btn-sm py-0 px-2 zoom-btn active" data-zoom="1.0" style="font-size: 0.7rem;">1x</button>
                                <button type="button" class="btn btn-outline-light btn-sm py-0 px-2 zoom-btn" data-zoom="2.0" style="font-size: 0.7rem;">2x</button>
                                <button type="button" class="btn btn-outline-light btn-sm py-0 px-2 zoom-btn" data-zoom="3.0" style="font-size: 0.7rem;">3x</button>
                                <button type="button" class="btn btn-outline-warning btn-sm py-0 px-2" id="torchToggleBtn" style="display: none; font-size: 0.7rem;">
                                    <i class="bi bi-lightbulb"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart Table Items -->
                <div class="pos-cart-items px-2 py-1" style="min-height: 220px; max-height: calc(100vh - 460px); overflow-x: hidden; overflow-y: auto;">
                    <table class="table table-sm align-middle mb-0 pos-cart-table" id="cartTable">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th>Item</th>
                                <th style="width: 76px;" class="text-center">Qty</th>
                                <th style="width: 58px;" class="text-end">Price</th>
                                <th style="width: 62px;" class="text-end">Total</th>
                                <th style="width: 22px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cartTableBody">
                            <tr id="emptyCartRow">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-cart3 display-6 d-block text-secondary mb-2"></i>
                                    Cart is empty<br>
                                    <small>Scan barcode or tap a garment card</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Cart Totals & Checkout Trigger -->
                <div class="p-3 bg-light border-top mt-auto">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Subtotal:</span>
                        <strong id="cartSubtotal" class="text-dark">Rs. 0</strong>
                    </div>

                    <div class="row g-2 align-items-center mb-1">
                        <div class="col-6 small text-muted">
                            <span>Discount:</span>
                        </div>
                        <div class="col-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Rs.</span>
                                <input type="number" id="cartBillDiscount" class="form-control text-end" value="0" min="0">
                            </div>
                        </div>
                    </div>

                    <?php if ($tax_percent > 0): ?>
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Tax (<?= $tax_percent ?>%):</span>
                        <strong id="cartTax" class="text-dark">Rs. 0</strong>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom my-2">
                        <span class="fs-5 fw-bold text-dark">Net Payable:</span>
                        <span class="fs-4 fw-bolder text-primary" id="cartNetTotal">Rs. 0</span>
                    </div>

                    <div class="row g-2">
                        <div class="col-4">
                            <button class="btn btn-outline-danger w-100 btn-sm py-2" id="clearCartBtn">
                                <i class="bi bi-trash"></i> Clear
                            </button>
                        </div>
                        <div class="col-8">
                            <button class="btn btn-success w-100 py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" id="openPayModalBtn">
                                <i class="bi bi-credit-card fs-5"></i> Pay & Bill (F8)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW 2: DEDICATED INLINE CHECKOUT & PAYMENT FORM (REPLACES CHECKOUT MODAL) -->
            <div id="posCheckoutView" class="d-flex flex-column h-100 bg-white" style="display: none !important;">
                <!-- Header with Back to Cart -->
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="backToCartBtn" title="Back to Cart (Esc)">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-credit-card-2-front text-primary me-1"></i> Checkout & Payment</h6>
                    </div>
                    <span class="badge bg-primary-subtle text-primary fw-bold px-2 py-1" id="checkoutItemCountBadge">0 Items</span>
                </div>

                <!-- Form Body -->
                <div class="p-3 flex-grow-1 overflow-auto" style="max-height: calc(100vh - 280px);">
                    <!-- Net Payable Card -->
                    <div class="text-center p-3 mb-3 bg-primary-subtle rounded-3 border border-primary-subtle">
                        <span class="text-muted small text-uppercase fw-semibold d-block">Total Amount Payable</span>
                        <h2 class="fw-bolder text-primary mb-1" id="modalNetPayable">Rs. 0</h2>
                        <div class="small text-muted" id="checkoutCustomerSummary">Customer: Walk-in Client</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">PAYMENT METHOD</label>
                        <select id="checkoutMethodSelect" class="form-select">
                            <option value="cash" selected>Cash</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">Easypaisa</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="card">Card / POS Terminal</option>
                            <option value="split">Split Payment (Multiple)</option>
                        </select>
                    </div>

                    <!-- Split Payment Form -->
                    <div id="splitPaymentSection" class="p-3 bg-light rounded-3 mb-3 d-none border">
                        <label class="form-label small fw-bold text-muted mb-2">SPLIT PAYMENT BREAKDOWN</label>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <span class="small fw-semibold">Cash:</span>
                                <input type="number" id="splitCash" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-6">
                                <span class="small fw-semibold">JazzCash:</span>
                                <input type="number" id="splitJazzCash" class="form-control form-control-sm" value="0">
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <span class="small fw-semibold">Easypaisa / Bank:</span>
                                <input type="number" id="splitDigital" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-6">
                                <span class="small fw-semibold">Card:</span>
                                <input type="number" id="splitCard" class="form-control form-control-sm" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Single Payment Cash Controls -->
                    <div id="singleCashSection" class="mb-3">
                        <label class="form-label small fw-bold text-muted">CASH RECEIVED FROM CUSTOMER</label>
                        <input type="number" id="cashReceivedInput" class="form-control form-control-lg text-end fw-bold" placeholder="0">
                        <div class="d-flex gap-1 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 quick-cash-btn" data-val="exact">Exact</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 quick-cash-btn" data-val="1000">1,000</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 quick-cash-btn" data-val="2000">2,000</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1 quick-cash-btn" data-val="5000">5,000</button>
                        </div>
                    </div>

                    <!-- Change & Balance Live Display -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="p-2 border rounded text-center bg-light">
                                <small class="text-muted d-block">Change Return</small>
                                <strong class="text-success fs-5" id="changeReturnDisplay">Rs. 0</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded text-center bg-light">
                                <small class="text-muted d-block">Khata Balance Due</small>
                                <strong class="text-danger fs-5" id="balanceDueDisplay">Rs. 0</strong>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">SALE NOTES / MEMO (OPTIONAL)</label>
                        <input type="text" id="checkoutNotes" class="form-control form-control-sm" placeholder="e.g. Special customer discount approved">
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="p-3 bg-light border-top mt-auto">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary btn-lg fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2" id="confirmCheckoutThermalBtn">
                            <i class="bi bi-printer fs-5"></i> Complete & Print Thermal (Enter)
                        </button>
                        <button type="button" class="btn btn-outline-dark btn-sm py-2" id="confirmCheckoutA4Btn">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Complete & Print A4 Invoice
                        </button>
                        <button type="button" class="btn btn-link text-muted btn-sm text-decoration-none" id="cancelCheckoutBtn">
                            ← Return to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Floating Cart Sticky Bar (Visible only when cart has items on screen < 992px) -->
<div class="mobile-cart-sticky-bar d-lg-none d-none no-print" id="mobileCartStickyBar">
    <div>
        <span class="small text-white-50 d-block" style="font-size: 0.7rem;">Cart: <span id="stickyItemCount">0</span> items</span>
        <strong class="fs-6 text-white" id="stickyTotalVal">Rs. 0</strong>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-light" onclick="switchMobileView('cart')">
            <i class="bi bi-cart3"></i> View Cart
        </button>
        <button class="btn btn-sm btn-success fw-bold px-3" onclick="openCheckoutView()">
            Checkout <i class="bi bi-arrow-right"></i>
        </button>
    </div>
</div>





<!-- ========================================== -->
<!-- MODAL: Held Sales (Resume Sale) -->
<!-- ========================================== -->
<div class="modal fade" id="heldSalesModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-pause-circle me-1"></i> Held Sales (Parked Carts)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Hold ID</th>
                                <th>Time</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="heldSalesTableBody">
                            <tr><td colspan="6" class="text-center py-4 text-muted">No held sales.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>




<!-- Include ZXing for 100% offline-ready barcode scanning fallback -->
<script src="assets/js/zxing.min.js"></script>
<!-- Include POS JavaScript Engine -->
<script src="assets/js/pos-engine.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
