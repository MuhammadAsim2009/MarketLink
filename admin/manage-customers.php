<?php
/**
 * MarketLink - Admin Customer Management
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Manage Customers';

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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-person-gear text-primary me-2"></i>Manage Shopper Accounts</h1>
            <p class="text-muted small mb-0">View registered customers, monitor pre-order activities, and manage account statuses</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Admin Panel
            </a>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form action="<?= BASE_URL ?>admin/manage-customers.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search customer by name, email, phone..." value="<?= e($search_q) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Customer Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended Only</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                    <?php if (!empty($search_q) || $status_filter !== 'all'): ?>
                        <a href="<?= BASE_URL ?>admin/manage-customers.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fs-6">Registered Customers (<?= count($customers) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($customers)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Customer Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Pre-Orders</th>
                                <th>Completed Spend</th>
                                <th>Registered</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr class="<?= $c['status'] === 'suspended' ? 'table-light opacity-75' : '' ?>">
                                    <td class="fw-bold"><?= e($c['name']) ?></td>
                                    <td><?= e($c['email']) ?></td>
                                    <td><?= e($c['phone'] ?: '—') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= $c['orders_count'] ?> order(s)</span></td>
                                    <td class="fw-semibold text-primary"><?= format_currency($c['total_spent']) ?></td>
                                    <td class="small text-muted"><?= format_date($c['created_at']) ?></td>
                                    <td><?= get_status_badge($c['status']) ?></td>
                                    <td class="text-end">
                                        <?php if ($c['status'] === 'active'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-customers.php" class="d-inline" onsubmit="return confirm('Suspend customer account for <?= e($c['name']) ?>?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="customer_id" value="<?= $c['user_id'] ?>">
                                                <button type="submit" name="action" value="suspend" class="btn btn-outline-danger btn-sm py-1 px-2" title="Suspend Account">
                                                    <i class="bi bi-slash-circle me-1"></i> Suspend
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-customers.php" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="customer_id" value="<?= $c['user_id'] ?>">
                                                <button type="submit" name="action" value="activate" class="btn btn-outline-success btn-sm py-1 px-2" title="Reactivate Account">
                                                    <i class="bi bi-check-circle me-1"></i> Reactivate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1 text-secondary-subtle d-block mb-2"></i>
                    <h6>No customer accounts found</h6>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
