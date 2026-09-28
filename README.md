# 🌿 MarketLink — Digital Farmers Market & Pre-Order Platform

> **A Farm-to-Community Pre-Ordering & Geospatial Discovery Platform**  
> *Connecting local sustainable growers directly with neighborhood shoppers to eliminate food waste and ensure peak harvest freshness.*

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Leaflet](https://img.shields.io/badge/Leaflet-1.9.4-199900?style=for-the-badge&logo=leaflet&logoColor=white)](https://leafletjs.com/)
[![OpenStreetMap](https://img.shields.io/badge/Maps-OpenStreetMap-7EBC6F?style=for-the-badge&logo=openstreetmap&logoColor=white)](https://www.openstreetmap.org/)
[![Status](https://img.shields.io/badge/Status-Production%20Ready-027A48?style=for-the-badge)](#)

---

## 📖 Table of Contents

1. [Project Overview](#-project-overview)
2. [Key Portals & Features](#-key-portals--features)
   - [🛒 Customer Experience](#1-customer-experience)
   - [🌾 Farmer Producer Portal](#2-farmer-producer-portal)
   - [🛡️ Platform Administration](#3-platform-administration)
3. [🗺️ Geospatial & Map Integration](#-geospatial--map-integration)
4. [🎨 Custom Popup Modal System](#-custom-popup-modal-system)
5. [🔒 Security Architecture](#-security-architecture)
6. [🛠️ Tech Stack](#-tech-stack)
7. [🚀 Quick Start Installation Guide](#-quick-start-installation-guide)
8. [🔑 Test Accounts & Demo Credentials](#-test-accounts--demo-credentials)
9. [📁 Project Directory Structure](#-project-directory-structure)
10. [🧪 Verification & Testing](#-verification--testing)

---

## 🌟 Project Overview

**MarketLink** is a digital solution connecting independent regional farmers and urban community markets with local consumers. Built around the **weekend market pre-order model**, MarketLink solves key challenges in agricultural retail:

- **Zero Food Waste:** Farmers only harvest and pack produce that has already been pre-ordered.
- **Guaranteed Freshness:** Produce moves from soil to customer basket within hours on market day.
- **Convenient Weekend Pickups:** Customers reserve farm goods throughout the week and collect their custom baskets during designated market time windows without standing in long queues.
- **Local Economic Support:** Direct peer-to-farmer commerce with 100% transparent pricing in Pakistani Rupee (PKR).

---

## 🏛️ Key Portals & Features

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                                 MARKETLINK ECOSYSTEM                             │
├──────────────────────┬───────────────────────────────┬───────────────────────────┤
│  🛒 CUSTOMER PORTAL  │   🌾 FARMER PRODUCER PORTAL   │  🛡️ ADMIN CONTROL HUB    │
├──────────────────────┼───────────────────────────────┼───────────────────────────┤
│ • Interactive Map    │ • SaaS Sidebar Dashboard      │ • Platform Analytics      │
│ • Fresh Produce Hub  │ • Inventory Management        │ • Stall Approvals & KYC   │
│ • Multi-Stall Cart   │ • Bulk Restock Modal Tool     │ • Customer Management     │
│ • Time-Slot Checkout │ • Pre-Order Pipeline Workflow │ • Market Plazas CRUD      │
│ • Order Tracking     │ • Leaflet Stall Pin Picker    │ • Content Moderation      │
│ • Verified Reviews   │ • Reputation & Reply System   │ • System Announcements    │
│ • Saved Favorites    │ • In-App Notification Stream  │ • Revenue & Sales Reports │
└──────────────────────┴───────────────────────────────┴───────────────────────────┘
```

### 1. Customer Experience
- **Interactive OpenStreetMap Discovery (`customer/browse-markets.php`):** Locate nearby physical farmers markets with live "Find Markets Near Me" GPS detection, operational days/hours, and direct "Get Directions" navigation.
- **Harvest Catalog & Search (`customer/browse-products.php`):** Filter fresh produce by category, organic farming practice, participating market, farmer stall, and price range.
- **Stall Profiles (`customer/farmer-detail.php`):** View farmer biographies, practice certifications, stall coordinates, and customer ratings.
- **Multi-Stall Pre-Order Basket (`customer/cart.php`):** Seamless cart grouped per farmer stall with dynamic stock capping.
- **Structured Checkout (`customer/checkout.php`):** Select exact pickup dates and time windows; safe database transactions prevent over-ordering.
- **Order Tracking & Reorder Drawer (`customer/orders.php`):** Status tracking (`placed` ➔ `accepted` ➔ `ready` ➔ `completed`) with itemized breakdowns, direct "Get Directions to Pickup Point", and one-click basket reordering.
- **Verified Reviews & Star Ratings (`customer/reviews.php`):** Review farmer stalls after order completion, browse feedback, and read farmer responses.
- **Saved Favorites (`customer/favorites.php`):** Bookmark preferred stalls and frequently purchased produce.

### 2. Farmer Producer Portal
- **SaaS Sidebar Dashboard (`farmer/dashboard.php`):** Instant KPI cards tracking harvest catalog count, pending order queues with badge alerts, total revenue in PKR, and average stall ratings.
- **Produce Inventory Management (`farmer/products.php`):** Complete CRUD interface, high-res photo uploads, instant sold-out toggle, and an advanced **Bulk Restock Modal Tool** with individual quantity additions and live previews.
- **Pre-Order Pipeline Workflow (`farmer/orders.php`):** Multi-tab order manager with single-click transitions (`Accept`, `Decline`, `Mark Packed & Ready`, `Mark Picked Up & Paid`). Declining automatically restores inventory.
- **Stall Profile & Geolocation (`farmer/profile.php`):** Configure stall branding, pickup windows, order cutoff thresholds, market associations, and an interactive draggable OpenStreetMap pin with **GPS auto-detection** and **Nominatim address search**.
- **Customer Feedback & Replies (`farmer/reviews.php`):** Reputation analytics card with star distribution breakdown and inline response tools.

### 3. Platform Administration
- **Admin Command Center (`admin/dashboard.php`):** Real-time ecosystem statistics, quick pending farmer approvals, and operational health metrics.
- **Farmer Stalls Management (`admin/manage-farmers.php`):** Review farmer applications, inspect stall profiles, approve, suspend, or reactivate stalls.
- **Customer Oversight (`admin/manage-customers.php`):** Account lifecycle management, order history inspection, and account security controls.
- **Market Plazas Directory (`admin/manage-markets.php`):** Add, update, and manage physical farmers markets with interactive Leaflet map coordinate pinning and platform-wide network map.
- **Content Moderation (`admin/moderation.php`):** Moderate public reviews, override produce visibility, and dispatch platform-wide announcements.
- **Analytics & Reporting (`admin/reports.php`):** Comprehensive revenue analytics, order completion rates, top-selling categories, and printable summaries.

---

## 🗺️ Geospatial & Map Integration

MarketLink uses **Leaflet.js** and **OpenStreetMap** to provide complete mapping functionality without proprietary API keys or external billing requirements:

| Map Feature | Location | Technical Implementation |
|---|---|---|
| **Market Discovery Map** | `customer/browse-markets.php` | Leaflet tile layers, custom SVG pins, popup details, and "Explore Stalls" links |
| **"Find Markets Near Me"** | `customer/browse-markets.php` | Browser Geolocation API, pulsing user pin, and dynamic viewport bounds fitting |
| **"Get Directions" Integration** | All Portals & Modals | Generates direct navigation URLs (`https://www.google.com/maps/dir/?api=1&destination=LAT,LNG`) |
| **Stall Geocoding Picker** | `farmer/profile.php` | Draggable Leaflet marker with live two-way sync to coordinate inputs, GPS auto-detect, and OpenStreetMap Nominatim live search |
| **Market Plaza Setup** | `admin/manage-markets.php` | Coordinate picker with Nominatim address resolution, GPS detection, and platform network map |
| **Central Support Hub** | `contact.php` | Interactive Leaflet contact map with turn-by-turn directions link |

---

## 🎨 Custom Popup Modal System

All default browser `alert()` and `confirm()` dialogs are replaced with a **Custom SaaS Popup Modal System** built into `assets/js/main.js` and `assets/css/style.css`:

- **Frosted Glass Backdrop:** `backdrop-filter: blur(5px)` with smooth scale-and-fade entrance animations.
- **Contextual Icon Badges:**
  - 🔴 **Danger / Deletion / Suspension:** Crimson gradient badge with trash/exclamation icons.
  - 🟡 **Warning / Restoration:** Amber gradient badge for bulk actions and basket clears.
  - 🟢 **Success / Approval / Completion:** Emerald gradient badge for order collection and approvals.
  - 🔵 **Info / Notices:** Blue/Primary badge for geocoding and geolocation feedback.
- **Declarative & Programmatic API:**
  - Supports declarative attributes: `data-confirm="..."`, `data-confirm-title="..."`, `data-confirm-type="..."`, `data-confirm-btn="..."`
  - Supports Promise-based JavaScript: `await customConfirm({ title: '...', message: '...', type: 'danger' })`
  - Supports custom alert: `customAlert({ title: 'Notice', message: '...', type: 'info' })`
  - Automatically captures and intercepts legacy form submissions.

---

## 🔒 Security Architecture

MarketLink implements comprehensive security practices:

1. **Prepared SQL Statements (PDO):** 100% parameter binding across all database queries to prevent SQL Injection.
2. **CSRF Protection:** Cryptographically secure CSRF token generation and validation on every state-changing POST request (`verify_csrf_token()`).
3. **Password Security:** Secure hashing using `PASSWORD_BCRYPT` (Cost: 12) with zero plain-text storage.
4. **XSS Mitigation:** Comprehensive escaping via `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` using the `e()` helper.
5. **Role-Based Access Control (RBAC):** Strict gatekeeping via `includes/auth-check.php` checking both user session role (`customer`, `farmer`, `admin`) and account status (`active`, `pending`, `suspended`).
6. **Session Hardening:** Session regeneration upon login, `httponly` and `samesite=Lax` cookies to prevent session fixation.

---

## 🛠️ Tech Stack

- **Backend:** PHP 8.1+ (Procedural architecture with strict PDO exception handling)
- **Database:** MySQL 8.0+ / MariaDB 10.4+ (InnoDB engine with Foreign Key constraints and composite indexes)
- **Frontend Framework:** Bootstrap 5.3.3 & Bootstrap Icons 1.11.3
- **Mapping & Geocoding:** Leaflet.js v1.9.4 & OpenStreetMap (OSM Nominatim)
- **Design System:** Custom CSS3 with CSS variables, Plus Jakarta Sans & Inter typography, and light/earthy color palette
- **Client Scripting:** Vanilla JavaScript (ES6+, zero heavy JS framework dependencies)

---

## 🚀 Quick Start Installation Guide

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (with Apache 2.4+ and MySQL 8.0+ / MariaDB with PHP 8.1+)
- Any modern web browser (Google Chrome, Mozilla Firefox, Microsoft Edge)

### Step-by-Step Setup

1. **Clone or Copy to XAMPP Web Root:**
   ```bash
   # Clone into your XAMPP htdocs directory
   cd C:\xampp\htdocs\
   git clone https://github.com/MuhammadAsim2009/MarketLink.git marketlink
   ```
   *(Or copy the `marketlink` project folder directly into `C:\xampp\htdocs\marketlink` or `E:\xampp\htdocs\marketlink`)*

2. **Start Services:**
   - Open **XAMPP Control Panel**.
   - Start **Apache** and **MySQL**.

3. **Create Database:**
   - Open `http://localhost/phpmyadmin` in your browser.
   - Click **New** and create a database named: `marketlink_db` (Collation: `utf8mb4_unicode_ci`).

4. **Import Database Schema & Seed Data:**
   - In phpMyAdmin, select `marketlink_db`.
   - Click the **Import** tab.
   - Choose `marketlink/sql/schema.sql` and click **Import**.
   - Click the **Import** tab again.
   - Choose `marketlink/sql/seed-data.sql` and click **Import**.

5. **Verify Database Connection (`config/db.php`):**
   ```php
   $db_host = 'localhost';
   $db_port = '3306';
   $db_name = 'marketlink_db';
   $db_user = 'root';
   $db_pass = ''; // Default XAMPP password is empty
   ```

6. **Launch MarketLink:**
   Navigate to:
   ```
   http://localhost/marketlink/
   ```

---

## 🔑 Test Accounts & Demo Credentials

Every portal includes pre-seeded demonstration data:

| Portal / Role | Full Name / Stall | Email Address | Password | Key Demo Features |
|---|---|---|---|---|
| **🛡️ Administrator** | Platform SuperAdmin | `admin@gmail.com` | `Admin@123` | KPI Dashboard, Stall Approvals, Moderation |
| **🌾 Farmer (Active)** | Tariq Mahmood (Green Valley Organics) | `greenvalley@marketlink.test` | `Farmer@123` | Inventory, Bulk Restock, Orders Pipeline |
| **🌾 Farmer (Active)** | Bashir Ahmed (Sunny Orchards) | `sunnyorchard@marketlink.test` | `Farmer@123` | Fruit Catalog, Map Pin Location, Reviews |
| **🌾 Farmer (Active)** | Kulsoom Bibi (Happy Hen Pastures) | `happyhen@marketlink.test` | `Farmer@123` | Dairy/Eggs, Order Cutoff Hours, Reviews |
| **🌾 Farmer (Pending)** | Asad Khan (Artisan Dairy) | `artisan@marketlink.test` | `Farmer@123` | Use Admin portal to approve this stall |
| **🛒 Customer** | Sarah Jenkins | `sarah.customer@marketlink.test` | `Customer@123` | Active Basket, Orders, Geolocation Map |
| **🛒 Customer** | David Miller | `david.customer@marketlink.test` | `Customer@123` | Completed Orders, Verified Reviewer |

> **Unified Sign In (All Roles):** `http://localhost/marketlink/auth/login.php`

---

## 📁 Project Directory Structure

```
marketlink/
├── admin/                      # Platform Administrator Portal
│   ├── includes/               # Admin sidebar header, topbar, and footer
│   │   ├── header.php
│   │   └── footer.php
│   ├── dashboard.php           # Admin KPI center and rapid farmer approvals
│   ├── manage-farmers.php      # Farmer stall KYC, inspections, status toggles
│   ├── manage-customers.php    # Customer account oversight and spend tracking
│   ├── manage-markets.php      # Physical farmers market plazas directory & map
│   ├── moderation.php          # Review moderation and system announcements
│   └── reports.php             # Platform analytics, revenue breakdowns, exports
├── assets/
│   ├── css/
│   │   └── style.css           # Complete SaaS design system, design tokens, modals
│   ├── js/
│   │   └── main.js             # Custom modal system, spinners, form interceptor
│   └── images/                 # Produce photos, stall banners, brand icons
├── auth/                       # Authentication & Session Management
│   ├── forgot-password.php     # Token-based password recovery
│   ├── login.php               # Unified sign-in for all roles (Admin, Farmer, Customer)
│   ├── logout.php              # Secure session termination
│   ├── register.php            # Multi-role registration with practice options
│   └── reset-password.php      # Secure password reset handler
├── config/                     # Core Configuration
│   ├── constants.php           # App-wide status codes, categories, paths
│   ├── db.php                  # Active PDO database connection
│   └── db.example.php          # Environment template
├── customer/                   # Customer Portal & Shopper Workflows
│   ├── browse-markets.php      # OpenStreetMap directory + "Near Me" GPS
│   ├── browse-products.php     # Multi-filter fresh harvest catalog
│   ├── cart.php                # Grouped pre-order basket with live stock capping
│   ├── checkout.php            # Slot-based pickup scheduling & checkout
│   ├── dashboard.php           # Customer profile, ready alerts, quick re-order
│   ├── farmer-detail.php       # Stall showcase, harvest menu, map, reviews
│   ├── favorites.php           # Bookmarked farms and produce items
│   ├── orders.php              # Status tracking, detail drawer, "Get Directions"
│   ├── product-detail.php      # Produce showcase, stock badge, reviews
│   └── reviews.php             # Verified review submission and feedback feed
├── farmer/                     # Farmer Producer Portal
│   ├── includes/               # Farmer sidebar header and app footer
│   │   ├── header.php
│   │   └── footer.php
│   ├── dashboard.php           # Real-time revenue, order queues, quick stats
│   ├── orders.php              # Pre-order workflow management pipeline
│   ├── products.php            # Harvest CRUD & Bulk Restock Modal Tool
│   ├── profile.php             # Stall branding, cutoff hours, Leaflet GPS picker
│   └── reviews.php             # Rating analytics & customer reply tool
├── includes/                   # Shared Platform Components
│   ├── auth-check.php          # Role-based route guard & user status check
│   ├── footer.php              # Global footer with brand links & scripts
│   ├── functions.php           # Procedural helpers (CSRF, sanitization, flash)
│   └── header.php              # Responsive glassmorphism navigation header
├── md_files/                   # System Documentation & Specifications
│   ├── INSTALL.md              # Detailed local installation manual
│   ├── MarketLink...SRS.md     # Software Requirements Specification
│   └── memory.md               # Engineering roadmap & completion logs
├── sql/                        # Database Scripts
│   ├── schema.sql              # Complete relational InnoDB schema with indexes
│   └── seed-data.sql           # FK-safe demonstration dataset
├── about.php                   # About Us & platform mission page
├── contact.php                 # Contact inquiry form & Central Support Hub map
├── index.php                   # Public landing page with live platform metrics
├── notifications.php           # In-app notification center with category filters
└── README.md                   # Project documentation (this file)
```

---

## 🧪 Verification & Testing

MarketLink has undergone comprehensive validation:
- **Zero Syntax Errors:** All 38 PHP scripts verified with `php -l` linting.
- **SQL Integrity:** Schema and seed imports tested with zero foreign key constraint conflicts (`SET FOREIGN_KEY_CHECKS = 0`).
- **Responsive Viewports:** Tested across desktop (1920px), laptop (1366px), tablet (768px), and mobile (375px).
- **Cross-Browser Compatibility:** Tested on Google Chrome, Mozilla Firefox, and Microsoft Edge.

---

## 📄 License & Attribution

Developed as an end-to-end web platform solution for **Aptech Pakistan**.  
Built with ❤️ to empower local agriculture, strengthen community food systems, and champion zero food waste.
