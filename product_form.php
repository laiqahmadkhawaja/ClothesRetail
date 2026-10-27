<?php
// product_form.php - Dedicated Full-Page Form for Adding and Editing Products (Garments & Open Fabrics/Thaan)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_role('admin', 'manager', 'inventory');

$id = intval($_GET['id'] ?? 0);
$is_edit = $id > 0;
$product = null;

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        $_SESSION['flash_error'] = "Product #$id not found.";
        header("Location: products.php");
        exit;
    }
}

// Handle Form Submission (BEFORE header inclusion to allow redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['product_code'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    if (empty($barcode)) {
        $barcode = '89' . mt_rand(10000000, 99999999);
    }
    $name = trim($_POST['name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0) ?: null;
    $brand_id = intval($_POST['brand_id'] ?? 0) ?: null;
    $item_type = in_array($_POST['item_type'] ?? '', ['readymade', 'fabric']) ? $_POST['item_type'] : 'readymade';
    $unit_type = trim($_POST['unit_type'] ?? ($item_type === 'fabric' ? 'meter' : 'piece'));
    $meters_per_suit = floatval($_POST['meters_per_suit'] ?? 0);
    $thaan_length = floatval($_POST['thaan_length'] ?? 0);
    $season = trim($_POST['season'] ?? 'All Season');
    $rack = trim($_POST['rack_location'] ?? '');
    $alert_qty = floatval($_POST['alert_quantity'] ?? 5);
    $status = $_POST['status'] ?? 'active';
    $desc = trim($_POST['description'] ?? '');

    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $wholesale_price = floatval($_POST['wholesale_price'] ?? 0);

    $save_and_matrix = isset($_POST['save_and_matrix']);

    if (empty($code) || empty($name)) {
        $_SESSION['flash_error'] = 'Product Code and Name are required.';
    } else {
        try {
            if ($is_edit) {
                // Update Product
                $stmt = $pdo->prepare("UPDATE products SET 
                    barcode = ?, name = ?, category_id = ?, brand_id = ?, 
                    item_type = ?, unit_type = ?, meters_per_suit = ?, thaan_length = ?,
                    season = ?, rack_location = ?, description = ?, 
                    cost_price = ?, selling_price = ?, wholesale_price = ?, 
                    alert_quantity = ?, status = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $barcode, $name, $category_id, $brand_id,
                    $item_type, $unit_type, $meters_per_suit, $thaan_length,
                    $season, $rack, $desc,
                    $cost_price, $selling_price, $wholesale_price,
                    $alert_qty, $status, $id
                ]);

                // Update default variant price if 1 variant exists or was 0
                $stmtUpdVar = $pdo->prepare("UPDATE product_variants SET purchase_price = ?, selling_price = ?, wholesale_price = ? WHERE product_id = ? AND (selling_price = 0 OR (SELECT COUNT(*) FROM (SELECT id FROM product_variants WHERE product_id = ?) as tmp) = 1)");
                $stmtUpdVar->execute([$cost_price, $selling_price, $wholesale_price, $id, $id]);

                log_audit($pdo, 'Update Product', 'Catalog', "Updated product #$id: $name ($code)");
                $_SESSION['flash_success'] = "Product '$name' updated successfully!";
                
                if ($save_and_matrix) {
                    header("Location: product_matrix.php?product_id=$id");
                } else {
                    header("Location: products.php");
                }
                exit;

            } else {
                // Create New Product
                $initial_stock = floatval($_POST['initial_stock'] ?? 0);
                $default_size_id = intval($_POST['default_size_id'] ?? 2);
                $default_color_id = intval($_POST['default_color_id'] ?? 1);

                $stmt = $pdo->prepare("INSERT INTO products 
                    (product_code, barcode, name, category_id, brand_id, item_type, unit_type, meters_per_suit, thaan_length, season, rack_location, description, cost_price, selling_price, wholesale_price, alert_quantity, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $code, $barcode, $name, $category_id, $brand_id, 
                    $item_type, $unit_type, $meters_per_suit, $thaan_length,
                    $season, $rack, $desc, $cost_price, $selling_price, $wholesale_price, 
                    $alert_qty, $status
                ]);
                $new_id = $pdo->lastInsertId();

                // Automatically create initial variant for immediate POS readiness
                $sku = "PRD{$new_id}-S{$default_size_id}-C{$default_color_id}";
                $stmtVar = $pdo->prepare("INSERT INTO product_variants 
                    (product_id, size_id, color_id, sku, barcode, purchase_price, selling_price, wholesale_price, stock_quantity, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmtVar->execute([
                    $new_id, $default_size_id, $default_color_id, 
                    $sku, $barcode, $cost_price, $selling_price, $wholesale_price, $initial_stock
                ]);
                $new_var_id = $pdo->lastInsertId();

                if ($initial_stock > 0) {
                    $user = current_user();
                    $stmtMv = $pdo->prepare("INSERT INTO stock_movements 
                        (product_id, variant_id, movement_date, movement_type, reference_no, qty_in, qty_out, unit_cost, notes, created_by) 
                        VALUES (?, ?, NOW(), 'opening', 'INIT-STOCK', ?, 0, ?, 'Product Initial Setup', ?)");
                    $stmtMv->execute([$new_id, $new_var_id, $initial_stock, $cost_price, $user['id'] ?? 1]);
                }

                log_audit($pdo, 'Create Product', 'Catalog', "Created product: $name ($code, Barcode: $barcode, Type: $item_type)");
                $_SESSION['flash_success'] = "Product '$name' created successfully!";

                if ($save_and_matrix) {
                    header("Location: product_matrix.php?product_id=$new_id");
                } else {
                    header("Location: products.php");
                }
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Error: ' . (strpos($e->getMessage(), 'Duplicate') !== false ? 'Product code or barcode already exists!' : $e->getMessage());
        }
    }
}

$page_title = $is_edit ? "Edit Product: " . htmlspecialchars($product['name']) : "Add New Product / Fabric";
require_once __DIR__ . '/includes/header.php';

// Dropdown options
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
$sizes = $pdo->query("SELECT * FROM sizes ORDER BY sort_order ASC, id ASC")->fetchAll();
$colors = $pdo->query("SELECT * FROM colors ORDER BY name ASC")->fetchAll();
$seasons = ['All Season', 'Summer 2026', 'Winter 2026', 'Eid Collection', 'Festive / Wedding', 'Spring'];

// Determine values
$val_code = $is_edit ? $product['product_code'] : ('PRD-' . date('ym') . '-' . mt_rand(100, 999));
$val_barcode = $is_edit ? $product['barcode'] : ('89' . mt_rand(10000000, 99999999));
$val_name = $is_edit ? $product['name'] : '';
$val_category = $is_edit ? $product['category_id'] : '';
$val_brand = $is_edit ? $product['brand_id'] : '';
$val_item_type = $is_edit ? ($product['item_type'] ?? 'readymade') : 'readymade';
$val_unit_type = $is_edit ? ($product['unit_type'] ?? 'piece') : 'piece';
$val_meters_suit = $is_edit ? floatval($product['meters_per_suit'] ?? 4.5) : 4.5;
$val_thaan_len = $is_edit ? floatval($product['thaan_length'] ?? 35.0) : 35.0;
$val_season = $is_edit ? $product['season'] : 'All Season';
$val_rack = $is_edit ? $product['rack_location'] : '';
$val_cost = $is_edit ? floatval($product['cost_price']) : 0;
$val_sell = $is_edit ? floatval($product['selling_price']) : 0;
$val_wholesale = $is_edit ? floatval($product['wholesale_price']) : 0;
$val_alert = $is_edit ? floatval($product['alert_quantity']) : 5;
$val_status = $is_edit ? $product['status'] : 'active';
$val_desc = $is_edit ? $product['description'] : '';
?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <!-- Page Header & Action Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1 small">
                            <li class="breadcrumb-item"><a href="products.php" class="text-decoration-none">Products Catalog</a></li>
                            <li class="breadcrumb-item active"><?= $is_edit ? 'Edit Product' : 'Add Product' ?></li>
                        </ol>
                    </nav>
                    <h5 class="fw-bold mb-0">
                        <i class="bi <?= $is_edit ? 'bi-pencil-square text-warning' : 'bi-plus-circle text-primary' ?> me-2"></i>
                        <?= $is_edit ? 'Edit Product Details' : 'Add New Garment or Open Fabric (Thaan)' ?>
                    </h5>
                    <small class="text-muted">Dedicated form for Readymade garments (Pieces) and Open Unstitched Fabric (Meters / Suits / Thaans)</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="products.php" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-arrow-left me-1"></i> Back to Catalog
                    </a>
                    <?php if ($is_edit): ?>
                        <a href="product_matrix.php?product_id=<?= $id ?>" class="btn btn-outline-primary btn-sm px-3">
                            <i class="bi bi-grid-3x3-gap me-1"></i> Size/Color Matrix
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form method="POST" action="product_form.php<?= $is_edit ? '?id=' . $id : '' ?>" id="productForm">
            <!-- 1. System Item Type & Unit Mode (Garment vs Fabric) -->
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-sliders2 text-primary me-2"></i> Product Classification & Unit System
                </h6>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">ITEM TYPE / سسٹم کی قسم *</label>
                        <div class="d-flex gap-3 mt-1">
                            <div class="form-check form-check-inline p-3 border rounded-3 flex-fill bg-light" style="cursor: pointer;">
                                <input class="form-check-input" type="radio" name="item_type" id="typeReadymade" value="readymade" <?= $val_item_type === 'readymade' ? 'checked' : '' ?> onchange="toggleItemType('readymade')">
                                <label class="form-check-label fw-bold d-block" for="typeReadymade" style="cursor: pointer;">
                                    👗 Readymade Garment
                                    <small class="text-muted d-block fw-normal">Ready-to-wear shirts, trousers, suits sold by <strong>Piece</strong></small>
                                </label>
                            </div>
                            <div class="form-check form-check-inline p-3 border rounded-3 flex-fill bg-light" style="cursor: pointer;">
                                <input class="form-check-input" type="radio" name="item_type" id="typeFabric" value="fabric" <?= $val_item_type === 'fabric' ? 'checked' : '' ?> onchange="toggleItemType('fabric')">
                                <label class="form-check-label fw-bold d-block" for="typeFabric" style="cursor: pointer;">
                                    🧵 Open Fabric / Thaan (تھان / کھلا کپڑا)
                                    <small class="text-muted d-block fw-normal">Unstitched rolls sold by <strong>Meters, Yards/Gaz, or Suits</strong></small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">DEFAULT BASE UNIT *</label>
                        <select name="unit_type" id="unitTypeSelect" class="form-select form-select-lg">
                            <option value="piece" <?= $val_unit_type === 'piece' ? 'selected' : '' ?>>Piece (Pcs) - عدد</option>
                            <option value="meter" <?= $val_unit_type === 'meter' ? 'selected' : '' ?>>Meter (m) - میٹر</option>
                            <option value="yard" <?= $val_unit_type === 'yard' ? 'selected' : '' ?>>Yard / Gaz - گز</option>
                            <option value="suit" <?= $val_unit_type === 'suit' ? 'selected' : '' ?>>Suit - سوٹ</option>
                            <option value="thaan" <?= $val_unit_type === 'thaan' ? 'selected' : '' ?>>Thaan / Roll - تھان</option>
                        </select>
                        <small class="text-muted">Stock and sales base unit measurement.</small>
                    </div>

                    <!-- Fabric (Thaan) Specific Measurement Parameters -->
                    <div class="col-12 <?= $val_item_type === 'fabric' ? '' : 'd-none' ?>" id="fabricConfigSection">
                        <div class="p-3 bg-primary-subtle rounded-3 border border-primary-subtle">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark">
                                        METERS PER SUIT (سوٹ کا ناپ - میٹرز) *
                                    </label>
                                    <div class="input-group">
                                        <input type="number" step="0.05" name="meters_per_suit" id="metersPerSuitInput" class="form-control fw-bold" value="<?= $val_meters_suit ?>" placeholder="e.g. 4.5">
                                        <span class="input-group-text">Meters / Suit</span>
                                    </div>
                                    <small class="text-muted">Gents standard: 4.5m or 4.0m. Ladies Kurti: 2.5m.</small>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-bold text-dark">
                                        FULL THAAN ROLL LENGTH (تھان کی لمبائی)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" step="0.5" name="thaan_length" id="thaanLengthInput" class="form-control fw-bold" value="<?= $val_thaan_len ?>" placeholder="e.g. 35.0">
                                        <span class="input-group-text">Meters / Thaan</span>
                                    </div>
                                    <small class="text-muted">Average length of an unopened fabric roll/thaan.</small>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info py-2 px-3 mb-0 small" id="fabricLiveCalc">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <strong>Fabric Conversion Formula:</strong> 1 Suit = <span id="dispSuitMeters"><?= $val_meters_suit ?></span> meters. 1 Thaan (<span id="dispThaanMeters"><?= $val_thaan_len ?></span>m) gives approx <strong id="dispThaanSuits"><?= $val_meters_suit > 0 ? round($val_thaan_len / $val_meters_suit, 1) : 0 ?></strong> full suits.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Basic Identification & Categorization -->
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-info-circle text-primary me-2"></i> Basic Product Information
                </h6>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold">PRODUCT CODE / SKU *</label>
                        <div class="input-group">
                            <input type="text" name="product_code" id="productCodeInput" class="form-control font-monospace fw-bold" required value="<?= htmlspecialchars($val_code) ?>" <?= $is_edit ? 'readonly' : '' ?>>
                            <?php if (!$is_edit): ?>
                                <button class="btn btn-outline-secondary" type="button" onclick="generateCode()" title="Generate Random Code">
                                    <i class="bi bi-shuffle"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold">BARCODE (1D EAN / CODE128) *</label>
                        <div class="input-group">
                            <input type="text" name="barcode" id="barcodeInput" class="form-control font-monospace fw-bold" required value="<?= htmlspecialchars($val_barcode) ?>">
                            <button class="btn btn-outline-secondary" type="button" onclick="generateBarcode()" title="Generate 13-digit EAN">
                                <i class="bi bi-arrow-repeat"></i> Auto
                            </button>
                        </div>
                        <small class="text-muted">Scan manufacturer barcode or generate internal tag code.</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold">PRODUCT NAME / DESCRIPTION *</label>
                        <input type="text" name="name" class="form-control fw-bold" required placeholder="e.g. Boski Linen Suit / Cotton Lawn / Denim Shirt" value="<?= htmlspecialchars($val_name) ?>">
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-bold">CATEGORY</label>
                        <select name="category_id" class="form-select">
                            <option value="">Select Category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $val_category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-bold">BRAND / MANUFACTURER</label>
                        <select name="brand_id" class="form-select">
                            <option value="">Select Brand...</option>
                            <?php foreach ($brands as $br): ?>
                                <option value="<?= $br['id'] ?>" <?= $val_brand == $br['id'] ? 'selected' : '' ?>><?= htmlspecialchars($br['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-bold">SEASON / COLLECTION</label>
                        <select name="season" class="form-select">
                            <?php foreach ($seasons as $s): ?>
                                <option value="<?= $s ?>" <?= $val_season == $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-bold">RACK / SHELF LOCATION</label>
                        <input type="text" name="rack_location" class="form-control" placeholder="e.g. Rack A-3, Shelf 2" value="<?= htmlspecialchars($val_rack) ?>">
                    </div>
                </div>
            </div>

            <!-- 3. Pricing Tiers & Inventory Settings -->
            <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="bi bi-cash-stack text-success me-2"></i> Pricing & Inventory Rates
                </h6>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold text-muted">PURCHASE / COST PRICE (RS.)</label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" name="cost_price" id="costPriceInput" class="form-control text-end fw-bold" value="<?= $val_cost ?>" oninput="recalcMargins()">
                        </div>
                        <small class="text-muted">Supplier cost per unit (per piece or per meter).</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold text-primary">RETAIL SELLING PRICE (RS.) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary-subtle text-primary fw-bold">Rs.</span>
                            <input type="number" step="0.01" name="selling_price" id="sellingPriceInput" class="form-control text-end fw-bold text-primary" required value="<?= $val_sell ?>" oninput="recalcMargins()">
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Counter Retail Rate</span>
                            <span id="retailMarginBadge" class="badge bg-success-subtle text-success">Margin: 0%</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold text-success">WHOLESALE PRICE (RS.) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-success-subtle text-success fw-bold">Rs.</span>
                            <input type="number" step="0.01" name="wholesale_price" id="wholesalePriceInput" class="form-control text-end fw-bold text-success" required value="<?= $val_wholesale ?>" oninput="recalcMargins()">
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Bulk / Reseller Rate</span>
                            <span id="wholesaleMarginBadge" class="badge bg-info-subtle text-info">Margin: 0%</span>
                        </div>
                    </div>

                    <!-- Calculated Suit Price Preview (For Fabric Type) -->
                    <div class="col-12 <?= $val_item_type === 'fabric' ? '' : 'd-none' ?>" id="suitPricingRow">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="row text-center">
                                <div class="col-6 border-end">
                                    <small class="text-muted d-block">Retail Suit Price (<span class="unitSuitMeters"><?= $val_meters_suit ?></span>m)</small>
                                    <h5 class="fw-bold text-primary mb-0" id="dispRetailSuitPrice">Rs. 0</h5>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Wholesale Suit Price (<span class="unitSuitMeters"><?= $val_meters_suit ?></span>m)</small>
                                    <h5 class="fw-bold text-success mb-0" id="dispWholesaleSuitPrice">Rs. 0</h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!$is_edit): ?>
                    <!-- Initial Opening Stock (Create Mode Only) -->
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold text-dark">INITIAL OPENING STOCK</label>
                        <div class="input-group">
                            <input type="number" step="0.1" name="initial_stock" class="form-control fw-bold" value="0">
                            <span class="input-group-text" id="stockUnitLabel">Pcs</span>
                        </div>
                        <small class="text-muted">Pieces for garments, or Total Meters for fabric.</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold">DEFAULT PRIMARY SIZE</label>
                        <select name="default_size_id" class="form-select">
                            <?php foreach ($sizes as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['name'] === 'Standard' || $s['name'] === 'M' ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['size_group']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Standard size for initial SKU.</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold">DEFAULT PRIMARY COLOR</label>
                        <select name="default_color_id" class="form-select">
                            <?php foreach ($colors as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Primary color for initial SKU.</small>
                    </div>
                    <?php endif; ?>

                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">LOW STOCK ALERT LEVEL</label>
                        <input type="number" step="0.5" name="alert_quantity" class="form-control" value="<?= $val_alert ?>">
                        <small class="text-muted">Notify dashboard when stock drops below this value.</small>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">CATALOG STATUS</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $val_status === 'active' ? 'selected' : '' ?>>Active (Visible in POS & Matrix)</option>
                            <option value="inactive" <?= $val_status === 'inactive' ? 'selected' : '' ?>>Inactive (Archived)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold">DESCRIPTION & FABRIC SPECIFICATIONS</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="e.g. 100% Egyptian Giza Cotton, Mercerized, Wrinkle-Free, Thread Count 80/2"><?= htmlspecialchars($val_desc) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Form Action Footer -->
            <div class="card border-0 shadow-sm rounded-3 p-3 mb-5 bg-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <a href="products.php" class="btn btn-light px-4">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </a>
                    <div class="d-flex gap-2">
                        <button type="submit" name="save_only" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> <?= $is_edit ? 'Update Product' : 'Save Product' ?>
                        </button>
                        <button type="submit" name="save_and_matrix" class="btn btn-outline-primary px-4 fw-bold">
                            <i class="bi bi-grid-3x3-gap me-1"></i> <?= $is_edit ? 'Update & Open Matrix' : 'Save & Open Matrix' ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleItemType(type) {
    const fabricSec = document.getElementById('fabricConfigSection');
    const suitPricingRow = document.getElementById('suitPricingRow');
    const unitSelect = document.getElementById('unitTypeSelect');
    const stockUnitLabel = document.getElementById('stockUnitLabel');

    if (type === 'fabric') {
        fabricSec.classList.remove('d-none');
        suitPricingRow.classList.remove('d-none');
        unitSelect.value = 'meter';
        if (stockUnitLabel) stockUnitLabel.textContent = 'Meters';
    } else {
        fabricSec.classList.add('d-none');
        suitPricingRow.classList.add('d-none');
        unitSelect.value = 'piece';
        if (stockUnitLabel) stockUnitLabel.textContent = 'Pcs';
    }
    updateFabricCalcs();
}

function updateFabricCalcs() {
    const suitMeters = parseFloat(document.getElementById('metersPerSuitInput')?.value) || 0;
    const thaanMeters = parseFloat(document.getElementById('thaanLengthInput')?.value) || 0;
    const sellPrice = parseFloat(document.getElementById('sellingPriceInput')?.value) || 0;
    const wholesalePrice = parseFloat(document.getElementById('wholesalePriceInput')?.value) || 0;

    const dispSuitM = document.getElementById('dispSuitMeters');
    const dispThaanM = document.getElementById('dispThaanMeters');
    const dispThaanS = document.getElementById('dispThaanSuits');
    
    if (dispSuitM) dispSuitM.textContent = suitMeters;
    if (dispThaanM) dispThaanM.textContent = thaanMeters;
    if (dispThaanS) dispThaanS.textContent = suitMeters > 0 ? (thaanMeters / suitMeters).toFixed(1) : '0';

    document.querySelectorAll('.unitSuitMeters').forEach(el => el.textContent = suitMeters);

    const retailSuitPrice = suitMeters * sellPrice;
    const wholesaleSuitPrice = suitMeters * wholesalePrice;

    const dispR = document.getElementById('dispRetailSuitPrice');
    const dispW = document.getElementById('dispWholesaleSuitPrice');
    if (dispR) dispR.textContent = 'Rs. ' + retailSuitPrice.toLocaleString();
    if (dispW) dispW.textContent = 'Rs. ' + wholesaleSuitPrice.toLocaleString();
}

function recalcMargins() {
    const cost = parseFloat(document.getElementById('costPriceInput').value) || 0;
    const sell = parseFloat(document.getElementById('sellingPriceInput').value) || 0;
    const wholesale = parseFloat(document.getElementById('wholesalePriceInput').value) || 0;

    const retailMargin = cost > 0 ? Math.round(((sell - cost) / cost) * 100) : 0;
    const wholesaleMargin = cost > 0 ? Math.round(((wholesale - cost) / cost) * 100) : 0;

    const rmBadge = document.getElementById('retailMarginBadge');
    const wmBadge = document.getElementById('wholesaleMarginBadge');

    if (rmBadge) rmBadge.textContent = `Margin: ${retailMargin}%`;
    if (wmBadge) wmBadge.textContent = `Margin: ${wholesaleMargin}%`;

    updateFabricCalcs();
}

function generateCode() {
    const code = 'PRD-' + Math.floor(1000 + Math.random() * 9000);
    document.getElementById('productCodeInput').value = code;
}

function generateBarcode() {
    const rand = '89' + Math.floor(10000000 + Math.random() * 90000000);
    document.getElementById('barcodeInput').value = rand;
}

document.addEventListener('DOMContentLoaded', function() {
    recalcMargins();
    const metersInput = document.getElementById('metersPerSuitInput');
    const thaanInput = document.getElementById('thaanLengthInput');
    if (metersInput) metersInput.addEventListener('input', updateFabricCalcs);
    if (thaanInput) thaanInput.addEventListener('input', updateFabricCalcs);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
