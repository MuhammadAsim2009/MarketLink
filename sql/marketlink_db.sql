-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 28, 2026 at 05:00 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `marketlink_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `farmer_profiles`
--

CREATE TABLE `farmer_profiles` (
  `farmer_id` int(11) NOT NULL,
  `stall_name` varchar(150) NOT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `operating_days` varchar(100) DEFAULT NULL,
  `pickup_window_start` time DEFAULT NULL,
  `pickup_window_end` time DEFAULT NULL,
  `order_cutoff_hours` int(11) NOT NULL DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `farmer_profiles`
--

INSERT INTO `farmer_profiles` (`farmer_id`, `stall_name`, `address`, `latitude`, `longitude`, `operating_days`, `pickup_window_start`, `pickup_window_end`, `order_cutoff_hours`) VALUES
(2, 'Faseeh Dairy Farm', 'Near KFC, Royal Road, Larkana', 27.55077510, 68.21141940, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', '12:00:00', '22:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `favorite_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `farmer_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`favorite_id`, `customer_id`, `farmer_id`, `product_id`, `created_at`) VALUES
(1, 3, NULL, 3, '2026-09-28 18:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `markets`
--

CREATE TABLE `markets` (
  `market_id` int(11) NOT NULL,
  `market_name` varchar(150) NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `operating_days` varchar(100) DEFAULT NULL,
  `timings` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `markets`
--

INSERT INTO `markets` (`market_id`, `market_name`, `address`, `latitude`, `longitude`, `operating_days`, `timings`) VALUES
(1, 'Jelus Bazar', 'Jelus Bazar, Larkana', 27.56218000, 68.21325000, 'Mon,Tue,Wed,Thu,Sat,Sun', '07:00 AM - 08:30 PM'),
(2, 'Resham Gali', 'Dr. Bhagwandas Chowk, Bandar Road, Jafri Imambargah, and Bughio House', 27.55444770, 68.21745730, 'Mon,Tue,Wed,Thu,Fri', '11:00 AM - 10:00 PM'),
(3, 'Royal Road', 'Station Rd, Wakel Colony Wakil Colony Larkana, Pakistan', 27.55212430, 68.21272700, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', '11:00 AM - 01:00 AM');

-- --------------------------------------------------------

--
-- Table structure for table `market_farmers`
--

CREATE TABLE `market_farmers` (
  `market_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `market_farmers`
--

