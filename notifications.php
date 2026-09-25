<?php
/**
 * MarketLink - User In-App Notifications Center (SaaS Design)
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    set_flash('warning', 'Please log in to view your notifications.');
    redirect(BASE_URL . 'auth/login.php');
}

$user_id = get_logged_in_user_id();
$current_role = get_logged_in_user_role();

// Helper for relative timestamps
function notif_time_ago($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $time);
}

// Helper to determine category, icons and styling
function get_notif_meta($message) {
    $msg_lower = strtolower($message);
    if (str_contains($msg_lower, 'order') || str_contains($msg_lower, 'pickup') || str_contains($msg_lower, 'basket') || str_contains($msg_lower, 'harvest')) {
        return [
            'category' => 'order',
            'badge' => 'Order',
            'badge_class' => 'badge-success',
            'icon' => 'bi-bag-check-fill',
            'icon_bg' => 'background: rgba(46,125,79,.12); color: var(--primary);'
        ];
    } elseif (str_contains($msg_lower, 'approved') || str_contains($msg_lower, 'account') || str_contains($msg_lower, 'profile') || str_contains($msg_lower, 'suspended') || str_contains($msg_lower, 'password')) {
        return [
            'category' => 'account',
            'badge' => 'Account',
            'badge_class' => 'badge-info',
            'icon' => 'bi-shield-check-fill',
            'icon_bg' => 'background: rgba(43,114,186,.12); color: #2B72BA;'
        ];
    } elseif (str_contains($msg_lower, 'announcement') || str_contains($msg_lower, 'broadcast') || str_contains($msg_lower, 'market') || str_contains($msg_lower, 'festival')) {
        return [
            'category' => 'announcement',
            'badge' => 'Market',
            'badge_class' => 'badge-warning',
            'icon' => 'bi-megaphone-fill',
            'icon_bg' => 'background: rgba(235,142,39,.12); color: #EB8E27;'
        ];
    } elseif (str_contains($msg_lower, 'review') || str_contains($msg_lower, 'rating') || str_contains($msg_lower, 'reply') || str_contains($msg_lower, 'feedback')) {
        return [
            'category' => 'review',
            'badge' => 'Review',
            'badge_class' => 'badge-primary',
            'icon' => 'bi-star-fill',
            'icon_bg' => 'background: rgba(244,123,62,.12); color: var(--accent);'
        ];
    }
    return [
        'category' => 'system',
        'badge' => 'System',
        'badge_class' => 'badge-neutral',
        'icon' => 'bi-bell-fill',
        'icon_bg' => 'background: var(--surface-2); color: var(--text-2);'
    ];
}

// Handle Mark As Read / Delete / Clear Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request session.');
        redirect(BASE_URL . 'notifications.php');
    }

    $action = sanitize_input($_POST['action'] ?? '');
    $notif_id = (int)($_POST['notification_id'] ?? 0);

    try {
        if ($action === 'mark_all_read') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
            $stmt->execute([':uid' => $user_id]);
            set_flash('success', 'All notifications marked as read.');
        } elseif ($action === 'clear_read') {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = :uid AND is_read = 1");
            $stmt->execute([':uid' => $user_id]);
            set_flash('info', 'All read notifications have been cleared.');
        } elseif ($action === 'mark_read' && $notif_id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = :nid AND user_id = :uid");
            $stmt->execute([':nid' => $notif_id, ':uid' => $user_id]);
            set_flash('success', 'Notification marked as read.');
        } elseif ($action === 'delete' && $notif_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE notification_id = :nid AND user_id = :uid");
            $stmt->execute([':nid' => $notif_id, ':uid' => $user_id]);
            set_flash('info', 'Notification removed.');
        }
    } catch (PDOException $e) {
        error_log("Notification update error: " . $e->getMessage());
    }
    redirect(BASE_URL . 'notifications.php');
}

// Active filter (all, unread, orders, system)
$filter = sanitize_input($_GET['filter'] ?? 'all');

// Fetch all notifications for user
$all_notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 60");
    $stmt->execute([':uid' => $user_id]);
    $all_notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Notifications load error: " . $e->getMessage());
}

// Calculate summary stats
$total_count = count($all_notifications);
$unread_count = 0;
$read_count = 0;
$order_count = 0;

foreach ($all_notifications as $n) {
    if (!$n['is_read']) $unread_count++;
    else $read_count++;
    
    $meta = get_notif_meta($n['message']);
    if ($meta['category'] === 'order') $order_count++;
}

// Filter list
$filtered_notifications = array_filter($all_notifications, function($n) use ($filter) {
    if ($filter === 'unread') return !$n['is_read'];
    if ($filter === 'orders') {
        $meta = get_notif_meta($n['message']);
        return $meta['category'] === 'order';
    }
    if ($filter === 'announcements') {
        $meta = get_notif_meta($n['message']);
        return in_array($meta['category'], ['announcement', 'system']);
    }
    return true;
});

$is_farmer = ($current_role === ROLE_FARMER);
$is_admin = ($current_role === ROLE_ADMIN);
$page_title = 'Notifications Center';
$active_nav = 'notifications';

if ($is_farmer) {
    require_once __DIR__ . '/farmer/includes/header.php';
} elseif ($is_admin) {
    require_once __DIR__ . '/admin/includes/header.php';
} else {
    require_once __DIR__ . '/includes/header.php';
}
?>

<?php if (!$is_farmer && !$is_admin): ?>
<div class="container py-4">
<?php endif; ?>

    <!-- Breadcrumb & Top Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge badge-primary px-2 py-1"><i class="bi bi-bell-fill me-1"></i> Inbox</span>
                <?php if ($unread_count > 0): ?>
                    <span class="badge badge-danger px-2 py-1"><?= $unread_count ?> Unread</span>
                <?php else: ?>
                    <span class="badge badge-success px-2 py-1"><i class="bi bi-check-circle me-1"></i> All Caught Up</span>
                <?php endif; ?>
            </div>
            <h1 class="h3 fw-bold mb-1">Notifications Center</h1>
            <p class="text-muted small mb-0">Real-time status updates on your <?= $is_farmer ? 'pre-orders, harvest inventory, customer reviews, and market bulletins' : 'pre-orders, market schedules, and announcements' ?></p>
        </div>

        <!-- Global Action Controls -->
        <div class="d-flex align-items-center gap-2">
            <?php if ($unread_count > 0): ?>
                <form action="<?= BASE_URL ?>notifications.php" method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check2-all me-1"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($read_count > 0): ?>
                <form action="<?= BASE_URL ?>notifications.php" method="POST" class="d-inline" onsubmit="return confirm('Clear all read notifications?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_read">
                    <button type="submit" class="btn btn-ghost btn-sm text-muted" title="Clear read notifications">
                        <i class="bi bi-trash3 me-1"></i> Clear Read
                    </button>
                </form>
            <?php endif; ?>

            <a href="<?= $current_role === ROLE_FARMER ? BASE_URL . 'farmer/dashboard.php' : ($current_role === ROLE_ADMIN ? BASE_URL . 'admin/dashboard.php' : BASE_URL . 'customer/dashboard.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Notifications Feed Column -->
        <div class="col-lg-8">
            <!-- Filter Pills -->
            <div class="d-flex align-items-center gap-2 mb-3 overflow-x-auto pb-1">
                <a href="<?= BASE_URL ?>notifications.php?filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill px-3">
                    All <span class="badge <?= $filter === 'all' ? 'bg-white text-dark' : 'badge-neutral' ?> ms-1"><?= $total_count ?></span>
                </a>
                <a href="<?= BASE_URL ?>notifications.php?filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill px-3">
                    Unread <span class="badge <?= $filter === 'unread' ? 'bg-white text-dark' : 'badge-danger' ?> ms-1"><?= $unread_count ?></span>
                </a>
                <a href="<?= BASE_URL ?>notifications.php?filter=orders" class="btn btn-sm <?= $filter === 'orders' ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill px-3">
                    <i class="bi bi-bag-check me-1"></i> Orders <span class="badge <?= $filter === 'orders' ? 'bg-white text-dark' : 'badge-neutral' ?> ms-1"><?= $order_count ?></span>
                </a>
                <a href="<?= BASE_URL ?>notifications.php?filter=announcements" class="btn btn-sm <?= $filter === 'announcements' ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill px-3">
                    <i class="bi bi-megaphone me-1"></i> Announcements
                </a>
            </div>

            <!-- Notification Items List -->
            <?php if (!empty($filtered_notifications)): ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($filtered_notifications as $n): 
                        $meta = get_notif_meta($n['message']);
                        $is_unread = !$n['is_read'];
                    ?>
                        <div class="card border transition-all <?= $is_unread ? 'border-primary-subtle shadow-xs' : 'shadow-none' ?>" style="<?= $is_unread ? 'background: #FAFDFC; border-left: 4px solid var(--primary) !important;' : 'border-left: 4px solid transparent;' ?>">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-start gap-3">
                                    <!-- Dynamic Category Icon Avatar -->
                                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; <?= $meta['icon_bg'] ?>">
                                        <i class="bi <?= $meta['icon'] ?>" style="font-size: 1.1rem;"></i>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= $meta['badge_class'] ?> px-2 py-0" style="font-size: .7rem;"><?= $meta['badge'] ?></span>
                                                <?php if ($is_unread): ?>
                                                    <span class="badge bg-primary px-1 py-0" style="font-size: .65rem; border-radius: 4px;">NEW</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted notif-timestamp" data-timestamp="<?= strtotime($n['created_at']) ?>" title="<?= format_datetime($n['created_at']) ?>">
                                                <i class="bi bi-clock me-1" style="font-size: .75rem;"></i><span class="time-ago-val"><?= notif_time_ago($n['created_at']) ?></span>
                                            </small>
                                        </div>

                                        <p class="mb-0 small text-dark <?= $is_unread ? 'fw-semibold' : '' ?>" style="line-height: 1.45;">
                                            <?= e($n['message']) ?>
                                        </p>
                                    </div>

                                    <!-- Action Controls -->
                                    <div class="d-flex align-items-center gap-1 flex-shrink-0 ms-1">
                                        <?php if ($is_unread): ?>
                                            <form action="<?= BASE_URL ?>notifications.php" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm p-1 text-primary" title="Mark as read" style="width: 28px; height: 28px; border-radius: 6px;">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form action="<?= BASE_URL ?>notifications.php" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm p-1 text-muted" title="Delete notification" style="width: 28px; height: 28px; border-radius: 6px;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- Empty State -->
                <div class="card border shadow-xs py-5 px-4 text-center">
                    <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: var(--surface-2); color: var(--text-3);">
                        <i class="bi bi-bell-slash fs-2"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-1">No notifications found</h4>
                    <p class="text-muted small mb-4 max-w-600 mx-auto">
                        <?= $filter === 'unread' ? 'You are all caught up! There are no unread notifications waiting for your attention.' : 'You do not have any notifications matching this filter yet.' ?>
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <?php if ($filter !== 'all'): ?>
                            <a href="<?= BASE_URL ?>notifications.php?filter=all" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-list-ul me-1"></i> View All Notifications
                            </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-basket me-1"></i> Browse Farmers Market
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Side Info & Quick Shortcuts -->
        <div class="col-lg-4">
            <!-- Account Summary Card -->
            <div class="card border shadow-xs mb-3">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 fw-bold fs-6"><i class="bi bi-sliders me-2 text-primary"></i>Notification Preferences</h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3 mb-3 p-2 bg-light rounded border">
                        <div class="user-avatar" style="width: 38px; height: 38px; font-size: .9rem;">
                            <?= strtoupper(mb_substr(get_logged_in_user_name(), 0, 1)) ?>
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate small"><?= e(get_logged_in_user_name()) ?></div>
                            <span class="badge badge-primary px-2 py-0 text-uppercase" style="font-size: .65rem;"><?= e($current_role) ?></span>
                        </div>
                    </div>

                    <div class="small text-muted mb-3">
                        Notifications help keep you updated with live order transitions, farm-gate stall confirmations, and official market weather bulletins.
                    </div>

                    <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                        <li class="d-flex align-items-center text-success">
                            <i class="bi bi-check-circle-fill me-2"></i> In-App Pre-Order Alerts
                        </li>
                        <li class="d-flex align-items-center text-success">
                            <i class="bi bi-check-circle-fill me-2"></i> Ready for Pickup Ping
                        </li>
                        <li class="d-flex align-items-center text-success">
                            <i class="bi bi-check-circle-fill me-2"></i> Farmer Review Responses
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Quick Action Shortcuts Card -->
            <div class="card border shadow-xs">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0 fw-bold fs-6"><i class="bi bi-lightning-charge text-accent me-2"></i><?= $is_farmer ? 'Producer Shortcuts' : 'Quick Actions' ?></h6>
                </div>
                <div class="card-body p-3 d-flex flex-column gap-2">
                    <?php if ($is_farmer): ?>
                        <a href="<?= BASE_URL ?>farmer/orders.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                            <i class="bi bi-receipt me-2"></i> Pre-Orders Queue
                        </a>
                        <a href="<?= BASE_URL ?>farmer/products.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                            <i class="bi bi-box-seam me-2"></i> Harvest Catalog
                        </a>
                        <a href="<?= BASE_URL ?>farmer/reviews.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                            <i class="bi bi-star me-2"></i> Customer Reviews
                        </a>
                        <a href="<?= BASE_URL ?>farmer/profile.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                            <i class="bi bi-shop me-2"></i> Stall Settings
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>customer/orders.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                            <i class="bi bi-bag-check me-2"></i> Track Active Orders
                        </a>
                        <a href="<?= BASE_URL ?>customer/browse-markets.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                            <i class="bi bi-geo-alt me-2"></i> Explore Weekend Markets
                        </a>
                        <a href="<?= BASE_URL ?>customer/favorites.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                            <i class="bi bi-heart me-2"></i> Saved Stalls & Harvest
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php if (!$is_farmer && !$is_admin): ?>
</div>
<?php endif; ?>

<script>
// Dynamic live relative timestamp ticker for notifications
(function() {
    function formatTimeAgo(timestampSec) {
        const nowSec = Math.floor(Date.now() / 1000);
        const diff = Math.max(0, nowSec - timestampSec);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        if (diff < 172800) return 'Yesterday';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';

        const d = new Date(timestampSec * 1000);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    function updateAllTimestamps() {
        document.querySelectorAll('.notif-timestamp').forEach(function(el) {
            const ts = parseInt(el.getAttribute('data-timestamp'), 10);
            if (!isNaN(ts)) {
                const valEl = el.querySelector('.time-ago-val');
                if (valEl) {
                    valEl.textContent = formatTimeAgo(ts);
                }
            }
        });
    }

    // Refresh timestamps live every 30 seconds
    setInterval(updateAllTimestamps, 30000);
})();
</script>

<?php 
if ($is_farmer) {
    require_once __DIR__ . '/farmer/includes/footer.php';
} elseif ($is_admin) {
    require_once __DIR__ . '/admin/includes/footer.php';
} else {
    require_once __DIR__ . '/includes/footer.php';
}
?>
