<?php
/**
 * Authentication System - FIXED VERSION
 * GatePass Pro - Smart Gate Management System
 */

require_once 'config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
        } catch (Exception $e) {
            error_log("Database connection error in Auth: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function login($username, $password) {
        try {
            // Check for too many failed attempts
            $this->checkLoginAttempts($username);
            
            $query = "SELECT u.*, ur.role_name, ur.permissions 
                     FROM users u 
                     LEFT JOIN user_roles ur ON u.role_id = ur.id 
                     WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $this->clearLoginAttempts($username);
                $this->updateLastLogin($user['id']);
                $sessionToken = $this->createSession($user);
                
                $this->logActivity($user['id'], 'LOGIN', 'User logged in successfully');
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role_name'],
                        'permissions' => json_decode($user['permissions'] ?? '[]', true)
                    ],
                    'token' => $sessionToken
                ];
            } else {
                // Failed login
                $this->recordFailedAttempt($username);
                $this->logActivity(null, 'LOGIN_FAILED', 'Failed login attempt for: ' . $username);
                
                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ];
        }
    }
    
    public function logout($userId) {
        try {
            // Destroy session
            $query = "DELETE FROM user_sessions WHERE user_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            
            $this->logActivity($userId, 'LOGOUT', 'User logged out');
            
            return ['success' => true];
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Logout failed'];
        }
    }
    
    public function validateSession($token) {
        try {
            $query = "SELECT s.*, u.*, ur.role_name, ur.permissions 
                     FROM user_sessions s
                     LEFT JOIN users u ON s.user_id = u.id
                     LEFT JOIN user_roles ur ON u.role_id = ur.id
                     WHERE s.id = ? AND u.is_active = 1 
                     AND s.last_activity > DATE_SUB(NOW(), INTERVAL 3600 SECOND)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$token]);
            $session = $stmt->fetch();
            
            if ($session) {
                // Update last activity
                $updateQuery = "UPDATE user_sessions SET last_activity = NOW() WHERE id = ?";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute([$token]);
                
                return [
                    'valid' => true,
                    'user' => [
                        'id' => $session['user_id'],
                        'username' => $session['username'],
                        'email' => $session['email'],
                        'full_name' => $session['full_name'],
                        'role' => $session['role_name'],
                        'permissions' => json_decode($session['permissions'] ?? '[]', true)
                    ]
                ];
            }
            
            return ['valid' => false];
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return ['valid' => false];
        }
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Verify current password
            $query = "SELECT password FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            // Validate new password
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'New password must be at least 6 characters long'];
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([$hashedPassword, $userId]);
            
            $this->logActivity($userId, 'PASSWORD_CHANGE', 'User changed password');
            
            return ['success' => true, 'message' => 'Password changed successfully'];
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }
    
    public function createUser($userData) {
        try {
            // Validate input
            $errors = $this->validateInput($userData, [
                'username' => ['required' => true, 'min' => 3, 'max' => 50],
                'email' => ['required' => true, 'email' => true],
                'password' => ['required' => true, 'min' => 6],
                'full_name' => ['required' => true, 'max' => 100],
                'role_id' => ['required' => true]
            ]);
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if username or email already exists
            $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$userData['username'], $userData['email']]);
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Create user
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, password, full_name, phone, role_id, is_active) 
                     VALUES (?, ?, ?, ?, ?, ?, 1)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $userData['username'],
                $userData['email'],
                $hashedPassword,
                $userData['full_name'],
                $userData['phone'] ?? null,
                $userData['role_id']
            ]);
            
            $userId = $this->db->lastInsertId();
            $this->logActivity($userId, 'USER_CREATED', 'New user account created');
            
            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            error_log("User creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create user'];
        }
    }
    
    private function createSession($user) {
        $sessionId = bin2hex(random_bytes(32));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $query = "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, payload) 
                 VALUES (?, ?, ?, ?, ?)";
        
        $payload = json_encode([
            'user_id' => $user['id'],
            'login_time' => time()
        ]);
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$sessionId, $user['id'], $ipAddress, $userAgent, $payload]);
        
        return $sessionId;
    }
    
    private function updateLastLogin($userId) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
    }
    
    private function checkLoginAttempts($username) {
        // Check for login attempts in the database
        $key = 'login_attempts_' . md5($username);
        
        // For now, we'll use session-based attempt tracking
        // In production, consider using Redis or database storage
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $attempts = $_SESSION[$key] ?? 0;
        $lastAttempt = $_SESSION[$key . '_time'] ?? 0;
        
        if ($attempts >= 5 && (time() - $lastAttempt) < 900) { // 15 minutes lockout
            throw new Exception('Too many failed attempts. Please try again later.');
        }
        
        if ((time() - $lastAttempt) > 900) {
            unset($_SESSION[$key]);
            unset($_SESSION[$key . '_time']);
        }
    }
    
    private function recordFailedAttempt($username) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $key = 'login_attempts_' . md5($username);
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        $_SESSION[$key . '_time'] = time();
    }
    
    private function clearLoginAttempts($username) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $key = 'login_attempts_' . md5($username);
        unset($_SESSION[$key]);
        unset($_SESSION[$key . '_time']);
    }
    
    public function hasPermission($user, $permission) {
        if (in_array('all', $user['permissions'])) {
            return true;
        }
        
        return in_array($permission, $user['permissions']);
    }
    
    public function requirePermission($user, $permission) {
        if (!$this->hasPermission($user, $permission)) {
            http_response_code(403);
            jsonResponse(['error' => 'Access denied']);
        }
    }
    
    private function validateInput($data, $rules) {
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
            }
        }
        
        return $errors;
    }
    
    private function logActivity($userId, $action, $description, $ipAddress = null) {
        try {
            $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            
            $ipAddress = $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? '');
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt->execute([$userId, $action, $description, $ipAddress, $userAgent]);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}

// Authentication middleware
function requireAuth() {
    $headers = getallheaders();
    if (!$headers) {
        $headers = [];
    }
    
    $token = $headers['Authorization'] ?? $_GET['token'] ?? $_POST['token'] ?? null;
    
    if (!$token) {
        http_response_code(401);
        jsonResponse(['error' => 'Authentication required']);
    }
    
    // Remove "Bearer " prefix if present
    $token = str_replace('Bearer ', '', $token);
    
    try {
        $auth = new Auth();
        $session = $auth->validateSession($token);
        
        if (!$session['valid']) {
            http_response_code(401);
            jsonResponse(['error' => 'Invalid or expired session']);
        }
        
        return $session['user'];
    } catch (Exception $e) {
        error_log("Auth middleware error: " . $e->getMessage());
        http_response_code(500);
        jsonResponse(['error' => 'Authentication error']);
    }
}

// Helper function for cross-platform header retrieval
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
