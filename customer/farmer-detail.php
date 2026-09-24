<?php
/**
 * MarketLink - Farmer Stall Profile & Available Produce View
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$farmer_id = (int)($_GET['farmer_id'] ?? 0);
if ($farmer_id <= 0) {
    set_flash('danger', 'Farmer stall not found.');
    redirect(BASE_URL . 'customer/browse-markets.php');
}

// Fetch Farmer Info & Stall Profile
$farmer = null;
$products = [];
$reviews = [];
$avg_rating = 5.0;
$attending_markets = [];
$is_favorited = false;

try {
    $f_stmt = $pdo->prepare("SELECT u.user_id, u.name as contact_name, u.email, u.phone, fp.* 
                             FROM users u 
                             LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                             WHERE u.user_id = :fid AND u.role = 'farmer' AND u.status = 'active' LIMIT 1");
    $f_stmt->execute([':fid' => $farmer_id]);
    $farmer = $f_stmt->fetch();

    if (!$farmer) {
        set_flash('danger', 'Farmer stall is not currently active or found.');
        redirect(BASE_URL . 'customer/browse-markets.php');
    }

    // Fetch this farmer's products
    $p_stmt = $pdo->prepare("SELECT * FROM products WHERE farmer_id = :fid ORDER BY is_sold_out ASC, created_at DESC");
    $p_stmt->execute([':fid' => $farmer_id]);
    $products = $p_stmt->fetchAll();

    // Fetch markets this farmer attends
    $m_stmt = $pdo->prepare("SELECT m.market_id, m.market_name, m.address, m.operating_days 
                             FROM market_farmers mf 
                             JOIN markets m ON mf.market_id = m.market_id 
                             WHERE mf.farmer_id = :fid");
    $m_stmt->execute([':fid' => $farmer_id]);
    $attending_markets = $m_stmt->fetchAll();

    // Fetch reviews
    $r_stmt = $pdo->prepare("SELECT r.*, u.name as customer_name 
                             FROM reviews r 
                             JOIN users u ON r.customer_id = u.user_id 
                             WHERE r.farmer_id = :fid 
                             ORDER BY r.created_at DESC");
    $r_stmt->execute([':fid' => $farmer_id]);
    $reviews = $r_stmt->fetchAll();

    if (!empty($reviews)) {
        $sum = array_sum(array_column($reviews, 'rating'));
        $avg_rating = round($sum / count($reviews), 1);
    }

    // Check if customer has favorited this farmer
    if (is_logged_in() && get_logged_in_user_role() === ROLE_CUSTOMER) {
        $fav_chk = $pdo->prepare("SELECT favorite_id FROM favorites WHERE customer_id = :cid AND farmer_id = :fid LIMIT 1");
        $fav_chk->execute([':cid' => get_logged_in_user_id(), ':fid' => $farmer_id]);
        $is_favorited = (bool)$fav_chk->fetch();
    }

} catch (PDOException $e) {
    error_log("Farmer detail error: " . $e->getMessage());
    set_flash('danger', 'Database error.');
    redirect(BASE_URL . 'customer/browse-markets.php');
}

$page_title = ($farmer['stall_name'] ?: $farmer['contact_name']) . ' — Stall Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Farmer Stall Header Banner -->
    <div class="card shadow-sm border-0 mb-4 overflow-hidden">
        <div class="card-body p-4 p-md-5 bg-white">
            <div class="row align-items-center g-4">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i> Verified Local Grower</span>
                        <div class="text-warning small ms-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi <?= $i <= round($avg_rating) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                            <?php endfor; ?>
                            <span class="text-muted ms-1">(<?= count($reviews) ?> reviews)</span>
                        </div>
                    </div>
                    <h1 class="h2 fw-bold mb-2"><?= e($farmer['stall_name'] ?: $farmer['contact_name']) ?></h1>
                    <p class="text-muted mb-3"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($farmer['address'] ?: 'Market Stall') ?></p>
                    
                    <div class="d-flex flex-wrap gap-3 small text-muted">
                        <div><i class="bi bi-calendar-check text-primary me-1"></i> Market Days: <strong><?= e($farmer['operating_days'] ?: 'Sat, Sun') ?></strong></div>
                        <div><i class="bi bi-clock text-primary me-1"></i> Pickup Hours: <strong><?= e($farmer['pickup_window_start'] ?? '08:00') ?> - <?= e($farmer['pickup_window_end'] ?? '14:00') ?></strong></div>
                        <div><i class="bi bi-hourglass-split text-primary me-1"></i> Pre-order Cutoff: <strong><?= e($farmer['order_cutoff_hours'] ?? 2) ?> hrs before pickup</strong></div>
                    </div>
                </div>

                <div class="col-md-4 text-md-end">
                    <?php if (is_logged_in() && get_logged_in_user_role() === ROLE_CUSTOMER): ?>
                        <form method="POST" action="<?= BASE_URL ?>customer/favorites.php" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="farmer_id" value="<?= $farmer['user_id'] ?>">
                            <input type="hidden" name="action" value="<?= $is_favorited ? 'remove' : 'add' ?>">
                            <button type="submit" class="btn <?= $is_favorited ? 'btn-danger' : 'btn-outline-danger' ?> btn-sm px-3">
                                <i class="bi <?= $is_favorited ? 'bi-heart-fill' : 'bi-heart' ?> me-1"></i> <?= $is_favorited ? 'Saved to Favorites' : 'Save Favorite Stall' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="#produce-section" class="btn btn-primary btn-sm px-3 ms-2">
                        <i class="bi bi-basket me-1"></i> View Harvest
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Attending Markets List -->
    <?php if (!empty($attending_markets)): ?>
        <div class="alert alert-light border shadow-sm mb-4 d-flex align-items-center gap-3">
            <i class="bi bi-geo-fill fs-3 text-primary"></i>
            <div>
                <strong>Find this stall at the following weekend markets:</strong>
                <div class="small text-muted mt-1">
                    <?php foreach ($attending_markets as $m): ?>
                        <span class="badge bg-white text-dark border me-2"><i class="bi bi-pin-map text-primary me-1"></i><?= e($m['market_name']) ?> (<?= e($m['operating_days']) ?>)</span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Farmer Produce Catalog -->
    <div id="produce-section" class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="h5 fw-bold mb-0"><i class="bi bi-basket-fill text-primary me-2"></i>Weekly Harvest & Stock (<?= count($products) ?>)</h4>
            <span class="small text-muted">Reserve for weekend pickup</span>
        </div>

        <div class="row g-4">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $prod): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="card product-card card-hover <?= $prod['is_sold_out'] ? 'opacity-75' : '' ?>">
                            <div class="product-img-wrapper d-flex align-items-center justify-content-center bg-light text-muted">
                                <?php if (!empty($prod['image_url'])): ?>
                                    <img src="<?= e(get_image_url($prod['image_url'])) ?>" alt="<?= e($prod['name']) ?>" class="w-100 h-100 object-fit-cover" onerror="this.src='https://placehold.co/300x200?text=Produce'">
                                <?php else: ?>
                                    <i class="bi bi-egg-fried fs-1 text-primary-subtle"></i>
                                <?php endif; ?>

                                <?php if ($prod['is_sold_out']): ?>
                                    <span class="position-absolute top-0 end-0 badge bg-danger m-2">Sold Out</span>
                                <?php elseif ($prod['quantity_available'] <= 5): ?>
                                    <span class="position-absolute top-0 end-0 badge bg-warning text-dark m-2">Only <?= $prod['quantity_available'] ?> Left</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-body">
                                <span class="badge bg-secondary-subtle text-secondary align-self-start mb-2"><?= e($prod['category']) ?></span>
                                <h5 class="h6 fw-bold mb-1 text-truncate" title="<?= e($prod['name']) ?>"><?= e($prod['name']) ?></h5>
                                <p class="small text-muted text-truncate mb-2"><?= e($prod['description'] ?: 'Fresh from local farm') ?></p>
                                
                                <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                                    <span class="product-price"><?= format_currency($prod['price']) ?><small class="text-muted fs-6 fw-normal"> / <?= e($prod['unit']) ?></small></span>
                                    <a href="<?= BASE_URL ?>customer/product-detail.php?id=<?= $prod['product_id'] ?>" class="btn btn-sm btn-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="bi bi-basket fs-1 text-secondary-subtle d-block mb-2"></i>
                    <h6>No harvest products listed currently</h6>
                    <p class="small text-muted mb-0">Check back closer to the weekend market date when new harvests are published.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stall Map Location & Reviews Breakdown -->
    <div class="row g-4">
        <!-- Location Map -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fs-6"><i class="bi bi-pin-map text-accent me-2"></i>Stall & Farm Coordinates</h5>
                </div>
                <div class="card-body p-3">
                    <div id="stallDetailMap" style="height: 280px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);"></div>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-geo-alt me-1"></i> <?= e($farmer['address'] ?: 'Location on map') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews List -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fs-6"><i class="bi bi-chat-heart text-primary me-2"></i>Customer Reviews (<?= count($reviews) ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($reviews)): ?>
                        <div class="list-group list-group-flush" style="max-height: 330px; overflow-y: auto;">
                            <?php foreach ($reviews as $rev): ?>
                                <div class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold small"><?= e($rev['customer_name']) ?></span>
                                        <span class="text-muted small"><?= format_date($rev['created_at']) ?></span>
                                    </div>
                                    <div class="text-warning small mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= $rev['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="small text-secondary mb-2"><?= nl2br(e($rev['comment'])) ?></p>
                                    <?php if (!empty($rev['farmer_response'])): ?>
                                        <div class="p-2 bg-light rounded border-start border-success border-2 small text-muted">
                                            <strong class="text-success"><i class="bi bi-reply-fill me-1"></i>Stall Reply:</strong> <?= e($rev['farmer_response']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-star fs-2 text-secondary-subtle d-block mb-1"></i>
                            <p class="small mb-0">No reviews yet for this stall. Be the first to leave a review after your pickup!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet Stall Map Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lat = <?= (float)($farmer['latitude'] ?: 31.5204) ?>;
    const lng = <?= (float)($farmer['longitude'] ?: 74.3587) ?>;

    const map = L.map('stallDetailMap').setView([lat, lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map)
        .bindPopup('<strong><?= e(addslashes($farmer['stall_name'] ?: $farmer['contact_name'])) ?></strong><br><?= e(addslashes($farmer['address'] ?: 'Stall Location')) ?>')
        .openPopup();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
