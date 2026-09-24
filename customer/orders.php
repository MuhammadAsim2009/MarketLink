<?php
/**
 * MarketLink - Customer Pre-Order History & Status Tracking
 */

$required_role = 'customer';
require_once __DIR__ . '/../includes/auth-check.php';

$customer_id = get_logged_in_user_id();
$view_order_id = (int)($_GET['order_id'] ?? 0);
$status_filter = sanitize_input($_GET['status'] ?? 'all');

// Handle Cancel Pre-Order or Reorder via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session submission. Please try again.');
        redirect(BASE_URL . 'customer/orders.php');
    }

    $action = sanitize_input($_POST['action'] ?? '');
    $target_order_id = (int)($_POST['order_id'] ?? 0);

    // 1. CANCEL ORDER (Customer-initiated)
    if ($action === 'cancel_order' && $target_order_id > 0) {
        try {
            // Strict Row-Level check: order must belong to this customer
            $chk = $pdo->prepare("SELECT o.*, fp.stall_name, u.name as farmer_name 
                                  FROM orders o 
                                  JOIN users u ON o.farmer_id = u.user_id 
                                  LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                                  WHERE o.order_id = :oid AND o.customer_id = :cid LIMIT 1");
            $chk->execute([':oid' => $target_order_id, ':cid' => $customer_id]);
            $order = $chk->fetch();

            if ($order && in_array($order['status'], [ORDER_STATUS_PLACED, ORDER_STATUS_ACCEPTED])) {
                $pdo->beginTransaction();

                // Update order status to cancelled
                $upd = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = :oid AND customer_id = :cid");
                $upd->execute([':oid' => $target_order_id, ':cid' => $customer_id]);

                // Restore reserved inventory
                $it_stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :oid");
                $it_stmt->execute([':oid' => $target_order_id]);
                $items = $it_stmt->fetchAll();

                $restock_stmt = $pdo->prepare("UPDATE products SET quantity_available = quantity_available + :qty, is_sold_out = 0 WHERE product_id = :pid");
                foreach ($items as $item) {
                    $restock_stmt->execute([
                        ':qty' => $item['quantity'],
                        ':pid' => $item['product_id']
                    ]);
                }

                // Notify Farmer
                $cust_name = get_logged_in_user_name();
                create_notification($pdo, $order['farmer_id'], "Pre-Order #{$target_order_id} was cancelled by {$cust_name}. Reserved items have been restored to your inventory.");

                $pdo->commit();
                set_flash('info', "Pre-order #{$target_order_id} has been cancelled successfully.");
            } else {
                set_flash('danger', 'This order cannot be cancelled (it may already be packed, completed, or already cancelled).');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Order cancellation error: " . $e->getMessage());
            set_flash('danger', 'Failed to cancel order.');
        }
        redirect(BASE_URL . 'customer/orders.php');
    }

    // 2. REORDER BASKET (Adds past items to current cart)
    if ($action === 'reorder' && $target_order_id > 0) {
        try {
            $it_stmt = $pdo->prepare("SELECT oi.product_id, oi.quantity, p.quantity_available, p.is_sold_out 
                                     FROM order_items oi 
                                     JOIN orders o ON oi.order_id = o.order_id 
                                     JOIN products p ON oi.product_id = p.product_id 
                                     WHERE oi.order_id = :oid AND o.customer_id = :cid");
            $it_stmt->execute([':oid' => $target_order_id, ':cid' => $customer_id]);
            $items = $it_stmt->fetchAll();

            if (!empty($items)) {
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                $added_count = 0;
                foreach ($items as $item) {
                    if (!$item['is_sold_out'] && $item['quantity_available'] > 0) {
                        $qty = min($item['quantity'], $item['quantity_available']);
                        $_SESSION['cart'][$item['product_id']] = $qty;
                        $added_count++;
                    }
                }
                set_flash('success', "Loaded {$added_count} available item(s) from Order #{$target_order_id} into your basket.");
                redirect(BASE_URL . 'customer/cart.php');
            } else {
                set_flash('warning', 'Could not reorder items (they may be sold out or no longer in season).');
            }
        } catch (PDOException $e) {
            error_log("Reorder error: " . $e->getMessage());
            set_flash('danger', 'Failed to reorder.');
        }
        redirect(BASE_URL . 'customer/orders.php');
    }
}

// Fetch single order details if requested
$selected_order = null;
$selected_order_items = [];
if ($view_order_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT o.*, u.name as farmer_name, u.phone as farmer_phone, fp.stall_name, fp.address as stall_address 
                              FROM orders o 
                              JOIN users u ON o.farmer_id = u.user_id 
                              LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                              WHERE o.order_id = :oid AND o.customer_id = :cid LIMIT 1");
        $stmt->execute([':oid' => $view_order_id, ':cid' => $customer_id]);
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
        error_log("Fetch customer order detail error: " . $e->getMessage());
    }
}

// Fetch all customer orders with filtering
$sql = "SELECT o.*, u.name as farmer_name, fp.stall_name, 
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count 
        FROM orders o 
        JOIN users u ON o.farmer_id = u.user_id 
        LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
        WHERE o.customer_id = :cid";
$params = [':cid' => $customer_id];

if ($status_filter !== 'all' && !empty($status_filter)) {
    $sql .= " AND o.status = :status";
    $params[':status'] = $status_filter;
}
$sql .= " ORDER BY o.created_at DESC";

$orders = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch customer orders error: " . $e->getMessage());
}

