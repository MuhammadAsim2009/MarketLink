<?php
/**
 * MarketLink - Forgot Password Request
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = false;
$demo_reset_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitize_input($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Invalidate previous tokens for this email
                    $del_stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
                    $del_stmt->execute([':email' => $email]);

                    // Generate a cryptographically secure token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 minutes expiry

                    $ins_stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (:email, :token, :expires_at, NOW())");
                    $ins_stmt->execute([
                        ':email' => $email,
                        ':token' => $token,
                        ':expires_at' => $expires_at
                    ]);

                    $reset_url = BASE_URL . 'auth/reset-password.php?token=' . urlencode($token);
                    $demo_reset_link = $reset_url;
                }

                // Generic message to prevent email enumeration
                $success = true;
            } catch (PDOException $e) {
                error_log("Password reset request error: " . $e->getMessage());
                $error = 'An error occurred while processing your request.';
            }
        }
    }
}

$page_title = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container" style="max-width: 520px;">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-2">
                    <i class="bi bi-key-fill fs-3"></i>
                </div>
                <h1 class="h3 fw-bold">Reset Your Password</h1>
                <p class="text-muted small">Enter your registered email address and we'll send you a password reset link.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small mb-4 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-3 mb-4">
                    <h6 class="alert-heading fw-bold mb-1"><i class="bi bi-envelope-check me-1"></i> Reset Request Received</h6>
                    <p class="small mb-0">If this email is registered in our system, you will receive password reset instructions. The reset link is valid for <strong>30 minutes</strong>.</p>
                </div>

                <?php if (!empty($demo_reset_link)): ?>
                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="small fw-bold text-success mb-1"><i class="bi bi-info-circle me-1"></i> Quick Reset Link:</div>
                        <p class="small text-muted mb-2">You can access your reset link directly below to choose a new password:</p>
                        <a href="<?= e($demo_reset_link) ?>" class="btn btn-primary btn-sm w-100 text-truncate">
                            <i class="bi bi-arrow-right-circle me-1"></i> Open Password Reset Form
                        </a>
                    </div>
                <?php endif; ?>

                <div class="text-center">
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            <?php else: ?>
                <form action="<?= BASE_URL ?>auth/forgot-password.php" method="POST" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold" for="email">Registered Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="name@example.com" autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
                        <i class="bi bi-send me-1"></i> Send Password Reset Link
                    </button>

                    <div class="text-center small text-muted">
                        Remembered your password? <a href="<?= BASE_URL ?>auth/login.php" class="fw-bold text-primary">Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
