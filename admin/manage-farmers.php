<?php
/**
 * MarketLink - Admin Farmer & Stall Management
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Manage Farmers & Stalls';

// Handle Farmer Status Change (Approve, Suspend, Activate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session submission.');
        redirect(BASE_URL . 'admin/manage-farmers.php');
    }

    $target_farmer_id = (int)($_POST['farmer_id'] ?? 0);
    $action = sanitize_input($_POST['action'] ?? '');

    if ($target_farmer_id > 0) {
        try {
            if ($action === 'approve') {
                $upd = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = :uid AND role = 'farmer'");
                $upd->execute([':uid' => $target_farmer_id]);
                create_notification($pdo, $target_farmer_id, "Congratulations! Your farmer stall has been approved by the platform administrator and is now live to customers.");
                set_flash('success', 'Farmer stall approved and activated.');
            } elseif ($action === 'suspend') {
                $upd = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE user_id = :uid AND role = 'farmer'");
                $upd->execute([':uid' => $target_farmer_id]);
                create_notification($pdo, $target_farmer_id, "Your farmer account has been suspended by the platform administrator. Please contact support.");
                set_flash('warning', 'Farmer account has been suspended.');
            } elseif ($action === 'activate') {
                $upd = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = :uid AND role = 'farmer'");
                $upd->execute([':uid' => $target_farmer_id]);
                create_notification($pdo, $target_farmer_id, "Your farmer stall account has been reactivated.");
                set_flash('success', 'Farmer account reactivated.');
            }
        } catch (PDOException $e) {
            error_log("Admin update farmer error: " . $e->getMessage());
            set_flash('danger', 'Database error while updating farmer status.');
        }
    }
    redirect(BASE_URL . 'admin/manage-farmers.php');
}

// Filter and Search parameters
$search_q = sanitize_input($_GET['q'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? 'all');

$sql = "SELECT u.user_id, u.name as contact_name, u.email, u.phone, u.status, u.created_at, 
        fp.stall_name, fp.address, fp.operating_days, fp.pickup_window_start, fp.pickup_window_end, 
        (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id) as total_products, 
        (SELECT COUNT(*) FROM orders WHERE farmer_id = u.user_id) as total_orders, 
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE farmer_id = u.user_id AND status = 'completed') as total_revenue,
        (SELECT COALESCE(AVG(rating), 5) FROM reviews WHERE farmer_id = u.user_id) as avg_rating 
        FROM users u 
        LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
        WHERE u.role = 'farmer'";
$params = [];

if ($status_filter !== 'all' && !empty($status_filter)) {
    $sql .= " AND u.status = :status";
    $params[':status'] = $status_filter;
}
if (!empty($search_q)) {
    $sql .= " AND (u.name LIKE :q OR u.email LIKE :q OR fp.stall_name LIKE :q OR fp.address LIKE :q)";
    $params[':q'] = '%' . $search_q . '%';
}
$sql .= " ORDER BY (u.status = 'pending') DESC, u.created_at DESC";

$farmers = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $farmers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Admin fetch farmers error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-people text-primary me-2"></i>Manage Farmers & Stalls</h1>
            <p class="text-muted small mb-0">Review new stall registrations, manage active growers, and audit sales performance</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Admin Panel
            </a>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form action="<?= BASE_URL ?>admin/manage-farmers.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search by stall name, grower name, email..." value="<?= e($search_q) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Approval Only</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Farmers</option>
                        <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended Accounts</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                    <?php if (!empty($search_q) || $status_filter !== 'all'): ?>
                        <a href="<?= BASE_URL ?>admin/manage-farmers.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Farmers Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6">Registered Farmers (<?= count($farmers) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($farmers)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Stall Name & Contact</th>
                                <th>Email / Phone</th>
                                <th>Location & Schedule</th>
                                <th>Products</th>
                                <th>Orders / Volume</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($farmers as $f): ?>
                                <tr class="<?= $f['status'] === 'pending' ? 'table-warning' : ($f['status'] === 'suspended' ? 'table-light opacity-75' : '') ?>">
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($f['stall_name'] ?: 'Unnamed Stall') ?></div>
                                        <div class="small text-muted"><i class="bi bi-person me-1"></i><?= e($f['contact_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small"><?= e($f['email']) ?></div>
                                        <small class="text-muted"><?= e($f['phone'] ?: '—') ?></small>
                                    </td>
                                    <td>
                                        <div class="small text-truncate" style="max-width: 200px;" title="<?= e($f['address']) ?>">
                                            <i class="bi bi-geo-alt me-1 text-danger"></i><?= e($f['address'] ?: '—') ?>
                                        </div>
                                        <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= e($f['operating_days'] ?: 'Sat,Sun') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= $f['total_products'] ?> items</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-primary"><?= format_currency($f['total_revenue']) ?></div>
                                        <small class="text-muted"><?= $f['total_orders'] ?> pre-order(s)</small>
                                    </td>
                                    <td>
                                        <?= get_status_badge($f['status']) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <?php if ($f['status'] === 'pending'): ?>
                                                <form method="POST" action="<?= BASE_URL ?>admin/manage-farmers.php" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="farmer_id" value="<?= $f['user_id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm py-1 px-2" title="Approve Stall">
                                                        <i class="bi bi-check-lg me-1"></i> Approve
                                                    </button>
                                                    <button type="submit" name="action" value="suspend" class="btn btn-outline-danger btn-sm py-1 px-2" title="Reject / Suspend" onclick="return confirm('Reject this application?');">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($f['status'] === 'active'): ?>
                                                <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $f['user_id'] ?>" target="_blank" class="btn btn-light btn-sm border" title="Preview Public Stall">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <form method="POST" action="<?= BASE_URL ?>admin/manage-farmers.php" class="d-inline" onsubmit="return confirm('Suspend this farmer account? They will not be able to log in or sell produce.');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="farmer_id" value="<?= $f['user_id'] ?>">
                                                    <button type="submit" name="action" value="suspend" class="btn btn-outline-danger btn-sm py-1 px-2" title="Suspend Account">
                                                        <i class="bi bi-slash-circle"></i>
                                                    </button>
                                                </form>
                                            <?php elseif ($f['status'] === 'suspended'): ?>
                                                <form method="POST" action="<?= BASE_URL ?>admin/manage-farmers.php" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="farmer_id" value="<?= $f['user_id'] ?>">
                                                    <button type="submit" name="action" value="activate" class="btn btn-outline-success btn-sm py-1 px-2" title="Reactivate Account">
                                                        <i class="bi bi-check-circle me-1"></i> Reactivate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1 text-secondary-subtle d-block mb-2"></i>
                    <h6>No farmers found for this filter</h6>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
