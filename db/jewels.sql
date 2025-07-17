-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Jun 24, 2025 at 06:20 AM
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
-- Database: `jewels`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `icon` varchar(255) DEFAULT 'default-icon.png',
  `isActive` tinyint(1) DEFAULT 0,
  `isVerified` tinyint(1) DEFAULT 0,
  `isDeleted` tinyint(1) DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_purpose` enum('PASSWORD_RESET','EMAIL_VERIFICATION') DEFAULT NULL,
  `otp_created_at` timestamp NULL DEFAULT NULL,
  `otp_expires_at` timestamp NULL DEFAULT NULL,
  `otp_is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `firstname`, `lastname`, `email`, `username`, `password`, `address`, `phone`, `icon`, `isActive`, `isVerified`, `isDeleted`, `otp_code`, `otp_purpose`, `otp_created_at`, `otp_expires_at`, `otp_is_used`, `created_at`, `updated_at`) VALUES
(1, 'Loraine', 'Castro', 'castro.loraine.26@gmail.com', 'admin', '$2y$10$x4K9zpfuXWr19eePB38LGeqSikdPVXpS4A2gb8kmsiYHHpWHR1Q.m', 'Bulacan', '09123456789', 'no-icon.png', 1, 1, 0, '014505', 'PASSWORD_RESET', '2025-06-23 02:31:14', '2025-06-22 20:46:14', 0, '2025-06-22 13:48:08', '2025-06-24 04:15:29'),
(2, 'Loraine', 'Castro', 'email1@example.com', 'user1', '$2y$10$QY/yT42LIiYrlqN6xCIAfemmFQBoa6X.4EKc082LRCxU5QH65xRZq', 'Bulacan', '09123456789', 'profilePhoto1.jpg', 1, 1, 0, NULL, 'EMAIL_VERIFICATION', '2025-06-24 04:04:55', NULL, 1, '2025-06-24 04:04:55', '2025-06-24 04:18:08'),
(3, 'Loraine', 'Castro', 'email2@example.com', 'user2', '$2y$10$Pfn55NvVDAMZ2UgR0b136.xssGX9h3Ce/CrvPEgoDmE8sIBwvpgSq', 'Bulacan', '09123456789', 'profilePhoto2.jpg', 1, 1, 0, NULL, 'EMAIL_VERIFICATION', '2025-06-24 04:08:45', NULL, 1, '2025-06-24 04:08:45', '2025-06-24 04:18:45');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `email_otp` (`email`,`otp_code`,`otp_is_used`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `session_token` (`session_token`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
