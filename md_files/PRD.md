# PRD.md — MarketLink

## What to build
A Web platform connecting local farmers-market **Farmers** with **Customers**.
Farmers publish their weekly stock and pricing and manage incoming pre-orders.
Customers discover nearby markets/farmers on a map, browse stock, place
pre-orders for pickup, and leave reviews. An **Admin** oversees farmers,
customers, markets, and platform health.

Built for TechWiz 7 (Aptech), plain PHP + MySQL, no framework.

## Targeted users
- **Customers** — regular shoppers who want to know what's fresh and
  available before making a trip to a farmers market.
- **Farmers** — small local sellers who want an easy way to publish stock
  and manage pre-orders without a POS system.
- **Admin** — platform owner who approves farmers, manages markets, and
  keeps an eye on overall activity.

## Features (high-level — full breakdown in phases.md)
- Registration/login for Customer & Farmer, separate Admin login
- Market & Farmer browsing with map view
- Product search/filter (category, price, market, day)
- Cart + pre-order placement with pickup slot selection
- Order status workflow (placed → accepted → ready → completed/cancelled)
- Order history, favorites, reviews & ratings
- Admin dashboard: farmer/customer approval, market management, reports
- Optional: basic AI FAQ assistant

## Out of scope
- Payment gateway (paid at pickup)
- Delivery/courier logistics
- Farmer license/food-safety verification

## Success criteria
- All functional requirements in the SRS work end-to-end and can be
  demoed in the mandatory video
- Judges can be walked through design decisions and code in an interview
- Documentation, SQL scripts, and credentials are submission-ready
