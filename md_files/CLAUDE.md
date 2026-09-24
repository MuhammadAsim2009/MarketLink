# MarketLink — Agent Instructions

## What this project is
"MarketLink" — a full-stack Web app connecting local farmers-market Farmers with
Customers. Built for the TechWiz 7 competition (Aptech). See `project-spec.md`
for scope, `database-schema.md` for the DB design, and `features-checklist.md`
for the full functional requirement list broken into tasks.

## Tech stack (locked in — do not switch without asking)
- **Backend:** Plain PHP (no framework — no Laravel, no Composer packages for
  routing/ORM). Use native PDO for all database access (prepared statements only).
- **Database:** MySQL (via XAMPP locally).
- **Frontend:** HTML5, CSS3, Bootstrap (for grid/components only — don't let
  Bootstrap defaults do all the visual work, customize colors/fonts), vanilla
  JavaScript / jQuery for interactivity.
- **Maps:** OpenStreetMap + Leaflet.js (free, no API key needed — do NOT set up
  Google Maps API, it requires billing).
- **AI Assistant (optional feature):** if implemented, keep it a separate,
  clearly-isolated module (e.g. a widget that calls a hosted tool like tawk.to,
  or a simple rule-based FAQ matcher) — never let it gate core functionality.

## Folder structure convention
```
/marketlink
  /config        -> db.php (PDO connection), constants
  /includes      -> shared header.php, footer.php, auth-check.php
  /auth          -> login.php, register.php, logout.php, reset-password.php
  /customer      -> dashboard, browse, cart, orders, favorites, reviews
  /farmer        -> dashboard, products, orders, profile
  /admin         -> dashboard, manage-farmers, manage-customers, manage-markets, reports
  /assets        -> css/, js/, images/
  /sql           -> schema.sql, seed-data.sql
```

## Coding conventions
- Every DB query uses PDO prepared statements — never concatenate user input
  into SQL strings.
- Passwords hashed with `password_hash()` / verified with `password_verify()`.
- Every page that requires login checks session role at the top
  (`includes/auth-check.php`) before rendering anything.
- Keep PHP logic and HTML readable — light use of PHP in HTML is fine for a
  student project, but pull DB/query logic into small functions rather than
  giant inline blocks.
- Comment non-obvious logic briefly — the project must be defensible in a
  judge interview, so write it the way you'd want to explain it out loud.

## What NOT to do
- Do not scaffold this from a pre-made admin-dashboard template — build the UI
  from scratch (Figma/Canva mockups are fine as a guide).
- Do not implement any payment gateway — orders are paid at pickup, out of scope.
- Do not implement delivery/courier logistics — pickup only.
- Do not fully auto-generate documentation — Asim writes the project report
  himself; the agent can help draft sections but must flag anything that
  needs his own explanation for the judge interview.

## Priority order (see features-checklist.md for full detail)
1. Auth + role-based access (Customer / Farmer / Admin)
2. Farmer: product CRUD + weekly stock
3. Customer: browse/search/filter + cart + place pre-order
4. Order status workflow (placed → accepted → ready → completed / cancelled)
5. OpenStreetMap/Leaflet integration for market & farmer locations
6. Admin panel (approve farmers, manage markets, reports)
7. Reviews & ratings, favorites, notifications
8. Optional AI FAQ assistant — only after everything above works
