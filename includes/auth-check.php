<?php
/**
 * MarketLink - Authentication & Role Guard
 * Include at the top of all protected pages
 * Usage before include:
 *   $required_role = ROLE_CUSTOMER; // or ROLE_FARMER or ROLE_ADMIN or array(ROLE_CUSTOMER, ROLE_FARMER)
 *   require_once __DIR__ . '/../includes/auth-check.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// 1. Check if user is logged in
if (!is_logged_in()) {
    set_flash('warning', 'Please log in to access this page.');
    redirect(BASE_URL . 'auth/login.php');
}

$current_user_id = get_logged_in_user_id();
$current_role = get_logged_in_user_role();

// 2. Real-time active status check from database (handles immediate suspension by admin)
try {
    $status_stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = :user_id LIMIT 1");
    $status_stmt->execute([':user_id' => $current_user_id]);
    $user_record = $status_stmt->fetch();

    if (!$user_record) {
        // User record no longer exists
        session_unset();
        session_destroy();
        session_start();
        set_flash('danger', 'Your account could not be found. Please contact support.');
        redirect(BASE_URL . 'auth/login.php');
    }

    if ($user_record['status'] === STATUS_SUSPENDED) {
        // User has been suspended
        session_unset();
        session_destroy();
        session_start();
        set_flash('danger', 'Your account has been suspended by the platform administrator.');
        redirect(BASE_URL . 'auth/login.php');
    }

    if ($user_record['status'] === STATUS_PENDING && $current_role === ROLE_FARMER) {
        // Pending approval notification check
        $_SESSION['is_pending_approval'] = true;
    } else {
        unset($_SESSION['is_pending_approval']);
    }
} catch (PDOException $e) {
    error_log("Auth status check failed: " . $e->getMessage());
}

// 3. Verify role permission if $required_role is specified
if (isset($required_role)) {
    $has_permission = false;
    if (is_array($required_role)) {
        $has_permission = in_array($current_role, $required_role, true);
    } else {
        $has_permission = ($current_role === $required_role);
    }

    if (!$has_permission) {
        set_flash('danger', 'Access denied: You do not have permission to view that page.');
        redirect_by_role($current_role);
    }
}
