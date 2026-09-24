<?php
/**
 * MarketLink - Platform Administrator Dashboard
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Platform Admin Dashboard';

// Handle quick approve / reject of pending farmers via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $target_farmer_id = (int)($_POST['farmer_id'] ?? 0);
        $action = $_POST['action'];

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
                    set_flash('warning', 'Farmer account has been suspended.');
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
$pending_farmers_list = [];

try {
    $total_farmers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer'")->fetchColumn();
    $pending_farmers_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND status = 'pending'")->fetchColumn();
    $total_customers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $total_markets = (int)$pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
    $total_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    // Fetch list of pending farmers
    $pf_stmt = $pdo->query("SELECT u.user_id, u.name, u.email, u.phone, u.created_at, fp.stall_name, fp.address, fp.operating_days 
                            FROM users u 
                            LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                            WHERE u.role = 'farmer' AND u.status = 'pending' 
                            ORDER BY u.created_at DESC");
    $pending_farmers_list = $pf_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Admin dashboard metrics error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-shield-check text-primary me-2"></i>Platform Administration</h1>
            <p class="text-muted small mb-0">Overview of MarketLink community, stall approvals, and system health</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>admin/manage-markets.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-geo me-1"></i> Manage Markets
            </a>
            <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-primary btn-sm">
                <i class="bi bi-graph-up me-1"></i> View Reports
            </a>
        </div>
    </div>

    <!-- Overview Counters -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-sm-6">
            <div class="card p-3 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Registered Farmers</div>
                        <div class="fs-4 fw-bold"><?= $total_farmers ?></div>
                        <?php if ($pending_farmers_count > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $pending_farmers_count ?> Pending Review</span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success">All Reviewed</span>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 bg-primary-subtle text-primary rounded-3">
                        <i class="bi bi-shop fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-3 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Active Customers</div>
                        <div class="fs-4 fw-bold"><?= $total_customers ?></div>
                        <span class="badge bg-secondary-subtle text-secondary">Verified Shoppers</span>
                    </div>
                    <div class="p-3 bg-info-subtle text-info rounded-3">
                        <i class="bi bi-people fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-3 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Farmers Markets</div>
                        <div class="fs-4 fw-bold"><?= $total_markets ?></div>
                        <span class="badge bg-success-subtle text-success">Map Geocoded</span>
                    </div>
                    <div class="p-3 bg-success-subtle text-success rounded-3">
                        <i class="bi bi-geo-alt fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="card p-3 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Pre-Orders</div>
                        <div class="fs-4 fw-bold"><?= $total_orders ?></div>
                        <span class="badge bg-primary-subtle text-primary">Platform Wide</span>
                    </div>
                    <div class="p-3 bg-warning-subtle text-warning rounded-3">
                        <i class="bi bi-receipt-cutoff fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Farmer Approvals Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6">
                <i class="bi bi-person-check-fill text-warning me-1"></i> Farmer Registrations Pending Approval 
                <?php if ($pending_farmers_count > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-2"><?= $pending_farmers_count ?></span>
                <?php endif; ?>
            </h5>
            <a href="<?= BASE_URL ?>admin/manage-farmers.php" class="btn btn-outline-secondary btn-sm py-0 px-2">All Farmers</a>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($pending_farmers_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Stall Name</th>
                                <th>Contact Person</th>
                                <th>Email / Phone</th>
                                <th>Location</th>
                                <th>Submitted Date</th>
                                <th class="text-end">Review Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_farmers_list as $pf): ?>
                                <tr>
                                    <td class="fw-bold"><?= e($pf['stall_name'] ?: 'Unnamed Stall') ?></td>
                                    <td><?= e($pf['name']) ?></td>
                                    <td>
                                        <div><?= e($pf['email']) ?></div>
                                        <small class="text-muted"><?= e($pf['phone'] ?: '—') ?></small>
                                    </td>
                                    <td class="small text-muted"><?= e($pf['address'] ?: '—') ?></td>
                                    <td><?= format_date($pf['created_at']) ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= BASE_URL ?>admin/dashboard.php" class="d-inline-flex gap-1" onsubmit="return confirm('Confirm action for this farmer account?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="farmer_id" value="<?= $pf['user_id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm py-1 px-2">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="suspend" class="btn btn-outline-danger btn-sm py-1 px-2">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-check2-all fs-1 text-success d-block mb-1"></i>
                    <p class="mb-0 small">No pending farmer applications at the moment. All registered farmers have been reviewed.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
