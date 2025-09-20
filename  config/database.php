<?php
/**
 * Database Configuration
 * GatePass Pro - Smart Gate Management System
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'vxjtgclw_gatepass';
    private $username = 'vxjtgclw_gatepass';
    private $password = 'nS%?A,O?AO]41!C6';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }
        
        return $this->conn;
    }
}

// System Configuration
define('SITE_NAME', 'GatePass Pro');
define('SITE_URL', 'https://yourdomain.com');
define('UPLOAD_PATH', 'uploads/');
define('QR_CODE_PATH', 'qrcodes/');
define('VISITOR_PHOTOS_PATH', 'uploads/visitors/');
define('VEHICLE_PHOTOS_PATH', 'uploads/vehicles/');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', '');
define('SMTP_FROM_NAME', 'GatePass Pro');

// Security Configuration
define('JWT_SECRET', 'your-jwt-secret-key-change-this-in-production');
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutes

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);

// QR Code Configuration
define('QR_CODE_SIZE', 200);
define('QR_CODE_MARGIN', 2);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session Configuration
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// CORS Headers
function setCorsHeaders() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Response Helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Validation Helper
function validateInput($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = $field . ' is required';
            continue;
        }
        
        if (!empty($value)) {
            if (isset($rule['min']) && strlen($value) < $rule['min']) {
                $errors[$field] = $field . ' must be at least ' . $rule['min'] . ' characters';
            }
            
            if (isset($rule['max']) && strlen($value) > $rule['max']) {
                $errors[$field] = $field . ' must not exceed ' . $rule['max'] . ' characters';
            }
            
            if (isset($rule['email']) && $rule['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = $field . ' must be a valid email address';
            }
            
            if (isset($rule['phone']) && $rule['phone'] && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $value)) {
                $errors[$field] = $field . ' must be a valid phone number';
            }
        }
    }
    
    return $errors;
}

// Security Helper
function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
    } else {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
    }
    return $data;
}

// File Upload Helper
function handleFileUpload($file, $uploadPath, $allowedTypes = ['jpg', 'jpeg', 'png']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds maximum allowed size');
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedTypes)) {
        throw new Exception('File type not allowed');
    }
    
    $fileName = uniqid() . '.' . $fileExtension;
    $fullPath = $uploadPath . $fileName;
    
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new Exception('Failed to save file');
    }
    
    return $fileName;
}

// Logging Helper
function logActivity($userId, $action, $description, $ipAddress = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        $ipAddress = $ipAddress ?: $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->execute([$userId, $action, $description, $ipAddress, $userAgent]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Generate unique codes
function generateCode($prefix = '', $length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = $prefix;
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Date/Time Helpers
function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>