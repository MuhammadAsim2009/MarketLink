# memory.md — MarketLink

Update this file regularly as work happens.

## Current status
- Phase: All Core Phases (1–5) Complete & Fully Functional -> Ready for Phase 7 (Submission Prep / Video Demo)
- Last file worked on: `about.php`, `contact.php`, `notifications.php`, `includes/header.php`, `includes/footer.php`
- Last updated: 2026-09-24

## Completed
- **Phase 1: Auth & Architecture Foundation**
  - Database schema (`sql/schema.sql`) and seed data (`sql/seed-data.sql`) containing complete InnoDB tables and test credentials.
  - PDO connection configuration (`config/db.php`, `config/db.example.php`) and site constants (`config/constants.php`).
  - Procedural helper library (`includes/functions.php`) with CSRF protection, session flash messages, status badge helpers, and notification utilities.
  - Role-based route guard (`includes/auth-check.php`) enforcing real-time user status (active/pending/suspended) and row-level security.
  - Custom design tokens & stylesheet (`assets/css/style.css`) matching `design.md` color scheme (#3D8B47, #2A6334, #E8935A, #FBF9F4, #2B2B28) and typography.
  - Shared responsive header and footer (`includes/header.php`, `includes/footer.php`) with dynamic role-aware navigation.
  - Customer & Farmer registration (`auth/register.php`), unified login (`auth/login.php`), admin login (`auth/admin-login.php`), logout (`auth/logout.php`), and tokenized password reset flow (`auth/forgot-password.php`, `auth/reset-password.php`) featuring interactive show/hide password toggle buttons on all password fields.
  - Landing page (`index.php`) and role dashboard skeletons.

- **Phase 2: Farmer Module**
  - Product CRUD Management (`farmer/products.php`): Full add, edit, delete with stock validation, category filtering, search, image upload/URL, one-click sold out toggling, and weekend bulk restock tool.
  - Pre-Order Workflow (`farmer/orders.php`): Incoming order queue, status transitions (`placed` -> `accepted` / `declined` -> `ready` -> `completed`), automatic stock restoration upon decline, and instant customer in-app notifications.
  - Stall Profile & Location Pin (`farmer/profile.php`): Stall branding, contact details, operating days, pickup windows (start, end, cutoff hours), interactive Leaflet + OpenStreetMap pin picker, and market association checkboxes.
  - Review Management (`farmer/reviews.php`): Average stall ratings, review feed, and public farmer reply workflow with customer notification triggers.

- **Phase 3: Customer Module**
  - Farmers Market Discovery (`customer/browse-markets.php`): Interactive OpenStreetMap directory with Leaflet markers, operating days/hours filters, and list of attending farmer stalls.
  - Farmer Stall Detail (`customer/farmer-detail.php`): Full stall branding, location map, weekly harvest catalog, and customer reviews.
  - Harvest Produce Catalog (`customer/browse-products.php`): Search and multi-criteria filters (category, market, farmer, in-stock only, price sort).
  - Produce Detail View (`customer/product-detail.php`): High-res photo view, harvest description, stock availability badge, customer reviews, and quantity selector.
  - Pre-Order Basket (`customer/cart.php`): Item review, live stock capping, quantity adjustments, and subtotal calculation.
  - Multi-Stall Pre-Order Checkout (`customer/checkout.php`): Grouped orders per stall with pickup date/time slot selection, race-condition transaction safety, and instant farmer in-app notifications.
  - Order Tracking & Reorder (`customer/orders.php`): Status lifecycle tracking, customer cancellation with automated inventory restore, itemized breakdown, and one-click basket reordering.
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

## In progress
- Ready for demo recording, test runs, and final report compilation.

## Next up
- Submission deliverables: verify SQL seed credentials in phpMyAdmin and walk through user roles for Aptech competition presentation.

## Test Credentials
- **Administrator:** `admin@marketlink.test` / `Admin@123`
- **Farmer (Green Valley):** `greenvalley@marketlink.test` / `Farmer@123`
- **Customer (Sarah Jenkins):** `sarah.customer@marketlink.test` / `Customer@123`
- **Customer (David Miller):** `david.customer@marketlink.test` / `Customer@123`
