<?php
// brands.php - Dedicated Form & List for Garment/Fabric Brands & Mills
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

require_role('admin', 'manager', 'inventory');

$action = $_GET['action'] ?? 'list';
$edit_id = intval($_GET['id'] ?? 0);

// Add Brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add') {
    $name = trim($_POST['name']);
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO brands (name) VALUES (?)");
            $stmt->execute([$name]);
            $_SESSION['flash_success'] = "Brand '$name' added successfully!";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Brand with this name already exists.";
        }
    }
    header("Location: brands.php");
    exit;
}

// Edit Brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'edit') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $status = $_POST['status'] ?? 'active';

    if (!empty($name) && $id > 0) {
        $stmt = $pdo->prepare("UPDATE brands SET name = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $status, $id]);
        $_SESSION['flash_success'] = "Brand updated successfully!";
    }
    header("Location: brands.php");
    exit;
}

$edit_brand = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_brand = $stmt->fetch();
    if (!$edit_brand) {
        $_SESSION['flash_error'] = "Brand #$edit_id not found.";
        header("Location: brands.php");
        exit;
    }
}

$page_title = ($action === 'create') ? 'Add Brand' : (($action === 'edit') ? 'Edit Brand' : 'Brands & Mills');
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<!-- DEDICATED FORM VIEW -->
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0">
                        <i class="bi <?= $action === 'edit' ? 'bi-pencil-square text-warning' : 'bi-plus-circle text-primary' ?> me-2"></i>
                        <?= $action === 'edit' ? 'Edit Brand: ' . htmlspecialchars($edit_brand['name']) : 'Add New Brand / Mill' ?>
                    </h5>
                    <small class="text-muted">Dedicated form for garment and fabric brand management</small>
                </div>
                <a href="brands.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <form method="POST" action="brands.php">
                <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $edit_brand['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label small fw-bold">BRAND / MILL / LABEL NAME *</label>
                    <input type="text" name="name" class="form-control form-control-lg fw-bold" required 
                           placeholder="e.g. Maria B, Cambridge, Charcoal, Pasha Fabrics, Gul Ahmed" 
                           value="<?= htmlspecialchars($edit_brand['name'] ?? '') ?>" autofocus>
                </div>

                <?php if ($action === 'edit'): ?>
                <div class="mb-4">
                    <label class="form-label small fw-bold">STATUS</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($edit_brand['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($edit_brand['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="brands.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> <?= $action === 'edit' ? 'Update Brand' : 'Save Brand' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<?php
$brands = $pdo->query("SELECT b.*, COUNT(p.id) AS product_count 
                       FROM brands b 
                       LEFT JOIN products p ON b.id = p.brand_id 
                       GROUP BY b.id 
                       ORDER BY b.name ASC")->fetchAll();
?>
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0">Clothing & Fabric Brands</h5>
            <small class="text-muted">Manage manufacturers, textile mills, and designer labels</small>
        </div>
        <a href="brands.php?action=create" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Add Brand
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Brand / Mill Name</th>
                    <th>Products in Store</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($brands)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No brands found. Click 'Add Brand' to create one.</td></tr>
                <?php else: ?>
                    <?php foreach ($brands as $idx => $br): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><strong class="text-dark"><?= htmlspecialchars($br['name']) ?></strong></td>
                            <td><span class="badge bg-secondary-subtle text-secondary"><?= $br['product_count'] ?> Products</span></td>
                            <td><?= status_badge($br['status']) ?></td>
                            <td class="text-end">
                                <a href="brands.php?action=edit&id=<?= $br['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
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
