<?php
/**
 * Application Configuration
 * GatePass Pro - Smart Gate Management System
 */

// Environment Configuration
define('ENVIRONMENT', 'production'); // development, testing, production

// Application Information
define('APP_NAME', 'GatePass Pro');
define('APP_VERSION', '1.0.0');
define('APP_AUTHOR', 'GatePass Pro Team');
define('APP_DESCRIPTION', 'Smart Gate Management System');

// Base URLs and Paths
define('BASE_URL', ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']));
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');
define('API_URL', BASE_URL . '/api');

// Directory Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('EXPORTS_PATH', ROOT_PATH . '/exports');

// File Upload Configuration
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('VISITOR_PHOTOS_PATH', UPLOAD_PATH . 'visitors/');
define('VEHICLE_PHOTOS_PATH', UPLOAD_PATH . 'vehicles/');
define('DOCUMENTS_PATH', UPLOAD_PATH . 'documents/');
define('QR_CODE_PATH', ROOT_PATH . '/qrcodes/');

// File Size Limits (in bytes)
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);  // 5MB
define('MAX_DOCUMENT_SIZE', 20 * 1024 * 1024); // 20MB

// Allowed File Types
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv']);
define('ALLOWED_ARCHIVE_TYPES', ['zip', 'rar', '7z']);

// Image Processing
define('IMAGE_QUALITY', 85);
define('THUMBNAIL_WIDTH', 150);
define('THUMBNAIL_HEIGHT', 150);
define('MAX_IMAGE_WIDTH', 1920);
define('MAX_IMAGE_HEIGHT', 1080);

// QR Code Configuration
define('QR_CODE_SIZE', 300);
define('QR_CODE_MARGIN', 2);
define('QR_CODE_ERROR_CORRECTION', 'M'); // L, M, Q, H
define('QR_CODE_FORMAT', 'PNG');

// Security Configuration
define('JWT_SECRET', 'your-super-secret-jwt-key-change-this-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 3600); // 1 hour

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'gatepass_session');
define('SESSION_SECURE', true); // Set to true in production with HTTPS
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Lax');

// Password Requirements
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

// Login Security
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_RESET_EXPIRY', 3600); // 1 hour

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Date and Time
define('DEFAULT_TIMEZONE', 'Africa/Nairobi');
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'M j, Y');
define('DISPLAY_DATETIME_FORMAT', 'M j, Y g:i A');

// Email Configuration
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_FROM_ADDRESS', 'noreply@yourdomain.com');
define('MAIL_REPLY_TO', 'support@yourdomain.com');

// SMTP Settings (will be overridden by database settings)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls'); // tls, ssl, or empty
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_TIMEOUT', 30);

// Notification Settings
define('NOTIFICATION_BATCH_SIZE', 50);
define('NOTIFICATION_RETRY_ATTEMPTS', 3);
define('NOTIFICATION_RETRY_DELAY', 300); // 5 minutes

// Report Configuration
define('REPORT_MAX_RECORDS', 10000);
define('REPORT_TIMEOUT', 300); // 5 minutes
define('EXPORT_FORMATS', ['pdf', 'excel', 'csv']);

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hour
define('CACHE_PATH', ROOT_PATH . '/cache/');

// API Configuration
define('API_RATE_LIMIT', 100); // requests per minute
define('API_RATE_LIMIT_WINDOW', 60); // seconds
define('API_TIMEOUT', 30); // seconds

// Debug and Logging
if (ENVIRONMENT === 'development') {
    define('DEBUG_MODE', true);
    define('LOG_LEVEL', 'DEBUG');
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} elseif (ENVIRONMENT === 'testing') {
    define('DEBUG_MODE', true);
    define('LOG_LEVEL', 'INFO');
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    define('DEBUG_MODE', false);
    define('LOG_LEVEL', 'ERROR');
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Logging Configuration
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_MAX_FILES', 5);
define('LOG_DATE_FORMAT', 'Y-m-d H:i:s');

// Backup Configuration
define('BACKUP_ENABLED', true);
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_PATH', ROOT_PATH . '/backups/');

// Feature Flags
define('FEATURE_EMAIL_NOTIFICATIONS', true);
define('FEATURE_SMS_NOTIFICATIONS', false);
define('FEATURE_FACIAL_RECOGNITION', false);
define('FEATURE_BIOMETRIC_SCANNER', false);
define('FEATURE_VEHICLE_TRACKING', true);
define('FEATURE_VISITOR_PHOTOS', true);
define('FEATURE_BULK_IMPORT', true);
define('FEATURE_ADVANCED_REPORTS', true);

// Integration Settings
define('GOOGLE_MAPS_API_KEY', '');
define('TWILIO_SID', '');
define('TWILIO_TOKEN', '');
define('SLACK_WEBHOOK_URL', '');

// Performance Settings
define('ENABLE_COMPRESSION', true);
define('ENABLE_BROWSER_CACHE', true);
define('CACHE_BUSTING', true);

// Security Headers
define('SECURITY_HEADERS', [
    'X-Frame-Options' => 'SAMEORIGIN',
    'X-Content-Type-Options' => 'nosniff',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net;"
]);

// Application Constants
define('DEFAULT_VISIT_DURATION', 2); // hours
define('MAX_VISIT_DURATION', 24); // hours
define('VISITOR_CODE_LENGTH', 8);
define('VISIT_CODE_LENGTH', 10);
define('BADGE_NUMBER_LENGTH', 4);

// Status Constants
define('VISIT_STATUS_SCHEDULED', 'Scheduled');
define('VISIT_STATUS_CHECKED_IN', 'Checked In');
define('VISIT_STATUS_CHECKED_OUT', 'Checked Out');
define('VISIT_STATUS_EXPIRED', 'Expired');
define('VISIT_STATUS_CANCELLED', 'Cancelled');

define('VISITOR_STATUS_ACTIVE', 'Active');
define('VISITOR_STATUS_BLACKLISTED', 'Blacklisted');
define('VISITOR_STATUS_PENDING', 'Pending');

// Validation Rules
define('VALIDATION_RULES', [
    'phone' => '/^[\+]?[0-9\s\-\(\)]{10,20}$/',
    'email' => FILTER_VALIDATE_EMAIL,
    'plate_number' => '/^[A-Z0-9\-\s]{2,15}$/i',
    'visitor_code' => '/^[A-Z0-9]{8}$/',
    'visit_code' => '/^[A-Z0-9]{10}$/',
]);

// Maintenance Mode
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'System is under maintenance. Please try again later.');
define('MAINTENANCE_ALLOWED_IPS', ['127.0.0.1', '::1']);

