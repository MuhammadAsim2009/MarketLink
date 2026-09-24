<?php
/**
 * MarketLink - Reset Password Form
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$token = sanitize_input($_GET['token'] ?? ($_POST['token'] ?? ''));
$is_valid_token = false;
$reset_record = null;
$error = '';
$token_error = '';

if (empty($token)) {
    $token_error = 'No reset token provided. Please request a new password reset link.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT reset_id, email, expires_at FROM password_resets WHERE token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $reset_record = $stmt->fetch();

        if ($reset_record) {
            if (strtotime($reset_record['expires_at']) < time()) {
                $token_error = 'This link has expired or was already used, please request a new one.';
                // Clean up expired token
                $del = $pdo->prepare("DELETE FROM password_resets WHERE reset_id = :id");
                $del->execute([':id' => $reset_record['reset_id']]);
            } else {
                $is_valid_token = true;
            }
        } else {
            $token_error = 'This link has expired or was already used, please request a new one.';
        }
    } catch (PDOException $e) {
        error_log("Reset token verification error: " . $e->getMessage());
        $token_error = 'A database error occurred. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_valid_token) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo->beginTransaction();

                // Update user password
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $upd_stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
                $upd_stmt->execute([
                    ':hash' => $new_hash,
                    ':email' => $reset_record['email']
                ]);

                // Invalidate all tokens for this email (single-use)
                $del_stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
                $del_stmt->execute([':email' => $reset_record['email']]);

                $pdo->commit();

                set_flash('success', 'Your password has been successfully updated! You can now log in.');
                redirect(BASE_URL . 'auth/login.php');

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Password reset failed: " . $e->getMessage());
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
}

$page_title = 'Set New Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="card auth-card">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-2">
                    <i class="bi bi-shield-lock fs-3"></i>
                </div>
                <h1 class="h3 brand-font">Create New Password</h1>
                <p class="text-muted small">Choose a strong password for your account</p>
            </div>

            <?php if (!empty($token_error)): ?>
                <div class="alert alert-danger py-3 mb-4">
                    <i class="bi bi-exclamation-octagon me-1"></i> <?= e($token_error) ?>
                </div>
                <div class="text-center">
                    <a href="<?= BASE_URL ?>auth/forgot-password.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-arrow-repeat me-1"></i> Request New Reset Link
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-3"><?= e($error) ?></div>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>auth/reset-password.php" method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="password">New Password</label>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="At least 6 characters" autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Repeat new password">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                        <i class="bi bi-check2-circle me-1"></i> Update Password
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
