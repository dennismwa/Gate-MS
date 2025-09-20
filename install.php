<?php
/**
 * Installation Script - FIXED VERSION
 * GatePass Pro - Smart Gate Management System
 */

// Start session at the beginning
session_start();

// Prevent access if already installed
if (file_exists('config/installed.lock')) {
    header('Location: index.php');
    exit;
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Process installation steps
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // Database configuration test
            $host = $_POST['db_host'] ?? '';
            $name = $_POST['db_name'] ?? '';
            $user = $_POST['db_user'] ?? '';
            $pass = $_POST['db_pass'] ?? '';
            
            // Validate input
            if (empty($host) || empty($name) || empty($user)) {
                $errors[] = 'Database host, name, and username are required';
                break;
            }
            
            try {
                $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8", $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
                // Test the connection
                $pdo->query("SELECT 1");
                
                // Store database config in session
                $_SESSION['db_config'] = [
                    'host' => $host,
                    'name' => $name,
                    'user' => $user,
                    'pass' => $pass
                ];
                
                $success[] = 'Database connection successful!';
                $step = 3;
            } catch (PDOException $e) {
                $errors[] = 'Database connection failed: ' . $e->getMessage();
            }
            break;
            
        case 3:
            // Create database tables and initial data
            if (!isset($_SESSION['db_config'])) {
                $errors[] = 'Database configuration not found. Please go back to step 2.';
                break;
            }
            
            $dbConfig = $_SESSION['db_config'];
            
            try {
                $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8", 
                              $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
                // Read and execute SQL file
                if (!file_exists('gatepass_database.sql')) {
                    // Create the SQL if file doesn't exist
                    createDatabaseSQL();
                }
                
                $sql = file_get_contents('gatepass_database.sql');
                
                // Split by semicolon and execute each statement
                $statements = preg_split('/;\s*$/m', $sql);
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                        try {
                            $pdo->exec($statement);
                        } catch (PDOException $e) {
                            // Log but don't fail on duplicate table errors
                            if (!strpos($e->getMessage(), 'already exists')) {
                                throw $e;
                            }
                        }
                    }
                }
                
                $success[] = 'Database tables created successfully!';
                $step = 4;
            } catch (Exception $e) {
                $errors[] = 'Database setup failed: ' . $e->getMessage();
            }
            break;
            
        case 4:
            // Create admin user
            if (!isset($_SESSION['db_config'])) {
                $errors[] = 'Database configuration not found. Please restart installation.';
                break;
            }
            
            $dbConfig = $_SESSION['db_config'];
            
            $adminUser = trim($_POST['admin_username'] ?? '');
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminConfirm = $_POST['admin_confirm'] ?? '';
            $companyName = trim($_POST['company_name'] ?? '');
            
            if (empty($adminUser) || empty($adminEmail) || empty($adminPassword)) {
                $errors[] = 'All admin fields are required';
            } elseif ($adminPassword !== $adminConfirm) {
                $errors[] = 'Passwords do not match';
            } elseif (strlen($adminPassword) < 6) {
                $errors[] = 'Password must be at least 6 characters';
            } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address';
            } else {
                try {
                    $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8", 
                                  $dbConfig['user'], $dbConfig['pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    
                    // Update admin user
                    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ? WHERE id = 1");
                    $stmt->execute([$adminUser, $adminEmail, $hashedPassword, 'System Administrator']);
                    
                    // Update company settings
                    if (!empty($companyName)) {
                        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'company_name'");
                        $stmt->execute([$companyName]);
                        
                        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'site_name'");
                        $stmt->execute([$companyName . ' - GatePass Pro']);
                    }
                    
                    // Create config file
                    createConfigFile($dbConfig);
                    
                    // Create required directories
                    createDirectories();
                    
                    // Create installation lock
                    file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
                    
                    $success[] = 'Installation completed successfully!';
                    $step = 5;
                } catch (Exception $e) {
                    $errors[] = 'Admin user creation failed: ' . $e->getMessage();
                }
            }
            break;
    }
}

