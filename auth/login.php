<?php
/**
 * MarketLink - User Login
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect_by_role(get_logged_in_user_role());
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
            $error = 'Please enter both email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id, name, email, password_hash, role, status FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $email_val]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Check status
                    if ($user['status'] === STATUS_SUSPENDED) {
                        $error = 'Your account has been suspended. Please contact the platform administrator.';
                    } else {
                        // Establish Session
                        $_SESSION['user_id'] = (int)$user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['email'] = $user['email'];

                        if ($user['status'] === STATUS_PENDING && $user['role'] === ROLE_FARMER) {
                            $_SESSION['is_pending_approval'] = true;
                        }

                        // Success notification & redirect
                        set_flash('success', 'Welcome back, ' . $user['name'] . '!');
                        redirect_by_role($user['role']);
                    }
                } else {
                    // Generic error per Security-Access.md
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                error_log("Login query error: " . $e->getMessage());
                $error = 'A database error occurred. Please try again later.';
            }
        }
    }
}

$page_title = 'Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-split-card">
        <div class="row g-0">
            <!-- Left Showcase Banner -->
            <div class="col-lg-5 d-none d-lg-block">
                <div class="auth-showcase">
                    <div>
                        <div class="auth-showcase-badge">
                            <i class="bi bi-flower1 text-warning"></i> MarketLink Platform
                        </div>
                        <h2>Fresh Harvest, Held Just For You.</h2>
                        <p class="lead-text">
                            Connect with verified local growers, reserve your weekend produce basket in advance, and pick up fresh without the queues.
                        </p>
                        
                        <div class="auth-features-list">
                            <div class="auth-feature-item">
                                <div class="auth-feature-icon">
                                    <i class="bi bi-basket3-fill"></i>
                                </div>
                                <div class="auth-feature-text">
                                    <h6>Live Harvest Pre-Orders</h6>
                                    <p>Never miss out on seasonal favorites and organic batches.</p>
                                </div>
                            </div>
                            <div class="auth-feature-item">
                                <div class="auth-feature-icon">
                                    <i class="bi bi-clock-fill"></i>
                                </div>
                                <div class="auth-feature-text">
                                    <h6>Express Weekend Pickup</h6>
                                    <p>Walk up to your farmer's stall at your selected time slot.</p>
                                </div>
                            </div>
                            <div class="auth-feature-item">
                                <div class="auth-feature-icon">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <div class="auth-feature-text">
                                    <h6>Interactive Market Maps</h6>
                                    <p>Discover neighborhood weekend markets and attending stalls.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trust Quote -->
                    <div class="auth-trust-box">
                        <div class="auth-trust-stars">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <p class="auth-trust-quote">"The easiest way to support local family farms while getting the freshest food every Saturday."</p>
                        <p class="auth-trust-author">— Sarah J., Verified Community Shopper</p>
                    </div>
                </div>
            </div>

            <!-- Right Form Section -->
            <div class="col-lg-7">
                <div class="auth-form-side">
                    <div class="auth-form-header">
                        <div class="d-inline-flex p-2 rounded-3 bg-primary-subtle text-primary mb-2">
                            <i class="bi bi-person-check fs-4"></i>
                        </div>
                        <h1 class="h3 fw-bold">Sign In to Your Account</h1>
                        <p class="text-muted small mb-0">Enter your credentials to access pre-orders, favorites, or stall management</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small mb-4 d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>auth/login.php" method="POST" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="email">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= e($email_val) ?>" required placeholder="name@example.com" autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0" for="password">Password</label>
                                <a href="<?= BASE_URL ?>auth/forgot-password.php" class="small text-muted text-decoration-underline">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" required placeholder="Enter your account password">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mb-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to MarketLink
                        </button>

                        <div class="text-center small text-muted mb-3">
                            Don't have an account yet? <a href="<?= BASE_URL ?>auth/register.php" class="fw-bold text-primary">Create an account</a>
                        </div>

                        <!-- Quick Demo Credentials Helper -->
                        <div class="demo-creds-box">
                            <div class="fw-semibold small text-muted mb-2 d-flex align-items-center justify-content-between">
                                <span><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Demo Access</span>
                                <span class="badge bg-white text-muted border">One-Click Fill</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary bg-white text-dark py-1 px-2" onclick="fillCreds('sarah.customer@marketlink.test', 'Customer@123')">
                                    <i class="bi bi-person me-1"></i> Customer
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary bg-white text-dark py-1 px-2" onclick="fillCreds('greenvalley@marketlink.test', 'Farmer@123')">
                                    <i class="bi bi-shop me-1"></i> Farmer Stall
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary bg-white text-dark py-1 px-2" onclick="fillCreds('admin@marketlink.test', 'Admin@123')">
                                    <i class="bi bi-shield-lock me-1"></i> Administrator
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
