<?php
/**
 * MarketLink - User Registration (Customer & Farmer)
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect_by_role(get_logged_in_user_role());
}

$selected_role = isset($_GET['role']) && $_GET['role'] === 'farmer' ? ROLE_FARMER : ROLE_CUSTOMER;
$errors = [];
$form_data = [
    'role' => $selected_role,
    'name' => '',
    'email' => '',
    'phone' => '',
    'stall_name' => '',
    'address' => '',
    'operating_days' => ['Sat', 'Sun'],
    'pickup_window_start' => '08:00',
    'pickup_window_end' => '14:00',
    'order_cutoff_hours' => 2
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid form submission. Please try again.';
    } else {
        $role = sanitize_input($_POST['role'] ?? ROLE_CUSTOMER);
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $form_data['role'] = $role;
        $form_data['name'] = $name;
        $form_data['email'] = $email;
        $form_data['phone'] = $phone;

        if ($role === ROLE_FARMER) {
            $form_data['stall_name'] = sanitize_input($_POST['stall_name'] ?? '');
            $form_data['address'] = sanitize_input($_POST['address'] ?? '');
            $form_data['operating_days'] = isset($_POST['operating_days']) && is_array($_POST['operating_days']) ? $_POST['operating_days'] : [];
            $form_data['pickup_window_start'] = sanitize_input($_POST['pickup_window_start'] ?? '08:00');
            $form_data['pickup_window_end'] = sanitize_input($_POST['pickup_window_end'] ?? '14:00');
            $form_data['order_cutoff_hours'] = (int)($_POST['order_cutoff_hours'] ?? 2);
        }

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Full name is required.';
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required.';
        } else {
            // Check if email already registered
            $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
            $check_stmt->execute([':email' => $email]);
            if ($check_stmt->fetch()) {
                $errors['email'] = 'This email is already registered. Please login or use another email.';
            }
        }

        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters long.';
        }

        if ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($role === ROLE_FARMER) {
            if (empty($form_data['stall_name'])) {
                $errors['stall_name'] = 'Stall / Farm name is required.';
            }
            if (empty($form_data['address'])) {
                $errors['address'] = 'Farm / Stall location address is required.';
            }
        }

        // Process Registration if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $status = ($role === ROLE_FARMER) ? STATUS_PENDING : STATUS_ACTIVE;
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $user_stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, phone, role, status, created_at) VALUES (:name, :email, :password_hash, :phone, :role, :status, NOW())");
                $user_stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password_hash' => $password_hash,
                    ':phone' => $phone,
                    ':role' => $role,
                    ':status' => $status
                ]);
                $user_id = (int)$pdo->lastInsertId();

                if ($role === ROLE_FARMER) {
                    $days_str = implode(',', $form_data['operating_days']);
                    $profile_stmt = $pdo->prepare("INSERT INTO farmer_profiles (farmer_id, stall_name, address, operating_days, pickup_window_start, pickup_window_end, order_cutoff_hours) VALUES (:farmer_id, :stall_name, :address, :operating_days, :pickup_window_start, :pickup_window_end, :order_cutoff_hours)");
                    $profile_stmt->execute([
                        ':farmer_id' => $user_id,
                        ':stall_name' => $form_data['stall_name'],
                        ':address' => $form_data['address'],
                        ':operating_days' => $days_str,
                        ':pickup_window_start' => $form_data['pickup_window_start'],
                        ':pickup_window_end' => $form_data['pickup_window_end'],
                        ':order_cutoff_hours' => $form_data['order_cutoff_hours']
                    ]);
                }

                $pdo->commit();

                // Send Welcome Notification
                if ($role === ROLE_FARMER) {
                    create_notification($pdo, $user_id, "Welcome to MarketLink! Your farmer account has been registered and is pending approval from the platform administrator.");
                    set_flash('info', 'Registration submitted successfully! Your farmer stall is pending administrator review and approval.');
                } else {
                    create_notification($pdo, $user_id, "Welcome to MarketLink! Discover local fresh produce and pre-order from your favorite farmers.");
                    set_flash('success', 'Account created successfully! Please log in to start exploring.');
                }

                redirect(BASE_URL . 'auth/login.php');

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Registration failed: " . $e->getMessage());
                $errors['general'] = 'Registration could not be completed due to a database error. Please try again.';
            }
        }
    }
}

$page_title = 'Create an Account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="card auth-card">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-2">
                    <i class="bi bi-person-plus-fill fs-3"></i>
                </div>
                <h1 class="h3 brand-font">Join MarketLink</h1>
                <p class="text-muted small">Sign up to buy farm-fresh goods or sell your local harvest</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger py-2 small mb-3"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <!-- Role Switcher Tabs -->
            <ul class="nav nav-pills nav-fill mb-4 p-1 bg-light rounded border">
                <li class="nav-item">
                    <button type="button" class="nav-link py-2 <?= $form_data['role'] === ROLE_CUSTOMER ? 'active' : '' ?>" id="tab-customer" onclick="setRole('customer')">
                        <i class="bi bi-bag-heart me-1"></i> I am a Customer
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link py-2 <?= $form_data['role'] === ROLE_FARMER ? 'active' : '' ?>" id="tab-farmer" onclick="setRole('farmer')">
                        <i class="bi bi-shop me-1"></i> I am a Farmer / Stall
                    </button>
                </li>
            </ul>

            <form action="<?= BASE_URL ?>auth/register.php" method="POST" id="registerForm" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="role" id="roleInput" value="<?= e($form_data['role']) ?>">

                <!-- Common User Fields -->
                <div class="mb-3">
                    <label class="form-label" for="name"><span id="nameLabel"><?= $form_data['role'] === ROLE_FARMER ? 'Contact Person Name' : 'Full Name' ?></span> <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($form_data['name']) ?>" required placeholder="e.g. John Doe">
                    <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= e($form_data['email']) ?>" required placeholder="name@example.com">
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" value="<?= e($form_data['phone']) ?>" placeholder="03001234567">
                    </div>
                </div>

                <!-- Farmer-Specific Section -->
                <div id="farmerFields" style="<?= $form_data['role'] === ROLE_FARMER ? '' : 'display: none;' ?>">
                    <hr class="my-3 text-secondary-subtle">
                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-shop me-1"></i> Stall & Pickup Configuration</h6>

                    <div class="mb-3">
                        <label class="form-label" for="stall_name">Stall / Farm Brand Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['stall_name']) ? 'is-invalid' : '' ?>" id="stall_name" name="stall_name" value="<?= e($form_data['stall_name']) ?>" placeholder="e.g. Green Valley Organics (Stall #5)">
                        <?php if (isset($errors['stall_name'])): ?><div class="invalid-feedback"><?= e($errors['stall_name']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="address">Stall Location / Farm Address <span class="text-danger">*</span></label>
                        <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" id="address" name="address" rows="2" placeholder="e.g. Central Market Plaza, Row B, Stall 12"><?= e($form_data['address']) ?></textarea>
                        <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Operating Days</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php 
                            $all_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                            $selected_days = is_array($form_data['operating_days']) ? $form_data['operating_days'] : explode(',', $form_data['operating_days']);
                            foreach ($all_days as $day): 
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="operating_days[]" value="<?= $day ?>" id="day_<?= $day ?>" <?= in_array($day, $selected_days) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="day_<?= $day ?>"><?= $day ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="pickup_window_start">Pickup Start</label>
                            <input type="time" class="form-control" id="pickup_window_start" name="pickup_window_start" value="<?= e($form_data['pickup_window_start']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pickup_window_end">Pickup End</label>
                            <input type="time" class="form-control" id="pickup_window_end" name="pickup_window_end" value="<?= e($form_data['pickup_window_end']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="order_cutoff_hours" title="Minimum hours required before pickup slot">Cutoff (Hours)</label>
                            <input type="number" min="1" max="72" class="form-control" id="order_cutoff_hours" name="order_cutoff_hours" value="<?= e($form_data['order_cutoff_hours']) ?>">
                        </div>
                    </div>
                </div>

                <hr class="my-3 text-secondary-subtle">

                <!-- Passwords -->
                <div class="row g-2 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" required placeholder="At least 6 characters">
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" required placeholder="Repeat password">
                        <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div><?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                    <i class="bi bi-check-circle me-1"></i> Complete Registration
                </button>

                <div class="text-center small text-muted">
                    Already have an account? <a href="<?= BASE_URL ?>auth/login.php" class="fw-semibold">Log In here</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setRole(role) {
    document.getElementById('roleInput').value = role;
    const farmerFields = document.getElementById('farmerFields');
    const nameLabel = document.getElementById('nameLabel');
    const tabCustomer = document.getElementById('tab-customer');
    const tabFarmer = document.getElementById('tab-farmer');

    if (role === 'farmer') {
        farmerFields.style.display = 'block';
        nameLabel.textContent = 'Contact Person Name';
        tabFarmer.classList.add('active');
        tabCustomer.classList.remove('active');
    } else {
        farmerFields.style.display = 'none';
        nameLabel.textContent = 'Full Name';
        tabCustomer.classList.add('active');
        tabFarmer.classList.remove('active');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
