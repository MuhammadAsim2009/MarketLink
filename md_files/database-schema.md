# MarketLink — Database Schema (MySQL)

Notes:
- Engine: InnoDB (for FK support). Charset: utf8mb4.
- `role` distinguishes Customer/Farmer/Admin in a single `users` table to
  keep auth simple; Farmer-specific and Customer-specific data lives in
  their own linked tables.

## users
| Column | Type | Notes |
|---|---|---|
| user_id | INT PK AUTO_INCREMENT | |
| name | VARCHAR(100) | |
| email | VARCHAR(150) UNIQUE | |
| password_hash | VARCHAR(255) | |
| phone | VARCHAR(20) | |
| role | ENUM('customer','farmer','admin') | |
| status | ENUM('active','suspended','pending') DEFAULT 'active' | pending = farmer awaiting admin approval |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

## farmer_profiles
| Column | Type | Notes |
|---|---|---|
| farmer_id | INT PK, FK -> users.user_id | |
| stall_name | VARCHAR(150) | |
| address | TEXT | |
| latitude | DECIMAL(10,8) | |
| longitude | DECIMAL(11,8) | |
| operating_days | VARCHAR(100) | e.g. "Sat,Sun" |
| pickup_window_start | TIME | |
| pickup_window_end | TIME | |
| order_cutoff_hours | INT | hours before pickup |

## markets
| Column | Type | Notes |
|---|---|---|
| market_id | INT PK AUTO_INCREMENT | |
| market_name | VARCHAR(150) | |
| address | TEXT | |
| latitude | DECIMAL(10,8) | |
| longitude | DECIMAL(11,8) | |
| operating_days | VARCHAR(100) | |
| timings | VARCHAR(100) | |

## market_farmers (many-to-many: a farmer can sell at multiple markets)
| Column | Type | Notes |
|---|---|---|
| market_id | INT FK -> markets.market_id | |
| farmer_id | INT FK -> users.user_id | |
| PRIMARY KEY | (market_id, farmer_id) | |

## products
| Column | Type | Notes |
|---|---|---|
| product_id | INT PK AUTO_INCREMENT | |
| farmer_id | INT FK -> users.user_id | |
| category | VARCHAR(50) | vegetables/fruits/dairy/baked goods/etc |
| name | VARCHAR(100) | |
| description | TEXT | |
| price | DECIMAL(10,2) | |
| unit | VARCHAR(20) | e.g. kg, dozen, piece |
| quantity_available | INT | |
| image_url | VARCHAR(255) | |
| is_sold_out | BOOLEAN DEFAULT FALSE | |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

## orders
| Column | Type | Notes |
|---|---|---|
| order_id | INT PK AUTO_INCREMENT | |
| customer_id | INT FK -> users.user_id | |
| farmer_id | INT FK -> users.user_id | denormalized for quick farmer-side queries |
| pickup_date | DATE | |
| pickup_slot | VARCHAR(50) | |
| status | ENUM('placed','accepted','declined','ready','completed','cancelled') DEFAULT 'placed' | |
| total_amount | DECIMAL(10,2) | |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

## order_items
| Column | Type | Notes |
|---|---|---|
| order_item_id | INT PK AUTO_INCREMENT | |
| order_id | INT FK -> orders.order_id | |
| product_id | INT FK -> products.product_id | |
| quantity | INT | |
| price_at_order | DECIMAL(10,2) | snapshot, in case product price changes later |

## favorites
| Column | Type | Notes |
|---|---|---|
| favorite_id | INT PK AUTO_INCREMENT | |
| customer_id | INT FK -> users.user_id | |
| farmer_id | INT FK -> users.user_id NULL | either farmer or product favorited |
| product_id | INT FK -> products.product_id NULL | |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

## reviews
| Column | Type | Notes |
|---|---|---|
| review_id | INT PK AUTO_INCREMENT | |
| customer_id | INT FK -> users.user_id | |
| farmer_id | INT FK -> users.user_id NULL | review can target farmer... |
| product_id | INT FK -> products.product_id NULL | ...or a specific product |
| rating | TINYINT | 1-5 |
| comment | TEXT | |
| farmer_response | TEXT NULL | |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

## notifications
| Column | Type | Notes |
|---|---|---|
| notification_id | INT PK AUTO_INCREMENT | |
| user_id | INT FK -> users.user_id | |
| message | TEXT | |
| is_read | BOOLEAN DEFAULT FALSE | |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

## Key relationships
- users (farmer) 1—N products
- users (customer) 1—N orders
- orders 1—N order_items N—1 products
- markets N—N users(farmer) via market_farmers
- users (customer) 1—N favorites, 1—N reviews
- users (admin) manages all — no direct FK, controlled via `role`

## Suggested indexes
- `products(farmer_id, category)`
- `orders(customer_id, status)`
- `orders(farmer_id, status)`
- `reviews(farmer_id)`, `reviews(product_id)`
