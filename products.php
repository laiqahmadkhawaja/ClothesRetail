<?php
// products.php - Product & Fabric Catalog & Variant Management
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$page_title = 'Products & Fabrics Catalog';
require_once __DIR__ . '/includes/header.php';

// Filters
$q = trim($_GET['q'] ?? '');
$filter_cat = intval($_GET['category_id'] ?? 0);
$filter_brand = intval($_GET['brand_id'] ?? 0);
$filter_season = trim($_GET['season'] ?? '');
$filter_type = trim($_GET['item_type'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($q)) {
    $where[] = "(p.name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ? OR p.rack_location LIKE ?)";
    $params = array_merge($params, ["%$q%", "%$q%", "%$q%", "%$q%"]);
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
if (!empty($filter_type)) {
    $where[] = "p.item_type = ?";
    $params[] = $filter_type;
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
            <h5 class="fw-bold mb-0">Garments & Fabric (Thaan) Catalog</h5>
            <small class="text-muted">Manage readymade clothes, unstitched fabric rolls (thaans), and inventory stock</small>
        </div>
        <div class="d-flex gap-2">
            <a href="product_form.php" class="btn btn-primary btn-sm px-3 shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Add New Product / Fabric
            </a>
            <a href="product_matrix.php" class="btn btn-outline-primary btn-sm px-3">
                <i class="bi bi-grid-3x3-gap me-1"></i> Open Size & Color Matrix
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <form method="GET" action="products.php" class="row g-2 mt-3 pt-3 border-top">
        <div class="col-12 col-md-3">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search code, name, barcode, rack..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-6 col-md-2">
            <select name="item_type" class="form-select form-select-sm">
                <option value="">All Types (Garment & Fabric)</option>
                <option value="readymade" <?= $filter_type === 'readymade' ? 'selected' : '' ?>>👗 Readymade Garments</option>
                <option value="fabric" <?= $filter_type === 'fabric' ? 'selected' : '' ?>>🧵 Open Fabric / Thaan</option>
            </select>
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
                    <th>Product / Fabric Name</th>
                    <th>Type & Units</th>
                    <th>Category & Brand</th>
                    <th>Variants</th>
                    <th>Stock Available</th>
                    <th>Pricing (Retail / W-Sale)</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">No products or fabrics found matching the criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border fw-semibold font-monospace"><?= htmlspecialchars($p['product_code']) ?></span>
                                <?php if (!empty($p['barcode'])): ?>
                                    <div class="text-muted small mt-1 font-monospace" style="font-size: 0.72rem;" title="Barcode / Tag Code">
                                        <i class="bi bi-upc text-primary"></i> <?= htmlspecialchars($p['barcode']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars(substr($p['description'] ?? '', 0, 45)) ?></small>
                                <?php if (!empty($p['rack_location'])): ?>
                                    <div class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($p['rack_location']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (($p['item_type'] ?? '') === 'fabric'): ?>
                                    <span class="badge bg-purple-subtle text-purple border" style="background-color: #f3e8ff; color: #6b21a8;">
                                        🧵 Fabric (<?= htmlspecialchars($p['unit_type'] ?? 'meter') ?>)
                                    </span>
                                    <?php if ($p['meters_per_suit'] > 0): ?>
                                        <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                            Suit: <strong><?= $p['meters_per_suit'] ?>m</strong>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary border">
                                        👗 Garment (<?= htmlspecialchars($p['unit_type'] ?? 'piece') ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['brand_name'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info fw-bold"><?= $p['variant_count'] ?> Combos</span>
                            </td>
                            <td>
                                <?php 
                                    $unitLabel = ($p['item_type'] ?? '') === 'fabric' ? ($p['unit_type'] ?? 'm') : 'pcs';
                                ?>
                                <span class="badge <?= $p['total_stock'] <= 5 ? ($p['total_stock'] <= 0 ? 'bg-danger' : 'bg-warning text-dark') : 'bg-success' ?> fs-6">
                                    <?= rtrim(rtrim(number_format($p['total_stock'], 2), '0'), '.') ?> <?= $unitLabel ?>
                                </span>
                            </td>
                            <td>
                                <div>
                                    <strong class="text-primary"><?= format_currency($p['selling_price']) ?></strong>
                                    <small class="text-muted">/<?= $p['unit_type'] ?? 'unit' ?></small>
                                </div>
                                <?php if ($p['wholesale_price'] > 0): ?>
                                    <div class="small text-success">
                                        W-Sale: <strong><?= format_currency($p['wholesale_price']) ?></strong>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= status_badge($p['status']) ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="product_matrix.php?product_id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Size & Color Matrix">
                                        <i class="bi bi-grid-3x3-gap"></i> Matrix
                                    </a>
                                    <a href="product_form.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Edit Product Form">
                                        <i class="bi bi-pencil"></i> Edit
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
