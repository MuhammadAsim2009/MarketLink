<?php
/**
 * MarketLink - Customer Favorites (Saved Stalls & Produce)
 */

$required_role = 'customer';
require_once __DIR__ . '/../includes/auth-check.php';

$customer_id = get_logged_in_user_id();

// Handle Toggle Favorite via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session submission.');
        redirect(BASE_URL . 'customer/favorites.php');
    }

    $action = sanitize_input($_POST['action'] ?? 'add');
    $farmer_id = !empty($_POST['farmer_id']) ? (int)$_POST['farmer_id'] : null;
    $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;

    try {
        if ($action === 'add') {
            if ($farmer_id) {
                $ins = $pdo->prepare("INSERT IGNORE INTO favorites (customer_id, farmer_id, created_at) VALUES (:cid, :fid, NOW())");
                $ins->execute([':cid' => $customer_id, ':fid' => $farmer_id]);
                set_flash('success', 'Stall saved to your favorites!');
            } elseif ($product_id) {
                $ins = $pdo->prepare("INSERT IGNORE INTO favorites (customer_id, product_id, created_at) VALUES (:cid, :pid, NOW())");
                $ins->execute([':cid' => $customer_id, ':pid' => $product_id]);
                set_flash('success', 'Harvest item saved to your favorites!');
            }
        } elseif ($action === 'remove') {
            if ($farmer_id) {
                $del = $pdo->prepare("DELETE FROM favorites WHERE customer_id = :cid AND farmer_id = :fid");
                $del->execute([':cid' => $customer_id, ':fid' => $farmer_id]);
                set_flash('info', 'Stall removed from favorites.');
            } elseif ($product_id) {
                $del = $pdo->prepare("DELETE FROM favorites WHERE customer_id = :cid AND product_id = :pid");
                $del->execute([':cid' => $customer_id, ':pid' => $product_id]);
                set_flash('info', 'Product removed from favorites.');
            }
        }
    } catch (PDOException $e) {
        error_log("Toggle favorite error: " . $e->getMessage());
        set_flash('danger', 'Failed to update favorites.');
    }

    // Redirect back to referring page or favorites list
    $referer = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . 'customer/favorites.php');
    redirect($referer);
}

