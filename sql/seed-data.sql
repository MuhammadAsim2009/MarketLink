-- MarketLink Seed Data
USE `marketlink_db`;

-- Clear existing data if re-seeding
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `notifications`;
DELETE FROM `reviews`;
DELETE FROM `favorites`;
DELETE FROM `order_items`;
DELETE FROM `orders`;
DELETE FROM `products`;
DELETE FROM `market_farmers`;
DELETE FROM `markets`;
DELETE FROM `farmer_profiles`;
DELETE FROM `users`;
DELETE FROM `password_resets`;

ALTER TABLE `notifications` AUTO_INCREMENT = 1;
ALTER TABLE `reviews` AUTO_INCREMENT = 1;
ALTER TABLE `favorites` AUTO_INCREMENT = 1;
ALTER TABLE `order_items` AUTO_INCREMENT = 1;
ALTER TABLE `orders` AUTO_INCREMENT = 1;
ALTER TABLE `products` AUTO_INCREMENT = 1;
ALTER TABLE `markets` AUTO_INCREMENT = 1;
ALTER TABLE `users` AUTO_INCREMENT = 1;
ALTER TABLE `password_resets` AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;

-- Users
-- Passwords:
-- Admin: Admin@123
-- Farmers: Farmer@123
-- Customers: Customer@123
INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `phone`, `role`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@marketlink.test', '$2y$10$mM7NxYiAmYIc5VeuRpdODOD1n8f3scmAvgVQwhGtPSdme7gvtiZxO', '03001234567', 'admin', 'active', NOW()),
(2, 'Green Valley Organic Farm', 'greenvalley@marketlink.test', '$2y$10$87/M7Gz59igiyJZSOulsBuROkTzYdRxDlqugUPFW1qcp53E3sxuM.', '03112345678', 'farmer', 'active', NOW()),
(3, 'Sunny Orchard & Berries', 'sunnyorchard@marketlink.test', '$2y$10$87/M7Gz59igiyJZSOulsBuROkTzYdRxDlqugUPFW1qcp53E3sxuM.', '03223456789', 'farmer', 'active', NOW()),
(4, 'Happy Hen Dairy & Eggs', 'happyhen@marketlink.test', '$2y$10$87/M7Gz59igiyJZSOulsBuROkTzYdRxDlqugUPFW1qcp53E3sxuM.', '03334567890', 'farmer', 'active', NOW()),
(5, 'Artisan Bakehouse & Honey', 'artisan@marketlink.test', '$2y$10$87/M7Gz59igiyJZSOulsBuROkTzYdRxDlqugUPFW1qcp53E3sxuM.', '03445678901', 'farmer', 'pending', NOW()),
(6, 'Sarah Jenkins', 'sarah.customer@marketlink.test', '$2y$10$2d9UxNJnRD8iECbeMOkWKeeD790ADvdTfbDA4xagU/VA1VGFPUwbe', '03019876543', 'customer', 'active', NOW()),
(7, 'David Miller', 'david.customer@marketlink.test', '$2y$10$2d9UxNJnRD8iECbeMOkWKeeD790ADvdTfbDA4xagU/VA1VGFPUwbe', '03028765432', 'customer', 'active', NOW());

-- Farmer Profiles
INSERT INTO `farmer_profiles` (`farmer_id`, `stall_name`, `address`, `latitude`, `longitude`, `operating_days`, `pickup_window_start`, `pickup_window_end`, `order_cutoff_hours`) VALUES
(2, 'Green Valley Organics (Stall #12)', 'Plot 45, Agro Zone, North Valley', 31.52040000, 74.35870000, 'Sat,Sun', '08:00:00', '14:00:00', 2),
(3, 'Sunny Orchards & Berry Farm (Stall #4)', 'Farmstead 12, Hilltop Ridge', 31.53000000, 74.37000000, 'Fri,Sat,Sun', '09:00:00', '15:00:00', 3),
(4, 'Happy Hen Pastures (Stall #9)', 'Barn #3, Greenfield Meadow', 31.51500000, 74.34000000, 'Wed,Sat,Sun', '07:30:00', '13:30:00', 2),
(5, 'Artisan Loaves & Pure Honey (Stall #18)', 'Cottage Rd, Old Mill District', 31.54500000, 74.32000000, 'Sat,Sun', '09:00:00', '16:00:00', 4);