INSERT INTO `market_farmers` (`market_id`, `farmer_id`) VALUES
(3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'Welcome to MarketLink! Your farmer account has been registered and is pending approval from the platform administrator.', 1, '2026-09-28 17:44:35'),
(2, 2, 'Congratulations! Your farmer stall has been approved by the platform administrator and is now live to customers.', 1, '2026-09-28 18:17:32'),
(3, 3, 'Welcome to MarketLink! Discover local fresh produce and pre-order from your favorite farmers.', 1, '2026-09-28 18:56:52'),
(4, 2, 'New Pre-Order #1 received from Muhammad Asim for pickup on Sep 30, 2026 (04:00 PM – 05:00 PM).', 1, '2026-09-28 19:01:08'),
(5, 3, 'Your pre-order #1 with Faseeh Dairy Farm has been placed successfully!', 1, '2026-09-28 19:01:08'),
(6, 3, 'Great news! Your pre-order #1 has been accepted by Faseeh Dairy Farm.', 1, '2026-09-28 19:02:16'),
(7, 2, 'New Pre-Order #2 received from Muhammad Asim for pickup on Sep 30, 2026 (07:00 PM – 08:00 PM).', 1, '2026-09-28 19:02:57'),
(8, 3, 'Your pre-order #2 with Faseeh Dairy Farm has been placed successfully!', 1, '2026-09-28 19:02:57'),
(9, 2, 'Pre-Order #2 was cancelled by Muhammad Asim. Reserved items have been restored to your inventory.', 1, '2026-09-28 19:03:01'),
(10, 2, 'New Pre-Order #3 received from Muhammad Asim for pickup on Sep 30, 2026 (07:00 PM – 08:00 PM).', 1, '2026-09-28 19:03:15'),
(11, 3, 'Your pre-order #3 with Faseeh Dairy Farm has been placed successfully!', 1, '2026-09-28 19:03:15'),
(12, 3, 'Your pre-order #3 could not be accepted by Faseeh Dairy Farm. Any reserved stock has been released.', 1, '2026-09-28 19:03:32'),
(13, 3, 'Your pre-order #1 is freshly packed and READY for pickup at Faseeh Dairy Farm!', 1, '2026-09-28 19:03:49'),
(14, 3, 'Pre-order #1 marked as completed. Thank you for supporting local farmers! Please consider leaving a review.', 1, '2026-09-28 19:04:04'),
(15, 2, 'Muhammad Asim left a 4-star review for your stall: \"Very Fresh & Good Quality\"', 1, '2026-09-28 19:05:03'),
(16, 3, 'Faseeh Dairy Farm has replied to your review: \"Thank you for your review @MuhammadAsim\"', 1, '2026-09-28 19:06:12');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `pickup_date` date NOT NULL,
  `pickup_slot` varchar(50) NOT NULL,
  `status` enum('placed','accepted','declined','ready','completed','cancelled') NOT NULL DEFAULT 'placed',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `farmer_id`, `pickup_date`, `pickup_slot`, `status`, `total_amount`, `created_at`) VALUES
(1, 3, 2, '2026-09-30', '04:00 PM – 05:00 PM', 'completed', 3500.00, '2026-09-28 19:01:08'),
(2, 3, 2, '2026-09-30', '07:00 PM – 08:00 PM', 'cancelled', 3500.00, '2026-09-28 19:02:57'),
(3, 3, 2, '2026-09-30', '07:00 PM – 08:00 PM', 'declined', 3500.00, '2026-09-28 19:03:15');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price_at_order` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price_at_order`) VALUES
(1, 1, 2, 5, 700.00),
(2, 2, 2, 5, 700.00),
(3, 3, 2, 5, 700.00);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`reset_id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(1, 'admin@gmail.com', 'ec8332ec2bb40b153fd332804bb35b4ff645440983104d0813e74700a4f7c5f8', '2026-09-28 14:49:34', '2026-09-28 17:19:34'),
(5, 'sasim4589@gmail.com', '0788366a15b5013ed6ac859b63e3a0cb4755f2185042f516b5582bd071a5919d', '2026-09-28 17:25:26', '2026-09-28 19:55:26');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_attempts`
--

CREATE TABLE `password_reset_attempts` (
  `attempt_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_attempts`
--

INSERT INTO `password_reset_attempts` (`attempt_id`, `ip_address`, `email`, `attempted_at`) VALUES
(1, '::1', 'sasim4589@gmail.com', '2026-09-28 19:55:26'),
(2, '::1', 'sahibkhajar@gmail.com', '2026-09-28 19:55:42'),
(3, '::1', 'sahib@gmail.com', '2026-09-28 19:55:53'),
(4, '::1', 'aqib@gmail.com', '2026-09-28 19:56:03'),
(5, '::1', 'aqibshaikh@gmail.com', '2026-09-28 19:56:11');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'kg',
  `quantity_available` int(11) NOT NULL DEFAULT 0,
  `image_url` text DEFAULT NULL,
  `is_sold_out` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `farmer_id`, `category`, `name`, `description`, `price`, `unit`, `quantity_available`, `image_url`, `is_sold_out`, `created_at`) VALUES
