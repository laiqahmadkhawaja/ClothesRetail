<?php
// categories.php - Dedicated Form & List for Garment/Fabric Categories
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

require_role('admin', 'manager', 'inventory');

$action = $_GET['action'] ?? 'list';
$edit_id = intval($_GET['id'] ?? 0);

// Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add') {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description'] ?? '');
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $desc]);
            $_SESSION['flash_success'] = "Category '$name' created successfully!";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Category with this name or slug already exists.";
        }
    }
    header("Location: categories.php");
    exit;
}

// Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'edit') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $desc = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (!empty($name) && $id > 0) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $desc, $status, $id]);
        $_SESSION['flash_success'] = "Category updated successfully!";
    }
    header("Location: categories.php");
    exit;
}

$edit_cat = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_cat = $stmt->fetch();
    if (!$edit_cat) {
        $_SESSION['flash_error'] = "Category #$edit_id not found.";
        header("Location: categories.php");
        exit;
    }
}

$page_title = ($action === 'create') ? 'Add Category' : (($action === 'edit') ? 'Edit Category' : 'Garment & Fabric Categories');
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<!-- DEDICATED FORM VIEW -->
<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-0">
                        <i class="bi <?= $action === 'edit' ? 'bi-pencil-square text-warning' : 'bi-plus-circle text-primary' ?> me-2"></i>
                        <?= $action === 'edit' ? 'Edit Category: ' . htmlspecialchars($edit_cat['name']) : 'Add New Category' ?>
                    </h5>
                    <small class="text-muted">Dedicated form for creating and editing product departments</small>
                </div>
                <a href="categories.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to List
                </a>
            </div>

            <form method="POST" action="categories.php">
                <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $edit_cat['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label small fw-bold">CATEGORY NAME *</label>
                    <input type="text" name="name" class="form-control form-control-lg fw-bold" required 
                           placeholder="e.g. Shalwar Kameez, Cotton Lawns, Kurtis, Trousers" 
                           value="<?= htmlspecialchars($edit_cat['name'] ?? '') ?>" autofocus>
                </div>

                <?php if ($action === 'edit'): ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold">STATUS</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($edit_cat['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($edit_cat['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mb-4">
                    <label class="form-label small fw-bold">DESCRIPTION / DEPARTMENT NOTES</label>
                    <textarea name="description" class="form-control" rows="3" 
                              placeholder="Brief description or guidelines for this department..."><?= htmlspecialchars($edit_cat['description'] ?? '') ?></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="categories.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> <?= $action === 'edit' ? 'Update Category' : 'Save Category' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<?php
// Fetch categories with product counts
$categories = $pdo->query("SELECT c.*, COUNT(p.id) AS product_count 
                           FROM categories c 
                           LEFT JOIN products p ON c.id = p.category_id 
                           GROUP BY c.id 
                           ORDER BY c.name ASC")->fetchAll();
?>
<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0">Garment & Fabric Categories</h5>
            <small class="text-muted">Manage clothing departments: Men's, Women's, Kids, Shalwar Kameez, Kurtis, Unstitched Fabric, etc.</small>
        </div>
        <a href="categories.php?action=create" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Add Category
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Category Name</th>
                    <th>Slug / Identifier</th>
                    <th>Description</th>
                    <th>Products Assigned</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No categories created yet. Click 'Add Category' to create one.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $idx => $cat): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <strong class="text-dark"><?= htmlspecialchars($cat['name']) ?></strong>
                            </td>
                            <td><code class="small"><?= htmlspecialchars($cat['slug']) ?></code></td>
                            <td><small class="text-muted"><?= htmlspecialchars($cat['description'] ?? '-') ?></small></td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary fw-bold"><?= $cat['product_count'] ?> Products</span>
                            </td>
                            <td><?= status_badge($cat['status']) ?></td>
                            <td class="text-end">
                                <a href="categories.php?action=edit&id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-secondary">
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