-- Markets
INSERT INTO `markets` (`market_id`, `market_name`, `address`, `latitude`, `longitude`, `operating_days`, `timings`) VALUES
(1, 'Central Square Farmers Market', 'Heritage Park Plaza, Downtown', 31.52040000, 74.35870000, 'Sat,Sun', '07:00 AM - 02:00 PM'),
(2, 'Riverside Organic Fair', 'Riverfront Promenade, West Bank', 31.53500000, 74.32500000, 'Fri,Sat', '08:00 AM - 03:00 PM'),
(3, 'Meadowbrook Community Market', 'Meadowbrook Sports Complex Parking, East Suburbs', 31.50500000, 74.38500000, 'Sunday', '08:30 AM - 01:30 PM');

-- Market Farmers
INSERT INTO `market_farmers` (`market_id`, `farmer_id`) VALUES
(1, 2),
(1, 3),
(1, 4),
(2, 2),
(2, 4),
(3, 3);

-- Products
INSERT INTO `products` (`product_id`, `farmer_id`, `category`, `name`, `description`, `price`, `unit`, `quantity_available`, `image_url`, `is_sold_out`, `created_at`) VALUES
(1, 2, 'Vegetables', 'Farm-Fresh Heirloom Tomatoes', 'Juicy, vine-ripened organic heirloom tomatoes bursting with natural sweetness.', 4.50, 'kg', 25, 'assets/images/products/tomatoes.jpg', 0, NOW()),
(2, 2, 'Vegetables', 'Crisp Organic Baby Spinach', 'Tender baby spinach leaves, harvested early morning for peak crunch.', 3.00, 'bunch', 40, 'assets/images/products/spinach.jpg', 0, NOW()),
(3, 2, 'Vegetables', 'Rainbow Swiss Chard', 'Vibrant, colorful stems and deep green leaves full of nutrients.', 3.50, 'bunch', 18, 'assets/images/products/chard.jpg', 0, NOW()),
(4, 3, 'Fruits', 'Honeycrisp Apples', 'Sweet, incredibly crunchy orchard apples perfect for snacking and pies.', 5.00, 'kg', 50, 'assets/images/products/apples.jpg', 0, NOW()),
(5, 3, 'Fruits', 'Wild Mountain Strawberries', 'Sweet, intensely fragrant small batch strawberries picked fresh.', 6.00, 'box', 30, 'assets/images/products/strawberries.jpg', 0, NOW()),
(6, 4, 'Dairy & Eggs', 'Free-Range Brown Farm Eggs', 'Grade A pasture-raised large eggs with rich golden yolks.', 4.00, 'dozen', 60, 'assets/images/products/eggs.jpg', 0, NOW()),
(7, 4, 'Dairy & Eggs', 'Artisanal Fresh Goat Cheese', 'Creamy, tangy artisanal chevre made from 100% pure goat milk.', 7.50, 'piece', 15, 'assets/images/products/cheese.jpg', 0, NOW());

-- Orders
INSERT INTO `orders` (`order_id`, `customer_id`, `farmer_id`, `pickup_date`, `pickup_slot`, `status`, `total_amount`, `created_at`) VALUES
(1, 6, 2, '2026-09-26', '09:00 AM - 10:00 AM', 'placed', 12.00, NOW()),
(2, 6, 3, '2026-09-26', '10:00 AM - 11:00 AM', 'accepted', 11.00, NOW()),
(3, 7, 4, '2026-09-27', '08:00 AM - 09:00 AM', 'completed', 15.50, NOW() - INTERVAL 2 DAY);

-- Order Items
INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price_at_order`) VALUES
(1, 1, 1, 2, 4.50),
(2, 1, 2, 1, 3.00),
(3, 2, 4, 1, 5.00),
(4, 2, 5, 1, 6.00),
(5, 3, 6, 2, 4.00),
(6, 3, 7, 1, 7.50);

-- Reviews
INSERT INTO `reviews` (`review_id`, `customer_id`, `farmer_id`, `product_id`, `rating`, `comment`, `farmer_response`, `created_at`) VALUES
(1, 7, 4, 6, 5, 'The eggs are unbeatable! Bright yellow yolks and very fresh. Will buy every weekend.', 'Thank you David! Our hens roam free on grass all day.', NOW() - INTERVAL 1 DAY),
(2, 6, 2, 1, 5, 'Best tomatoes in town. Highly recommend Green Valley!', NULL, NOW() - INTERVAL 3 DAY);

-- Favorites
INSERT INTO `favorites` (`favorite_id`, `customer_id`, `farmer_id`, `product_id`, `created_at`) VALUES
(1, 6, 2, NULL, NOW()),
(2, 6, NULL, 1, NOW()),
(3, 7, 4, NULL, NOW());

-- Notifications
INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'You have a new pre-order #1 from Sarah Jenkins for pickup on 2026-09-26.', 0, NOW()),
(2, 6, 'Your pre-order #2 has been accepted by Sunny Orchard & Berries!', 1, NOW());
