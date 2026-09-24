# MarketLink — Installation Guide

> **TechWiz 7 · Aptech Pakistan**  
> Platform: XAMPP (Apache + MySQL + PHP 8.x) · Windows

---

## Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| XAMPP | 8.x | Bundled Apache 2.4 + MySQL 8.0 + PHP 8.1+ |
| Browser | Any modern | Chrome, Firefox, Edge recommended |
| phpMyAdmin | Included | Accessed via `http://localhost/phpmyadmin` |

---

## Step 1 — Clone / Copy the Project

Place the `marketlink` folder inside your XAMPP web root:

```
C:\xampp\htdocs\marketlink\
```

> If you're on a non-standard XAMPP install (e.g. `E:\xampp`), adjust the path accordingly. The project has been developed and tested at `E:\xampp\htdocs\marketlink\`.

---

## Step 2 — Start XAMPP Services

1. Open **XAMPP Control Panel**.
2. Start **Apache** and **MySQL**.
3. Verify both show green status indicators.

---

## Step 3 — Create the Database

### Option A — phpMyAdmin (recommended for demo)

1. Open your browser and navigate to:  
   `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar.
3. Enter database name: `marketlink_db`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**.

### Option B — MySQL CLI

```bash
mysql -u root -p -e "CREATE DATABASE marketlink_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## Step 4 — Import the Schema

1. In phpMyAdmin, select **`marketlink_db`** from the left sidebar.
2. Click the **Import** tab.
3. Click **Choose File** and select:  
   `marketlink/sql/schema.sql`
4. Click **Go**.

You should see: *"Import has been successfully finished."*

---

## Step 5 — Import the Seed Data

1. Still inside phpMyAdmin with `marketlink_db` selected.
2. Click the **Import** tab again.
3. Click **Choose File** and select:  
   `marketlink/sql/seed-data.sql`
4. Click **Go**.

> ⚠️ The seed file uses `SET FOREIGN_KEY_CHECKS = 0` and `DELETE FROM` (not TRUNCATE) so it is safe to re-run at any time without FK constraint errors.

---

## Step 6 — Verify the Database Configuration

Open `config/db.php` and confirm the credentials match your XAMPP setup:

```php
$db_host = 'localhost';
$db_port = '3306';
$db_name = 'marketlink_db';
$db_user = 'root';
$db_pass = '';          // Leave empty for default XAMPP root user
```

If your MySQL root password is set, update `$db_pass` accordingly.

---

## Step 7 — Open MarketLink in the Browser

Navigate to:

```
http://localhost/marketlink/
```

You should see the MarketLink landing page with the green hero section.

---

## Test Credentials

Use these accounts to demo every role:

| Role | Email | Password | Notes |
|---|---|---|---|
| **Admin** | `admin@marketlink.test` | `Admin@123` | Login via `http://localhost/marketlink/auth/admin-login.php` |
| **Farmer** | `greenvalley@marketlink.test` | `Farmer@123` | Active farmer — Green Valley Organics |
| **Farmer** | `sunnyorchard@marketlink.test` | `Farmer@123` | Active farmer — Sunny Orchards |
| **Farmer** | `happyhen@marketlink.test` | `Farmer@123` | Active farmer — Happy Hen Pastures |
| **Farmer** | `artisan@marketlink.test` | `Farmer@123` | **Pending** farmer — use Admin to approve |
| **Customer** | `sarah.customer@marketlink.test` | `Customer@123` | Has existing cart/orders |
| **Customer** | `david.customer@marketlink.test` | `Customer@123` | Has completed order with review |

---

## Role-by-Role Demo Walkthrough

### 🌿 Farmer Flow
1. Log in as `greenvalley@marketlink.test`
2. **Farmer Dashboard** → view order queue and revenue totals
3. **My Products** → add a new product, edit price, mark sold out
4. **Orders** → accept a pending pre-order
5. **My Profile** → update stall details and drag the map pin
6. **Reviews** → reply to a customer review

### 🛒 Customer Flow
1. Log in as `sarah.customer@marketlink.test`
2. **Browse Markets** → explore the map, filter by day
3. **Browse Products** → search "tomato", filter by category
4. **Product Detail** → add item to cart
5. **Cart** → review basket, adjust quantities
6. **Checkout** → select pickup date/slot, confirm order
7. **My Orders** → track order status
8. **Favorites** → save a stall
9. **Reviews** → review a completed order

### 🔑 Admin Flow
1. Navigate to `http://localhost/marketlink/auth/admin-login.php`
2. Log in as `admin@marketlink.test`
3. **Dashboard** → view platform KPIs
4. **Manage Farmers** → approve the pending `artisan@marketlink.test` account
5. **Manage Markets** → add or edit a market with map pin
6. **Moderation** → delete a review, toggle product visibility
7. **Reports** → view revenue and order analytics

---

## Password Reset (No Mail Server Required)

1. Go to `http://localhost/marketlink/auth/forgot-password.php`
2. Enter any registered email.
3. The reset token is displayed **on screen** (no email sent — suitable for local demo).
4. Copy the token and use the reset link shown.

---

## Folder Structure Summary

```
marketlink/
├── assets/
│   ├── css/style.css          # Custom design tokens & styles
│   └── js/main.js             # Vanilla JS utilities
├── auth/                      # Login, register, logout, password reset
├── admin/                     # Admin dashboard, management, reports
├── customer/                  # Browse, cart, checkout, orders, favorites
├── farmer/                    # Products, orders, profile, reviews
├── config/
│   ├── db.php                 # Database connection (PDO)
│   └── constants.php          # App-wide constants
├── includes/
│   ├── functions.php          # Helper functions (CSRF, flash, sanitize)
│   ├── auth-check.php         # Role-based route guard
│   ├── header.php             # Shared navigation header
│   └── footer.php             # Shared footer + scripts
├── sql/
│   ├── schema.sql             # Full InnoDB schema (run first)
│   └── seed-data.sql          # Demo data (run after schema)
├── notifications.php          # In-app notification center
├── about.php                  # About Us page
├── contact.php                # Contact Us + map
├── index.php                  # Landing page
└── INSTALL.md                 # This file
```

---

## Troubleshooting

| Problem | Fix |
|---|---|
| Blank page / 500 error | Check Apache error log at `C:\xampp\apache\logs\error.log` |
| "Connection failed" | Verify MySQL is running and `config/db.php` credentials are correct |
| FK constraint error on seed import | The seed file uses `SET FOREIGN_KEY_CHECKS = 0` — ensure you import the full file, not individual statements |
| Session not persisting | Ensure PHP session extension is enabled in `php.ini` |
| Map not loading | Leaflet loads from CDN — requires internet connection |
| Images not showing | Seed data uses placeholder paths; images can be placed in `assets/images/products/` |

---

*MarketLink — TechWiz 7 · Aptech Pakistan · Built with PHP 8 + MySQL + Bootstrap 5 + Leaflet.js*
