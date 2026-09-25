<?php
/**
 * MarketLink - Admin Portal Shared Header & SaaS Sidebar Layout
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$required_role = 'admin';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';

$admin_id     = get_logged_in_user_id();
$admin_name   = get_logged_in_user_name();
$user_initial = $admin_name ? strtoupper(mb_substr($admin_name, 0, 1)) : 'A';
$page_title   = isset($page_title) ? $page_title . ' — Admin Portal' : 'Admin Portal — MarketLink';
$active_nav   = $active_nav ?? 'dashboard';

// Fetch live admin metrics for sidebar badges
$sidebar_pending_farmers = 0;
$sidebar_total_markets   = 0;
$sidebar_unread_notifs   = 0;

if (isset($pdo) && $admin_id) {
    try {
        // Fresh admin name & email
        $u_stmt = $pdo->prepare("SELECT name, email FROM users WHERE user_id = :uid LIMIT 1");
        $u_stmt->execute([':uid' => $admin_id]);
        $u_data = $u_stmt->fetch();
        if ($u_data && !empty($u_data['name'])) {
            $admin_name = $u_data['name'];
            $_SESSION['user_name'] = $admin_name;
        }

        // Pending farmers count
        $pf_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'farmer' AND status = 'pending'");
        $sidebar_pending_farmers = (int)$pf_stmt->fetchColumn();

        // Markets count
        $m_stmt = $pdo->query("SELECT COUNT(*) FROM markets");
        $sidebar_total_markets = (int)$m_stmt->fetchColumn();

        // Unread notifications
        $sidebar_unread_notifs = get_unread_notifications_count($pdo, $admin_id);
    } catch (PDOException $e) {
        error_log("Admin sidebar metrics query error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="MarketLink Platform Administration Portal — Supervise farm stalls, approve registrations, manage local markets, and inspect analytics.">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <!-- MarketLink SaaS Design Tokens & Stylesheet -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>css/style.css?v=<?= time() ?>">

    <style>
    /* Admin Portal SaaS Sidebar & Layout Direct Styles */
    .admin-app-wrapper {
        display: flex;
        min-height: 100vh;
        background-color: #F8FAFC;
        color: var(--text, #1A2E22);
        font-family: var(--font-sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
        position: relative;
    }
    .admin-sidebar {
        width: 270px;
        max-width: 270px;
        background: #FFFFFF;
        border-right: 1px solid #E2E8F0;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 1045;
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        box-sizing: border-box;
    }
    .admin-sidebar * {
        box-sizing: border-box;
    }
    .admin-sidebar-content {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        padding-bottom: 0.75rem;
    }
    .admin-sidebar-content::-webkit-scrollbar {
        width: 4px;
    }
    .admin-sidebar-content::-webkit-scrollbar-track {
        background: transparent;
    }
    .admin-sidebar-content::-webkit-scrollbar-thumb {
        background: #E2E8F0;
        border-radius: 4px;
    }
    .admin-sidebar-brand {
        height: 72px;
        padding: 0 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #F1F5F9;
        flex-shrink: 0;
        width: 100%;
        overflow: hidden;
        gap: 0.5rem;
    }
    .admin-brand-title {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        text-decoration: none;
        font-family: var(--font-display, inherit);
        font-weight: 800;
        font-size: 1.15rem;
        color: var(--text, #1A2E22);
    }
    .admin-brand-title .brand-logo-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
        color: #38BDF8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.35);
    }
    .admin-brand-badge {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        background: rgba(15, 23, 42, 0.08);
        color: #0F172A;
        padding: 2px 7px;
        border-radius: 4px;
        letter-spacing: 0.05em;
        border: 1px solid rgba(15, 23, 42, 0.12);
    }
    .admin-profile-banner {
        padding: 0.9rem 1.25rem;
        background: #F8FAFC;
        border-bottom: 1px solid #F1F5F9;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        overflow: hidden;
        min-width: 0;
        flex-shrink: 0;
    }
    .admin-profile-avatar {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #0F172A;
        color: #38BDF8;
        font-weight: 700;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        border: 1px solid rgba(15, 23, 42, 0.15);
    }
    .admin-nav-section-title {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94A3B8;
        padding: 1rem 1.25rem 0.35rem;
    }
    .admin-nav-list {
        list-style: none;
        padding: 0.35rem 0.75rem;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .admin-nav-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.62rem 0.85rem;
        color: #475569;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.15s ease-in-out;
        text-decoration: none;
    }
    .admin-nav-link-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .admin-nav-link .nav-icon {
        font-size: 1.15rem;
        width: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748B;
        transition: color 0.15s ease-in-out;
    }
    .admin-nav-link:hover {
        color: #0F172A;
        background: #F1F5F9;
    }
    .admin-nav-link:hover .nav-icon {
        color: #0F172A;
    }
    .admin-nav-link.active {
        color: #FFFFFF;
        background: #0F172A;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.25);
    }
    .admin-nav-link.active .nav-icon {
        color: #38BDF8;
    }
    .admin-sidebar-footer {
        margin-top: auto;
        padding: 0.85rem 1rem;
        border-top: 1px solid #E2E8F0;
        background: #FFFFFF;
        flex-shrink: 0;
        position: sticky;
        bottom: 0;
        z-index: 10;
        box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.02);
    }
    .admin-user-chip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.55rem 0.65rem;
        border-radius: 8px;
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        gap: 0.5rem;
        width: 100%;
        min-width: 0;
        overflow: hidden;
    }
    .admin-user-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #0F172A;
        color: #38BDF8;
        font-weight: 700;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .admin-user-name {
        font-size: 0.825rem;
        font-weight: 600;
        color: #1E293B;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }
    .admin-user-role {
        font-size: 0.7rem;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }
    .admin-logout-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        color: #EF4444;
        background: #FEE2E2;
        text-decoration: none;
        transition: all 0.15s ease-in-out;
        flex-shrink: 0;
        border: none;
    }
    .admin-logout-btn:hover {
        background: #EF4444;
        color: #FFFFFF;
        transform: scale(1.05);
    }
    .admin-main-wrapper {
        flex: 1 1 0%;
        margin-left: 270px;
        display: flex;
        flex-direction: column;
        min-width: 0;
        background: #F8FAFC;
        min-height: 100vh;
    }
    .admin-topbar {
        height: 72px;
        background: #FFFFFF;
        border-bottom: 1px solid #E2E8F0;
        padding: 0 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 1030;
    }
    .admin-content-body {
        padding: 2rem;
        flex: 1 1 auto;
    }
    .admin-sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(2px);
        z-index: 1040;
        display: none;
        opacity: 0;
        transition: opacity 0.25s ease-in-out;
    }
    .admin-sidebar-backdrop.show {
        display: block;
        opacity: 1;
    }
    @media (max-width: 991.98px) {
        .admin-sidebar {
            transform: translateX(-100%);
        }
        .admin-sidebar.open {
            transform: translateX(0);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
        }
        .admin-main-wrapper {
            margin-left: 0;
        }
        .admin-topbar {
            padding: 0 1.25rem;
        }
        .admin-content-body {
            padding: 1.25rem 1rem;
        }
    }
    </style>