function createConfigFile($dbConfig) {
    $configContent = "<?php
/**
 * Database Configuration - Auto Generated
 * GatePass Pro - Smart Gate Management System
 */

class Database {
    private \$host = '{$dbConfig['host']}';
    private \$db_name = '{$dbConfig['name']}';
    private \$username = '{$dbConfig['user']}';
    private \$password = '{$dbConfig['pass']}';
    private \$conn;

    public function getConnection() {
        \$this->conn = null;
        
        try {
            \$this->conn = new PDO(
                \"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name . \";charset=utf8\",
                \$this->username,
                \$this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8\"
                )
            );
        } catch(PDOException \$exception) {
            error_log(\"Connection error: \" . \$exception->getMessage());
            throw new Exception(\"Database connection failed\");
        }
        
        return \$this->conn;
    }
}

// System Configuration
define('SITE_NAME', 'GatePass Pro');
define('SITE_URL', 'http' . (isset(\$_SERVER['HTTPS']) ? 's' : '') . '://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['SCRIPT_NAME']));
define('UPLOAD_PATH', 'uploads/');
define('QR_CODE_PATH', 'qrcodes/');
define('VISITOR_PHOTOS_PATH', 'uploads/visitors/');
define('VEHICLE_PHOTOS_PATH', 'uploads/vehicles/');

// Email Configuration
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', '');
define('SMTP_FROM_NAME', 'GatePass Pro');

// Security Configuration
define('JWT_SECRET', '" . bin2hex(random_bytes(32)) . "');
define('SESSION_LIFETIME', 3600);
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300);

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);

// QR Code Configuration
define('QR_CODE_SIZE', 200);
define('QR_CODE_MARGIN', 2);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// CORS Headers
function setCorsHeaders() {
    header(\"Access-Control-Allow-Origin: *\");
    header(\"Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS\");
    header(\"Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With\");
    
    if (\$_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Response Helper
function jsonResponse(\$data, \$status = 200) {
    http_response_code(\$status);
    header('Content-Type: application/json');
    echo json_encode(\$data);
    exit();
}

// Validation Helper
function validateInput(\$data, \$rules) {
    \$errors = [];
    
    foreach (\$rules as \$field => \$rule) {
        \$value = \$data[\$field] ?? '';
        
        if (isset(\$rule['required']) && \$rule['required'] && empty(\$value)) {
            \$errors[\$field] = \$field . ' is required';
            continue;
        }
        
        if (!empty(\$value)) {
            if (isset(\$rule['min']) && strlen(\$value) < \$rule['min']) {
                \$errors[\$field] = \$field . ' must be at least ' . \$rule['min'] . ' characters';
            }
            
            if (isset(\$rule['max']) && strlen(\$value) > \$rule['max']) {
                \$errors[\$field] = \$field . ' must not exceed ' . \$rule['max'] . ' characters';
            }
            
            if (isset(\$rule['email']) && \$rule['email'] && !filter_var(\$value, FILTER_VALIDATE_EMAIL)) {
                \$errors[\$field] = \$field . ' must be a valid email address';
            }
            
            if (isset(\$rule['phone']) && \$rule['phone'] && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', \$value)) {
                \$errors[\$field] = \$field . ' must be a valid phone number';
            }
        }
    }
    
    return \$errors;
}

// Security Helper
function sanitizeInput(\$data) {
    if (is_array(\$data)) {
        foreach (\$data as \$key => \$value) {
            \$data[\$key] = sanitizeInput(\$value);
        }
    } else {
        \$data = trim(\$data);
        \$data = stripslashes(\$data);
        \$data = htmlspecialchars(\$data, ENT_QUOTES, 'UTF-8');
    }
    return \$data;
}

// Logging Helper
function logActivity(\$userId, \$action, \$description, \$ipAddress = null) {
    try {
        \$database = new Database();
        \$db = \$database->getConnection();
        
        \$query = \"INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?)\";
        \$stmt = \$db->prepare(\$query);
        
        \$ipAddress = \$ipAddress ?: \$_SERVER['REMOTE_ADDR'] ?? '';
        \$userAgent = \$_SERVER['HTTP_USER_AGENT'] ?? '';
        
        \$stmt->execute([\$userId, \$action, \$description, \$ipAddress, \$userAgent]);
    } catch (Exception \$e) {
        error_log(\"Failed to log activity: \" . \$e->getMessage());
    }
}

// Generate unique codes
function generateCode(\$prefix = '', \$length = 8) {
    \$characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    \$code = \$prefix;
    for (\$i = 0; \$i < \$length; \$i++) {
        \$code .= \$characters[rand(0, strlen(\$characters) - 1)];
    }
    return \$code;
}

// Date/Time Helpers
function formatDateTime(\$datetime, \$format = 'Y-m-d H:i:s') {
    if (empty(\$datetime)) return '';
    return date(\$format, strtotime(\$datetime));
}

function getTimeAgo(\$datetime) {
    \$time = time() - strtotime(\$datetime);
    
    if (\$time < 60) return 'just now';
    if (\$time < 3600) return floor(\$time/60) . ' minutes ago';
    if (\$time < 86400) return floor(\$time/3600) . ' hours ago';
    if (\$time < 2592000) return floor(\$time/86400) . ' days ago';
    
    return date('M j, Y', strtotime(\$datetime));
}
?>";

    if (!is_dir('config')) {
        mkdir('config', 0755, true);
    }
    
    file_put_contents('config/database.php', $configContent);
}

function createDirectories() {
    $dirs = [
        'uploads',
        'uploads/visitors',
        'uploads/vehicles', 
        'uploads/documents',
        'qrcodes',
        'logs',
        'exports',
        'backups'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            
            // Create security .htaccess for upload directories
            if (strpos($dir, 'uploads') !== false) {
                file_put_contents($dir . '/.htaccess', 
                    "Options -Indexes\n" .
                    "deny from all\n" .
                    "<Files ~ \"\\.(jpg|jpeg|png|gif|pdf)$\">\n" .
                    "allow from all\n" .
                    "</Files>"
                );
            }
        }
    }
}

function createDatabaseSQL() {
    $sql = "-- GatePass Pro Database Schema
-- Auto-generated during installation

CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS `user_roles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `role_name` varchar(50) NOT NULL,
    `permissions` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `user_roles` (`role_name`, `permissions`) VALUES
('Super Admin', '[\"all\"]'),
('Admin', '[\"dashboard\", \"visitors\", \"vehicles\", \"staff\", \"reports\", \"settings\"]'),
('Security', '[\"dashboard\", \"visitors\", \"vehicles\", \"checkin\", \"checkout\"]'),
('Receptionist', '[\"dashboard\", \"visitors\", \"checkin\", \"checkout\", \"reports\"]');

CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `full_name` varchar(100) NOT NULL,
    `phone` varchar(20),
    `role_id` int(11) NOT NULL DEFAULT 1,
    `profile_photo` varchar(255),
    `is_active` tinyint(1) DEFAULT 1,
    `last_login` timestamp NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role_id`) VALUES
('admin', 'admin@example.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 1);

CREATE TABLE IF NOT EXISTS `visitor_categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `category_name` varchar(50) NOT NULL,
    `description` text,
    `color` varchar(7) DEFAULT '#3B82F6',
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `visitor_categories` (`category_name`, `description`, `color`) VALUES
('Business', 'Business meetings and appointments', '#3B82F6'),
('Delivery', 'Package and goods delivery', '#F59E0B'),
('Maintenance', 'Maintenance and repair services', '#EF4444'),
('Guest', 'Personal guests and visitors', '#10B981'),
('Contractor', 'Construction and contract work', '#8B5CF6');

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
    KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    KEY `expected_date` (`expected_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    KEY `visit_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` varchar(128) NOT NULL,
    `user_id` int(11),
    `ip_address` varchar(45),
    `user_agent` text,
    `payload` longtext,
    `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    file_put_contents('gatepass_database.sql', $sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GatePass Pro Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="bg-blue-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ri-shield-check-line text-3xl text-white"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">GatePass Pro Installation</h1>
                <p class="text-gray-600">Smart Gate Management System Setup</p>
            </div>

            <!-- Progress Steps -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center <?php echo $step >= $i ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-600'; ?>">
                            <?php if ($step > $i): ?>
                                <i class="ri-check-line"></i>
                            <?php else: ?>
                                <?php echo $i; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($i < 5): ?>
                        <div class="w-16 h-1 <?php echo $step > $i ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="flex justify-between mt-2 text-sm text-gray-600">
                    <span>Welcome</span>
                    <span>Database</span>
                    <span>Tables</span>
                    <span>Admin</span>
                    <span>Complete</span>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center">
                    <i class="ri-error-warning-line mr-2"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Success Messages -->
            <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center">
                    <i class="ri-check-circle-line mr-2"></i>
                    <div>
                        <?php foreach ($success as $msg): ?>
                        <p><?php echo htmlspecialchars($msg); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Installation Steps -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <?php if ($step == 1): ?>
                <!-- Step 1: Welcome -->
                <div class="text-center">
                    <h2 class="text-2xl font-bold mb-4">Welcome to GatePass Pro</h2>
                    <p class="text-gray-600 mb-6">This installation wizard will help you set up your gate management system. Please ensure you have the following ready:</p>
                    
                    <div class="text-left bg-gray-50 p-6 rounded-lg mb-6">
                        <h3 class="font-semibold mb-3">Requirements:</h3>
                        <ul class="space-y-2">
                            <li class="flex items-center">
                                <i class="ri-check-circle-line text-green-600 mr-2"></i>
                                PHP 7.4 or higher
                            </li>
                            <li class="flex items-center">
                                <i class="ri-check-circle-line text-green-600 mr-2"></i>
                                MySQL 5.7 or higher
                            </li>
                            <li class="flex items-center">
                                <i class="ri-check-circle-line text-green-600 mr-2"></i>
                                Database credentials
                            </li>
                            <li class="flex items-center">
                                <i class="ri-check-circle-line text-green-600 mr-2"></i>
                                Write permissions for uploads folder
                            </li>
                        </ul>
                    </div>
                    
                    <a href="?step=2" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200 inline-flex items-center">
                        Get Started
                        <i class="ri-arrow-right-line ml-2"></i>
                    </a>
                </div>

                <?php elseif ($step == 2): ?>
                <!-- Step 2: Database Configuration -->
                <h2 class="text-2xl font-bold mb-6">Database Configuration</h2>
                <p class="text-gray-600 mb-6">Please enter your database connection details:</p>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Host</label>
                        <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Name</label>
                        <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'vxjtgclw_gatepass'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Username</label>
                        <input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'vxjtgclw_gatepass'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>
                        <input type="password" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? 'nS%?A,O?AO]41!C6'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="flex space-x-4">
                        <a href="?step=1" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition duration-200 text-center">
                            Back
                        </a>
                        <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            Test Connection
                        </button>
                    </div>
                </form>

                <?php elseif ($step == 3): ?>
                <!-- Step 3: Create Tables -->
                <h2 class="text-2xl font-bold mb-6">Create Database Tables</h2>
                <p class="text-gray-600 mb-6">Click the button below to create the required database tables and initial data:</p>
                
                <form method="POST" class="space-y-6">
                    <div class="bg-blue-50 p-6 rounded-lg">
                        <h3 class="font-semibold mb-2">What will be created:</h3>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• User accounts and roles</li>
                            <li>• Visitor and vehicle management tables</li>
                            <li>• Visit tracking and QR code tables</li>
                            <li>• Notification and activity logs</li>
                            <li>• System settings and configuration</li>
                        </ul>
                    </div>
                    
                    <div class="flex space-x-4">
                        <a href="?step=2" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition duration-200 text-center">
                            Back
                        </a>
                        <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            Create Tables
                        </button>
                    </div>
                </form>

                <?php elseif ($step == 4): ?>
                <!-- Step 4: Admin User -->
                <h2 class="text-2xl font-bold mb-6">Create Admin Account</h2>
                <p class="text-gray-600 mb-6">Create your administrator account and configure basic settings:</p>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company/Organization Name</label>
                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($_POST['company_name'] ?? 'Your Company'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Username</label>
                        <input type="text" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? 'admin'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email</label>
                        <input type="email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? 'admin@example.com'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password</label>
                        <input type="password" name="admin_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <p class="text-sm text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" name="admin_confirm" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div class="flex space-x-4">
                        <a href="?step=3" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition duration-200 text-center">
                            Back
                        </a>
                        <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            Create Admin
                        </button>
                    </div>
                </form>

                <?php elseif ($step == 5): ?>
                <!-- Step 5: Complete -->
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="ri-check-circle-line text-4xl text-green-600"></i>
                    </div>
                    
                    <h2 class="text-2xl font-bold mb-4">Installation Complete!</h2>
                    <p class="text-gray-600 mb-6">GatePass Pro has been successfully installed and configured. You can now start using your gate management system.</p>
                    
                    <div class="bg-green-50 p-6 rounded-lg mb-6 text-left">
                        <h3 class="font-semibold mb-3">Next Steps:</h3>
                        <ul class="space-y-2">
                            <li class="flex items-center">
                                <i class="ri-arrow-right-circle-line text-green-600 mr-2"></i>
                                Login with your admin credentials
                            </li>
                            <li class="flex items-center">
                                <i class="ri-arrow-right-circle-line text-green-600 mr-2"></i>
                                Configure email settings (optional)
                            </li>
                            <li class="flex items-center">
                                <i class="ri-arrow-right-circle-line text-green-600 mr-2"></i>
                                Add visitor categories
                            </li>
                            <li class="flex items-center">
                                <i class="ri-arrow-right-circle-line text-green-600 mr-2"></i>
                                Create additional user accounts
                            </li>
                            <li class="flex items-center">
                                <i class="ri-arrow-right-circle-line text-green-600 mr-2"></i>
                                Test QR code scanning functionality
                            </li>
                        </ul>
                    </div>
                    
                    <div class="space-y-4">
                        <a href="index.php" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200 inline-flex items-center justify-center">
                            <i class="ri-login-circle-line mr-2"></i>
                            Go to Login
                        </a>
                        
                        <div class="text-sm text-gray-500">
                            <p>For security reasons, please delete or rename the install.php file.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-sm text-gray-500">
                <p>&copy; <?php echo date('Y'); ?> GatePass Pro. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
