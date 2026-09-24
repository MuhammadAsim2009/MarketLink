<?php
/**
 * MarketLink - Farmer Hub Dashboard
 */

$required_role = 'farmer';
require_once __DIR__ . '/../includes/auth-check.php';

$active_nav = 'dashboard';
$page_title = 'Farmer Hub Dashboard';
$farmer_id = get_logged_in_user_id();

// Fetch farmer profile and metrics
$profile = null;
$products_count = 0;
$pending_orders_count = 0;
$ready_orders_count = 0;
$total_revenue = 0.00;
$avg_rating = 0.0;
$total_reviews = 0;
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

    // Ready for pickup count
    $rdy_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE farmer_id = :fid AND status = 'ready'");
    $rdy_stmt->execute([':fid' => $farmer_id]);
    $ready_orders_count = (int)$rdy_stmt->fetchColumn();

    // Revenue from completed orders
    $rev_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE farmer_id = :fid AND status = 'completed'");
    $rev_stmt->execute([':fid' => $farmer_id]);
    $total_revenue = (float)$rev_stmt->fetchColumn();

    // Average rating
    $rev_data = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg_r FROM reviews WHERE farmer_id = :fid");
    $rev_data->execute([':fid' => $farmer_id]);
    $r_row = $rev_data->fetch();
    $total_reviews = (int)($r_row['cnt'] ?? 0);
    $avg_rating = round((float)($r_row['avg_r'] ?? 0), 1);

    // Recent incoming orders
    $inc_stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                              FROM orders o 
                              JOIN users u ON o.customer_id = u.user_id 
                              WHERE o.farmer_id = :fid 
                              ORDER BY o.created_at DESC LIMIT 6");
    $inc_stmt->execute([':fid' => $farmer_id]);
    $incoming_orders = $inc_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Farmer dashboard query error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome / Stall Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h3 mb-0 fw-bold"><?= e($profile['stall_name'] ?? get_logged_in_user_name()) ?></h1>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2" style="font-size: 0.72rem;">
                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Active Producer
            </span>
        </div>
        <p class="text-muted small mb-0">
            <i class="bi bi-calendar3 me-1"></i> Market Days: <strong><?= e($profile['operating_days'] ?? 'Weekend (Sat, Sun)') ?></strong> &bull; 
            <i class="bi bi-clock me-1 ms-1"></i> Pickup Window: <strong><?= e($profile['pickup_window_start'] ?? '08:00') ?> - <?= e($profile['pickup_window_end'] ?? '14:00') ?></strong>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>farmer/products.php?action=add" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Add Produce
        </a>
        <a href="<?= BASE_URL ?>farmer/profile.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-sliders me-1"></i> Stall Settings
        </a>
    </div>
</div>

