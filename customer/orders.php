<?php
/**
 * MarketLink - Customer Pre-Order History & Status Tracking (SaaS Design)
 */

$required_role = 'customer';
require_once __DIR__ . '/../includes/auth-check.php';

$customer_id = get_logged_in_user_id();
$view_order_id = (int)($_GET['order_id'] ?? 0);
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$search_query = sanitize_input($_GET['q'] ?? '');

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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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

// Fetch status counts for filter badges & top metrics
$status_counts = [];
$total_spend = 0.00;
try {
    $counts_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM orders WHERE customer_id = :cid GROUP BY status");
    $counts_stmt->execute([':cid' => $customer_id]);
    $status_counts = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $spend_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE customer_id = :cid AND status != 'cancelled'");
    $spend_stmt->execute([':cid' => $customer_id]);
    $total_spend = (float)$spend_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Status counts error: " . $e->getMessage());
}

$total_all = array_sum($status_counts);
$count_placed = $status_counts['placed'] ?? 0;
$count_accepted = $status_counts['accepted'] ?? 0;
$count_ready = $status_counts['ready'] ?? 0;
$count_completed = $status_counts['completed'] ?? 0;
$count_cancelled = $status_counts['cancelled'] ?? 0;
$count_active = $count_placed + $count_accepted;

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

