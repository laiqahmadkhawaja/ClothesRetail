<?php
// login.php - Authentication Page
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';
$settings = get_settings($pdo);
$shop_name = $settings['shop_name'] ?? 'ClothesRetail POS';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            log_audit($pdo, 'Login', 'Auth', 'User logged in successfully');

            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid username or password, or account is disabled.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($shop_name) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/clothes-retail.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .login-header {
            background: #4f46e5;
            color: #ffffff;
            padding: 2.2rem 2rem 1.8rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="login-card p-0">
    <div class="login-header">
        <i class="bi bi-shop-window display-4 mb-2"></i>
        <h4 class="fw-bold mb-1"><?= htmlspecialchars($shop_name) ?></h4>
        <p class="mb-0 text-white-50 small">Clothing Retail & Khata Management ERP</p>
    </div>

    <div class="p-4 p-sm-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small mb-4" role="alert">
                <i class="bi bi-exclamation-circle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label text-muted small fw-semibold">USERNAME</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label text-muted small fw-semibold">PASSWORD</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Store
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <span class="text-muted small d-block mb-2">Demo Quick Login:</span>
            <div class="btn-group w-100" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillLogin('admin', 'admin123')">
                    <strong>Admin</strong>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillLogin('cashier', 'cashier123')">
                    <strong>Cashier</strong>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillLogin('manager', 'admin123')">
                    <strong>Manager</strong>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(user, pass) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
}
</script>

</body>
</html>