// Localization
define('DEFAULT_LANGUAGE', 'en');
define('SUPPORTED_LANGUAGES', ['en', 'sw']);
define('CURRENCY_SYMBOL', 'KSh');
define('CURRENCY_CODE', 'KES');

// Mobile App Configuration
define('MOBILE_APP_VERSION', '1.0.0');
define('FORCE_APP_UPDATE', false);
define('APP_STORE_URL', '');
define('PLAY_STORE_URL', '');

// Analytics
define('ANALYTICS_ENABLED', false);
define('GOOGLE_ANALYTICS_ID', '');

// Third-party Services
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET_KEY', '');

// Auto-cleanup Settings
define('AUTO_CLEANUP_ENABLED', true);
define('CLEANUP_OLD_LOGS_DAYS', 30);
define('CLEANUP_OLD_SESSIONS_HOURS', 24);
define('CLEANUP_OLD_NOTIFICATIONS_DAYS', 90);
define('CLEANUP_OLD_QR_CODES_DAYS', 30);

// Initialize error handling
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');

// Custom Error Handler
function customErrorHandler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    $error = [
        'type' => 'Error',
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'timestamp' => date(DATETIME_FORMAT)
    ];
    
    logError($error);
    
    if (DEBUG_MODE) {
        echo "<b>Error:</b> {$message} in <b>{$file}</b> on line <b>{$line}</b><br>";
    }
    
    return true;
}

// Custom Exception Handler
function customExceptionHandler($exception) {
    $error = [
        'type' => 'Exception',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'timestamp' => date(DATETIME_FORMAT)
    ];
    
    logError($error);
    
    if (DEBUG_MODE) {
        echo "<b>Uncaught Exception:</b> " . $exception->getMessage();
        echo "<br><b>File:</b> " . $exception->getFile();
        echo "<br><b>Line:</b> " . $exception->getLine();
        echo "<br><b>Trace:</b><pre>" . $exception->getTraceAsString() . "</pre>";
    } else {
        echo "An unexpected error occurred. Please try again later.";
    }
}

// Logging Function
function logError($error) {
    $logFile = LOGS_PATH . '/error.log';
    
    if (!is_dir(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }
    
    $logEntry = "[{$error['timestamp']}] {$error['type']}: {$error['message']}";
    if (isset($error['file'])) {
        $logEntry .= " in {$error['file']}";
    }
    if (isset($error['line'])) {
        $logEntry .= " on line {$error['line']}";
    }
    $logEntry .= PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Set timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// Create required directories
$requiredDirs = [
    UPLOAD_PATH,
    VISITOR_PHOTOS_PATH,
    VEHICLE_PHOTOS_PATH,
    DOCUMENTS_PATH,
    QR_CODE_PATH,
    LOGS_PATH,
    EXPORTS_PATH
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        
        // Create .htaccess for security
        if (strpos($dir, 'uploads') !== false) {
            file_put_contents($dir . '.htaccess', "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|pdf)$\">\nAllow from all\n</Files>");
        }
    }
}

// Utility Functions
function generateUniqueId($prefix = '', $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $id = $prefix;
    for ($i = 0; $i < $length; $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $id;
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone($phone) {
    return preg_match(VALIDATION_RULES['phone'], $phone);
}

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    return trim($filename, '.-');
}

function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isMaintenanceMode() {
    if (!MAINTENANCE_MODE) {
        return false;
    }
    
    $clientIP = getClientIP();
    return !in_array($clientIP, MAINTENANCE_ALLOWED_IPS);
}
?>