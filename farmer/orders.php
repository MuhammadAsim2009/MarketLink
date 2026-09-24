<?php
/**
 * MarketLink - Farmer Pre-Order Management
 */

$required_role = 'farmer';
require_once __DIR__ . '/../includes/auth-check.php';

$farmer_id = get_logged_in_user_id();
$view_order_id = (int)($_GET['order_id'] ?? 0);
$status_filter = sanitize_input($_GET['status'] ?? 'all');

// Handle Order Status Transitions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid submission. Please try again.');
        redirect(BASE_URL . 'farmer/orders.php');
    }

    $target_order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = sanitize_input($_POST['new_status'] ?? '');
    $valid_statuses = [ORDER_STATUS_ACCEPTED, ORDER_STATUS_DECLINED, ORDER_STATUS_READY, ORDER_STATUS_COMPLETED];

    if ($target_order_id > 0 && in_array($new_status, $valid_statuses, true)) {
        try {
            // Strict Row-Level check: order must belong to this farmer
            $chk_stmt = $pdo->prepare("SELECT o.order_id, o.customer_id, o.status, fp.stall_name, u.name as farmer_name 
                                       FROM orders o 
                                       JOIN users u ON o.farmer_id = u.user_id 
                                       LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                                       WHERE o.order_id = :oid AND o.farmer_id = :fid LIMIT 1");
            $chk_stmt->execute([':oid' => $target_order_id, ':fid' => $farmer_id]);
            $order = $chk_stmt->fetch();

            if (!$order) {
                set_flash('danger', 'Order not found or unauthorized.');
                redirect(BASE_URL . 'farmer/orders.php');
            }

            $current_status = $order['status'];
            $stall_name = $order['stall_name'] ?: $order['farmer_name'];

            // Validate transition rules
            $can_update = false;
            if ($new_status === ORDER_STATUS_ACCEPTED && $current_status === ORDER_STATUS_PLACED) {
                $can_update = true;
                $notif_msg = "Great news! Your pre-order #{$target_order_id} has been accepted by {$stall_name}.";
            } elseif ($new_status === ORDER_STATUS_DECLINED && $current_status === ORDER_STATUS_PLACED) {
                $can_update = true;
                $notif_msg = "Your pre-order #{$target_order_id} could not be accepted by {$stall_name}. Any reserved stock has been released.";
                // Return stock back to products
                $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :oid");
                $items_stmt->execute([':oid' => $target_order_id]);
                $items = $items_stmt->fetchAll();
                foreach ($items as $item) {
                    $rst = $pdo->prepare("UPDATE products SET quantity_available = quantity_available + :qty WHERE product_id = :pid");
                    $rst->execute([':qty' => $item['quantity'], ':pid' => $item['product_id']]);
                }
            } elseif ($new_status === ORDER_STATUS_READY && ($current_status === ORDER_STATUS_ACCEPTED || $current_status === ORDER_STATUS_PLACED)) {
                $can_update = true;
                $notif_msg = "Your pre-order #{$target_order_id} is freshly packed and READY for pickup at {$stall_name}!";
            } elseif ($new_status === ORDER_STATUS_COMPLETED && ($current_status === ORDER_STATUS_READY || $current_status === ORDER_STATUS_ACCEPTED)) {
                $can_update = true;
                $notif_msg = "Pre-order #{$target_order_id} marked as completed. Thank you for supporting local farmers! Please consider leaving a review.";
            }

            if ($can_update) {
                $upd = $pdo->prepare("UPDATE orders SET status = :status WHERE order_id = :oid AND farmer_id = :fid");
                $upd->execute([
                    ':status' => $new_status,
                    ':oid' => $target_order_id,
                    ':fid' => $farmer_id
                ]);

                // Create notification for customer
                create_notification($pdo, $order['customer_id'], $notif_msg);

                set_flash('success', "Order #{$target_order_id} updated to " . ucfirst($new_status) . ".");
            } else {
                set_flash('warning', "Cannot change order #{$target_order_id} from " . ucfirst($current_status) . " to " . ucfirst($new_status) . ".");
            }
        } catch (PDOException $e) {
            error_log("Order status update error: " . $e->getMessage());
            set_flash('danger', 'Database error while updating order.');
        }
    }
    redirect(BASE_URL . 'farmer/orders.php' . ($view_order_id ? '?order_id=' . $view_order_id : ''));
}

