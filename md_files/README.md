# MarketLink

Farm Fresh Just a Click Away — a Web platform connecting local farmers-market
Farmers with Customers. Built for TechWiz 7 (Aptech).

See `PRD.md` for what this app does and who it's for, `Architecture.md` for
how it's structured, `phases.md` for the build order, `rules.md` for coding
conventions, `design.md` for the visual system, and `memory.md` for
current build progress.

## Tech stack
- PHP (plain, no framework) + PDO
- MySQL
- HTML5, CSS3, Bootstrap, vanilla JS/jQuery
- Leaflet.js + OpenStreetMap for maps

## Local setup (XAMPP)
1. Install XAMPP, start Apache + MySQL.
2. Clone/copy this project into `htdocs/marketlink`.
3. Create a database `marketlink_db` in phpMyAdmin.
4. Import `sql/schema.sql` (and `sql/seed-data.sql` if present) into it.
5. Copy `config/db.example.php` to `config/db.php` and set your DB
   credentials (default XAMPP: user `root`, empty password).
6. Visit `http://localhost/marketlink/` in the browser.

## Test accounts
_(fill in once seed data exists — required for submission)_
- Admin: `admin@marketlink.test` / `TBD`
- Sample Farmer: `TBD`
- Sample Customer: `TBD`

## Project deliverables checklist
See `PRD.md` and the original SRS for the full mandatory deliverables list
(installation instructions, credentials, demo video, SQL scripts, etc).
