# MarketLink — Features Checklist

Organized by build priority (see CLAUDE.md for the priority order).
✅ = Implemented and verified | 🚫 = Explicitly out of scope

## 1. Auth & Roles
- [x] ✅ Customer registration (name, phone, email, address, password)
- [x] ✅ Farmer registration (stall name, contact person, phone, email, address)
- [x] ✅ Login (shared form, redirect by role) + separate Admin login (`auth/admin-login.php`)
- [x] ✅ Secure session management (PHP sessions, role stored server-side)
- [x] ✅ Logout (`auth/logout.php`)
- [x] ✅ Password reset via tokenized link stored in `password_resets` table (token shown on-screen for demo — no mail server required)
- [x] ✅ Role-based route guarding (`includes/auth-check.php`) — active/pending/suspended status enforced

## 2. Farmer Module
- [x] ✅ Farmer profile: markets sold at, operating days, pickup windows, interactive map pin (Leaflet + OSM)
- [x] ✅ Product CRUD (name, category, price, unit, quantity, description, image URL/upload)
- [x] ✅ Weekend bulk restock tool (recurring weekly stock template)
- [x] ✅ Mark item sold out / one-click toggle
- [x] ✅ View incoming pre-orders, accept/decline (auto stock restore on decline), mark ready, complete
- [x] ✅ Order cutoff hours and pickup windows configured per farmer profile
- [x] ✅ View order history with totals, pending count, revenue breakdown
- [x] ✅ View & respond to customer reviews (public farmer reply with notification trigger)

## 3. Customer Module
- [x] ✅ Browse markets by location/day with attending farmer stall list
- [x] ✅ View farmer profile (stall, location, days, current stock, avg rating)
- [x] ✅ Interactive map view of markets/farmers (Leaflet + OpenStreetMap markers)
- [x] ✅ Browse/search/filter products (category, price range, market, farmer, in-stock toggle, sort)
- [x] ✅ Product detail view (photo, description, stock badge, reviews, quantity selector)
- [x] ✅ Cart + place pre-order with live stock capping
- [x] ✅ Select pickup date/time slot within farmer's window at checkout
- [x] ✅ View order status lifecycle, customer cancellation before cutoff (auto inventory restore)
- [x] ✅ Order history + one-click reorder basket
- [x] ✅ Favorites (save farmer stalls & produce items)
- [x] ✅ Reviews & star ratings on completed orders

## 4. Admin Module
- [x] ✅ Admin dashboard: live totals (farmers, customers, markets, orders, pending review count)
- [x] ✅ Approve/suspend farmer registrations with stall profile inspection
- [x] ✅ Activate/deactivate customer accounts
- [x] ✅ Manage markets (add/edit/remove, address, days, timings, coordinates via Leaflet map pin)
- [x] ✅ Content moderation (remove reviews, product visibility overrides)
- [x] ✅ Platform-wide announcement broadcasts (stored as notifications for all users)
- [x] ✅ Platform reports (orders, revenue by market, completed order rates, active farmers)

## 5. Cross-cutting
- [x] ✅ Responsive layout (Bootstrap 5 — mobile/tablet/desktop)
- [x] ✅ In-app notifications center (`notifications.php`) for order confirmation, status updates, farmer replies
- [x] ✅ About Us page (`about.php`)
- [x] ✅ Contact Us page with embedded Leaflet map (`contact.php`)
- [x] ✅ Server-side input validation + CSRF protection on all forms

## 6. Optional
- [ ] AI FAQ assistant — **not implemented** (deprioritized for TechWiz 7 scope)

## Explicitly not building
- 🚫 Payment gateway / online payment
- 🚫 Delivery or courier logistics
- 🚫 Farmer license/food-safety verification
