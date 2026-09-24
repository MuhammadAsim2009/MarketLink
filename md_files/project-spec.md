# MarketLink — Project Spec

## One-line pitch
A platform connecting local farmers-market Farmers with Customers: Farmers
publish weekly stock and manage pre-orders; Customers discover nearby markets,
browse stock, place pre-orders for pickup, and leave reviews.

## Roles
- **Visitor** — not logged in, browse-only (optional, not mandatory per SRS).
- **Customer** — registers, browses markets/products, places pre-orders,
  tracks order history, saves favorites, leaves reviews.
- **Farmer** — registers stall, manages weekly stock/pricing, manages
  incoming pre-orders, views sales insights, responds to reviews.
- **Admin** — approves/suspends Farmers, activates/deactivates Customers,
  manages markets, moderates content, views platform-wide reports.

## In scope
- Registration/login for Customer & Farmer, separate Admin login
- Market & Farmer browsing with map view (OpenStreetMap/Leaflet)
- Product search/filter (category, price, market, day)
- Cart + pre-order placement with pickup date/time slot
- Order status workflow + cancel/modify before cutoff
- Order history, favorites, reviews & ratings
- Admin dashboard: farmer/customer management, market management, reports
- Optional: basic AI FAQ assistant

## Explicitly out of scope
- Payment gateway (orders paid in person at pickup)
- Delivery/courier logistics (pickup only)
- Farmer identity/licensing/food-safety verification

## Non-functional requirements
- Responsive across desktop/tablet/mobile
- Compatible with major browsers
- Secure auth (only logged-in users reach personalized features)
- Reasonable performance even with growing product catalogues
- Available with minimal downtime (mainly relevant once hosted)

## Deliverables checklist (for the project report, not the code)
- [ ] Problem Definition
- [ ] Design Specifications
- [ ] Flowcharts / Data Flow Diagrams
- [ ] Database Design (ERD + table definitions)
- [ ] Test data used in the project
- [ ] Installation instructions (MANDATORY)
- [ ] User credentials for all user types (MANDATORY)
- [ ] ReadMe.doc with assumptions made
- [ ] SQL script file(s) with schema + seed data
- [ ] Demo video (.mp4) showing all functional requirements (MANDATORY)
- [ ] Sitemap on the home page (SRS does not explicitly require this for
      MarketLink, but worth doing for clarity)
- [ ] Acknowledge any AI tools used in building the project
