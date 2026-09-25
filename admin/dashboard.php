<?php
/**
 * MarketLink - Platform Administrator Dashboard (SaaS Redesign)
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Platform Overview';
$active_nav = 'dashboard';

// Handle quick approve / reject of pending farmers via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $target_farmer_id = (int)($_POST['farmer_id'] ?? 0);
        $action = sanitize_input($_POST['action'] ?? '');

        if ($target_farmer_id > 0) {
            try {
                if ($action === 'approve') {
                    $upd = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = :uid AND role = 'farmer'");
                    $upd->execute([':uid' => $target_farmer_id]);
                    create_notification($pdo, $target_farmer_id, "Congratulations! Your farmer stall has been approved by the platform administrator and is now live to customers.");
                    set_flash('success', 'Farmer account approved and activated successfully.');
                } elseif ($action === 'suspend') {
                    $upd = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE user_id = :uid AND role = 'farmer'");
                    $upd->execute([':uid' => $target_farmer_id]);
                    set_flash('warning', 'Farmer application rejected and account suspended.');
                }
            } catch (PDOException $e) {
                error_log("Admin action error: " . $e->getMessage());
                set_flash('danger', 'Failed to update farmer status.');
            }
        }
    }
    redirect(BASE_URL . 'admin/dashboard.php');
}

// Fetch Admin Metrics
$total_farmers = 0;
$pending_farmers_count = 0;
$total_customers = 0;
$total_markets = 0;
$total_orders = 0;
$total_products = 0;
$total_completed_revenue = 0.00;
$pending_farmers_list = [];
$recent_orders = [];

try {
    $total_farmers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer'")->fetchColumn();
    $pending_farmers_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND status = 'pending'")->fetchColumn();
    $total_customers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $total_markets = (int)$pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
    $total_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $total_products = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $total_completed_revenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed'")->fetchColumn();

    // Fetch list of pending farmers
    $pf_stmt = $pdo->query("SELECT u.user_id, u.name, u.email, u.phone, u.created_at, fp.stall_name, fp.address, fp.operating_days, fp.pickup_window_start, fp.pickup_window_end, fp.bio 
                            FROM users u 
                            LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                            WHERE u.role = 'farmer' AND u.status = 'pending' 
                            ORDER BY u.created_at DESC");
    $pending_farmers_list = $pf_stmt->fetchAll();

    // Recent 5 platform orders
    $ro_stmt = $pdo->query("SELECT o.order_id, o.customer_id, o.farmer_id, o.total_amount, o.status, o.pickup_date, o.created_at,
                                   c.name as customer_name, f.name as farmer_name, fp.stall_name 
                            FROM orders o
                            JOIN users c ON o.customer_id = c.user_id
                            JOIN users f ON o.farmer_id = f.user_id
                            LEFT JOIN farmer_profiles fp ON f.user_id = fp.farmer_id
                            ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $ro_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Admin dashboard metrics error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Platform Overview</h1>
        <p class="text-muted small mb-0">System health monitoring, stall approvals queue, and community engagement metrics</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="bi bi-geo-alt-fill me-1 text-primary"></i> + New Market
        </a>
        <a href="<?= BASE_URL ?>admin/moderation.php" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="bi bi-broadcast me-1 text-info"></i> Broadcast Notice
        </a>
        <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-primary btn-sm rounded-3">
            <i class="bi bi-graph-up me-1"></i> Full Analytics
        </a>
    </div>
</div>

<!-- SaaS 4-Metric Grid -->
<div class="row g-3 mb-4">
    <!-- Registered Farmers Card -->
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-0 shadow-xs rounded-4 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold mb-1">Farmer Stalls</div>
                    <div class="fs-4 fw-bold text-dark"><?= $total_farmers ?></div>
                    <div class="mt-2">
                        <?php if ($pending_farmers_count > 0): ?>
                            <span class="badge bg-warning text-dark px-2 py-1 rounded-pill" style="font-size: 0.72rem;">
                                <i class="bi bi-exclamation-circle-fill me-1"></i><?= $pending_farmers_count ?> Needs Review
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill" style="font-size: 0.72rem;">
                                <i class="bi bi-check-circle-fill me-1"></i>All Stalls Active
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rounded-4 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 1.5rem;">
                    <i class="bi bi-shop"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Customers Card -->
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-0 shadow-xs rounded-4 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold mb-1">Registered Shoppers</div>
                    <div class="fs-4 fw-bold text-dark"><?= $total_customers ?></div>
                    <div class="mt-2">
                        <span class="badge bg-secondary-subtle text-secondary px-2 py-1 rounded-pill" style="font-size: 0.72rem;">
                            <i class="bi bi-people-fill me-1"></i>Local Community
                        </span>
                    </div>
                </div>
                <div class="rounded-4 bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 1.5rem;">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Markets Card -->
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-0 shadow-xs rounded-4 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold mb-1">Farmers Markets</div>
                    <div class="fs-4 fw-bold text-dark"><?= $total_markets ?></div>
                    <div class="mt-2">
                        <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill" style="font-size: 0.72rem;">
                            <i class="bi bi-geo-alt-fill me-1"></i>Map Directory
                        </span>
                    </div>
                </div>
                <div class="rounded-4 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 1.5rem;">
                    <i class="bi bi-pin-map-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Pre-Orders Volume Card -->
    <div class="col-xl-3 col-sm-6">
        <div class="card p-3 border-0 shadow-xs rounded-4 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold mb-1">Platform Pre-Orders</div>
                    <div class="fs-4 fw-bold text-dark"><?= $total_orders ?></div>
                    <div class="mt-2">
                        <span class="badge bg-primary-subtle text-primary px-2 py-1 rounded-pill" style="font-size: 0.72rem;">
                            <?= format_currency($total_completed_revenue) ?> Settled
                        </span>
                    </div>
                </div>
                <div class="rounded-4 bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; font-size: 1.5rem;">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Columns -->
<div class="row g-4">
    <!-- Left Column: Pending Farmer Approvals -->
    <div class="col-lg-8">
        <div class="card shadow-xs border-0 rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="bi bi-person-check-fill text-warning me-1"></i> Pending Farmer Registrations
                    </h5>
                    <?php if ($pending_farmers_count > 0): ?>
                        <span class="badge bg-danger rounded-pill px-2" style="font-size: 0.72rem;"><?= $pending_farmers_count ?> Action Required</span>
                    <?php endif; ?>
                </div>
                <a href="<?= BASE_URL ?>admin/manage-farmers.php" class="btn btn-outline-secondary btn-sm rounded-3 py-1 px-3">
                    View All Farmers
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($pending_farmers_list)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Stall & Producer</th>
                                    <th>Contact Info</th>
                                    <th>Operating Days</th>
                                    <th>Submitted</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_farmers_list as $pf): 
                                    $stall_initial = strtoupper(mb_substr($pf['stall_name'] ?: $pf['name'], 0, 1));
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem; flex-shrink: 0;">
                                                    <?= e($stall_initial) ?>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div class="fw-bold text-dark text-truncate"><?= e($pf['stall_name'] ?: 'Unnamed Stall') ?></div>
                                                    <small class="text-muted d-block text-truncate"><?= e($pf['name']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= e($pf['email']) ?></div>
                                            <small class="text-muted"><?= e($pf['phone'] ?: 'No phone provided') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border small"><?= e($pf['operating_days'] ?: 'Weekend Market') ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= format_date($pf['created_at']) ?></small>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <!-- Review Modal Trigger -->
                                                <button type="button" class="btn btn-light btn-sm border review-farmer-btn rounded-3" title="Inspect Application Details"
                                                        data-id="<?= $pf['user_id'] ?>"
                                                        data-name="<?= e($pf['name']) ?>"
                                                        data-stall="<?= e($pf['stall_name'] ?: 'Unnamed Stall') ?>"
                                                        data-email="<?= e($pf['email']) ?>"
                                                        data-phone="<?= e($pf['phone'] ?: 'Not provided') ?>"
                                                        data-address="<?= e($pf['address'] ?: 'Not provided') ?>"
                                                        data-days="<?= e($pf['operating_days'] ?: 'Weekend Markets') ?>"
                                                        data-window="<?= e(($pf['pickup_window_start'] ?? '08:00') . ' - ' . ($pf['pickup_window_end'] ?? '14:00')) ?>"
                                                        data-bio="<?= e($pf['bio'] ?: 'No farm story or bio submitted.') ?>"
                                                        data-created="<?= format_date($pf['created_at']) ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>

                                                <form method="POST" action="<?= BASE_URL ?>admin/dashboard.php" class="d-inline" onsubmit="return confirm('Approve and activate <?= e($pf['name']) ?>\'s farmer stall?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="farmer_id" value="<?= $pf['user_id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm rounded-3" title="Approve Stall">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>

                                                <form method="POST" action="<?= BASE_URL ?>admin/dashboard.php" class="d-inline" onsubmit="return confirm('Reject and suspend this farmer registration?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="farmer_id" value="<?= $pf['user_id'] ?>">
                                                    <button type="submit" name="action" value="suspend" class="btn btn-outline-danger btn-sm rounded-3" title="Reject Application">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-patch-check-fill fs-1 text-success d-block mb-2"></i>
                        <h6 class="fw-bold text-dark">All Farmer Registrations Reviewed</h6>
                        <p class="small text-muted mb-0">There are no pending stall approvals in the queue.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Orders Feed -->
        <div class="card shadow-xs border-0 rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-receipt text-primary me-1"></i> Recent Platform Pre-Orders
                </h5>
                <a href="<?= BASE_URL ?>admin/reports.php" class="small text-decoration-none fw-semibold">View Reports <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recent_orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Farmer Stall</th>
                                    <th>Pickup Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $ord): ?>
                                    <tr>
                                        <td><strong>#<?= $ord['order_id'] ?></strong></td>
                                        <td><?= e($ord['customer_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e($ord['stall_name'] ?: $ord['farmer_name']) ?></span></td>
                                        <td><small class="text-muted"><?= format_date($ord['pickup_date']) ?></small></td>
                                        <td class="fw-bold text-primary"><?= format_currency($ord['total_amount']) ?></td>
                                        <td><?= get_status_badge($ord['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <p class="small mb-0">No orders recorded on the platform yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Quick Operations & System Health -->
    <div class="col-lg-4">
        <!-- Quick Admin Operations Card -->
        <div class="card shadow-xs border-0 rounded-4 mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Admin Shortcuts
                </h5>
            </div>
            <div class="card-body p-3">
                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-outline-primary text-start p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-plus-circle-dotted fs-5"></i>
                            <div>
                                <div class="fw-bold small">Add Farmers Market</div>
                                <small class="text-muted" style="font-size: 0.75rem;">Set coordinates & operating hours</small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>

                    <a href="<?= BASE_URL ?>admin/moderation.php" class="btn btn-outline-secondary text-start p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-broadcast fs-5 text-info"></i>
                            <div>
                                <div class="fw-bold small">Send System Broadcast</div>
                                <small class="text-muted" style="font-size: 0.75rem;">Deliver in-app bulletin to users</small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>

                    <a href="<?= BASE_URL ?>admin/manage-farmers.php?status=pending" class="btn btn-outline-secondary text-start p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-lines-fill fs-5 text-warning"></i>
                            <div>
                                <div class="fw-bold small">Pending Farmers Queue</div>
                                <small class="text-muted" style="font-size: 0.75rem;"><?= $pending_farmers_count ?> application(s) awaiting review</small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>

                    <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-outline-secondary text-start p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-printer fs-5 text-success"></i>
                            <div>
                                <div class="fw-bold small">Export Monthly Report</div>
                                <small class="text-muted" style="font-size: 0.75rem;">Printable summary & charts</small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right small opacity-50"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- System Governance Widget -->
        <div class="card shadow-xs border-0 rounded-4 bg-dark text-white p-4" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-shield-lock-fill text-primary fs-4"></i>
                <h6 class="fw-bold mb-0 text-white">Platform Health</h6>
            </div>
            <p class="small mb-3 text-white-50" style="font-size: 0.82rem; line-height: 1.5;">
                MarketLink multi-role portal is running live with row-level role enforcement, geocoded map integration, and CSRF protection.
            </p>
            <div class="d-flex align-items-center justify-content-between pt-2 border-top border-white border-opacity-10 small">
                <span>Produce Catalog: <strong><?= $total_products ?> items</strong></span>
                <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Healthy</span>
            </div>
        </div>
    </div>
</div>

<!-- Farmer Details Modal Dialog -->
<div class="modal fade" id="farmerReviewModal" tabindex="-1" aria-labelledby="farmerReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark">Pending Approval</span>
                    <span id="frmCreated" class="text-muted small">Submitted: —</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="rounded-4 bg-primary-subtle text-primary d-flex align-items-center justify-content-center fs-3 fw-bold" style="width: 56px; height: 56px;">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div>
                        <h4 id="frmStall" class="h5 fw-bold mb-1 text-dark">Stall Name</h4>
                        <div class="text-muted small"><i class="bi bi-person me-1"></i>Grower: <strong id="frmName">Contact</strong></div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="small text-muted fw-semibold mb-1">Email Address</label>
                        <div id="frmEmail" class="p-2 bg-light rounded-3 border small fw-bold">—</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-semibold mb-1">Phone Number</label>
                        <div id="frmPhone" class="p-2 bg-light rounded-3 border small fw-bold">—</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-semibold mb-1">Operating Days</label>
                        <div id="frmDays" class="p-2 bg-light rounded-3 border small">—</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-semibold mb-1">Pickup Hours</label>
                        <div id="frmWindow" class="p-2 bg-light rounded-3 border small">—</div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted fw-semibold mb-1">Farm / Stall Location</label>
                        <div id="frmAddress" class="p-2 bg-light rounded-3 border small">—</div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted fw-semibold mb-1">Producer Bio & Practices</label>
                        <div id="frmBio" class="p-3 bg-light rounded-3 border small text-secondary" style="line-height: 1.6;">—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <form method="POST" action="<?= BASE_URL ?>admin/dashboard.php" class="d-inline" id="modalRejectForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="farmer_id" id="modalRejectFarmerId" value="">
                        <button type="submit" name="action" value="suspend" class="btn btn-outline-danger btn-sm rounded-3">
                            <i class="bi bi-x-lg me-1"></i> Reject Application
                        </button>
                    </form>
                    <form method="POST" action="<?= BASE_URL ?>admin/dashboard.php" class="d-inline" id="modalApproveForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="farmer_id" id="modalApproveFarmerId" value="">
                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm rounded-3">
                            <i class="bi bi-check-lg me-1"></i> Approve & Activate
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('farmerReviewModal');
    if (modalEl) {
        const reviewModal = new bootstrap.Modal(modalEl);
        document.querySelectorAll('.review-farmer-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const stall = this.getAttribute('data-stall');
                const email = this.getAttribute('data-email');
                const phone = this.getAttribute('data-phone');
                const address = this.getAttribute('data-address');
                const days = this.getAttribute('data-days');
                const windowVal = this.getAttribute('data-window');
                const bio = this.getAttribute('data-bio');
                const created = this.getAttribute('data-created');

                document.getElementById('frmName').textContent = name;
                document.getElementById('frmStall').textContent = stall;
                document.getElementById('frmEmail').textContent = email;
                document.getElementById('frmPhone').textContent = phone;
                document.getElementById('frmAddress').textContent = address;
                document.getElementById('frmDays').textContent = days;
                document.getElementById('frmWindow').textContent = windowVal;
                document.getElementById('frmBio').textContent = bio;
                document.getElementById('frmCreated').textContent = 'Submitted: ' + created;

                document.getElementById('modalApproveFarmerId').value = id;
                document.getElementById('modalRejectFarmerId').value = id;

                reviewModal.show();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
