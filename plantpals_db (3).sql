-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2025 at 05:44 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `plantpals_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'BUAH', NULL, '2025-06-10 19:21:10'),
(2, 'SAYUR', NULL, '2025-06-10 19:21:10'),
(4, 'BUNGA', NULL, '2025-06-10 19:21:10'),
(7, 'Lainnya', NULL, '2025-06-10 20:09:41'),
(8, 'Perlengkapan menanam', NULL, '2025-06-10 20:08:35');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `store_id`, `subject`, `message`, `sent_at`, `is_read`) VALUES
(1, 6, 5, 4, 'Balasan dari Pembeli', 'hallo kak', '2025-06-10 18:10:20', 1),
(2, 5, 6, 4, 'Balasan dari toko anggi', 'halloo', '2025-06-10 18:10:59', 1),
(3, 5, 6, 4, 'Balasan dari toko anggi', 'ada yang bisa dibantuu ka?', '2025-06-10 18:11:12', 1),
(4, 6, 13, 10, 'Balasan dari Pembeli', 'kak tomatnya masi ada?', '2025-06-10 20:29:44', 0),
(5, 6, 5, 4, 'Balasan dari Pembeli', 'bunga tulipnya masi ada kak?', '2025-06-10 20:52:59', 1),
(6, 5, 6, 4, 'Balasan dari toko anggi', 'masi kakaa', '2025-06-10 20:53:44', 1),
(7, 5, 6, 4, 'Balasan dari toko anggi', 'silahkan di order yaa!', '2025-06-10 20:53:56', 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` enum('pending','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` enum('bank_transfer','cod') NOT NULL DEFAULT 'bank_transfer',
  `delivery_address` text NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `order_status`, `payment_method`, `delivery_address`, `customer_name`, `customer_phone`, `customer_email`, `notes`, `order_date`) VALUES
