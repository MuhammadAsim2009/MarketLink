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

<div class="auth-container" style="max-width: 500px;">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="bg-dark text-white p-4 text-center">
            <div class="d-inline-flex p-3 rounded-circle bg-white bg-opacity-10 text-white mb-2">
                <i class="bi bi-shield-lock-fill fs-3"></i>
            </div>
            <h1 class="h4 fw-bold mb-1 text-white">Administrator Portal</h1>
            <p class="text-white-50 small mb-0">Authorized personnel only — MarketLink Platform</p>
        </div>
        <div class="card-body p-4 p-md-5">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-exclamation flex-shrink-0"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>auth/admin-login.php" method="POST" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label small fw-semibold" for="email">Admin Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-person-badge"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email_val ?: 'admin@marketlink.test') ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold" for="password">Admin Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                        <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" required placeholder="Enter admin password">
                        <button class="btn btn-outline-secondary border-start-0 bg-light text-muted px-3" type="button" data-toggle-password="password" title="Show password" style="border-color: var(--border);">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-dark w-100 py-2 fw-semibold mb-3">
                    <i class="bi bi-lock-fill me-1"></i> Secure Admin Sign In
                </button>

                <div class="p-2 bg-light rounded text-center small text-muted border">
                    Default Credentials: <code>admin@marketlink.test</code> / <code>Admin@123</code>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
