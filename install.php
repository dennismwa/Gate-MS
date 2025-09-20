<?php
/**
 * Installation Script
 * GatePass Pro - Smart Gate Management System
 */

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
            
            try {
                $pdo = new PDO("mysql:host={$host};dbname={$name}", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Store database config in session
                session_start();
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
            session_start();
            if (!isset($_SESSION['db_config'])) {
                header('Location: install.php?step=1');
                exit;
            }
            
            $dbConfig = $_SESSION['db_config'];
            
            try {
                $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['name']}", 
                              $dbConfig['user'], $dbConfig['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Read and execute SQL file
                $sql = file_get_contents('gatepass_database.sql');
                $statements = explode(';', $sql);
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $pdo->exec($statement);
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
            session_start();
            $dbConfig = $_SESSION['db_config'];
            
            $adminUser = $_POST['admin_username'] ?? '';
            $adminEmail = $_POST['admin_email'] ?? '';
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminConfirm = $_POST['admin_confirm'] ?? '';
            $companyName = $_POST['company_name'] ?? '';
            
            if (empty($adminUser) || empty($adminEmail) || empty($adminPassword)) {
                $errors[] = 'All admin fields are required';
            } elseif ($adminPassword !== $adminConfirm) {
                $errors[] = 'Passwords do not match';
            } elseif (strlen($adminPassword) < 6) {
                $errors[] = 'Password must be at least 6 characters';
            } else {
                try {
                    $pdo = new PDO("mysql:host={$dbConfig['host']};dbname={$dbConfig['name']}", 
                                  $dbConfig['user'], $dbConfig['pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Update admin user
                    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ? WHERE id = 1");
                    $stmt->execute([$adminUser, $adminEmail, $hashedPassword, 'System Administrator']);
                    
                    // Update company settings
                    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'company_name'");
                    $stmt->execute([$companyName]);
                    
                    // Create config file
                    createConfigFile($dbConfig);
                    
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
                \"mysql:host=\" . \$this->host . \";dbname=\" . \$this->db_name,
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
define('SITE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['SCRIPT_NAME']));
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

date_default_timezone_set('Africa/Nairobi');
?>";

    if (!is_dir('config')) {
        mkdir('config', 0755, true);
    }
    
    file_put_contents('config/database.php', $configContent);
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
                        <input type="text" name="db_host" value="localhost" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Name</label>
                        <input type="text" name="db_name" value="vxjtgclw_gatepass" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Username</label>
                        <input type="text" name="db_user" value="vxjtgclw_gatepass" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>
                        <input type="password" name="db_pass" value="nS%?A,O?AO]41!C6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
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
                        <input type="text" name="company_name" value="Your Company" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Username</label>
                        <input type="text" name="admin_username" value="admin" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email</label>
                        <input type="email" name="admin_email" value="admin@example.com" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
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