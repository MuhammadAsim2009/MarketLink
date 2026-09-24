<?php
/**
 * MarketLink - Platform Reports & Analytics
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Platform Analytics & Reports';

// 1. Overall Platform Aggregates
$total_revenue = 0.00;
$total_orders = 0;
$completed_orders = 0;
$active_farmers = 0;
$active_customers = 0;

// 2. Revenue by Market Breakdown
$market_breakdown = [];

// 3. Top-Selling Stalls
$top_stalls = [];

// 4. Popular Produce Categories
$category_stats = [];

try {
    // Total Revenue & Counts
    $tot_rev_stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'");
    $total_revenue = (float)$tot_rev_stmt->fetchColumn();

    $tot_ord_stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = (int)$tot_ord_stmt->fetchColumn();

    $comp_ord_stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'");
    $completed_orders = (int)$comp_ord_stmt->fetchColumn();

    $active_farmers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND status = 'active'")->fetchColumn();
    $active_customers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'active'")->fetchColumn();

    // Top Stalls by Revenue
    $top_stmt = $pdo->query("SELECT u.user_id, u.name as contact_name, fp.stall_name, 
                            COUNT(o.order_id) as orders_count, 
                            COALESCE(SUM(o.total_amount), 0) as stall_revenue 
                            FROM users u 
                            LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                            LEFT JOIN orders o ON u.user_id = o.farmer_id 
                            WHERE u.role = 'farmer' 
                            GROUP BY u.user_id 
                            ORDER BY stall_revenue DESC LIMIT 8");
    $top_stalls = $top_stmt->fetchAll();

    // Category Distribution
    $cat_stmt = $pdo->query("SELECT category, COUNT(*) as product_count, AVG(price) as avg_price 
                            FROM products GROUP BY category ORDER BY product_count DESC");
    $category_stats = $cat_stmt->fetchAll();

    // Market Breakdown
    $mkt_stmt = $pdo->query("SELECT m.market_id, m.market_name, m.operating_days, 
                             (SELECT COUNT(*) FROM market_farmers WHERE market_id = m.market_id) as stall_count 
                             FROM markets m ORDER BY stall_count DESC");
    $market_breakdown = $mkt_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Reports load error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-graph-up text-primary me-2"></i>Platform Reports & Analytics</h1>
            <p class="text-muted small mb-0">Performance insights, order volumes, revenue metrics, and market distribution</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print / Export Report
            </button>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Metric Counters -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-sm-6">
            <div class="card p-3 border-0 bg-primary-subtle text-primary-emphasis shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small fw-semibold">Completed Sales Volume</div>
                        <div class="fs-4 fw-bold"><?= format_currency($total_revenue) ?></div>
                    </div>
                    <div class="p-3 bg-white rounded-circle text-primary shadow-sm">
                        <i class="bi bi-cash-coin fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-3 border-0 bg-warning-subtle text-warning-emphasis shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small fw-semibold">Total Pre-Orders</div>
                        <div class="fs-4 fw-bold"><?= $total_orders ?></div>
                        <span class="small text-muted"><?= $completed_orders ?> completed</span>
                    </div>
                    <div class="p-3 bg-white rounded-circle text-warning shadow-sm">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-3 border-0 bg-success-subtle text-success-emphasis shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small fw-semibold">Active Farmer Stalls</div>
                        <div class="fs-4 fw-bold"><?= $active_farmers ?></div>
                        <span class="small text-muted">Selling local harvest</span>
                    </div>
                    <div class="p-3 bg-white rounded-circle text-success shadow-sm">
                        <i class="bi bi-shop fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-3 border-0 bg-info-subtle text-info-emphasis shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small fw-semibold">Registered Shoppers</div>
                        <div class="fs-4 fw-bold"><?= $active_customers ?></div>
                        <span class="small text-muted">Active customers</span>
                    </div>
                    <div class="p-3 bg-white rounded-circle text-info shadow-sm">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Tables -->
    <div class="row g-4">
        <!-- Top Performing Farmer Stalls -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fs-6"><i class="bi bi-trophy-fill text-warning me-2"></i>Stall Performance & Revenue</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Stall Name</th>
                                    <th>Contact</th>
                                    <th>Orders</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($top_stalls)): ?>
                                    <?php foreach ($top_stalls as $s): ?>
                                        <tr>
                                            <td class="fw-bold"><?= e($s['stall_name'] ?: $s['contact_name']) ?></td>
                                            <td class="small text-muted"><?= e($s['contact_name']) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= $s['orders_count'] ?></span></td>
                                            <td class="text-end fw-bold text-primary"><?= format_currency($s['stall_revenue']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category & Market Breakdown -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fs-6"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Harvest Category Distribution</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Category</th>
                                    <th>Live Produce Items</th>
                                    <th class="text-end">Avg. Unit Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($category_stats as $cat): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary-subtle text-secondary"><?= e($cat['category']) ?></span></td>
                                        <td><strong><?= $cat['product_count'] ?></strong> items</td>
                                        <td class="text-end fw-semibold text-primary"><?= format_currency($cat['avg_price']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fs-6"><i class="bi bi-geo-alt text-primary me-2"></i>Market Participation Breakdown</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Market Plaza</th>
                                    <th>Operating Days</th>
                                    <th class="text-end">Attending Stalls</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($market_breakdown as $mb): ?>
                                    <tr>
                                        <td class="fw-bold"><?= e($mb['market_name']) ?></td>
                                        <td class="small text-muted"><?= e($mb['operating_days']) ?></td>
                                        <td class="text-end"><span class="badge bg-success-subtle text-success"><?= $mb['stall_count'] ?> active</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
