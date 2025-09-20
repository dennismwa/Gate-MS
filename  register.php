<?php
/**
 * Visitor Self-Registration Page
 * GatePass Pro - Smart Gate Management System
 */

require_once 'config/database.php';
require_once 'classes/VisitorManager.php';
require_once 'classes/VisitManager.php';
require_once 'classes/NotificationManager.php';

$errors = [];
$success = false;
$registrationCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $visitManager = new VisitManager();
        
        $data = [
            'visitor_name' => $_POST['visitor_name'] ?? '',
            'visitor_email' => $_POST['visitor_email'] ?? '',
            'visitor_phone' => $_POST['visitor_phone'] ?? '',
            'visitor_company' => $_POST['visitor_company'] ?? '',
            'host_name' => $_POST['host_name'] ?? '',
            'host_department' => $_POST['host_department'] ?? '',
            'host_email' => $_POST['host_email'] ?? '',
            'visit_date' => $_POST['visit_date'] ?? '',
            'visit_time' => $_POST['visit_time'] ?? '',
            'duration_hours' => $_POST['duration_hours'] ?? 2,
            'purpose' => $_POST['purpose'] ?? '',
            'vehicle_plate' => $_POST['vehicle_plate'] ?? '',
            'special_requirements' => $_POST['special_requirements'] ?? ''
        ];
        
        // Validate required fields
        if (empty($data['visitor_name'])) {
            $errors[] = 'Visitor name is required';
        }
        if (empty($data['visitor_phone'])) {
            $errors[] = 'Phone number is required';
        }
        if (empty($data['host_name'])) {
            $errors[] = 'Host name is required';
        }
        if (empty($data['visit_date'])) {
            $errors[] = 'Visit date is required';
        }
        if (empty($data['visit_time'])) {
            $errors[] = 'Visit time is required';
        }
        if (empty($data['purpose'])) {
            $errors[] = 'Purpose of visit is required';
        }
        
        // Validate email if provided
        if (!empty($data['visitor_email']) && !filter_var($data['visitor_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // Validate phone number
        if (!preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $data['visitor_phone'])) {
            $errors[] = 'Please enter a valid phone number';
        }
        
        // Validate date (must be today or future)
        if (strtotime($data['visit_date']) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Visit date cannot be in the past';
        }
        
        if (empty($errors)) {
            $result = $visitManager->createPreRegistration($data, null);
            
            if ($result['success']) {
                $success = true;
                $registrationCode = $result['registration_code'];
            } else {
                $errors[] = $result['message'] ?? 'Registration failed. Please try again.';
            }
        }
        
    } catch (Exception $e) {
        error_log("Visitor registration error: " . $e->getMessage());
        $errors[] = 'An error occurred. Please try again later.';
    }
}

