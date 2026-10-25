<?php
// categories.php - Garment Categories & Subcategories
$page_title = 'Garment Categories';
require_once __DIR__ . '/includes/header.php';

require_role('admin', 'manager', 'inventory');

// Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $desc = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';

    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $desc, $status, $id]);
    $_SESSION['flash_success'] = "Category updated successfully!";
    header("Location: categories.php");
    exit;
}

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
            <h5 class="fw-bold mb-0">Garment Categories</h5>
            <small class="text-muted">Manage clothing departments: Men's, Women's, Kids, Shalwar Kameez, Kurtis, etc.</small>
        </div>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-circle me-1"></i> Add Category
        </button>
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
                    <th>Garments Assigned</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
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
                            <button class="btn btn-sm btn-outline-secondary" onclick='editCat(<?= json_encode($cat) ?>)'>
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Add Garment Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">CATEGORY NAME *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Waistcoats & Blazers">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">DESCRIPTION</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief notes about this category..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Category -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editCatId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">CATEGORY NAME *</label>
                        <input type="text" name="name" id="editCatName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">STATUS</label>
                        <select name="status" id="editCatStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">DESCRIPTION</label>
                        <textarea name="description" id="editCatDesc" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCat(cat) {
    document.getElementById('editCatId').value = cat.id;
    document.getElementById('editCatName').value = cat.name;
    document.getElementById('editCatDesc').value = cat.description || '';
    document.getElementById('editCatStatus').value = cat.status;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
