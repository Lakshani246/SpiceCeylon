-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 05, 2025 at 04:31 PM
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
-- Database: `spiceceylon_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','moderator') DEFAULT 'super_admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(2, 'admin', 'jk@gmail.com', '$2y$10$xLOOBdgQwlz8fki.AdYYduNMcoUh/CRlnwwsEc3vldWVAW06KCvp.', 'super_admin', '2025-12-05 14:52:04');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `customer_id`, `product_id`, `quantity`, `added_at`, `price`) VALUES
(10, 1, 25, 1, '2025-12-05 13:59:19', 450.00);

-- --------------------------------------------------------

--
-- Table structure for table `forecast_data`
--

CREATE TABLE `forecast_data` (
  `forecast_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `forecast_value` decimal(10,2) DEFAULT NULL,
  `forecast_month` varchar(20) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('Unread','Read') DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `shipping_fee` decimal(10,2) DEFAULT 0.00,
  `final_total` decimal(10,2) DEFAULT NULL,
  `shipping_name` varchar(255) DEFAULT NULL,
  `shipping_phone` varchar(20) DEFAULT NULL,
  `shipping_address` varchar(255) DEFAULT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_postal` varchar(20) DEFAULT NULL,
  `payment_method` enum('cash_on_delivery','credit_card','paypal') DEFAULT 'cash_on_delivery',
  `notes` text DEFAULT NULL,
  `status` enum('Pending','Processing','Completed','Cancelled','Shipped','Delivered','Confirmed') DEFAULT 'Pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `total`, `total_amount`, `shipping_fee`, `final_total`, `shipping_name`, `shipping_phone`, `shipping_address`, `shipping_city`, `shipping_postal`, `payment_method`, `notes`, `status`, `order_date`, `created_at`) VALUES
(2, 1, NULL, 1700.00, 200.00, 1900.00, 'sripali', '0543256712', 'kandy', 'kandy', '21000', 'cash_on_delivery', '', 'Pending', '2025-12-05 13:04:51', '2025-12-05 13:04:51'),
(3, 1, NULL, 750.00, 200.00, 950.00, 'wer', '2345', 'sdfg', 'dfg', '234', 'credit_card', '', 'Pending', '2025-12-05 13:06:20', '2025-12-05 13:06:20'),
(5, 1, NULL, 8500.00, 200.00, 8700.00, 'ishara', '0352234657', 'colombo,srilanka', 'colombo', '1234', 'credit_card', '2 packs for travel', 'Pending', '2025-12-05 13:33:02', '2025-12-05 13:33:02'),
(6, 1, NULL, 840.00, 200.00, 1040.00, 'ishara', '12345', 'colombo', 'colombo', '21345', 'cash_on_delivery', '', 'Pending', '2025-12-05 13:54:53', '2025-12-05 13:54:53');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price`, `total_price`) VALUES
(1, 2, 2, 1, 1250.00, 1250.00),
(2, 2, 1, 1, 450.00, 450.00),
(3, 3, 4, 1, 750.00, 750.00),
(5, 5, 36, 1, 8500.00, 8500.00),
(6, 6, 43, 2, 420.00, 840.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment_cards`
--

CREATE TABLE `payment_cards` (
  `card_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `card_name` varchar(100) NOT NULL,
  `card_last_four` char(4) NOT NULL,
  `card_expiry` varchar(7) NOT NULL,
  `card_type` enum('Visa','MasterCard','Amex') DEFAULT 'Visa',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_cards`
--

INSERT INTO `payment_cards` (`card_id`, `customer_id`, `order_id`, `card_name`, `card_last_four`, `card_expiry`, `card_type`, `is_default`, `created_at`) VALUES
(1, 1, 5, 'ishara', '2145', '12/25', 'MasterCard', 1, '2025-12-05 13:33:02');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `farmer_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `farmer_id`, `name`, `description`, `price`, `stock`, `image`, `status`, `created_at`) VALUES
(1, 2, 'Cinnamon', 'Sweet and aromatic Ceylon cinnamon bark, true cinnamon with delicate flavor.', 450.00, 200, 'cinnamon.jpg', 'Approved', '2025-11-23 11:14:12'),
(2, 2, 'Cardamom', 'Aromatic green cardamom pods with intense flavor and medicinal properties.', 1250.00, 150, 'cardamom.jpg', 'Approved', '2025-11-23 11:14:12'),
(3, 2, 'Cloves', 'Aromatic flower buds with warm, sweet flavor, perfect for meat dishes and teas.', 890.00, 120, 'cloves.jpg', 'Approved', '2025-11-23 11:14:12'),
(4, 2, 'Nutmeg', 'Warm, nutty spice perfect for both sweet and savory dishes, freshly ground.', 750.00, 95, 'nutmeg.jpg', 'Approved', '2025-11-23 11:14:12'),
(5, 2, 'Mace', 'Delicate spice from nutmeg covering, with subtle flavor for baking and sauces.', 920.00, 80, 'mace.jpg', 'Approved', '2025-11-23 11:14:12'),
(6, 2, 'Black Pepper', 'Freshly ground black pepper with robust flavor and heat, freshly harvested.', 650.00, 180, 'black_pepper.jpg', 'Approved', '2025-11-23 11:14:12'),
(7, 2, 'Cumin Seeds', 'Warm, earthy seeds with distinctive aroma, essential for curry powders.', 380.00, 160, 'cumin_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(8, 2, 'Coriander Seeds', 'Mild, citrusy seeds essential for curry powders and spice blends.', 290.00, 170, 'coriander_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(9, 2, 'Fennel Seeds', 'Sweet, licorice-flavored seeds for cooking and digestive health.', 350.00, 140, 'fennel_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(10, 2, 'Fenugreek Seeds', 'Bitter seeds used in curry powders and traditional medicinal preparations.', 340.00, 110, 'fenugreek_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(11, 2, 'Mustard Seeds', 'Tiny seeds that pack a punch of flavor when tempered in oil.', 310.00, 130, 'mustard_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(12, 2, 'Turmeric', 'Golden yellow spice with earthy flavor and powerful health benefits.', 320.00, 200, 'turmeric.jpg', 'Approved', '2025-11-23 11:14:12'),
(13, 2, 'Vanilla', 'Premium vanilla beans with rich, sweet aroma and flavor for baking.', 2800.00, 80, 'vanilla.jpg', 'Approved', '2025-11-23 11:14:12'),
(14, 2, 'Sesame Seeds', 'Nutty seeds perfect for both sweets and savory dishes, rich in calcium.', 380.00, 150, 'sesame_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(15, 2, 'Pandan Leaves', 'Fragrant leaves used for flavoring rice, desserts, and drinks.', 220.00, 100, 'pandan_leaves.jpg', 'Approved', '2025-11-23 11:14:12'),
(16, 2, 'Curry Leaves', 'Aromatic leaves essential for Sri Lankan tempering and curry dishes.', 180.00, 150, 'curry_leaves.jpg', 'Approved', '2025-11-23 11:14:12'),
(17, 2, 'Lemongrass', 'Citrusy stalks used for teas, curries, and soups, freshly cut.', 190.00, 120, 'lemongrass.jpg', 'Approved', '2025-11-23 11:14:12'),
(18, 2, 'Sri Lankan Ginger', 'Fresh, aromatic ginger with strong flavor and medicinal properties.', 280.00, 130, 'ginger.jpg', 'Approved', '2025-11-23 11:14:12'),
(19, 2, 'Garlic', 'Fresh local garlic bulbs with robust flavor for cooking.', 320.00, 200, 'garlic.jpg', 'Approved', '2025-11-23 11:14:12'),
(20, 2, 'Ceylon Citron / Lemon', 'Fresh Ceylon lemons with aromatic zest and juice.', 150.00, 180, 'lemon.jpg', 'Approved', '2025-11-23 11:14:12'),
(21, 2, 'Bay Leaves', 'Aromatic leaves ideal for flavoring soups, stews, and rice dishes.', 280.00, 120, 'bay_leaves.jpg', 'Approved', '2025-11-23 11:14:12'),
(22, 2, 'Star Anise', 'Star-shaped spice with distinctive licorice flavor for soups and meats.', 950.00, 100, 'star_anise.jpg', 'Approved', '2025-11-23 11:14:12'),
(23, 2, 'Asafoetida', 'Strong aromatic resin used as a flavor enhancer in vegetarian cooking.', 680.00, 90, 'asafoetida.jpg', 'Approved', '2025-11-23 11:14:12'),
(24, 2, 'Celery Seeds', 'Tiny seeds with intense celery flavor for cooking and pickling.', 420.00, 110, 'celery_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(25, 2, 'Ceylon Chili / Bird\'s Eye Chili', 'Small but extremely hot chilies essential for Sri Lankan cuisine.', 450.00, 140, 'ceylon_chili.jpg', 'Approved', '2025-11-23 11:14:12'),
(26, 2, 'Chili Powder', 'Finely ground red chili powder for adding heat and color to dishes.', 380.00, 160, 'chili_powder.jpg', 'Approved', '2025-11-23 11:14:12'),
(27, 2, 'Black Mustard Seeds', 'Sharp, pungent mustard seeds for tempering and pickling.', 330.00, 120, 'black_mustard_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(28, 2, 'Goraka', 'Sour fruit used as a natural souring agent in Sri Lankan cooking.', 420.00, 90, 'goraka.jpg', 'Approved', '2025-11-23 11:14:12'),
(29, 2, 'Tamarind', 'Tangy fruit pulp used for adding sour flavor to curries and chutneys.', 350.00, 110, 'tamarind.jpg', 'Approved', '2025-11-23 11:14:12'),
(30, 2, 'Screw Pine / Kewra', 'Aromatic flowers used for flavoring desserts and rice dishes.', 1200.00, 60, 'screw_pine.jpg', 'Approved', '2025-11-23 11:14:12'),
(31, 2, 'Licorice Powder', 'Sweet, aromatic powder used in teas and traditional medicine.', 580.00, 85, 'licorice_powder.jpg', 'Approved', '2025-11-23 11:14:12'),
(32, 2, 'Annatto Seeds', 'Natural coloring seeds that impart yellow-orange color to food.', 410.00, 95, 'annatto_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(33, 2, 'Ajwain (Carom Seeds)', 'Pungent seeds with thyme-like flavor, good for digestion.', 360.00, 100, 'ajwain.jpg', 'Approved', '2025-11-23 11:14:12'),
(34, 2, 'Dill', 'Fresh dill leaves with mild anise flavor for salads and fish dishes.', 270.00, 110, 'dill.jpg', 'Approved', '2025-11-23 11:14:12'),
(35, 2, 'Sweet Flag', 'Medicinal herb with aromatic roots, used in traditional remedies.', 890.00, 70, 'sweet_flag.jpg', 'Approved', '2025-11-23 11:14:12'),
(36, 2, 'Saffron', 'World\'s most precious spice, hand-picked crimson threads for premium dishes.', 8500.00, 30, 'saffron.jpg', 'Approved', '2025-11-23 11:14:12'),
(37, 2, 'Poppy Seeds', 'Tiny blue seeds perfect for baking and thickening curries.', 520.00, 95, 'poppy_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(38, 2, 'Caraway Seeds', 'Aromatic seeds traditionally used in breads and European dishes.', 480.00, 100, 'caraway_seeds.jpg', 'Approved', '2025-11-23 11:14:12'),
(39, 2, 'Juniper Berries', 'Aromatic berries excellent for flavoring meats, sauces, and gin.', 720.00, 85, 'juniper_berries.jpg', 'Approved', '2025-11-23 11:14:12'),
(40, 2, 'Sumac', 'Tangy crimson spice with lemony flavor for seasoning and marinades.', 580.00, 110, 'sumac.jpg', 'Approved', '2025-11-23 11:14:12'),
(41, 2, 'Roasted Curry Powder', 'Traditional Sri Lankan roasted curry powder with complex flavors.', 550.00, 150, 'roasted_curry_powder.jpg', 'Approved', '2025-11-23 11:14:12'),
(42, 2, 'Unroasted Curry Powder', 'Mild, unroasted curry powder for light-colored dishes.', 520.00, 140, 'unroasted_curry_powder.jpg', 'Approved', '2025-11-23 11:14:12'),
(43, 2, 'Chili Paste', 'Freshly ground chili paste for instant heat and flavor in cooking.', 420.00, 120, 'chili_paste.jpg', 'Approved', '2025-11-23 11:14:12');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` varchar(50) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `review_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`review_id`, `product_id`, `user_name`, `user_email`, `rating`, `review_text`, `created_at`) VALUES
(1, 'cinnamon', 'sripali', 's@gmail.com', 5, 'excellent product.', '2025-11-18 07:15:02');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `spice_name` varchar(255) DEFAULT NULL,
  `quantity_needed` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('Pending','Approved by Farmer','Approved by Admin','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `role` enum('customer','farmer') DEFAULT NULL,
  `farm_location` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'default-avatar.jpg',
  `is_registered` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `phone`, `address`, `role`, `farm_location`, `profile_image`, `is_registered`, `created_at`) VALUES
(1, 'sripali', 's@gmail.com', '$2y$10$fvsQh6aRyY.xL1BP10oqVO7D/YUbHhvpwyujxLAiVs4FchN/wPjDG', '0762541960', 'debathgama,kegalle', 'customer', '', 'user_1_1764943140.jpg', 1, '2025-11-15 07:23:11'),
(2, 'dilshani', 'd@gmail.com', '$2y$10$EtCIkNELPYuX5z9zFza8COnxQ8cpwDMx2AlktKlgvqGoF4xTUaskm', '0812345674', 'pilimathalawa,kandy', 'farmer', 'kandy', 'default-avatar.jpg', 1, '2025-11-15 07:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `website_stats`
--

CREATE TABLE `website_stats` (
  `stat_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `visit_count` int(11) DEFAULT 0,
  `page_views` int(11) DEFAULT 0,
  `unique_visitors` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `website_stats`
--

INSERT INTO `website_stats` (`stat_id`, `visit_date`, `visit_count`, `page_views`, `unique_visitors`, `created_at`) VALUES
(1, '2025-12-05', 0, 0, 0, '2025-12-05 15:29:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `forecast_data`
--
ALTER TABLE `forecast_data`
  ADD PRIMARY KEY (`forecast_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payment_cards`
--
ALTER TABLE `payment_cards`
  ADD PRIMARY KEY (`card_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `farmer_id` (`farmer_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `website_stats`
--
ALTER TABLE `website_stats`
  ADD PRIMARY KEY (`stat_id`),
  ADD UNIQUE KEY `unique_date` (`visit_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `forecast_data`
--
ALTER TABLE `forecast_data`
  MODIFY `forecast_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payment_cards`
--
ALTER TABLE `payment_cards`
  MODIFY `card_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `website_stats`
--
ALTER TABLE `website_stats`
  MODIFY `stat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `forecast_data`
--
ALTER TABLE `forecast_data`
  ADD CONSTRAINT `forecast_data_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_cards`
--
ALTER TABLE `payment_cards`
  ADD CONSTRAINT `payment_cards_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_cards_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
