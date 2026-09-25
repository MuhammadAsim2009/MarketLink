<?php
/**
 * MarketLink - Admin Content Moderation & Broadcast Center
 * SaaS Redesign with Sidebar Layout
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Content Moderation & Announcements';
$active_nav = 'moderation';

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
                          ORDER BY r.created_at DESC LIMIT 20");
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
                          ORDER BY p.created_at DESC LIMIT 30");
    $all_products = $p_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Moderation products load error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header & Action Controls -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-2 border-bottom">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge badge-primary px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i> Governance</span>
            <span class="badge badge-neutral px-2 py-1">Platform Safety & Quality</span>
        </div>
        <h1 class="h3 fw-bold mb-1">Content Moderation & Broadcast</h1>
        <p class="text-muted small mb-0">Oversee customer ratings, inspect farm listings, and publish urgent platform-wide notifications</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </div>
</div>

<!-- Broadcast Announcement Card -->
<div class="card border shadow-xs mb-4" style="background: linear-gradient(135deg, rgba(46,125,79,0.04) 0%, rgba(43,114,186,0.04) 100%);">
    <div class="card-header bg-transparent py-3 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0 fs-6 fw-bold">
            <i class="bi bi-megaphone-fill text-primary me-2"></i>Publish Platform Announcement
        </h5>
        <span class="badge badge-warning px-2 py-1"><i class="bi bi-broadcast me-1"></i> Instant In-App Dispatch</span>
    </div>
    <div class="card-body p-4">
        <form action="<?= BASE_URL ?>admin/moderation.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="broadcast">

            <div class="row g-3">
                <div class="col-lg-3 col-md-4">
                    <label class="form-label small fw-semibold" for="target_audience">Target Audience</label>
                    <select class="form-select form-select-sm" id="target_audience" name="target_audience">
                        <option value="all">Everyone (All Registered Users)</option>
                        <option value="farmer">Farmers & Stalls Only</option>
                        <option value="customer">Shoppers / Consumers Only</option>
                    </select>
                </div>
                <div class="col-lg-7 col-md-6">
                    <label class="form-label small fw-semibold" for="broadcast_message">Announcement Bulletin Message</label>
                    <input type="text" class="form-control form-control-sm" id="broadcast_message" name="broadcast_message" placeholder="e.g. Model Town Market will open at 06:30 AM this Sunday with fresh harvest arrival." required>
                </div>
                <div class="col-lg-2 col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-send-fill me-1"></i> Send Notice
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Review Moderation Section -->
<div class="card border shadow-xs mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="card-title mb-0 fs-6 fw-bold">
                <i class="bi bi-star-fill text-warning me-2"></i>Customer Reviews & Ratings Oversight
            </h5>
            <span class="badge badge-neutral"><?= count($recent_reviews) ?> recent</span>
        </div>
        <div>
            <input type="text" id="reviewSearch" class="form-control form-control-sm" placeholder="Filter reviews..." style="max-width: 200px;">
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($recent_reviews)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reviewsTable">
                    <thead class="table-light small">
                        <tr>
                            <th>Shopper</th>
                            <th>Stall / Product Target</th>
                            <th>Rating</th>
                            <th>Comment & Farmer Reply</th>
                            <th>Date</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_reviews as $r): ?>
                            <tr class="review-row">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                            <?= strtoupper(mb_substr($r['customer_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark small"><?= e($r['customer_name']) ?></div>
                                            <small class="text-muted">ID #<?= $r['customer_id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><?= e($r['stall_name'] ?: $r['farmer_name']) ?></div>
                                    <?php if (!empty($r['product_name'])): ?>
                                        <small class="text-muted"><i class="bi bi-tag me-1"></i><?= e($r['product_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-warning small text-nowrap">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= $r['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td style="max-width: 340px;">
                                    <div class="small text-secondary mb-1">
                                        "<?= e($r['comment']) ?>"
                                    </div>
                                    <?php if (!empty($r['farmer_response'])): ?>
                                        <div class="p-2 bg-light rounded text-success small border">
                                            <strong><i class="bi bi-reply-fill me-1"></i>Farmer:</strong> <?= e($r['farmer_response']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted text-nowrap"><?= format_date($r['created_at']) ?></small>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="<?= BASE_URL ?>admin/moderation.php" class="d-inline" onsubmit="return confirm('Permanently remove this review from the public platform?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_review">
                                        <input type="hidden" name="review_id" value="<?= $r['review_id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" title="Remove Review">
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
            <div class="text-center py-5 text-muted">
                <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: var(--surface-2); color: var(--text-3);">
                    <i class="bi bi-star fs-2"></i>
                </div>
                <h6 class="fw-bold mb-1">No Reviews Posted Yet</h6>
                <p class="small text-muted mb-0">Customer reviews will appear here for quality monitoring and moderation.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Produce Catalogue Oversight -->
<div class="card border shadow-xs">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="card-title mb-0 fs-6 fw-bold">
                <i class="bi bi-basket-fill text-primary me-2"></i>Produce Catalogue Oversight
            </h5>
            <span class="badge badge-neutral"><?= count($all_products) ?> listed</span>
        </div>
        <div>
            <input type="text" id="productSearch" class="form-control form-control-sm" placeholder="Search produce..." style="max-width: 200px;">
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($all_products)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="productsTable">
                    <thead class="table-light small">
                        <tr>
                            <th>Produce Item</th>
                            <th>Stall / Farmer</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock Available</th>
                            <th>Public Status</th>
                            <th class="text-end">Moderation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_products as $p): ?>
                            <tr class="product-row">
                                <td>
                                    <div class="fw-bold text-dark"><?= e($p['name']) ?></div>
                                    <small class="text-muted">SKU #PROD-<?= $p['product_id'] ?></small>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><?= e($p['stall_name'] ?: $p['farmer_name']) ?></div>
                                    <small class="text-muted"><?= e($p['farmer_name']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-neutral"><?= e($p['category']) ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-primary"><?= format_currency($p['price']) ?></span>
                                    <small class="text-muted">/ <?= e($p['unit']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $p['quantity_available'] ?> <?= e($p['unit']) ?></span>
                                </td>
                                <td>
                                    <?php if ($p['is_sold_out']): ?>
                                        <span class="badge badge-danger">Hidden / Sold Out</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><i class="bi bi-check-circle me-1"></i> Live</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="<?= BASE_URL ?>admin/moderation.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_product">
                                        <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                        <input type="hidden" name="is_sold_out" value="<?= $p['is_sold_out'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $p['is_sold_out'] ? 'btn-outline-success' : 'btn-outline-warning' ?> py-1 px-2" title="Toggle Listing Visibility">
                                            <?= $p['is_sold_out'] ? '<i class="bi bi-eye me-1"></i> Unhide' : '<i class="bi bi-eye-slash me-1"></i> Hide' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: var(--surface-2); color: var(--text-3);">
                    <i class="bi bi-basket fs-2"></i>
                </div>
                <h6 class="fw-bold mb-1">No Produce Items in Catalog</h6>
                <p class="small text-muted mb-0">Farmer produce listings will appear here for oversight.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Review filter
    const reviewSearch = document.getElementById('reviewSearch');
    if (reviewSearch) {
        reviewSearch.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('#reviewsTable .review-row').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        });
    }

    // Product filter
    const productSearch = document.getElementById('productSearch');
    if (productSearch) {
        productSearch.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('#productsTable .product-row').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
