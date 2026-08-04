-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2026 at 01:38 PM
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
-- Database: `meedo_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `login`
--

CREATE TABLE `login` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Administrator','Treasury') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login`
--

INSERT INTO `login` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2y$10$.ahwmub1WGDt44dsT1Ddqe0y6dhWYIUXBDX.WLrP4XCnbv1Cg0FcC', 'Administrator'),
(2, 'treasury', '$2y$10$BNxB09eLarkFe1hlKpurtekRczXFNA.kKThx6F3XQMYg5Io.kW7fO', 'Treasury');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `stall_id` int(11) DEFAULT NULL,
  `tenant_name` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `due_date` date NOT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `month_covered` date DEFAULT NULL,
  `status` enum('Paid','Pending','Overdue') DEFAULT 'Pending',
  `penalty` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `stall_id`, `tenant_name`, `amount`, `payment_date`, `due_date`, `receipt_number`, `month_covered`, `status`, `penalty`, `notes`, `created_at`) VALUES
(3, 2, 'jayson udo', 2000.00, '2026-07-01', '2026-07-01', 'REG-20260701-5', '2026-07-01', 'Paid', 0.00, NULL, '2026-07-01 10:14:14'),
(4, 15, 'Evelyn Tan', 1520.00, '2026-07-01', '2026-07-01', 'REG-20260701-6', '2026-07-01', 'Paid', 0.00, NULL, '2026-07-01 10:56:56'),
(5, 14, 'Loraine Ang', 1520.00, '2026-07-02', '2026-07-01', 'REG-20260702-7', '2026-07-01', 'Paid', 0.00, NULL, '2026-07-02 03:15:18');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `icon_class` varchar(50) DEFAULT 'Store',
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `icon_class`, `display_order`, `created_at`) VALUES
(1, 'fish', 'Store', 0, '2026-07-01 09:20:23'),
(2, 'meat', 'Store', -1, '2026-07-01 09:20:35'),
(9, 'rice store', 'Rice & Grains', -1, '2026-07-01 10:54:37');

-- --------------------------------------------------------

--
-- Table structure for table `stalls`
--

CREATE TABLE `stalls` (
  `id` int(11) NOT NULL,
  `stall_number` varchar(20) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `tenant_name` varchar(100) DEFAULT NULL,
  `status` enum('Occupied','Vacant') DEFAULT 'Vacant',
  `monthly_rent` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stalls`
--

INSERT INTO `stalls` (`id`, `stall_number`, `section_id`, `tenant_name`, `status`, `monthly_rent`, `created_at`) VALUES
(2, 'M-001', 2, 'jayson udo', 'Occupied', 2000.00, '2026-07-01 09:20:41'),
(3, 'M-002', 2, NULL, 'Vacant', 2000.00, '2026-07-01 09:20:47'),
(11, 'M-003', 2, NULL, 'Vacant', 2000.00, '2026-07-01 10:21:21'),
(14, 'S9-001', 9, 'Loraine Ang', 'Occupied', 1520.00, '2026-07-01 10:54:56'),
(15, 'S9-002', 9, 'Evelyn Tan', 'Occupied', 1520.00, '2026-07-01 10:55:08'),
(16, 'S1-001', 1, NULL, 'Vacant', 2300.00, '2026-07-02 03:07:56');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `street` varchar(100) DEFAULT NULL,
  `barangay` varchar(50) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `stall_id` int(11) DEFAULT NULL,
  `business_name` varchar(100) NOT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `full_name`, `date_of_birth`, `contact_number`, `street`, `barangay`, `city`, `stall_id`, `business_name`, `status`, `created_at`, `updated_at`) VALUES
(5, 'jayson udo', '2026-07-22', '09103120376', 'Romblon', 'budiong', 'romblon', 2, 'kangkong store', 'active', '2026-07-01 10:14:14', '2026-07-01 10:14:14'),
(6, 'Evelyn Tan', '2013-05-01', '09103120376', 'Romblon', 'batiano', 'romblon', 15, 'bugasan ni evelyn', 'active', '2026-07-01 10:56:56', '2026-07-01 10:56:56'),
(7, 'Loraine Ang', '1997-08-12', '09467273567', 'Looban Street', 'Tumingad', 'romblon', 14, 'RICE STORE NI LORAINE', 'active', '2026-07-02 03:15:18', '2026-07-02 03:16:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stall_id` (`stall_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stalls`
--
ALTER TABLE `stalls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stall_number` (`stall_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_section` (`section_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_stall` (`stall_id`),
  ADD KEY `idx_tenant_name` (`full_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `login`
--
ALTER TABLE `login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `stalls`
--
ALTER TABLE `stalls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`stall_id`) REFERENCES `stalls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stalls`
--
ALTER TABLE `stalls`
  ADD CONSTRAINT `stalls_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `tenants_ibfk_1` FOREIGN KEY (`stall_id`) REFERENCES `stalls` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