// Fetch Customer's Favorite Stalls
$favorite_farmers = [];
try {
    $f_stmt = $pdo->prepare("SELECT fav.favorite_id, u.user_id, u.name, fp.stall_name, fp.address, fp.operating_days, 
                             (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id AND is_sold_out = 0) as product_count,
                             (SELECT COALESCE(AVG(rating), 5) FROM reviews WHERE farmer_id = u.user_id) as avg_rating 
                             FROM favorites fav 
                             JOIN users u ON fav.farmer_id = u.user_id 
                             LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                             WHERE fav.customer_id = :cid AND fav.farmer_id IS NOT NULL 
                             ORDER BY fav.created_at DESC");
    $f_stmt->execute([':cid' => $customer_id]);
    $favorite_farmers = $f_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch favorite farmers error: " . $e->getMessage());
}

// Fetch Customer's Favorite Products
$favorite_products = [];
try {
    $p_stmt = $pdo->prepare("SELECT fav.favorite_id, p.*, u.name as farmer_name, fp.stall_name 
                             FROM favorites fav 
                             JOIN products p ON fav.product_id = p.product_id 
                             JOIN users u ON p.farmer_id = u.user_id 
                             LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                             WHERE fav.customer_id = :cid AND fav.product_id IS NOT NULL 
                             ORDER BY fav.created_at DESC");
    $p_stmt->execute([':cid' => $customer_id]);
    $favorite_products = $p_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch favorite products error: " . $e->getMessage());
}

$page_title = 'My Saved Favorites';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-heart-fill text-danger me-2"></i>My Saved Favorites</h1>
            <p class="text-muted small mb-0">Quick access to your preferred farmers market stalls and go-to seasonal produce</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-basket me-1"></i> Discover More
            </a>
        </div>
    </div>

    <!-- Favorite Farmers Section -->
    <div class="mb-5">
        <h5 class="fw-bold mb-3"><i class="bi bi-shop text-primary me-2"></i>Favorite Farmer Stalls (<?= count($favorite_farmers) ?>)</h5>

        <?php if (!empty($favorite_farmers)): ?>
            <div class="row g-3">
                <?php foreach ($favorite_farmers as $f): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border-0 p-3 card-hover bg-white">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="p-2 rounded-circle bg-primary-subtle text-primary fs-5">
                                        <i class="bi bi-shop"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0"><?= e($f['stall_name'] ?: $f['name']) ?></h6>
                                        <div class="text-warning small">
                                            <i class="bi bi-star-fill"></i> <?= number_format($f['avg_rating'], 1) ?>
                                        </div>
                                    </div>
                                </div>
                                <form method="POST" action="<?= BASE_URL ?>customer/favorites.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="farmer_id" value="<?= $f['user_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Remove from Favorites">
                                        <i class="bi bi-heart-fill fs-5"></i>
                                    </button>
                                </form>
                            </div>

                            <p class="small text-muted mb-3">
                                <i class="bi bi-geo-alt text-danger me-1"></i> <?= e($f['address'] ?: 'Market Stall') ?><br>
                                <i class="bi bi-calendar3 text-primary me-1"></i> Days: <strong><?= e($f['operating_days'] ?: 'Sat, Sun') ?></strong>
                            </p>

                            <div class="mt-auto d-flex gap-2">
                                <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $f['user_id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                    View Stall
                                </a>
                                <a href="<?= BASE_URL ?>customer/browse-products.php?farmer_id=<?= $f['user_id'] ?>" class="btn btn-primary btn-sm flex-grow-1">
                                    Browse Stock (<?= $f['product_count'] ?>)
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 p-4 text-center text-muted">
                <i class="bi bi-shop fs-2 text-secondary-subtle mb-2"></i>
                <p class="small mb-0">You have not saved any favorite farmer stalls yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Favorite Products Section -->
    <div>
        <h5 class="fw-bold mb-3"><i class="bi bi-basket-fill text-primary me-2"></i>Favorite Produce & Items (<?= count($favorite_products) ?>)</h5>

        <?php if (!empty($favorite_products)): ?>
            <div class="row g-3">
                <?php foreach ($favorite_products as $p): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card product-card card-hover shadow-sm border-0">
                            <div class="product-img-wrapper d-flex align-items-center justify-content-center bg-light">
                                <?php if (!empty($p['image_url'])): ?>
                                    <img src="<?= e(get_image_url($p['image_url'])) ?>" alt="<?= e($p['name']) ?>" class="w-100 h-100 object-fit-cover" onerror="this.src='https://placehold.co/300x200?text=Produce'">
                                <?php else: ?>
                                    <i class="bi bi-egg-fried fs-1 text-primary-subtle"></i>
                                <?php endif; ?>

                                <form method="POST" action="<?= BASE_URL ?>customer/favorites.php" class="position-absolute top-0 end-0 m-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= $p['product_id'] ?>">
                                    <button type="submit" class="btn btn-light btn-sm rounded-circle p-1 shadow-sm" title="Remove Favorite">
                                        <i class="bi bi-heart-fill text-danger"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="product-body">
                                <span class="badge bg-secondary-subtle text-secondary align-self-start mb-2"><?= e($p['category']) ?></span>
                                <h6 class="fw-bold mb-1 text-truncate"><?= e($p['name']) ?></h6>
                                <div class="small text-muted mb-2"><?= e($p['stall_name'] ?: $p['farmer_name']) ?></div>
                                <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                                    <span class="product-price"><?= format_currency($p['price']) ?> <small class="text-muted fs-6 fw-normal">/ <?= e($p['unit']) ?></small></span>
                                    <a href="<?= BASE_URL ?>customer/product-detail.php?id=<?= $p['product_id'] ?>" class="btn btn-sm btn-primary">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0 p-4 text-center text-muted">
                <i class="bi bi-basket fs-2 text-secondary-subtle mb-2"></i>
                <p class="small mb-0">No favorite produce saved yet. Click the heart icon on any product to save it here!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
