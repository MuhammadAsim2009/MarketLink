<?php
/**
 * MarketLink - Admin Customer Management (SaaS Redesign)
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Customer Accounts';
$active_nav = 'customers';

// Handle Customer Account Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request session.');
        redirect(BASE_URL . 'admin/manage-customers.php');
    }

    $target_customer_id = (int)($_POST['customer_id'] ?? 0);
    $action = sanitize_input($_POST['action'] ?? '');

    if ($target_customer_id > 0) {
        try {
            if ($action === 'suspend') {
                $upd = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE user_id = :uid AND role = 'customer'");
                $upd->execute([':uid' => $target_customer_id]);
                set_flash('warning', 'Customer account has been suspended.');
            } elseif ($action === 'activate') {
                $upd = $pdo->prepare("UPDATE users SET status = 'active' WHERE user_id = :uid AND role = 'customer'");
                $upd->execute([':uid' => $target_customer_id]);
                set_flash('success', 'Customer account has been reactivated.');
            }
        } catch (PDOException $e) {
            error_log("Admin update customer error: " . $e->getMessage());
            set_flash('danger', 'Database error.');
        }
    }
    redirect(BASE_URL . 'admin/manage-customers.php');
}

$search_q = sanitize_input($_GET['q'] ?? '');
$status_filter = sanitize_input($_GET['status'] ?? 'all');

// Counts
$counts = ['all' => 0, 'active' => 0, 'suspended' => 0];
try {
    $c_stmt = $pdo->query("SELECT status, COUNT(*) as c FROM users WHERE role = 'customer' GROUP BY status");
    while ($row = $c_stmt->fetch()) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int)$row['c'];
        }
    }
    $counts['all'] = array_sum($counts);
} catch (PDOException $e) {}

$sql = "SELECT u.user_id, u.name, u.email, u.phone, u.status, u.created_at, 
        (SELECT COUNT(*) FROM orders WHERE customer_id = u.user_id) as orders_count, 
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE customer_id = u.user_id AND status = 'completed') as total_spent,
        (SELECT COUNT(*) FROM reviews WHERE customer_id = u.user_id) as reviews_count 
        FROM users u 
        WHERE u.role = 'customer'";
$params = [];

if ($status_filter !== 'all' && !empty($status_filter)) {
    $sql .= " AND u.status = :status";
    $params[':status'] = $status_filter;
}
if (!empty($search_q)) {
    $sql .= " AND (u.name LIKE :q1 OR u.email LIKE :q2 OR u.phone LIKE :q3)";
    $params[':q1'] = '%' . $search_q . '%';
    $params[':q2'] = '%' . $search_q . '%';
    $params[':q3'] = '%' . $search_q . '%';
}
$sql .= " ORDER BY u.created_at DESC";

$customers = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Admin fetch customers error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Customer Accounts Management</h1>
        <p class="text-muted small mb-0">Monitor registered shoppers, track community orders, and manage access statuses</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- SaaS Filter Pills -->
<div class="d-flex flex-wrap gap-2 mb-4 p-2 bg-white rounded-4 border shadow-xs">
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'all' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>admin/manage-customers.php?status=all">
        All Shoppers <span class="badge <?= $status_filter === 'all' ? 'bg-white text-dark' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['all'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'active' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>admin/manage-customers.php?status=active">
        Active Accounts <span class="badge <?= $status_filter === 'active' ? 'bg-white text-dark' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['active'] ?></span>
    </a>
    <a class="btn btn-sm rounded-pill px-3 <?= $status_filter === 'suspended' ? 'btn-primary' : 'btn-ghost text-secondary' ?>" href="<?= BASE_URL ?>admin/manage-customers.php?status=suspended">
        Suspended <span class="badge <?= $status_filter === 'suspended' ? 'bg-white text-dark' : 'bg-light text-secondary border' ?> ms-1"><?= $counts['suspended'] ?></span>
    </a>
</div>

<!-- Filters & Search Bar -->
<div class="card shadow-xs border-0 rounded-4 mb-4">
    <div class="card-body p-3">
        <form action="<?= BASE_URL ?>admin/manage-customers.php" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="status" value="<?= e($status_filter) ?>">
            <div class="col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search customer by full name, email address, phone..." value="<?= e($search_q) ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 rounded-3">Filter Search</button>
                <?php if (!empty($search_q)): ?>
                    <a href="<?= BASE_URL ?>admin/manage-customers.php?status=<?= e($status_filter) ?>" class="btn btn-outline-secondary btn-sm rounded-3">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card shadow-xs border-0 rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0 fs-6 fw-bold">
            <i class="bi bi-people text-primary me-1"></i> Registered Customers Directory (<?= count($customers) ?>)
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($customers)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Shopper</th>
                            <th>Contact Information</th>
                            <th>Pre-Orders Placed</th>
                            <th>Total Farm Spend</th>
                            <th>Reviews</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): 
                            $initial = strtoupper(mb_substr($c['name'], 0, 1));
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-info-subtle text-info border d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 0.9rem; flex-shrink: 0;">
                                            <?= e($initial) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= e($c['name']) ?></div>
                                            <small class="text-muted">Joined <?= format_date($c['created_at']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= e($c['email']) ?></div>
                                    <small class="text-muted"><?= e($c['phone'] ?: '—') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="bi bi-bag-check me-1 text-primary"></i><?= $c['orders_count'] ?> pre-orders
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= format_currency($c['total_spent']) ?></span>
                                </td>
                                <td>
                                    <div class="small text-muted"><i class="bi bi-star me-1 text-warning"></i><?= $c['reviews_count'] ?> review(s)</div>
                                </td>
                                <td>
                                    <?= get_status_badge($c['status']) ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- View Customer Details Modal Trigger -->
                                        <button type="button" class="btn btn-light btn-sm border view-customer-btn rounded-3" title="View Customer Details"
                                                data-id="<?= $c['user_id'] ?>"
                                                data-name="<?= e($c['name']) ?>"
                                                data-email="<?= e($c['email']) ?>"
                                                data-phone="<?= e($c['phone'] ?: 'Not provided') ?>"
                                                data-orders="<?= (int)$c['orders_count'] ?>"
                                                data-spent="<?= e(format_currency($c['total_spent'])) ?>"
                                                data-reviews="<?= (int)$c['reviews_count'] ?>"
                                                data-status="<?= e($c['status']) ?>"
                                                data-created="<?= format_date($c['created_at']) ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if ($c['status'] === 'active'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-customers.php" class="d-inline" onsubmit="return confirm('Suspend <?= e($c['name']) ?>\'s customer account?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="customer_id" value="<?= $c['user_id'] ?>">
                                                <button type="submit" name="action" value="suspend" class="btn btn-outline-danger btn-sm rounded-3" title="Suspend Account">
                                                    <i class="bi bi-pause-circle"></i>
                                                </button>
                                            </form>
                                        <?php elseif ($c['status'] === 'suspended'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-customers.php" class="d-inline" onsubmit="return confirm('Reactivate <?= e($c['name']) ?>\'s account?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="customer_id" value="<?= $c['user_id'] ?>">
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
                <h6 class="fw-bold">No customers found</h6>
                <p class="small text-muted mb-0">Try changing your search terms or filter.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Customer Details Modal Dialog -->
<div class="modal fade" id="customerDetailModal" tabindex="-1" aria-labelledby="customerDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span id="cStatusBadge" class="badge bg-success">Active</span>
                    <span id="cCreated" class="text-muted small">Member since: —</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center fs-3 fw-bold" style="width: 52px; height: 52px;">
                        <i class="bi bi-person"></i>
                    </div>
                    <div>
                        <h4 id="cName" class="h5 fw-bold mb-1 text-dark">Customer Name</h4>
                        <div class="text-muted small">Verified Community Shopper</div>
                    </div>
                </div>

                <div class="row g-2 mb-3 text-center">
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 border">
                            <span class="text-muted small d-block" style="font-size: 0.7rem;">Pre-Orders</span>
                            <strong id="cOrders" class="fs-6 text-dark">0</strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 border">
                            <span class="text-muted small d-block" style="font-size: 0.7rem;">Total Spend</span>
                            <strong id="cSpent" class="fs-6 text-primary">PKR 0.00</strong>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-light rounded-3 border">
                            <span class="text-muted small d-block" style="font-size: 0.7rem;">Reviews</span>
                            <strong id="cReviews" class="fs-6 text-warning">0</strong>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="small text-muted fw-semibold mb-1">Email Address</label>
                        <div id="cEmail" class="p-2 bg-light rounded-3 border small fw-bold">—</div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted fw-semibold mb-1">Phone Number</label>
                        <div id="cPhone" class="p-2 bg-light rounded-3 border small fw-bold">—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-3 px-4 bg-light d-flex justify-content-between">
                <div class="small text-muted">User ID: <code id="cId">#0</code></div>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const custModalEl = document.getElementById('customerDetailModal');
    if (custModalEl) {
        const custModal = new bootstrap.Modal(custModalEl);
        document.querySelectorAll('.view-customer-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const email = this.getAttribute('data-email');
                const phone = this.getAttribute('data-phone');
                const orders = this.getAttribute('data-orders');
                const spent = this.getAttribute('data-spent');
                const reviews = this.getAttribute('data-reviews');
                const status = this.getAttribute('data-status');
                const created = this.getAttribute('data-created');

                document.getElementById('cId').textContent = '#' + id;
                document.getElementById('cName').textContent = name;
                document.getElementById('cEmail').textContent = email;
                document.getElementById('cPhone').textContent = phone;
                document.getElementById('cOrders').textContent = orders;
                document.getElementById('cSpent').textContent = spent;
                document.getElementById('cReviews').textContent = reviews;
                document.getElementById('cCreated').textContent = 'Member since: ' + created;

                const badge = document.getElementById('cStatusBadge');
                if (status === 'active') {
                    badge.className = 'badge bg-success';
                    badge.textContent = 'Active Account';
                } else {
                    badge.className = 'badge bg-danger';
                    badge.textContent = 'Suspended';
                }

                custModal.show();
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
