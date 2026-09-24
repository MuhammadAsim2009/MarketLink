<?php
/**
 * MarketLink - Farmer Customer Reviews & Responses
 */

$required_role = 'farmer';
require_once __DIR__ . '/../includes/auth-check.php';

$farmer_id = get_logged_in_user_id();

// Handle Farmer Response to Review via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session submission. Please try again.');
        redirect(BASE_URL . 'farmer/reviews.php');
    }

    $review_id = (int)($_POST['review_id'] ?? 0);
    $response_text = sanitize_input($_POST['farmer_response'] ?? '');

    if ($review_id > 0 && !empty($response_text)) {
        try {
            // Verify review belongs to this farmer or farmer's product
            $chk = $pdo->prepare("SELECT r.review_id, r.customer_id, fp.stall_name, u.name as farmer_name 
                                  FROM reviews r 
                                  LEFT JOIN products p ON r.product_id = p.product_id 
                                  JOIN users u ON u.user_id = :fid 
                                  LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                                  WHERE r.review_id = :rid AND (r.farmer_id = :fid2 OR p.farmer_id = :fid3) LIMIT 1");
            $chk->execute([':fid' => $farmer_id, ':rid' => $review_id, ':fid2' => $farmer_id, ':fid3' => $farmer_id]);
            $rev = $chk->fetch();

            if ($rev) {
                $upd = $pdo->prepare("UPDATE reviews SET farmer_response = :resp WHERE review_id = :rid");
                $upd->execute([':resp' => $response_text, ':rid' => $review_id]);

                $stall_name = $rev['stall_name'] ?: $rev['farmer_name'];
                create_notification($pdo, $rev['customer_id'], "{$stall_name} has replied to your review: \"" . mb_strimwidth($response_text, 0, 50, '...') . "\"");

                set_flash('success', 'Your response to the customer has been posted.');
            } else {
                set_flash('danger', 'Unauthorized or review not found.');
            }
        } catch (PDOException $e) {
            error_log("Farmer review response error: " . $e->getMessage());
            set_flash('danger', 'Database error.');
        }
    }
    redirect(BASE_URL . 'farmer/reviews.php');
}

// Fetch all reviews for this farmer
$reviews = [];
$avg_rating = 0;
$total_reviews = 0;

