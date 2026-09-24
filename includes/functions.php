<?php
/**
 * MarketLink - Shared Helper Functions
 * Plain PHP Procedural Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escapes output for safe HTML rendering (XSS protection)
 */
function e($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize basic string inputs
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return trim((string)$data);
}

/**
 * Generate CSRF token if not present in session
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF input field
 */
function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify CSRF token from POST request
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Set a session flash message
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Check if a flash message exists
 */
function has_flash() {
    return isset($_SESSION['flash']);
}

/**
 * Retrieve and clear the session flash message
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message alert if available
 */
function render_flash() {
    $flash = get_flash();
    if ($flash) {
        $alertType = $flash['type'] === 'error' ? 'danger' : $flash['type'];
        echo '<div class="alert alert-' . e($alertType) . ' alert-dismissible fade show my-3" role="alert">';
        echo e($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Check if user is currently authenticated
 */
function is_logged_in() {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/**
 * Get current logged in user ID
 */
function get_logged_in_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged in user role
 */
function get_logged_in_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current logged in user display name
 */
function get_logged_in_user_name() {
    return $_SESSION['user_name'] ?? 'User';
}

/**
 * Safe redirect to a given URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Redirect user to their respective dashboard based on role
 */
function redirect_by_role($role) {
    switch ($role) {
        case ROLE_ADMIN:
            redirect(BASE_URL . 'admin/dashboard.php');
            break;
        case ROLE_FARMER:
            redirect(BASE_URL . 'farmer/dashboard.php');
            break;
        case ROLE_CUSTOMER:
            redirect(BASE_URL . 'customer/dashboard.php');
            break;
        default:
            redirect(BASE_URL . 'index.php');
            break;
    }
}

/**
 * Format currency with configured symbol
 */
function format_currency($amount) {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * Format standard date
 */
function format_date($date) {
    if (empty($date)) return '—';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date(DATE_FORMAT, $timestamp);
}

/**
 * Format standard datetime
 */
function format_datetime($datetime) {
    if (empty($datetime)) return '—';
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    return date(DATETIME_FORMAT, $timestamp);
}

/**
 * Get styled badge HTML for status (Orders & Users)
 */
function get_status_badge($status) {
    $status = strtolower($status);
    $badgeClasses = [
        'placed'    => 'bg-secondary',
        'accepted'  => 'bg-primary',
        'ready'     => 'bg-warning text-dark',
        'completed' => 'bg-success',
        'declined'  => 'bg-danger',
        'cancelled' => 'bg-danger',
        'active'    => 'bg-success',
        'pending'   => 'bg-warning text-dark',
        'suspended' => 'bg-danger',
    ];
    $class = $badgeClasses[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $class . ' px-2 py-1 rounded-pill text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">' . e(ucfirst($status)) . '</span>';
}

/**
 * Create an in-app notification for a user
 */
function create_notification($pdo, $user_id, $message) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:user_id, :message, 0, NOW())");
        return $stmt->execute([
            ':user_id' => $user_id,
            ':message' => $message
        ]);
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Count unread notifications for a user
 */
function get_unread_notifications_count($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Format product/stall image URL (handles external web URLs, data URIs, and local relative paths)
 */
function get_image_url($url, $fallback = 'https://placehold.co/400x300?text=Produce') {
    if (empty($url)) {
        return $fallback;
    }
    // Check if it's already an external HTTP/HTTPS URL, protocol-relative URL, or data URI
    if (preg_match('#^(https?://|//|data:image/)#i', $url)) {
        return $url;
    }
    // Relative local path - trim leading slash to avoid duplicate slashes
    $clean_url = ltrim($url, '/\\');
    return BASE_URL . $clean_url;
}

