<?php
/**
 * QR Code Verification Page
 * GatePass Pro - Smart Gate Management System
 */

require_once 'config/database.php';
require_once 'classes/QRCodeGenerator.php';
require_once 'classes/VisitManager.php';

$qrCode = $_GET['code'] ?? '';
$result = null;
$error = null;

if (!empty($qrCode)) {
    try {
        $qrGenerator = new QRCodeGenerator();
        $result = $qrGenerator->verifyQRCode($qrCode);
    } catch (Exception $e) {
        $error = 'Verification failed. Please try again.';
        error_log("QR verification error: " . $e->getMessage());
    }
}

// Get system settings
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('site_name', 'company_name', 'primary_color')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $settings = [
        'site_name' => 'GatePass Pro',
        'company_name' => 'Your Company',
        'primary_color' => '#3B82F6'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Verification - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $settings['primary_color']; ?>;
        }
        .btn-primary {
            background-color: var(--primary-color);
        }
        .text-primary {
            color: var(--primary-color);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-lg mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="<?php echo ($result && $result['success']) ? 'bg-green-100' : 'bg-red-100'; ?> w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="<?php echo ($result && $result['success']) ? 'ri-check-circle-line text-green-600' : 'ri-error-warning-line text-red-600'; ?> text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">QR Code Verification</h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($settings['company_name']); ?></p>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <?php if (empty($qrCode)): ?>
                <!-- No QR Code -->
                <div class="text-center">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Invalid Request</h2>
                    <p class="text-gray-600 mb-6">No QR code provided for verification.</p>
                    <a href="index.php" class="btn-primary text-white px-6 py-2 rounded-lg hover:opacity-90 transition duration-200">
                        Go to Login
                    </a>
                </div>

                <?php elseif ($error): ?>
                <!-- Error -->
                <div class="text-center">
                    <h2 class="text-xl font-semibold text-red-600 mb-4">Verification Failed</h2>
                    <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($error); ?></p>
                    <a href="index.php" class="btn-primary text-white px-6 py-2 rounded-lg hover:opacity-90 transition duration-200">
                        Go to Login
                    </a>
                </div>

                <?php elseif ($result && $result['success']): ?>
                <!-- Valid QR Code -->
                <div class="text-center">
                    <h2 class="text-xl font-semibold text-green-600 mb-4">Valid QR Code</h2>
                    
                    <?php if (isset($result['data'])): ?>
                    <div class="text-left bg-green-50 p-4 rounded-lg mb-6">
                        <h3 class="font-semibold mb-3">Visit Details:</h3>
                        <?php $data = $result['data']; ?>
                        
                        <?php if (isset($data['visitor_name'])): ?>
                        <p><strong>Visitor:</strong> <?php echo htmlspecialchars($data['visitor_name']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($data['visitor_company'])): ?>
                        <p><strong>Company:</strong> <?php echo htmlspecialchars($data['visitor_company']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($data['host_name'])): ?>
                        <p><strong>Host:</strong> <?php echo htmlspecialchars($data['host_name']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($data['visit_code'])): ?>
                        <p><strong>Visit Code:</strong> <?php echo htmlspecialchars($data['visit_code']); ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($data['status'])): ?>
                        <p><strong>Status:</strong> 
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                <?php echo $data['status'] === 'Checked In' ? 'bg-green-100 text-green-800' : 
                                          ($data['status'] === 'Scheduled' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                <?php echo htmlspecialchars($data['status']); ?>
                            </span>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p class="text-gray-600 mb-6">This QR code is valid and can be used for check-in/out operations.</p>
                    <a href="index.php" class="btn-primary text-white px-6 py-2 rounded-lg hover:opacity-90 transition duration-200">
                        Go to Dashboard
                    </a>
                </div>

                <?php else: ?>
                <!-- Invalid QR Code -->
                <div class="text-center">
                    <h2 class="text-xl font-semibold text-red-600 mb-4">Invalid QR Code</h2>
                    <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($result['message'] ?? 'This QR code is not valid or has expired.'); ?></p>
                    <a href="index.php" class="btn-primary text-white px-6 py-2 rounded-lg hover:opacity-90 transition duration-200">
                        Go to Login
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Additional Info -->
            <div class="text-center mt-6 text-sm text-gray-500">
                <p>For assistance, please contact security or reception.</p>
            </div>
        </div>
    </div>
</body>
</html>