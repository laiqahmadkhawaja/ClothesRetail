<?php
// sizes_colors.php - Central Size & Color Management Dedicated Forms
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

require_role('admin', 'manager', 'inventory');

$action = $_GET['action'] ?? 'list';

// 1. Add Size
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_size') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_color') {
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

$page_title = ($action === 'add_size') ? 'Add Clothing Size' : (($action === 'add_color') ? 'Add Color Swatch' : 'Sizes & Colors Management');
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'add_size'): ?>
<!-- DEDICATED FORM: ADD SIZE -->
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-rulers me-2"></i> Add Clothing / Fabric Size
                    </h5>
                    <small class="text-muted">Dedicated form for size definitions</small>
                </div>
                <a href="sizes_colors.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <form method="POST" action="sizes_colors.php">
                <input type="hidden" name="form_action" value="add_size">

                <div class="mb-3">
                    <label class="form-label small fw-bold">SIZE LABEL / NAME *</label>
                    <input type="text" name="name" class="form-control form-control-lg fw-bold" required 
                           placeholder="e.g. XL, 38, 4.5m Suit, Free Size" autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">SIZE GROUP</label>
                    <select name="size_group" class="form-select">
                        <option value="Standard">Standard (S, M, L, XL, XXL)</option>
                        <option value="Suits & Fabric">Suits & Fabric (4.0m, 4.5m, Thaan)</option>
                        <option value="Numeric Waist">Numeric Waist (28, 30, 32, 34, 36)</option>
                        <option value="Kids Age">Kids Age (2Y, 4Y, 6Y, 8Y)</option>
                        <option value="Collar / Neck">Collar / Neck (14.5, 15, 15.5, 16)</option>
                        <option value="Free Size">Free Size / Unstitched</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">DISPLAY SORT ORDER</label>
                    <input type="number" name="sort_order" class="form-control" value="10">
                    <small class="text-muted">Lower numbers appear first in the Matrix and POS</small>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="sizes_colors.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Save Size
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($action === 'add_color'): ?>
<!-- DEDICATED FORM: ADD COLOR -->
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0 text-info">
                        <i class="bi bi-palette me-2"></i> Add Color Swatch
                    </h5>
                    <small class="text-muted">Dedicated form for color swatches</small>
                </div>
                <a href="sizes_colors.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <form method="POST" action="sizes_colors.php">
                <input type="hidden" name="form_action" value="add_color">

                <div class="mb-3">
                    <label class="form-label small fw-bold">COLOR NAME *</label>
                    <input type="text" name="name" class="form-control form-control-lg fw-bold" required 
                           placeholder="e.g. Royal Navy, Olive Green, Boski White, Maroon" autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">VISUAL COLOR SWATCH / HEX CODE</label>
                    <div class="input-group">
                        <input type="color" name="hex_code" id="colorPicker" class="form-control form-control-color" value="#1e3a8a" title="Choose color">
                        <input type="text" id="hexText" class="form-control font-monospace" value="#1e3a8a" readonly>
                    </div>
                    <small class="text-muted">Pick color tone for POS dots and matrix swatches</small>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="sizes_colors.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-info text-white px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Save Color Swatch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('colorPicker')?.addEventListener('input', function() {
    document.getElementById('hexText').value = this.value;
});
</script>

<?php else: ?>
<!-- LIST VIEW -->
<?php
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
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-rulers text-primary me-2"></i> Clothing & Fabric Sizes</h5>
                    <small class="text-muted">Standard, numeric, suit lengths & custom</small>
                </div>
                <a href="sizes_colors.php?action=add_size" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Add Size
                </a>
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
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-palette text-info me-2"></i> Fabric & Garment Colors</h5>
                    <small class="text-muted">Color names & visual swatches</small>
                </div>
                <a href="sizes_colors.php?action=add_color" class="btn btn-info btn-sm text-white">
                    <i class="bi bi-plus-circle me-1"></i> Add Color
                </a>
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
                                    <span class="color-dot" style="background-color: <?= $c['hex_code'] ?>; width: 22px; height: 22px; border: 1px solid #ccc;"></span>
                                </td>
                                <td><strong class="text-dark"><?= htmlspecialchars($c['name']) ?></strong></td>
                                <td><code class="small"><?= htmlspecialchars($c['hex_code']) ?></code></td>
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
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
