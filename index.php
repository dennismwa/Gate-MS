<?php
/**
 * Main Application Entry Point
 * GatePass Pro - Smart Gate Management System
 */

// Start session
session_start();

// Include configuration and classes
require_once 'config/database.php';
require_once 'auth.php';

// Set error reporting based on environment
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set CORS headers for API requests
setCorsHeaders();

// Check if this is an API request
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, '/api/') !== false) {
    // Redirect to API handler
    include 'api/index.php';
    exit;
}

// Check for installation
if (!file_exists('config/installed.lock')) {
    // Redirect to installation if not installed
    header('Location: install.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GatePass Pro - Smart Gate Management System</title>
    <meta name="description" content="Professional gate management system with QR code scanning, visitor tracking, and comprehensive reporting.">
    <meta name="keywords" content="gate management, visitor management, QR code, security, access control">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    
    <!-- CSS Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    
    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.3/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qr-scanner/1.4.2/qr-scanner.umd.min.js"></script>
    
    <!-- Custom Styles -->
    <style>
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
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .mobile-hide {
                display: none;
            }
            
            .mobile-full {
                width: 100% !important;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .auto-dark {
                background-color: #1f2937;
                color: #f9fafb;
            }
        }
        
        /* Focus styles for accessibility */
        .focus-ring:focus {
            outline: 2px solid #3B82F6;
            outline-offset: 2px;
        }
        
        /* Status indicators */
        .status-online {
            background-color: #10B981;
        }
        
        .status-offline {
            background-color: #EF4444;
        }
        
        .status-pending {
            background-color: #F59E0B;
        }
        
        /* Custom button styles */
        .btn-primary {
            background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%);
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
        
        /* Camera preview styles */
        .camera-preview {
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }
        
        .camera-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 2px solid #3B82F6;
            width: 200px;
            height: 200px;
            border-radius: 8px;
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
        
        /* Badge styles */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .badge-success {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .badge-warning {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        .badge-error {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        
        .badge-info {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Page content will be loaded here by JavaScript -->
    <div id="app-root"></div>
    
    <!-- Service Worker Registration for PWA functionality -->
    <script>
        // Register service worker for offline functionality
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed');
                    });
            });
        }
    </script>
    
    <!-- Load the main application -->
    <script>
        // Load the main application HTML content
        fetch('gatepass_system.html')
            .then(response => response.text())
            .then(html => {
                // Extract the body content from the HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const appContent = doc.getElementById('app');
                
                if (appContent) {
                    document.getElementById('app-root').innerHTML = appContent.innerHTML;
                    
                    // Initialize the application
                    if (typeof initializeApp === 'function') {
                        initializeApp();
                    }
                } else {
                    // Fallback: load inline content
                    loadInlineApp();
                }
            })
            .catch(error => {
                console.error('Error loading application:', error);
                loadInlineApp();
            });
        
        function loadInlineApp() {
            // Fallback inline application loader
            document.getElementById('app-root').innerHTML = `
                <div class="min-h-screen flex items-center justify-center bg-blue-600">
                    <div class="text-center text-white">
                        <div class="spinner rounded-full h-16 w-16 border-4 border-white border-t-transparent mx-auto mb-4"></div>
                        <h2 class="text-2xl font-bold mb-2">GatePass Pro</h2>
                        <p class="text-blue-200">Loading application...</p>
                        <div class="mt-4">
                            <button onclick="window.location.reload()" class="bg-white text-blue-600 px-4 py-2 rounded hover:bg-gray-100 transition duration-200">
                                Reload
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }
    </script>
    
    <!-- Global JavaScript Configuration -->
    <script>
        // Global application configuration
        window.APP_CONFIG = {
            apiUrl: '<?php echo SITE_URL; ?>/api/',
            siteUrl: '<?php echo SITE_URL; ?>',
            siteName: '<?php echo SITE_NAME; ?>',
            uploadPath: '<?php echo UPLOAD_PATH; ?>',
            qrCodePath: '<?php echo QR_CODE_PATH; ?>',
            maxFileSize: <?php echo MAX_FILE_SIZE; ?>,
            allowedImageTypes: <?php echo json_encode(ALLOWED_IMAGE_TYPES); ?>,
            sessionLifetime: <?php echo SESSION_LIFETIME; ?>,
            currentUser: null,
            permissions: [],
            settings: {}
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
        
        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
            // You can send errors to a logging service here
        });
        
        // Global unhandled promise rejection handler
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
            e.preventDefault();
        });
    </script>
    
    <!-- Analytics (Optional) -->
    <script>
        // Add your analytics code here if needed
        // Example: Google Analytics, Mixpanel, etc.
    </script>
</body>
</html>