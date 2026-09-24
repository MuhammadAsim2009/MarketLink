# phases.md — MarketLink

Break the project into phases and complete them in order. Don't start a
later phase until the current one works end-to-end.

## Phase 1: Auth
- DB schema setup (`sql/schema.sql`)
- Customer registration/login
- Farmer registration/login
- Admin login (separate, direct-access)
- Session management + role-based redirect
- Password reset flow

## Phase 2: Farmer Module
- Farmer profile (stall info, markets, operating days, pickup windows,
  map pin)
- Product CRUD (add/edit/delete, mark sold out)
- View/accept/decline incoming pre-orders, mark ready for pickup

## Phase 3: Customer Module
- Browse markets/farmers, map view (Leaflet + OSM)
- Product search/filter/sort
- Cart + place pre-order with pickup slot
- Order status tracking, cancel/modify before cutoff
- Order history + reorder, favorites

## Phase 4: Admin Module
- Admin dashboard (platform totals)
- Approve/suspend farmers, activate/deactivate customers
- Manage markets (add/edit/remove)
- Content moderation
- Reports (orders, revenue by market, active farmers)

## Phase 5: Reviews, Notifications & Polish
- Reviews & ratings (customer side) + farmer responses
- In-app/email notifications (order confirmed, ready for pickup)
- About Us / Contact Us pages
- Responsive layout pass across all pages
- Dark corners: empty states, loading indicators, form validation polish

## Phase 6 (Optional): AI FAQ Assistant
- Only after Phases 1–5 are solid
- Simple FAQ-matching or hosted widget (e.g. tawk.to) — isolated module,
  doesn't block core flow if it breaks

## Phase 7: Submission Prep
- Seed test data + finalize test credentials for all roles
- Write installation instructions
- Record demo video covering every functional requirement
- Finalize project report (problem definition, diagrams, DB design,
  assumptions in ReadMe.doc)
- Export SQL scripts
