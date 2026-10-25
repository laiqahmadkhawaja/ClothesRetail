<?php
// products.php - Product Catalog & Variant Management
$page_title = 'Products Catalog';
require_once __DIR__ . '/includes/header.php';

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    require_role('admin', 'manager', 'inventory');

    $code = trim($_POST['product_code']);
    $name = trim($_POST['name']);
    $cat_id = intval($_POST['category_id']) ?: null;
    $brand_id = intval($_POST['brand_id']) ?: null;
    $season = trim($_POST['season'] ?? 'All Season');
    $rack = trim($_POST['rack_location'] ?? '');
    $alert_qty = intval($_POST['alert_quantity'] ?? 5);
    $desc = trim($_POST['description'] ?? '');

    if (empty($code) || empty($name)) {
        $_SESSION['flash_error'] = 'Product Code and Name are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (product_code, name, category_id, brand_id, season, rack_location, description, alert_quantity) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $cat_id, $brand_id, $season, $rack, $desc, $alert_qty]);
            $new_id = $pdo->lastInsertId();

            log_audit($pdo, 'Create Product', 'Catalog', "Created product: $name ($code)");
            $_SESSION['flash_success'] = "Product created successfully! You can now configure its Size & Color Matrix.";
            header("Location: product_matrix.php?product_id=$new_id");
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'Error: ' . (strpos($e->getMessage(), 'Duplicate') !== false ? 'Product code already exists!' : $e->getMessage());
        }
    }
    header("Location: products.php");
    exit;
}

// Handle Update Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    require_role('admin', 'manager', 'inventory');

    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $cat_id = intval($_POST['category_id']) ?: null;
    $brand_id = intval($_POST['brand_id']) ?: null;
    $season = trim($_POST['season'] ?? 'All Season');
    $rack = trim($_POST['rack_location'] ?? '');
    $alert_qty = intval($_POST['alert_quantity'] ?? 5);
    $status = $_POST['status'] ?? 'active';
    $desc = trim($_POST['description'] ?? '');

    $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, brand_id = ?, season = ?, rack_location = ?, description = ?, alert_quantity = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $cat_id, $brand_id, $season, $rack, $desc, $alert_qty, $status, $id]);

    log_audit($pdo, 'Update Product', 'Catalog', "Updated product #$id");
    $_SESSION['flash_success'] = "Product updated successfully!";
    header("Location: products.php");
    exit;
}

