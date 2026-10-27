<?php
// users.php - User Accounts, Roles & RBAC Dedicated Forms
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

require_role('admin');

$action = $_GET['action'] ?? 'list';
$edit_id = intval($_GET['id'] ?? 0);

// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_user') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($username) && !empty($password) && !empty($full_name)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, full_name, role, phone, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $hash, $full_name, $role, $phone]);

            log_audit($pdo, 'Create User', 'Users', "Created user account for $full_name ($username) as $role");
            $_SESSION['flash_success'] = "User account created successfully!";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Username already taken.";
        }
    }
    header("Location: users.php");
    exit;
}

// Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'edit_user') {
    $id = intval($_POST['id']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $new_password = $_POST['new_password'] ?? '';

    if (!empty($new_password)) {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, phone = ?, status = ?, password_hash = ? WHERE id = ?");
        $stmt->execute([$full_name, $role, $phone, $status, $hash, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, phone = ?, status = ? WHERE id = ?");
        $stmt->execute([$full_name, $role, $phone, $status, $id]);
    }

    log_audit($pdo, 'Update User', 'Users', "Updated user account #$id");
    $_SESSION['flash_success'] = "User account updated!";
    header("Location: users.php");
    exit;
}

$edit_user = null;
if ($action === 'edit' && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch();
    if (!$edit_user) {
        $_SESSION['flash_error'] = "User #$edit_id not found.";
        header("Location: users.php");
        exit;
    }
}

$page_title = ($action === 'create') ? 'Create User Account' : (($action === 'edit') ? 'Edit User' : 'User Accounts & Access Control');
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
                        <i class="bi <?= $action === 'edit' ? 'bi-pencil-square text-warning' : 'bi-person-plus text-primary' ?> me-2"></i>
                        <?= $action === 'edit' ? 'Edit User: ' . htmlspecialchars($edit_user['full_name']) : 'Add New Staff Account' ?>
                    </h5>
                    <small class="text-muted">Dedicated form for user credentials and RBAC permission roles</small>
                </div>
                <a href="users.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Users
                </a>
            </div>

            <form method="POST" action="users.php">
                <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit_user' : 'add_user' ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">FULL NAME *</label>
                        <input type="text" name="full_name" class="form-control" required 
                               placeholder="e.g. Muhammad Usman" 
                               value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>" autofocus>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">LOGIN USERNAME *</label>
                        <input type="text" name="username" class="form-control" required 
                               placeholder="e.g. usman_cashier" 
                               value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>" 
                               <?= $action === 'edit' ? 'readonly' : '' ?>>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">ROLE & ACCESS LEVEL *</label>
                        <select name="role" class="form-select" required>
                            <option value="cashier" <?= ($edit_user['role'] ?? '') === 'cashier' ? 'selected' : '' ?>>Cashier (POS & Sales only)</option>
                            <option value="inventory" <?= ($edit_user['role'] ?? '') === 'inventory' ? 'selected' : '' ?>>Inventory Clerk (Catalog & Stock)</option>
                            <option value="accountant" <?= ($edit_user['role'] ?? '') === 'accountant' ? 'selected' : '' ?>>Accountant (Khata & Ledgers)</option>
                            <option value="manager" <?= ($edit_user['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Shop Manager (Operations & Reports)</option>
                            <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>System Administrator (Full Control)</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">CONTACT PHONE</label>
                        <input type="text" name="phone" class="form-control" 
                               placeholder="0300-1234567" 
                               value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>">
                    </div>
                </div>

                <?php if ($action === 'create'): ?>
                <div class="mb-4">
                    <label class="form-label small fw-bold">LOGIN PASSWORD *</label>
                    <input type="password" name="password" class="form-control" required placeholder="Choose a secure password">
                </div>
                <?php else: ?>
                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">ACCOUNT STATUS</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($edit_user['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($edit_user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label small fw-bold">RESET PASSWORD (OPTIONAL)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="users.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> <?= $action === 'edit' ? 'Update User' : 'Save User Account' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<?php
$users = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
$auditLogs = $pdo->query("SELECT a.*, u.full_name AS user_name, u.username
                         FROM audit_logs a
                         LEFT JOIN users u ON a.user_id = u.id
                         ORDER BY a.id DESC
                         LIMIT 30")->fetchAll();
?>

<div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0">Staff Accounts & Role-Based Access</h5>
            <small class="text-muted">Control user credentials, permissions and review system activity logs</small>
        </div>
        <a href="users.php?action=create" class="btn btn-primary btn-sm px-3 shadow-sm">
            <i class="bi bi-person-plus me-1"></i> Add New User
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Users List -->
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-people text-primary me-2"></i> Active Staff Accounts</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($u['full_name']) ?></strong>
                                    <div class="text-muted small">@<?= htmlspecialchars($u['username']) ?></div>
                                </td>
                                <td><?= role_badge($u['role']) ?></td>
                                <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                                <td><?= status_badge($u['status']) ?></td>
                                <td class="small text-muted"><?= format_date($u['created_at'], false) ?></td>
                                <td class="text-end">
                                    <a href="users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Security & Audit Trail -->
    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-check text-success me-2"></i> Security Audit Log</h6>
            </div>
            <div class="table-responsive" style="max-height: 480px;">
                <table class="table table-sm align-middle mb-0" style="font-size: 12px;">
                    <thead class="table-light text-muted">
                        <tr>
                            <th>Action</th>
                            <th>User</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($log['action']) ?></strong>
                                    <small class="d-block text-muted"><?= htmlspecialchars($log['details'] ?? '') ?></small>
                                </td>
                                <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                                <td class="small text-muted"><?= date('d M, h:i A', strtotime($log['created_at'])) ?></td>
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
