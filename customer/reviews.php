<?php
/**
 * MarketLink - Customer Reviews & Ratings
 */

$required_role = 'customer';
require_once __DIR__ . '/../includes/auth-check.php';

$customer_id = get_logged_in_user_id();
$target_farmer_id = (int)($_GET['farmer_id'] ?? 0);
$target_product_id = (int)($_GET['product_id'] ?? 0);
$target_farmer = null;
$target_product = null;

// Handle Review Submission via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session submission.');
        redirect(BASE_URL . 'customer/reviews.php');
    }

    $fid = !empty($_POST['farmer_id']) ? (int)$_POST['farmer_id'] : null;
    $pid = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $rating = (int)($_POST['rating'] ?? 5);
    $comment = sanitize_input($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $rating = 5;
    }

    if (empty($fid)) {
        set_flash('danger', 'Please select a farmer stall to review.');
    } elseif (empty($comment)) {
        set_flash('danger', 'Please write a review comment.');
    } else {
        try {
            // Verify that the customer has actually completed an order with this farmer
            $eligibility_chk = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = :cid AND farmer_id = :fid AND status = 'completed'");
            $eligibility_chk->execute([':cid' => $customer_id, ':fid' => $fid]);
            $has_completed = (int)$eligibility_chk->fetchColumn();

            if ($has_completed === 0) {
                set_flash('warning', 'You can only leave reviews for farmer stalls where you have a completed order pickup.');
                redirect(BASE_URL . 'customer/reviews.php');
            }

            $stmt = $pdo->prepare("INSERT INTO reviews (customer_id, farmer_id, product_id, rating, comment, created_at) 
                                  VALUES (:cid, :fid, :pid, :rating, :comment, NOW())");
            $stmt->execute([
                ':cid' => $customer_id,
                ':fid' => $fid,
                ':pid' => $pid,
                ':rating' => $rating,
                ':comment' => $comment
            ]);

            // Notify Farmer of the new review
            $cust_name = get_logged_in_user_name();
            if ($fid) {
                create_notification($pdo, $fid, "{$cust_name} left a {$rating}-star review for your stall: \"" . mb_strimwidth($comment, 0, 40, '...') . "\"");
            }

            set_flash('success', 'Thank you! Your review has been submitted.');
            redirect(BASE_URL . 'customer/reviews.php');

        } catch (PDOException $e) {
            error_log("Review submit error: " . $e->getMessage());
            set_flash('danger', 'Database error while submitting review.');
        }
    }
}