// Filters
$q = trim($_GET['q'] ?? '');
$filter_cat = intval($_GET['category_id'] ?? 0);
$filter_brand = intval($_GET['brand_id'] ?? 0);
$filter_season = trim($_GET['season'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($q)) {
    $where[] = "(p.name LIKE ? OR p.product_code LIKE ? OR p.rack_location LIKE ?)";
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%"]);
}
if ($filter_cat > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $filter_cat;
}
if ($filter_brand > 0) {
    $where[] = "p.brand_id = ?";
    $params[] = $filter_brand;
}
if (!empty($filter_season)) {
    $where[] = "p.season = ?";
    $params[] = $filter_season;
}

$where_clause = implode(' AND ', $where);

// Fetch products with variant summary
$sql = "SELECT p.*, c.name AS category_name, b.name AS brand_name,
               COUNT(pv.id) AS variant_count,
               COALESCE(SUM(pv.stock_quantity), 0) AS total_stock,
               MIN(pv.selling_price) AS min_price,
               MAX(pv.selling_price) AS max_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN product_variants pv ON p.id = pv.product_id AND pv.status = 'active'
        WHERE $where_clause
        GROUP BY p.id
        ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Categories, Brands, Seasons for dropdowns
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands ORDER BY name ASC")->fetchAll();
$seasons = ['All Season', 'Summer 2026', 'Winter 2026', 'Eid Collection', 'Festive / Wedding', 'Spring'];
?>

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="fw-bold mb-0">Garment Catalog & Matrix</h5>
            <small class="text-muted">Manage clothing items, sizes, colors and inventory stock</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus-circle me-1"></i> Add New Garment
            </button>
            <a href="product_matrix.php" class="btn btn-outline-primary btn-sm px-3">
                <i class="bi bi-grid-3x3-gap me-1"></i> Open Size & Color Matrix
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <form method="GET" action="products.php" class="row g-2 mt-3 pt-3 border-top">
        <div class="col-12 col-md-3">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search code, garment name, rack..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-6 col-md-2">
            <select name="category_id" class="form-select form-select-sm">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $filter_cat == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="brand_id" class="form-select form-select-sm">
                <option value="0">All Brands</option>
                <?php foreach ($brands as $br): ?>
                    <option value="<?= $br['id'] ?>" <?= $filter_brand == $br['id'] ? 'selected' : '' ?>><?= htmlspecialchars($br['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="season" class="form-select form-select-sm">
                <option value="">All Seasons</option>
                <?php foreach ($seasons as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_season == $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-secondary btn-sm flex-grow-1"><i class="bi bi-filter"></i> Filter</button>
            <a href="products.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th>Code</th>
                    <th>Garment Name</th>
                    <th>Category & Brand</th>
                    <th>Season / Rack</th>
                    <th>Variants</th>
                    <th>Stock Units</th>
                    <th>Price Range</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No products found matching the criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border fw-semibold"><?= htmlspecialchars($p['product_code']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 45)) ?></small>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['brand_name'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($p['season']) ?></span>
                                <div class="small text-muted mt-1"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['rack_location'] ?: 'Unassigned') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info fw-bold"><?= $p['variant_count'] ?> Size/Color Combos</span>
                            </td>
                            <td>
                                <span class="badge <?= $p['total_stock'] <= 5 ? ($p['total_stock'] <= 0 ? 'bg-danger' : 'bg-warning text-dark') : 'bg-success' ?> fs-6">
                                    <?= $p['total_stock'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($p['variant_count'] > 0): ?>
                                    <strong class="text-dark">
                                        <?= $p['min_price'] == $p['max_price'] ? format_currency($p['min_price']) : format_currency($p['min_price']) . ' - ' . format_currency($p['max_price']) ?>
                                    </strong>
                                <?php else: ?>
                                    <span class="text-muted small">No price</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= status_badge($p['status']) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="product_matrix.php?product_id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Size & Color Inventory Matrix">
                                        <i class="bi bi-grid-3x3-gap"></i> Matrix
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick='editProduct(<?= json_encode($p) ?>)' title="Edit Details">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: Add New Product -->
<!-- ========================================== -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Add New Clothing Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="products.php">
                <input type="hidden" name="action" value="add_product">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">PRODUCT CODE *</label>
                            <input type="text" name="product_code" class="form-control" required placeholder="e.g. PRD-1006" value="PRD-<?= rand(1000, 9999) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">GARMENT / PRODUCT NAME *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Men Slim Fit Oxford Cotton Shirt">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">CATEGORY</label>
                            <select name="category_id" class="form-select">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">BRAND / MANUFACTURER</label>
                            <select name="brand_id" class="form-select">
                                <option value="">Select Brand</option>
                                <?php foreach ($brands as $br): ?>
                                    <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">SEASONAL COLLECTION</label>
                            <select name="season" class="form-select">
                                <?php foreach ($seasons as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">RACK / SHELF LOCATION</label>
                            <input type="text" name="rack_location" class="form-control" placeholder="e.g. Rack A-3, Shelf 2">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">LOW STOCK ALERT LEVEL</label>
                            <input type="number" name="alert_quantity" class="form-control" value="5" min="1">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">DESCRIPTION / FABRIC SPECS</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Fabric composition, wash instructions, styling details..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Create Product & Open Variant Matrix</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: Edit Product -->
<!-- ========================================== -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit Clothing Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="products.php">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">PRODUCT CODE</label>
                            <input type="text" id="editCode" class="form-control bg-light" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">GARMENT / PRODUCT NAME *</label>
                            <input type="text" name="name" id="editName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">CATEGORY</label>
                            <select name="category_id" id="editCat" class="form-select">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">BRAND</label>
                            <select name="brand_id" id="editBrand" class="form-select">
                                <option value="">Select Brand</option>
                                <?php foreach ($brands as $br): ?>
                                    <option value="<?= $br['id'] ?>"><?= htmlspecialchars($br['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">SEASON</label>
                            <select name="season" id="editSeason" class="form-select">
                                <?php foreach ($seasons as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">RACK LOCATION</label>
                            <input type="text" name="rack_location" id="editRack" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">ALERT LEVEL</label>
                            <input type="number" name="alert_quantity" id="editAlert" class="form-control" min="1">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">STATUS</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">DESCRIPTION</label>
                            <textarea name="description" id="editDesc" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editProduct(p) {
    document.getElementById('editId').value = p.id;
    document.getElementById('editCode').value = p.product_code;
    document.getElementById('editName').value = p.name;
    document.getElementById('editCat').value = p.category_id || '';
    document.getElementById('editBrand').value = p.brand_id || '';
    document.getElementById('editSeason').value = p.season || 'All Season';
    document.getElementById('editRack').value = p.rack_location || '';
    document.getElementById('editAlert').value = p.alert_quantity || 5;
    document.getElementById('editStatus').value = p.status || 'active';
    document.getElementById('editDesc').value = p.description || '';

    new bootstrap.Modal(document.getElementById('editProductModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