// Get system settings for branding
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
    <title>Visitor Registration - <?php echo htmlspecialchars($settings['site_name']); ?></title>
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
        .border-primary {
            border-color: var(--primary-color);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="btn-primary w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4 text-white">
                    <i class="ri-user-add-line text-3xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Visitor Pre-Registration</h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($settings['company_name']); ?></p>
            </div>

            <?php if ($success): ?>
            <!-- Success Message -->
            <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="ri-check-circle-line text-4xl text-green-600"></i>
                </div>
                
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Registration Successful!</h2>
                <p class="text-gray-600 mb-6">Your visit has been pre-registered. Please save your registration code below:</p>
                
                <div class="bg-gray-100 p-6 rounded-lg mb-6">
                    <p class="text-sm text-gray-600 mb-2">Registration Code</p>
                    <p class="text-3xl font-bold text-primary"><?php echo htmlspecialchars($registrationCode); ?></p>
                </div>
                
                <div class="text-left bg-blue-50 p-6 rounded-lg mb-6">
                    <h3 class="font-semibold mb-3 text-blue-900">What's Next?</h3>
                    <ul class="space-y-2 text-blue-800">
                        <li class="flex items-center">
                            <i class="ri-arrow-right-circle-line mr-2"></i>
                            Your registration is pending approval
                        </li>
                        <li class="flex items-center">
                            <i class="ri-arrow-right-circle-line mr-2"></i>
                            You'll receive an email notification once approved
                        </li>
                        <li class="flex items-center">
                            <i class="ri-arrow-right-circle-line mr-2"></i>
                            Bring your registration code when you visit
                        </li>
                        <li class="flex items-center">
                            <i class="ri-arrow-right-circle-line mr-2"></i>
                            Have a valid ID ready for verification
                        </li>
                    </ul>
                </div>
                
                <div class="space-y-4">
                    <button onclick="window.print()" class="w-full bg-gray-600 text-white py-3 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="ri-printer-line mr-2"></i>
                        Print This Page
                    </button>
                    <a href="register.php" class="w-full btn-primary text-white py-3 rounded-lg hover:opacity-90 transition duration-200 inline-block text-center">
                        Register Another Visit
                    </a>
                </div>
            </div>

            <?php else: ?>
            <!-- Registration Form -->
            <div class="bg-white rounded-xl shadow-lg p-8">
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

                <form method="POST" class="space-y-6">
                    <!-- Visitor Information -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Visitor Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                                <input type="text" name="visitor_name" value="<?php echo htmlspecialchars($_POST['visitor_name'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                                <input type="tel" name="visitor_phone" value="<?php echo htmlspecialchars($_POST['visitor_phone'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" name="visitor_email" value="<?php echo htmlspecialchars($_POST['visitor_email'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Company/Organization</label>
                                <input type="text" name="visitor_company" value="<?php echo htmlspecialchars($_POST['visitor_company'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Host Information -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Host Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Host Name *</label>
                                <input type="text" name="host_name" value="<?php echo htmlspecialchars($_POST['host_name'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                <input type="text" name="host_department" value="<?php echo htmlspecialchars($_POST['host_department'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Host Email</label>
                            <input type="email" name="host_email" value="<?php echo htmlspecialchars($_POST['host_email'] ?? ''); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Visit Details -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Visit Details</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Visit Date *</label>
                                <input type="date" name="visit_date" value="<?php echo htmlspecialchars($_POST['visit_date'] ?? date('Y-m-d')); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Visit Time *</label>
                                <input type="time" name="visit_time" value="<?php echo htmlspecialchars($_POST['visit_time'] ?? ''); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Duration (hours)</label>
                                <select name="duration_hours" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="1" <?php echo ($_POST['duration_hours'] ?? '') == '1' ? 'selected' : ''; ?>>1 hour</option>
                                    <option value="2" <?php echo ($_POST['duration_hours'] ?? '2') == '2' ? 'selected' : ''; ?>>2 hours</option>
                                    <option value="3" <?php echo ($_POST['duration_hours'] ?? '') == '3' ? 'selected' : ''; ?>>3 hours</option>
                                    <option value="4" <?php echo ($_POST['duration_hours'] ?? '') == '4' ? 'selected' : ''; ?>>4 hours</option>
                                    <option value="8" <?php echo ($_POST['duration_hours'] ?? '') == '8' ? 'selected' : ''; ?>>Full day</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Purpose of Visit *</label>
                            <textarea name="purpose" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                      required><?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Vehicle Information -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Vehicle Information (Optional)</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Vehicle Plate Number</label>
                            <input type="text" name="vehicle_plate" value="<?php echo htmlspecialchars($_POST['vehicle_plate'] ?? ''); ?>" 
                                   placeholder="e.g., ABC-123" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Special Requirements -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Special Requirements</h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Additional Information</label>
                            <textarea name="special_requirements" rows="3" 
                                      placeholder="Any special requirements, accessibility needs, or additional information..." 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($_POST['special_requirements'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <div class="flex items-start">
                            <input type="checkbox" id="terms" name="terms" class="mt-1 mr-3" required>
                            <label for="terms" class="text-sm text-gray-700">
                                I agree to the terms and conditions of visit. I understand that:
                                <ul class="mt-2 space-y-1 text-xs">
                                    <li>• I must carry a valid ID for verification</li>
                                    <li>• This registration is subject to approval</li>
                                    <li>• I will follow all security protocols</li>
                                    <li>• My visit details may be recorded for security purposes</li>
                                </ul>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-6">
                        <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg hover:opacity-90 transition duration-200 text-lg font-medium">
                            <i class="ri-send-plane-line mr-2"></i>
                            Submit Registration
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="text-center mt-8 text-sm text-gray-500">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['company_name']); ?>. All rights reserved.</p>
                <p class="mt-2">
                    <a href="index.php" class="text-primary hover:underline">Staff Login</a> | 
                    <a href="mailto:info@company.com" class="text-primary hover:underline">Contact Support</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-format phone number
        document.querySelector('input[name="visitor_phone"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.substring(0, 10);
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            }
            e.target.value = value;
        });

        // Auto-format vehicle plate
        document.querySelector('input[name="vehicle_plate"]').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });

        // Set minimum time to current time if date is today
        document.querySelector('input[name="visit_date"]').addEventListener('change', function(e) {
            const timeInput = document.querySelector('input[name="visit_time"]');
            const selectedDate = new Date(e.target.value);
            const today = new Date();
            
            if (selectedDate.toDateString() === today.toDateString()) {
                const currentTime = today.getHours().toString().padStart(2, '0') + ':' + 
                                  today.getMinutes().toString().padStart(2, '0');
                timeInput.min = currentTime;
            } else {
                timeInput.removeAttribute('min');
            }
        });

        // Auto-focus next field on Enter
        document.querySelectorAll('input, select, textarea').forEach(function(element, index, elements) {
            element.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.type !== 'textarea') {
                    e.preventDefault();
                    const nextElement = elements[index + 1];
                    if (nextElement) {
                        nextElement.focus();
                    }
                }
            });
        });
    </script>
</body>
</html>