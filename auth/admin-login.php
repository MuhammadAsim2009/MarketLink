<?php
/**
 * MarketLink - Admin Login Portal
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    if (get_logged_in_user_role() === ROLE_ADMIN) {
        redirect(BASE_URL . 'admin/dashboard.php');
    } else {
        redirect_by_role(get_logged_in_user_role());
    }
}

$error = '';
$email_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email_val = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email_val) || empty($password)) {
            $error = 'Please enter both administrator email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id, name, email, password_hash, role, status FROM users WHERE email = :email AND role = 'admin' LIMIT 1");
                $stmt->execute([':email' => $email_val]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($user['status'] === STATUS_SUSPENDED) {
                        $error = 'This administrative account is suspended.';
                    } else {
                        $_SESSION['user_id'] = (int)$user['user_id'];
                        $_SESSION['role'] = ROLE_ADMIN;
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['email'] = $user['email'];

                        set_flash('success', 'Admin session initialized. Welcome back, ' . $user['name'] . '!');
                        redirect(BASE_URL . 'admin/dashboard.php');
                    }
                } else {
                    $error = 'Invalid administrative credentials.';
                }
            } catch (PDOException $e) {
                error_log("Admin login error: " . $e->getMessage());
                $error = 'Database error. Please try again.';
            }
        }
    }
}

$page_title = 'Admin Portal Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="card auth-card border-dark-subtle shadow">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 rounded-circle bg-dark text-white mb-2">
                    <i class="bi bi-shield-lock-fill fs-3"></i>
                </div>
                <h1 class="h3 brand-font">Administrator Portal</h1>
                <p class="text-muted small">Authorized personnel only — TechWiz 7 Platform Management</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="bi bi-shield-exclamation me-1"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>auth/admin-login.php" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="email">Admin Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($email_val ?: 'admin@marketlink.test') ?>" required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Admin Password</label>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter admin password">
                </div>

                <button type="submit" class="btn btn-dark w-100 py-2 mb-3">
                    <i class="bi bi-lock me-1"></i> Secure Admin Sign In
                </button>

                <div class="p-2 bg-light rounded text-center small text-muted">
                    Default Credentials: <code>admin@marketlink.test</code> / <code>Admin@123</code>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
