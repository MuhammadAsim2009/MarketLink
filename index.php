<?php
/**
 * MarketLink - Public Landing Page
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$active_nav  = 'home';
$page_title  = 'Farm Fresh, Just a Click Away';

$markets          = [];
$featured_products = [];
$total_farmers    = 0;
$total_markets    = 0;
$total_products   = 0;

try {
    $markets          = $pdo->query("SELECT * FROM markets ORDER BY market_name ASC LIMIT 3")->fetchAll();
    $featured_products = $pdo->query(
        "SELECT p.*, u.name AS farmer_name, fp.stall_name
           FROM products p
           JOIN users u ON p.farmer_id = u.user_id
           LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id
          WHERE p.is_sold_out = 0
          ORDER BY p.created_at DESC LIMIT 4"
    )->fetchAll();
    $total_farmers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='farmer' AND status='active'")->fetchColumn();
    $total_markets  = (int)$pdo->query("SELECT COUNT(*) FROM markets")->fetchColumn();
    $total_products = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_sold_out = 0")->fetchColumn();
} catch (PDOException $e) {
    error_log("Landing page data error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Hero ────────────────────────────────────────────────────── -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">

            <!-- Left copy -->
            <div class="col-lg-6">
                <div class="hero-eyebrow">
                    <i class="bi bi-patch-check-fill"></i>
                    Aptech TechWiz 7 · Best Project
                </div>
                <h1>
                    Farm-Fresh Produce,<br>
                    Direct from <span class="highlight">Local Farmers</span>
                </h1>
                <p class="hero-lead">
                    Skip the middlemen. Browse real-time harvest from local weekend markets, lock in your basket with a pre-order, and collect fresh at the stall.
                </p>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-basket2"></i> Browse Fresh Stock
                    </a>
                    <a href="<?= BASE_URL ?>customer/browse-markets.php" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-geo-alt"></i> Find a Market
                    </a>
                </div>
                <div class="hero-stats">
                    <div>
                        <div class="hero-stat-val"><?= max(3, $total_markets) ?>+</div>
                        <div class="hero-stat-lbl">Active Markets</div>
                    </div>
                    <div>
                        <div class="hero-stat-val"><?= max(5, $total_farmers) ?>+</div>
                        <div class="hero-stat-lbl">Local Stalls</div>
                    </div>
                    <div>
                        <div class="hero-stat-val"><?= max(20, $total_products) ?>+</div>
                        <div class="hero-stat-lbl">Fresh Items</div>
                    </div>
                </div>
            </div>

            <!-- Right widget -->
            <div class="col-lg-6 col-xl-5 offset-xl-1">
                <div class="hero-widget">
                    <div class="hero-widget-header">
                        <span><i class="bi bi-search me-2"></i>Quick Stock Finder</span>
                        <span class="live-dot">Live inventory</span>
                    </div>
                    <div class="hero-widget-body">
                        <form action="<?= BASE_URL ?>customer/browse-products.php" method="GET">
                            <div class="mb-3">
                                <label class="form-label">What are you looking for?</label>
                                <div class="input-group" style="gap:0;">
                                    <span style="display:flex;align-items:center;padding:0 .875rem;background:var(--surface-2);border:1.5px solid var(--border);border-right:none;border-radius:var(--radius-sm) 0 0 var(--radius-sm);color:var(--text-3);">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" name="q" class="form-control"
                                           placeholder="Tomatoes, eggs, berries…"
                                           style="border-radius:0 var(--radius-sm) var(--radius-sm) 0; border-left:none;">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">All categories</option>
                                    <option value="Vegetables">🥦 Organic Vegetables</option>
                                    <option value="Fruits">🍓 Fresh Orchard Fruits</option>
                                    <option value="Dairy &amp; Eggs">🥚 Dairy &amp; Pasture Eggs</option>
                                    <option value="Bakery &amp; Honey">🍯 Artisanal Bakery &amp; Honey</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-accent w-100 btn-lg">
                                <i class="bi bi-arrow-right-circle"></i> Search Harvest
                            </button>
                        </form>

                        <!-- Quick links -->
                        <div class="mt-4 pt-3" style="border-top:1px solid var(--border);">
                            <p class="form-label mb-2">Popular right now</p>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                $tags = ['Tomatoes','Eggs','Strawberries','Spinach','Apples','Cheese'];
                                foreach ($tags as $tag): ?>
                                    <a href="<?= BASE_URL ?>customer/browse-products.php?q=<?= urlencode($tag) ?>"
                                       style="display:inline-flex;align-items:center;padding:.3rem .75rem;border:1.5px solid var(--border);border-radius:var(--radius-pill);font-size:.8rem;color:var(--text-2);transition:all var(--transition);"
                                       onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';this.style.background='var(--primary-light)';"
                                       onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-2)';this.style.background='transparent';">
                                        <?= $tag ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── How It Works ─────────────────────────────────────────────── -->
<section class="section-gap bg-white" style="border-bottom:1px solid var(--border);">
    <div class="container">
        <div class="text-center max-w-600 mx-auto mb-5">
            <p class="section-label">Simple Process</p>
            <h2>How MarketLink Works</h2>
            <p class="text-muted" style="font-size:1rem; margin-top:.75rem;">
                Three steps from farm to your hands — no middlemen, no surprises.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-icon" style="background:#EAF5EE;">
                        <i class="bi bi-geo-alt-fill" style="color:var(--primary);"></i>
                    </div>
                    <p class="step-num">Step 01</p>
                    <h3>Locate Your Market</h3>
                    <p>Browse our interactive OpenStreetMap directory to find weekend farmers markets near you and see which stalls will be attending.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-icon" style="background:#FEF3EC;">
                        <i class="bi bi-basket3-fill" style="color:var(--accent);"></i>
                    </div>
                    <p class="step-num">Step 02</p>
                    <h3>Reserve Your Pre-Order</h3>
                    <p>Browse real-time weekly inventory, select your quantities, pick a pickup time slot, and lock in your order before the cutoff.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="step-icon" style="background:#EFF8FF;">
                        <i class="bi bi-bag-check-fill" style="color:var(--info);"></i>
                    </div>
                    <p class="step-num">Step 03</p>
                    <h3>Collect &amp; Pay at Stall</h3>
                    <p>Visit the farmer's stall at your selected slot, collect your fresh produce without the queue, and pay cash directly to the grower.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Featured Products ─────────────────────────────────────────── -->
<section class="section-gap">
    <div class="container">
        <div class="d-flex align-items-end justify-content-between mb-5">
            <div>
                <p class="section-label mb-1">Harvest Highlights</p>
                <h2 class="mb-0">Freshly Listed This Week</h2>
            </div>
            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-outline-primary btn-sm" style="white-space:nowrap;">
                View all <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php if (!empty($featured_products)): ?>
                <?php foreach ($featured_products as $prod): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="product-card">
                            <div class="product-img-wrapper">
                                <?php if (!empty($prod['image_url']) && file_exists(__DIR__ . '/' . $prod['image_url'])): ?>
                                    <img src="<?= BASE_URL . e($prod['image_url']) ?>" alt="<?= e($prod['name']) ?>">
                                <?php else: ?>
                                    <i class="bi bi-egg-fried product-img-placeholder"></i>
                                <?php endif; ?>
                            </div>
                            <div class="product-body">
                                <span class="badge badge-neutral mb-2"><?= e($prod['category']) ?></span>
                                <h4 style="font-size:.95rem; font-weight:700; margin-bottom:.3rem;" class="text-truncate" title="<?= e($prod['name']) ?>">
                                    <?= e($prod['name']) ?>
                                </h4>
                                <p style="font-size:.8rem; color:var(--text-3); margin-bottom:auto; display:flex; align-items:center; gap:5px;">
                                    <i class="bi bi-shop" style="color:var(--primary);"></i>
                                    <?= e($prod['stall_name'] ?: $prod['farmer_name']) ?>
                                </p>
                                <div class="divider" style="margin:1rem 0 .75rem;"></div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="product-price">
                                        <?= format_currency($prod['price']) ?>
                                        <small>/ <?= e($prod['unit']) ?></small>
                                    </div>
                                    <a href="<?= BASE_URL ?>customer/product-detail.php?id=<?= $prod['product_id'] ?>"
                                       class="btn btn-primary btn-sm">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5" style="background:var(--surface);border:1.5px dashed var(--border);border-radius:var(--radius-lg);">
                        <i class="bi bi-basket2" style="font-size:2.5rem;color:var(--border);display:block;margin-bottom:1rem;"></i>
                        <p class="text-muted mb-0">Fresh listings will appear here once farmers update their stock.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── Markets Grid ──────────────────────────────────────────────── -->
<?php if (!empty($markets)): ?>
<section class="section-gap" style="background:var(--surface); border-top:1px solid var(--border); border-bottom:1px solid var(--border);">
    <div class="container">
        <div class="d-flex align-items-end justify-content-between mb-5">
            <div>
                <p class="section-label mb-1">Local Venues</p>
                <h2 class="mb-0">This Weekend's Markets</h2>
            </div>
            <a href="<?= BASE_URL ?>customer/browse-markets.php" class="btn btn-outline-primary btn-sm" style="white-space:nowrap;">
                All markets <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($markets as $m): ?>
                <div class="col-md-4">
                    <a href="<?= BASE_URL ?>customer/browse-markets.php?market=<?= $m['market_id'] ?>"
                       style="text-decoration:none;">
                        <div class="card card-hover h-100" style="padding:1.5rem;">
                            <div style="display:flex;align-items:center;gap:14px;margin-bottom:1rem;">
                                <div style="width:48px;height:48px;background:var(--primary-light);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="bi bi-shop" style="color:var(--primary);font-size:1.25rem;"></i>
                                </div>
                                <h3 style="font-size:.95rem;font-weight:700;margin:0;color:var(--text);"><?= e($m['market_name']) ?></h3>
                            </div>
                            <p style="font-size:.83rem;color:var(--text-3);margin-bottom:.75rem;display:flex;align-items:flex-start;gap:5px;">
                                <i class="bi bi-geo-alt" style="color:var(--primary);flex-shrink:0;margin-top:2px;"></i>
                                <?= e($m['address']) ?>
                            </p>
                            <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:auto;">
                                <?php foreach (explode(',', $m['operating_days']) as $day): ?>
                                    <span class="badge badge-primary"><?= trim($day) ?></span>
                                <?php endforeach; ?>
                                <?php if ($m['timings']): ?>
                                    <span class="badge badge-neutral"><i class="bi bi-clock me-1"></i><?= e($m['timings']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── CTA Banner ────────────────────────────────────────────────── -->
<section class="cta-section">
    <div class="container text-center" style="position:relative;z-index:1;">
        <div class="max-w-600 mx-auto">
            <p class="section-label" style="color:rgba(255,255,255,.6);">For Farmers</p>
            <h2 style="color:#fff; font-size:clamp(1.6rem,4vw,2.25rem); margin-bottom:1rem;">
                Run Your Stall Smarter
            </h2>
            <p style="color:rgba(255,255,255,.7); font-size:1.05rem; margin-bottom:2rem; line-height:1.7;">
                List your weekly harvest, set pickup windows, accept pre-orders from local customers, and cut food waste — all from one dashboard.
            </p>
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <a href="<?= BASE_URL ?>auth/register.php?role=farmer" class="btn btn-accent btn-lg">
                    <i class="bi bi-shop"></i> Register Your Stall
                </a>
                <a href="<?= BASE_URL ?>about.php"
                   style="display:inline-flex;align-items:center;gap:6px;color:rgba(255,255,255,.75);padding:.75rem 1.5rem;border:1.5px solid rgba(255,255,255,.25);border-radius:var(--radius-sm);font-size:1rem;font-weight:600;font-family:var(--font-display);transition:all var(--transition);"
                   onmouseover="this.style.color='#fff';this.style.borderColor='rgba(255,255,255,.6)';"
                   onmouseout="this.style.color='rgba(255,255,255,.75)';this.style.borderColor='rgba(255,255,255,.25)';">
                    Learn more <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
