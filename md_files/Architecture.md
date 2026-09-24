# Architecture.md — MarketLink

## App flow
```
Visitor/Customer/Farmer/Admin
        |
   login/register  ->  session created with role
        |
   role-based redirect
        |
  +-----------+-----------+-----------+
  |           |           |           |
Customer    Farmer      Admin      (Visitor: browse-only, no session)
  |           |           |
Browse      Manage      Manage
markets/    stock &     farmers/
products    orders      customers/
  |           |         markets
Cart ->    Accept/       |
Pre-order  decline    Reports
  |        orders
Order
status
tracking
```

All roles talk to the same MySQL database through PHP pages using PDO.
There is no separate API layer — pages fetch data server-side and render
HTML directly (classic PHP request/response cycle), with a bit of
JavaScript/AJAX for things like live cart updates and the map widget.

## Folder & file structure
```
/marketlink
  /config
    db.php                 -> PDO connection setup
    constants.php          -> site-wide constants (base URL, etc.)
  /includes
    header.php
    footer.php
    auth-check.php         -> role-based access guard, include at top of protected pages
    functions.php          -> shared helper functions
  /auth
    login.php
    register.php
    logout.php
    forgot-password.php
    reset-password.php
  /customer
    dashboard.php
    browse-markets.php
    browse-products.php
    product-detail.php
    cart.php
    checkout.php           -> place pre-order
    orders.php             -> order history + status
    favorites.php
    reviews.php
  /farmer
    dashboard.php
    products.php           -> CRUD
    orders.php             -> accept/decline/ready
    profile.php            -> stall info, map pin, pickup windows
  /admin
    dashboard.php
    manage-farmers.php
    manage-customers.php
    manage-markets.php
    reports.php
    moderation.php
  /assets
    /css
    /js
    /images
  /sql
    schema.sql
    seed-data.sql
  index.php                -> public landing page + sitemap
```

## Tech stack
- **Backend:** Plain PHP, PDO for all DB access (prepared statements only)
- **Database:** MySQL (via XAMPP locally)
- **Frontend:** HTML5, CSS3, Bootstrap (layout/components), vanilla
  JavaScript / jQuery for interactivity
- **Maps:** Leaflet.js + OpenStreetMap tiles (no API key needed)
- **Optional AI:** kept as an isolated widget/module, not core to the flow

See `database-schema.md` (from the earlier planning docs) for full table
definitions, and `rules.md` for coding conventions.
