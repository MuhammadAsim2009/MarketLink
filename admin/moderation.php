<?php
/**
 * MarketLink - Admin Content Moderation & Broadcast Center
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Content Moderation & Announcements';

// Handle Moderation & Broadcast Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session token.');
        redirect(BASE_URL . 'admin/moderation.php');
    }

    $action = sanitize_input($_POST['action'] ?? '');

    // 1. DELETE FLAGGED REVIEW
    if ($action === 'delete_review') {
        $rid = (int)($_POST['review_id'] ?? 0);
        if ($rid > 0) {
            try {
                $del = $pdo->prepare("DELETE FROM reviews WHERE review_id = :rid");
                $del->execute([':rid' => $rid]);
                set_flash('success', 'Review has been removed from platform.');
            } catch (PDOException $e) {
                set_flash('danger', 'Failed to delete review.');
            }
        }
        redirect(BASE_URL . 'admin/moderation.php');
    }

    // 2. TOGGLE PRODUCT VISIBILITY
    if ($action === 'toggle_product') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $sold_out = (int)($_POST['is_sold_out'] ?? 0);
        $new_val = $sold_out ? 0 : 1;
        if ($pid > 0) {
            try {
                $upd = $pdo->prepare("UPDATE products SET is_sold_out = :val WHERE product_id = :pid");
                $upd->execute([':val' => $new_val, ':pid' => $pid]);
                set_flash('info', 'Product status updated.');
            } catch (PDOException $e) {
                set_flash('danger', 'Failed to update product.');
            }
        }
        redirect(BASE_URL . 'admin/moderation.php');
    }

    // 3. BROADCAST ANNOUNCEMENT
    if ($action === 'broadcast') {
        $target_audience = sanitize_input($_POST['target_audience'] ?? 'all');
        $message = sanitize_input($_POST['broadcast_message'] ?? '');

        if (!empty($message)) {
            try {
                $sql = "SELECT user_id FROM users WHERE status = 'active'";
                if ($target_audience === 'farmer') {
                    $sql .= " AND role = 'farmer'";
                } elseif ($target_audience === 'customer') {
                    $sql .= " AND role = 'customer'";
                }

                $users = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
                $ins = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (:uid, :msg, 0, NOW())");

                foreach ($users as $uid) {
                    $ins->execute([':uid' => $uid, ':msg' => "[Platform Notice] " . $message]);
                }

                set_flash('success', 'Broadcast notice delivered to ' . count($users) . ' active user account(s).');
            } catch (PDOException $e) {
                error_log("Broadcast error: " . $e->getMessage());
                set_flash('danger', 'Failed to send broadcast.');
            }
        }
        redirect(BASE_URL . 'admin/moderation.php');
    }
}

// Fetch recent reviews for moderation
$recent_reviews = [];
try {
    $r_stmt = $pdo->query("SELECT r.*, u.name as customer_name, f.name as farmer_name, fp.stall_name, p.name as product_name 
                          FROM reviews r 
                          JOIN users u ON r.customer_id = u.user_id 
                          LEFT JOIN users f ON r.farmer_id = f.user_id 
                          LEFT JOIN farmer_profiles fp ON f.user_id = fp.farmer_id 
                          LEFT JOIN products p ON r.product_id = p.product_id 
                          ORDER BY r.created_at DESC LIMIT 15");
    $recent_reviews = $r_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Moderation reviews load error: " . $e->getMessage());
}

// Fetch all products for oversight
$all_products = [];
try {
    $p_stmt = $pdo->query("SELECT p.*, u.name as farmer_name, fp.stall_name 
                          FROM products p 
                          JOIN users u ON p.farmer_id = u.user_id 
                          LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                          ORDER BY p.created_at DESC LIMIT 20");
    $all_products = $p_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Moderation products load error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-shield-shaded text-primary me-2"></i>Content Moderation & Broadcast</h1>
            <p class="text-muted small mb-0">Oversee customer reviews, inspect produce listings, and publish platform announcements</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Admin Panel
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Platform Broadcast Card -->
        <div class="col-12">
            <div class="card shadow-sm border-0 bg-primary-subtle text-primary-emphasis">
                <div class="card-body p-4">
                    <h5 class="card-title fs-6 fw-bold mb-2">
                        <i class="bi bi-megaphone-fill text-primary me-2"></i>Publish Platform-Wide Announcement
                    </h5>
                    <p class="small mb-3">Broadcast an official notification to all registered customers and farmers (e.g. holiday market timings or weather updates):</p>

                    <form action="<?= BASE_URL ?>admin/moderation.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="broadcast">

                        <div class="row g-3 align-items-center">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold" for="target_audience">Target Audience</label>
                                <select class="form-select form-select-sm" id="target_audience" name="target_audience">
                                    <option value="all">Everyone (All Users)</option>
                                    <option value="farmer">Farmers & Stalls Only</option>
                                    <option value="customer">Shoppers / Customers Only</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small fw-semibold" for="broadcast_message">Announcement Message</label>
                                <input type="text" class="form-control form-control-sm" id="broadcast_message" name="broadcast_message" placeholder="e.g. Central Market will open early this Saturday at 06:30 AM!" required>
                            </div>
                            <div class="col-md-2 pt-md-4">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-send me-1"></i> Send Notice
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Moderation Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6"><i class="bi bi-star text-warning me-2"></i>Recent Customer Reviews Oversight</h5>
            <span class="badge bg-light text-dark border"><?= count($recent_reviews) ?> recent</span>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($recent_reviews)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Customer</th>
                                <th>Stall / Product</th>
                                <th>Rating</th>
                                <th>Comment</th>
                                <th>Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reviews as $r): ?>
                                <tr>
                                    <td class="fw-bold"><?= e($r['customer_name']) ?></td>
                                    <td>
                                        <div class="small fw-semibold"><?= e($r['stall_name'] ?: $r['farmer_name']) ?></div>
                                        <?php if (!empty($r['product_name'])): ?>
                                            <small class="text-muted"><i class="bi bi-tag me-1"></i><?= e($r['product_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-warning small">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi <?= $i <= $r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td class="small text-secondary" style="max-width: 320px;">
                                        <?= e($r['comment']) ?>
                                        <?php if (!empty($r['farmer_response'])): ?>
                                            <div class="p-1 bg-light rounded text-success small mt-1">
                                                <strong>Reply:</strong> <?= e($r['farmer_response']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= format_date($r['created_at']) ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= BASE_URL ?>admin/moderation.php" class="d-inline" onsubmit="return confirm('Remove this review from the public platform?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_review">
                                            <input type="hidden" name="review_id" value="<?= $r['review_id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" title="Delete Review">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted small">No reviews recorded yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Catalogue Oversight -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fs-6"><i class="bi bi-basket text-primary me-2"></i>Produce Catalogue Oversight</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($all_products)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Produce</th>
                                <th>Stall</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th class="text-end">Moderation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_products as $p): ?>
                                <tr>
                                    <td class="fw-bold"><?= e($p['name']) ?></td>
                                    <td class="small text-muted"><?= e($p['stall_name'] ?: $p['farmer_name']) ?></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary"><?= e($p['category']) ?></span></td>
                                    <td class="fw-semibold text-primary"><?= format_currency($p['price']) ?> / <?= e($p['unit']) ?></td>
                                    <td><?= $p['quantity_available'] ?> <?= e($p['unit']) ?></td>
                                    <td>
                                        <?php if ($p['is_sold_out']): ?>
                                            <span class="badge bg-danger">Sold Out / Hidden</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Live</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="<?= BASE_URL ?>admin/moderation.php" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_product">
                                            <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                            <input type="hidden" name="is_sold_out" value="<?= $p['is_sold_out'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $p['is_sold_out'] ? 'btn-outline-success' : 'btn-outline-warning' ?>" title="Toggle Visibility">
                                                <?= $p['is_sold_out'] ? '<i class="bi bi-eye"></i> Show' : '<i class="bi bi-eye-slash"></i> Hide' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