$page_title = 'My Pre-Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-clock-history text-primary me-2"></i>My Pre-Orders & Pickups</h1>
            <p class="text-muted small mb-0">Track live status of your market pre-orders, view pickup details, and reorder</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                <i class="bi bi-basket me-1"></i> Order Fresh Produce
            </a>
        </div>
    </div>

    <!-- Status Filters Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm border">
        <li class="nav-item">
            <a class="nav-link py-1 px-3 <?= $status_filter === 'all' ? 'active' : '' ?>" href="<?= BASE_URL ?>customer/orders.php?status=all">
                All Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 <?= $status_filter === 'placed' ? 'active' : '' ?>" href="<?= BASE_URL ?>customer/orders.php?status=placed">
                Placed
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 <?= $status_filter === 'ready' ? 'active' : '' ?>" href="<?= BASE_URL ?>customer/orders.php?status=ready">
                <i class="bi bi-bag-check-fill text-warning me-1"></i> Ready for Pickup
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 <?= $status_filter === 'completed' ? 'active' : '' ?>" href="<?= BASE_URL ?>customer/orders.php?status=completed">
                Completed
            </a>
        </li>
    </ul>

    <div class="row g-4">
        <!-- Orders List Table Column -->
        <div class="<?= $selected_order ? 'col-lg-7' : 'col-12' ?>">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fs-6">Order History (<?= count($orders) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Farmer / Stall</th>
                                        <th>Pickup Schedule</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-end">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $ord): ?>
                                        <tr class="<?= ($view_order_id === (int)$ord['order_id']) ? 'table-primary' : '' ?>">
                                            <td><strong>#<?= $ord['order_id'] ?></strong></td>
                                            <td>
                                                <div class="fw-semibold"><?= e($ord['stall_name'] ?: $ord['farmer_name']) ?></div>
                                                <small class="text-muted"><?= $ord['item_count'] ?> item(s)</small>
                                            </td>
                                            <td>
                                                <div><?= format_date($ord['pickup_date']) ?></div>
                                                <small class="text-muted"><?= e($ord['pickup_slot']) ?></small>
                                            </td>
                                            <td class="fw-bold text-primary"><?= format_currency($ord['total_amount']) ?></td>
                                            <td><?= get_status_badge($ord['status']) ?></td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>customer/orders.php?order_id=<?= $ord['order_id'] ?>&status=<?= urlencode($status_filter) ?>" class="btn btn-sm <?= ($view_order_id === (int)$ord['order_id']) ? 'btn-primary' : 'btn-light border' ?>">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-clock fs-1 text-secondary-subtle d-block mb-2"></i>
                            <h6>No pre-orders found in this category</h6>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Selected Order Detail Column -->
        <?php if ($selected_order): ?>
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 sticky-top" style="top: 85px;">
                    <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                        <span class="fw-bold fs-6">Order Details #<?= $selected_order['order_id'] ?></span>
                        <a href="<?= BASE_URL ?>customer/orders.php?status=<?= urlencode($status_filter) ?>" class="btn-close btn-close-white" aria-label="Close"></a>
                    </div>
                    <div class="card-body p-4">
                        <!-- Stall & Pickup Schedule -->
                        <div class="p-3 bg-light rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-0 text-primary"><?= e($selected_order['stall_name'] ?: $selected_order['farmer_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($selected_order['stall_address'] ?: 'Market Stall') ?></small>
                                </div>
                                <div>
                                    <?= get_status_badge($selected_order['status']) ?>
                                </div>
                            </div>
                            <div class="small border-top pt-2 mt-2">
                                <div><i class="bi bi-calendar-event text-primary me-1"></i> Pickup Date: <strong><?= format_date($selected_order['pickup_date']) ?></strong></div>
                                <div><i class="bi bi-clock text-primary me-1"></i> Time Slot: <strong><?= e($selected_order['pickup_slot']) ?></strong></div>
                                <div><i class="bi bi-telephone text-primary me-1"></i> Stall Contact: <strong><?= e($selected_order['farmer_phone'] ?: '—') ?></strong></div>
                            </div>
                        </div>

                        <!-- Status Workflow Banner -->
                        <?php if ($selected_order['status'] === ORDER_STATUS_READY): ?>
                            <div class="alert alert-warning py-2 small mb-3">
                                <i class="bi bi-bag-check-fill me-1"></i> <strong>Your harvest is packed!</strong> Please visit the stall during your time slot and pay in person.
                            </div>
                        <?php endif; ?>

                        <!-- Items List -->
                        <h6 class="fw-bold mb-2 fs-6">Reserved Produce:</h6>
                        <ul class="list-group list-group-flush mb-3 border rounded">
                            <?php foreach ($selected_order_items as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                                    <div>
                                        <span class="fw-bold"><?= e($item['product_name']) ?></span>
                                        <span class="text-muted ms-1">&times; <?= $item['quantity'] ?> <?= e($item['unit']) ?></span>
                                    </div>
                                    <span class="fw-semibold"><?= format_currency($item['price_at_order'] * $item['quantity']) ?></span>
                                </li>
                            <?php endforeach; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-light fw-bold">
                                <span>Total Payable at Pickup:</span>
                                <span class="text-primary fs-6"><?= format_currency($selected_order['total_amount']) ?></span>
                            </li>
                        </ul>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-column gap-2 pt-2 border-top">
                            <!-- Reorder Button -->
                            <form method="POST" action="<?= BASE_URL ?>customer/orders.php">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reorder">
                                <input type="hidden" name="order_id" value="<?= $selected_order['order_id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm w-100 py-2">
                                    <i class="bi bi-arrow-repeat me-1"></i> Reorder This Basket
                                </button>
                            </form>

                            <!-- Review Button (for completed orders) -->
                            <?php if ($selected_order['status'] === ORDER_STATUS_COMPLETED): ?>
                                <a href="<?= BASE_URL ?>customer/reviews.php?farmer_id=<?= $selected_order['farmer_id'] ?>" class="btn btn-outline-warning text-dark btn-sm py-2">
                                    <i class="bi bi-star-fill text-warning me-1"></i> Rate & Review Stall
                                </a>
                            <?php endif; ?>

                            <!-- Cancel Button (if status is placed or accepted) -->
                            <?php if (in_array($selected_order['status'], [ORDER_STATUS_PLACED, ORDER_STATUS_ACCEPTED])): ?>
                                <form method="POST" action="<?= BASE_URL ?>customer/orders.php" onsubmit="return confirm('Are you sure you want to cancel this pre-order?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel_order">
                                    <input type="hidden" name="order_id" value="<?= $selected_order['order_id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100 py-2">
                                        <i class="bi bi-x-circle me-1"></i> Cancel Pre-Order
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
