<?php
// brands.php - Garment Brands & Manufacturers
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

require_role('admin', 'manager', 'inventory');

// Add Brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $status = $_POST['status'] ?? 'active';

    $stmt = $pdo->prepare("UPDATE brands SET name = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $status, $id]);
    $_SESSION['flash_success'] = "Brand updated successfully!";
    header("Location: brands.php");
    exit;
}

$page_title = 'Garment Brands';
require_once __DIR__ . '/includes/header.php';

$brands = $pdo->query("SELECT b.*, COUNT(p.id) AS product_count 
                       FROM brands b 
                       LEFT JOIN products p ON b.id = p.brand_id 
                       GROUP BY b.id 
                       ORDER BY b.name ASC")->fetchAll();
?>

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0">Clothing Brands</h5>
            <small class="text-muted">Manage manufacturers and designer labels</small>
        </div>
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addBrandModal">
            <i class="bi bi-plus-circle me-1"></i> Add Brand
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-muted">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Brand Name</th>
                    <th>Products in Store</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($brands as $idx => $br): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><strong class="text-dark"><?= htmlspecialchars($br['name']) ?></strong></td>
                        <td><span class="badge bg-secondary-subtle text-secondary"><?= $br['product_count'] ?> Products</span></td>
                        <td><?= status_badge($br['status']) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" onclick='editBrand(<?= json_encode($br) ?>)'>
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add Brand -->
<div class="modal fade" id="addBrandModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Add Clothing Brand</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="brands.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">BRAND / LABEL NAME *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Maria B, Cambridge, Charcoal">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Brand</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Brand -->
<div class="modal fade" id="editBrandModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit Brand</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="brands.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editBrandId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">BRAND NAME *</label>
                        <input type="text" name="name" id="editBrandName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">STATUS</label>
                        <select name="status" id="editBrandStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
function editBrand(b) {
    document.getElementById('editBrandId').value = b.id;
    document.getElementById('editBrandName').value = b.name;
    document.getElementById('editBrandStatus').value = b.status;
    new bootstrap.Modal(document.getElementById('editBrandModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
