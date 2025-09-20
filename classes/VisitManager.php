<?php
/**
 * Visit Management Class
 * GatePass Pro - Smart Gate Management System
 */

require_once 'QRCodeGenerator.php';
require_once 'NotificationManager.php';

class VisitManager {
    private $db;
    private $qrGenerator;
    private $notificationManager;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->qrGenerator = new QRCodeGenerator();
        $this->notificationManager = new NotificationManager();
    }
    
    public function createVisit($data, $userId) {
        try {
            // Validate input
            $errors = validateInput($data, [
                'visitor_id' => ['required' => true],
                'host_name' => ['required' => true, 'max' => 100],
                'purpose' => ['required' => true]
            ]);
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if visitor is blacklisted
            $visitorQuery = "SELECT * FROM visitors WHERE id = ?";
            $visitorStmt = $this->db->prepare($visitorQuery);
            $visitorStmt->execute([$data['visitor_id']]);
            $visitor = $visitorStmt->fetch();
            
            if (!$visitor) {
                return ['success' => false, 'message' => 'Visitor not found'];
            }
            
            if ($visitor['is_blacklisted']) {
                return ['success' => false, 'message' => 'Visitor is blacklisted: ' . $visitor['blacklist_reason']];
            }
            
            // Generate visit code
            $visitCode = $this->generateVisitCode();
            
            // Generate badge number
            $badgeNumber = $this->generateBadgeNumber();
            
            $query = "INSERT INTO visits (
                        visit_code, visitor_id, vehicle_id, host_name, host_department, 
                        host_phone, host_email, purpose, visit_type, expected_date, 
                        expected_time_in, expected_time_out, status, badge_number, 
                        access_areas, special_instructions, temperature_reading, 
                        health_declaration, created_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $visitCode,
                $data['visitor_id'],
                $data['vehicle_id'] ?? null,
                $data['host_name'],
                $data['host_department'] ?? null,
                $data['host_phone'] ?? null,
                $data['host_email'] ?? null,
                $data['purpose'],
                $data['visit_type'] ?? 'Walk-in',
                $data['expected_date'] ?? date('Y-m-d'),
                $data['expected_time_in'] ?? date('H:i:s'),
                $data['expected_time_out'] ?? null,
                $badgeNumber,
                $data['access_areas'] ?? null,
                $data['special_instructions'] ?? null,
                $data['temperature_reading'] ?? null,
                $data['health_declaration'] ?? 1
            ]);
            
            if ($result) {
                $visitId = $this->db->lastInsertId();
                
                // Generate QR code
                $qrResult = $this->qrGenerator->generateVisitQR($visitId);
                
                if ($qrResult['success']) {
                    // Update visit with QR code path
                    $updateQuery = "UPDATE visits SET qr_code = ? WHERE id = ?";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->execute([$qrResult['qr_path'], $visitId]);
                }
                
                logActivity($userId, 'VISIT_CREATED', "New visit created for visitor ID: {$data['visitor_id']}");
                
                // Send notification to host if email provided
                if (!empty($data['host_email'])) {
                    $this->notificationManager->sendVisitNotification($visitId, 'visit_scheduled');
                }
                
                return [
                    'success' => true,
                    'visit_id' => $visitId,
                    'visit_code' => $visitCode,
                    'badge_number' => $badgeNumber,
                    'qr_code' => $qrResult['qr_path'] ?? null,
                    'message' => 'Visit created successfully'
                ];
            } else {
                throw new Exception('Failed to create visit');
            }
            
        } catch (Exception $e) {
            error_log("Create visit error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create visit'];
        }
    }
    
    public function checkIn($data, $userId) {
        try {
            $visitId = $data['visit_id'] ?? null;
            $qrCode = $data['qr_code'] ?? null;
            
            if ($visitId) {
                $visit = $this->getVisitById($visitId);
            } elseif ($qrCode) {
                $visit = $this->getVisitByQRCode($qrCode);
            } else {
                return ['success' => false, 'message' => 'Visit ID or QR code required'];
            }
            
            if (!$visit) {
                return ['success' => false, 'message' => 'Visit not found'];
            }
            
            if ($visit['status'] === 'Checked In') {
                return ['success' => false, 'message' => 'Visitor is already checked in'];
            }
            
            if ($visit['status'] === 'Checked Out') {
                return ['success' => false, 'message' => 'Visit has already been completed'];
            }
            
            // Check if visitor is blacklisted
            $visitorQuery = "SELECT is_blacklisted, blacklist_reason FROM visitors WHERE id = ?";
            $visitorStmt = $this->db->prepare($visitorQuery);
            $visitorStmt->execute([$visit['visitor_id']]);
            $visitor = $visitorStmt->fetch();
            
            if ($visitor['is_blacklisted']) {
                return ['success' => false, 'message' => 'Visitor is blacklisted: ' . $visitor['blacklist_reason']];
            }
            
            // Update visit record
            $query = "UPDATE visits SET 
                        status = 'Checked In',
                        check_in_time = NOW(),
                        check_in_by = ?,
                        items_carried_in = ?,
                        temperature_reading = ?,
                        health_declaration = ?,
                        notes = ?
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $userId,
                $data['items_carried_in'] ?? null,
                $data['temperature_reading'] ?? null,
                $data['health_declaration'] ?? 1,
                $data['notes'] ?? null,
                $visit['id']
            ]);
            
            if ($result) {
                logActivity($userId, 'VISITOR_CHECKIN', "Visitor checked in: {$visit['visitor_name']} (Visit: {$visit['visit_code']})");
                
                // Send notification
                $this->notificationManager->sendVisitNotification($visit['id'], 'visitor_checkin');
                
                return [
                    'success' => true,
                    'visit' => $this->getVisitById($visit['id']),
                    'message' => 'Check-in successful'
                ];
            } else {
                throw new Exception('Failed to update visit record');
            }
            
        } catch (Exception $e) {
            error_log("Check-in error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Check-in failed'];
        }
    }
    
    public function checkOut($visitId, $userId, $data = []) {
        try {
            $visit = $this->getVisitById($visitId);
            
            if (!$visit) {
                return ['success' => false, 'message' => 'Visit not found'];
            }
            
            if ($visit['status'] !== 'Checked In') {
                return ['success' => false, 'message' => 'Visitor is not currently checked in'];
            }
            
            $query = "UPDATE visits SET 
                        status = 'Checked Out',
                        check_out_time = NOW(),
                        check_out_by = ?,
                        items_carried_out = ?,
                        rating = ?,
                        feedback = ?,
                        notes = CONCAT(COALESCE(notes, ''), ?)
                     WHERE id = ?";
            
            $additionalNotes = !empty($data['checkout_notes']) ? "\nCheckout: " . $data['checkout_notes'] : '';
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $userId,
                $data['items_carried_out'] ?? null,
                $data['rating'] ?? null,
                $data['feedback'] ?? null,
                $additionalNotes,
                $visitId
            ]);
            
            if ($result) {
                logActivity($userId, 'VISITOR_CHECKOUT', "Visitor checked out: {$visit['visitor_name']} (Visit: {$visit['visit_code']})");
                
                // Send notification
                $this->notificationManager->sendVisitNotification($visitId, 'visitor_checkout');
                
                return [
                    'success' => true,
                    'visit' => $this->getVisitById($visitId),
                    'message' => 'Check-out successful'
                ];
            } else {
                throw new Exception('Failed to update visit record');
            }
            
        } catch (Exception $e) {
            error_log("Check-out error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Check-out failed'];
        }
    }
    
    public function getVisits($filters = []) {
        try {
            $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $filters['date_to'] ?? date('Y-m-d');
            $status = $filters['status'] ?? '';
            $visitorId = $filters['visitor_id'] ?? '';
            $page = max(1, intval($filters['page'] ?? 1));
            $limit = max(1, min(100, intval($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $whereConditions = ["DATE(vis.created_at) BETWEEN ? AND ?"];
            $params = [$dateFrom, $dateTo];
            
            if (!empty($status)) {
                $whereConditions[] = "vis.status = ?";
                $params[] = $status;
            }
            
            if (!empty($visitorId)) {
                $whereConditions[] = "vis.visitor_id = ?";
                $params[] = $visitorId;
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM visits vis {$whereClause}";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get visits
            $query = "SELECT vis.*, v.full_name as visitor_name, v.phone as visitor_phone, 
                            v.company as visitor_company, v.photo as visitor_photo,
                            vc.category_name as visitor_category,
                            veh.plate_number, veh.vehicle_type,
                            u1.full_name as checked_in_by_name,
                            u2.full_name as checked_out_by_name,
                            TIMESTAMPDIFF(MINUTE, vis.check_in_time, COALESCE(vis.check_out_time, NOW())) as duration_minutes
                     FROM visits vis
                     LEFT JOIN visitors v ON vis.visitor_id = v.id
                     LEFT JOIN visitor_categories vc ON v.category_id = vc.id
                     LEFT JOIN vehicles veh ON vis.vehicle_id = veh.id
                     LEFT JOIN users u1 ON vis.check_in_by = u1.id
                     LEFT JOIN users u2 ON vis.check_out_by = u2.id
                     {$whereClause}
                     ORDER BY vis.created_at DESC
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $visits = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $visits,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get visits error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch visits'];
        }
    }
    
    public function verifyQRCode($qrCode) {
        try {
            $visit = $this->getVisitByQRCode($qrCode);
            
            if (!$visit) {
                return ['success' => false, 'message' => 'Invalid QR code'];
            }
            
            // Check if visit is still valid
            $expectedDate = $visit['expected_date'];
            $currentDate = date('Y-m-d');
            
            if ($expectedDate < $currentDate) {
                return ['success' => false, 'message' => 'Visit has expired'];
            }
            
            return [
                'success' => true,
                'visit' => $visit,
                'message' => 'QR code verified successfully'
            ];
            
        } catch (Exception $e) {
            error_log("QR verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'QR verification failed'];
        }
    }
    
    public function createPreRegistration($data, $userId) {
        try {
            // Validate input
            $errors = validateInput($data, [
                'visitor_name' => ['required' => true, 'max' => 100],
                'visitor_phone' => ['required' => true, 'phone' => true],
                'host_name' => ['required' => true, 'max' => 100],
                'visit_date' => ['required' => true],
                'visit_time' => ['required' => true],
                'purpose' => ['required' => true]
            ]);
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Generate registration code
            $registrationCode = $this->generateRegistrationCode();
            
            $query = "INSERT INTO pre_registrations (
                        registration_code, visitor_name, visitor_email, visitor_phone,
                        visitor_company, host_name, host_department, host_email,
                        visit_date, visit_time, duration_hours, purpose, vehicle_plate,
                        special_requirements, created_by
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $registrationCode,
                $data['visitor_name'],
                $data['visitor_email'] ?? null,
                $data['visitor_phone'],
                $data['visitor_company'] ?? null,
                $data['host_name'],
                $data['host_department'] ?? null,
                $data['host_email'] ?? null,
                $data['visit_date'],
                $data['visit_time'],
                $data['duration_hours'] ?? 2,
                $data['purpose'],
                $data['vehicle_plate'] ?? null,
                $data['special_requirements'] ?? null,
                $userId
            ]);
            
            if ($result) {
                $registrationId = $this->db->lastInsertId();
                
                // Generate QR code for pre-registration
                $qrResult = $this->qrGenerator->generatePreRegistrationQR($registrationId);
                
                if ($qrResult['success']) {
                    $updateQuery = "UPDATE pre_registrations SET qr_code = ? WHERE id = ?";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->execute([$qrResult['qr_path'], $registrationId]);
                }
                
                logActivity($userId, 'PRE_REGISTRATION_CREATED', "Pre-registration created: {$data['visitor_name']}");
                
                // Send email notification if email provided
                if (!empty($data['visitor_email'])) {
                    $this->notificationManager->sendPreRegistrationNotification($registrationId);
                }
                
                return [
                    'success' => true,
                    'registration_id' => $registrationId,
                    'registration_code' => $registrationCode,
                    'qr_code' => $qrResult['qr_path'] ?? null,
                    'message' => 'Pre-registration created successfully'
                ];
            } else {
                throw new Exception('Failed to create pre-registration');
            }
            
        } catch (Exception $e) {
            error_log("Create pre-registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create pre-registration'];
        }
    }
    
    public function getPreRegistrations($filters = []) {
        try {
            $date = $filters['date'] ?? date('Y-m-d');
            $status = $filters['status'] ?? '';
            $page = max(1, intval($filters['page'] ?? 1));
            $limit = max(1, min(100, intval($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $whereConditions = ["visit_date = ?"];
            $params = [$date];
            
            if (!empty($status)) {
                $whereConditions[] = "status = ?";
                $params[] = $status;
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM pre_registrations {$whereClause}";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get pre-registrations
            $query = "SELECT pr.*, u.full_name as approved_by_name
                     FROM pre_registrations pr
                     LEFT JOIN users u ON pr.approved_by = u.id
                     {$whereClause}
                     ORDER BY pr.created_at DESC
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $preRegistrations = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $preRegistrations,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get pre-registrations error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch pre-registrations'];
        }
    }
    
    public function approvePreRegistration($registrationId, $userId, $data = []) {
        try {
            $query = "UPDATE pre_registrations SET 
                        status = 'Approved',
                        approved_by = ?,
                        approval_notes = ?,
                        updated_at = NOW()
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $userId,
                $data['approval_notes'] ?? null,
                $registrationId
            ]);
            
            if ($result) {
                logActivity($userId, 'PRE_REGISTRATION_APPROVED', "Pre-registration approved (ID: {$registrationId})");
                
                // Send approval notification
                $this->notificationManager->sendPreRegistrationApproval($registrationId);
                
                return ['success' => true, 'message' => 'Pre-registration approved successfully'];
            } else {
                throw new Exception('Failed to approve pre-registration');
            }
            
        } catch (Exception $e) {
            error_log("Approve pre-registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to approve pre-registration'];
        }
    }
    
    // Helper Methods
    private function getVisitById($visitId) {
        try {
            $query = "SELECT vis.*, v.full_name as visitor_name, v.is_blacklisted
                     FROM visits vis
                     LEFT JOIN visitors v ON vis.visitor_id = v.id
                     WHERE vis.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitId]);
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Get visit by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    private function getVisitByQRCode($qrCode) {
        try {
            $query = "SELECT vis.*, v.full_name as visitor_name, v.is_blacklisted
                     FROM visits vis
                     LEFT JOIN visitors v ON vis.visitor_id = v.id
                     WHERE vis.visit_code = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$qrCode]);
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Get visit by QR code error: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateVisitCode() {
        do {
            $code = 'VIS' . date('Ymd') . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
            
            $query = "SELECT id FROM visits WHERE visit_code = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$code]);
            
        } while ($stmt->fetch());
        
        return $code;
    }
    
    private function generateRegistrationCode() {
        do {
            $code = 'REG' . date('Ymd') . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
            
            $query = "SELECT id FROM pre_registrations WHERE registration_code = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$code]);
            
        } while ($stmt->fetch());
        
        return $code;
    }
    
    private function generateBadgeNumber() {
        do {
            $number = 'B' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "SELECT id FROM visits WHERE badge_number = ? AND status IN ('Scheduled', 'Checked In')";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$number]);
            
        } while ($stmt->fetch());
        
        return $number;
    }
    
    // Dashboard Statistics Methods
    public function getTodayVisitorsCount() {
        try {
            $query = "SELECT COUNT(*) as count FROM visits WHERE DATE(check_in_time) = CURDATE()";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    public function getCurrentlyInsideCount() {
        try {
            $query = "SELECT COUNT(*) as count FROM visits WHERE status = 'Checked In'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    public function getPendingPreRegistrationsCount() {
        try {
            $query = "SELECT COUNT(*) as count FROM pre_registrations WHERE status = 'Pending' AND visit_date >= CURDATE()";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    public function getRecentCheckins($limit = 5) {
        try {
            $query = "SELECT vis.*, v.full_name as visitor_name, v.company as visitor_company
                     FROM visits vis
                     LEFT JOIN visitors v ON vis.visitor_id = v.id
                     WHERE vis.status = 'Checked In'
                     ORDER BY vis.check_in_time DESC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function getUpcomingVisits($limit = 5) {
        try {
            $query = "SELECT * FROM pre_registrations 
                     WHERE status = 'Approved' AND visit_date >= CURDATE()
                     ORDER BY visit_date ASC, visit_time ASC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function getVisitsReport($dateFrom, $dateTo) {
        try {
            $query = "SELECT 
                        DATE(vis.check_in_time) as date,
                        COUNT(*) as total_visits,
                        COUNT(CASE WHEN vis.status = 'Checked In' THEN 1 END) as checked_in,
                        COUNT(CASE WHEN vis.status = 'Checked Out' THEN 1 END) as checked_out,
                        COUNT(DISTINCT vis.visitor_id) as unique_visitors,
                        AVG(TIMESTAMPDIFF(MINUTE, vis.check_in_time, vis.check_out_time)) as avg_duration,
                        AVG(vis.rating) as avg_rating
                     FROM visits vis
                     WHERE DATE(vis.check_in_time) BETWEEN ? AND ?
                     GROUP BY DATE(vis.check_in_time)
                     ORDER BY date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$dateFrom, $dateTo]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Visits report error: " . $e->getMessage());
            return [];
        }
    }
}
?>
