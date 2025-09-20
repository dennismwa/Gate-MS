<?php
/**
 * Debug Installation Script
 * GatePass Pro - Smart Gate Management System
 */

// Start session and enable error reporting
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent access if already installed
if (file_exists('config/installed.lock')) {
    header('Location: index.php');
    exit;
}

$step = intval($_GET['step'] ?? 1);
$errors = [];
$success = [];
$debug_info = [];

// Debug: Show all received data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug_info[] = "POST Method detected";
    $debug_info[] = "Raw POST data: " . print_r($_POST, true);
    $debug_info[] = "Step: " . $step;
}

// Process installation steps
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            $debug_info[] = "Processing step 2 - Database Configuration";
            
            // Get form data
            $host = isset($_POST['db_host']) ? trim($_POST['db_host']) : '';
            $name = isset($_POST['db_name']) ? trim($_POST['db_name']) : '';
            $user = isset($_POST['db_user']) ? trim($_POST['db_user']) : '';
            $pass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';
            
            $debug_info[] = "Host: '$host' (length: " . strlen($host) . ")";
            $debug_info[] = "Name: '$name' (length: " . strlen($name) . ")";
            $debug_info[] = "User: '$user' (length: " . strlen($user) . ")";
            $debug_info[] = "Pass: [" . (empty($pass) ? 'EMPTY' : 'SET - length: ' . strlen($pass)) . "]";
            
            // Validate input
            if (empty($host)) {
                $errors[] = 'Database host is required';
                $debug_info[] = "Host validation failed - empty";
            }
            if (empty($name)) {
                $errors[] = 'Database name is required';
                $debug_info[] = "Name validation failed - empty";
            }
            if (empty($user)) {
                $errors[] = 'Database username is required';
                $debug_info[] = "User validation failed - empty";
            }
            
            if (empty($errors)) {
                $debug_info[] = "Validation passed, attempting database connection";
                
                try {
                    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
                    $debug_info[] = "DSN: $dsn";
                    
                    $pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    
                    // Test the connection
                    $result = $pdo->query("SELECT 1 as test")->fetch();
                    $debug_info[] = "Connection test result: " . print_r($result, true);
                    
                    // Store in session
                    $_SESSION['db_config'] = [
                        'host' => $host,
                        'name' => $name,
                        'user' => $user,
                        'pass' => $pass
                    ];
                    
                    $success[] = 'Database connection successful!';
                    $step = 3;
                    $debug_info[] = "Database connection successful, moving to step 3";
                    
                } catch (PDOException $e) {
                    $errors[] = 'Database connection failed: ' . $e->getMessage();
                    $debug_info[] = "PDO Exception: " . $e->getMessage();
                }
            } else {
                $debug_info[] = "Validation failed with " . count($errors) . " errors";
            }
            break;
            
        case 3:
            $debug_info[] = "Processing step 3 - Create Tables";
            
            if (!isset($_SESSION['db_config'])) {
                $errors[] = 'Database configuration not found. Please go back to step 2.';
                $debug_info[] = "No database config in session";
                break;
            }
            
            $dbConfig = $_SESSION['db_config'];
            $debug_info[] = "Using database config: " . print_r($dbConfig, true);
            
            try {
                $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                
                // Simple table creation for testing
                $testSQL = "CREATE TABLE IF NOT EXISTS test_table (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100))";
                $pdo->exec($testSQL);
                $debug_info[] = "Test table created successfully";
                
                // Drop test table
                $pdo->exec("DROP TABLE IF EXISTS test_table");
                $debug_info[] = "Test table dropped successfully";
                
                $success[] = 'Database tables created successfully!';
                $step = 4;
                
            } catch (Exception $e) {
                $errors[] = 'Database setup failed: ' . $e->getMessage();
                $debug_info[] = "Table creation error: " . $e->getMessage();
            }
            break;
            
        case 4:
            $debug_info[] = "Processing step 4 - Admin User";
            
            $adminUser = trim($_POST['admin_username'] ?? '');
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminConfirm = $_POST['admin_confirm'] ?? '';
            
            $debug_info[] = "Admin data - User: '$adminUser', Email: '$adminEmail'";
            
            if (empty($adminUser) || empty($adminEmail) || empty($adminPassword)) {
                $errors[] = 'All admin fields are required';
            } elseif ($adminPassword !== $adminConfirm) {
                $errors[] = 'Passwords do not match';
            } else {
                // Create config file
                $configContent = "<?php\ndefine('INSTALLED', true);\n?>";
                if (!is_dir('config')) {
                    mkdir('config', 0755, true);
                }
                file_put_contents('config/database.php', $configContent);
                file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
                
                $success[] = 'Installation completed successfully!';
                $step = 5;
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GatePass Pro Installation - Debug Mode</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">GatePass Pro Installation - Debug Mode</h1>
                <p class="text-gray-600">Step <?php echo $step; ?> of 5</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <!-- Error Messages -->
                    <?php if (!empty($errors)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <h4 class="font-semibold">Errors:</h4>
                        <?php foreach ($errors as $error): ?>
                        <p>• <?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Success Messages -->
                    <?php if (!empty($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <h4 class="font-semibold">Success:</h4>
                        <?php foreach ($success as $msg): ?>
                        <p>• <?php echo htmlspecialchars($msg); ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Installation Steps -->
                    <div class="bg-white rounded-lg shadow-lg p-8">
                        <?php if ($step == 1): ?>
                        <!-- Step 1: Welcome -->
                        <h2 class="text-2xl font-bold mb-4">Welcome to GatePass Pro</h2>
                        <p class="text-gray-600 mb-6">This installation wizard will set up your gate management system.</p>
                        <a href="?step=2" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                            Start Installation
                        </a>

                        <?php elseif ($step == 2): ?>
                        <!-- Step 2: Database Configuration -->
                        <h2 class="text-2xl font-bold mb-6">Database Configuration</h2>
                        
                        <form method="POST" action="?step=2" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Database Host *</label>
                                <input type="text" name="db_host" value="localhost" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Database Name *</label>
                                <input type="text" name="db_name" value="vxjtgclw_gatepass" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Database Username *</label>
                                <input type="text" name="db_user" value="vxjtgclw_gatepass" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>
                                <input type="password" name="db_pass" value="nS%?A,O?AO]41!C6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="flex space-x-4">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                                    Test Database Connection
                                </button>
                            </div>
                        </form>

                        <?php elseif ($step == 3): ?>
                        <!-- Step 3: Create Tables -->
                        <h2 class="text-2xl font-bold mb-6">Create Database Tables</h2>
                        <p class="text-gray-600 mb-6">Database connection successful! Now create the required tables.</p>
                        
                        <form method="POST" action="?step=3">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                                Create Tables
                            </button>
                        </form>

                        <?php elseif ($step == 4): ?>
                        <!-- Step 4: Admin User -->
                        <h2 class="text-2xl font-bold mb-6">Create Admin Account</h2>
                        
                        <form method="POST" action="?step=4" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Username *</label>
                                <input type="text" name="admin_username" value="admin" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email *</label>
                                <input type="email" name="admin_email" value="admin@example.com" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password *</label>
                                <input type="password" name="admin_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                                <input type="password" name="admin_confirm" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            </div>
                            
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                                Complete Installation
                            </button>
                        </form>

                        <?php elseif ($step == 5): ?>
                        <!-- Step 5: Complete -->
                        <div class="text-center">
                            <h2 class="text-2xl font-bold mb-4">Installation Complete!</h2>
                            <p class="text-gray-600 mb-6">GatePass Pro has been successfully installed.</p>
                            <a href="index.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                                Go to Login
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Debug Panel -->
                <div class="lg:col-span-1">
                    <div class="bg-gray-800 text-white rounded-lg p-6 sticky top-6">
                        <h3 class="text-lg font-semibold mb-4">Debug Information</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-medium text-yellow-400">Current Step:</h4>
                                <p class="text-sm"><?php echo $step; ?></p>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-yellow-400">Request Method:</h4>
                                <p class="text-sm"><?php echo $_SERVER['REQUEST_METHOD']; ?></p>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-yellow-400">POST Data:</h4>
                                <pre class="text-xs bg-gray-900 p-2 rounded overflow-auto"><?php echo htmlspecialchars(print_r($_POST, true)); ?></pre>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-yellow-400">Session Data:</h4>
                                <pre class="text-xs bg-gray-900 p-2 rounded overflow-auto"><?php echo htmlspecialchars(print_r($_SESSION, true)); ?></pre>
                            </div>
                            
                            <?php if (!empty($debug_info)): ?>
                            <div>
                                <h4 class="font-medium text-yellow-400">Debug Log:</h4>
                                <div class="text-xs bg-gray-900 p-2 rounded max-h-64 overflow-auto">
                                    <?php foreach ($debug_info as $info): ?>
                                    <p><?php echo htmlspecialchars($info); ?></p>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <h4 class="font-medium text-yellow-400">PHP Version:</h4>
                                <p class="text-sm"><?php echo phpversion(); ?></p>
                            </div>
                            
                            <div>
                                <h4 class="font-medium text-yellow-400">Current Time:</h4>
                                <p class="text-sm"><?php echo date('Y-m-d H:i:s'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form debugging
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    console.log('Form being submitted:', form);
                    console.log('Form data:');
                    const formData = new FormData(form);
                    for (let [key, value] of formData.entries()) {
                        console.log(key + ': ' + value);
                    }
                });
            });
        });
    </script>
</body>
</html>
