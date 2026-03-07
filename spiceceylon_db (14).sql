-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 07, 2026 at 03:03 PM
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
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','moderator') DEFAULT 'super_admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active' COMMENT 'Admin account status',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `email`, `phone`, `avatar`, `password`, `role`, `created_at`, `status`, `updated_at`, `last_login`) VALUES
(1, 'admin', 'jk@gmail.com', '0764162016', NULL, '$2y$10$BP90LgPftNOwMeyIKM71XesidbaGZrpaKbJIg7siYmgEWKWW98hMq', 'super_admin', '2025-12-06 14:12:45', 'active', '2025-12-24 05:17:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity`
--

CREATE TABLE `admin_activity` (
  `activity_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `activity_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_logins`
--

CREATE TABLE `admin_logins` (
  `login_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_roles` enum('all','customers','farmers','admins','specific') DEFAULT 'all',
  `target_user_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_important` tinyint(1) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `announcement_type` varchar(50) DEFAULT 'general',
  `status` enum('active','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `title`, `message`, `target_roles`, `target_user_id`, `created_by`, `is_important`, `expires_at`, `announcement_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'system maintenance', 'system maintenace scheduled for 27th feb', 'all', NULL, 1, 0, '2026-02-27 23:59:59', 'maintenance', 'active', '2026-02-25 04:21:30', '2026-02-25 04:21:30'),
(2, 'system maintenance', 'system maintenace scheduled for 27th feb', 'all', NULL, 1, 0, '2026-02-27 23:59:59', 'maintenance', 'active', '2026-02-25 04:21:38', '2026-02-25 04:21:38'),
(3, 'Delivery Delays Due to Weather', 'Dear Customers,\r\n\r\nDue to recent weather conditions in the central province, deliveries to Kandy, Nuwara Eliya, and surrounding areas may be delayed by 1-2 business days.\r\n\r\nWe\'re working closely with our delivery partners to ensure your spices reach you as soon as possible. Thank you for your understanding.\r\n\r\nStay safe!\r\nSpiceCeylon Team', 'customers', NULL, 1, 1, '2026-03-10 23:59:59', 'alert', 'active', '2026-03-02 17:02:54', '2026-03-02 17:02:54'),
(4, ' Product Upload Guidelines Update', 'Dear Farmers,\r\n\r\nWe\'ve updated our product upload guidelines. Please ensure:\r\n✅ High-quality photos (minimum 800x600)\r\n✅ Accurate product descriptions\r\n✅ Updated stock levels\r\n✅ Competitive pricing\r\n\r\nProducts with incomplete information will not be approved. Check your dashboard for pending products.\r\n\r\nThank you for your cooperation!\r\nAdmin Team', 'farmers', NULL, 1, 1, '2026-03-10 23:59:59', 'update', 'active', '2026-03-02 17:07:57', '2026-03-02 17:07:57');

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `banner_id` int(11) NOT NULL,
  `banner_title` varchar(255) DEFAULT NULL,
  `banner_subtitle` varchar(500) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `banner_color` varchar(20) DEFAULT '#b85c38',
  `banner_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `package_size` varchar(20) DEFAULT '1kg',
  `total_price` decimal(10,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_info`
--

CREATE TABLE `contact_info` (
  `contact_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `value` text NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_info`
--

INSERT INTO `contact_info` (`contact_id`, `type`, `title`, `value`, `icon`, `display_order`, `status`, `created_at`) VALUES
(1, 'address', 'Address', 'Colombo, Sri Lanka', 'fas fa-map-marker-alt', 0, 'active', '2025-12-24 14:50:03'),
(2, 'phone', 'Phone', '+94 11 234 5678', 'fas fa-phone', 0, 'active', '2025-12-24 14:50:03'),
(3, 'email', 'Email', 'info@spiceceylon.com', 'fas fa-envelope', 0, 'active', '2025-12-24 14:50:03'),
(4, 'hours', 'Business Hours', 'Mon-Sat: 8:00 AM - 6:00 PM', 'fas fa-clock', 0, 'active', '2025-12-24 14:50:03');

-- --------------------------------------------------------

--
-- Table structure for table `faq_items`
--

CREATE TABLE `faq_items` (
  `faq_id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faq_items`
--

INSERT INTO `faq_items` (`faq_id`, `question`, `answer`, `category`, `display_order`, `status`, `created_at`) VALUES
(1, 'What is SpiceCeylon?', 'SpiceCeylon is a platform connecting Sri Lankan farmers with spice lovers worldwide.', 'General', 0, 'active', '2025-12-24 14:50:03'),
(2, 'Are your spices organic?', 'Yes, all our spices are 100% organic and grown without harmful chemicals.', 'Quality', 0, 'active', '2025-12-24 14:50:03'),
(3, 'How do you ensure quality?', 'We work directly with farmers and have strict quality control measures.', 'Quality', 0, 'active', '2025-12-24 14:50:03'),
(4, 'What shipping methods do you use?', 'We use reliable shipping partners to ensure timely delivery worldwide.', 'Shipping', 0, 'active', '2025-12-24 14:50:03');

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
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('admin','farmer','customer') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_role` enum('admin','farmer','customer') NOT NULL,
  `subject` varchar(255) DEFAULT '',
  `message` text NOT NULL,
  `related_order_id` int(11) DEFAULT NULL,
  `related_product_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `sender_role`, `receiver_id`, `receiver_role`, `subject`, `message`, `related_order_id`, `related_product_id`, `is_read`, `created_at`) VALUES
(1, 1, 'admin', 1, 'customer', 'Order #2 Confirmed', 'Your order has been confirmed and will be shipped soon.', NULL, NULL, 0, '2026-01-16 16:25:21'),
(3, 1, 'customer', 1, 'customer', 'Re: Order #2 Confirmed', 'thank you for the confirmation.', NULL, NULL, 0, '2026-02-26 14:36:31'),
(11, 2, 'farmer', 1, 'customer', 'Regarding your request: pandan leaves', 'can I know the exact date product need to be delivered?', NULL, NULL, 0, '2026-02-28 15:11:59'),
(12, 1, 'admin', 2, 'farmer', 'regarding the address changed ', 'because of the location address changed we need a confirmation letter for the verification to add the new location to the google map.', NULL, NULL, 0, '2026-03-02 16:40:40');

-- --------------------------------------------------------

--
-- Table structure for table `messages_new`
--

CREATE TABLE `messages_new` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('admin','farmer','customer') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_role` enum('admin','farmer','customer') NOT NULL,
  `subject` varchar(255) DEFAULT '',
  `message` text NOT NULL,
  `related_order_id` int(11) DEFAULT NULL,
  `related_product_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_roles` enum('all','customers','farmers','admins','specific') DEFAULT 'all',
  `target_user_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('admin','farmer','customer') DEFAULT 'admin',
  `is_important` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `title`, `message`, `target_roles`, `target_user_id`, `sender_id`, `sender_role`, `is_important`, `expires_at`, `created_at`) VALUES
(1, 'Welcome to SpiceCeylon!', 'Thank you for joining our community. Explore our fresh spices directly from Sri Lankan farmers.', 'all', NULL, 1, 'admin', 0, NULL, '2026-01-16 16:23:51'),
(2, 'New Features Available', 'Check out our new messaging system to communicate directly with admins and farmers.', 'all', NULL, 1, 'admin', 0, NULL, '2026-01-16 16:23:51'),
(3, 'Farmers: Stock Updates', 'Remember to update your stock levels regularly for accurate customer orders.', 'farmers', NULL, 1, 'admin', 0, NULL, '2026-01-16 16:23:51'),
(4, 'Customers: Order Tracking', 'You can now track your order status and receive updates via messages.', 'customers', NULL, 1, 'admin', 0, NULL, '2026-01-16 16:23:51'),
(5, 'Important: Harvest Season', 'The cinnamon harvest season is starting next week. Please prepare your stock.', 'farmers', NULL, 1, 'admin', 1, NULL, '2026-01-16 16:25:21'),
(6, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 17:44:45'),
(7, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Completed. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 17:44:57'),
(8, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 18:14:13'),
(9, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 18:15:23'),
(10, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 18:15:46'),
(11, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 18:33:58'),
(12, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 18:41:58'),
(13, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 18:42:20'),
(14, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 18:48:52'),
(15, 'Request Status Update', 'Your request for \'pandan leaves\' has been updated to: Accepted. ', 'specific', 1, 2, 'farmer', 0, NULL, '2026-03-01 18:49:12'),
(16, 'Password Security Update', 'Dear Users,\r\n\r\nTo enhance your account security, we\'re implementing two-factor authentication (2FA). \r\n\r\nPlease update your password and enable 2FA in your profile settings within the next 7 days.\r\n\r\nStay safe,\r\nSpiceCeylon Security Team', 'all', NULL, 1, 'admin', 1, NULL, '2026-03-02 17:18:47');

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
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `status` enum('Pending','Processing','Completed','Cancelled','Shipped','Delivered','Confirmed') DEFAULT 'Pending',
  `is_deleted` tinyint(1) DEFAULT 0,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `total`, `total_amount`, `shipping_fee`, `final_total`, `shipping_name`, `shipping_phone`, `shipping_address`, `shipping_city`, `shipping_postal`, `payment_method`, `payment_status`, `notes`, `admin_notes`, `status`, `is_deleted`, `order_date`, `created_at`, `updated_at`) VALUES
(2, 1, NULL, 1700.00, 200.00, 1900.00, 'sripali', '0543256712', 'kandy', 'kandy', '21000', 'cash_on_delivery', 'pending', '', '', 'Shipped', 0, '2025-12-05 13:04:51', '2025-12-05 13:04:51', '2026-03-03 18:55:50'),
(3, 1, NULL, 750.00, 200.00, 950.00, 'wer', '2345', 'sdfg', 'dfg', '234', 'credit_card', 'pending', '', '', 'Pending', 0, '2025-12-05 13:06:20', '2025-12-05 13:06:20', '2026-03-03 18:57:56'),
(5, 1, NULL, 8500.00, 200.00, 8700.00, 'ishara', '0352234657', 'colombo,srilanka', 'colombo', '1234', 'credit_card', 'pending', '2 packs for travel', '', 'Processing', 0, '2025-12-05 13:33:02', '2025-12-05 13:33:02', '2026-02-24 18:03:57'),
(6, 1, NULL, 840.00, 200.00, 1040.00, 'ishara', '12345', 'colombo', 'colombo', '21345', 'cash_on_delivery', 'pending', '', '', 'Confirmed', 0, '2025-12-05 13:54:53', '2025-12-05 13:54:53', '2026-02-28 15:18:58'),
(7, 1, NULL, 1600.00, 200.00, 1928.00, 'sripali', '0762541960', 'debathgama,kegalle', 'kegalle', '71000', 'cash_on_delivery', 'pending', 'no.', '', 'Confirmed', 0, '2025-12-24 13:56:54', '2025-12-24 13:56:54', '2026-02-28 14:50:21'),
(8, 1, NULL, 1870.00, 200.00, 2219.60, 'sripali', '0761234567', 'debathgama,kegalle', 'kandy', '12345', 'cash_on_delivery', 'pending', '', '', 'Delivered', 0, '2026-02-26 16:08:08', '2026-02-26 16:08:08', '2026-03-03 08:45:54'),
(9, 1, NULL, 2130.00, 200.00, 2500.40, 'sripali', '0761234567', 'debathgama,kegalle', 'maharagama', '10230', 'cash_on_delivery', 'pending', '', '', 'Confirmed', 0, '2026-02-28 16:01:46', '2026-02-28 16:01:46', '2026-03-03 18:43:08'),
(10, 5, NULL, 1300.00, 200.00, 1604.00, 'Peter Smith', '0777346982', 'no 07, main street, Colombo', 'Colombo', '1234', 'cash_on_delivery', 'pending', '', NULL, 'Pending', 0, '2026-03-04 13:10:42', '2026-03-04 13:10:42', '2026-03-04 13:10:42'),
(11, 1, NULL, 470.00, 200.00, 707.60, 'sripali', '0761234567', 'debathgama,kegalle', 'kegalle', '71000', 'cash_on_delivery', 'pending', '', NULL, 'Pending', 0, '2026-03-05 15:25:45', '2026-03-05 15:25:45', '2026-03-05 15:25:45');

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
(6, 6, 43, 2, 420.00, 840.00),
(7, 7, 25, 2, 450.00, 900.00),
(8, 7, 29, 2, 350.00, 700.00),
(9, 8, 28, 4, 420.00, 1680.00),
(10, 8, 17, 1, 190.00, 190.00),
(11, 9, 31, 1, 580.00, 580.00),
(12, 9, 3, 1, 890.00, 890.00),
(13, 9, 21, 1, 280.00, 280.00),
(14, 9, 7, 1, 380.00, 380.00),
(15, 10, 29, 1, 350.00, 350.00),
(16, 10, 25, 1, 450.00, 450.00),
(17, 10, 16, 1, 180.00, 180.00),
(18, 10, 19, 1, 320.00, 320.00),
(19, 11, 1, 1, 470.00, 470.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `history_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `changed_by_admin` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`history_id`, `order_id`, `status`, `changed_by_admin`, `notes`, `changed_at`) VALUES
(1, 3, 'Processing', 1, '', '2025-12-23 08:20:43'),
(2, 3, 'Completed', 1, '', '2025-12-23 13:29:26'),
(3, 3, 'Confirmed', 1, '', '2025-12-23 13:30:03'),
(4, 6, 'Processing', 1, '', '2025-12-24 04:39:38'),
(5, 2, 'Cancelled', 1, 'Cancelled by admin', '2025-12-24 04:39:53'),
(6, 2, 'Pending', 1, '', '2025-12-24 08:17:06'),
(7, 6, 'Shipped', 1, '', '2025-12-24 08:17:52'),
(8, 3, 'Delivered', 1, '', '2026-02-24 17:16:44'),
(9, 7, 'Completed', 1, '', '2026-02-24 18:03:45'),
(10, 5, 'Processing', 1, '', '2026-02-24 18:03:57'),
(11, 7, 'Confirmed', 1, '', '2026-02-28 14:50:21'),
(12, 6, 'Confirmed', 1, '', '2026-02-28 15:18:58'),
(13, 2, 'Completed', 1, '', '2026-02-28 15:19:33'),
(14, 2, 'Confirmed', 1, '', '2026-02-28 15:19:40'),
(15, 9, 'Shipped', 1, '', '2026-02-28 16:07:30'),
(16, 8, 'Delivered', 1, '', '2026-03-03 08:45:54'),
(17, 2, 'Pending', 1, '', '2026-03-03 17:59:09'),
(18, 3, 'Processing', 1, '', '2026-03-03 18:28:01'),
(19, 9, 'Pending', 1, '', '2026-03-03 18:33:54'),
(20, 9, 'Processing', 1, '', '2026-03-03 18:35:45'),
(21, 9, 'Shipped', 1, '', '2026-03-03 18:39:19'),
(22, 3, 'Cancelled', 1, 'Cancelled by admin', '2026-03-03 18:41:55'),
(23, 9, 'Confirmed', 1, '', '2026-03-03 18:43:08'),
(24, 2, 'Shipped', 1, '', '2026-03-03 18:55:50'),
(25, 3, 'Pending', 1, '', '2026-03-03 18:57:56');

-- --------------------------------------------------------

--
-- Table structure for table `page_content`
--

CREATE TABLE `page_content` (
  `content_id` int(11) NOT NULL,
  `page_name` varchar(50) NOT NULL,
  `section` varchar(100) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `page_content`
--

INSERT INTO `page_content` (`content_id`, `page_name`, `section`, `title`, `content`, `image`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'about', 'hero', 'About SpiceCeylon', 'Connecting farmers with spice lovers worldwide', '../assets/images/about/hero-bg.jpg', 0, '2025-12-24 14:21:27', '2025-12-26 07:49:18'),
(2, 'about', 'mission', 'Our Mission', 'To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.', '../assets/images/about/mission-image.jpg', 0, '2025-12-24 14:21:27', '2025-12-26 07:49:18'),
(3, 'about', 'values', 'Our Values', '[{\"icon\":\"fa-leaf\",\"title\":\"Authenticity\",\"desc\":\"100% pure Sri Lankan spices\"},{\"icon\":\"fa-handshake\",\"title\":\"Fair Trade\",\"desc\":\"Direct from farmers, fair prices\"},{\"icon\":\"fa-seedling\",\"title\":\"Sustainability\",\"desc\":\"Eco-friendly farming practices\"},{\"icon\":\"fa-heart\",\"title\":\"Quality\",\"desc\":\"Rigorous quality checks\"}]', '', 0, '2025-12-24 14:21:27', '2025-12-26 07:08:13'),
(4, 'home', 'hero_title', 'Hero Title', 'Welcome to SpiceCeylon', NULL, 0, '2025-12-24 14:50:03', '2025-12-24 14:50:03'),
(5, 'home', 'hero_subtitle', 'Hero Subtitle', 'Discover authentic Sri Lankan spices directly from farmers. Fresh, organic, and fair trade.', NULL, 0, '2025-12-24 14:50:03', '2025-12-24 14:50:03'),
(6, 'home', 'hero_button', 'Hero Button', 'Shop Now', NULL, 0, '2025-12-24 14:50:03', '2025-12-24 14:50:03'),
(7, 'home', 'hero_link', 'Hero Link', '#products', NULL, 0, '2025-12-24 14:50:03', '2025-12-24 14:50:03'),
(8, 'home', 'feature_1', 'Spice Varieties', 'Fresh spices from Sri Lanka', NULL, 0, '2025-12-24 14:50:03', '2025-12-24 14:50:03'),
(9, 'home', 'feature_2', 'Organic', '100% organic products', NULL, 0, '2025-12-24 14:50:03', '2025-12-24 14:50:03'),
(10, 'home', 'feature_3', 'From Farmers', 'Direct from farmers', NULL, 0, '2025-12-24 14:50:03', '2025-12-24 14:50:03'),
(11, 'home', 'feature_4', 'Support', '24/7 customer support', NULL, 0, '2025-12-24 14:50:03', '2025-12-24 14:50:03'),
(12, 'about', 'story', 'Our Story', '<div>Founded in 2020, SpiceCeylon began with a simple vision: to bring authentic Sri Lankan spices directly from farmers to consumers worldwide.</div><div>Our journey started in the heart of Sri Lanka\'s spice-growing regions, where we witnessed first-hand the challenges faced by local farmers in reaching global markets. We created a platform that eliminates middlemen, ensuring farmers receive fair prices while customers enjoy premium quality spices at reasonable rates.</div><div>Today, we work with over 200 farmers across Sri Lanka, bringing you spices that are not only delicious but also ethically sourced and sustainably grown.</div><div>To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.</p></div><div><br></div>', '../assets/images/about/story-image.jpg', 0, '2025-12-26 07:06:32', '2025-12-26 07:49:18'),
(13, 'about', 'timeline', 'Our Journey', '[{\"year\":\"2020\",\"title\":\"The Beginning\",\"desc\":\"SpiceCeylon founded with 5 partner farmers in Kandy\"},{\"year\":\"2021\",\"title\":\"Expansion\",\"desc\":\"Expanded to 50 farmers across 3 regions. Launched e-commerce platform\"},{\"year\":\"2022\",\"title\":\"International\",\"desc\":\"Started exporting to 10 countries. Received organic certification\"},{\"year\":\"2023\",\"title\":\"Today\",\"desc\":\"200+ farmers, 50+ spice varieties, serving customers worldwide\"}]', '', 0, '2025-12-26 07:06:32', '2025-12-26 07:08:13'),
(14, 'about', 'stats', 'Our Stats', '[{\"number\":\"200+\",\"label\":\"Partner Farmers\"},{\"number\":\"50+\",\"label\":\"Spice Varieties\"},{\"number\":\"10,000+\",\"label\":\"Happy Customers\"},{\"number\":\"25+\",\"label\":\"Countries Served\"}]', '', 0, '2025-12-26 07:06:32', '2025-12-26 07:08:13'),
(15, 'about', 'hero', 'About SpiceCeylon', 'Connecting farmers with spice lovers worldwide', NULL, 0, '2026-03-04 17:06:05', '2026-03-04 17:06:05'),
(16, 'about', 'story', 'Our Story', '<div>Founded in 2020, SpiceCeylon began with a simple vision: to bring authentic Sri Lankan spices directly from farmers to consumers worldwide.</div><div>Our journey started in the heart of Sri Lanka\'s spice-growing regions, where we witnessed first-hand the challenges faced by local farmers in reaching global markets. We created a platform that eliminates middlemen, ensuring farmers receive fair prices while customers enjoy premium quality spices at reasonable rates.</div><div>Today, we work with over 200 farmers across Sri Lanka, bringing you spices that are not only delicious but also ethically sourced and sustainably grown.</div><div>To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.<p></p></div><div><br></div>', '../assets/images/about/story-image.jpg', 0, '2026-03-04 17:06:06', '2026-03-04 17:14:20'),
(17, 'about', 'mission', 'Our Mission', 'To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.', '../assets/images/about/mission-image.jpg', 0, '2026-03-04 17:06:06', '2026-03-04 17:15:17'),
(18, 'about', 'values', 'Our Values', '[{\"icon\":\"fa-leaf\",\"title\":\"Authenticity\",\"desc\":\"100% pure Sri Lankan spices\"},{\"icon\":\"fa-handshake\",\"title\":\"Fair Trade\",\"desc\":\"Direct from farmers, fair prices\"},{\"icon\":\"fa-seedling\",\"title\":\"Sustainability\",\"desc\":\"Eco-friendly farming practices\"},{\"icon\":\"fa-heart\",\"title\":\"Quality\",\"desc\":\"Rigorous quality checks\"}]', NULL, 0, '2026-03-04 17:06:06', '2026-03-04 17:06:06'),
(19, 'about', 'timeline', 'Our Journey', '[{\"year\":\"2020\",\"title\":\"The Beginning\",\"desc\":\"SpiceCeylon founded with 5 partner farmers in Kandy\"},{\"year\":\"2021\",\"title\":\"Expansion\",\"desc\":\"Expanded to 50 farmers across 3 regions. Launched e-commerce platform\"},{\"year\":\"2022\",\"title\":\"International\",\"desc\":\"Started exporting to 10 countries. Received organic certification\"},{\"year\":\"2023\",\"title\":\"Today\",\"desc\":\"200+ farmers, 50+ spice varieties, serving customers worldwide\"}]', NULL, 0, '2026-03-04 17:06:06', '2026-03-04 17:06:06'),
(20, 'about', 'stats', 'Our Stats', '[{\"number\":\"200+\",\"label\":\"Partner Farmers\"},{\"number\":\"40+\",\"label\":\"Spice Varieties\"},{\"number\":\"10,000+\",\"label\":\"Happy Customers\"},{\"number\":\"25+\",\"label\":\"Countries Served\"}]', NULL, 0, '2026-03-04 17:06:06', '2026-03-04 17:06:06'),
(21, 'about', 'hero', 'About SpiceCeylon', 'Connecting farmers with spice lovers worldwide', NULL, 0, '2026-03-04 17:11:04', '2026-03-04 17:11:04'),
(22, 'about', 'story', 'Our Story', '<div>Founded in 2020, SpiceCeylon began with a simple vision: to bring authentic Sri Lankan spices directly from farmers to consumers worldwide.</div><div>Our journey started in the heart of Sri Lanka\'s spice-growing regions, where we witnessed first-hand the challenges faced by local farmers in reaching global markets. We created a platform that eliminates middlemen, ensuring farmers receive fair prices while customers enjoy premium quality spices at reasonable rates.</div><div>Today, we work with over 200 farmers across Sri Lanka, bringing you spices that are not only delicious but also ethically sourced and sustainably grown.</div><div>To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.<p></p></div><div><br></div>', '../assets/images/about/story-image.jpg', 0, '2026-03-04 17:11:04', '2026-03-04 17:14:42'),
(23, 'about', 'mission', 'Our Mission', 'To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.', '../assets/images/about/mission-image.jpg', 0, '2026-03-04 17:11:04', '2026-03-04 17:15:28'),
(24, 'about', 'values', 'Our Values', '[{\"icon\":\"fa-leaf\",\"title\":\"Authenticity\",\"desc\":\"100% pure Sri Lankan spices\"},{\"icon\":\"fa-handshake\",\"title\":\"Fair Trade\",\"desc\":\"Direct from farmers, fair prices\"},{\"icon\":\"fa-seedling\",\"title\":\"Sustainability\",\"desc\":\"Eco-friendly farming practices\"},{\"icon\":\"fa-heart\",\"title\":\"Quality\",\"desc\":\"Rigorous quality checks\"}]', NULL, 0, '2026-03-04 17:11:04', '2026-03-04 17:11:04'),
(25, 'about', 'timeline', 'Our Journey', '[{\"year\":\"2020\",\"title\":\"The Beginning\",\"desc\":\"SpiceCeylon founded with 5 partner farmers in Kandy\"},{\"year\":\"2021\",\"title\":\"Expansion\",\"desc\":\"Expanded to 50 farmers across 3 regions. Launched e-commerce platform\"},{\"year\":\"2022\",\"title\":\"International\",\"desc\":\"Started exporting to 10 countries. Received organic certification\"},{\"year\":\"2023\",\"title\":\"Today\",\"desc\":\"200+ farmers, 50+ spice varieties, serving customers worldwide\"}]', NULL, 0, '2026-03-04 17:11:04', '2026-03-04 17:11:04'),
(26, 'about', 'stats', 'Our Stats', '[{\"number\":\"200+\",\"label\":\"Partner Farmers\"},{\"number\":\"50+\",\"label\":\"Spice Varieties\"},{\"number\":\"10,000+\",\"label\":\"Happy Customers\"},{\"number\":\"25+\",\"label\":\"Countries Served\"}]', NULL, 0, '2026-03-04 17:11:04', '2026-03-04 17:11:04'),
(27, 'about', 'hero', 'About SpiceCeylon', 'Connecting farmers with spice lovers worldwide', NULL, 0, '2026-03-04 17:17:39', '2026-03-04 17:17:39'),
(28, 'about', 'story', 'Our Story', '<div>Founded in 2020, SpiceCeylon began with a simple vision: to bring authentic Sri Lankan spices directly from farmers to consumers worldwide.</div><div>Our journey started in the heart of Sri Lanka\'s spice-growing regions, where we witnessed first-hand the challenges faced by local farmers in reaching global markets. We created a platform that eliminates middlemen, ensuring farmers receive fair prices while customers enjoy premium quality spices at reasonable rates.</div><div>Today, we work with over 200 farmers across Sri Lanka, bringing you spices that are not only delicious but also ethically sourced and sustainably grown.</div><div>To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.<p></p></div><div><br></div>', '../assets/images/about/story-image.jpg', 0, '2026-03-04 17:17:39', '2026-03-04 17:20:32'),
(29, 'about', 'mission', 'Our Mission', 'To bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.', '../assets/images/about/mission-image.jpg', 0, '2026-03-04 17:17:39', '2026-03-04 17:20:05'),
(30, 'about', 'values', 'Our Values', '[{\"icon\":\"fa-leaf\",\"title\":\"Authenticity\",\"desc\":\"100% pure Sri Lankan spices\"},{\"icon\":\"fa-handshake\",\"title\":\"Fair Trade\",\"desc\":\"Direct from farmers, fair prices\"},{\"icon\":\"fa-seedling\",\"title\":\"Sustainability\",\"desc\":\"Eco-friendly farming practices\"},{\"icon\":\"fa-heart\",\"title\":\"Quality\",\"desc\":\"Rigorous quality checks\"}]', NULL, 0, '2026-03-04 17:17:39', '2026-03-04 17:17:39'),
(31, 'about', 'timeline', 'Our Journey', '[{\"year\":\"2020\",\"title\":\"The Beginning\",\"desc\":\"SpiceCeylon founded with 5 partner farmers in Kandy\"},{\"year\":\"2021\",\"title\":\"Expansion\",\"desc\":\"Expanded to 50 farmers across 3 regions. Launched e-commerce platform\"},{\"year\":\"2022\",\"title\":\"International\",\"desc\":\"Started exporting to 10 countries. Received organic certification\"},{\"year\":\"2023\",\"title\":\"Today\",\"desc\":\"200+ farmers, 50+ spice varieties, serving customers worldwide\"}]', NULL, 0, '2026-03-04 17:17:39', '2026-03-04 17:17:39'),
(32, 'about', 'stats', 'Our Stats', '[{\"number\":\"200+\",\"label\":\"Partner Farmers\"},{\"number\":\"40+\",\"label\":\"Spice Varieties\"},{\"number\":\"10,000+\",\"label\":\"Happy Customers\"},{\"number\":\"25+\",\"label\":\"Countries Served\"}]', NULL, 0, '2026-03-04 17:17:39', '2026-03-04 17:17:39');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `user_type` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`reset_id`, `email`, `token`, `expiry`, `used`, `user_type`, `created_at`) VALUES
(1, 's@gmail.com', '31811f3b51a0c627d1adad075485f822b69a1a33872d724e95256bd10ebeaec6085654ec2e9c975d88d1a4606531bb345d46', '2026-02-24 11:47:54', 0, 'user', '2026-02-24 10:32:54');

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
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `method_id` int(11) NOT NULL,
  `method_name` varchar(255) NOT NULL,
  `method_type` varchar(50) DEFAULT NULL,
  `credentials` text DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`method_id`, `method_name`, `method_type`, `credentials`, `is_enabled`, `sort_order`, `created_at`) VALUES
(1, 'Credit/Debit Card', 'stripe', '', 0, 1, '2025-12-24 07:24:45'),
(2, 'PayPal', 'paypal', NULL, 1, 2, '2025-12-24 07:24:45'),
(3, 'Bank Transfer', 'bank', NULL, 1, 3, '2025-12-24 07:24:45'),
(4, 'Cash on Delivery', 'cod', NULL, 1, 4, '2025-12-24 07:24:45');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `farmer_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT 'Spices',
  `price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_approved` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `farmer_id`, `name`, `description`, `category`, `price`, `stock`, `image`, `status`, `created_at`, `admin_approved`, `approved_by`, `approved_at`, `rejection_reason`) VALUES
(1, 2, 'Cinnamon- කුරුඳු (Kurundu)', 'Sweet and aromatic Ceylon cinnamon bark, true cinnamon with delicate flavor.', 'Whole Spices', 470.00, 170, 'cinnamon.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2026-03-02 18:53:14', NULL),
(2, 2, 'Cardamom - එනසාල් (Enasal)\r\n\r\n', 'Aromatic green cardamom pods with intense flavor and medicinal properties.', 'Whole Spices', 1260.00, 124, 'cardamom.jpg', 'Pending', '2025-11-23 11:14:12', 'approved', 1, '2025-12-23 18:36:24', NULL),
(3, 2, 'Cloves- කරාබුනැටි (Karabu Nati)\r\n\r\n', 'Aromatic flower buds with warm, sweet flavor, perfect for meat dishes and teas.', 'Whole Spices', 890.00, 120, 'cloves.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-23 18:36:16', NULL),
(4, 3, 'Nutmeg - සාදික්කා (Sadikka)', 'Warm, nutty spice perfect for both sweet and savory dishes, freshly ground.', 'Whole Spices', 750.00, 95, 'nutmeg.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:59', NULL),
(5, 3, 'Mace- වසාවාසි (Wasawasi)', 'Delicate spice from nutmeg covering, with subtle flavor for baking and sauces.', 'Whole Spices', 920.00, 80, 'mace.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:52:02', NULL),
(6, 3, 'Black Pepper - ගම්මිරිස් (Gammiris)\r\n\r\n', 'Freshly ground black pepper with robust flavor and heat, freshly harvested.', 'Whole Spices', 650.00, 180, 'black_pepper.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:38:21', NULL),
(7, 3, 'Cumin Seeds - සූදුරු (Suduru)', 'Warm, earthy seeds with distinctive aroma, essential for curry powders.', 'Spices', 380.00, 160, 'cumin_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:52:05', NULL),
(8, 3, 'Coriander Seeds - කොත්තමල්ලි (Koththamalli)', 'Mild, citrusy seeds essential for curry powders and spice blends.', 'Spices', 290.00, 170, 'coriander_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2026-03-04 13:59:28', NULL),
(9, 3, 'Fennel Seeds - මාදුරු (Maduru)', 'Sweet, licorice-flavored seeds for cooking and digestive health.', 'Whole Spices', 350.00, 140, 'fennel_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:52:09', NULL),
(10, 3, 'Fenugreek Seeds - උළුහාල් (Uluhal)', 'Bitter seeds used in curry powders and traditional medicinal preparations.', 'Whole Spices', 340.00, 110, 'fenugreek_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-28 17:27:18', NULL),
(11, 3, 'Mustard Seeds - අබ (Aba)', 'Tiny seeds that pack a punch of flavor when tempered in oil.', 'Whole Spices', 310.00, 130, 'mustard_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:52:12', NULL),
(12, 3, 'Turmeric - කහ (Kaha)', 'Golden yellow spice with earthy flavor and powerful health benefits.', 'Roots & Bulbs', 320.00, 200, 'turmeric.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 13:36:00', NULL),
(13, 3, 'Vanilla - වැනිලා', 'Premium vanilla beans with rich, sweet aroma and flavor for baking.', 'Fruits & Pods', 2800.00, 80, 'vanilla.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:52:14', NULL),
(14, 3, 'Sesame Seeds - තල (Thala)', 'Nutty seeds perfect for both sweets and savory dishes, rich in calcium.', 'Whole Spices', 380.00, 150, 'sesame_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'pending', NULL, NULL, NULL),
(15, 3, 'Pandan Leaves - රම්පේ (Rampé)', 'Fragrant leaves used for flavoring rice, desserts, and drinks.', 'Leaves & Herbs', 220.00, 100, 'pandan_leaves.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 15:26:28', NULL),
(16, 3, 'Curry Leaves - කරපිංචා (Karapincha)', 'Aromatic leaves essential for Sri Lankan tempering and curry dishes.', 'Leaves & Herbs', 180.00, 150, 'curry_leaves.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 15:26:33', NULL),
(17, 3, 'Lemongrass - සේර (Sera)', 'Citrusy stalks used for teas, curries, and soups, freshly cut.', 'Leaves & Herbs', 190.00, 120, 'lemongrass.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:52:18', NULL),
(18, 3, 'Sri Lankan Ginger - ඉඟුරු (Inguru)', 'Fresh, aromatic ginger with strong flavor and medicinal properties.', 'Roots & Bulbs', 280.00, 130, 'ginger.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 15:26:39', NULL),
(19, 3, 'Garlic - සුදු ලූනු (Sudu Lunu)', 'Fresh local garlic bulbs with robust flavor for cooking.', 'Roots & Bulbs', 320.00, 200, 'garlic.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:52:21', NULL),
(20, 3, 'Ceylon Citron / Lemon - දෙහි (Dehi)', 'Fresh Ceylon lemons with aromatic zest and juice.', 'Fruits & Pods', 150.00, 180, 'lemon.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 15:26:43', NULL),
(21, 2, 'Bay Leaves -  බේ කොළ (Bay Kola)', 'Aromatic leaves ideal for flavoring soups, stews, and rice dishes.', 'Leaves & Herbs', 280.00, 120, 'bay_leaves.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:37:44', NULL),
(22, 2, 'Star Anise- තරු අසමෝදගම් (tharu asamodagam)', 'Star-shaped spice with distinctive licorice flavor for soups and meats.\r\n\r\nUsage: Used to flavor rice (biryani), curries, and sometimes tea.', 'Whole Spices', 950.00, 100, 'star_anise.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:37:48', NULL),
(23, 2, 'Asafoetida- පෙරුම්කායම් (Perumkayam)\r\n\r\n', 'Strong aromatic resin used as a flavor enhancer in vegetarian cooking.\r\n\r\nUses: Primarily used for its strong savory, onion-like flavor in tempering (curries) and as a digestive aid.', 'Specialty Spices', 680.00, 90, 'asafoetida.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:42:11', NULL),
(24, 2, 'Celery Seeds - සැල්දිරි ඇට (Saldeeri Aeta)', 'Tiny seeds with intense celery flavor for cooking and pickling.', 'Whole Spices', 420.00, 110, 'celery_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:37:53', NULL),
(25, 2, 'Ceylon Chili / Bird\'s Eye Chili - කොච්චි (Kochchi)', 'Small but extremely hot chilies essential for Sri Lankan cuisine.', 'Chilies & Peppers', 450.00, 140, 'ceylon_chili.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:50:52', NULL),
(26, 2, 'Chili Powder - මිරිස් කුඩු (Miris Kudu)', 'Finely ground red chili powder for adding heat and color to dishes.', 'Chilies & Peppers', 380.00, 160, 'chili_powder.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:50:55', NULL),
(27, 2, 'Black Mustard Seeds - කළු අබ (Kalu Aba)', 'Sharp, pungent mustard seeds for tempering and pickling.', 'Whole Spices', 330.00, 120, 'black_mustard_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:50:58', NULL),
(28, 2, 'Goraka - ගොරකා (Goraka)', 'Sour fruit used as a natural souring agent in Sri Lankan cooking.', 'Fruits & Pods', 420.00, 90, 'goraka.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:00', NULL),
(29, 2, 'Tamarind - සියඹලා (Siyambala)', 'Tangy fruit pulp used for adding sour flavor to curries and chutneys.', 'Fruits & Pods', 350.00, 110, 'tamarind.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:03', NULL),
(30, 2, 'Screw Pine / Kewra- වැටකෙයියා (Wetakeiya)', 'Aromatic flowers used for flavoring desserts and rice dishes.\r\n\r\nKewra Water/Essence: The fragrant male flowers of the Wetakeiya plant are used to produce kewra water or kewra essence, a popular flavoring agent in South Asian cuisine (rice dishes, desserts).\r\n\r\nLeaves: The leaves of the screw pine, or closely related Pandan (Pandanus amaryllifolius), are used in Southeast Asian and Sri Lankan cooking to add a distinct aroma, often described as similar to basmati rice. \r\n\r\n', 'Fruits & Pods', 1200.00, 60, 'screw_pine.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:06', NULL),
(31, 2, 'Licorice Powder - වැල්මී කුඩු (Welmee Kudu)', 'Sweet, aromatic powder used in teas and traditional medicine.', 'Powders & Pastes', 580.00, 85, 'licorice_powder.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:08', NULL),
(32, 2, 'Annatto Seeds - කහට කුරුඳු (Kahata Kurundu)', 'Natural coloring seeds that impart yellow-orange color to food.', 'Whole Spices', 410.00, 95, 'annatto_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:10', NULL),
(34, 2, 'Dill -  ඩිල්', 'Fresh dill leaves with mild anise flavor for salads and fish dishes.', 'Leaves & Herbs', 270.00, 110, 'dill.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:14', NULL),
(35, 2, 'Sweet Flag - වදකහ (Wadakaha)', 'A spice and Medicinal herb with aromatic roots, used in traditional remedies.\r\nOften used for digestive issues, stomach ailments, and to add a unique aroma to food.', 'Leaves & Herbs', 890.00, 70, 'sweet_flag.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:38:25', NULL),
(36, 2, 'Saffron - කුංකුම (Kunkuma)', 'World\'s most precious spice, hand-picked crimson threads for premium dishes.', 'Specialty Spices', 8500.00, 30, 'saffron.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:18', NULL),
(37, 2, 'Poppy Seeds - පොපි ඇට (Popi äta)', 'Tiny blue seeds perfect for baking and thickening curries.', 'Whole Spices', 520.00, 95, 'poppy_seeds.jpg', 'Pending', '2025-11-23 11:14:12', 'approved', 1, '2025-12-28 17:27:07', NULL),
(38, 2, 'Caraway Seeds - සුවඳ සූදුරු (Suwanda Suduru)', 'Aromatic seeds traditionally used in breads and European dishes.\r\n\r\nUsage: Used in local spice blends, herbal remedies, curries, and to relieve digestive issues.\r\n\r\nDistinction: Caraway is different from common Sri Lankan cumin (Suduru).', 'Whole Spices', 480.00, 100, 'caraway_seeds.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:27', NULL),
(39, 2, 'Juniper Berries - (Haubera, Aaraar.)', 'Aromatic berries excellent for flavoring meats, sauces, and gin.', 'Whole Spices', 720.00, 85, 'juniper_berries.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:29', NULL),
(40, 2, 'Sumac', 'Tangy crimson spice with lemony flavor for seasoning and marinades.', 'Specialty Spices', 580.00, 110, 'sumac.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:33', NULL),
(41, 2, 'Roasted Curry Powder - බැදපු කරි කුඩු (Badapu Kari Kudu)', 'Traditional Sri Lankan roasted curry powder with complex flavors.', 'Powders & Pastes', 550.00, 150, 'roasted_curry_powder.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:35', NULL),
(42, 2, 'Unroasted Curry Powder - අමු කරි කුඩු (Amu Kari Kudu)', 'Mild, unroasted curry powder for light-colored dishes.', 'Powders & Pastes', 520.00, 140, 'unroasted_curry_powder.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:37', NULL),
(43, 2, 'Chili Paste - මිරිස් පේස්ට් (Miris Paste)', 'Freshly ground chili paste for instant heat and flavor in cooking.', 'Chilies & Peppers', 420.00, 120, 'chili_paste.jpg', 'Approved', '2025-11-23 11:14:12', 'approved', 1, '2025-12-24 14:51:39', NULL),
(44, 2, 'Dried Ginger Sweet Candy- වියළි ඉඟුරු රසකැවිල්ල (Viyali Inguru Rasakawilla)', '200gm Dried Ginger Sweet Candy , Flavoured Ginger Slices | Mouth Fresher, Cough Relief, Immunity Booster', 'Specialty Spices', 3450.00, 5, 'Candied-Ginger.jpg', 'Pending', '2026-02-28 14:03:49', 'rejected', 1, '2026-03-04 14:03:19', 'only the spices accepted.products made from using spices won\'t accepted.');

-- --------------------------------------------------------

--
-- Table structure for table `product_requests`
--

CREATE TABLE `product_requests` (
  `request_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity_requested` int(11) DEFAULT 1,
  `urgency` enum('Low','Medium','High') DEFAULT 'Medium',
  `status` enum('Pending','Reviewed','Approved','Rejected','Completed') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `assigned_farmer_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_requests`
--

INSERT INTO `product_requests` (`request_id`, `customer_id`, `product_name`, `category`, `description`, `quantity_requested`, `urgency`, `status`, `admin_notes`, `created_at`, `updated_at`, `assigned_farmer_id`) VALUES
(1, 1, 'pandan leaves', 'Leaves & Herbs', 'need for overseas travelling.', 1, 'High', '', '[Farmer Update: 2026-03-01 19:49:12 - Status changed to: Accepted]', '2025-12-24 10:32:45', '2026-03-01 18:49:12', 2),
(2, 1, 'vanilla', 'Specialty Spices', 'need dried vanilla 10kg', 10, 'Medium', 'Approved', NULL, '2026-01-22 19:58:25', '2026-03-02 16:44:33', 2),
(3, 1, 'turmeric', 'Roots & Bulbs', 'need for shop', 5, 'Medium', 'Approved', NULL, '2026-01-22 19:59:52', '2026-01-22 20:15:33', 3),
(4, 1, 'Cinnoman', 'Whole Spices', 'can i get the Cinnamon powders 2kg?', 2, 'Low', 'Approved', NULL, '2026-02-26 15:41:31', '2026-03-04 12:46:48', 3),
(5, 5, 'can i know if there is products made from ginger spices?', 'Whole Spices', 'can i know if there is products made from ginger spices? if there is i would like to buy it.', 5, 'Low', 'Approved', NULL, '2026-03-04 13:10:12', '2026-03-04 13:15:28', 3);

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
  `customer_id` int(11) NOT NULL,
  `spice_name` varchar(100) NOT NULL,
  `quantity_needed` decimal(10,2) NOT NULL,
  `message` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `request_history`
--

CREATE TABLE `request_history` (
  `history_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `changed_by_admin` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `request_history`
--

INSERT INTO `request_history` (`history_id`, `request_id`, `changed_by_admin`, `old_status`, `new_status`, `notes`, `changed_at`) VALUES
(1, 1, 1, 'Pending', 'Approved', 'Assigned to farmer: dilshani (ID: 2)', '2026-01-22 19:49:52'),
(2, 3, 1, 'Pending', 'Approved', 'Assigned to farmer: ranjith edirisinghe (ID: 3)', '2026-01-22 20:15:33'),
(7, 2, 1, 'Pending', 'Approved', 'Assigned to farmer: dilshani (ID: 2)', '2026-03-02 16:44:34'),
(8, 4, 1, 'Pending', 'Approved', 'Assigned to farmer: ranjith edirisinghe (ID: 3)', '2026-03-04 12:46:48'),
(9, 5, 1, 'Pending', 'Reviewed', 'Marked as reviewed by admin', '2026-03-04 13:14:17'),
(10, 5, 1, 'Reviewed', 'Approved', 'Assigned to farmer: ranjith edirisinghe (ID: 3)', '2026-03-04 13:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `request_notifications`
--

CREATE TABLE `request_notifications` (
  `notification_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `customer_id`, `product_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 36, 5, 'best product', '2025-12-26 13:27:11'),
(2, 1, 24, 5, 'This Celery Seed was perfect for what I needed for my pickle relish and pickles this year. It is very cot effective and I will most certainly be ordering more.', '2026-02-23 16:50:51');

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
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'SpiceCeylon', '2025-12-24 07:09:25'),
(2, 'site_email', 'info@spiceceylon.com', '2025-12-24 07:09:25'),
(3, 'site_phone', '+94 11 234 5678', '2025-12-24 07:09:25'),
(4, 'site_address', 'Colombo, Sri Lanka', '2025-12-24 07:09:25'),
(5, 'currency', 'Rs.', '2025-12-24 07:09:25'),
(6, 'meta_description', 'Authentic Sri Lankan spices direct from farmers', '2025-12-24 07:09:25'),
(7, 'meta_keywords', 'ceylon spices, sri lankan spices, organic spices, farm fresh', '2025-12-24 07:09:25'),
(8, 'facebook', 'https://facebook.com/spiceceylon', '2025-12-24 07:09:25'),
(9, 'instagram', 'https://instagram.com/spiceceylon', '2025-12-24 07:09:25'),
(10, 'twitter', 'https://twitter.com/spiceceylon', '2025-12-24 07:09:25'),
(11, 'youtube', 'https://youtube.com/spiceceylon', '2025-12-24 07:09:25'),
(12, 'linkedin', 'https://linkedin.com/company/spiceceylon', '2025-12-24 07:09:25'),
(13, 'free_shipping_min', '5000', '2026-03-04 13:48:09'),
(14, 'local_shipping_fee', '300', '2026-03-04 14:42:26'),
(15, 'international_shipping_fee', '2500', '2026-03-04 13:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `shipping_zones`
--

CREATE TABLE `shipping_zones` (
  `zone_id` int(11) NOT NULL,
  `zone_name` varchar(255) NOT NULL,
  `countries` text DEFAULT NULL,
  `shipping_fee` decimal(10,2) DEFAULT 0.00,
  `delivery_time` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shipping_zones`
--

INSERT INTO `shipping_zones` (`zone_id`, `zone_name`, `countries`, `shipping_fee`, `delivery_time`, `is_active`, `created_at`) VALUES
(1, 'Colombo District', ' Sri Lanka (Colombo)', 200.00, '1- 3 business days', 1, '2026-03-04 13:49:27'),
(2, 'Other Districts', ' Sri Lanka (Other)', 300.00, ' 3-6 days', 1, '2026-03-04 14:01:16'),
(3, 'Asia', ' India, Singapore, Malaysia', 2000.00, ' 7-10 days', 1, '2026-03-04 14:03:01'),
(5, 'Europe', ' UK, Germany, France', 3500.00, ' 7-14 business days', 1, '2026-03-04 14:13:52');

-- --------------------------------------------------------

--
-- Table structure for table `sliders`
--

CREATE TABLE `sliders` (
  `slider_id` int(11) NOT NULL,
  `slider_title` varchar(255) NOT NULL,
  `slider_subtitle` text DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `slider_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `member_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`member_id`, `name`, `role`, `bio`, `image`, `display_order`, `status`, `created_at`) VALUES
(1, 'Kumar Perera', 'Founder & CEO', 'Former agricultural officer with 15+ years experience', '../assets/images/team/kumar-perera.jpg', 0, 'active', '2025-12-24 14:21:27'),
(2, 'Anjali Fernando', 'Head of Operations', 'Expert in supply chain management', '../assets/images/team/anjali-fernando.jpg', 0, 'active', '2025-12-24 14:21:27'),
(3, 'Rajith Silva', 'Farmer Relations', 'Working directly with 200+ farmers', '../assets/images/team/rajith-silva.jpg', 0, 'active', '2025-12-24 14:21:27');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','active','inactive','suspended','rejected') NOT NULL DEFAULT 'pending' COMMENT 'User account status: pending=waiting approval, approved=approved but not active yet',
  `email_notifications` tinyint(1) DEFAULT 1,
  `push_notifications` tinyint(1) DEFAULT 1,
  `last_notification_check` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `phone`, `address`, `role`, `farm_location`, `profile_image`, `is_registered`, `created_at`, `status`, `email_notifications`, `push_notifications`, `last_notification_check`) VALUES
(1, 'sripali', 's@gmail.com', '$2y$10$fvsQh6aRyY.xL1BP10oqVO7D/YUbHhvpwyujxLAiVs4FchN/wPjDG', '0761234567', 'debathgama,kegalle', 'customer', '', 'user_1_1764943140.jpg', 1, '2025-11-15 07:23:11', 'active', 1, 1, '2026-03-06 15:14:55'),
(2, 'dilshani', 'd@gmail.com', '$2y$10$L4aUm6Xtp59TDz.YI09bO.rgZn0XlB8GWF/KWzeUtfn1AydINuQ52', '0523465789', '143/B/, 1st Ln, Dehianga, Nuwara Eliya', 'farmer', 'kandy / Nuwara Eliya', 'default-avatar.jpg', 1, '2025-11-15 07:48:09', 'active', 1, 1, '2026-03-03 12:34:13'),
(3, 'ranjith edirisinghe', 'r@gmail.com', '$2y$10$W7hhU7TOf4g7vZOlwg7sbeolMA5fQ0mH.m15aDuYICLEv8SGFq8CO', '0352258339', 'nuwaraeliya', 'farmer', 'nuwaraeliya', 'default-avatar.jpg', 1, '2025-12-23 13:03:52', 'active', 1, 1, '2026-03-05 15:30:35'),
(5, 'Peter Smith', 'peter@gmail.com', '$2y$10$ELuqYyfTpHBXpbdHHQayXuRzJsV2LpWLOT.n.ElQw8ChDa18PR6gW', '0777346982', 'no 07, main street, Colombo', 'customer', '', 'user_5_1772457313.jpg', 1, '2026-02-23 09:37:09', 'pending', 1, 1, '2026-03-04 13:07:25');

-- --------------------------------------------------------

--
-- Table structure for table `user_announcement_status`
--

CREATE TABLE `user_announcement_status` (
  `status_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_announcement_status`
--

INSERT INTO `user_announcement_status` (`status_id`, `announcement_id`, `user_id`, `is_read`, `read_at`, `created_at`) VALUES
(1, 1, 1, 1, '2026-02-25 12:18:37', '2026-02-25 04:21:30'),
(2, 1, 2, 1, '2026-02-25 12:45:02', '2026-02-25 04:21:30'),
(3, 1, 3, 0, NULL, '2026-02-25 04:21:30'),
(4, 2, 1, 1, '2026-02-25 12:18:37', '2026-02-25 04:21:38'),
(5, 2, 2, 0, NULL, '2026-02-25 04:21:38'),
(6, 2, 3, 0, NULL, '2026-02-25 04:21:38'),
(7, 3, 1, 0, NULL, '2026-03-02 17:02:54'),
(8, 4, 2, 0, NULL, '2026-03-02 17:07:57'),
(9, 4, 3, 0, NULL, '2026-03-02 17:07:57');

-- --------------------------------------------------------

--
-- Stand-in structure for view `user_notifications_view`
-- (See below for the actual view)
--
CREATE TABLE `user_notifications_view` (
`notification_id` int(11)
,`title` varchar(255)
,`message` text
,`target_roles` enum('all','customers','farmers','admins','specific')
,`sender_id` int(11)
,`sender_role` enum('admin','farmer','customer')
,`is_important` tinyint(1)
,`expires_at` timestamp
,`created_at` timestamp
,`user_id` int(11)
,`user_name` varchar(255)
,`user_email` varchar(255)
,`user_role` enum('customer','farmer')
,`is_read` int(4)
,`read_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `user_notification_status`
--

CREATE TABLE `user_notification_status` (
  `status_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notification_status`
--

INSERT INTO `user_notification_status` (`status_id`, `notification_id`, `user_id`, `is_read`, `read_at`, `created_at`) VALUES
(1, 3, 2, 1, '2026-01-16 18:41:20', '2026-01-16 18:41:20'),
(2, 1, 1, 1, '2026-01-22 20:02:02', '2026-01-22 20:02:02'),
(3, 2, 1, 1, '2026-01-22 20:02:11', '2026-01-22 20:02:07'),
(4, 4, 1, 1, '2026-02-25 12:10:35', '2026-02-25 12:10:35'),
(5, 1, 2, 1, '2026-02-25 12:19:39', '2026-02-25 12:19:39'),
(6, 2, 2, 1, '2026-02-25 12:19:39', '2026-02-25 12:19:39'),
(7, 5, 2, 1, '2026-02-25 12:19:39', '2026-02-25 12:19:39'),
(8, 16, 1, 0, NULL, '2026-03-02 17:18:48'),
(9, 16, 2, 0, NULL, '2026-03-02 17:18:48'),
(10, 16, 3, 1, '2026-03-05 15:32:55', '2026-03-02 17:18:48');

-- --------------------------------------------------------

--
-- Table structure for table `website_content`
--

CREATE TABLE `website_content` (
  `content_id` int(11) NOT NULL,
  `content_key` varchar(100) NOT NULL,
  `content_title` varchar(255) DEFAULT NULL,
  `content_text` text DEFAULT NULL,
  `content_type` enum('home','about','faq','contact','banner','blog') DEFAULT 'home',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `website_content`
--

INSERT INTO `website_content` (`content_id`, `content_key`, `content_title`, `content_text`, `content_type`, `created_at`, `updated_at`) VALUES
(1, 'home_title', 'Home Title', 'Discover Authentic Ceylon Spices', 'home', '2025-12-24 05:22:41', '2025-12-24 05:22:41'),
(2, 'home_subtitle', 'Home Subtitle', 'Direct from Sri Lankan farmers to your kitchen', 'home', '2025-12-24 05:22:41', '2025-12-24 05:22:41'),
(3, 'home_content', 'Home Content', '<h3>Why Choose SpiceCeylon?</h3>\r\n<p>We bring you the finest Ceylon spices directly from local farmers. Our spices are:</p>\r\n<ul>\r\n<li><strong>100% Organic:</strong> Grown without harmful chemicals</li>\r\n<li><strong>Freshly Harvested:</strong> Direct from farm to your door</li>\r\n<li><strong>Fair Trade:</strong> Supporting local farming communities</li>\r\n<li><strong>Premium Quality:</strong> Hand-picked and carefully processed</li>\r\n</ul>\r\n<p>Experience the true taste of Sri Lanka with our authentic spice collection.</p>', 'home', '2025-12-24 05:22:41', '2025-12-24 05:22:41'),
(4, 'about_content', 'About Content', '                                        <h2>Our Story</h2>\r\n<p>Founded in 2020, SpiceCeylon began with a simple vision: to bring authentic Sri Lankan spices directly from farmers to consumers worldwide.\r\nOur journey started in the heart of Sri Lanka\'s spice-growing regions, where we witnessed first-hand the challenges faced by local farmers in reaching global markets. We created a platform that eliminates middlemen, ensuring farmers receive fair prices while customers enjoy premium quality spices at reasonable rates.\r\nToday, we work with over 200 farmers across Sri Lanka, bringing you spices that are not only delicious but also ethically sourced and sustainably grown.\r\nTo bridge the gap between Sri Lankan farmers and global consumers by providing authentic, high-quality spices while ensuring fair trade practices and sustainable farming.</p>\r\n\r\n<h3>Our Mission</h3>\r\n<p>To provide authentic, high-quality Ceylon spices while supporting sustainable farming practices and fair trade principles.</p>\r\n\r\n<h3>Our Values</h3>\r\n<div class=\"row\">\r\n<div class=\"col-md-4\">\r\n<h4>Quality</h4>\r\n<p>We ensure every spice meets our strict quality standards through careful selection and processing.</p>\r\n</div>\r\n<div class=\"col-md-4\">\r\n<h4>Sustainability</h4>\r\n<p>We promote organic farming methods that protect Sri Lanka\'s rich biodiversity.</p>\r\n</div>\r\n<div class=\"col-md-4\">\r\n<h4>Community</h4>\r\n<p>We work directly with farmers, ensuring fair prices and supporting local communities.</p>\r\n</div>\r\n</div>                                    ', 'about', '2025-12-24 05:22:41', '2025-12-24 15:06:56'),
(5, 'faq_content', 'FAQ Content', '<h2>Frequently Asked Questions</h2>\r\n\r\n<div class=\"faq-item\">\r\n<h4>Q: How fresh are your spices?</h4>\r\n<p>A: Our spices are harvested fresh and processed within days to ensure maximum flavor and aroma.</p>\r\n</div>\r\n\r\n<div class=\"faq-item\">\r\n<h4>Q: Do you ship internationally?</h4>\r\n<p>A: Yes, we ship worldwide. Shipping costs and times vary by location.</p>\r\n</div>\r\n\r\n<div class=\"faq-item\">\r\n<h4>Q: Are your spices organic?</h4>\r\n<p>A: Yes, all our spices are 100% organic and grown without synthetic pesticides or fertilizers.</p>\r\n</div>\r\n\r\n<div class=\"faq-item\">\r\n<h4>Q: How should I store spices?</h4>\r\n<p>A: Store in airtight containers in a cool, dark place away from direct sunlight and moisture.</p>\r\n</div>\r\n\r\n<div class=\"faq-item\">\r\n<h4>Q: What payment methods do you accept?</h4>\r\n<p>A: We accept credit/debit cards, bank transfers, and cash on delivery (Sri Lanka only).</p>\r\n</div>', 'faq', '2025-12-24 05:22:41', '2025-12-24 05:22:41'),
(6, 'contact_info', 'Contact Information', '<h3>Get in Touch</h3>\r\n<p><i class=\"fas fa-map-marker-alt\"></i> <strong>Address:</strong> Colombo, Sri Lanka</p>\r\n<p><i class=\"fas fa-phone\"></i> <strong>Phone:</strong> +94 11 234 5678</p>\r\n<p><i class=\"fas fa-envelope\"></i> <strong>Email:</strong> info@spiceceylon.com</p>\r\n<p><i class=\"fas fa-clock\"></i> <strong>Business Hours:</strong><br>\r\nMonday - Friday: 9:00 AM - 6:00 PM<br>\r\nSaturday: 9:00 AM - 2:00 PM<br>\r\nSunday: Closed</p>', 'contact', '2025-12-24 05:22:41', '2025-12-24 05:22:41');

-- --------------------------------------------------------

--
-- Table structure for table `website_settings`
--

CREATE TABLE `website_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'text',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `wishlist_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`wishlist_id`, `customer_id`, `product_id`, `created_at`) VALUES
(1, 1, 1, '2025-12-26 13:17:36'),
(10, 1, 11, '2026-03-03 08:24:11');

-- --------------------------------------------------------

--
-- Structure for view `user_notifications_view`
--
DROP TABLE IF EXISTS `user_notifications_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `user_notifications_view`  AS SELECT `n`.`notification_id` AS `notification_id`, `n`.`title` AS `title`, `n`.`message` AS `message`, `n`.`target_roles` AS `target_roles`, `n`.`sender_id` AS `sender_id`, `n`.`sender_role` AS `sender_role`, `n`.`is_important` AS `is_important`, `n`.`expires_at` AS `expires_at`, `n`.`created_at` AS `created_at`, `u`.`user_id` AS `user_id`, `u`.`name` AS `user_name`, `u`.`email` AS `user_email`, `u`.`role` AS `user_role`, coalesce(`uns`.`is_read`,0) AS `is_read`, `uns`.`read_at` AS `read_at` FROM ((`notifications` `n` join `users` `u`) left join `user_notification_status` `uns` on(`n`.`notification_id` = `uns`.`notification_id` and `u`.`user_id` = `uns`.`user_id`)) WHERE (`n`.`target_roles` = 'all' OR `n`.`target_roles` = 'customers' AND `u`.`role` = 'customer' OR `n`.`target_roles` = 'farmers' AND `u`.`role` = 'farmer' OR `n`.`target_roles` = 'admins' AND exists(select 1 from `admins` `a` where `a`.`admin_id` = `u`.`user_id` limit 1) OR `n`.`target_roles` = 'specific' AND `n`.`target_user_id` = `u`.`user_id`) AND (`n`.`expires_at` is null OR `n`.`expires_at` > current_timestamp()) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `admin_activity`
--
ALTER TABLE `admin_activity`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_logins`
--
ALTER TABLE `admin_logins`
  ADD PRIMARY KEY (`login_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`banner_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `contact_info`
--
ALTER TABLE `contact_info`
  ADD PRIMARY KEY (`contact_id`);

--
-- Indexes for table `faq_items`
--
ALTER TABLE `faq_items`
  ADD PRIMARY KEY (`faq_id`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_receiver` (`receiver_id`,`receiver_role`,`is_read`),
  ADD KEY `idx_sender` (`sender_id`,`sender_role`);

--
-- Indexes for table `messages_new`
--
ALTER TABLE `messages_new`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_receiver` (`receiver_id`,`receiver_role`,`is_read`),
  ADD KEY `idx_sender` (`sender_id`,`sender_role`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `target_user_id` (`target_user_id`);

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
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `changed_by_admin` (`changed_by_admin`);

--
-- Indexes for table `page_content`
--
ALTER TABLE `page_content`
  ADD PRIMARY KEY (`content_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `payment_cards`
--
ALTER TABLE `payment_cards`
  ADD PRIMARY KEY (`card_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`method_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `product_requests`
--
ALTER TABLE `product_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `assigned_farmer_id` (`assigned_farmer_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`);

--
-- Indexes for table `request_history`
--
ALTER TABLE `request_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `changed_by_admin` (`changed_by_admin`);

--
-- Indexes for table `request_notifications`
--
ALTER TABLE `request_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  ADD PRIMARY KEY (`zone_id`);

--
-- Indexes for table `sliders`
--
ALTER TABLE `sliders`
  ADD PRIMARY KEY (`slider_id`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`member_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_announcement_status`
--
ALTER TABLE `user_announcement_status`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `unique_user_announcement` (`announcement_id`,`user_id`);

--
-- Indexes for table `user_notification_status`
--
ALTER TABLE `user_notification_status`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `unique_notification_user` (`notification_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `website_content`
--
ALTER TABLE `website_content`
  ADD PRIMARY KEY (`content_id`),
  ADD UNIQUE KEY `content_key` (`content_key`),
  ADD KEY `idx_content_key` (`content_key`),
  ADD KEY `idx_content_type` (`content_type`);

--
-- Indexes for table `website_settings`
--
ALTER TABLE `website_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `website_stats`
--
ALTER TABLE `website_stats`
  ADD PRIMARY KEY (`stat_id`),
  ADD UNIQUE KEY `unique_date` (`visit_date`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `unique_wishlist` (`customer_id`,`product_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_activity`
--
ALTER TABLE `admin_activity`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_logins`
--
ALTER TABLE `admin_logins`
  MODIFY `login_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `banner_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `contact_info`
--
ALTER TABLE `contact_info`
  MODIFY `contact_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `faq_items`
--
ALTER TABLE `faq_items`
  MODIFY `faq_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `forecast_data`
--
ALTER TABLE `forecast_data`
  MODIFY `forecast_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `messages_new`
--
ALTER TABLE `messages_new`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `page_content`
--
ALTER TABLE `page_content`
  MODIFY `content_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_cards`
--
ALTER TABLE `payment_cards`
  MODIFY `card_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `product_requests`
--
ALTER TABLE `product_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- AUTO_INCREMENT for table `request_history`
--
ALTER TABLE `request_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `request_notifications`
--
ALTER TABLE `request_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `shipping_zones`
--
ALTER TABLE `shipping_zones`
  MODIFY `zone_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sliders`
--
ALTER TABLE `sliders`
  MODIFY `slider_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_announcement_status`
--
ALTER TABLE `user_announcement_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_notification_status`
--
ALTER TABLE `user_notification_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `website_content`
--
ALTER TABLE `website_content`
  MODIFY `content_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `website_settings`
--
ALTER TABLE `website_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `website_stats`
--
ALTER TABLE `website_stats`
  MODIFY `stat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity`
--
ALTER TABLE `admin_activity`
  ADD CONSTRAINT `admin_activity_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`);

--
-- Constraints for table `admin_logins`
--
ALTER TABLE `admin_logins`
  ADD CONSTRAINT `admin_logins_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`);

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
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages_new`
--
ALTER TABLE `messages_new`
  ADD CONSTRAINT `messages_new_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_new_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

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
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_status_history_ibfk_2` FOREIGN KEY (`changed_by_admin`) REFERENCES `admins` (`admin_id`);

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
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `admins` (`admin_id`);

--
-- Constraints for table `product_requests`
--
ALTER TABLE `product_requests`
  ADD CONSTRAINT `product_requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_requests_ibfk_2` FOREIGN KEY (`assigned_farmer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `request_history`
--
ALTER TABLE `request_history`
  ADD CONSTRAINT `request_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `product_requests` (`request_id`),
  ADD CONSTRAINT `request_history_ibfk_2` FOREIGN KEY (`changed_by_admin`) REFERENCES `admins` (`admin_id`);

--
-- Constraints for table `request_notifications`
--
ALTER TABLE `request_notifications`
  ADD CONSTRAINT `request_notifications_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `product_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_notifications_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notification_status`
--
ALTER TABLE `user_notification_status`
  ADD CONSTRAINT `user_notification_status_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`notification_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_notification_status_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
