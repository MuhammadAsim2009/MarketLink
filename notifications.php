<?php
/**
 * MarketLink - User In-App Notifications Center
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    set_flash('warning', 'Please log in to view your notifications.');
    redirect(BASE_URL . 'auth/login.php');
}

$user_id = get_logged_in_user_id();

// Handle Mark As Read / Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session.');
        redirect(BASE_URL . 'notifications.php');
    }

    $action = sanitize_input($_POST['action'] ?? '');
    $notif_id = (int)($_POST['notification_id'] ?? 0);

    try {
        if ($action === 'mark_all_read') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
            $stmt->execute([':uid' => $user_id]);
            set_flash('success', 'All notifications marked as read.');
        } elseif ($action === 'mark_read' && $notif_id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = :nid AND user_id = :uid");
            $stmt->execute([':nid' => $notif_id, ':uid' => $user_id]);
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

// Fetch all notifications for user
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([':uid' => $user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Notifications load error: " . $e->getMessage());
}

$page_title = 'Notifications';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-bell-fill text-primary me-2"></i>Notifications Center</h1>
            <p class="text-muted small mb-0">Updates on your pre-orders, stall approvals, and market announcements</p>
        </div>
        <?php if (!empty($notifications)): ?>
            <form action="<?= BASE_URL ?>notifications.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-check2-all me-1"></i> Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0 max-w-700 mx-auto">
        <div class="card-body p-0">
            <?php if (!empty($notifications)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                        <div class="list-group-item p-3 d-flex justify-content-between align-items-start <?= !$n['is_read'] ? 'bg-primary-subtle text-primary-emphasis' : '' ?>">
                            <div class="d-flex gap-3">
                                <div class="mt-1">
                                    <i class="bi <?= !$n['is_read'] ? 'bi-circle-fill text-primary' : 'bi-bell text-muted' ?>" style="font-size: 0.75rem;"></i>
                                </div>
                                <div>
                                    <p class="mb-1 small <?= !$n['is_read'] ? 'fw-semibold' : 'text-secondary' ?>"><?= e($n['message']) ?></p>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= format_datetime($n['created_at']) ?></small>
                                </div>
                            </div>
                            <div class="d-flex gap-1 ms-2">
                                <?php if (!$n['is_read']): ?>
                                    <form action="<?= BASE_URL ?>notifications.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-primary p-0" title="Mark Read">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form action="<?= BASE_URL ?>notifications.php" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-1" title="Delete">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bell-slash fs-1 text-secondary-subtle d-block mb-2"></i>
                    <h6>No notifications at the moment</h6>
                    <p class="small text-muted mb-0">When your pre-orders update or announcements are posted, they will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
