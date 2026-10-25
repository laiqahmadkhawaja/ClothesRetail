<?php
// sizes_colors.php - Central Size & Color Management
$page_title = 'Sizes & Colors Management';
require_once __DIR__ . '/includes/header.php';

require_role('admin', 'manager', 'inventory');

// 1. Add Size
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_size') {
    $name = trim($_POST['name']);
    $group = trim($_POST['size_group'] ?? 'Standard');
    $sort = intval($_POST['sort_order'] ?? 0);

    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO sizes (name, size_group, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$name, $group, $sort]);
        $_SESSION['flash_success'] = "Size '$name' created!";
    }
    header("Location: sizes_colors.php");
    exit;
}

// 2. Add Color
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_color') {
    $name = trim($_POST['name']);
    $hex = trim($_POST['hex_code'] ?? '#000000');

    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO colors (name, hex_code) VALUES (?, ?)");
        $stmt->execute([$name, $hex]);
        $_SESSION['flash_success'] = "Color '$name' created with swatch!";
    }
    header("Location: sizes_colors.php");
    exit;
}

// 3. Delete Size / Color
if (isset($_GET['del_size'])) {
    $id = intval($_GET['del_size']);
    try {
        $pdo->prepare("DELETE FROM sizes WHERE id = ?")->execute([$id]);
        $_SESSION['flash_success'] = "Size deleted.";
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Cannot delete size: it is currently used by existing garment variants.";
    }
    header("Location: sizes_colors.php");
    exit;
}

if (isset($_GET['del_color'])) {
    $id = intval($_GET['del_color']);
    try {
        $pdo->prepare("DELETE FROM colors WHERE id = ?")->execute([$id]);
        $_SESSION['flash_success'] = "Color deleted.";
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Cannot delete color: it is currently used by existing garment variants.";
    }
    header("Location: sizes_colors.php");
    exit;
}

$sizes = $pdo->query("SELECT s.*, COUNT(pv.id) AS variant_count 
                      FROM sizes s 
                      LEFT JOIN product_variants pv ON s.id = pv.size_id 
                      GROUP BY s.id 
                      ORDER BY s.sort_order ASC, s.name ASC")->fetchAll();

$colors = $pdo->query("SELECT c.*, COUNT(pv.id) AS variant_count 
                       FROM colors c 
                       LEFT JOIN product_variants pv ON c.id = pv.color_id 
                       GROUP BY c.id 
                       ORDER BY c.name ASC")->fetchAll();
?>

<div class="row g-4">
    <!-- SIZES COLUMN -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-rulers text-primary me-2"></i> Clothing Sizes</h5>
                    <small class="text-muted">XS-XXL, Numeric waist, Kids ages</small>
                </div>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSizeModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Size
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Size Name</th>
                            <th>Group</th>
                            <th>Sort Order</th>
                            <th>Variants Using</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sizes as $s): ?>
                            <tr>
                                <td><span class="size-badge fs-6"><?= htmlspecialchars($s['name']) ?></span></td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($s['size_group']) ?></span></td>
                                <td><small class="text-muted"><?= $s['sort_order'] ?></small></td>
                                <td><small class="fw-bold"><?= $s['variant_count'] ?></small></td>
                                <td class="text-end">
                                    <a href="sizes_colors.php?del_size=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this size?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- COLORS COLUMN -->
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-palette text-info me-2"></i> Fabric Colors</h5>
                    <small class="text-muted">Color names & visual swatches</small>
                </div>
                <button class="btn btn-info btn-sm text-white" data-bs-toggle="modal" data-bs-target="#addColorModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Color
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>Swatch</th>
                            <th>Color Name</th>
                            <th>Hex Code</th>
                            <th>Variants Using</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($colors as $c): ?>
                            <tr>
                                <td>
                                    <span class="color-dot" style="width: 22px; height: 22px; background-color: <?= htmlspecialchars($c['hex_code']) ?>;"></span>
                                </td>
                                <td><strong class="text-dark"><?= htmlspecialchars($c['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($c['hex_code']) ?></code></td>
                                <td><small class="fw-bold"><?= $c['variant_count'] ?></small></td>
                                <td class="text-end">
                                    <a href="sizes_colors.php?del_color=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this color?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Size -->
<div class="modal fade" id="addSizeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Add Clothing Size</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="sizes_colors.php">
                <input type="hidden" name="action" value="add_size">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SIZE LABEL *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. 3XL, 38, 10-11Y">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SIZE SYSTEM / GROUP</label>
                        <select name="size_group" class="form-select">
                            <option value="Standard">Standard (XS, S, M, L, XL, XXL, 3XL)</option>
                            <option value="Numeric">Numeric Waist/Chest (28, 30, 32, 34, 36, 38, 40)</option>
                            <option value="Kids">Kids Age (2-3Y, 4-5Y, 6-7Y, 8-9Y, 10-12Y)</option>
                            <option value="Shoes">Shoe Sizes (6, 7, 8, 9, 10, 11)</option>
                            <option value="Custom">Custom / Free Size</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SORT ORDER</label>
                        <input type="number" name="sort_order" class="form-control" value="10">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Size</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Add Color -->
<div class="modal fade" id="addColorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title fw-bold">Add Fabric Color</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="sizes_colors.php">
                <input type="hidden" name="action" value="add_color">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">COLOR NAME *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Olive Green, Charcoal Grey, Maroon">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SWATCH COLOR PICKER</label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" name="hex_code" id="colorPicker" class="form-control form-control-color" value="#1a1a1a" style="width: 50px;">
                            <span class="small text-muted">Select exact swatch color for POS & cards</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Save Color</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
