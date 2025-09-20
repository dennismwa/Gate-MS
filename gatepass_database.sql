-- Gate Management System Database Schema
-- Database: vxjtgclw_gatepass

-- System settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
);

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'GatePass Pro'),
('primary_color', '#3B82F6'),
('secondary_color', '#1F2937'),
('accent_color', '#10B981'),
('email_notifications', '1'),
('sms_notifications', '0'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('company_name', 'Your Company'),
('company_address', ''),
('company_phone', ''),
('company_email', '');

-- User roles table
CREATE TABLE IF NOT EXISTS `user_roles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `role_name` varchar(50) NOT NULL,
    `permissions` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `role_name` (`role_name`)
);

-- Insert default roles
INSERT INTO `user_roles` (`role_name`, `permissions`) VALUES
('Super Admin', '["all"]'),
('Admin', '["dashboard", "visitors", "vehicles", "staff", "reports", "settings"]'),
('Security', '["dashboard", "visitors", "vehicles", "checkin", "checkout"]'),
('Receptionist', '["dashboard", "visitors", "checkin", "checkout", "reports"]');

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `full_name` varchar(100) NOT NULL,
    `phone` varchar(20),
    `role_id` int(11) NOT NULL,
    `profile_photo` varchar(255),
    `is_active` tinyint(1) DEFAULT 1,
    `last_login` timestamp NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `role_id` (`role_id`),
    FOREIGN KEY (`role_id`) REFERENCES `user_roles`(`id`)
);

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role_id`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 1);

-- Visitor categories table
CREATE TABLE IF NOT EXISTS `visitor_categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `category_name` varchar(50) NOT NULL,
    `description` text,
    `color` varchar(7) DEFAULT '#3B82F6',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);

-- Insert default visitor categories
INSERT INTO `visitor_categories` (`category_name`, `description`, `color`) VALUES
('Business', 'Business meetings and appointments', '#3B82F6'),
('Delivery', 'Package and goods delivery', '#F59E0B'),
('Maintenance', 'Maintenance and repair services', '#EF4444'),
('Guest', 'Personal guests and visitors', '#10B981'),
('Contractor', 'Construction and contract work', '#8B5CF6');

-- Visitors table
CREATE TABLE IF NOT EXISTS `visitors` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `visitor_code` varchar(20) NOT NULL,
    `full_name` varchar(100) NOT NULL,
    `email` varchar(100),
    `phone` varchar(20),
    `company` varchar(100),
    `id_type` enum('National ID', 'Passport', 'Driving License', 'Other') DEFAULT 'National ID',
    `id_number` varchar(50),
    `photo` varchar(255),
    `category_id` int(11),
    `emergency_contact_name` varchar(100),
    `emergency_contact_phone` varchar(20),
    `is_blacklisted` tinyint(1) DEFAULT 0,
    `blacklist_reason` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `visitor_code` (`visitor_code`),
    KEY `category_id` (`category_id`),
    FOREIGN KEY (`category_id`) REFERENCES `visitor_categories`(`id`)
);

-- Vehicles table
CREATE TABLE IF NOT EXISTS `vehicles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `plate_number` varchar(20) NOT NULL,
    `vehicle_type` enum('Car', 'Motorcycle', 'Van', 'Truck', 'Bus', 'Other') DEFAULT 'Car',
    `make` varchar(50),
    `model` varchar(50),
    `color` varchar(30),
    `owner_type` enum('Visitor', 'Staff', 'Company') DEFAULT 'Visitor',
    `owner_id` int(11),
    `driver_name` varchar(100),
    `driver_phone` varchar(20),
    `driver_license` varchar(50),
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `plate_number` (`plate_number`)
);

-- Visits table (main entry/exit records)
CREATE TABLE IF NOT EXISTS `visits` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `visit_code` varchar(20) NOT NULL,
    `visitor_id` int(11) NOT NULL,
    `vehicle_id` int(11),
    `host_name` varchar(100),
    `host_department` varchar(100),
    `host_phone` varchar(20),
    `host_email` varchar(100),
    `purpose` text,
    `visit_type` enum('Pre-registered', 'Walk-in', 'Scheduled') DEFAULT 'Walk-in',
    `expected_date` date,
    `expected_time_in` time,
    `expected_time_out` time,
    `check_in_time` timestamp NULL,
    `check_out_time` timestamp NULL,
    `check_in_by` int(11),
    `check_out_by` int(11),
    `status` enum('Scheduled', 'Checked In', 'Checked Out', 'Expired', 'Cancelled') DEFAULT 'Scheduled',
    `qr_code` varchar(255),
    `access_areas` text,
    `special_instructions` text,
    `badge_number` varchar(20),
    `items_carried_in` text,
    `items_carried_out` text,
    `temperature_reading` decimal(4,1),
    `health_declaration` tinyint(1) DEFAULT 1,
    `notes` text,
    `rating` int(1),
    `feedback` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `visit_code` (`visit_code`),
    KEY `visitor_id` (`visitor_id`),
    KEY `vehicle_id` (`vehicle_id`),
    KEY `check_in_by` (`check_in_by`),
    KEY `check_out_by` (`check_out_by`),
    KEY `status` (`status`),
    KEY `expected_date` (`expected_date`),
    FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`),
    FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`),
    FOREIGN KEY (`check_in_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`check_out_by`) REFERENCES `users`(`id`)
);

-- Pre-registrations table
CREATE TABLE IF NOT EXISTS `pre_registrations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `registration_code` varchar(20) NOT NULL,
    `visitor_name` varchar(100) NOT NULL,
    `visitor_email` varchar(100),
    `visitor_phone` varchar(20),
    `visitor_company` varchar(100),
    `host_name` varchar(100) NOT NULL,
    `host_department` varchar(100),
    `host_email` varchar(100),
    `visit_date` date NOT NULL,
    `visit_time` time NOT NULL,
    `duration_hours` int(11) DEFAULT 2,
    `purpose` text,
    `vehicle_plate` varchar(20),
    `special_requirements` text,
    `status` enum('Pending', 'Approved', 'Rejected', 'Expired', 'Used') DEFAULT 'Pending',
    `approved_by` int(11),
    `approval_notes` text,
    `created_by` int(11),
    `qr_code` varchar(255),
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `registration_code` (`registration_code`),
    KEY `approved_by` (`approved_by`),
    KEY `created_by` (`created_by`),
    KEY `status` (`status`),
    KEY `visit_date` (`visit_date`),
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
);

