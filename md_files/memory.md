# memory.md — MarketLink

Update this file regularly as work happens.

## Current status
- Phase: **Phase 7 Complete — Submission Ready (SaaS Redesign & Clean Branding)** 🎉
- Last file worked on: `auth/login.php`, `auth/register.php`, `about.php`
- Last updated: 2026-09-24

## Completed
- **Phase 1: Auth & Architecture Foundation**
  - Database schema (`sql/schema.sql`) and seed data (`sql/seed-data.sql`) containing complete InnoDB tables and test credentials.
  - PDO connection configuration (`config/db.php`, `config/db.example.php`) and site constants (`config/constants.php`) with PKR currency standard.
  - Procedural helper library (`includes/functions.php`) with CSRF protection, session flash messages, status badge helpers, and notification utilities.
  - Role-based route guard (`includes/auth-check.php`) enforcing real-time user status (active/pending/suspended) and row-level security.
  - High-end SaaS design system & stylesheet (`assets/css/style.css`) with responsive split-screen auth layouts and earthy design tokens.
  - Shared responsive header and footer (`includes/header.php`, `includes/footer.php`) with dynamic role-aware navigation, clean Dashboard shortcut button, non-conflicting z-index layering, centered icon buttons, and glassmorphism navbar.
  - SaaS Notifications Center (`notifications.php`) with dynamic category avatars (Orders, Accounts, Announcements, Reviews), live filter pills, relative timestamps, single & bulk read/clear controls, and helpful side widgets.
  - Customer & Farmer registration (`auth/register.php`), unified login (`auth/login.php`), admin login (`auth/admin-login.php`), logout (`auth/logout.php`), and tokenized password reset flow (`auth/forgot-password.php`, `auth/reset-password.php`) featuring 2-column SaaS split-screen layouts with brand showcases and interactive show/hide password toggle buttons on all password fields.
  - Landing page (`index.php`) and role dashboard skeletons.

- **Phase 2: Farmer Module (SaaS Portal Redesign)**
  - Dedicated Farmer Portal Architecture (`farmer/includes/header.php`, `farmer/includes/footer.php`): Full-featured responsive sidebar navigation, top app bar with breadcrumb context, real-time counter badges (pending pre-orders, active inventory, unread notifications), mobile drawer toggler, and public stall quick preview.
  - Farmer Hub Dashboard (`farmer/dashboard.php`): Modern SaaS 4-card metric grid (Harvest Catalog, Pending Action Pre-Orders with pulse badges, PKR Completed Revenue, Average Stall Rating), recent pre-orders queue, and quick operations toolbar.
  - Product CRUD Management (`farmer/products.php`): Inventory table with live stock badges, PKR price per unit, instant sold-out toggle, add/edit harvest form with photo uploads, and weekend bulk restock modal tool.
  - Pre-Order Workflow (`farmer/orders.php`): Status pill tabs with dynamic counts, interactive queue, order detail drawer, 1-click status transitions (`placed` -> `accepted` / `declined` -> `ready` -> `completed`), automatic inventory restore on decline, and instant customer notifications.
  - Stall Profile & Location Pin (`farmer/profile.php`): Stall branding, operating days, pickup window times, order cutoff hours, interactive Leaflet + OpenStreetMap pin picker, and market association checkboxes.
  - Review Management (`farmer/reviews.php`): Reputation analytics card with star distribution breakdown (5★ to 1★), verified customer feedback stream, and inline public stall reply tool.

