<?php
/**
 * MarketLink - Farmer Hub Dashboard
 */

$required_role = 'farmer';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Farmer Dashboard';
$farmer_id = get_logged_in_user_id();

// Fetch farmer profile and metrics
$profile = null;
$products_count = 0;
$pending_orders_count = 0;
$total_revenue = 0.00;
$incoming_orders = [];

try {
    // Farmer profile
    $p_stmt = $pdo->prepare("SELECT * FROM farmer_profiles WHERE farmer_id = :fid");
    $p_stmt->execute([':fid' => $farmer_id]);
    $profile = $p_stmt->fetch();

    // Active products count
    $prod_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE farmer_id = :fid");
    $prod_stmt->execute([':fid' => $farmer_id]);
    $products_count = (int)$prod_stmt->fetchColumn();

    // Pending pre-orders count
    $ord_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE farmer_id = :fid AND status = 'placed'");
    $ord_stmt->execute([':fid' => $farmer_id]);
    $pending_orders_count = (int)$ord_stmt->fetchColumn();

    // Revenue from completed orders
    $rev_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE farmer_id = :fid AND status = 'completed'");
    $rev_stmt->execute([':fid' => $farmer_id]);
    $total_revenue = (float)$rev_stmt->fetchColumn();

    // Recent incoming orders
    $inc_stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                              FROM orders o 
                              JOIN users u ON o.customer_id = u.user_id 
                              WHERE o.farmer_id = :fid 
                              ORDER BY o.created_at DESC LIMIT 5");
    $inc_stmt->execute([':fid' => $farmer_id]);
    $incoming_orders = $inc_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Farmer dashboard query error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <?php if (!empty($_SESSION['is_pending_approval'])): ?>
        <div class="alert alert-warning border-warning d-flex align-items-center gap-3 mb-4 shadow-sm" role="alert">
            <i class="bi bi-clock-history fs-3 text-warning"></i>
            <div>
                <h6 class="alert-heading fw-bold mb-1">Stall Approval In Progress</h6>
                <p class="small mb-0">Your farmer stall registration is currently being reviewed by the platform administrator. You can prepare your product catalog now; it will go live to customers as soon as your account is approved.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= e($profile['stall_name'] ?? get_logged_in_user_name()) ?> 🌾</h1>
            <p class="text-muted small mb-0">
                Operating Days: <strong><?= e($profile['operating_days'] ?? 'Not set') ?></strong> &bull; 
                Pickup Window: <strong><?= e($profile['pickup_window_start'] ?? '08:00') ?> - <?= e($profile['pickup_window_end'] ?? '14:00') ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>farmer/products.php?action=add" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Add New Product
            </a>
            <a href="<?= BASE_URL ?>farmer/profile.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-gear me-1"></i> Edit Stall Details
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 border-0 bg-primary-subtle text-primary-emphasis">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-white rounded-circle shadow-sm">
                        <i class="bi bi-box-seam fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $products_count ?></div>
                        <div class="small">Active Products in Stock</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 border-0 bg-warning-subtle text-warning-emphasis">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-white rounded-circle shadow-sm">
                        <i class="bi bi-bell fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $pending_orders_count ?></div>
                        <div class="small">Orders Needing Action</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 border-0 bg-success-subtle text-success-emphasis">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-white rounded-circle shadow-sm">
                        <i class="bi bi-cash-stack fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= format_currency($total_revenue) ?></div>
                        <div class="small">Completed Pickup Revenue</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Incoming Pre-Orders -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="card-title mb-0 fs-6"><i class="bi bi-receipt me-1 text-primary"></i> Recent Incoming Pre-Orders</h5>
            <a href="<?= BASE_URL ?>farmer/orders.php" class="btn btn-outline-primary btn-sm py-0 px-2">Manage All Orders</a>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($incoming_orders)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Pickup Date & Slot</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($incoming_orders as $ord): ?>
                                <tr>
                                    <td><strong>#<?= $ord['order_id'] ?></strong></td>
                                    <td><?= e($ord['customer_name']) ?></td>
                                    <td><?= e($ord['customer_phone'] ?: '—') ?></td>
                                    <td>
                                        <div><?= format_date($ord['pickup_date']) ?></div>
                                        <small class="text-muted"><?= e($ord['pickup_slot']) ?></small>
                                    </td>
                                    <td class="fw-bold text-primary"><?= format_currency($ord['total_amount']) ?></td>
                                    <td><?= get_status_badge($ord['status']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>farmer/orders.php?order_id=<?= $ord['order_id'] ?>" class="btn btn-light btn-sm border">
                                            Manage
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
                    <h6>No orders received yet</h6>
                    <p class="small text-muted mb-0">When customers reserve pre-orders for your weekend stall, they will show up here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
