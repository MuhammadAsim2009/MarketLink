<?php
/**
 * MarketLink - Product Detail & Add to Pre-Order
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$product_id = (int)($_GET['id'] ?? 0);
if ($product_id <= 0) {
    set_flash('danger', 'Product not found.');
    redirect(BASE_URL . 'customer/browse-products.php');
}

$product = null;
$farmer = null;
$product_reviews = [];
$avg_rating = 5.0;
$is_favorited = false;

try {
    $stmt = $pdo->prepare("SELECT p.*, u.name as farmer_name, u.phone as farmer_phone, 
                          fp.stall_name, fp.address as stall_address, fp.operating_days, 
                          fp.pickup_window_start, fp.pickup_window_end, fp.order_cutoff_hours 
                          FROM products p 
                          JOIN users u ON p.farmer_id = u.user_id 
                          LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                          WHERE p.product_id = :pid AND u.status = 'active' LIMIT 1");
    $stmt->execute([':pid' => $product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        set_flash('danger', 'This product is currently unavailable.');
        redirect(BASE_URL . 'customer/browse-products.php');
    }

    // Product reviews
    $r_stmt = $pdo->prepare("SELECT r.*, u.name as customer_name 
                             FROM reviews r 
                             JOIN users u ON r.customer_id = u.user_id 
                             WHERE r.product_id = :pid 
                             ORDER BY r.created_at DESC");
    $r_stmt->execute([':pid' => $product_id]);
    $product_reviews = $r_stmt->fetchAll();

    if (!empty($product_reviews)) {
        $sum = array_sum(array_column($product_reviews, 'rating'));
        $avg_rating = round($sum / count($product_reviews), 1);
    }

    // Check favorite status for logged in customer
    if (is_logged_in() && get_logged_in_user_role() === ROLE_CUSTOMER) {
        $fav_stmt = $pdo->prepare("SELECT favorite_id FROM favorites WHERE customer_id = :cid AND product_id = :pid LIMIT 1");
        $fav_stmt->execute([':cid' => get_logged_in_user_id(), ':pid' => $product_id]);
        $is_favorited = (bool)$fav_stmt->fetch();
    }

} catch (PDOException $e) {
    error_log("Product detail query error: " . $e->getMessage());
    set_flash('danger', 'Database error.');
    redirect(BASE_URL . 'customer/browse-products.php');
}

$page_title = $product['name'] . ' — Fresh Harvest';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customer/browse-products.php">Harvest</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>customer/browse-products.php?category=<?= urlencode($product['category']) ?>"><?= e($product['category']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4 mb-5">
        <!-- Product Image Showcase -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="d-flex align-items-center justify-content-center bg-light" style="height: 380px;">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="<?= e(get_image_url($product['image_url'])) ?>" alt="<?= e($product['name']) ?>" class="w-100 h-100 object-fit-cover" onerror="this.src='https://placehold.co/600x400?text=Produce'">
                    <?php else: ?>
                        <i class="bi bi-egg-fried fs-1 text-primary" style="font-size: 5rem !important;"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Product Details & Add to Cart Action -->
        <div class="col-lg-6">
            <div class="ps-lg-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-secondary-subtle text-secondary fs-6 fw-semibold px-3 py-1"><?= e($product['category']) ?></span>
                    
                    <?php if (is_logged_in() && get_logged_in_user_role() === ROLE_CUSTOMER): ?>
                        <form method="POST" action="<?= BASE_URL ?>customer/favorites.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                            <input type="hidden" name="action" value="<?= $is_favorited ? 'remove' : 'add' ?>">
                            <button type="submit" class="btn btn-sm <?= $is_favorited ? 'btn-danger' : 'btn-outline-danger' ?>">
                                <i class="bi <?= $is_favorited ? 'bi-heart-fill' : 'bi-heart' ?> me-1"></i> <?= $is_favorited ? 'Saved' : 'Favorite' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <h1 class="h2 fw-bold mb-2"><?= e($product['name']) ?></h1>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="text-warning small">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi <?= $i <= round($avg_rating) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="small text-muted">(<?= count($product_reviews) ?> customer reviews)</span>
                </div>

                <div class="display-6 fw-bold text-primary mb-3">
                    <?= format_currency($product['price']) ?> <span class="fs-6 text-muted fw-normal">/ <?= e($product['unit']) ?></span>
                </div>

                <!-- Stock Availability Badge -->
                <div class="mb-4">
                    <?php if ($product['is_sold_out'] || $product['quantity_available'] <= 0): ?>
                        <div class="alert alert-danger py-2 small mb-0 d-inline-flex align-items-center">
                            <i class="bi bi-x-circle-fill me-2"></i> Sold Out for this week's market.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success py-2 small mb-0 d-inline-flex align-items-center">
                            <i class="bi bi-check-circle-fill me-2"></i> Available in Stock: <strong><?= $product['quantity_available'] ?> <?= e($product['unit']) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Harvest Description -->
                <div class="mb-4">
                    <h6 class="fw-bold mb-1">Harvest & Freshness Notes</h6>
                    <p class="text-secondary"><?= nl2br(e($product['description'] ?: 'Fresh harvest sourced directly from local market fields.')) ?></p>
                </div>

                <!-- Farmer Stall Card -->
                <div class="p-3 bg-white rounded-3 border mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-3 rounded-circle bg-primary-subtle text-primary fs-4">
                                <i class="bi bi-shop"></i>
                            </div>
                            <div>
                                <div class="fw-bold"><?= e($product['stall_name'] ?: $product['farmer_name']) ?></div>
                                <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($product['stall_address'] ?: 'Market Stall') ?></div>
                                <div class="small text-muted"><i class="bi bi-calendar3 me-1"></i>Days: <?= e($product['operating_days'] ?: 'Sat, Sun') ?></div>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $product['farmer_id'] ?>" class="btn btn-outline-primary btn-sm">
                            View Stall
                        </a>
                    </div>
                </div>

                <!-- Add to Pre-Order Cart Form -->
                <?php if (!$product['is_sold_out'] && $product['quantity_available'] > 0): ?>
                    <form action="<?= BASE_URL ?>customer/cart.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">

                        <div class="d-flex gap-3 align-items-center mb-3">
                            <div style="width: 120px;">
                                <label class="form-label small fw-semibold" for="quantity">Quantity (<?= e($product['unit']) ?>)</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="<?= $product['quantity_available'] ?>" required>
                            </div>
                            <div class="flex-grow-1 pt-4">
                                <button type="submit" class="btn btn-primary btn-lg w-100 py-2 shadow-sm">
                                    <i class="bi bi-cart-plus me-2"></i> Add to Pre-Order Basket
                                </button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary btn-lg w-100 py-2" disabled>
                        Item Currently Unavailable
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Product Customer Reviews Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6"><i class="bi bi-chat-left-heart-fill text-primary me-2"></i>Customer Reviews on this Harvest (<?= count($product_reviews) ?>)</h5>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($product_reviews)): ?>
                <div class="row g-3">
                    <?php foreach ($product_reviews as $rev): ?>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded border h-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold"><?= e($rev['customer_name']) ?></span>
                                    <span class="small text-muted"><?= format_date($rev['created_at']) ?></span>
                                </div>
                                <div class="text-warning small mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi <?= $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="small text-secondary mb-2"><?= nl2br(e($rev['comment'])) ?></p>
                                <?php if (!empty($rev['farmer_response'])): ?>
                                    <div class="p-2 bg-white rounded border-start border-success border-2 small text-muted">
                                        <strong class="text-success"><i class="bi bi-reply-fill me-1"></i>Stall Owner:</strong> <?= e($rev['farmer_response']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <p class="small mb-0">No reviews for this product yet. Pick up this weekend and share your thoughts!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