(1, 2, 'Dairy & Eggs', 'Milk', 'A nutrient-rich liquid food produced by the mammary glands of mammals.', 200.00, 'litre', 30, 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=600&auto=format&fit=crop&q=80', 0, '2026-09-28 18:26:00'),
(2, 2, 'Dairy & Eggs', 'Cheese', 'A solid or semi-solid food made from milk, produced in a wide range of flavors, textures, and forms by coagulation of the milk protein casein.', 700.00, 'kg', 25, 'assets/images/products/prod_2_1790602495.jpeg', 0, '2026-09-28 18:34:55'),
(3, 2, 'Dairy & Eggs', 'Eggs', 'Standard poultry farm eggs', 250.00, 'dozen', 30, 'assets/images/products/prod_2_1790602692.jpeg', 0, '2026-09-28 18:38:12');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `farmer_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text NOT NULL,
  `farmer_response` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `customer_id`, `farmer_id`, `product_id`, `rating`, `comment`, `farmer_response`, `created_at`) VALUES
(1, 3, 2, NULL, 4, 'Very Fresh & Good Quality', 'Thank you for your review @MuhammadAsim', '2026-09-28 19:05:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('customer','farmer','admin') NOT NULL,
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password_hash`, `phone`, `role`, `status`, `created_at`) VALUES
(1, 'Admin', 'admin@gmail.com', '$2y$10$mM7NxYiAmYIc5VeuRpdODOD1n8f3scmAvgVQwhGtPSdme7gvtiZxO', '03001234567', 'admin', 'active', '2026-09-26 19:34:02'),
(2, 'Faseeh', 'faseeh@gmail.com', '$2y$10$CNupNqXAgG1k4wWQfCZ7VeZGHWHM6b4m2qPjT2kFQeWSABxTCB2mq', '03001234567', 'farmer', 'active', '2026-09-28 17:44:35'),
(3, 'Muhammad Asim', 'sasim4589@gmail.com', '$2y$10$ThfS008jxZm8qPXCAsGQquMT2dxExo7.MJdfPVPhm8QrCztR6A7Vy', '03323230869', 'customer', 'active', '2026-09-28 18:56:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `farmer_profiles`
--
ALTER TABLE `farmer_profiles`
  ADD PRIMARY KEY (`farmer_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD KEY `fk_favorites_farmer` (`farmer_id`),
  ADD KEY `fk_favorites_product` (`product_id`),
  ADD KEY `idx_favorites_customer` (`customer_id`);

--
-- Indexes for table `markets`
--
ALTER TABLE `markets`
  ADD PRIMARY KEY (`market_id`);

--
-- Indexes for table `market_farmers`
--
ALTER TABLE `market_farmers`
  ADD PRIMARY KEY (`market_id`,`farmer_id`),
  ADD KEY `fk_market_farmers_farmer` (`farmer_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user` (`user_id`,`is_read`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_orders_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_orders_farmer_status` (`farmer_id`,`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `fk_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_product` (`product_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_resets_token` (`token`),
  ADD KEY `idx_resets_email` (`email`);

--
-- Indexes for table `password_reset_attempts`
--
ALTER TABLE `password_reset_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_attempts_ip_time` (`ip_address`,`attempted_at`),
  ADD KEY `idx_attempts_email_time` (`email`,`attempted_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_products_farmer_cat` (`farmer_id`,`category`),
  ADD KEY `idx_products_category` (`category`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `fk_reviews_customer` (`customer_id`),
  ADD KEY `idx_reviews_farmer` (`farmer_id`),
  ADD KEY `idx_reviews_product` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `markets`
--
ALTER TABLE `markets`
  MODIFY `market_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_reset_attempts`
--
ALTER TABLE `password_reset_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `farmer_profiles`
--
ALTER TABLE `farmer_profiles`
  ADD CONSTRAINT `fk_farmer_profiles_user` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `fk_favorites_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_favorites_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_favorites_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `market_farmers`
--
ALTER TABLE `market_farmers`
  ADD CONSTRAINT `fk_market_farmers_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_market_farmers_market` FOREIGN KEY (`market_id`) REFERENCES `markets` (`market_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orders_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
