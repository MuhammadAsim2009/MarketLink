<?php
/**
 * MarketLink - Browse & Filter Fresh Produce
 * Accessible to Visitors and Customers
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_nav = 'products';
$page_title = 'Fresh Harvest & Produce';

// Filter parameters
$search_q = sanitize_input($_GET['q'] ?? '');
$filter_category = sanitize_input($_GET['category'] ?? '');
$filter_market_id = (int)($_GET['market_id'] ?? $_GET['market'] ?? 0);
$filter_farmer_id = (int)($_GET['farmer_id'] ?? 0);
$filter_max_price = !empty($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$filter_in_stock = isset($_GET['in_stock']) ? (int)$_GET['in_stock'] : 1;
$sort = sanitize_input($_GET['sort'] ?? 'newest');

$categories = ['Vegetables', 'Fruits', 'Dairy & Eggs', 'Bakery & Honey', 'Herbs & Microgreens', 'Pantry & Preserves'];

// Build dynamic query
$sql = "SELECT p.*, u.name as farmer_contact, fp.stall_name, fp.address as stall_address 
        FROM products p 
        JOIN users u ON p.farmer_id = u.user_id 
        LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
        WHERE u.status = 'active'";
$params = [];

if (!empty($search_q)) {
    $sql .= " AND (p.name LIKE :q1 OR p.description LIKE :q2 OR fp.stall_name LIKE :q3 OR p.category LIKE :q4)";
    $params[':q1'] = '%' . $search_q . '%';
    $params[':q2'] = '%' . $search_q . '%';
    $params[':q3'] = '%' . $search_q . '%';
    $params[':q4'] = '%' . $search_q . '%';
}
if (!empty($filter_category)) {
    $sql .= " AND p.category = :cat";
    $params[':cat'] = $filter_category;
}
if ($filter_farmer_id > 0) {
    $sql .= " AND p.farmer_id = :fid";
    $params[':fid'] = $filter_farmer_id;
}
if ($filter_market_id > 0) {
    $sql .= " AND p.farmer_id IN (SELECT farmer_id FROM market_farmers WHERE market_id = :mid)";
    $params[':mid'] = $filter_market_id;
}
if ($filter_max_price > 0) {
    $sql .= " AND p.price <= :max_price";
    $params[':max_price'] = $filter_max_price;
}
if ($filter_in_stock) {
    $sql .= " AND p.is_sold_out = 0 AND p.quantity_available > 0";
}

// Sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY p.name ASC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

$products = [];
$markets = [];
$farmers = [];

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    $markets = $pdo->query("SELECT market_id, market_name FROM markets ORDER BY market_name ASC")->fetchAll();
    $farmers = $pdo->query("SELECT u.user_id, u.name, fp.stall_name FROM users u LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id WHERE u.role = 'farmer' AND u.status = 'active' ORDER BY COALESCE(fp.stall_name, u.name) ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Browse products error: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-basket text-primary me-2"></i>Browse Fresh Farm Harvest</h1>
            <p class="text-muted small mb-0">Reserve weekly fruit, vegetables, dairy, and artisanal baked goods directly from local stalls</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border py-2 px-3">
                <i class="bi bi-check2-circle text-success me-1"></i> <?= count($products) ?> items available
            </span>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="card shadow-sm border-0 sticky-top" style="top: 85px;">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-6"><i class="bi bi-sliders me-1 text-primary"></i> Filter Harvest</span>
                    <a href="<?= BASE_URL ?>customer/browse-products.php" class="small text-muted text-decoration-underline">Reset</a>
                </div>
                <div class="card-body p-3">
                    <form action="<?= BASE_URL ?>customer/browse-products.php" method="GET">
                        <!-- Search Box -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="q">Keyword</label>
                            <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= e($search_q) ?>" placeholder="Tomatoes, eggs, honey...">
                        </div>

                        <!-- Category -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="category">Category</label>
                            <select class="form-select form-select-sm" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Market Filter -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="market_id">Specific Market</label>
                            <select class="form-select form-select-sm" id="market_id" name="market_id">
                                <option value="">All Markets</option>
                                <?php foreach ($markets as $m): ?>
                                    <option value="<?= $m['market_id'] ?>" <?= $filter_market_id === (int)$m['market_id'] ? 'selected' : '' ?>><?= e($m['market_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Farmer Filter -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="farmer_id">Specific Farmer / Stall</label>
                            <select class="form-select form-select-sm" id="farmer_id" name="farmer_id">
                                <option value="">All Farmers</option>
                                <?php foreach ($farmers as $f): ?>
                                    <option value="<?= $f['user_id'] ?>" <?= $filter_farmer_id === (int)$f['user_id'] ? 'selected' : '' ?>><?= e($f['stall_name'] ?: $f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- In Stock Only -->
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="in_stock" <?= $filter_in_stock ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="in_stock">
                                In Stock & Available Only
                            </label>
                        </div>

                        <!-- Sort Order -->
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="sort">Sort By</label>
                            <select class="form-select form-select-sm" id="sort" name="sort">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Freshly Listed (Newest)</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100 py-2">
                            <i class="bi bi-funnel me-1"></i> Apply Filters
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="col-lg-9">
            <?php if (!empty($products)): ?>
                <div class="row g-3">
                    <?php foreach ($products as $prod): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card product-card card-hover <?= $prod['is_sold_out'] ? 'opacity-75' : '' ?>">
                                <div class="product-img-wrapper d-flex align-items-center justify-content-center bg-light text-muted">
                                    <?php if (!empty($prod['image_url'])): ?>
                                        <img src="<?= BASE_URL . e($prod['image_url']) ?>" alt="<?= e($prod['name']) ?>" class="w-100 h-100 object-fit-cover" onerror="this.src='https://placehold.co/300x200?text=Produce'">
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
                                    
                                    <div class="small text-muted mb-2">
                                        <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $prod['farmer_id'] ?>" class="text-muted text-decoration-none">
                                            <i class="bi bi-shop me-1 text-primary"></i> <?= e($prod['stall_name'] ?: $prod['farmer_contact']) ?>
                                        </a>
                                    </div>

                                    <div class="mt-auto d-flex justify-content-between align-items-center pt-2 border-top">
                                        <span class="product-price"><?= format_currency($prod['price']) ?><small class="text-muted fs-6 fw-normal"> / <?= e($prod['unit']) ?></small></span>
                                        <a href="<?= BASE_URL ?>customer/product-detail.php?id=<?= $prod['product_id'] ?>" class="btn btn-sm btn-primary">
                                            View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 p-5 text-center text-muted">
                    <i class="bi bi-search fs-1 text-secondary-subtle mb-3"></i>
                    <h5>No harvest produce found</h5>
                    <p class="small text-muted mb-3">Try adjusting your keyword, category, or clearing the in-stock filters.</p>
                    <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm align-self-center px-4">
                        Clear All Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
