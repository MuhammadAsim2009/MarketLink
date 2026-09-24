<?php
/**
 * MarketLink - About Us Page
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$active_nav = 'about';
$page_title = 'About Us';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="hero-section py-5">
    <div class="container py-3">
        <div class="max-w-700 mx-auto text-center">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold mb-3">
                <i class="bi bi-patch-check-fill me-1"></i> Aptech TechWiz 7 Competition Entry
            </span>
            <h1 class="display-5 fw-bold mb-3">Connecting Local Growers With Conscious Communities</h1>
            <p class="lead text-muted mb-0">
                MarketLink is a web platform designed to empower local farmers market sellers and provide shoppers with direct access to freshly harvested seasonal produce.
            </p>
        </div>
    </div>
</section>

<!-- Mission & Pillars -->
<section class="py-5 bg-white border-bottom">
    <div class="container py-3">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <span class="text-primary fw-bold text-uppercase small letter-spacing-1">Our Core Mission</span>
                <h2 class="h2 fw-bold mb-3">Eliminating Middlemen, Supporting Local Farmsteads</h2>
                <p class="text-secondary mb-3">
                    Small local farmers often struggle with erratic weekend foot traffic, unpredictable sales, and unnecessary food waste. Meanwhile, urban shoppers want organic, farm-fresh fruit and vegetables but miss out when stalls run out early.
                </p>
                <p class="text-secondary mb-4">
                    <strong>MarketLink</strong> solves this by providing a lightweight pre-order scheduling platform where farmers publish their weekly harvest, and shoppers reserve their produce ahead of weekend market hours.
                </p>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded border">
                            <h6 class="fw-bold text-primary mb-1"><i class="bi bi-recycle me-1"></i> Zero Food Waste</h6>
                            <p class="small text-muted mb-0">Farmers harvest and pack exactly what has been pre-ordered.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded border">
                            <h6 class="fw-bold text-primary mb-1"><i class="bi bi-clock-history me-1"></i> Express Pickup</h6>
                            <p class="small text-muted mb-0">Shoppers skip queues with scheduled weekend collection slots.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow border-0 rounded-4 p-4 bg-light">
                    <h5 class="fw-bold mb-3"><i class="bi bi-code-slash text-primary me-2"></i>TechWiz 7 Project Architecture</h5>
                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 small">
                        <li class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div>
                                <strong>Plain PHP Backend:</strong> Procedural function-based architecture adhering strictly to Aptech standards without external framework overhead.
                            </div>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div>
                                <strong>Secure PDO & Prepared Statements:</strong> 100% parameter-bound queries, zero concatenated SQL, with strict row-level security.
                            </div>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div>
                                <strong>OpenStreetMap & Leaflet.js:</strong> Free, open-source map geocoding for market discovery without API key or billing limitations.
                            </div>
                        </li>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div>
                                <strong>Custom Natural Design System:</strong> Earthy palette (#3D8B47 Fresh Green, #E8935A Harvest Accent) with Poppins and Inter typography.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5 text-center bg-body-tertiary">
    <div class="container py-3">
        <div class="max-w-700 mx-auto">
            <h3 class="fw-bold mb-3">Ready to Experience Farm Fresh?</h3>
            <p class="text-muted mb-4">Discover markets in your neighborhood or register your farm stall today.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= BASE_URL ?>customer/browse-markets.php" class="btn btn-primary px-4 py-2">
                    <i class="bi bi-geo-alt me-1"></i> Find Markets
                </a>
                <a href="<?= BASE_URL ?>auth/register.php?role=farmer" class="btn btn-outline-primary px-4 py-2">
                    <i class="bi bi-shop me-1"></i> Register Stall
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
