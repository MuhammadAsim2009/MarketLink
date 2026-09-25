<?php
/**
 * MarketLink - Platform Reports & Analytics
 * SaaS Redesign with Sidebar Layout
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Platform Analytics & Reports';
$active_nav = 'reports';

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
$total_products_count = 0;

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
    foreach ($category_stats as $cs) {
        $total_products_count += (int)$cs['product_count'];
    }

    // Market Breakdown
    $mkt_stmt = $pdo->query("SELECT m.market_id, m.market_name, m.operating_days, 
                             (SELECT COUNT(*) FROM market_farmers WHERE market_id = m.market_id) as stall_count 
                             FROM markets m ORDER BY stall_count DESC");
    $market_breakdown = $mkt_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Reports load error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header & Action Controls -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-2 border-bottom">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge badge-primary px-2 py-1"><i class="bi bi-graph-up-arrow me-1"></i> Analytics</span>
            <span class="badge badge-neutral px-2 py-1">Financial & Inventory Intelligence</span>
        </div>
        <h1 class="h3 fw-bold mb-1">Platform Reports & Analytics</h1>
        <p class="text-muted small mb-0">High-level financial summaries, order turnover, stall leaderboards, and market participation</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print / Export Report
        </button>
        <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-primary btn-sm">
            <i class="bi bi-speedometer2 me-1"></i> Admin Dashboard
        </a>
    </div>
</div>

<!-- Key Financial & Operational Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="card border shadow-xs p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle text-primary bg-primary-subtle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi bi-cash-coin fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Settled Platform Volume</div>
                    <div class="fs-4 fw-bold text-dark"><?= format_currency($total_revenue) ?></div>
                    <small class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Completed Orders</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card border shadow-xs p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle text-warning bg-warning-subtle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi bi-receipt fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Pre-Order Volume</div>
                    <div class="fs-4 fw-bold text-dark"><?= $total_orders ?></div>
                    <small class="text-muted"><?= $completed_orders ?> fulfilled</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card border shadow-xs p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle text-success bg-success-subtle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi bi-shop fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Active Farmer Stalls</div>
                    <div class="fs-4 fw-bold text-dark"><?= $active_farmers ?></div>
                    <small class="text-muted">Verified producers</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="card border shadow-xs p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 rounded-circle text-info bg-info-subtle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi bi-people fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Registered Shoppers</div>
                    <div class="fs-4 fw-bold text-dark"><?= $active_customers ?></div>
                    <small class="text-muted">Active consumer base</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Breakdown Tables & Charts -->
<div class="row g-4">
    <!-- Top Performing Farmer Stalls -->
    <div class="col-lg-6">
        <div class="card border shadow-xs h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-trophy-fill text-warning me-2"></i>Stall Performance & Revenue Leaderboard
                </h5>
                <span class="badge badge-neutral">Top 8 Producers</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Rank & Stall</th>
                                <th>Orders</th>
                                <th class="text-end">Gross Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($top_stalls)): ?>
                                <?php $rank = 1; foreach ($top_stalls as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge <?= $rank === 1 ? 'badge-warning' : ($rank === 2 ? 'badge-info' : ($rank === 3 ? 'badge-primary' : 'badge-neutral')) ?>" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%;">
                                                    <?= $rank ?>
                                                </span>
                                                <div>
                                                    <div class="fw-bold text-dark small"><?= e($s['stall_name'] ?: $s['contact_name']) ?></div>
                                                    <small class="text-muted"><?= e($s['contact_name']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= $s['orders_count'] ?> orders</span>
                                        </td>
                                        <td class="text-end fw-bold text-primary">
                                            <?= format_currency($s['stall_revenue']) ?>
                                        </td>
                                    </tr>
                                <?php $rank++; endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted small">No stall sales records logged yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Category & Market Participation -->
    <div class="col-lg-6">
        <!-- Harvest Category Distribution Card with Progress Bars -->
        <div class="card border shadow-xs mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-pie-chart-fill text-primary me-2"></i>Harvest Category Distribution
                </h5>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($category_stats)): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($category_stats as $cat): 
                            $pct = $total_products_count > 0 ? round(($cat['product_count'] / $total_products_count) * 100) : 0;
                        ?>
                            <div>
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="fw-semibold text-dark"><?= e($cat['category']) ?></span>
                                    <span class="text-muted"><?= $cat['product_count'] ?> items (<?= $pct ?>%) &bull; Avg <?= format_currency($cat['avg_price']) ?></span>
                                </div>
                                <div class="progress" style="height: 7px; border-radius: 4px; background: var(--surface-2);">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%; background: var(--primary);" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center mb-0 py-3">No produce items categorized yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Market Attendance Breakdown Card -->
        <div class="card border shadow-xs">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-geo-alt-fill text-primary me-2"></i>Market Plaza Stall Attendance
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Market Plaza</th>
                                <th>Operating Schedule</th>
                                <th class="text-end">Attending Stalls</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($market_breakdown as $mb): ?>
                                <tr>
                                    <td class="fw-bold text-dark small"><?= e($mb['market_name']) ?></td>
                                    <td><span class="badge badge-primary"><?= e($mb['operating_days']) ?></span></td>
                                    <td class="text-end">
                                        <span class="badge badge-success"><?= $mb['stall_count'] ?> active</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
