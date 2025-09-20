<?php
/**
 * QR Code Generator Class
 * GatePass Pro - Smart Gate Management System
 */

require_once '../vendor/autoload.php'; // Assuming you use Composer for QR code library
// Alternative: You can use any QR code library or service

class QRCodeGenerator {
    private $db;
    private $qrCodePath;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->qrCodePath = QR_CODE_PATH;
        
        // Create QR codes directory if it doesn't exist
        if (!is_dir($this->qrCodePath)) {
            mkdir($this->qrCodePath, 0755, true);
        }
    }
    
    public function generateVisitQR($visitId) {
        try {
            // Get visit details
            $query = "SELECT * FROM visits WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitId]);
            $visit = $stmt->fetch();
            
            if (!$visit) {
                return ['success' => false, 'message' => 'Visit not found'];
            }
            
            // Create QR data
            $qrData = [
                'type' => 'visit',
                'visit_id' => $visitId,
                'visit_code' => $visit['visit_code'],
                'visitor_id' => $visit['visitor_id'],
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => $visit['expected_date']
            ];
            
            $qrString = json_encode($qrData);
            $fileName = 'visit_' . $visitId . '_' . time() . '.png';
            $filePath = $this->qrCodePath . $fileName;
            
            // Generate QR code using simple method (you can integrate with libraries like endroid/qr-code)
            $this->generateQRImage($qrString, $filePath);
            
            return [
                'success' => true,
                'qr_path' => $fileName,
                'qr_url' => SITE_URL . '/' . QR_CODE_PATH . $fileName,
                'qr_data' => $qrString
            ];
            
        } catch (Exception $e) {
            error_log("Generate visit QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate QR code'];
        }
    }
    
    public function generatePreRegistrationQR($registrationId) {
        try {
            // Get pre-registration details
            $query = "SELECT * FROM pre_registrations WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch();
            
            if (!$registration) {
                return ['success' => false, 'message' => 'Pre-registration not found'];
            }
            
            // Create QR data
            $qrData = [
                'type' => 'pre_registration',
                'registration_id' => $registrationId,
                'registration_code' => $registration['registration_code'],
                'visitor_name' => $registration['visitor_name'],
                'visit_date' => $registration['visit_date'],
                'visit_time' => $registration['visit_time'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $qrString = json_encode($qrData);
            $fileName = 'prereg_' . $registrationId . '_' . time() . '.png';
            $filePath = $this->qrCodePath . $fileName;
            
            // Generate QR code
            $this->generateQRImage($qrString, $filePath);
            
            return [
                'success' => true,
                'qr_path' => $fileName,
                'qr_url' => SITE_URL . '/' . QR_CODE_PATH . $fileName,
                'qr_data' => $qrString
            ];
            
        } catch (Exception $e) {
            error_log("Generate pre-registration QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate QR code'];
        }
    }
    
    public function generateVisitorCardQR($visitorId) {
        try {
            // Get visitor details
            $query = "SELECT * FROM visitors WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitorId]);
            $visitor = $stmt->fetch();
            
            if (!$visitor) {
                return ['success' => false, 'message' => 'Visitor not found'];
            }
            
            // Create QR data for visitor card
            $qrData = [
                'type' => 'visitor_card',
                'visitor_id' => $visitorId,
                'visitor_code' => $visitor['visitor_code'],
                'full_name' => $visitor['full_name'],
                'phone' => $visitor['phone'],
                'company' => $visitor['company'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $qrString = json_encode($qrData);
            $fileName = 'visitor_card_' . $visitorId . '_' . time() . '.png';
            $filePath = $this->qrCodePath . $fileName;
            
            // Generate QR code
            $this->generateQRImage($qrString, $filePath);
            
            return [
                'success' => true,
                'qr_path' => $fileName,
                'qr_url' => SITE_URL . '/' . QR_CODE_PATH . $fileName,
                'qr_data' => $qrString
            ];
            
        } catch (Exception $e) {
            error_log("Generate visitor card QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate QR code'];
        }
    }
    
    public function generateAccessQR($visitId, $accessAreas = []) {
        try {
            // Get visit details
            $query = "SELECT vis.*, v.full_name as visitor_name 
                     FROM visits vis 
                     LEFT JOIN visitors v ON vis.visitor_id = v.id 
                     WHERE vis.id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitId]);
            $visit = $stmt->fetch();
            
            if (!$visit) {
                return ['success' => false, 'message' => 'Visit not found'];
            }
            
            // Create QR data for access control
            $qrData = [
                'type' => 'access_control',
                'visit_id' => $visitId,
                'visit_code' => $visit['visit_code'],
                'visitor_name' => $visit['visitor_name'],
                'access_areas' => $accessAreas,
                'valid_from' => date('Y-m-d H:i:s'),
                'valid_until' => $visit['expected_date'] . ' 23:59:59',
                'badge_number' => $visit['badge_number']
            ];
            
            $qrString = json_encode($qrData);
            $fileName = 'access_' . $visitId . '_' . time() . '.png';
            $filePath = $this->qrCodePath . $fileName;
            
            // Generate QR code
            $this->generateQRImage($qrString, $filePath);
            
            return [
                'success' => true,
                'qr_path' => $fileName,
                'qr_url' => SITE_URL . '/' . QR_CODE_PATH . $fileName,
                'qr_data' => $qrString
            ];
            
        } catch (Exception $e) {
            error_log("Generate access QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate access QR code'];
        }
    }
    
    public function verifyQRCode($qrString) {
        try {
            $qrData = json_decode($qrString, true);
            
            if (!$qrData || !isset($qrData['type'])) {
                return ['success' => false, 'message' => 'Invalid QR code format'];
            }
            
            switch ($qrData['type']) {
                case 'visit':
                    return $this->verifyVisitQR($qrData);
                    
                case 'pre_registration':
                    return $this->verifyPreRegistrationQR($qrData);
                    
                case 'visitor_card':
                    return $this->verifyVisitorCardQR($qrData);
                    
                case 'access_control':
                    return $this->verifyAccessQR($qrData);
                    
                default:
                    return ['success' => false, 'message' => 'Unknown QR code type'];
            }
            
        } catch (Exception $e) {
            error_log("Verify QR code error: " . $e->getMessage());
            return ['success' => false, 'message' => 'QR code verification failed'];
        }
    }
    
    private function verifyVisitQR($qrData) {
        try {
            $visitId = $qrData['visit_id'] ?? null;
            
            if (!$visitId) {
                return ['success' => false, 'message' => 'Invalid visit QR code'];
            }
            
            // Get visit details
            $query = "SELECT vis.*, v.full_name as visitor_name, v.is_blacklisted, v.blacklist_reason
                     FROM visits vis
                     LEFT JOIN visitors v ON vis.visitor_id = v.id
                     WHERE vis.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitId]);
            $visit = $stmt->fetch();
            
            if (!$visit) {
                return ['success' => false, 'message' => 'Visit not found'];
            }
            
            // Check if visitor is blacklisted
            if ($visit['is_blacklisted']) {
                return ['success' => false, 'message' => 'Visitor is blacklisted: ' . $visit['blacklist_reason']];
            }
            
            // Check if visit is expired
            if ($visit['expected_date'] < date('Y-m-d')) {
                return ['success' => false, 'message' => 'Visit has expired'];
            }
            
            // Check visit status
            if ($visit['status'] === 'Checked Out') {
                return ['success' => false, 'message' => 'Visit has already been completed'];
            }
            
            return [
                'success' => true,
                'data' => $visit,
                'message' => 'Valid visit QR code'
            ];
            
        } catch (Exception $e) {
            error_log("Verify visit QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Visit verification failed'];
        }
    }
    
    private function verifyPreRegistrationQR($qrData) {
        try {
            $registrationId = $qrData['registration_id'] ?? null;
            
            if (!$registrationId) {
                return ['success' => false, 'message' => 'Invalid pre-registration QR code'];
            }
            
            // Get pre-registration details
            $query = "SELECT * FROM pre_registrations WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch();
            
            if (!$registration) {
                return ['success' => false, 'message' => 'Pre-registration not found'];
            }
            
            // Check status
            if ($registration['status'] !== 'Approved') {
                return ['success' => false, 'message' => 'Pre-registration not approved'];
            }
            
            // Check if already used
            if ($registration['status'] === 'Used') {
                return ['success' => false, 'message' => 'Pre-registration already used'];
            }
            
            // Check date
            if ($registration['visit_date'] < date('Y-m-d')) {
                return ['success' => false, 'message' => 'Pre-registration has expired'];
            }
            
            return [
                'success' => true,
                'data' => $registration,
                'message' => 'Valid pre-registration QR code'
            ];
            
        } catch (Exception $e) {
            error_log("Verify pre-registration QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Pre-registration verification failed'];
        }
    }
    
    private function verifyVisitorCardQR($qrData) {
        try {
            $visitorId = $qrData['visitor_id'] ?? null;
            
            if (!$visitorId) {
                return ['success' => false, 'message' => 'Invalid visitor card QR code'];
            }
            
            // Get visitor details
            $query = "SELECT * FROM visitors WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitorId]);
            $visitor = $stmt->fetch();
            
            if (!$visitor) {
                return ['success' => false, 'message' => 'Visitor not found'];
            }
            
            // Check if visitor is blacklisted
            if ($visitor['is_blacklisted']) {
                return ['success' => false, 'message' => 'Visitor is blacklisted: ' . $visitor['blacklist_reason']];
            }
            
            return [
                'success' => true,
                'data' => $visitor,
                'message' => 'Valid visitor card QR code'
            ];
            
        } catch (Exception $e) {
            error_log("Verify visitor card QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Visitor card verification failed'];
        }
    }
    
    private function verifyAccessQR($qrData) {
        try {
            $visitId = $qrData['visit_id'] ?? null;
            $validUntil = $qrData['valid_until'] ?? null;
            
            if (!$visitId || !$validUntil) {
                return ['success' => false, 'message' => 'Invalid access QR code'];
            }
            
            // Check if access is still valid
            if ($validUntil < date('Y-m-d H:i:s')) {
                return ['success' => false, 'message' => 'Access QR code has expired'];
            }
            
            // Get visit details
            $query = "SELECT vis.*, v.full_name as visitor_name, v.is_blacklisted
                     FROM visits vis
                     LEFT JOIN visitors v ON vis.visitor_id = v.id
                     WHERE vis.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitId]);
            $visit = $stmt->fetch();
            
            if (!$visit) {
                return ['success' => false, 'message' => 'Visit not found'];
            }
            
            // Check if visitor is blacklisted
            if ($visit['is_blacklisted']) {
                return ['success' => false, 'message' => 'Visitor access denied'];
            }
            
            // Check if visitor is currently checked in
            if ($visit['status'] !== 'Checked In') {
                return ['success' => false, 'message' => 'Visitor is not currently checked in'];
            }
            
            return [
                'success' => true,
                'data' => [
                    'visit' => $visit,
                    'access_areas' => $qrData['access_areas'] ?? [],
                    'badge_number' => $qrData['badge_number'] ?? null
                ],
                'message' => 'Valid access QR code'
            ];
            
        } catch (Exception $e) {
            error_log("Verify access QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Access verification failed'];
        }
    }
    
    private function generateQRImage($data, $filePath) {
        // Simple QR code generation using Google Charts API (for demonstration)
        // In production, use a proper QR code library like endroid/qr-code
        
        $size = QR_CODE_SIZE . 'x' . QR_CODE_SIZE;
        $qrUrl = "https://chart.googleapis.com/chart?chs={$size}&cht=qr&chl=" . urlencode($data);
        
        $imageData = file_get_contents($qrUrl);
        
        if ($imageData !== false) {
            file_put_contents($filePath, $imageData);
            return true;
        }
        
        return false;
    }
    
    public function generateBulkQRCodes($visits) {
        try {
            $results = [];
            
            foreach ($visits as $visit) {
                $result = $this->generateVisitQR($visit['id']);
                $results[] = [
                    'visit_id' => $visit['id'],
                    'visit_code' => $visit['visit_code'],
                    'result' => $result
                ];
            }
            
            return [
                'success' => true,
                'results' => $results,
                'message' => 'Bulk QR generation completed'
            ];
            
        } catch (Exception $e) {
            error_log("Bulk QR generation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Bulk QR generation failed'];
        }
    }
    
    public function regenerateQRCode($type, $entityId) {
        try {
            switch ($type) {
                case 'visit':
                    return $this->generateVisitQR($entityId);
                    
                case 'pre_registration':
                    return $this->generatePreRegistrationQR($entityId);
                    
                case 'visitor_card':
                    return $this->generateVisitorCardQR($entityId);
                    
                default:
                    return ['success' => false, 'message' => 'Invalid QR type'];
            }
            
        } catch (Exception $e) {
            error_log("Regenerate QR code error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to regenerate QR code'];
        }
    }
    
    public function deleteQRCode($filePath) {
        try {
            $fullPath = $this->qrCodePath . $filePath;
            
            if (file_exists($fullPath)) {
                unlink($fullPath);
                return ['success' => true, 'message' => 'QR code deleted'];
            }
            
            return ['success' => false, 'message' => 'QR code file not found'];
            
        } catch (Exception $e) {
            error_log("Delete QR code error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete QR code'];
        }
    }
    
    public function getQRCodeStats() {
        try {
            $stats = [];
            
            // Count QR codes generated today
            $todayQuery = "SELECT COUNT(*) as count FROM visits WHERE DATE(created_at) = CURDATE() AND qr_code IS NOT NULL";
            $todayStmt = $this->db->prepare($todayQuery);
            $todayStmt->execute();
            $stats['generated_today'] = $todayStmt->fetch()['count'];
            
            // Count total QR codes
            $totalQuery = "SELECT COUNT(*) as count FROM visits WHERE qr_code IS NOT NULL";
            $totalStmt = $this->db->prepare($totalQuery);
            $totalStmt->execute();
            $stats['total_generated'] = $totalStmt->fetch()['count'];
            
            // Count pre-registration QR codes
            $preRegQuery = "SELECT COUNT(*) as count FROM pre_registrations WHERE qr_code IS NOT NULL";
            $preRegStmt = $this->db->prepare($preRegQuery);
            $preRegStmt->execute();
            $stats['pre_registration_qr'] = $preRegStmt->fetch()['count'];
            
            // Count files in QR directory
            $files = glob($this->qrCodePath . '*.png');
            $stats['qr_files_count'] = count($files);
            
            // Calculate total file size
            $totalSize = 0;
            foreach ($files as $file) {
                $totalSize += filesize($file);
            }
            $stats['total_file_size'] = $totalSize;
            $stats['total_file_size_mb'] = round($totalSize / 1024 / 1024, 2);
            
            return ['success' => true, 'data' => $stats];
            
        } catch (Exception $e) {
            error_log("Get QR stats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to get QR code statistics'];
        }
    }
    
    public function cleanupOldQRCodes($daysBefore = 30) {
        try {
            $deletedCount = 0;
            $cutoffDate = date('Y-m-d', strtotime("-{$daysBefore} days"));
            
            // Get old QR codes from database
            $query = "SELECT qr_code FROM visits WHERE DATE(created_at) < ? AND qr_code IS NOT NULL
                     UNION
                     SELECT qr_code FROM pre_registrations WHERE DATE(created_at) < ? AND qr_code IS NOT NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$cutoffDate, $cutoffDate]);
            
            while ($row = $stmt->fetch()) {
                $filePath = $this->qrCodePath . $row['qr_code'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                    $deletedCount++;
                }
            }
            
            logActivity(null, 'QR_CLEANUP', "Cleaned up {$deletedCount} old QR code files");
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Cleaned up {$deletedCount} old QR code files"
            ];
            
        } catch (Exception $e) {
            error_log("QR cleanup error: " . $e->getMessage());
            return ['success' => false, 'message' => 'QR code cleanup failed'];
        }
    }
    
    public function generateCustomQR($data, $filename = null) {
        try {
            if (!$filename) {
                $filename = 'custom_' . time() . '.png';
            }
            
            $filePath = $this->qrCodePath . $filename;
            
            // Generate QR code
            $this->generateQRImage($data, $filePath);
            
            return [
                'success' => true,
                'qr_path' => $filename,
                'qr_url' => SITE_URL . '/' . QR_CODE_PATH . $filename,
                'message' => 'Custom QR code generated'
            ];
            
        } catch (Exception $e) {
            error_log("Generate custom QR error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate custom QR code'];
        }
    }
}
?>