// Fetch list of farmers customer has completed orders with (eligible for reviews)
$eligible_farmers = [];
try {
    $ef_stmt = $pdo->prepare("SELECT u.user_id, u.name, fp.stall_name, COUNT(o.order_id) as completed_orders_count
                              FROM orders o 
                              JOIN users u ON o.farmer_id = u.user_id 
                              LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                              WHERE o.customer_id = :cid AND o.status = 'completed'
                              GROUP BY u.user_id, u.name, fp.stall_name
                              ORDER BY COALESCE(NULLIF(fp.stall_name, ''), u.name) ASC");
    $ef_stmt->execute([':cid' => $customer_id]);
    $eligible_farmers = $ef_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Eligible farmers fetch error: " . $e->getMessage());
}

// Check if target farmer from URL is eligible
$target_eligible = false;
if ($target_farmer_id > 0) {
    foreach ($eligible_farmers as $ef) {
        if ((int)$ef['user_id'] === $target_farmer_id) {
            $target_eligible = true;
            break;
        }
    }
}

// Fetch customer's past submitted reviews
$my_reviews = [];
try {
    $r_stmt = $pdo->prepare("SELECT r.*, u.name as farmer_name, fp.stall_name, p.name as product_name 
                             FROM reviews r 
                             LEFT JOIN users u ON r.farmer_id = u.user_id 
                             LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                             LEFT JOIN products p ON r.product_id = p.product_id 
                             WHERE r.customer_id = :cid 
                             ORDER BY r.created_at DESC");
    $r_stmt->execute([':cid' => $customer_id]);
    $my_reviews = $r_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch customer reviews error: " . $e->getMessage());
}

$page_title = 'My Reviews & Ratings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-star-fill text-warning me-2"></i>My Reviews & Ratings</h1>
            <p class="text-muted small mb-0">Share your verified feedback on farm produce freshness and stall service</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>customer/orders.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> My Pre-Orders
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Leave Review Form Column -->
        <div class="col-lg-5">
            <?php if (empty($eligible_farmers)): ?>
                <!-- Notice when user has not completed any orders yet -->
                <div class="card shadow-sm border-0 sticky-top" style="top: 85px;">
                    <div class="card-body p-4 text-center">
                        <div class="mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                                <i class="bi bi-patch-check-fill fs-2 text-warning"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-2">Verified Reviews Only</h5>
                        <p class="text-muted small mb-4">
                            You can write a review once you have completed and picked up a pre-order from a local farmer stall. Please complete an order first to unlock ratings and reviews.
                        </p>
                        <div class="d-grid gap-2">
                            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm py-2">
                                <i class="bi bi-shop me-1"></i> Browse Fresh Produce
                            </a>
                            <a href="<?= BASE_URL ?>customer/orders.php" class="btn btn-outline-secondary btn-sm py-2">
                                <i class="bi bi-receipt me-1"></i> View My Pre-Orders
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Write Review Form -->
                <div class="card shadow-sm border-0 sticky-top" style="top: 85px;">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6"><i class="bi bi-pencil-square text-primary me-2"></i>Write a Verified Review</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="<?= BASE_URL ?>customer/reviews.php" method="POST" novalidate>
                            <?= csrf_field() ?>

                            <!-- Farmer Stall Selection -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold" for="farmer_id">Select Farmer Stall <span class="text-danger">*</span></label>
                                <select class="form-select" id="farmer_id" name="farmer_id" required>
                                    <?php if (count($eligible_farmers) > 1): ?>
                                        <option value="" <?= (!$target_eligible) ? 'selected' : '' ?> disabled>-- Choose a Farm Stall --</option>
                                    <?php endif; ?>
                                    <?php foreach ($eligible_farmers as $ef): ?>
                                        <?php 
                                            $isSelected = ($target_farmer_id === (int)$ef['user_id']) || (count($eligible_farmers) === 1);
                                            $displayName = !empty($ef['stall_name']) ? $ef['stall_name'] : $ef['name'];
                                            if (!empty($ef['stall_name']) && $ef['stall_name'] !== $ef['name']) {
                                                $displayName .= ' (' . $ef['name'] . ')';
                                            }
                                        ?>
                                        <option value="<?= (int)$ef['user_id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                                            <?= e($displayName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Only stalls where you have completed orders are listed.
                                </div>
                            </div>

                            <!-- Rating Selector (1 to 5 Stars) -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold" for="rating">Rating <span class="text-danger">*</span></label>
                                <select class="form-select" id="rating" name="rating">
                                    <option value="5">⭐⭐⭐⭐⭐ 5 - Outstanding Quality</option>
                                    <option value="4">⭐⭐⭐⭐ 4 - Very Fresh & Good</option>
                                    <option value="3">⭐⭐⭐ 3 - Average Quality</option>
                                    <option value="2">⭐⭐ 2 - Below Expectations</option>
                                    <option value="1">⭐ 1 - Poor Quality</option>
                                </select>
                            </div>

                            <!-- Comment -->
                            <div class="mb-4">
                                <label class="form-label small fw-semibold" for="comment">Your Feedback & Experience <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="How was the freshness, taste, and stall pickup experience?" required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-send me-1"></i> Submit Review
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Submitted Reviews Feed Column -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fs-6">My Submitted Reviews (<?= count($my_reviews) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($my_reviews)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($my_reviews as $rev): ?>
                                <div class="list-group-item p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="fw-bold mb-0 text-primary"><?= e($rev['stall_name'] ?: $rev['farmer_name']) ?></h6>
                                        <span class="small text-muted"><?= format_date($rev['created_at']) ?></span>
                                    </div>
                                    <div class="text-warning small mb-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted ms-2">(<?= $rev['rating'] ?>/5)</span>
                                    </div>
                                    <p class="small text-secondary mb-2"><?= nl2br(e($rev['comment'])) ?></p>

                                    <!-- Farmer Response -->
                                    <?php if (!empty($rev['farmer_response'])): ?>
                                        <div class="p-3 bg-light rounded border-start border-success border-3 small">
                                            <div class="fw-bold text-success mb-1"><i class="bi bi-reply-fill me-1"></i> Farmer Response:</div>
                                            <p class="mb-0 text-dark"><?= nl2br(e($rev['farmer_response'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-chat-heart fs-1 text-secondary-subtle d-block mb-2"></i>
                            <h6>No reviews written yet</h6>
                            <p class="small text-muted mb-0">After completing a weekend pickup, submit feedback here to help other local shoppers.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