// Fetch single order details if requested
$selected_order = null;
$selected_order_items = [];
if ($view_order_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
                              FROM orders o 
                              JOIN users u ON o.customer_id = u.user_id 
                              WHERE o.order_id = :oid AND o.farmer_id = :fid LIMIT 1");
        $stmt->execute([':oid' => $view_order_id, ':fid' => $farmer_id]);
        $selected_order = $stmt->fetch();

        if ($selected_order) {
            $it_stmt = $pdo->prepare("SELECT oi.*, p.name as product_name, p.unit, p.image_url 
                                     FROM order_items oi 
                                     JOIN products p ON oi.product_id = p.product_id 
                                     WHERE oi.order_id = :oid");
            $it_stmt->execute([':oid' => $view_order_id]);
            $selected_order_items = $it_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Fetch order detail error: " . $e->getMessage());
    }
}

// Fetch all orders for this farmer with status filtering
$sql = "SELECT o.*, u.name as customer_name, u.phone as customer_phone, 
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count 
        FROM orders o 
        JOIN users u ON o.customer_id = u.user_id 
        WHERE o.farmer_id = :fid";
$params = [':fid' => $farmer_id];

if ($status_filter !== 'all' && !empty($status_filter)) {
    $sql .= " AND o.status = :status";
    $params[':status'] = $status_filter;
}
$sql .= " ORDER BY o.created_at DESC";

$orders_list = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch farmer orders list error: " . $e->getMessage());
}

// Fetch count of orders by status for filter pill badges
$counts = [
    'all' => 0,
    'placed' => 0,
    'accepted' => 0,
    'ready' => 0,
    'completed' => 0,
    'declined' => 0
];
try {
    $c_stmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM orders WHERE farmer_id = :fid GROUP BY status");
    $c_stmt->execute([':fid' => $farmer_id]);
    $rows = $c_stmt->fetchAll();
    foreach ($rows as $r) {
        $st = $r['status'];
        $cnt = (int)$r['cnt'];
        $counts['all'] += $cnt;
        if (isset($counts[$st])) {
            $counts[$st] = $cnt;
        }
    }
} catch (PDOException $e) {}

$active_nav = 'orders';
$page_title = 'Pre-Orders & Pickup Queue';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Pre-Orders Queue</h1>
        <p class="text-muted small mb-0">Accept incoming reservations, prepare farm packs, and record completed stall pickups</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>farmer/dashboard.php" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard Hub
        </a>
    </div>
</div>

