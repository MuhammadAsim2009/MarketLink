<?php
/**
 * MarketLink - Customer Dashboard
 */

$required_role = 'customer';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Customer Dashboard';
$user_id = get_logged_in_user_id();

// Fetch customer stats & recent orders
$orders_count = 0;
$favorites_count = 0;
$recent_orders = [];

try {
    $orders_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = :cid");
    $orders_stmt->execute([':cid' => $user_id]);
    $orders_count = (int)$orders_stmt->fetchColumn();

    $fav_stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE customer_id = :cid");
    $fav_stmt->execute([':cid' => $user_id]);
    $favorites_count = (int)$fav_stmt->fetchColumn();

    $rec_stmt = $pdo->prepare("SELECT o.*, u.name as farmer_name, fp.stall_name 
                              FROM orders o 
                              JOIN users u ON o.farmer_id = u.user_id 
                              LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                              WHERE o.customer_id = :cid 
                              ORDER BY o.created_at DESC LIMIT 5");
    $rec_stmt->execute([':cid' => $user_id]);
    $recent_orders = $rec_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Customer dashboard data error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Hello, <?= e(get_logged_in_user_name()) ?> 👋</h1>
            <p class="text-muted small mb-0">Manage your pre-orders, favorite stalls, and market pickups</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                <i class="bi bi-basket me-1"></i> Browse Fresh Harvest
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 border-0 bg-primary-subtle text-primary-emphasis">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-white rounded-circle shadow-sm">
                        <i class="bi bi-bag-check fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $orders_count ?></div>
                        <div class="small">Total Pre-Orders Placed</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 border-0 bg-warning-subtle text-warning-emphasis">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-white rounded-circle shadow-sm">
                        <i class="bi bi-heart fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $favorites_count ?></div>
                        <div class="small">Saved Favorite Items</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 border-0 bg-success-subtle text-success-emphasis">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-white rounded-circle shadow-sm">
                        <i class="bi bi-geo-alt fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold">Live</div>
                        <div class="small">Weekend Pickup Ready</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Pre-Orders Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="card-title mb-0 fs-6"><i class="bi bi-clock-history me-1 text-primary"></i> Recent Pre-Orders</h5>
            <a href="<?= BASE_URL ?>customer/orders.php" class="btn btn-outline-primary btn-sm py-0 px-2">View All</a>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($recent_orders)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Order #</th>
                                <th>Farmer / Stall</th>
                                <th>Pickup Date & Slot</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $ord): ?>
                                <tr>
                                    <td><strong>#<?= $ord['order_id'] ?></strong></td>
                                    <td><?= e($ord['stall_name'] ?: $ord['farmer_name']) ?></td>
                                    <td>
                                        <div><?= format_date($ord['pickup_date']) ?></div>
                                        <small class="text-muted"><?= e($ord['pickup_slot']) ?></small>
                                    </td>
                                    <td class="fw-bold text-primary"><?= format_currency($ord['total_amount']) ?></td>
                                    <td><?= get_status_badge($ord['status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>customer/orders.php?order_id=<?= $ord['order_id'] ?>" class="btn btn-light btn-sm border">
                                            Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bag-x fs-1 text-secondary-subtle d-block mb-2"></i>
                    <h6>No pre-orders placed yet</h6>
                    <p class="small text-muted mb-3">Explore weekend farmers markets and pre-order fresh produce for quick pickup.</p>
                    <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                        Start Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
