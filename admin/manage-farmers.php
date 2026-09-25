<?php
/**
 * MarketLink - Admin Farmer & Stall Management (SaaS Redesign)
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Farmers & Stalls';
$active_nav = 'farmers';

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

// Counts for filter pills
$counts = ['all' => 0, 'pending' => 0, 'active' => 0, 'suspended' => 0];
try {
    $c_stmt = $pdo->query("SELECT status, COUNT(*) as c FROM users WHERE role = 'farmer' GROUP BY status");
    while ($row = $c_stmt->fetch()) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int)$row['c'];
        }
    }
    $counts['all'] = array_sum($counts);
} catch (PDOException $e) {}

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
    $sql .= " AND (u.name LIKE :q1 OR u.email LIKE :q2 OR fp.stall_name LIKE :q3 OR fp.address LIKE :q4)";
    $params[':q1'] = '%' . $search_q . '%';
    $params[':q2'] = '%' . $search_q . '%';
    $params[':q3'] = '%' . $search_q . '%';
    $params[':q4'] = '%' . $search_q . '%';
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

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Farmers & Stalls Management</h1>
        <p class="text-muted small mb-0">Review stall registrations, manage active producers, and monitor farm performance</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- SaaS Filter Pills -->
<div class="d-flex flex-wrap gap-2 mb-4 p-2 bg-white rounded-4 border shadow-xs">
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'all' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>admin/manage-farmers.php?status=all">
        All Farmers <span class="badge <?= $status_filter === 'all' ? 'bg-white text-dark' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['all'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'pending' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>admin/manage-farmers.php?status=pending">
        <i class="bi bi-clock-history text-warning me-1"></i> Pending Approval <span class="badge <?= $status_filter === 'pending' ? 'bg-white text-dark' : ($counts['pending'] > 0 ? 'bg-warning text-dark' : 'bg-light text-secondary border') ?> ms-1"><?= $counts['pending'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'active' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>admin/manage-farmers.php?status=active">
        Active Stalls <span class="badge <?= $status_filter === 'active' ? 'bg-white text-dark' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['active'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'suspended' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>admin/manage-farmers.php?status=suspended">
        Suspended <span class="badge <?= $status_filter === 'suspended' ? 'bg-white text-dark' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['suspended'] ?></span>
    </a>
</div>

<!-- Filters & Search Bar -->
<div class="card shadow-xs border-0 rounded-4 mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_URL ?>admin/manage-farmers.php" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="status" value="<?= e($status_filter) ?>">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by stall name, grower name, email, address..." value="<?= e($search_q) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 rounded-3">Filter Search</button>
                <?php if (!empty($search_q)): ?>
                    <a href="<?= BASE_URL ?>admin/manage-farmers.php?status=<?= e($status_filter) ?>" class="btn btn-outline-secondary btn-sm rounded-3">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Farmers Table -->
<div class="card shadow-xs border-0 rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0 fs-6 fw-bold">
            <i class="bi bi-shop text-primary me-1"></i> Farmer Profiles Directory (<?= count($farmers) ?>)
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($farmers)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Stall / Producer</th>
                            <th>Contact Info</th>
                            <th>Catalog & Orders</th>
                            <th>Settled Revenue</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($farmers as $f): 
                            $stall_initial = strtoupper(mb_substr($f['stall_name'] ?: $f['contact_name'], 0, 1));
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-light text-primary border d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; font-size: 0.95rem; flex-shrink: 0;">
                                            <?= e($stall_initial) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= e($f['stall_name'] ?: 'Unnamed Stall') ?></div>
                                            <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($f['contact_name']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= e($f['email']) ?></div>
                                    <small class="text-muted"><?= e($f['phone'] ?: '—') ?></small>
                                </td>
                                <td>
                                    <div class="small"><i class="bi bi-box-seam me-1 text-primary"></i><strong><?= $f['total_products'] ?></strong> produce items</div>
                                    <small class="text-muted"><i class="bi bi-receipt me-1"></i><?= $f['total_orders'] ?> orders</small>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= format_currency($f['total_revenue']) ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        <i class="bi bi-star-fill text-warning small"></i>
                                        <span class="fw-semibold small"><?= number_format($f['avg_rating'], 1) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?= get_status_badge($f['status']) ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- View Details Modal Trigger -->
                                        <button type="button" class="btn btn-light btn-sm border view-farmer-btn rounded-3" title="Inspect Full Stall Profile"
                                                data-id="<?= $f['user_id'] ?>"
                                                data-name="<?= e($f['contact_name']) ?>"
                                                data-stall="<?= e($f['stall_name'] ?: 'Unnamed Stall') ?>"
                                                data-email="<?= e($f['email']) ?>"
                                                data-phone="<?= e($f['phone'] ?: 'Not provided') ?>"
                                                data-address="<?= e($f['address'] ?: 'Not specified') ?>"
                                                data-days="<?= e($f['operating_days'] ?: 'Weekend Markets') ?>"
                                                data-window="<?= e(($f['pickup_window_start'] ?? '08:00') . ' - ' . ($f['pickup_window_end'] ?? '14:00')) ?>"
                                                data-bio="<?= e($f['bio'] ?? 'No farm description provided.') ?>"
                                                data-status="<?= e($f['status']) ?>"
                                                data-products="<?= (int)$f['total_products'] ?>"
                                                data-orders="<?= (int)$f['total_orders'] ?>"
                                                data-revenue="<?= e(format_currency($f['total_revenue'])) ?>"
                                                data-rating="<?= number_format($f['avg_rating'], 1) ?>"
                                                data-created="<?= format_date($f['created_at']) ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Public Stall Preview Link -->
                                        <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $f['user_id'] ?>" target="_blank" class="btn btn-light btn-sm border rounded-3" title="Preview Public Storefront">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>

                                        <!-- State actions -->
                                        <?php if ($f['status'] === 'pending'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-farmers.php" class="d-inline" onsubmit="return confirm('Approve this stall registration?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="farmer_id" value="<?= $f['user_id'] ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm rounded-3" title="Approve Stall">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($f['status'] === 'active'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-farmers.php" class="d-inline" onsubmit="return confirm('Suspend this farmer account?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="farmer_id" value="<?= $f['user_id'] ?>">
                                                <button type="submit" name="action" value="suspend" class="btn btn-outline-danger btn-sm rounded-3" title="Suspend Account">
                                                    <i class="bi bi-pause-circle"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($f['status'] === 'suspended'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-farmers.php" class="d-inline" onsubmit="return confirm('Reactivate this farmer stall?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="farmer_id" value="<?= $f['user_id'] ?>">
                                                <button type="submit" name="action" value="activate" class="btn btn-outline-success btn-sm rounded-3" title="Reactivate Account">
                                                    <i class="bi bi-play-circle"></i>
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
                <h6 class="fw-bold">No farmers found</h6>
                <p class="small text-muted mb-0">Try changing your search keywords or status filter.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detailed Farmer View Modal Dialog -->
<div class="modal fade" id="farmerDetailModal" tabindex="-1" aria-labelledby="farmerDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span id="dtStatusBadge" class="badge bg-success">Active</span>
                    <span id="dtCreated" class="text-muted small">Registered: —</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="rounded-4 bg-primary-subtle text-primary d-flex align-items-center justify-content-center fs-3 fw-bold" style="width: 56px; height: 56px;">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div>
                        <h4 id="dtStall" class="h5 fw-bold mb-1 text-dark">Stall Name</h4>
                        <div class="text-muted small"><i class="bi bi-person me-1"></i>Producer: <strong id="dtName">Contact</strong></div>
                    </div>
                </div>

                <!-- Stats summary strip -->
                <div class="row g-2 mb-3 text-center">
                    <div class="col-3">
                        <div class="p-2 bg-light rounded-3 border">
                            <span class="text-muted small d-block" style="font-size: 0.7rem;">Catalog Items</span>
                            <strong id="dtProducts" class="fs-6 text-dark">0</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded-3 border">
                            <span class="text-muted small d-block" style="font-size: 0.7rem;">Pre-Orders</span>
                            <strong id="dtOrders" class="fs-6 text-dark">0</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded-3 border">
                            <span class="text-muted small d-block" style="font-size: 0.7rem;">Sales Volume</span>
                            <strong id="dtRevenue" class="fs-6 text-primary">PKR 0.00</strong>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2 bg-light rounded-3 border">
                            <span class="text-muted small d-block" style="font-size: 0.7rem;">Rating</span>
                            <strong id="dtRating" class="fs-6 text-warning"><i class="bi bi-star-fill small me-1"></i>5.0</strong>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="small text-muted fw-semibold mb-1">Email Address</label>
                        <div id="dtEmail" class="p-2 bg-light rounded-3 border small fw-bold">—</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-semibold mb-1">Phone Number</label>
                        <div id="dtPhone" class="p-2 bg-light rounded-3 border small fw-bold">—</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-semibold mb-1">Operating Days</label>
                        <div id="dtDays" class="p-2 bg-light rounded-3 border small">—</div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted fw-semibold mb-1">Pickup Schedule</label>
                        <div id="dtWindow" class="p-2 bg-light rounded-3 border small">—</div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted fw-semibold mb-1">Location & Stall Address</label>
                        <div id="dtAddress" class="p-2 bg-light rounded-3 border small">—</div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted fw-semibold mb-1">Producer Bio & Farming Practices</label>
                        <div id="dtBio" class="p-3 bg-light rounded-3 border small text-secondary" style="line-height: 1.6;">—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-light d-flex justify-content-between">
                <div class="small text-muted">User ID: <code id="dtId">#0</code></div>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close Dialog</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailModalEl = document.getElementById('farmerDetailModal');
    if (detailModalEl) {
        const detailModal = new bootstrap.Modal(detailModalEl);
        document.querySelectorAll('.view-farmer-btn').forEach(function(btn) {
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
                const status = this.getAttribute('data-status');
                const products = this.getAttribute('data-products');
                const orders = this.getAttribute('data-orders');
                const revenue = this.getAttribute('data-revenue');
                const rating = this.getAttribute('data-rating');
                const created = this.getAttribute('data-created');

                document.getElementById('dtId').textContent = '#' + id;
                document.getElementById('dtName').textContent = name;
                document.getElementById('dtStall').textContent = stall;
                document.getElementById('dtEmail').textContent = email;
                document.getElementById('dtPhone').textContent = phone;
                document.getElementById('dtAddress').textContent = address;
                document.getElementById('dtDays').textContent = days;
                document.getElementById('dtWindow').textContent = windowVal;
                document.getElementById('dtBio').textContent = bio;
                document.getElementById('dtProducts').textContent = products;
                document.getElementById('dtOrders').textContent = orders;
                document.getElementById('dtRevenue').textContent = revenue;
                document.getElementById('dtRating').innerHTML = '<i class="bi bi-star-fill small me-1"></i>' + rating;
                document.getElementById('dtCreated').textContent = 'Registered: ' + created;

                const statusBadge = document.getElementById('dtStatusBadge');
                if (status === 'pending') {
                    statusBadge.className = 'badge bg-warning text-dark';
                    statusBadge.textContent = 'Pending Approval';
                } else if (status === 'active') {
                    statusBadge.className = 'badge bg-success';
                    statusBadge.textContent = 'Active Stall';
                } else {
                    statusBadge.className = 'badge bg-danger';
                    statusBadge.textContent = 'Suspended';
                }

                detailModal.show();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