// Fetch all customer orders with filtering and search
$sql = "SELECT o.*, u.name as farmer_name, fp.stall_name, 
        (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count 
        FROM orders o 
        JOIN users u ON o.farmer_id = u.user_id 
        LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
        WHERE o.customer_id = :cid";
$params = [':cid' => $customer_id];

if ($status_filter === 'active') {
    $sql .= " AND o.status IN ('placed', 'accepted')";
} elseif ($status_filter !== 'all' && !empty($status_filter)) {
    $sql .= " AND o.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search_query)) {
    $sql .= " AND (o.order_id = :exact_oid OR u.name LIKE :search_name OR fp.stall_name LIKE :search_stall)";
    $params[':exact_oid'] = is_numeric($search_query) ? (int)$search_query : 0;
    $params[':search_name'] = '%' . $search_query . '%';
    $params[':search_stall'] = '%' . $search_query . '%';
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

$page_title = 'My Pre-Orders & Pickups';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header Title Bar -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge badge-primary px-2 py-1"><i class="bi bi-clock-history me-1"></i> Order History</span>
                <?php if ($count_ready > 0): ?>
                    <span class="badge badge-warning px-2 py-1"><i class="bi bi-bag-check-fill me-1"></i> <?= $count_ready ?> Ready for Pickup</span>
                <?php endif; ?>
            </div>
            <h1 class="h3 fw-bold mb-1">My Pre-Orders &amp; Stalls</h1>
            <p class="text-muted small mb-0">Track live reservation status, inspect pickup schedule slots, and reorder favourite packs</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>customer/cart.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-bag me-1"></i> View Basket
            </a>
            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                <i class="bi bi-basket me-1"></i> Browse Fresh Harvest
            </a>
        </div>
    </div>

    <!-- Top KPI Metric Quick Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background: rgba(46,125,79,.12); color: var(--primary);">
                        <i class="bi bi-receipt fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Pre-Orders</div>
                        <div class="fs-5 fw-bold text-dark"><?= $total_all ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background: rgba(43,114,186,.12); color: #2B72BA;">
                        <i class="bi bi-hourglass-split fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">In Progress</div>
                        <div class="fs-5 fw-bold text-dark"><?= $count_active ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border shadow-xs p-3 <?= $count_ready > 0 ? 'border-warning bg-warning-subtle' : '' ?>">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background: rgba(235,142,39,.15); color: #EB8E27;">
                        <i class="bi bi-bag-check-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Ready for Pickup</div>
                        <div class="fs-5 fw-bold <?= $count_ready > 0 ? 'text-warning-emphasis' : 'text-dark' ?>"><?= $count_ready ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background: rgba(46,125,79,.12); color: var(--primary);">
                        <i class="bi bi-wallet2 fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Farm Spend</div>
                        <div class="fs-5 fw-bold text-primary"><?= format_currency($total_spend) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SaaS Level Filter & Search Toolbar -->
    <div class="card border shadow-xs mb-4">
        <div class="card-body p-2 p-md-3">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <!-- Status Segment Filter Tabs -->
                <div class="d-flex align-items-center gap-1 overflow-x-auto pb-1 pb-lg-0">
                    <a href="<?= BASE_URL ?>customer/orders.php?status=all<?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>" 
                       class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        All <span class="badge <?= $status_filter === 'all' ? 'bg-white text-dark' : 'badge-neutral' ?> ms-1"><?= $total_all ?></span>
                    </a>
                    <a href="<?= BASE_URL ?>customer/orders.php?status=active<?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>" 
                       class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'active' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-hourglass-split me-1"></i> In Progress <span class="badge <?= $status_filter === 'active' ? 'bg-white text-dark' : 'badge-neutral' ?> ms-1"><?= $count_active ?></span>
                    </a>
                    <a href="<?= BASE_URL ?>customer/orders.php?status=ready<?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>" 
                       class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'ready' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-bag-check-fill me-1 text-warning"></i> Ready <span class="badge <?= $status_filter === 'ready' ? 'bg-white text-dark' : 'badge-warning' ?> ms-1"><?= $count_ready ?></span>
                    </a>
                    <a href="<?= BASE_URL ?>customer/orders.php?status=completed<?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>" 
                       class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'completed' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="bi bi-check2-circle me-1 text-success"></i> Completed <span class="badge <?= $status_filter === 'completed' ? 'bg-white text-dark' : 'badge-neutral' ?> ms-1"><?= $count_completed ?></span>
                    </a>
                    <a href="<?= BASE_URL ?>customer/orders.php?status=cancelled<?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>" 
                       class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'cancelled' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Cancelled <span class="badge <?= $status_filter === 'cancelled' ? 'bg-white text-dark' : 'badge-neutral' ?> ms-1"><?= $count_cancelled ?></span>
                    </a>
                </div>

                <!-- Search Input Bar -->
                <form action="<?= BASE_URL ?>customer/orders.php" method="GET" class="d-flex align-items-center gap-2 flex-shrink-0">
                    <input type="hidden" name="status" value="<?= e($status_filter) ?>">
                    <div class="input-group input-group-sm" style="min-width: 240px;">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" value="<?= e($search_query) ?>" class="form-control border-start-0" placeholder="Search order #, stall, farmer...">
                        <?php if (!empty($search_query)): ?>
                            <a href="<?= BASE_URL ?>customer/orders.php?status=<?= urlencode($status_filter) ?>" class="btn btn-light border border-start-0 text-muted" title="Clear search">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Search</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Orders Main Display Grid -->
    <div class="row g-4">
        <!-- Orders List Table Column -->
        <div class="<?= $selected_order ? 'col-lg-7' : 'col-12' ?>">
            <div class="card border shadow-xs">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title mb-0 fs-6 fw-bold">Order History</h5>
                        <span class="badge badge-neutral"><?= count($orders) ?> <?= count($orders) === 1 ? 'order' : 'orders' ?></span>
                    </div>
                    <?php if (!empty($search_query)): ?>
                        <small class="text-muted">Filtering by: "<strong><?= e($search_query) ?></strong>"</small>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="ps-3">Order</th>
                                        <th>Farmer / Stall</th>
                                        <th>Pickup Schedule</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $ord): 
                                        $is_current_selected = ($view_order_id === (int)$ord['order_id']);
                                    ?>
                                        <tr class="<?= $is_current_selected ? 'table-primary-subtle' : '' ?>" style="<?= $is_current_selected ? 'background: #F0FDF4; font-weight: 500;' : '' ?>">
                                            <td class="ps-3">
                                                <div class="fw-bold text-dark">#<?= $ord['order_id'] ?></div>
                                                <small class="text-muted"><?= date('M j, Y', strtotime($ord['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= e($ord['stall_name'] ?: $ord['farmer_name']) ?></div>
                                                <small class="text-muted"><i class="bi bi-box-seam me-1"></i><?= $ord['item_count'] ?> item(s)</small>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark"><i class="bi bi-calendar3 text-primary me-1"></i><?= format_date($ord['pickup_date']) ?></div>
                                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($ord['pickup_slot']) ?></small>
                                            </td>
                                            <td class="fw-bold text-primary">
                                                <?= format_currency($ord['total_amount']) ?>
                                            </td>
                                            <td>
                                                <?= get_status_badge($ord['status']) ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="<?= BASE_URL ?>customer/orders.php?order_id=<?= $ord['order_id'] ?>&status=<?= urlencode($status_filter) ?><?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>" 
                                                   class="btn btn-sm <?= $is_current_selected ? 'btn-primary' : 'btn-outline-primary' ?>">
                                                    <?= $is_current_selected ? 'Viewing' : 'Details' ?> <i class="bi bi-chevron-right ms-1"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 px-4 text-muted">
                            <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: var(--surface-2); color: var(--text-3);">
                                <i class="bi bi-receipt-cutoff fs-2"></i>
                            </div>
                            <h5 class="fw-bold mb-1">No pre-orders found</h5>
                            <p class="small text-muted mb-4 max-w-600 mx-auto">
                                <?= !empty($search_query) ? 'No orders match your search keyword. Try clearing the search.' : 'You do not have any pre-orders under this status filter.' ?>
                            </p>
                            <div class="d-flex justify-content-center gap-2">
                                <?php if (!empty($search_query) || $status_filter !== 'all'): ?>
                                    <a href="<?= BASE_URL ?>customer/orders.php" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-arrow-repeat me-1"></i> Reset Filters
                                    </a>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                                    <i class="bi bi-basket me-1"></i> Explore Farmers Market
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Selected Order Detail Column (Drawer) -->
        <?php if ($selected_order): ?>
            <div class="col-lg-5">
                <div class="card border shadow-sm sticky-top" style="top: 85px;">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded bg-primary-subtle text-primary p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="bi bi-receipt"></i>
                            </div>
                            <div>
                                <span class="fw-bold fs-6">Order Details #<?= $selected_order['order_id'] ?></span>
                                <div class="text-muted" style="font-size: .75rem;">Placed on <?= format_datetime($selected_order['created_at']) ?></div>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>customer/orders.php?status=<?= urlencode($status_filter) ?><?= !empty($search_query) ? '&q=' . urlencode($search_query) : '' ?>" class="btn-close" aria-label="Close" title="Close details"></a>
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
                                <div class="mb-1"><i class="bi bi-calendar-event text-primary me-1"></i> Pickup Date: <strong><?= format_date($selected_order['pickup_date']) ?></strong></div>
                                <div class="mb-1"><i class="bi bi-clock text-primary me-1"></i> Time Window: <strong><?= e($selected_order['pickup_slot']) ?></strong></div>
                                <div><i class="bi bi-telephone text-primary me-1"></i> Farmer Contact: <strong><?= e($selected_order['farmer_phone'] ?: '—') ?></strong></div>
                            </div>
                        </div>

                        <!-- Status Workflow Alert -->
                        <?php if ($selected_order['status'] === ORDER_STATUS_READY): ?>
                            <div class="alert alert-warning py-2 px-3 small mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-bag-check-fill fs-5 text-warning flex-shrink-0"></i>
                                <div><strong>Your harvest pack is ready!</strong> Head to the stall during your window slot and pay in person.</div>
                            </div>
                        <?php elseif ($selected_order['status'] === ORDER_STATUS_PLACED): ?>
                            <div class="alert alert-info py-2 px-3 small mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill fs-5 text-info flex-shrink-0"></i>
                                <div>Order received. The farmer will confirm preparation shortly.</div>
                            </div>
                        <?php elseif ($selected_order['status'] === ORDER_STATUS_COMPLETED): ?>
                            <div class="alert alert-success py-2 px-3 small mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-check-circle-fill fs-5 text-success flex-shrink-0"></i>
                                <div>Order completed &amp; collected. Thank you for supporting local farmers!</div>
                            </div>
                        <?php endif; ?>

                        <!-- Items List -->
                        <h6 class="fw-bold mb-2 fs-6">Reserved Harvest Items:</h6>
                        <ul class="list-group list-group-flush mb-3 border rounded">
                            <?php foreach ($selected_order_items as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 small">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded bg-light d-flex align-items-center justify-content-center border" style="width: 32px; height: 32px; flex-shrink: 0;">
                                            <?php if (!empty($item['image_url'])): ?>
                                                <img src="<?= e(get_image_url($item['image_url'])) ?>" alt="<?= e($item['product_name']) ?>" class="w-100 h-100 rounded object-fit-cover" onerror="this.src='https://placehold.co/100x100?text=Produce'">
                                            <?php else: ?>
                                                <i class="bi bi-egg-fried text-primary" style="font-size: .8rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark"><?= e($item['product_name']) ?></span>
                                            <div class="text-muted" style="font-size: .75rem;">&times; <?= $item['quantity'] ?> <?= e($item['unit']) ?></div>
                                        </div>
                                    </div>
                                    <span class="fw-bold text-primary"><?= format_currency($item['price_at_order'] * $item['quantity']) ?></span>
                                </li>
                            <?php endforeach; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-light fw-bold">
                                <span>Total (Pay at Pickup):</span>
                                <span class="text-primary fs-5"><?= format_currency($selected_order['total_amount']) ?></span>
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
                                    <i class="bi bi-star-fill text-warning me-1"></i> Rate &amp; Review Stall
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
