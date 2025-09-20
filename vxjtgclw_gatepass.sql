-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 20, 2025 at 11:05 PM
-- Server version: 8.0.42
-- PHP Version: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vxjtgclw_gatepass`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_areas`
--

CREATE TABLE `access_areas` (
  `id` int NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `description` text,
  `requires_escort` tinyint(1) DEFAULT '0',
  `max_occupancy` int DEFAULT NULL,
  `is_restricted` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `access_areas`
--

INSERT INTO `access_areas` (`id`, `area_name`, `description`, `requires_escort`, `max_occupancy`, `is_restricted`, `is_active`, `created_at`) VALUES
(1, 'Reception', 'Main reception and waiting area', 0, NULL, 0, 1, '2025-09-20 15:00:27'),
(2, 'Office Floor 1', 'First floor offices', 0, NULL, 0, 1, '2025-09-20 15:00:27'),
(3, 'Office Floor 2', 'Second floor offices', 0, NULL, 0, 1, '2025-09-20 15:00:27'),
(4, 'Conference Rooms', 'Meeting and conference rooms', 0, NULL, 0, 1, '2025-09-20 15:00:27'),
(5, 'Parking Area', 'Vehicle parking zones', 0, NULL, 0, 1, '2025-09-20 15:00:27'),
(6, 'Cafeteria', 'Dining and break areas', 0, NULL, 0, 1, '2025-09-20 15:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE `blacklist` (
  `id` int NOT NULL,
  `entity_type` enum('Visitor','Vehicle','Company') NOT NULL,
  `entity_id` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `added_by` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_visit_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_visit_summary` (
`checked_in` bigint
,`checked_out` bigint
,`total_visits` bigint
,`unique_visitors` bigint
,`vehicles_count` bigint
,`visit_date` date
);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text,
  `data` text,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pre_registrations`
--

CREATE TABLE `pre_registrations` (
  `id` int NOT NULL,
  `registration_code` varchar(20) NOT NULL,
  `visitor_name` varchar(100) NOT NULL,
  `visitor_email` varchar(100) DEFAULT NULL,
  `visitor_phone` varchar(20) DEFAULT NULL,
  `visitor_company` varchar(100) DEFAULT NULL,
  `host_name` varchar(100) NOT NULL,
  `host_department` varchar(100) DEFAULT NULL,
  `host_email` varchar(100) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time NOT NULL,
  `duration_hours` int DEFAULT '2',
  `purpose` text,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `special_requirements` text,
  `status` enum('Pending','Approved','Rejected','Expired','Used') DEFAULT 'Pending',
  `approved_by` int DEFAULT NULL,
  `approval_notes` text,
  `created_by` int DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_templates`
--

CREATE TABLE `qr_templates` (
  `id` int NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_data` text NOT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `qr_templates`
--

INSERT INTO `qr_templates` (`id`, `template_name`, `template_data`, `is_default`, `created_at`) VALUES
(1, 'Default Visit Card', '{\"front\": {\"logo\": true, \"visitor_name\": true, \"company\": true, \"visit_date\": true, \"host\": true, \"badge_number\": true}, \"back\": {\"qr_code\": true, \"instructions\": true, \"emergency_contact\": true}}', 1, '2025-09-20 15:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int NOT NULL,
  `report_name` varchar(100) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `parameters` text,
  `generated_by` int NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'GatePass Pro', '2025-09-20 15:00:26'),
(2, 'primary_color', '#3B82F6', '2025-09-20 15:00:26'),
(3, 'secondary_color', '#1F2937', '2025-09-20 15:00:26'),
(4, 'accent_color', '#10B981', '2025-09-20 15:00:26'),
(5, 'email_notifications', '1', '2025-09-20 15:00:26'),
(6, 'sms_notifications', '0', '2025-09-20 15:00:26'),
(7, 'smtp_host', '', '2025-09-20 15:00:26'),
(8, 'smtp_port', '587', '2025-09-20 15:00:26'),
(9, 'smtp_username', '', '2025-09-20 15:00:26'),
(10, 'smtp_password', '', '2025-09-20 15:00:26'),
(11, 'company_name', 'Your Company', '2025-09-20 15:00:26'),
(12, 'company_address', '', '2025-09-20 15:00:26'),
(13, 'company_phone', '', '2025-09-20 15:00:26'),
(14, 'company_email', '', '2025-09-20 15:00:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role_id` int NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `role_id`, `profile_photo`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', NULL, 1, NULL, 1, NULL, '2025-09-20 15:00:27', '2025-09-20 15:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `permissions` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `role_name`, `permissions`, `created_at`) VALUES
(1, 'Super Admin', '[\"all\"]', '2025-09-20 15:00:26'),
(2, 'Admin', '[\"dashboard\", \"visitors\", \"vehicles\", \"staff\", \"reports\", \"settings\"]', '2025-09-20 15:00:26'),
(3, 'Security', '[\"dashboard\", \"visitors\", \"vehicles\", \"checkin\", \"checkout\"]', '2025-09-20 15:00:26'),
(4, 'Receptionist', '[\"dashboard\", \"visitors\", \"checkin\", \"checkout\", \"reports\"]', '2025-09-20 15:00:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` longtext,
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `vehicle_type` enum('Car','Motorcycle','Van','Truck','Bus','Other') DEFAULT 'Car',
  `make` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `owner_type` enum('Visitor','Staff','Company') DEFAULT 'Visitor',
  `owner_id` int DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `driver_phone` varchar(20) DEFAULT NULL,
  `driver_license` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visitors`
--

CREATE TABLE `visitors` (
  `id` int NOT NULL,
  `visitor_code` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `id_type` enum('National ID','Passport','Driving License','Other') DEFAULT 'National ID',
  `id_number` varchar(50) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `is_blacklisted` tinyint(1) DEFAULT '0',
  `blacklist_reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visitor_categories`
--

CREATE TABLE `visitor_categories` (
  `id` int NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `description` text,
  `color` varchar(7) DEFAULT '#3B82F6',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `visitor_categories`
--

INSERT INTO `visitor_categories` (`id`, `category_name`, `description`, `color`, `is_active`, `created_at`) VALUES
(1, 'Business', 'Business meetings and appointments', '#3B82F6', 1, '2025-09-20 15:00:27'),
(2, 'Delivery', 'Package and goods delivery', '#F59E0B', 1, '2025-09-20 15:00:27'),
(3, 'Maintenance', 'Maintenance and repair services', '#EF4444', 1, '2025-09-20 15:00:27'),
(4, 'Guest', 'Personal guests and visitors', '#10B981', 1, '2025-09-20 15:00:27'),
(5, 'Contractor', 'Construction and contract work', '#8B5CF6', 1, '2025-09-20 15:00:27');

-- --------------------------------------------------------

--
-- Stand-in structure for view `visitor_stats`
-- (See below for the actual view)
--
CREATE TABLE `visitor_stats` (
`avg_rating` decimal(14,4)
,`category_name` varchar(50)
,`company` varchar(100)
,`email` varchar(100)
,`full_name` varchar(100)
,`id` int
,`last_visit` timestamp
,`phone` varchar(20)
,`total_visits` bigint
);

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` int NOT NULL,
  `visit_code` varchar(20) NOT NULL,
  `visitor_id` int NOT NULL,
  `vehicle_id` int DEFAULT NULL,
  `host_name` varchar(100) DEFAULT NULL,
  `host_department` varchar(100) DEFAULT NULL,
  `host_phone` varchar(20) DEFAULT NULL,
  `host_email` varchar(100) DEFAULT NULL,
  `purpose` text,
  `visit_type` enum('Pre-registered','Walk-in','Scheduled') DEFAULT 'Walk-in',
  `expected_date` date DEFAULT NULL,
  `expected_time_in` time DEFAULT NULL,
  `expected_time_out` time DEFAULT NULL,
  `check_in_time` timestamp NULL DEFAULT NULL,
  `check_out_time` timestamp NULL DEFAULT NULL,
  `check_in_by` int DEFAULT NULL,
  `check_out_by` int DEFAULT NULL,
  `status` enum('Scheduled','Checked In','Checked Out','Expired','Cancelled') DEFAULT 'Scheduled',
  `qr_code` varchar(255) DEFAULT NULL,
  `access_areas` text,
  `special_instructions` text,
  `badge_number` varchar(20) DEFAULT NULL,
  `items_carried_in` text,
  `items_carried_out` text,
  `temperature_reading` decimal(4,1) DEFAULT NULL,
  `health_declaration` tinyint(1) DEFAULT '1',
  `notes` text,
  `rating` int DEFAULT NULL,
  `feedback` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_areas`
--
ALTER TABLE `access_areas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `entity_type_id` (`entity_type`,`entity_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `pre_registrations`
--
ALTER TABLE `pre_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_code` (`registration_code`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `status` (`status`),
  ADD KEY `visit_date` (`visit_date`),
  ADD KEY `idx_pre_registrations_visit_date` (`visit_date`);

--
-- Indexes for table `qr_templates`
--
ALTER TABLE `qr_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `last_activity` (`last_activity`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`);

--
-- Indexes for table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visitor_code` (`visitor_code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_visitors_created_at` (`created_at`);

--
-- Indexes for table `visitor_categories`
--
ALTER TABLE `visitor_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_code` (`visit_code`),
  ADD KEY `visitor_id` (`visitor_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `check_in_by` (`check_in_by`),
  ADD KEY `check_out_by` (`check_out_by`),
  ADD KEY `status` (`status`),
  ADD KEY `expected_date` (`expected_date`),
  ADD KEY `idx_visits_check_in_time` (`check_in_time`),
  ADD KEY `idx_visits_check_out_time` (`check_out_time`),
  ADD KEY `idx_visits_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_areas`
--
ALTER TABLE `access_areas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blacklist`
--
ALTER TABLE `blacklist`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pre_registrations`
--
ALTER TABLE `pre_registrations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_templates`
--
ALTER TABLE `qr_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visitor_categories`
--
ALTER TABLE `visitor_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `daily_visit_summary`
--
DROP TABLE IF EXISTS `daily_visit_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vxjtgclw`@`localhost` SQL SECURITY DEFINER VIEW `daily_visit_summary`  AS SELECT cast(`visits`.`check_in_time` as date) AS `visit_date`, count(0) AS `total_visits`, count((case when (`visits`.`status` = 'Checked In') then 1 end)) AS `checked_in`, count((case when (`visits`.`status` = 'Checked Out') then 1 end)) AS `checked_out`, count(distinct `visits`.`visitor_id`) AS `unique_visitors`, count(distinct `visits`.`vehicle_id`) AS `vehicles_count` FROM `visits` WHERE (`visits`.`check_in_time` is not null) GROUP BY cast(`visits`.`check_in_time` as date) ORDER BY `visit_date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `visitor_stats`
--
DROP TABLE IF EXISTS `visitor_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`vxjtgclw`@`localhost` SQL SECURITY DEFINER VIEW `visitor_stats`  AS SELECT `v`.`id` AS `id`, `v`.`full_name` AS `full_name`, `v`.`email` AS `email`, `v`.`phone` AS `phone`, `v`.`company` AS `company`, `vc`.`category_name` AS `category_name`, count(`vs`.`id`) AS `total_visits`, max(`vs`.`check_in_time`) AS `last_visit`, avg(`vs`.`rating`) AS `avg_rating` FROM ((`visitors` `v` left join `visitor_categories` `vc` on((`v`.`category_id` = `vc`.`id`))) left join `visits` `vs` on((`v`.`id` = `vs`.`visitor_id`))) GROUP BY `v`.`id` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD CONSTRAINT `blacklist_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pre_registrations`
--
ALTER TABLE `pre_registrations`
  ADD CONSTRAINT `pre_registrations_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pre_registrations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`);

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `visitors`
--
ALTER TABLE `visitors`
  ADD CONSTRAINT `visitors_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `visitor_categories` (`id`);

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`),
  ADD CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`),
  ADD CONSTRAINT `visits_ibfk_3` FOREIGN KEY (`check_in_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `visits_ibfk_4` FOREIGN KEY (`check_out_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
