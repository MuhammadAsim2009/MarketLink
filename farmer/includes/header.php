<?php
/**
 * MarketLink - Farmer Portal Shared Header & SaaS Sidebar Layout
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$required_role = 'farmer';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

$farmer_id     = get_logged_in_user_id();
$farmer_name   = get_logged_in_user_name();
$user_initial  = $farmer_name ? strtoupper(mb_substr($farmer_name, 0, 1)) : 'F';
$page_title    = isset($page_title) ? $page_title . ' — Farmer Portal' : 'Farmer Portal — MarketLink';
$active_nav    = $active_nav ?? 'dashboard';

// Fetch farmer profile & real-time badge counts
$sidebar_profile = null;
$sidebar_pending_orders = 0;
$sidebar_products_count = 0;
$sidebar_unread_notifs = 0;

if (isset($pdo) && $farmer_id) {
    try {
        // Fetch fresh user name and email from database
        $u_stmt = $pdo->prepare("SELECT name, email FROM users WHERE user_id = :uid LIMIT 1");
        $u_stmt->execute([':uid' => $farmer_id]);
        $u_data = $u_stmt->fetch();
        if ($u_data && !empty($u_data['name'])) {
            $farmer_name = $u_data['name'];
            $_SESSION['user_name'] = $farmer_name;
        }

        // Stall profile
        $fp_stmt = $pdo->prepare("SELECT stall_name, address, operating_days FROM farmer_profiles WHERE farmer_id = :fid LIMIT 1");
        $fp_stmt->execute([':fid' => $farmer_id]);
        $sidebar_profile = $fp_stmt->fetch();

        // Pending pre-orders count
        $po_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE farmer_id = :fid AND status = 'placed'");
        $po_stmt->execute([':fid' => $farmer_id]);
        $sidebar_pending_orders = (int)$po_stmt->fetchColumn();

        // Products count
        $pr_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE farmer_id = :fid");
        $pr_stmt->execute([':fid' => $farmer_id]);
        $sidebar_products_count = (int)$pr_stmt->fetchColumn();

        // Unread notifications
        $sidebar_unread_notifs = get_unread_notifications_count($pdo, $farmer_id);
    } catch (PDOException $e) {
        error_log("Farmer sidebar metrics query error: " . $e->getMessage());
    }
}

$stall_display_name = !empty($sidebar_profile['stall_name']) ? $sidebar_profile['stall_name'] : ($farmer_name ? $farmer_name . "'s Farm" : 'My Farm Stall');
$stall_initial = strtoupper(mb_substr($stall_display_name, 0, 1));
$user_initial = $farmer_name ? strtoupper(mb_substr($farmer_name, 0, 1)) : 'F';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="MarketLink Farmer Producer Portal — Manage harvest produce, live pre-orders, stall pickup schedules, and customer feedback.">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <!-- MarketLink SaaS Design Tokens & Stylesheet -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">
</head>
<body class="bg-light">

<div class="farmer-app-wrapper">

    <!-- Mobile Sidebar Backdrop -->
    <div class="farmer-sidebar-backdrop" id="farmerSidebarBackdrop"></div>

    <!-- ── FARMER SAAS SIDEBAR ────────────────────────────────── -->
    <aside class="farmer-sidebar" id="farmerSidebar">
        <!-- Top Brand Identity -->
        <div class="farmer-sidebar-brand">
            <a href="<?= BASE_URL ?>farmer/dashboard.php" class="farmer-brand-title">
                <span class="brand-logo-icon"><i class="bi bi-flower1"></i></span>
                <span>Market<span class="text-primary">Link</span></span>
            </a>
            <span class="farmer-brand-badge">Producer</span>
        </div>

        <!-- Stall Profile Banner -->
        <div class="farmer-stall-banner">
            <div class="farmer-stall-avatar"><?= e($stall_initial) ?></div>
            <div style="min-width: 0; flex: 1 1 0%; overflow: hidden;">
                <div class="fw-bold small text-dark text-truncate" title="<?= e($stall_display_name) ?>">
                    <?= e($stall_display_name) ?>
                </div>
                <div class="d-flex align-items-center gap-1 text-muted text-truncate" style="font-size: 0.72rem;">
                    <i class="bi bi-patch-check-fill text-success flex-shrink-0"></i>
                    <span class="text-truncate">Verified Stall</span>
                </div>
            </div>
        </div>

        <!-- Scrollable Middle Navigation Content -->
        <div class="farmer-sidebar-content">
            <!-- Main Navigation Section -->
            <div class="farmer-nav-section-title">Farm Management</div>
            <ul class="farmer-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>farmer/dashboard.php" class="farmer-nav-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
                        <div class="farmer-nav-link-content">
                            <i class="bi bi-speedometer2 nav-icon"></i>
                            <span>Dashboard</span>
                        </div>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>farmer/products.php" class="farmer-nav-link <?= $active_nav === 'products' ? 'active' : '' ?>">
                        <div class="farmer-nav-link-content">
                            <i class="bi bi-box-seam nav-icon"></i>
                            <span>Harvest & Products</span>
                        </div>
                        <?php if ($sidebar_products_count > 0): ?>
                            <span class="badge <?= $active_nav === 'products' ? 'bg-white text-primary' : 'bg-light text-secondary border' ?> rounded-pill" style="font-size: 0.7rem;">
                                <?= $sidebar_products_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>farmer/orders.php" class="farmer-nav-link <?= $active_nav === 'orders' ? 'active' : '' ?>">
                        <div class="farmer-nav-link-content">
                            <i class="bi bi-receipt nav-icon"></i>
                            <span>Pre-Orders Queue</span>
                        </div>
                        <?php if ($sidebar_pending_orders > 0): ?>
                            <span class="badge bg-danger rounded-pill px-2" style="font-size: 0.7rem;">
                                <?= $sidebar_pending_orders ?> NEW
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>farmer/reviews.php" class="farmer-nav-link <?= $active_nav === 'reviews' ? 'active' : '' ?>">
                        <div class="farmer-nav-link-content">
                            <i class="bi bi-star nav-icon"></i>
                            <span>Customer Reviews</span>
                        </div>
                    </a>
                </li>
            </ul>

            <!-- Public Channels & Storefront -->
            <div class="farmer-nav-section-title">Storefront & Market</div>
            <ul class="farmer-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $farmer_id ?>" target="_blank" class="farmer-nav-link">
                        <div class="farmer-nav-link-content">
                            <i class="bi bi-box-arrow-up-right nav-icon"></i>
                            <span>View Public Stall</span>
                        </div>
                        <i class="bi bi-arrow-up-right small opacity-50"></i>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>index.php" class="farmer-nav-link">
                        <div class="farmer-nav-link-content">
                            <i class="bi bi-grid nav-icon"></i>
                            <span>Marketplace Home</span>
                        </div>
                    </a>
                </li>
            </ul>

            <!-- System & Account -->
            <div class="farmer-nav-section-title">Account</div>
            <ul class="farmer-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>farmer/profile.php" class="farmer-nav-link <?= $active_nav === 'profile' ? 'active' : '' ?>">
                        <div class="farmer-nav-link-content">
                            <i class="bi bi-shop nav-icon"></i>
                            <span>Stall Settings</span>
                        </div>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>notifications.php" class="farmer-nav-link <?= $active_nav === 'notifications' ? 'active' : '' ?>">
                        <div class="farmer-nav-link-content">
                            <i class="bi bi-bell nav-icon"></i>
                            <span>Notifications</span>
                        </div>
                        <?php if ($sidebar_unread_notifs > 0): ?>
                            <span class="badge bg-primary rounded-pill px-2" style="font-size: 0.7rem;">
                                <?= $sidebar_unread_notifs ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Pinned Bottom User Chip -->
        <div class="farmer-sidebar-footer">
            <div class="farmer-user-chip">
                <div class="d-flex align-items-center gap-2" style="min-width: 0; flex: 1 1 0%; overflow: hidden;">
                    <div class="farmer-user-avatar"><?= e($user_initial) ?></div>
                    <div style="min-width: 0; flex: 1 1 0%; overflow: hidden;">
                        <div class="farmer-user-name text-truncate" title="<?= e($farmer_name) ?>"><?= e($farmer_name) ?></div>
                        <div class="farmer-user-role text-muted text-truncate">Farmer Account</div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>auth/logout.php" class="farmer-logout-btn" title="Log Out">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ── MAIN WRAPPER ────────────────────────────────────────── -->
    <div class="farmer-main-wrapper">

        <!-- Top Application Bar -->
        <header class="farmer-topbar">
            <div class="d-flex align-items-center gap-3">
                <!-- Mobile Sidebar Toggle Hamburger -->
                <button class="btn btn-light btn-sm d-lg-none p-2 border" id="farmerSidebarToggle" type="button" aria-label="Toggle Navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>

                <!-- Breadcrumb context -->
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1" style="font-size: 0.75rem;">
                        <i class="bi bi-shop me-1"></i> Farm Portal
                    </span>
                    <i class="bi bi-chevron-right small text-muted" style="font-size: 0.7rem;"></i>
                    <span class="small fw-bold text-secondary"><?= e(ucfirst($active_nav)) ?></span>
                </div>
            </div>

            <!-- Topbar Actions -->
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>farmer/products.php?action=add" class="btn btn-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle"></i> Add Produce
                </a>

                <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $farmer_id ?>" target="_blank" class="btn btn-outline-secondary btn-sm d-none d-md-inline-flex align-items-center gap-1" title="Preview your public customer stall">
                    <i class="bi bi-eye"></i> Storefront
                </a>

                <!-- Notification Bell -->
                <a href="<?= BASE_URL ?>notifications.php" class="btn btn-light btn-sm border position-relative p-2" title="Notifications">
                    <i class="bi bi-bell text-secondary"></i>
                    <?php if ($sidebar_unread_notifs > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                            <?= min($sidebar_unread_notifs, 9) ?><?= $sidebar_unread_notifs > 9 ? '+' : '' ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown Menu -->
                <div class="dropdown">
                    <button class="btn btn-light btn-sm border d-flex align-items-center gap-2 p-1 pe-2 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;"><?= e($user_initial) ?></div>
                        <span class="small fw-semibold d-none d-sm-inline text-dark"><?= e($farmer_name) ?></span>
                        <i class="bi bi-chevron-down small text-muted" style="font-size: 0.65rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2" style="border-radius: 12px; min-width: 200px;">
                        <li class="px-3 py-1 border-bottom mb-1">
                            <div class="small fw-bold text-dark"><?= e($stall_display_name) ?></div>
                            <div class="text-muted" style="font-size: 0.72rem;"><?= e($_SESSION['email'] ?? 'Farmer Account') ?></div>
                        </li>
                        <li><a class="dropdown-item small py-2" href="<?= BASE_URL ?>farmer/profile.php"><i class="bi bi-gear me-2 text-muted"></i>Stall Settings</a></li>
                        <li><a class="dropdown-item small py-2" href="<?= BASE_URL ?>farmer/products.php"><i class="bi bi-box-seam me-2 text-muted"></i>My Produce Catalog</a></li>
                        <li><a class="dropdown-item small py-2" href="<?= BASE_URL ?>farmer/orders.php"><i class="bi bi-receipt me-2 text-muted"></i>Incoming Orders</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item small py-2" href="<?= BASE_URL ?>index.php"><i class="bi bi-house me-2 text-muted"></i>Marketplace Home</a></li>
                        <li><a class="dropdown-item small py-2 text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="farmer-content-body">
            <?php
            // Flash notification alerts
            $flash = get_flash();
            if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-xs border-0 mb-4" role="alert" style="border-radius: var(--radius-md);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill') ?>"></i>
                        <div><?= e($flash['message']) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['is_pending_approval'])): ?>
                <div class="alert alert-warning border-warning d-flex align-items-center gap-3 mb-4 shadow-xs" role="alert" style="border-radius: var(--radius-md);">
                    <i class="bi bi-clock-history fs-3 text-warning flex-shrink-0"></i>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">Stall Approval In Progress</h6>
                        <p class="small mb-0">Your farmer stall registration is currently being reviewed by the platform administrator. You can prepare and organize your harvest catalog now; it will go live to local market shoppers as soon as your account is approved.</p>
                    </div>
                </div>
            <?php endif; ?>
