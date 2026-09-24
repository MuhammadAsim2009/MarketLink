<?php
/**
 * MarketLink - Shared Header
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title    = isset($page_title) ? $page_title . ' — ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$current_role  = get_logged_in_user_role();
$current_name  = get_logged_in_user_name();
$user_initial  = $current_name ? strtoupper(mb_substr($current_name, 0, 1)) : 'U';
$unread_notifs = 0;
if (is_logged_in() && isset($pdo)) {
    $unread_notifs = get_unread_notifications_count($pdo, get_logged_in_user_id());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="MarketLink — Farm-fresh produce directly from local farmers. Browse markets, reserve pre-orders, and pick up at your weekend stall.">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <!-- MarketLink Design System -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css">
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────────── -->
<nav class="navbar-ml" id="mainNav">
    <div class="container">

        <!-- Brand -->
        <a class="ml-brand" href="<?= BASE_URL ?>">
            <span class="brand-icon"><i class="bi bi-flower1"></i></span>
            Market<span class="accent">Link</span>
        </a>

        <!-- Desktop Nav Links -->
        <ul class="ml-nav">
            <li>
                <a class="nav-link <?= (!isset($active_nav) || $active_nav === 'home') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>">
                    Home
                </a>
            </li>
            <li>
                <a class="nav-link <?= (isset($active_nav) && $active_nav === 'markets') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>customer/browse-markets.php">
                    Markets
                </a>
            </li>
            <li>
                <a class="nav-link <?= (isset($active_nav) && $active_nav === 'products') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>customer/browse-products.php">
                    Products
                </a>
            </li>
            <li>
                <a class="nav-link <?= (isset($active_nav) && $active_nav === 'about') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>about.php">
                    About
                </a>
            </li>
            <li>
                <a class="nav-link <?= (isset($active_nav) && $active_nav === 'contact') ? 'active' : '' ?>"
                   href="<?= BASE_URL ?>contact.php">
                    Contact
                </a>
            </li>
        </ul>

        <!-- Desktop Actions -->
        <div class="ml-nav-actions">
            <?php if (!is_logged_in()): ?>
                <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-primary btn-sm hide-mobile">
                    Sign in
                </a>
                <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary btn-sm">
                    Get started
                </a>

            <?php else: ?>
                <!-- Notification Bell -->
                <a href="<?= BASE_URL ?>notifications.php" class="btn-icon" title="Notifications" style="position:relative;">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread_notifs > 0): ?>
                        <span class="notif-count"><?= min($unread_notifs, 9) ?><?= $unread_notifs > 9 ? '+' : '' ?></span>
                    <?php endif; ?>
                </a>

                <!-- Cart (customers only) -->
                <?php if ($current_role === ROLE_CUSTOMER): ?>
                    <?php $cart_count = !empty($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?>
                    <a href="<?= BASE_URL ?>customer/cart.php" class="btn-icon" title="Cart" style="position:relative;">
                        <i class="bi bi-bag"></i>
                        <span id="cartCountBadge" class="notif-count" style="<?= $cart_count > 0 ? '' : 'display:none;' ?>"><?= $cart_count > 9 ? '9+' : $cart_count ?></span>
                    </a>
                <?php endif; ?>

                <!-- Dashboard shortcut -->
                <?php if ($current_role === ROLE_ADMIN): ?>
                    <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-outline-primary btn-sm hide-mobile">
                        <i class="bi bi-speedometer2"></i> Admin
                    </a>
                <?php elseif ($current_role === ROLE_FARMER): ?>
                    <a href="<?= BASE_URL ?>farmer/dashboard.php" class="btn btn-outline-primary btn-sm hide-mobile">
                        <i class="bi bi-shop"></i> Hub
                    </a>
                <?php elseif ($current_role === ROLE_CUSTOMER): ?>
                    <a href="<?= BASE_URL ?>customer/dashboard.php" class="btn btn-outline-primary btn-sm hide-mobile">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                <?php endif; ?>

                <!-- User dropdown -->
                <div class="dropdown">
                    <div class="user-chip" onclick="this.closest('.dropdown').classList.toggle('open')">
                        <div class="user-avatar"><?= e($user_initial) ?></div>
                        <span class="user-name"><?= e($current_name) ?></span>
                        <i class="bi bi-chevron-down" style="font-size:.65rem; opacity:.5; flex-shrink:0;"></i>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-header"><?= ucfirst(e($current_role)) ?> account</div>

                        <?php if ($current_role === ROLE_CUSTOMER): ?>
                            <a class="dropdown-item" href="<?= BASE_URL ?>customer/orders.php">
                                <i class="bi bi-bag-check"></i> My Orders
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>customer/favorites.php">
                                <i class="bi bi-heart"></i> Favorites
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>customer/reviews.php">
                                <i class="bi bi-star"></i> Reviews
                            </a>
                        <?php elseif ($current_role === ROLE_FARMER): ?>
                            <a class="dropdown-item" href="<?= BASE_URL ?>farmer/products.php">
                                <i class="bi bi-box-seam"></i> My Products
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>farmer/orders.php">
                                <i class="bi bi-receipt"></i> Orders
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>farmer/profile.php">
                                <i class="bi bi-shop"></i> Stall Profile
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>farmer/reviews.php">
                                <i class="bi bi-star"></i> Reviews
                            </a>
                        <?php elseif ($current_role === ROLE_ADMIN): ?>
                            <a class="dropdown-item" href="<?= BASE_URL ?>admin/manage-farmers.php">
                                <i class="bi bi-people"></i> Farmers
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>admin/manage-markets.php">
                                <i class="bi bi-geo"></i> Markets
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>admin/reports.php">
                                <i class="bi bi-graph-up"></i> Reports
                            </a>
                        <?php endif; ?>

                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="<?= BASE_URL ?>notifications.php">
                            <i class="bi bi-bell"></i> Notifications
                            <?php if ($unread_notifs > 0): ?>
                                <span class="badge badge-danger ms-auto"><?= $unread_notifs ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="dropdown-item danger" href="<?= BASE_URL ?>auth/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Sign out
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Hamburger -->
            <button class="ml-toggler" id="navToggle" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Nav -->
<div class="ml-mobile-nav" id="mobileNav">
    <a class="nav-link" href="<?= BASE_URL ?>"><i class="bi bi-house-door"></i> Home</a>
    <a class="nav-link" href="<?= BASE_URL ?>customer/browse-markets.php"><i class="bi bi-geo-alt"></i> Markets</a>
    <a class="nav-link" href="<?= BASE_URL ?>customer/browse-products.php"><i class="bi bi-basket"></i> Products</a>
    <a class="nav-link" href="<?= BASE_URL ?>about.php"><i class="bi bi-info-circle"></i> About</a>
    <a class="nav-link" href="<?= BASE_URL ?>contact.php"><i class="bi bi-envelope"></i> Contact</a>
    <?php if (!is_logged_in()): ?>
        <div class="d-flex gap-2 mt-3 pt-3" style="border-top:1px solid var(--border);">
            <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-primary btn-sm flex-fill">Sign in</a>
            <a href="<?= BASE_URL ?>auth/register.php" class="btn btn-primary btn-sm flex-fill">Get started</a>
        </div>
    <?php endif; ?>
</div>

<!-- Flash Alerts -->
<div class="flash-wrapper" id="flashWrapper">
    <?php
    $flash = get_flash();
    if ($flash): ?>
        <div class="flash-alert <?= e($flash['type']) ?>" role="alert">
            <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill') ?> flash-icon"></i>
            <span><?= e($flash['message']) ?></span>
            <span class="flash-close" onclick="this.closest('.flash-alert').remove()">&#x2715;</span>
        </div>
    <?php endif; ?>
</div>

<script>
// Navbar scroll effect
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 10);
}, { passive: true });

// Mobile nav toggle
document.getElementById('navToggle').addEventListener('click', () => {
    document.getElementById('mobileNav').classList.toggle('open');
});

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    document.querySelectorAll('.dropdown.open').forEach(d => {
        if (!d.contains(e.target)) d.classList.remove('open');
    });
});

// Auto-dismiss flash alerts
setTimeout(() => {
    document.querySelectorAll('.flash-alert').forEach(a => {
        a.style.transition = 'opacity .4s';
        a.style.opacity = '0';
        setTimeout(() => a.remove(), 400);
    });
}, 5000);
</script>

<main class="main-content">