<!-- SaaS Interactive Filter Pills -->
<div class="d-flex flex-wrap gap-2 mb-4 p-2 bg-white rounded-4 border shadow-xs">
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'all' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>farmer/orders.php?status=all">
        All Orders <span class="badge <?= $status_filter === 'all' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['all'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'placed' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>farmer/orders.php?status=placed">
        <i class="bi bi-bell-fill text-warning me-1"></i> Placed <span class="badge <?= $status_filter === 'placed' ? 'bg-white text-primary' : ($counts['placed'] > 0 ? 'bg-danger text-white' : 'bg-light text-secondary border') ?> ms-1"><?= $counts['placed'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'accepted' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>farmer/orders.php?status=accepted">
        Accepted <span class="badge <?= $status_filter === 'accepted' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['accepted'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'ready' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>farmer/orders.php?status=ready">
        Ready for Pickup <span class="badge <?= $status_filter === 'ready' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['ready'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'completed' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>farmer/orders.php?status=completed">
        Completed <span class="badge <?= $status_filter === 'completed' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['completed'] ?></span>
    </a>
</div>

<div class="row g-4">
    <!-- Orders List Column -->
    <div class="<?= $selected_order ? 'col-lg-7' : 'col-12' ?>">
        <div class="card shadow-xs border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0 fs-6 fw-bold">Orders List (<?= count($orders_list) ?>)</h5>
            </div>
                <div class="card-body p-0">
                    <?php if (!empty($orders_list)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Pickup Schedule</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders_list as $ord): ?>
                                        <tr class="<?= ($view_order_id === (int)$ord['order_id']) ? 'table-primary' : '' ?>">
                                            <td><strong>#<?= $ord['order_id'] ?></strong></td>
                                            <td>
                                                <div class="fw-semibold"><?= e($ord['customer_name']) ?></div>
                                                <small class="text-muted"><?= e($ord['customer_phone'] ?: '—') ?></small>
                                            </td>
                                            <td>
                                                <div><?= format_date($ord['pickup_date']) ?></div>
                                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($ord['pickup_slot']) ?></small>
                                            </td>
                                            <td class="fw-bold text-primary"><?= format_currency($ord['total_amount']) ?></td>
                                            <td><?= get_status_badge($ord['status']) ?></td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>farmer/orders.php?order_id=<?= $ord['order_id'] ?>&status=<?= urlencode($status_filter) ?>" class="btn btn-sm <?= ($view_order_id === (int)$ord['order_id']) ? 'btn-primary' : 'btn-light border' ?>">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 text-secondary-subtle d-block mb-2"></i>
                            <h6>No pre-orders found for this filter</h6>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Selected Order Detail Panel -->
        <?php if ($selected_order): ?>
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 sticky-top" style="top: 85px;">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <span class="fw-bold fs-6">Order Details #<?= $selected_order['order_id'] ?></span>
                        <a href="<?= BASE_URL ?>farmer/orders.php?status=<?= urlencode($status_filter) ?>" class="btn-close btn-close-white" aria-label="Close"></a>
                    </div>
                    <div class="card-body p-4">
                        <!-- Customer Info Box -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-0"><?= e($selected_order['customer_name']) ?></h6>
                                    <small class="text-muted"><?= e($selected_order['customer_email']) ?></small>
                                </div>
                                <div>
                                    <?= get_status_badge($selected_order['status']) ?>
                                </div>
                            </div>
                            <div class="small">
                                <div><i class="bi bi-telephone text-primary me-1"></i> Phone: <strong><?= e($selected_order['customer_phone'] ?: 'Not provided') ?></strong></div>
                                <div><i class="bi bi-calendar-event text-primary me-1"></i> Pickup Date: <strong><?= format_date($selected_order['pickup_date']) ?></strong></div>
                                <div><i class="bi bi-clock text-primary me-1"></i> Time Slot: <strong><?= e($selected_order['pickup_slot']) ?></strong></div>
                            </div>
                        </div>

                        <!-- Items List -->
                        <h6 class="fw-bold mb-2 fs-6">Reserved Items:</h6>
                        <ul class="list-group list-group-flush mb-3 border rounded">
                            <?php foreach ($selected_order_items as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded bg-light d-flex align-items-center justify-content-center border" style="width: 32px; height: 32px; flex-shrink: 0; overflow: hidden;">
                                            <?php if (!empty($item['image_url'])): ?>
                                                <img src="<?= e(get_image_url($item['image_url'])) ?>" alt="<?= e($item['product_name']) ?>" class="w-100 h-100 rounded object-fit-cover" onerror="this.src='https://placehold.co/100x100?text=Produce'">
                                            <?php else: ?>
                                                <i class="bi bi-egg-fried text-primary" style="font-size: .8rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="fw-bold"><?= e($item['product_name']) ?></span>
                                            <span class="text-muted ms-1">&times; <?= $item['quantity'] ?> <?= e($item['unit']) ?></span>
                                        </div>
                                    </div>
                                    <span class="fw-semibold"><?= format_currency($item['price_at_order'] * $item['quantity']) ?></span>
                                </li>
                            <?php endforeach; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-light fw-bold">
                                <span>Total Payable at Pickup:</span>
                                <span class="text-primary fs-6"><?= format_currency($selected_order['total_amount']) ?></span>
                            </li>
                        </ul>

                        <!-- Action Buttons based on Status -->
                        <div class="pt-2 border-top">
                            <h6 class="small fw-bold text-muted mb-2">Update Order Status:</h6>
                            
                            <?php if ($selected_order['status'] === ORDER_STATUS_PLACED): ?>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="<?= BASE_URL ?>farmer/orders.php" class="flex-grow-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="order_id" value="<?= $selected_order['order_id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= ORDER_STATUS_ACCEPTED ?>">
                                        <button type="submit" class="btn btn-primary w-100 btn-sm py-2">
                                            <i class="bi bi-check-lg me-1"></i> Accept Pre-Order
                                        </button>
                                    </form>

                                    <form method="POST" action="<?= BASE_URL ?>farmer/orders.php" class="flex-grow-1" onsubmit="return confirm('Decline this pre-order? Reserved stock will be restored to your inventory.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="order_id" value="<?= $selected_order['order_id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= ORDER_STATUS_DECLINED ?>">
                                        <button type="submit" class="btn btn-outline-danger w-100 btn-sm py-2">
                                            <i class="bi bi-x-lg me-1"></i> Decline
                                        </button>
                                    </form>
                                </div>

                            <?php elseif ($selected_order['status'] === ORDER_STATUS_ACCEPTED): ?>
                                <form method="POST" action="<?= BASE_URL ?>farmer/orders.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= $selected_order['order_id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= ORDER_STATUS_READY ?>">
                                    <button type="submit" class="btn btn-warning text-dark w-100 py-2">
                                        <i class="bi bi-bag-check me-1"></i> Mark Packed & Ready for Pickup
                                    </button>
                                </form>

                            <?php elseif ($selected_order['status'] === ORDER_STATUS_READY): ?>
                                <form method="POST" action="<?= BASE_URL ?>farmer/orders.php" onsubmit="return confirm('Confirm that the customer has collected their basket and paid?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= $selected_order['order_id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= ORDER_STATUS_COMPLETED ?>">
                                    <button type="submit" class="btn btn-success w-100 py-2">
                                        <i class="bi bi-cash-stack me-1"></i> Mark Picked Up & Paid (Completed)
                                    </button>
                                </form>

                            <?php else: ?>
                                <div class="text-muted small text-center py-2 bg-light rounded">
                                    Order is marked as <strong><?= e(strtoupper($selected_order['status'])) ?></strong>. No further status changes needed.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