- **Phase 3: Customer Module**
  - Customer Hub (`customer/dashboard.php`): SaaS profile greeting bar, ready-for-pickup alert banner, 4 KPI metric cards (Total Pre-Orders, In Progress, Ready, Farm Spend), recent orders feed, and saved favorites quick reorder widget.
  - Farmers Market Discovery (`customer/browse-markets.php`): Interactive OpenStreetMap directory with Leaflet markers, operating days/hours filters, and list of attending farmer stalls.
  - Farmer Stall Detail (`customer/farmer-detail.php`): Full stall branding, location map, weekly harvest catalog, and customer reviews.
  - Harvest Produce Catalog (`customer/browse-products.php`): Search and multi-criteria filters (category, market, farmer, in-stock only, price sort).
  - Produce Detail View (`customer/product-detail.php`): High-res photo view, harvest description, stock availability badge, customer reviews, and quantity selector.
  - Pre-Order Basket (`customer/cart.php`): Item review, live stock capping, quantity adjustments, and subtotal calculation.
  - Multi-Stall Pre-Order Checkout (`customer/checkout.php`): Grouped orders per stall with pickup date/time slot selection, race-condition transaction safety, and instant farmer in-app notifications.
  - Order Tracking & Reorder (`customer/orders.php`): SaaS-level metric cards, interactive pill filter toolbar (All, In Progress, Ready, Completed, Cancelled), instant search bar, detailed itemized drawer breakdown, and one-click basket reordering.
  - Saved Favorites (`customer/favorites.php`): Manage favorite farmer stalls and go-to produce items.
  - Reviews & Star Ratings (`customer/reviews.php`): Verified review system enforcing completed order pickup eligibility with helpful guidance card when no orders are completed, dropdown filtering of completed stalls, and review feed displaying farmer stall replies.

- **Phase 4: Admin Module**
  - Platform Administrator Hub (`admin/dashboard.php`): Live system counts and rapid pending farmer review queue.
  - Farmer Stall Management (`admin/manage-farmers.php`): Approve, suspend, and reactivate farmer accounts with stall profile inspection.
  - Customer Account Management (`admin/manage-customers.php`): Shopper account status controls and pre-order spend tracking.
  - Market Directory CRUD (`admin/manage-markets.php`): Add/edit/delete physical farmers markets with interactive Leaflet map pin placement.
  - Content Moderation & Broadcast Center (`admin/moderation.php`): Review deletion, product visibility overrides, and platform-wide announcement broadcasts.
  - Platform Analytics & Reports (`admin/reports.php`): Sales volumes, completed order rates, market participation breakdown, and printable summaries.

- **Phase 5: Cross-Cutting & Polish**
  - In-App Notifications Center (`notifications.php`): Real-time notification feed with mark-as-read and dismiss actions.
  - About Us (`about.php`): Project overview, TechWiz 7 Aptech architecture defense, zero-food-waste mission.
  - Contact Us (`contact.php`): Inquiry form, support details, and central support hub Leaflet location map.
  - Full codebase validation: 38 PHP scripts with 100% clean linting and strict adherence to `design.md`, `Security-Access.md`, and `rules.md`.

- **Phase 7: Submission Prep**
  - `INSTALL.md`: Full installation guide — XAMPP setup, schema import, seed import, FK-safe re-seeding note, role demo walkthrough, troubleshooting table.
  - `md_files/features-checklist.md`: All Phases 1–5 features marked ✅ complete.
  - PHP lint validation: All 38 `.php` files pass — zero syntax errors.
  - SQL: `seed-data.sql` uses `SET FOREIGN_KEY_CHECKS = 0` + `DELETE FROM` (safe to re-run; avoids the TRUNCATE FK constraint error in phpMyAdmin).

## In progress
- Nothing. Project is submission-ready.

## Next up
- Submission deliverables: verify SQL seed credentials in phpMyAdmin and walk through user roles for Aptech competition presentation.

## Test Credentials
- **Administrator:** `admin@marketlink.test` / `Admin@123`
- **Farmer (Green Valley):** `greenvalley@marketlink.test` / `Farmer@123`
- **Customer (Sarah Jenkins):** `sarah.customer@marketlink.test` / `Customer@123`
- **Customer (David Miller):** `david.customer@marketlink.test` / `Customer@123`