try {
    $stmt = $pdo->prepare("SELECT r.*, u.name as customer_name, p.name as product_name 
                          FROM reviews r 
                          JOIN users u ON r.customer_id = u.user_id 
                          LEFT JOIN products p ON r.product_id = p.product_id 
                          WHERE r.farmer_id = :fid OR p.farmer_id = :fid2 
                          ORDER BY r.created_at DESC");
    $stmt->execute([':fid' => $farmer_id, ':fid2' => $farmer_id]);
    $reviews = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch reviews error: " . $e->getMessage());
}

// Star breakdown computation
$star_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$total_reviews = count($reviews);
if ($total_reviews > 0) {
    $sum = 0;
    foreach ($reviews as $r) {
        $sum += (int)$r['rating'];
        $star_counts[(int)$r['rating']] = ($star_counts[(int)$r['rating']] ?? 0) + 1;
    }
    $avg_rating = round($sum / $total_reviews, 1);
}

$active_nav = 'reviews';
$page_title = 'Customer Reviews & Ratings';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Customer Reviews & Ratings</h1>
        <p class="text-muted small mb-0">Monitor farm produce feedback, view verified customer ratings, and reply to stall reviews</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $farmer_id ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="bi bi-eye me-1"></i> Public Stall Reviews
        </a>
    </div>
</div>

<!-- SaaS Reputation & Star Distribution Card -->
<div class="card shadow-xs border-0 rounded-4 mb-4 bg-white overflow-hidden">
    <div class="card-body p-4">
        <div class="row align-items-center g-4">
            <!-- Overall Score Column -->
            <div class="col-lg-3 text-center border-end">
                <div class="display-4 fw-bold text-dark mb-1"><?= $avg_rating > 0 ? $avg_rating : '—' ?></div>
                <div class="text-warning mb-2 fs-5">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi <?= $i <= round($avg_rating) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                    <?php endfor; ?>
                </div>
                <div class="small fw-semibold text-muted">
                    Based on <?= $total_reviews ?> <?= $total_reviews === 1 ? 'verified review' : 'verified reviews' ?>
                </div>
            </div>

            <!-- Star Distribution Bars -->
            <div class="col-lg-5 border-end">
                <div class="d-flex flex-column gap-2">
                    <?php for ($s = 5; $s >= 1; $s--): 
                        $pct = $total_reviews > 0 ? round(($star_counts[$s] / $total_reviews) * 100) : 0;
                    ?>
                        <div class="d-flex align-items-center gap-2 small">
                            <span class="text-muted" style="width: 35px;"><?= $s ?> <i class="bi bi-star-fill text-warning" style="font-size: 0.75rem;"></i></span>
                            <div class="progress flex-grow-1" style="height: 6px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="text-muted text-end" style="width: 30px; font-size: 0.75rem;"><?= $star_counts[$s] ?></span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Trust Banner -->
            <div class="col-lg-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="d-inline-flex p-3 bg-success-subtle text-success rounded-circle flex-shrink-0">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Building Producer Trust</h6>
                        <p class="small text-muted mb-0" style="line-height: 1.45;">
                            Only customers who complete a pre-order pickup can leave reviews. Replying to reviews demonstrates authentic care and increases weekend pre-orders.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reviews Feed Card -->
<div class="card shadow-xs border-0 rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
        <h5 class="card-title mb-0 fs-6 fw-bold">Customer Feedback Feed (<?= $total_reviews ?>)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($reviews)): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($reviews as $rev): 
                    $c_initial = strtoupper(mb_substr($rev['customer_name'], 0, 1));
                ?>
                    <div class="list-group-item p-4">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex align-items-center gap-3">
                                <div class="user-avatar" style="width: 40px; height: 40px; font-size: 0.95rem;">
                                    <?= e($c_initial) ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                        <?= e($rev['customer_name']) ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 0.65rem;">
                                            <i class="bi bi-patch-check-fill me-1"></i> Verified Pickup
                                        </span>
                                    </div>
                                    <div class="text-warning small mb-0">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted ms-2" style="font-size: 0.75rem;"><?= format_date($rev['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($rev['product_name'])): ?>
                                <span class="badge bg-light text-secondary border px-2 py-1" style="font-size: 0.75rem;">
                                    <i class="bi bi-tag me-1"></i> <?= e($rev['product_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <p class="mb-3 text-secondary ps-sm-5 ms-sm-2" style="line-height: 1.5;"><?= nl2br(e($rev['comment'])) ?></p>

                        <!-- Farmer Response Box -->
                        <div class="ps-sm-5 ms-sm-2">
                            <?php if (!empty($rev['farmer_response'])): ?>
                                <div class="p-3 rounded-3 bg-light border-start border-success border-4 mb-2 small">
                                    <div class="fw-bold text-success mb-1 d-flex align-items-center gap-1">
                                        <i class="bi bi-reply-fill"></i> Your Public Response:
                                    </div>
                                    <p class="mb-0 text-dark"><?= nl2br(e($rev['farmer_response'])) ?></p>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-outline-primary btn-sm py-1 px-3 rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#replyForm_<?= $rev['review_id'] ?>" aria-expanded="false">
                                    <i class="bi bi-reply me-1"></i> Reply to Customer
                                </button>
                                <div class="collapse mt-3" id="replyForm_<?= $rev['review_id'] ?>">
                                    <form method="POST" action="<?= BASE_URL ?>farmer/reviews.php" class="p-3 bg-light rounded-3 border">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="review_id" value="<?= $rev['review_id'] ?>">
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold" for="resp_<?= $rev['review_id'] ?>">Public Stall Response:</label>
                                            <textarea class="form-control form-control-sm" id="resp_<?= $rev['review_id'] ?>" name="farmer_response" rows="2" placeholder="Thank the customer for their review and support..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm rounded-3">
                                            <i class="bi bi-send me-1"></i> Post Public Reply
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <div class="d-inline-flex p-3 bg-light rounded-circle mb-3">
                    <i class="bi bi-chat-square-heart fs-2 text-muted"></i>
                </div>
                <h6 class="fw-bold mb-1">No reviews received yet</h6>
                <p class="small text-muted mb-0">Once customers pick up their orders and leave feedback, reviews will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
