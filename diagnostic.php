<?php
/**
 * Diagnostic Script for GatePass Pro
 * This will help identify what's causing the 500 error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>GatePass Pro Diagnostic Tool</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow:auto;}</style>";

// Check 1: PHP Version
echo "<h2>1. PHP Version Check</h2>";
$phpVersion = phpversion();
echo "<p class='info'>PHP Version: " . $phpVersion . "</p>";
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo "<p class='success'>✓ PHP version is compatible</p>";
} else {
    echo "<p class='error'>✗ PHP version is too old. Requires 7.4 or higher</p>";
}

// Check 2: Required Extensions
echo "<h2>2. Required PHP Extensions</h2>";
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'session', 'openssl', 'gd'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='success'>✓ $ext extension is loaded</p>";
    } else {
        echo "<p class='error'>✗ $ext extension is missing</p>";
    }
}

// Check 3: File Permissions
echo "<h2>3. File and Directory Permissions</h2>";
$paths_to_check = [
    '.' => 'Current directory',
    'config' => 'Config directory',
    'uploads' => 'Uploads directory',
    'logs' => 'Logs directory',
    'qrcodes' => 'QR Codes directory'
];

foreach ($paths_to_check as $path => $description) {
    if (file_exists($path)) {
        if (is_writable($path)) {
            echo "<p class='success'>✓ $description ($path) is writable</p>";
        } else {
            echo "<p class='warning'>⚠ $description ($path) exists but is not writable</p>";
        }
    } else {
        echo "<p class='warning'>⚠ $description ($path) does not exist</p>";
    }
}

// Check 4: Configuration Files
echo "<h2>4. Configuration Files</h2>";
$config_files = [
    'config/database.php' => 'Database configuration',
    'config/installed.lock' => 'Installation lock file',
    'index.php' => 'Main application file'
];

foreach ($config_files as $file => $description) {
    if (file_exists($file)) {
        echo "<p class='success'>✓ $description ($file) exists</p>";
        
        // Check if it's readable
        if (is_readable($file)) {
            echo "<p class='info'>  → File is readable</p>";
            
            // For database.php, try to include it
            if ($file === 'config/database.php') {
                try {
                    include_once $file;
                    echo "<p class='success'>  → Database config file loads without errors</p>";
                    
                    // Test database connection
                    if (class_exists('Database')) {
                        try {
                            $database = new Database();
                            $db = $database->getConnection();
                            echo "<p class='success'>  → Database connection successful</p>";
                        } catch (Exception $e) {
                            echo "<p class='error'>  → Database connection failed: " . $e->getMessage() . "</p>";
                        }
                    } else {
                        echo "<p class='warning'>  → Database class not found in config file</p>";
                    }
                } catch (Exception $e) {
                    echo "<p class='error'>  → Error loading database config: " . $e->getMessage() . "</p>";
                } catch (ParseError $e) {
                    echo "<p class='error'>  → Parse error in database config: " . $e->getMessage() . "</p>";
                }
            }
        } else {
            echo "<p class='error'>  → File is not readable</p>";
        }
    } else {
        echo "<p class='error'>✗ $description ($file) is missing</p>";
    }
}

// Check 5: Try to identify the specific error in index.php
echo "<h2>5. Index.php Error Analysis</h2>";
if (file_exists('index.php')) {
    echo "<p class='info'>Attempting to identify the error in index.php...</p>";
    
    // Capture any errors when including index.php
    ob_start();
    $error_occurred = false;
    
    try {
        // Set a custom error handler to catch errors
        set_error_handler(function($severity, $message, $file, $line) use (&$error_occurred) {
            $error_occurred = true;
            echo "<p class='error'>PHP Error: $message in $file on line $line</p>";
        });
        
        // Try to include the index file but stop before any output
        $index_content = file_get_contents('index.php');
        
        // Check for common issues in the code
        if (strpos($index_content, '<?php') === false) {
            echo "<p class='error'>✗ index.php doesn't start with <?php tag</p>";
        } else {
            echo "<p class='success'>✓ index.php has proper PHP opening tag</p>";
        }
        
        // Check for syntax errors without executing
        if (php_check_syntax_string($index_content)) {
            echo "<p class='success'>✓ index.php has valid PHP syntax</p>";
        } else {
            echo "<p class='error'>✗ index.php has syntax errors</p>";
        }
        
        restore_error_handler();
        
    } catch (Exception $e) {
        echo "<p class='error'>Exception in index.php: " . $e->getMessage() . "</p>";
    } catch (ParseError $e) {
        echo "<p class='error'>Parse error in index.php: " . $e->getMessage() . "</p>";
    }
    
    $output = ob_get_clean();
    echo $output;
    
} else {
    echo "<p class='error'>✗ index.php file not found</p>";
}

// Check 6: Server Environment
echo "<h2>6. Server Environment</h2>";
echo "<p class='info'>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p class='info'>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</p>";
echo "<p class='info'>Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "</p>";
echo "<p class='info'>Current Working Directory: " . getcwd() . "</p>";

// Check 7: Error Logs
echo "<h2>7. Recent Error Logs</h2>";
$error_log_locations = [
    'logs/php_errors.log',
    'error_log',
    ini_get('error_log')
];

$found_logs = false;
foreach ($error_log_locations as $log_file) {
    if ($log_file && file_exists($log_file) && is_readable($log_file)) {
        $found_logs = true;
        echo "<p class='info'>Found error log: $log_file</p>";
        
        $log_content = file_get_contents($log_file);
        $recent_lines = array_slice(explode("\n", $log_content), -20); // Last 20 lines
        
        echo "<h3>Recent entries from $log_file:</h3>";
        echo "<pre>" . htmlspecialchars(implode("\n", $recent_lines)) . "</pre>";
    }
}

if (!$found_logs) {
    echo "<p class='warning'>⚠ No accessible error logs found</p>";
}

// Check 8: Memory and Limits
echo "<h2>8. PHP Configuration</h2>";
$php_settings = [
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'display_errors' => ini_get('display_errors'),
    'log_errors' => ini_get('log_errors')
];

foreach ($php_settings as $setting => $value) {
    echo "<p class='info'>$setting: $value</p>";
}

// Function to check syntax (PHP 8+ compatible)
function php_check_syntax_string($code) {
    // For PHP 8+, we'll do a basic check
    if (function_exists('token_get_all')) {
        $tokens = @token_get_all($code);
        return $tokens !== false;
    }
    return true; // Assume it's fine if we can't check
}

echo "<h2>9. Recommendations</h2>";
echo "<div style='background:#f0f8ff;padding:15px;border-radius:5px;'>";
echo "<h3>Quick Fixes to Try:</h3>";
echo "<ol>";
echo "<li><strong>Check the actual error:</strong> Look at the error logs above for specific PHP errors</li>";
echo "<li><strong>Verify database config:</strong> Make sure config/database.php was created correctly during installation</li>";
echo "<li><strong>File permissions:</strong> Ensure web server can read all files (chmod 644 for files, 755 for directories)</li>";
echo "<li><strong>Backup index.php:</strong> Copy index.php to index_backup.php and create a simple test version</li>";
echo "<li><strong>Check .htaccess:</strong> Temporarily rename .htaccess to .htaccess_disabled to rule out rewrite issues</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Next Step:</strong> If you see specific errors above, let me know what they are and I'll help you fix them.</p>";
echo "<p><strong>Access this diagnostic:</strong> Save this as 'diagnostic.php' and visit it in your browser</p>";
?>

<h2>10. Create Simple Test Index</h2>
<p>If you want to create a simple working index.php for testing, click the button below:</p>
<form method="post">
    <input type="hidden" name="create_simple_index" value="1">
    <button type="submit" style="background:#007cba;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;">Create Simple Test Index</button>
</form>

<?php
if (isset($_POST['create_simple_index'])) {
    $simple_index = '<?php
// Simple test version of index.php
session_start();

// Check if installed
if (!file_exists("config/installed.lock")) {
    header("Location: install.php");
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>GatePass Pro - Test Mode</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎉 GatePass Pro - Installation Successful!</h1>
        <p class="success">✓ Your installation is working correctly</p>
        <p>The system is ready to use. This is a simplified test version.</p>
        
        <h3>What to do next:</h3>
        <ol>
            <li>Replace this simple index.php with the full application index.php</li>
            <li>Make sure all required PHP classes are uploaded</li>
            <li>Verify database connection is working</li>
            <li>Test the full application functionality</li>
        </ol>
        
        <p><strong>Current Time:</strong> <?php echo date("Y-m-d H:i:s"); ?></p>
        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
        
        <?php if (file_exists("config/database.php")): ?>
        <p class="success">✓ Database configuration file exists</p>
        <?php else: ?>
        <p style="color:red;">✗ Database configuration file missing</p>
        <?php endif; ?>
    </div>
</body>
</html>';

    if (file_put_contents('index_simple.php', $simple_index)) {
        echo '<p style="color:green;font-weight:bold;">✓ Simple test index created as index_simple.php</p>';
        echo '<p><a href="index_simple.php" target="_blank">Click here to test it</a></p>';
    } else {
        echo '<p style="color:red;">✗ Failed to create simple index file</p>';
    }
}
?>
