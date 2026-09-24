# Security & Access Document — MarketLink

Defines who can do what inside MarketLink, and what happens when things
go wrong. Written in plain English so it's easy to defend in the judge
interview.

## Authentication method
- Email + password only (no OAuth/social login, no OTP — keeps it simple
  and matches the plain-PHP, no-framework stack).
- Passwords stored with `password_hash()`, verified with
  `password_verify()` — never stored in plain text, never logged.
- Login creates a PHP session holding `user_id` and `role`
  (`customer` / `farmer` / `admin`).
- Password reset via a tokenized link emailed to the user (token expires
  after a set time, e.g. 30 minutes, and is single-use).
- Session destroyed on logout (`session_unset()` + `session_destroy()`).

## User roles & permissions
| Role | Can do | Cannot do |
|---|---|---|
| **Visitor** (not logged in) | Browse markets/farmers/products (read-only) | Place orders, bookmark, review, see any dashboard |
| **Customer** | Browse, search/filter, place/cancel/modify own pre-orders, view own order history, manage own favorites, leave reviews on own completed orders | See other customers' orders, edit products, access farmer/admin pages |
| **Farmer** | Manage own products/stock, view/accept/decline pre-orders placed against their own stock, view own sales insights, respond to reviews on their own products | See other farmers' products/orders, edit customer accounts, access admin pages |
| **Admin** | Approve/suspend farmers, activate/deactivate customers, manage markets, moderate any listing/review, view platform-wide reports | Nothing restricted — but admin actions should still be logged (who did what, when) |

Every protected page starts by including `includes/auth-check.php`, which
verifies the session exists and the role matches what that page expects.
No page trusts a role passed in a form field or URL — role always comes
from the server-side session.

## Row-level security
Even within a role, users should only see their own data:
- A **customer** can only view/cancel/modify orders where
  `orders.customer_id == $_SESSION['user_id']`.
- A **farmer** can only view/manage products and orders where
  `products.farmer_id` / `orders.farmer_id == $_SESSION['user_id']`.
- Every query that fetches a specific record (order, product, favorite,
  review) filters by the logged-in user's ID in the `WHERE` clause — never
  relies only on hiding the link in the UI.
- Direct URL access to another user's record (e.g. guessing
  `order.php?id=45`) must return "not found" or "access denied," not the
  data.

## Error handling
| Failure | Response |
|---|---|
| Wrong email/password on login | Generic "Invalid email or password" (don't reveal which field was wrong) |
| Database connection fails | Show a friendly "Something went wrong, try again shortly" page; log the real error server-side |
| Form submitted with missing/invalid fields | Inline field-level error messages, form re-shown with entered data preserved (except password) |
| Farmer tries to accept an order that's already been cancelled | Show a clear message, don't silently fail or crash |
| Customer tries to order more than `quantity_available` | Block at submission with a clear stock-limit message |
| Expired/reused password reset token | "This link has expired or was already used, please request a new one" |
| Unauthorized page access (wrong role or no session) | Redirect to login with a message, never show a blank/broken page |

## Edge cases to handle before the demo
- Empty states: no products yet, no orders yet, no markets nearby —
  each needs a friendly message, not a blank page
- Customer places an order right as a farmer marks the item sold out
  (handle the race — re-check stock at order submission, not just at
  page load)
- Order cutoff time passes while a customer is mid-checkout — block the
  submission with a clear message
- Farmer account is suspended by admin while they're logged in — next
  action should be blocked, not just the next login
- Very long product names/descriptions or special characters — make sure
  they don't break layout or SQL (prepared statements handle the SQL
  side; CSS `overflow`/truncation handles the layout side)
- Slow/failed map load (Leaflet/OSM) — page should still work without
  the map rendering (map is an enhancement, not a hard dependency)