</head>
<body class="bg-light">

<div class="admin-app-wrapper">

    <!-- Mobile Sidebar Backdrop -->
    <div class="admin-sidebar-backdrop" id="adminSidebarBackdrop"></div>

    <!-- ── ADMIN SAAS SIDEBAR ─────────────────────────────────── -->
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Top Brand Identity -->
        <div class="admin-sidebar-brand">
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="admin-brand-title">
                <span class="brand-logo-icon"><i class="bi bi-shield-check"></i></span>
                <span>Market<span class="text-primary">Link</span></span>
            </a>
            <span class="admin-brand-badge">Admin</span>
        </div>

        <!-- Admin Identity Banner -->
        <div class="admin-profile-banner">
            <div class="admin-profile-avatar"><i class="bi bi-person-gear"></i></div>
            <div style="min-width: 0; flex: 1 1 0%; overflow: hidden;">
                <div class="fw-bold small text-dark text-truncate" title="<?= e($admin_name) ?>">
                    <?= e($admin_name) ?>
                </div>
                <div class="d-flex align-items-center gap-1 text-muted text-truncate" style="font-size: 0.72rem;">
                    <i class="bi bi-patch-check-fill text-primary flex-shrink-0"></i>
                    <span class="text-truncate">Platform Controller</span>
                </div>
            </div>
        </div>

        <!-- Scrollable Middle Navigation Content -->
        <div class="admin-sidebar-content">
            <!-- Core Management Section -->
            <div class="admin-nav-section-title">Core Operations</div>
            <ul class="admin-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>admin/dashboard.php" class="admin-nav-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
                        <div class="admin-nav-link-content">
                            <i class="bi bi-speedometer2 nav-icon"></i>
                            <span>Dashboard</span>
                        </div>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/manage-farmers.php" class="admin-nav-link <?= $active_nav === 'farmers' ? 'active' : '' ?>">
                        <div class="admin-nav-link-content">
                            <i class="bi bi-shop nav-icon"></i>
                            <span>Farmers & Stalls</span>
                        </div>
                        <?php if ($sidebar_pending_farmers > 0): ?>
                            <span class="badge bg-warning text-dark rounded-pill px-2" style="font-size: 0.7rem;">
                                <?= $sidebar_pending_farmers ?> Pending
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/manage-customers.php" class="admin-nav-link <?= $active_nav === 'customers' ? 'active' : '' ?>">
                        <div class="admin-nav-link-content">
                            <i class="bi bi-people nav-icon"></i>
                            <span>Customer Accounts</span>
                        </div>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/manage-markets.php" class="admin-nav-link <?= $active_nav === 'markets' ? 'active' : '' ?>">
                        <div class="admin-nav-link-content">
                            <i class="bi bi-geo-alt nav-icon"></i>
                            <span>Farmers Markets</span>
                        </div>
                        <?php if ($sidebar_total_markets > 0): ?>
                            <span class="badge <?= $active_nav === 'markets' ? 'bg-white text-dark' : 'bg-light text-secondary border' ?> rounded-pill" style="font-size: 0.7rem;">
                                <?= $sidebar_total_markets ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <!-- Governance & Insights Section -->
            <div class="admin-nav-section-title">Governance & Analytics</div>
            <ul class="admin-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>admin/moderation.php" class="admin-nav-link <?= $active_nav === 'moderation' ? 'active' : '' ?>">
                        <div class="admin-nav-link-content">
                            <i class="bi bi-megaphone nav-icon"></i>
                            <span>Moderation & Notice</span>
                        </div>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>admin/reports.php" class="admin-nav-link <?= $active_nav === 'reports' ? 'active' : '' ?>">
                        <div class="admin-nav-link-content">
                            <i class="bi bi-graph-up-arrow nav-icon"></i>
                            <span>Analytics & Reports</span>
                        </div>
                    </a>
                </li>
            </ul>

            <!-- System & Public Channels -->
            <div class="admin-nav-section-title">System & Storefront</div>
            <ul class="admin-nav-list">
                <li>
                    <a href="<?= BASE_URL ?>notifications.php" class="admin-nav-link <?= $active_nav === 'notifications' ? 'active' : '' ?>">
                        <div class="admin-nav-link-content">
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
                <li>
                    <a href="<?= BASE_URL ?>index.php" target="_blank" class="admin-nav-link">
                        <div class="admin-nav-link-content">
                            <i class="bi bi-box-arrow-up-right nav-icon"></i>
                            <span>Public Marketplace</span>
                        </div>
                        <i class="bi bi-arrow-up-right small opacity-50"></i>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Pinned Bottom User Chip -->
        <div class="admin-sidebar-footer">
            <div class="admin-user-chip">
                <div class="d-flex align-items-center gap-2" style="min-width: 0; flex: 1 1 0%; overflow: hidden;">
                    <div class="admin-user-avatar"><?= e($user_initial) ?></div>
                    <div style="min-width: 0; flex: 1 1 0%; overflow: hidden;">
                        <div class="admin-user-name text-truncate" title="<?= e($admin_name) ?>"><?= e($admin_name) ?></div>
                        <div class="admin-user-role text-muted text-truncate">Administrator</div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>auth/logout.php" class="admin-logout-btn" title="Log Out">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ── MAIN WRAPPER ────────────────────────────────────────── -->
    <div class="admin-main-wrapper">

        <!-- Top Application Bar -->
        <header class="admin-topbar">
            <div class="d-flex align-items-center gap-3">
                <!-- Mobile Sidebar Toggle Hamburger -->
                <button class="btn btn-light btn-sm d-lg-none p-2 border" id="adminSidebarToggle" type="button" aria-label="Toggle Navigation">
                    <i class="bi bi-list fs-5"></i>
                </button>

                <!-- Breadcrumb context -->
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-dark-subtle text-dark fw-semibold px-2 py-1" style="font-size: 0.75rem;">
                        <i class="bi bi-shield-check me-1 text-primary"></i> Admin Center
                    </span>
                    <i class="bi bi-chevron-right small text-muted" style="font-size: 0.7rem;"></i>
                    <span class="small fw-bold text-secondary"><?= e(ucfirst($active_nav)) ?></span>
                </div>
            </div>

            <!-- Topbar Actions -->
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1 rounded-3">
                    <i class="bi bi-plus-circle"></i> Add Market
                </a>

                <a href="<?= BASE_URL ?>admin/moderation.php" class="btn btn-outline-secondary btn-sm d-none d-md-inline-flex align-items-center gap-1 rounded-3" title="Broadcast system announcement">
                    <i class="bi bi-broadcast"></i> Broadcast
                </a>

                <!-- Notification Bell -->
                <a href="<?= BASE_URL ?>notifications.php" class="btn btn-light btn-sm border position-relative p-2 rounded-3" title="Notifications">
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
                        <div class="admin-user-avatar" style="width: 28px; height: 28px; font-size: 0.75rem;"><?= e($user_initial) ?></div>
                        <span class="small fw-semibold d-none d-sm-inline text-dark"><?= e($admin_name) ?></span>
                        <i class="bi bi-chevron-down small text-muted" style="font-size: 0.65rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2" style="border-radius: 12px; min-width: 210px;">
                        <li class="px-3 py-1 border-bottom mb-1">
                            <div class="small fw-bold text-dark"><?= e($admin_name) ?></div>
                            <div class="text-muted" style="font-size: 0.72rem;"><?= e($_SESSION['email'] ?? 'Administrator') ?></div>
                        </li>
                        <li><a class="dropdown-item small py-2" href="<?= BASE_URL ?>admin/dashboard.php"><i class="bi bi-speedometer2 me-2 text-muted"></i>Dashboard Hub</a></li>
                        <li><a class="dropdown-item small py-2" href="<?= BASE_URL ?>admin/manage-farmers.php"><i class="bi bi-shop me-2 text-muted"></i>Farmers & Stalls</a></li>
                        <li><a class="dropdown-item small py-2" href="<?= BASE_URL ?>admin/reports.php"><i class="bi bi-graph-up me-2 text-muted"></i>Analytics & Reports</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item small py-2" href="<?= BASE_URL ?>index.php" target="_blank"><i class="bi bi-box-arrow-up-right me-2 text-muted"></i>Marketplace Home</a></li>
                        <li><a class="dropdown-item small py-2 text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="admin-content-body">
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
