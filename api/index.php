<?php
/**
 * API Router
 * GatePass Pro - Smart Gate Management System
 */

require_once '../config/database.php';
require_once '../auth.php';
require_once '../classes/VisitorManager.php';
require_once '../classes/VehicleManager.php';
require_once '../classes/VisitManager.php';
require_once '../classes/QRCodeGenerator.php';
require_once '../classes/NotificationManager.php';

setCorsHeaders();

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Parse JSON input for non-GET requests
$input = [];
if ($method !== 'GET') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $input = array_merge($input, $_POST);
}

try {
    switch ($endpoint) {
        
        // Authentication endpoints
        case 'login':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $auth = new Auth();
            $result = $auth->login($input['username'], $input['password']);
            jsonResponse($result);
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user = requireAuth();
            $auth = new Auth();
            $result = $auth->logout($user['id']);
            jsonResponse($result);
            break;
            
        case 'profile':
            $user = requireAuth();
            if ($method === 'GET') {
                jsonResponse(['user' => $user]);
            } elseif ($method === 'PUT') {
                // Update profile logic here
                jsonResponse(['success' => true, 'message' => 'Profile updated']);
            }
            break;
            
        case 'change-password':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user = requireAuth();
            $auth = new Auth();
            $result = $auth->changePassword(
                $user['id'],
                $input['current_password'],
                $input['new_password']
            );
            jsonResponse($result);
            break;
            
        // Visitor management endpoints
        case 'visitors':
            $user = requireAuth();
            $visitorManager = new VisitorManager();
            
            if ($method === 'GET') {
                $filters = [
                    'search' => $_GET['search'] ?? '',
                    'category' => $_GET['category'] ?? '',
                    'status' => $_GET['status'] ?? '',
                    'page' => $_GET['page'] ?? 1,
                    'limit' => $_GET['limit'] ?? 20
                ];
                
                $visitors = $visitorManager->getVisitors($filters);
                jsonResponse($visitors);
                
            } elseif ($method === 'POST') {
                $auth = new Auth();
                $auth->requirePermission($user, 'visitors');
                
                $result = $visitorManager->createVisitor($input);
                jsonResponse($result);
                
            } elseif ($method === 'PUT') {
                $auth = new Auth();
                $auth->requirePermission($user, 'visitors');
                
                $visitorId = $input['id'] ?? null;
                if (!$visitorId) {
                    throw new Exception('Visitor ID required');
                }
                
                $result = $visitorManager->updateVisitor($visitorId, $input);
                jsonResponse($result);
            }
            break;
            
        case 'visitor':
            $user = requireAuth();
            $visitorId = $_GET['id'] ?? null;
            
            if (!$visitorId) {
                throw new Exception('Visitor ID required');
            }
            
            $visitorManager = new VisitorManager();
            
            if ($method === 'GET') {
                $visitor = $visitorManager->getVisitor($visitorId);
                jsonResponse($visitor);
                
            } elseif ($method === 'DELETE') {
                $auth = new Auth();
                $auth->requirePermission($user, 'visitors');
                
                $result = $visitorManager->deleteVisitor($visitorId);
                jsonResponse($result);
            }
            break;
            
        // Vehicle management endpoints
        case 'vehicles':
            $user = requireAuth();
            $vehicleManager = new VehicleManager();
            
            if ($method === 'GET') {
                $filters = [
                    'search' => $_GET['search'] ?? '',
                    'type' => $_GET['type'] ?? '',
                    'page' => $_GET['page'] ?? 1,
                    'limit' => $_GET['limit'] ?? 20
                ];
                
                $vehicles = $vehicleManager->getVehicles($filters);
                jsonResponse($vehicles);
                
            } elseif ($method === 'POST') {
                $auth = new Auth();
                $auth->requirePermission($user, 'vehicles');
                
                $result = $vehicleManager->createVehicle($input);
                jsonResponse($result);
            }
            break;
            
        // Visit management endpoints
        case 'visits':
            $user = requireAuth();
            $visitManager = new VisitManager();
            
            if ($method === 'GET') {
                $filters = [
                    'date_from' => $_GET['date_from'] ?? '',
                    'date_to' => $_GET['date_to'] ?? '',
                    'status' => $_GET['status'] ?? '',
                    'visitor_id' => $_GET['visitor_id'] ?? '',
                    'page' => $_GET['page'] ?? 1,
                    'limit' => $_GET['limit'] ?? 20
                ];
                
                $visits = $visitManager->getVisits($filters);
                jsonResponse($visits);
                
            } elseif ($method === 'POST') {
                $auth = new Auth();
                $auth->requirePermission($user, 'checkin');
                
                $result = $visitManager->createVisit($input, $user['id']);
                jsonResponse($result);
            }
            break;
            
        case 'checkin':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user = requireAuth();
            $auth = new Auth();
            $auth->requirePermission($user, 'checkin');
            
            $visitManager = new VisitManager();
            $result = $visitManager->checkIn($input, $user['id']);
            jsonResponse($result);
            break;
            
        case 'checkout':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user = requireAuth();
            $auth = new Auth();
            $auth->requirePermission($user, 'checkout');
            
            $visitId = $input['visit_id'] ?? null;
            if (!$visitId) {
                throw new Exception('Visit ID required');
            }
            
            $visitManager = new VisitManager();
            $result = $visitManager->checkOut($visitId, $user['id'], $input);
            jsonResponse($result);
            break;
            
        // QR Code endpoints
        case 'qr-verify':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user = requireAuth();
            $qrCode = $input['qr_code'] ?? null;
            
            if (!$qrCode) {
                throw new Exception('QR code required');
            }
            
            $visitManager = new VisitManager();
            $result = $visitManager->verifyQRCode($qrCode);
            jsonResponse($result);
            break;
            
        case 'generate-qr':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user = requireAuth();
            $visitId = $input['visit_id'] ?? null;
            
            if (!$visitId) {
                throw new Exception('Visit ID required');
            }
            
            $qrGenerator = new QRCodeGenerator();
            $result = $qrGenerator->generateVisitQR($visitId);
            jsonResponse($result);
            break;
            
        // Pre-registration endpoints
        case 'pre-registrations':
            $user = requireAuth();
            
            if ($method === 'GET') {
                $visitManager = new VisitManager();
                $filters = [
                    'date' => $_GET['date'] ?? date('Y-m-d'),
                    'status' => $_GET['status'] ?? '',
                    'page' => $_GET['page'] ?? 1,
                    'limit' => $_GET['limit'] ?? 20
                ];
                
                $preRegs = $visitManager->getPreRegistrations($filters);
                jsonResponse($preRegs);
                
            } elseif ($method === 'POST') {
                $visitManager = new VisitManager();
                $result = $visitManager->createPreRegistration($input, $user['id']);
                jsonResponse($result);
            }
            break;
            
        case 'approve-registration':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user = requireAuth();
            $auth = new Auth();
            $auth->requirePermission($user, 'visitors');
            
            $registrationId = $input['registration_id'] ?? null;
            if (!$registrationId) {
                throw new Exception('Registration ID required');
            }
            
            $visitManager = new VisitManager();
            $result = $visitManager->approvePreRegistration($registrationId, $user['id'], $input);
            jsonResponse($result);
            break;
            
        // Dashboard endpoints
        case 'dashboard-stats':
            $user = requireAuth();
            $visitManager = new VisitManager();
            $visitorManager = new VisitorManager();
            $vehicleManager = new VehicleManager();
            
            $stats = [
                'today_visitors' => $visitManager->getTodayVisitorsCount(),
                'currently_inside' => $visitManager->getCurrentlyInsideCount(),
                'vehicles_count' => $vehicleManager->getActiveVehiclesCount(),
                'pre_registered' => $visitManager->getPendingPreRegistrationsCount(),
                'recent_checkins' => $visitManager->getRecentCheckins(5),
                'upcoming_visits' => $visitManager->getUpcomingVisits(5)
            ];
            
            jsonResponse($stats);
            break;
            
        // Settings endpoints
        case 'settings':
            $user = requireAuth();
            $auth = new Auth();
            $auth->requirePermission($user, 'settings');
            
            if ($method === 'GET') {
                // Get system settings
                $database = new Database();
                $db = $database->getConnection();
                
                $query = "SELECT setting_key, setting_value FROM system_settings";
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $settings = [];
                while ($row = $stmt->fetch()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                
                jsonResponse(['settings' => $settings]);
                
            } elseif ($method === 'POST') {
                // Update settings
                $database = new Database();
                $db = $database->getConnection();
                
                foreach ($input as $key => $value) {
                    $query = "INSERT INTO system_settings (setting_key, setting_value) 
                             VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$key, $value, $value]);
                }
                
                logActivity($user['id'], 'SETTINGS_UPDATE', 'System settings updated');
                jsonResponse(['success' => true, 'message' => 'Settings updated successfully']);
            }
            break;
            
        // Notification endpoints
        case 'notifications':
            $user = requireAuth();
            $notificationManager = new NotificationManager();
            
            if ($method === 'GET') {
                $notifications = $notificationManager->getUserNotifications($user['id'], $_GET['unread_only'] ?? false);
                jsonResponse($notifications);
                
            } elseif ($method === 'PUT') {
                // Mark as read
                $notificationId = $input['notification_id'] ?? null;
                if ($notificationId) {
                    $result = $notificationManager->markAsRead($notificationId, $user['id']);
                    jsonResponse($result);
                }
            }
            break;
            
        // File upload endpoints
        case 'upload':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $user = requireAuth();
            $uploadType = $_POST['type'] ?? 'general';
            
            if (!isset($_FILES['file'])) {
                throw new Exception('No file uploaded');
            }
            
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $uploadPath = UPLOAD_PATH;
            
            switch ($uploadType) {
                case 'visitor_photo':
                    $uploadPath = VISITOR_PHOTOS_PATH;
                    break;
                case 'vehicle_photo':
                    $uploadPath = VEHICLE_PHOTOS_PATH;
                    break;
            }
            
            $fileName = handleFileUpload($_FILES['file'], $uploadPath, $allowedTypes);
            
            jsonResponse([
                'success' => true,
                'filename' => $fileName,
                'url' => $uploadPath . $fileName
            ]);
            break;
            
        // Reports endpoints
        case 'reports':
            $user = requireAuth();
            $auth = new Auth();
            $auth->requirePermission($user, 'reports');
            
            $reportType = $_GET['type'] ?? 'visits';
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $visitManager = new VisitManager();
            
            switch ($reportType) {
                case 'visits':
                    $data = $visitManager->getVisitsReport($dateFrom, $dateTo);
                    break;
                case 'visitors':
                    $visitorManager = new VisitorManager();
                    $data = $visitorManager->getVisitorsReport($dateFrom, $dateTo);
                    break;
                case 'vehicles':
                    $vehicleManager = new VehicleManager();
                    $data = $vehicleManager->getVehiclesReport($dateFrom, $dateTo);
                    break;
                default:
                    throw new Exception('Invalid report type');
            }
            
            jsonResponse(['data' => $data]);
            break;
            
        default:
            throw new Exception('Endpoint not found', 404);
    }
    
} catch (Exception $e) {
    $statusCode = $e->getCode() ?: 500;
    if ($statusCode < 100 || $statusCode > 599) {
        $statusCode = 500;
    }
    
    error_log("API Error [{$endpoint}]: " . $e->getMessage());
    
    jsonResponse([
        'error' => $e->getMessage(),
        'endpoint' => $endpoint,
        'method' => $method
    ], $statusCode);
}
?>