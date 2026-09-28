<?php
/**
 * MarketLink - Forgot Password Request
 * 
 * Verifies email from database, enforces a 5-attempt rate limit with 30-minute cooldown,
 * generates a secure single-use token, and delivers reset URL via SMTP (Port 587/465).
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

// Rate Limiting Configuration
define('MAX_RESET_ATTEMPTS', 5);
define('RESET_COOLDOWN_MINUTES', 30);
define('RESET_COOLDOWN_SECONDS', RESET_COOLDOWN_MINUTES * 60);

$error = '';
$mail_error = '';
$success = false;
$submitted_email = '';
$dev_reset_link = '';
$is_rate_limited = false;
$seconds_remaining = 0;
$attempts_used = 0;
$attempts_left = MAX_RESET_ATTEMPTS;

// Helper to get client IP address
function get_client_ip_address() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

$client_ip = get_client_ip_address();

// Ensure attempts table exists and prune old records
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_reset_attempts` (
        `attempt_id` INT AUTO_INCREMENT PRIMARY KEY,
        `ip_address` VARCHAR(45) NOT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_attempts_ip_time` (`ip_address`, `attempted_at`),
        INDEX `idx_attempts_email_time` (`email`, `attempted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Clean up attempts older than 24 hours
    $pdo->exec("DELETE FROM password_reset_attempts WHERE attempted_at < (NOW() - INTERVAL 24 HOUR)");

    // Query attempts within last 30 minutes for this IP
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as total_attempts, MAX(attempted_at) as latest_attempt 
                                 FROM password_reset_attempts 
                                 WHERE ip_address = :ip 
                                 AND attempted_at > (NOW() - INTERVAL " . (int)RESET_COOLDOWN_MINUTES . " MINUTE)");
    $check_stmt->execute([':ip' => $client_ip]);
    $attempt_data = $check_stmt->fetch();

    $attempts_used = (int)($attempt_data['total_attempts'] ?? 0);
    $attempts_left = max(0, MAX_RESET_ATTEMPTS - $attempts_used);

    if ($attempts_used >= MAX_RESET_ATTEMPTS && !empty($attempt_data['latest_attempt'])) {
        $last_attempt_ts = strtotime($attempt_data['latest_attempt']);
        $unlock_ts = $last_attempt_ts + RESET_COOLDOWN_SECONDS;
        $diff = $unlock_ts - time();

        if ($diff > 0) {
            $is_rate_limited = true;
            $seconds_remaining = $diff;
        } else {
            // Cooldown expired
            $attempts_used = 0;
            $attempts_left = MAX_RESET_ATTEMPTS;
        }
    }
} catch (PDOException $e) {
    error_log("Rate limiting database check error: " . $e->getMessage());
}

// Process Password Reset Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_rate_limited) {
        $error = 'Too many attempts. Password reset is temporarily locked. Please wait for the cooldown timer to expire.';
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request or expired session. Please refresh and try again.';
    } else {
        $email = sanitize_input($_POST['email'] ?? '');
        $submitted_email = $email;

        // Record attempt immediately in database
        try {
            $rec_stmt = $pdo->prepare("INSERT INTO password_reset_attempts (ip_address, email, attempted_at) VALUES (:ip, :email, NOW())");
            $rec_stmt->execute([
                ':ip' => $client_ip,
                ':email' => !empty($email) ? $email : null
            ]);
            $attempts_used++;
            $attempts_left = max(0, MAX_RESET_ATTEMPTS - $attempts_used);

            // Re-evaluate rate limit immediately after recording
            if ($attempts_used >= MAX_RESET_ATTEMPTS) {
                $is_rate_limited = true;
                $seconds_remaining = RESET_COOLDOWN_SECONDS;
            }
        } catch (PDOException $e) {
            error_log("Failed to log password reset attempt: " . $e->getMessage());
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else {
            try {
                // Step 1: Verify that email exists in database
                $stmt = $pdo->prepare("SELECT user_id, name, status, role FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'No registered account found with email "' . htmlspecialchars($email) . '".';
                } elseif ($user['status'] === STATUS_SUSPENDED) {
                    $error = 'This account has been suspended. Please contact platform administration.';
                } else {
                    // Step 2: Invalidate previous unused tokens for this email
                    $del_stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
                    $del_stmt->execute([':email' => $email]);

                    // Step 3: Generate secure 30-minute token
                    $token = bin2hex(random_bytes(32));
                    $expires_minutes = 30;
                    $expires_at = date('Y-m-d H:i:s', time() + ($expires_minutes * 60));

                    $ins_stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (:email, :token, :expires_at, NOW())");
                    $ins_stmt->execute([
                        ':email' => $email,
                        ':token' => $token,
                        ':expires_at' => $expires_at
                    ]);

                    // Step 4: Construct password reset URL
                    $reset_url = BASE_URL . 'auth/reset-password.php?token=' . urlencode($token);

                    // Step 5: Deliver via SMTP
                    $mail_result = send_password_reset_email($email, $user['name'], $reset_url, $expires_minutes);

                    if ($mail_result['success']) {
                        $success = true;
                    } else {
                        $success = true;
                        $mail_error = $mail_result['message'];
                        $dev_reset_link = $reset_url; // Fallback link for local environment
                    }
                }
            } catch (PDOException $e) {
                error_log("Password reset request error: " . $e->getMessage());
                $error = 'A database error occurred while processing your request. Please try again.';
            }
        }
    }
}

$page_title = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container py-5" style="max-width: 540px; margin: 0 auto;">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 rounded-circle <?= $is_rate_limited ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' ?> mb-3">
                    <i class="bi <?= $is_rate_limited ? 'bi-shield-x' : 'bi-shield-lock-fill' ?> fs-2"></i>
                </div>
                <h1 class="h3 fw-bold mb-1">Reset Your Password</h1>
                <p class="text-muted small">Enter your registered email address to receive your password reset URL</p>
            </div>

            <!-- Rate Limit / Cooldown Active Banner -->
            <?php if ($is_rate_limited): ?>
                <div class="alert alert-danger p-4 mb-4 rounded-4 border-2 border-danger-subtle shadow-sm">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-exclamation-octagon-fill fs-3 text-danger"></i>
                        <h6 class="alert-heading fw-bold mb-0 text-danger">Security Cooldown Active</h6>
                    </div>
                    <p class="small mb-3 text-dark">
                        You have exceeded the maximum limit of <strong><?= MAX_RESET_ATTEMPTS ?> attempts</strong>. To protect accounts from unauthorized access, password resets from this device are locked for <strong><?= RESET_COOLDOWN_MINUTES ?> minutes</strong>.
                    </p>
                    <div class="p-3 bg-white rounded-3 border d-flex align-items-center justify-content-between">
                        <span class="small fw-semibold text-muted"><i class="bi bi-clock-history me-1 text-danger"></i> Unlocks In:</span>
                        <span id="cooldownTimer" class="badge bg-danger text-white fs-6 font-monospace px-3 py-2 rounded-pill shadow-xs">
                            <?= sprintf('%02d:%02d', floor($seconds_remaining / 60), $seconds_remaining % 60) ?>
                        </span>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Return to Login
                    </a>
                </div>

                <script>
                // Live Cooldown Countdown Timer
                (function() {
                    let remaining = <?= (int)$seconds_remaining ?>;
                    const timerEl = document.getElementById('cooldownTimer');
                    
                    const interval = setInterval(function() {
                        remaining--;
                        if (remaining <= 0) {
                            clearInterval(interval);
                            if (timerEl) timerEl.textContent = '00:00 - Unlocked';
                            setTimeout(() => { window.location.reload(); }, 1500);
                        } else {
                            const mins = Math.floor(remaining / 60);
                            const secs = remaining % 60;
                            if (timerEl) {
                                timerEl.textContent = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
                            }
                        }
                    }, 1000);
                })();
                </script>

            <?php else: ?>

                <!-- Error Alert -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-3 mb-4 rounded-3 d-flex align-items-start gap-2">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                        <div class="w-100">
                            <strong>Verification Failed:</strong><br>
                            <?= e($error) ?>
                            <?php if ($attempts_used > 0 && $attempts_used < MAX_RESET_ATTEMPTS): ?>
                                <div class="mt-2 pt-2 border-top border-danger-subtle small text-danger-emphasis d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-shield-exclamation me-1"></i> Attempts used: <strong><?= $attempts_used ?> / <?= MAX_RESET_ATTEMPTS ?></strong></span>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= $attempts_left ?> left</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Success Screen -->
                <?php if ($success): ?>
                    <div class="alert alert-success py-3 mb-4 rounded-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-envelope-check-fill fs-4 text-success"></i>
                            <h6 class="alert-heading fw-bold mb-0">Reset Link Sent Successfully!</h6>
                        </div>
                        <p class="small mb-2">
                            A secure password reset link has been dispatched to <strong><?= e($submitted_email) ?></strong> via our SMTP server.
                        </p>
                        <ul class="small mb-0 ps-3 text-muted">
                            <li>Check your inbox and click the reset button.</li>
                            <li>If not received within a minute, check your <strong>Spam / Junk</strong> folder.</li>
                            <li>This link will expire in <strong>30 minutes</strong>.</li>
                        </ul>
                    </div>

                    <?php if (!empty($mail_error) && !empty($dev_reset_link)): ?>
                        <div class="p-3 bg-warning-subtle text-dark border border-warning rounded-3 mb-4">
                            <div class="small fw-bold text-warning-emphasis mb-1">
                                <i class="bi bi-info-circle-fill me-1"></i> SMTP Notice (Local Environment)
                            </div>
                            <p class="small mb-2" style="font-size: 0.8rem;">
                                SMTP status: <em><?= e($mail_error) ?></em>. Direct link for local testing:
                            </p>
                            <a href="<?= e($dev_reset_link) ?>" class="btn btn-primary btn-sm w-100 text-truncate">
                                <i class="bi bi-arrow-right-circle me-1"></i> Proceed to Reset Password Form
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex flex-column gap-2 mt-4">
                        <a href="<?= BASE_URL ?>auth/forgot-password.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-repeat me-1"></i> Send to Another Email
                        </a>
                        <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-light btn-sm text-muted">
                            <i class="bi bi-arrow-left me-1"></i> Back to Login
                        </a>
                    </div>

                <?php else: ?>

                    <!-- Form -->
                    <form action="<?= BASE_URL ?>auth/forgot-password.php" method="POST" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0" for="email">Registered Email Address <span class="text-danger">*</span></label>
                                <span class="badge bg-light text-muted border" title="Security attempt limit">
                                    <i class="bi bi-shield-lock me-1"></i> Max 5 attempts / 30m
                                </span>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" required placeholder="name@example.com" value="<?= e($submitted_email) ?>" autofocus>
                            </div>
                            <div class="form-text small text-muted">
                                <i class="bi bi-shield-check text-success me-1"></i> We'll verify your email against registered accounts before sending.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3 rounded-3 shadow-sm">
                            <i class="bi bi-send-fill me-1"></i> Send Password Reset Link
                        </button>

                        <div class="text-center small text-muted mt-3">
                            Remembered your credentials? <a href="<?= BASE_URL ?>auth/login.php" class="fw-bold text-primary text-decoration-none">Back to Login</a>
                        </div>
                    </form>

                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
