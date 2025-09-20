<?php
/**
 * Main Application Entry Point - FIXED VERSION
 * GatePass Pro - Smart Gate Management System
 */

// Start session
session_start();

// Check for installation
if (!file_exists('config/installed.lock')) {
    // Redirect to installation if not installed
    header('Location: install.php');
    exit;
}

// Include configuration and dependencies
require_once 'config/database.php';
require_once 'auth.php';

// Set error reporting based on environment
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for debugging
ini_set('log_errors', 1);
ini_set('error_log', 'logs/php_errors.log');

// Create logs directory if it doesn't exist
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

// Set CORS headers for API requests
function setCorsHeaders() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// JSON response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

setCorsHeaders();

// Check if this is an API request
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/') {
    $requestUri = substr($requestUri, strlen($basePath));
}

if (strpos($requestUri, '/api/') === 0) {
    // Redirect to API handler
    include 'api/index.php';
    exit;
}

// Get system settings for the application
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT setting_key, setting_value FROM system_settings";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    $settings = [
        'site_name' => 'GatePass Pro',
        'company_name' => 'Your Company',
        'primary_color' => '#3B82F6'
    ];
}

// Define constants
define('SITE_NAME', $settings['site_name'] ?? 'GatePass Pro');
define('SITE_URL', 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $basePath);
define('UPLOAD_PATH', 'uploads/');
define('QR_CODE_PATH', 'qrcodes/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(SITE_NAME); ?> - Smart Gate Management System</title>
    <meta name="description" content="Professional gate management system with QR code scanning, visitor tracking, and comprehensive reporting.">
    <meta name="keywords" content="gate management, visitor management, QR code, security, access control">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <link rel="manifest" href="manifest.json">
    
    <!-- CSS Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.3/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color'] ?? '#3B82F6'; ?>;
            --secondary-color: <?php echo $settings['secondary_color'] ?? '#1F2937'; ?>;
            --accent-color: <?php echo $settings['accent_color'] ?? '#10B981'; ?>;
        }
        
        /* Loading animations */
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Scanner animations */
        .scanner-line {
            animation: scanner 2s ease-in-out infinite;
        }
        
        @keyframes scanner {
            0%, 100% { transform: translateY(0); opacity: 1; }
            50% { transform: translateY(20px); opacity: 0.5; }
        }
        
        /* Fade in animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Slide up animations */
        .slide-up {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            body {
                background: white !important;
            }
        }
        
        /* Custom button styles */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1D4ED8 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }
        
        /* Card hover effects */
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* Notification styles */
        .notification-enter {
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Offline indicator */
        .offline-indicator {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #f59e0b;
            color: white;
            text-align: center;
            padding: 8px;
            z-index: 9999;
            display: none;
        }
        
        body.offline-mode .offline-indicator {
            display: block;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .mobile-hide {
                display: none;
            }
            
            .mobile-full {
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Offline Indicator -->
    <div class="offline-indicator">
        <i class="ri-wifi-off-line"></i>
        You are offline. Some features may be limited.
    </div>
    
    <!-- Main Application Container -->
    <div id="app" class="min-h-screen">
        <!-- Loading Screen -->
        <div id="loadingScreen" class="fixed inset-0 bg-blue-600 flex items-center justify-center z-50">
            <div class="text-center text-white">
                <div class="spinner rounded-full h-16 w-16 border-4 border-white border-t-transparent mx-auto mb-4"></div>
                <h2 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars(SITE_NAME); ?></h2>
                <p class="text-blue-200">Initializing system...</p>
            </div>
        </div>

        <!-- Login Screen -->
        <div id="loginScreen" class="hidden fixed inset-0 bg-gradient-to-br from-blue-600 to-blue-800 flex items-center justify-center">
            <div class="bg-white rounded-xl p-8 w-full max-w-md mx-4 shadow-2xl">
                <div class="text-center mb-8">
                    <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-shield-check-line text-2xl text-blue-600"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars(SITE_NAME); ?></h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($settings['company_name'] ?? 'Smart Gate Management System'); ?></p>
                </div>
                
                <form id="loginForm" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Username or Email</label>
                        <div class="relative">
                            <i class="ri-user-line absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="loginUsername" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter username or email" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <i class="ri-lock-line absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="password" id="loginPassword" class="w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter password" required>
                            <button type="button" onclick="togglePassword('loginPassword')" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <label class="flex items-center">
                            <input type="checkbox" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Remember me</span>
                        </label>
                        <a href="register.php" class="text-sm text-blue-600 hover:text-blue-800">Pre-register visit</a>
                    </div>
                    
                    <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg hover:opacity-90 transition duration-200 font-medium">
                        <i class="ri-login-circle-line mr-2"></i>
                        Sign In
                    </button>
                </form>
                
                <div class="mt-6 text-center text-sm text-gray-500">
                    <p>Default credentials: admin / admin123</p>
                </div>
            </div>
        </div>

        <!-- Main Application (will be loaded dynamically) -->
        <div id="mainApp" class="hidden">
            <!-- Content will be loaded here by JavaScript -->
        </div>
    </div>
    
    <!-- Global JavaScript Configuration -->
    <script>
        // Global application configuration
        window.APP_CONFIG = {
            apiUrl: '<?php echo SITE_URL; ?>/api/',
            siteUrl: '<?php echo SITE_URL; ?>',
            siteName: '<?php echo htmlspecialchars(SITE_NAME); ?>',
            uploadPath: '<?php echo UPLOAD_PATH; ?>',
            qrCodePath: '<?php echo QR_CODE_PATH; ?>',
            maxFileSize: <?php echo MAX_FILE_SIZE; ?>,
            allowedImageTypes: <?php echo json_encode(ALLOWED_IMAGE_TYPES); ?>,
            currentUser: null,
            permissions: [],
            settings: <?php echo json_encode($settings); ?>
        };
        
        // Global utility functions
        window.utils = {
            formatDate: function(date, format = 'Y-m-d H:i:s') {
                if (!date) return '';
                const d = new Date(date);
                return d.toLocaleString();
            },
            
            formatFileSize: function(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            },
            
            generateId: function() {
                return Math.random().toString(36).substr(2, 9);
            },
            
            debounce: function(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            },
            
            validateEmail: function(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            },
            
            validatePhone: function(phone) {
                const re = /^[\+]?[0-9\s\-\(\)]{10,20}$/;
                return re.test(phone);
            }
        };
    </script>
    
    <!-- Load Application JavaScript -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/scanner.js"></script>
    <script src="assets/js/offline.js"></script>
    
    <!-- Service Worker Registration -->
    <script>
        // Register service worker for offline functionality
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed');
                    });
            });
        }
        
        // Network status monitoring
        window.addEventListener('online', function() {
            document.body.classList.remove('offline-mode');
            showNotification('Connection restored', 'success');
        });
        
        window.addEventListener('offline', function() {
            document.body.classList.add('offline-mode');
            showNotification('You are offline', 'warning');
        });
        
        // Global error handling
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
            e.preventDefault();
        });
        
        // Password toggle function
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'ri-eye-off-line';
            } else {
                input.type = 'password';
                icon.className = 'ri-eye-line';
            }
        }
        
        // Show notification function
        function showNotification(message, type = 'info') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 notification-enter`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
