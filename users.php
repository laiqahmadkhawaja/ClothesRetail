<?php
// users.php - User Accounts, Roles & RBAC Management
$page_title = 'User Accounts & Access Control';
require_once __DIR__ . '/includes/header.php';

require_role('admin');

// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
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
        <button class="btn btn-primary btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-person-plus me-1"></i> Add New User
        </button>
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
                                    <button class="btn btn-sm btn-outline-secondary" onclick='editUser(<?= json_encode($u) ?>)'>
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
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
                                    <strong><?= htmlspecialchars($log['action']) ?></strong> (<?= htmlspecialchars($log['module']) ?>)
                                    <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars(substr($log['details'] ?? '', 0, 50)) ?></div>
                                </td>
                                <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                                <td class="text-muted" style="white-space: nowrap;"><?= date('d M, h:i A', strtotime($log['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Create Staff Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">FULL NAME *</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="e.g. Asim Raza">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">USERNAME *</label>
                            <input type="text" name="username" class="form-control" required placeholder="e.g. asim">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">PASSWORD *</label>
                            <input type="password" name="password" class="form-control" required placeholder="••••••••">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">ROLE</label>
                            <select name="role" class="form-select" required>
                                <option value="cashier">Cashier (POS only)</option>
                                <option value="inventory">Inventory Staff</option>
                                <option value="accountant">Accountant</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Administrator (Full Access)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">PHONE NUMBER</label>
                            <input type="text" name="phone" class="form-control" placeholder="0300-1122334">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Edit User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="id" id="editUserId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">FULL NAME *</label>
                        <input type="text" name="full_name" id="editFullName" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">ROLE</label>
                            <select name="role" id="editRole" class="form-select">
                                <option value="cashier">Cashier</option>
                                <option value="inventory">Inventory Staff</option>
                                <option value="accountant">Accountant</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">STATUS</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">PHONE</label>
                        <input type="text" name="phone" id="editPhone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">RESET PASSWORD (OPTIONAL)</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Leave empty to keep existing password">
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
function editUser(u) {
    document.getElementById('editUserId').value = u.id;
    document.getElementById('editFullName').value = u.full_name;
    document.getElementById('editRole').value = u.role;
    document.getElementById('editPhone').value = u.phone || '';
    document.getElementById('editStatus').value = u.status;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