(1, 9, 2240.00, 'cancelled', 'cod', 'kawo', 'dahlia candraa', '0928483736', 'anggia@gmail.com', '', '2025-06-10 05:53:36'),
(2, 6, 245577.00, 'pending', 'cod', 'Lombok Timur, Nusa Tenggara Barat', 'reni sopiani', '081999684399', 'reni@gmail.com', '', '2025-06-10 07:16:21'),
(3, 6, 675.00, 'pending', 'cod', 'Lombok Timur, Nusa Tenggara Barat', 'reni sopiani', '081999684399', 'reni@gmail.com', 'ppp', '2025-06-10 10:12:44'),
(4, 6, 245577.00, 'completed', 'cod', 'Lombok Timur, Nusa Tenggara Barat', 'reni sopiani', '081999684399', 'reni@gmail.com', 'ppp', '2025-06-10 10:13:32'),
(5, 6, 47001.00, 'pending', 'cod', 'Lombok Timur, Nusa Tenggara Barat', 'reni sopiani', '081999684399', 'reni@gmail.com', '', '2025-06-10 17:59:12');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(100) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `store_id` varchar(50) DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `category`, `unit_price`, `quantity`, `store_id`, `store_name`) VALUES
(1, 1, 32, 'Lily', NULL, 675.00, 1, 'toko_toko4_1749533254', 'Beauty garden\'s Store - (Ampenan)'),
(2, 1, 30, 'Dahlia', NULL, 690.00, 1, 'N/A', 'Toko Tidak Dikenal'),
(3, 1, 33, 'Safron', NULL, 875.00, 1, 'toko_toko4_1749533254', 'Beauty garden\'s Store - (Ampenan)'),
(4, 2, 27, 'Jasmin', NULL, 245577.00, 1, 'N/A', 'N/A'),
(5, 3, 32, 'Lily', NULL, 675.00, 1, 'toko_toko4_1749533254', 'Beauty garden\'s Store - (Ampenan)'),
(6, 4, 27, 'Jasmin', NULL, 245577.00, 1, 'toko_toko_anggi_1749503561', 'anggi\'s Store - (Lombok Tengah)'),
(7, 5, 34, 'Tulip', NULL, 47001.00, 1, 'toko_toko_anggi_1749503561', 'anggi\'s Store - (Lombok Tengah)');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `img` varchar(255) DEFAULT NULL,
  `scientific_name` varchar(100) DEFAULT NULL,
  `family` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `habitat` text DEFAULT NULL,
  `care_instructions` text DEFAULT NULL,
  `unique_fact` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `seller_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `img`, `scientific_name`, `family`, `description`, `habitat`, `care_instructions`, `unique_fact`, `price`, `stock`, `seller_id`, `category_id`, `created_at`, `updated_at`) VALUES
(27, 'Jasmine', '/PlantPals/assets/uploads/product_6848805968467.jpg', 'vece', 'ccc', '', '', '', '', 12555.00, 55, 5, 4, '2025-06-09 21:13:26', '2025-06-10 19:55:48'),
(28, 'Mawar', '/PlantPals/assets/uploads/product_6847bd9c47055.jpg', '', '', '', '', '', '', 50.00, 100, 5, 4, '2025-06-10 05:07:40', '2025-06-10 20:00:01'),
(29, 'Mawar pink', '/PlantPals/assets/uploads/product_6847c1d40a227.jpg', '', '', '', '', '', '', 225.56, 100, 9, 4, '2025-06-10 05:25:40', '2025-06-10 20:00:01'),
(30, 'Dahlia', '/PlantPals/assets/uploads/product_6847c1f444847.jpg', '', '', '', '', '', '', 690.00, 120, 10, 4, '2025-06-10 05:26:12', '2025-06-10 20:00:01'),
(31, 'Lotus', '/PlantPals/assets/uploads/product_6847c282bb68b.jpg', '', '', '', '', '', '', 897.00, 60, 11, 4, '2025-06-10 05:28:34', '2025-06-10 20:00:01'),
(32, 'Lily', '/PlantPals/assets/uploads/product_6847c2a27bb7b.jpg', '', '', '', '', '', '', 675.00, 88, 11, 4, '2025-06-10 05:29:06', '2025-06-10 20:00:01'),
(33, 'Safron', '/PlantPals/assets/uploads/product_6847c2d3509ec.jpg', '', '', '', '', '', '', 875.00, 25, 11, 4, '2025-06-10 05:29:55', '2025-06-10 20:00:01'),
(34, 'Tulip', '/PlantPals/assets/uploads/product_684880280ce7f.jpg', '', '', '', '', '', '', 47001.00, 16, 5, 4, '2025-06-10 17:34:41', '2025-06-10 19:23:11'),
(35, 'Wortel 1kg', '/PlantPals/assets/uploads/product_684893eea3d35.jpg', '', '', '', '', '', '', 17000.00, 86, 13, 2, '2025-06-10 20:22:06', '2025-06-10 20:25:32'),
(36, 'Tomat 1kg', '/PlantPals/assets/uploads/product_684894e8b268b.jpg', '', '', '', '', '', '', 11000.00, 45, 13, 2, '2025-06-10 20:26:16', '2025-06-10 20:26:16'),
(37, 'Terong', '/PlantPals/assets/uploads/product_68489510e23a9.jpg', '', '', '', '', '', '', 5000.00, 12, 13, 2, '2025-06-10 20:26:56', '2025-06-10 20:26:56'),
(38, 'Brokoli', '/PlantPals/assets/uploads/product_684895741c0be.jpg', '', '', '', '', '', '', 12000.00, 15, 13, 2, '2025-06-10 20:28:36', '2025-06-10 20:28:36'),
(39, 'Anggur Hijau', '/PlantPals/assets/uploads/product_6848977919e5f.jpg', '', '', '', '', '', '', 89000.00, 9, 14, 1, '2025-06-10 20:37:13', '2025-06-10 20:37:13'),
(40, 'Jeruk Manis 1kg', '/PlantPals/assets/uploads/product_684897fb23065.jpg', '', '', '', '', '', '', 23000.00, 15, 14, 1, '2025-06-10 20:39:23', '2025-06-10 20:39:23'),
(41, 'Nanas Madu 1kg', '/PlantPals/assets/uploads/product_684898522ba47.jpg', '', '', '', '', '', '', 34000.00, 21, 14, 1, '2025-06-10 20:40:50', '2025-06-10 20:40:50'),
(42, 'Jambu Air 1kg', '/PlantPals/assets/uploads/product_684898bf03404.jpg', '', '', '', '', '', '', 13000.00, 16, 14, 1, '2025-06-10 20:42:39', '2025-06-10 20:42:39'),
(43, 'Pepaya', '/PlantPals/assets/uploads/product_684898ffc4743.jpg', '', '', '', '', '', '', 13000.00, 12, 14, 1, '2025-06-10 20:43:43', '2025-06-10 20:43:43'),
(44, 'Semangka', '/PlantPals/assets/uploads/product_684899776181a.jpg', '', '', '', '', '', '', 35000.00, 13, 14, 1, '2025-06-10 20:45:43', '2025-06-10 20:45:43'),
(45, 'Mangga', '/PlantPals/assets/uploads/product_68489a0027151.jpg', '', '', '', '', '', '', 28000.00, 9, 14, 1, '2025-06-10 20:48:00', '2025-06-10 20:48:00'),
(46, 'Apel', '/PlantPals/assets/uploads/product_68489a25c5bdd.jpg', '', '', '', '', '', '', 18000.00, 19, 14, 1, '2025-06-10 20:48:37', '2025-06-10 20:48:37');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 27, 6, 5, 'pppppp', '2025-06-10 10:15:21');

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `store_id_string` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `seller_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `followers_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `store_id_string`, `name`, `address`, `phone_number`, `email`, `seller_user_id`, `created_at`, `updated_at`, `followers_count`) VALUES
(4, 'toko_toko_anggi_1749503561', 'anggi\'s Store', 'Lombok Tengah', '089709877890', NULL, 5, '2025-06-09 21:12:41', '2025-06-09 21:12:41', 0),
(5, 'toko_toko8_1749517007', 'candra dahlia\'s Store', 'kawo', '09889999999', NULL, 7, '2025-06-10 00:56:47', '2025-06-10 00:56:47', 0),
(7, 'toko_toko3_1749533079', 'Find your flower!\'s Store', 'Mataram', '0877777777', NULL, 10, '2025-06-10 05:24:39', '2025-06-10 17:37:01', 0),
(8, 'toko_toko4_1749533254', 'Beauty garden\'s Store', 'Ampenan', '0736522432663', NULL, 11, '2025-06-10 05:27:34', '2025-06-10 18:08:57', 0),
(9, 'toko_admin_1749538065', 'admin\'s Store', 'Mataram, Nusa Tenggara Barat', '098789789789', NULL, 12, '2025-06-10 06:47:45', '2025-06-10 06:47:45', 0),
(10, 'toko_suka_sayur_1749586803', 'suka sayur\'s Store', 'Lombok Barat, Nusa Tenggara Barat, Indonesia', '098765431234', NULL, 13, '2025-06-10 20:20:03', '2025-06-10 20:20:03', 0),
(11, 'toko_ada_buah_1749587744', 'Reyyy\'s Store', 'Lombok Tengah', '0987654567845', NULL, 14, '2025-06-10 20:35:44', '2025-06-10 20:35:44', 0);

-- --------------------------------------------------------

--
-- Table structure for table `store_followers`
--

CREATE TABLE `store_followers` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `followed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `phone_number`, `address`, `role`, `created_at`) VALUES
(5, 'toko anggi', '$2y$10$jA95iO8IHjT5GOmRRTsz9eMUtuf2ASlhNMqImB/dDG.8LcyY9CU72', 'anggi@gmail.com', 'anggi', '089709877890', 'Lombok Tengah', 'seller', '2025-06-09 21:12:41'),
(6, 'reni', '$2y$10$cCCjjYQnc05/XnDXqDaIMOPZ9e2qStTXiuwfCmQwR4EHJrYJuZuwu', 'reni@gmail.com', 'reni sopiani', '081999684399', 'Lombok Timur, Nusa Tenggara Barat', 'buyer', '2025-06-09 21:16:09'),
(7, 'toko8', '$2y$10$sli8C/y2/lPxG4Q/Dd2WbuagzrN9/p8nunw5u/q0rWeyN/MIrId0C', 'toko8@gmail.com', 'candra dahlia', '09889999999', 'kawo', 'seller', '2025-06-10 00:56:47'),
(9, 'anggia', '$2y$10$2glYXU6Jw30BB/lYZ5S1BuN.YUjmeeXQ.w7gQHMpQAIlRQ7FSldQG', 'anggia@gmail.com', 'dahlia candraa', '0928483736', 'Lombok Tengah', 'buyer', '2025-06-10 02:31:46'),
(10, 'toko3', '$2y$10$PqZ1S5ITeycLap92JTUmi.GP68jDLfaiATVh6FyRWlUwcMHOw8XLG', 'toko3@gmail.com', 'Find your flower!', '0877777777', 'Mataram', 'seller', '2025-06-10 05:24:39'),
(11, 'toko4', '$2y$10$H/hcn0D0MN7giVGE4UWT2uVIi4JQTXl1LXDC3uXUOVXbfXoN2J8IW', 'toko4@gmail.com', 'Beauty garden', '0736522432663', 'Ampenan', 'seller', '2025-06-10 05:27:34'),
(12, 'admin', '$2y$10$h9ltskuriuf1yDzU9A4uXutbq6vdZkUbwjuhCp9eqBZi4LajbexiG', 'admin@gmail.com', 'admin', '098789789789', 'Mataram, Nusa Tenggara Barat', 'admin', '2025-06-10 06:47:45'),
(13, 'suka sayur', '$2y$10$S10zndN7FnCTQpJhkKtf8eBnven7Y3SxpMhtlX24TEefLAtmFusUC', 'tokoreni@gmail.com', 'suka sayur', '098765431234', 'Lombok Barat, Nusa Tenggara Barat, Indonesia', 'seller', '2025-06-10 20:20:03'),
(14, 'ada buah', '$2y$10$i8YpDXdDyz/3zN6W9Xs.Rum/MRkMecdTh36J7IXkZREi/6CvAUe9W', 'rey@gmail.com', 'Reyyy', '0987654567845', 'Lombok Tengah', 'seller', '2025-06-10 20:35:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `seller_id` (`seller_id`),
  ADD KEY `fk_product_category` (`category_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_id_string` (`store_id_string`),
  ADD KEY `seller_user_id` (`seller_user_id`);

--
-- Indexes for table `store_followers`
--
ALTER TABLE `store_followers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_id` (`store_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `store_followers`
--
ALTER TABLE `store_followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `stores_ibfk_1` FOREIGN KEY (`seller_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_followers`
--
ALTER TABLE `store_followers`
  ADD CONSTRAINT `store_followers_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_followers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
