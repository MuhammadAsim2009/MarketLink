<?php
/**
 * MarketLink - Customer Dashboard (SaaS Design)
 */

$required_role = 'customer';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Customer Dashboard';
$user_id = get_logged_in_user_id();

$user_info = [];
$status_counts = [];
$total_spend = 0.00;
$recent_orders = [];
$ready_order = null;
$favorite_products = [];
$total_fav_count = 0;
$unread_notifs_count = 0;

try {
    // 1. User Info
    $u_stmt = $pdo->prepare("SELECT name, email, phone, created_at FROM users WHERE user_id = :uid LIMIT 1");
    $u_stmt->execute([':uid' => $user_id]);
    $user_info = $u_stmt->fetch() ?: [];

    // 2. Order counts by status
    $counts_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM orders WHERE customer_id = :cid GROUP BY status");
    $counts_stmt->execute([':cid' => $user_id]);
    $status_counts = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Total spend
    $spend_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE customer_id = :cid AND status != 'cancelled'");
    $spend_stmt->execute([':cid' => $user_id]);
    $total_spend = (float)$spend_stmt->fetchColumn();

    // 4. Check for any 'ready' order for pickup hero banner
    $ready_stmt = $pdo->prepare("SELECT o.*, u.name as farmer_name, u.phone as farmer_phone, fp.stall_name, fp.address as stall_address 
                                FROM orders o 
                                JOIN users u ON o.farmer_id = u.user_id 
                                LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                                WHERE o.customer_id = :cid AND o.status = 'ready' 
                                ORDER BY o.pickup_date ASC LIMIT 1");
    $ready_stmt->execute([':cid' => $user_id]);
    $ready_order = $ready_stmt->fetch();

    // 5. Recent orders
    $rec_stmt = $pdo->prepare("SELECT o.*, u.name as farmer_name, fp.stall_name, 
                              (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count 
                              FROM orders o 
                              JOIN users u ON o.farmer_id = u.user_id 
                              LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                              WHERE o.customer_id = :cid 
                              ORDER BY o.created_at DESC LIMIT 5");
    $rec_stmt->execute([':cid' => $user_id]);
    $recent_orders = $rec_stmt->fetchAll();

    // 6. Favorites
    $fav_cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE customer_id = :cid");
    $fav_cnt_stmt->execute([':cid' => $user_id]);
    $total_fav_count = (int)$fav_cnt_stmt->fetchColumn();

    $fav_stmt = $pdo->prepare("SELECT f.favorite_id, p.product_id, p.name, p.price, p.unit, p.image_url, p.is_sold_out, p.quantity_available, u.name as farmer_name, fp.stall_name 
                              FROM favorites f 
                              JOIN products p ON f.product_id = p.product_id 
                              JOIN users u ON p.farmer_id = u.user_id 
                              LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                              WHERE f.customer_id = :cid 
                              ORDER BY f.created_at DESC LIMIT 3");
    $fav_stmt->execute([':cid' => $user_id]);
    $favorite_products = $fav_stmt->fetchAll();

    // 7. Unread notifications
    $unread_notifs_count = get_unread_notifications_count($pdo, $user_id);

} catch (PDOException $e) {
    error_log("Customer dashboard data load error: " . $e->getMessage());
}

$total_orders = array_sum($status_counts);
$count_placed = $status_counts['placed'] ?? 0;
$count_accepted = $status_counts['accepted'] ?? 0;
$count_ready = $status_counts['ready'] ?? 0;
$count_completed = $status_counts['completed'] ?? 0;
$count_active = $count_placed + $count_accepted;
$cart_count = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- SaaS Profile Hero Welcome Bar -->
    <div class="card border shadow-xs mb-4" style="background: linear-gradient(135deg, rgba(46,125,79,.04) 0%, rgba(244,123,62,.04) 100%);">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 text-white fw-bold shadow-sm" style="width: 52px; height: 52px; background: var(--primary); font-size: 1.35rem;">
                        <?= strtoupper(mb_substr(get_logged_in_user_name(), 0, 1)) ?>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h1 class="h4 fw-bold mb-0 text-dark"><?= e(get_logged_in_user_name()) ?></h1>
                            <span class="badge badge-primary px-2 py-0" style="font-size: .7rem;">Customer</span>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-envelope me-1"></i><?= e($user_info['email'] ?? '') ?>
                            <?php if (!empty($user_info['created_at'])): ?>
                                <span class="mx-1">•</span> Member since <?= date('M Y', strtotime($user_info['created_at'])) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a href="<?= BASE_URL ?>customer/cart.php" class="btn btn-outline-primary btn-sm position-relative">
                        <i class="bi bi-bag me-1"></i> Basket
                        <?php if ($cart_count > 0): ?>
                            <span class="badge bg-primary ms-1"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= BASE_URL ?>notifications.php" class="btn btn-outline-primary btn-sm position-relative">
                        <i class="bi bi-bell me-1"></i> Alerts
                        <?php if ($unread_notifs_count > 0): ?>
                            <span class="badge bg-danger ms-1"><?= $unread_notifs_count ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-basket me-1"></i> Browse Fresh Harvest
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Ready Pickup Alert Banner (if any ready order exists) -->
    <?php if ($ready_order): ?>
        <div class="alert alert-warning border-warning shadow-xs p-3 mb-4 rounded-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; background: #FFF3CD; color: #856404;">
                    <i class="bi bi-bag-check-fill fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1 text-warning-emphasis">
                        <i class="bi bi-exclamation-circle-fill me-1"></i> Your Harvest Pack #<?= $ready_order['order_id'] ?> is Ready for Pickup!
                    </h6>
                    <div class="small text-muted">
                        Stall: <strong><?= e($ready_order['stall_name'] ?: $ready_order['farmer_name']) ?></strong> 
                        • Schedule: <strong><?= format_date($ready_order['pickup_date']) ?> (<?= e($ready_order['pickup_slot']) ?>)</strong>
                        • Location: <strong><?= e($ready_order['stall_address'] ?: 'Market Stall') ?></strong>
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0">
                <a href="<?= BASE_URL ?>customer/orders.php?order_id=<?= $ready_order['order_id'] ?>" class="btn btn-warning btn-sm text-dark fw-semibold px-3">
                    View Pickup Pass <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Stat Summary Grid -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="<?= BASE_URL ?>customer/orders.php?status=all" class="text-decoration-none">
                <div class="card border shadow-xs p-3 card-hover h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium">Total Pre-Orders</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: rgba(46,125,79,.12); color: var(--primary);">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-bold text-dark"><?= $total_orders ?></div>
                    <div class="small text-muted"><i class="bi bi-arrow-up-right me-1 text-primary"></i>Lifetime orders</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-lg-3">
            <a href="<?= BASE_URL ?>customer/orders.php?status=active" class="text-decoration-none">
                <div class="card border shadow-xs p-3 card-hover h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium">In Progress</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: rgba(43,114,186,.12); color: #2B72BA;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-bold text-dark"><?= $count_active ?></div>
                    <div class="small text-muted"><i class="bi bi-clock me-1 text-info"></i>Farmer preparing</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-lg-3">
            <a href="<?= BASE_URL ?>customer/orders.php?status=ready" class="text-decoration-none">
                <div class="card border shadow-xs p-3 card-hover h-100 <?= $count_ready > 0 ? 'border-warning bg-warning-subtle' : '' ?>">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium">Ready for Pickup</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: rgba(235,142,39,.15); color: #EB8E27;">
                            <i class="bi bi-bag-check-fill"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-bold <?= $count_ready > 0 ? 'text-warning-emphasis' : 'text-dark' ?>"><?= $count_ready ?></div>
                    <div class="small text-muted"><i class="bi bi-geo-alt me-1 text-warning"></i>At weekend stalls</div>
                </div>
            </a>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border shadow-xs p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-medium">Total Farm Spend</span>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: rgba(46,125,79,.12); color: var(--primary);">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="fs-4 fw-bold text-primary"><?= format_currency($total_spend) ?></div>
                <div class="small text-muted"><i class="bi bi-heart-fill me-1 text-danger"></i>Supporting local farms</div>
            </div>
        </div>
    </div>

    <!-- Main Content Layout (Orders + Sidebar) -->
    <div class="row g-4">
        <!-- Left: Recent Pre-Orders Feed -->
        <div class="col-lg-8">
            <div class="card border shadow-xs">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title mb-0 fs-6 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Recent Pre-Orders</h5>
                        <span class="badge badge-neutral"><?= count($recent_orders) ?> latest</span>
                    </div>
                    <a href="<?= BASE_URL ?>customer/orders.php" class="btn btn-outline-primary btn-sm">
                        View All Orders <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="ps-3">Order</th>
                                        <th>Farmer / Stall</th>
                                        <th>Pickup Schedule</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-end pe-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $ord): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <strong class="text-dark">#<?= $ord['order_id'] ?></strong>
                                                <div class="text-muted" style="font-size: .75rem;"><?= date('M j, Y', strtotime($ord['created_at'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark"><?= e($ord['stall_name'] ?: $ord['farmer_name']) ?></div>
                                                <small class="text-muted"><i class="bi bi-box-seam me-1"></i><?= $ord['item_count'] ?> item(s)</small>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark"><i class="bi bi-calendar-event text-primary me-1"></i><?= format_date($ord['pickup_date']) ?></div>
                                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($ord['pickup_slot']) ?></small>
                                            </td>
                                            <td class="fw-bold text-primary">
                                                <?= format_currency($ord['total_amount']) ?>
                                            </td>
                                            <td>
                                                <?= get_status_badge($ord['status']) ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="<?= BASE_URL ?>customer/orders.php?order_id=<?= $ord['order_id'] ?>" class="btn btn-outline-primary btn-sm">
                                                    Details
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
                                <i class="bi bi-cart-x fs-2"></i>
                            </div>
                            <h5 class="fw-bold mb-1">No pre-orders placed yet</h5>
                            <p class="small text-muted mb-4 max-w-600 mx-auto">
                                Explore local farmers markets and reserve fresh fruit, veggies, and dairy for your weekend basket.
                            </p>
                            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-basket me-1"></i> Explore Fresh Produce
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Saved Favorites & Quick Navigation -->
        <div class="col-lg-4">
            <!-- Saved Favorites Card -->
            <div class="card border shadow-xs mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <h6 class="card-title mb-0 fw-bold fs-6"><i class="bi bi-heart-fill text-danger me-2"></i>Saved Favorites (<?= $total_fav_count ?>)</h6>
                    <a href="<?= BASE_URL ?>customer/favorites.php" class="btn btn-outline-primary btn-sm py-0 px-2" style="font-size: .75rem;">View All</a>
                </div>
                <div class="card-body p-3">
                    <?php if (!empty($favorite_products)): ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($favorite_products as $fav): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <div class="rounded bg-white d-flex align-items-center justify-content-center border" style="width: 36px; height: 36px; flex-shrink: 0;">
                                            <?php if (!empty($fav['image_url'])): ?>
                                                <img src="<?= e(get_image_url($fav['image_url'])) ?>" alt="<?= e($fav['name']) ?>" class="w-100 h-100 rounded object-fit-cover" onerror="this.src='https://placehold.co/100x100?text=Produce'">
                                            <?php else: ?>
                                                <i class="bi bi-egg-fried text-primary" style="font-size: .8rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0">
                                            <a href="<?= BASE_URL ?>customer/product-detail.php?id=<?= $fav['product_id'] ?>" class="fw-semibold text-dark text-decoration-none d-block text-truncate small">
                                                <?= e($fav['name']) ?>
                                            </a>
                                            <small class="text-muted"><?= e($fav['stall_name'] ?: $fav['farmer_name']) ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0 ms-2">
                                        <div class="fw-bold text-primary small"><?= format_currency($fav['price']) ?></div>
                                        <small class="text-muted">/ <?= e($fav['unit']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-heart text-secondary-subtle fs-3 d-block mb-1"></i>
                            <div class="small">No saved favorites yet</div>
                            <p class="text-muted" style="font-size: .75rem;">Bookmark your favourite stalls and produce for 1-click reordering.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Action Hub Card -->
            <div class="card border shadow-xs">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="card-title mb-0 fw-bold fs-6"><i class="bi bi-lightning-charge-fill text-accent me-2"></i>Quick Services</h6>
                </div>
                <div class="card-body p-3 d-flex flex-column gap-2">
                    <a href="<?= BASE_URL ?>customer/browse-markets.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                        <i class="bi bi-geo-alt me-2"></i> Locate Farmers Markets
                    </a>
                    <a href="<?= BASE_URL ?>customer/orders.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                        <i class="bi bi-clock-history me-2"></i> Track Pre-Order Pickups
                    </a>
                    <a href="<?= BASE_URL ?>customer/reviews.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                        <i class="bi bi-star me-2"></i> My Reviews & Ratings
                    </a>
                    <a href="<?= BASE_URL ?>notifications.php" class="btn btn-outline-primary btn-sm text-start justify-content-start">
                        <i class="bi bi-bell me-2"></i> Notification Inbox
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