-- Access areas table
CREATE TABLE IF NOT EXISTS `access_areas` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `area_name` varchar(100) NOT NULL,
    `description` text,
    `requires_escort` tinyint(1) DEFAULT 0,
    `max_occupancy` int(11),
    `is_restricted` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);

-- Insert default access areas
INSERT INTO `access_areas` (`area_name`, `description`) VALUES
('Reception', 'Main reception and waiting area'),
('Office Floor 1', 'First floor offices'),
('Office Floor 2', 'Second floor offices'),
('Conference Rooms', 'Meeting and conference rooms'),
('Parking Area', 'Vehicle parking zones'),
('Cafeteria', 'Dining and break areas');

-- Blacklist table
CREATE TABLE IF NOT EXISTS `blacklist` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `entity_type` enum('Visitor', 'Vehicle', 'Company') NOT NULL,
    `entity_id` varchar(100) NOT NULL,
    `reason` text NOT NULL,
    `added_by` int(11) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `added_by` (`added_by`),
    KEY `entity_type_id` (`entity_type`, `entity_id`),
    FOREIGN KEY (`added_by`) REFERENCES `users`(`id`)
);

-- Notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11),
    `type` varchar(50) NOT NULL,
    `title` varchar(200) NOT NULL,
    `message` text,
    `data` text,
    `is_read` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `is_read` (`is_read`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11),
    `action` varchar(100) NOT NULL,
    `description` text,
    `ip_address` varchar(45),
    `user_agent` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Sessions table
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` varchar(128) NOT NULL,
    `user_id` int(11),
    `ip_address` varchar(45),
    `user_agent` text,
    `payload` longtext,
    `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `last_activity` (`last_activity`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Reports table
CREATE TABLE IF NOT EXISTS `reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_name` varchar(100) NOT NULL,
    `report_type` varchar(50) NOT NULL,
    `parameters` text,
    `generated_by` int(11) NOT NULL,
    `file_path` varchar(255),
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `generated_by` (`generated_by`),
    FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`)
);

-- QR Code templates table
CREATE TABLE IF NOT EXISTS `qr_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `template_name` varchar(100) NOT NULL,
    `template_data` text NOT NULL,
    `is_default` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);

-- Insert default QR template
INSERT INTO `qr_templates` (`template_name`, `template_data`, `is_default`) VALUES
('Default Visit Card', '{"front": {"logo": true, "visitor_name": true, "company": true, "visit_date": true, "host": true, "badge_number": true}, "back": {"qr_code": true, "instructions": true, "emergency_contact": true}}', 1);

-- Indexes for performance
CREATE INDEX idx_visits_check_in_time ON visits(check_in_time);
CREATE INDEX idx_visits_check_out_time ON visits(check_out_time);
CREATE INDEX idx_visits_created_at ON visits(created_at);
CREATE INDEX idx_visitors_created_at ON visitors(created_at);
CREATE INDEX idx_pre_registrations_visit_date ON pre_registrations(visit_date);

-- Views for common queries
CREATE VIEW visitor_stats AS
SELECT 
    v.id,
    v.full_name,
    v.email,
    v.phone,
    v.company,
    vc.category_name,
    COUNT(vs.id) as total_visits,
    MAX(vs.check_in_time) as last_visit,
    AVG(vs.rating) as avg_rating
FROM visitors v
LEFT JOIN visitor_categories vc ON v.category_id = vc.id
LEFT JOIN visits vs ON v.id = vs.visitor_id
GROUP BY v.id;

CREATE VIEW daily_visit_summary AS
SELECT 
    DATE(check_in_time) as visit_date,
    COUNT(*) as total_visits,
    COUNT(CASE WHEN status = 'Checked In' THEN 1 END) as checked_in,
    COUNT(CASE WHEN status = 'Checked Out' THEN 1 END) as checked_out,
    COUNT(DISTINCT visitor_id) as unique_visitors,
    COUNT(DISTINCT vehicle_id) as vehicles_count
FROM visits 
WHERE check_in_time IS NOT NULL
GROUP BY DATE(check_in_time)
ORDER BY visit_date DESC;