<!-- SaaS Metric Cards (4 Grid) -->
<div class="row g-3 mb-4">
    <!-- Active Products -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="farmer-stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Produce Catalog</span>
                <div class="farmer-icon-circle bg-primary-subtle text-primary">
                    <i class="bi bi-box-seam"></i>
                </div>
            </div>
            <div class="fs-3 fw-bold text-dark mb-1"><?= $products_count ?></div>
            <div class="d-flex align-items-center justify-content-between text-muted small">
                <span>Items listed in harvest</span>
                <a href="<?= BASE_URL ?>farmer/products.php" class="small fw-semibold text-primary">Manage &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Orders Needing Action -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="farmer-stat-card h-100 <?= $pending_orders_count > 0 ? 'border-danger-subtle' : '' ?>">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Pending Actions</span>
                <div class="farmer-icon-circle <?= $pending_orders_count > 0 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' ?>">
                    <i class="bi bi-receipt"></i>
                </div>
            </div>
            <div class="fs-3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <span><?= $pending_orders_count ?></span>
                <?php if ($pending_orders_count > 0): ?>
                    <span class="badge bg-danger rounded-pill px-2" style="font-size: 0.65rem;">ACTION REQUIRED</span>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center justify-content-between text-muted small">
                <span>New placed pre-orders</span>
                <a href="<?= BASE_URL ?>farmer/orders.php?status=placed" class="small fw-semibold text-danger">Review &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Revenue -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="farmer-stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Completed Revenue</span>
                <div class="farmer-icon-circle bg-success-subtle text-success">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            <div class="fs-3 fw-bold text-success mb-1"><?= format_currency($total_revenue) ?></div>
            <div class="d-flex align-items-center justify-content-between text-muted small">
                <span>Direct customer pickups</span>
                <span class="badge bg-light text-muted border">100% Retained</span>
            </div>
        </div>
    </div>

    <!-- Rating / Feedback -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="farmer-stat-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Stall Rating</span>
                <div class="farmer-icon-circle bg-warning-subtle text-warning">
                    <i class="bi bi-star-fill"></i>
                </div>
            </div>
            <div class="fs-3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <span><?= $avg_rating > 0 ? $avg_rating : '—' ?></span>
                <?php if ($avg_rating > 0): ?>
                    <span class="text-warning small" style="font-size: 0.85rem;"><i class="bi bi-star-fill"></i> / 5.0</span>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center justify-content-between text-muted small">
                <span><?= $total_reviews ?> customer <?= $total_reviews === 1 ? 'review' : 'reviews' ?></span>
                <a href="<?= BASE_URL ?>farmer/reviews.php" class="small fw-semibold text-primary">View &rarr;</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Recent Incoming Orders Table Column -->
    <div class="col-lg-8">
        <div class="card shadow-xs border-0 rounded-4 overflow-hidden h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 px-4 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i>
                    <h6 class="card-title mb-0 fw-bold fs-6">Recent Pre-Orders Queue</h6>
                </div>
                <a href="<?= BASE_URL ?>farmer/orders.php" class="btn btn-outline-primary btn-sm py-1 px-3 rounded-pill">
                    All Orders (<?= $pending_orders_count + $ready_orders_count ?> active)
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($incoming_orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-4">Order #</th>
                                    <th>Customer</th>
                                    <th>Pickup Slot</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach ($incoming_orders as $ord): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <a href="<?= BASE_URL ?>farmer/orders.php?order_id=<?= $ord['order_id'] ?>" class="fw-bold text-dark">
                                                #<?= $ord['order_id'] ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($ord['customer_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?= e($ord['customer_phone'] ?: 'No phone provided') ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= format_date($ord['pickup_date']) ?></div>
                                            <div class="text-muted" style="font-size: 0.72rem;"><i class="bi bi-clock me-1"></i><?= e($ord['pickup_slot']) ?></div>
                                        </td>
                                        <td class="fw-bold text-primary"><?= format_currency($ord['total_amount']) ?></td>
                                        <td><?= get_status_badge($ord['status']) ?></td>
                                        <td class="text-end pe-4">
                                            <a href="<?= BASE_URL ?>farmer/orders.php?order_id=<?= $ord['order_id'] ?>" class="btn btn-light btn-sm border px-2 py-1 rounded-2">
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <div class="d-inline-flex p-3 bg-light rounded-circle mb-3">
                            <i class="bi bi-inbox fs-2 text-muted"></i>
                        </div>
                        <h6 class="fw-bold mb-1">No orders received yet</h6>
                        <p class="small text-muted mb-3">When customers reserve pre-orders for your weekend stall, they will show up here.</p>
                        <a href="<?= BASE_URL ?>farmer/products.php?action=add" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> List Produce Items
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Operations & Stall Readiness Sidebar Column -->
    <div class="col-lg-4">
        <div class="d-flex flex-column gap-3">
            <!-- Quick Stall Actions -->
            <div class="card shadow-xs border-0 rounded-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h6 class="card-title mb-0 fw-bold fs-6"><i class="bi bi-lightning-charge text-accent me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body p-3 d-flex flex-column gap-2">
                    <a href="<?= BASE_URL ?>farmer/products.php?action=add" class="btn btn-outline-primary btn-sm text-start py-2 d-flex align-items-center justify-content-between rounded-3">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-plus-circle text-primary"></i> Add New Produce Item
                        </span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="<?= BASE_URL ?>farmer/products.php" class="btn btn-outline-primary btn-sm text-start py-2 d-flex align-items-center justify-content-between rounded-3">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-arrow-repeat text-success"></i> Bulk Weekend Restock
                        </span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="<?= BASE_URL ?>farmer/profile.php" class="btn btn-outline-primary btn-sm text-start py-2 d-flex align-items-center justify-content-between rounded-3">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-geo-alt text-danger"></i> Update Stall Location & Hours
                        </span>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                    <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $farmer_id ?>" target="_blank" class="btn btn-light btn-sm text-start py-2 d-flex align-items-center justify-content-between rounded-3 border">
                        <span class="d-flex align-items-center gap-2 text-dark">
                            <i class="bi bi-box-arrow-up-right text-secondary"></i> Preview Public Customer Stall
                        </span>
                        <i class="bi bi-arrow-up-right small opacity-50"></i>
                    </a>
                </div>
            </div>

            <!-- Stall Operations Card -->
            <div class="card shadow-xs border-0 rounded-4 bg-primary text-white p-4" style="background: linear-gradient(135deg, var(--primary) 0%, #1e5a36 100%);">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-shield-check fs-4"></i>
                    <h6 class="fw-bold mb-0 text-white">Producer Support</h6>
                </div>
                <p class="small mb-3 text-white-50" style="font-size: 0.82rem; line-height: 1.5;">
                    Keep your harvest catalog up-to-date before Friday evening to maximize weekend market pre-orders from local shoppers.
                </p>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-10 small">
                    <span>Order Cutoff: <strong><?= (int)($profile['order_cutoff_hours'] ?? 2) ?> hrs before</strong></span>
                    <a href="<?= BASE_URL ?>farmer/profile.php" class="text-white text-decoration-underline">Edit</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
