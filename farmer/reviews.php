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

    $total_reviews = count($reviews);
    if ($total_reviews > 0) {
        $sum = array_sum(array_column($reviews, 'rating'));
        $avg_rating = round($sum / $total_reviews, 1);
    }
} catch (PDOException $e) {
    error_log("Fetch reviews error: " . $e->getMessage());
}

$page_title = 'Customer Reviews';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-star-fill text-warning me-2"></i>Customer Reviews & Ratings</h1>
            <p class="text-muted small mb-0">Read feedback from market shoppers and reply to customer reviews</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>farmer/dashboard.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Rating Summary Box -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-md-3 text-center border-end">
                    <div class="display-5 fw-bold text-primary"><?= $avg_rating > 0 ? $avg_rating : '—' ?></div>
                    <div class="text-warning mb-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi <?= $i <= round($avg_rating) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="small text-muted"><?= $total_reviews ?> total review(s)</div>
                </div>
                <div class="col-md-9">
                    <h6 class="fw-bold mb-1">Building Trust With Local Shoppers</h6>
                    <p class="small text-muted mb-0">
                        Customers who pre-order your harvest can leave a rating and review once their order is completed. Replying to feedback demonstrates good customer care and attracts repeat weekend buyers.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Reviews List -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 fs-6">All Reviews (<?= $total_reviews ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($reviews)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($reviews as $rev): ?>
                        <div class="list-group-item p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold"><?= e($rev['customer_name']) ?></div>
                                    <div class="text-warning small mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted ms-2"><?= format_date($rev['created_at']) ?></span>
                                    </div>
                                    <?php if (!empty($rev['product_name'])): ?>
                                        <span class="badge bg-light text-dark border small mb-2"><i class="bi bi-tag me-1"></i> Product: <?= e($rev['product_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="mb-3 text-secondary"><?= nl2br(e($rev['comment'])) ?></p>

                            <!-- Farmer Response Box -->
                            <?php if (!empty($rev['farmer_response'])): ?>
                                <div class="p-3 rounded bg-light border-start border-success border-3 mb-2 small">
                                    <div class="fw-bold text-success mb-1"><i class="bi bi-reply-fill me-1"></i> Your Stall Response:</div>
                                    <p class="mb-0 text-dark"><?= nl2br(e($rev['farmer_response'])) ?></p>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-outline-primary btn-sm py-1 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#replyForm_<?= $rev['review_id'] ?>" aria-expanded="false">
                                    <i class="bi bi-reply me-1"></i> Write a Reply
                                </button>
                                <div class="collapse mt-3" id="replyForm_<?= $rev['review_id'] ?>">
                                    <form method="POST" action="<?= BASE_URL ?>farmer/reviews.php" class="p-3 bg-light rounded border">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="review_id" value="<?= $rev['review_id'] ?>">
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold" for="resp_<?= $rev['review_id'] ?>">Reply as Stall Owner:</label>
                                            <textarea class="form-control form-control-sm" id="resp_<?= $rev['review_id'] ?>" name="farmer_response" rows="2" placeholder="Thank the customer for their review..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">Post Reply</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-chat-square-heart fs-1 text-secondary-subtle d-block mb-2"></i>
                    <h6>No reviews received yet</h6>
                    <p class="small text-muted mb-0">Once customers pick up their orders and leave feedback, reviews will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
