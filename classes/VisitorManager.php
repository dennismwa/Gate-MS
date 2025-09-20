<?php
/**
 * Visitor Management Class
 * GatePass Pro - Smart Gate Management System
 */

class VisitorManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getVisitors($filters = []) {
        try {
            $search = $filters['search'] ?? '';
            $category = $filters['category'] ?? '';
            $status = $filters['status'] ?? '';
            $page = max(1, intval($filters['page'] ?? 1));
            $limit = max(1, min(100, intval($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(v.full_name LIKE ? OR v.email LIKE ? OR v.phone LIKE ? OR v.company LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($category)) {
                $whereConditions[] = "vc.category_name = ?";
                $params[] = $category;
            }
            
            if ($status === 'active') {
                $whereConditions[] = "v.is_blacklisted = 0";
            } elseif ($status === 'blacklisted') {
                $whereConditions[] = "v.is_blacklisted = 1";
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total 
                          FROM visitors v 
                          LEFT JOIN visitor_categories vc ON v.category_id = vc.id 
                          {$whereClause}";
            
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get visitors
            $query = "SELECT v.*, vc.category_name, vc.color as category_color,
                            (SELECT MAX(vis.check_in_time) FROM visits vis WHERE vis.visitor_id = v.id) as last_visit,
                            (SELECT COUNT(*) FROM visits vis WHERE vis.visitor_id = v.id) as total_visits,
                            (SELECT AVG(vis.rating) FROM visits vis WHERE vis.visitor_id = v.id AND vis.rating IS NOT NULL) as avg_rating
                     FROM visitors v 
                     LEFT JOIN visitor_categories vc ON v.category_id = vc.id 
                     {$whereClause}
                     ORDER BY v.created_at DESC 
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $visitors = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $visitors,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get visitors error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch visitors'];
        }
    }
    
    public function getVisitor($visitorId) {
        try {
            $query = "SELECT v.*, vc.category_name, vc.color as category_color,
                            (SELECT COUNT(*) FROM visits vis WHERE vis.visitor_id = v.id) as total_visits,
                            (SELECT COUNT(*) FROM visits vis WHERE vis.visitor_id = v.id AND vis.status = 'Checked In') as active_visits,
                            (SELECT AVG(vis.rating) FROM visits vis WHERE vis.visitor_id = v.id AND vis.rating IS NOT NULL) as avg_rating
                     FROM visitors v 
                     LEFT JOIN visitor_categories vc ON v.category_id = vc.id 
                     WHERE v.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitorId]);
            $visitor = $stmt->fetch();
            
            if (!$visitor) {
                return ['success' => false, 'message' => 'Visitor not found'];
            }
            
            // Get recent visits
            $visitsQuery = "SELECT vis.*, u1.full_name as checked_in_by_name, u2.full_name as checked_out_by_name,
                                  veh.plate_number, veh.vehicle_type
                           FROM visits vis 
                           LEFT JOIN users u1 ON vis.check_in_by = u1.id
                           LEFT JOIN users u2 ON vis.check_out_by = u2.id
                           LEFT JOIN vehicles veh ON vis.vehicle_id = veh.id
                           WHERE vis.visitor_id = ? 
                           ORDER BY vis.created_at DESC 
                           LIMIT 10";
            
            $visitsStmt = $this->db->prepare($visitsQuery);
            $visitsStmt->execute([$visitorId]);
            $recentVisits = $visitsStmt->fetchAll();
            
            $visitor['recent_visits'] = $recentVisits;
            
            return ['success' => true, 'data' => $visitor];
            
        } catch (Exception $e) {
            error_log("Get visitor error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch visitor details'];
        }
    }
    
    public function createVisitor($data) {
        try {
            // Validate input
            $errors = validateInput($data, [
                'full_name' => ['required' => true, 'max' => 100],
                'phone' => ['required' => true, 'phone' => true],
                'email' => ['email' => true],
                'id_number' => ['max' => 50]
            ]);
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check for existing visitor with same phone or email
            $checkQuery = "SELECT id FROM visitors WHERE phone = ? OR (email IS NOT NULL AND email = ?)";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$data['phone'], $data['email'] ?? '']);
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Visitor with this phone number or email already exists'];
            }
            
            // Generate visitor code
            $visitorCode = $this->generateVisitorCode();
            
            $query = "INSERT INTO visitors (visitor_code, full_name, email, phone, company, id_type, id_number, 
                                          category_id, emergency_contact_name, emergency_contact_phone, photo) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $visitorCode,
                $data['full_name'],
                $data['email'] ?? null,
                $data['phone'],
                $data['company'] ?? null,
                $data['id_type'] ?? 'National ID',
                $data['id_number'] ?? null,
                $data['category_id'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
                $data['photo'] ?? null
            ]);
            
            if ($result) {
                $visitorId = $this->db->lastInsertId();
                logActivity(null, 'VISITOR_CREATED', "New visitor created: {$data['full_name']}");
                
                return [
                    'success' => true,
                    'visitor_id' => $visitorId,
                    'visitor_code' => $visitorCode,
                    'message' => 'Visitor created successfully'
                ];
            } else {
                throw new Exception('Failed to insert visitor');
            }
            
        } catch (Exception $e) {
            error_log("Create visitor error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create visitor'];
        }
    }
    
    public function updateVisitor($visitorId, $data) {
        try {
            // Validate input
            $errors = validateInput($data, [
                'full_name' => ['required' => true, 'max' => 100],
                'phone' => ['required' => true, 'phone' => true],
                'email' => ['email' => true],
                'id_number' => ['max' => 50]
            ]);
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if visitor exists
            $checkQuery = "SELECT id FROM visitors WHERE id = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$visitorId]);
            
            if (!$checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Visitor not found'];
            }
            
            // Check for duplicate phone/email (excluding current visitor)
            $duplicateQuery = "SELECT id FROM visitors WHERE (phone = ? OR (email IS NOT NULL AND email = ?)) AND id != ?";
            $duplicateStmt = $this->db->prepare($duplicateQuery);
            $duplicateStmt->execute([$data['phone'], $data['email'] ?? '', $visitorId]);
            
            if ($duplicateStmt->fetch()) {
                return ['success' => false, 'message' => 'Another visitor with this phone number or email already exists'];
            }
            
            $query = "UPDATE visitors SET 
                        full_name = ?, email = ?, phone = ?, company = ?, id_type = ?, id_number = ?,
                        category_id = ?, emergency_contact_name = ?, emergency_contact_phone = ?,
                        updated_at = NOW()
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $data['full_name'],
                $data['email'] ?? null,
                $data['phone'],
                $data['company'] ?? null,
                $data['id_type'] ?? 'National ID',
                $data['id_number'] ?? null,
                $data['category_id'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null,
                $visitorId
            ]);
            
            if ($result) {
                logActivity(null, 'VISITOR_UPDATED', "Visitor updated: {$data['full_name']} (ID: {$visitorId})");
                return ['success' => true, 'message' => 'Visitor updated successfully'];
            } else {
                throw new Exception('Failed to update visitor');
            }
            
        } catch (Exception $e) {
            error_log("Update visitor error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update visitor'];
        }
    }
    
    public function deleteVisitor($visitorId) {
        try {
            // Check if visitor has active visits
            $activeVisitsQuery = "SELECT COUNT(*) as count FROM visits WHERE visitor_id = ? AND status = 'Checked In'";
            $activeStmt = $this->db->prepare($activeVisitsQuery);
            $activeStmt->execute([$visitorId]);
            $activeCount = $activeStmt->fetch()['count'];
            
            if ($activeCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete visitor with active visits'];
            }
            
            // Get visitor name for logging
            $nameQuery = "SELECT full_name FROM visitors WHERE id = ?";
            $nameStmt = $this->db->prepare($nameQuery);
            $nameStmt->execute([$visitorId]);
            $visitor = $nameStmt->fetch();
            
            if (!$visitor) {
                return ['success' => false, 'message' => 'Visitor not found'];
            }
            
            // Delete visitor (this will cascade to related records if properly configured)
            $query = "DELETE FROM visitors WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$visitorId]);
            
            if ($result) {
                logActivity(null, 'VISITOR_DELETED', "Visitor deleted: {$visitor['full_name']} (ID: {$visitorId})");
                return ['success' => true, 'message' => 'Visitor deleted successfully'];
            } else {
                throw new Exception('Failed to delete visitor');
            }
            
        } catch (Exception $e) {
            error_log("Delete visitor error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete visitor'];
        }
    }
    
    public function blacklistVisitor($visitorId, $reason, $userId) {
        try {
            $query = "UPDATE visitors SET is_blacklisted = 1, blacklist_reason = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$reason, $visitorId]);
            
            if ($result) {
                // Add to blacklist table
                $blacklistQuery = "INSERT INTO blacklist (entity_type, entity_id, reason, added_by) VALUES ('Visitor', ?, ?, ?)";
                $blacklistStmt = $this->db->prepare($blacklistQuery);
                $blacklistStmt->execute([$visitorId, $reason, $userId]);
                
                logActivity($userId, 'VISITOR_BLACKLISTED', "Visitor blacklisted (ID: {$visitorId})");
                return ['success' => true, 'message' => 'Visitor blacklisted successfully'];
            } else {
                throw new Exception('Failed to blacklist visitor');
            }
            
        } catch (Exception $e) {
            error_log("Blacklist visitor error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to blacklist visitor'];
        }
    }
    
    public function removeFromBlacklist($visitorId, $userId) {
        try {
            $query = "UPDATE visitors SET is_blacklisted = 0, blacklist_reason = NULL, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$visitorId]);
            
            if ($result) {
                // Update blacklist table
                $blacklistQuery = "UPDATE blacklist SET is_active = 0 WHERE entity_type = 'Visitor' AND entity_id = ?";
                $blacklistStmt = $this->db->prepare($blacklistQuery);
                $blacklistStmt->execute([$visitorId]);
                
                logActivity($userId, 'VISITOR_UNBLACKLISTED', "Visitor removed from blacklist (ID: {$visitorId})");
                return ['success' => true, 'message' => 'Visitor removed from blacklist successfully'];
            } else {
                throw new Exception('Failed to remove visitor from blacklist');
            }
            
        } catch (Exception $e) {
            error_log("Remove from blacklist error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to remove visitor from blacklist'];
        }
    }
    
    public function getVisitorByPhone($phone) {
        try {
            $query = "SELECT v.*, vc.category_name 
                     FROM visitors v 
                     LEFT JOIN visitor_categories vc ON v.category_id = vc.id 
                     WHERE v.phone = ? AND v.is_blacklisted = 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$phone]);
            $visitor = $stmt->fetch();
            
            return $visitor ? ['success' => true, 'data' => $visitor] : ['success' => false, 'message' => 'Visitor not found'];
            
        } catch (Exception $e) {
            error_log("Get visitor by phone error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to find visitor'];
        }
    }
    
    public function getVisitorByEmail($email) {
        try {
            $query = "SELECT v.*, vc.category_name 
                     FROM visitors v 
                     LEFT JOIN visitor_categories vc ON v.category_id = vc.id 
                     WHERE v.email = ? AND v.is_blacklisted = 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
            $visitor = $stmt->fetch();
            
            return $visitor ? ['success' => true, 'data' => $visitor] : ['success' => false, 'message' => 'Visitor not found'];
            
        } catch (Exception $e) {
            error_log("Get visitor by email error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to find visitor'];
        }
    }
    
    public function getVisitorsReport($dateFrom, $dateTo) {
        try {
            $query = "SELECT 
                        DATE(v.created_at) as date,
                        COUNT(*) as new_visitors,
                        COUNT(CASE WHEN v.is_blacklisted = 1 THEN 1 END) as blacklisted_visitors,
                        vc.category_name,
                        COUNT(CASE WHEN vc.category_name IS NOT NULL THEN 1 END) as category_count
                     FROM visitors v
                     LEFT JOIN visitor_categories vc ON v.category_id = vc.id
                     WHERE DATE(v.created_at) BETWEEN ? AND ?
                     GROUP BY DATE(v.created_at), vc.category_name
                     ORDER BY date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll();
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Visitors report error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getVisitorCategories() {
        try {
            $query = "SELECT * FROM visitor_categories WHERE is_active = 1 ORDER BY category_name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $categories = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $categories];
            
        } catch (Exception $e) {
            error_log("Get visitor categories error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch categories'];
        }
    }
    
    public function createVisitorCategory($data) {
        try {
            $errors = validateInput($data, [
                'category_name' => ['required' => true, 'max' => 50],
                'color' => ['required' => true]
            ]);
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            $query = "INSERT INTO visitor_categories (category_name, description, color) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $data['category_name'],
                $data['description'] ?? null,
                $data['color']
            ]);
            
            if ($result) {
                $categoryId = $this->db->lastInsertId();
                return ['success' => true, 'category_id' => $categoryId, 'message' => 'Category created successfully'];
            } else {
                throw new Exception('Failed to create category');
            }
            
        } catch (Exception $e) {
            error_log("Create visitor category error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create category'];
        }
    }
    
    private function generateVisitorCode() {
        do {
            $code = 'VTR' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            
            $query = "SELECT id FROM visitors WHERE visitor_code = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$code]);
            
        } while ($stmt->fetch());
        
        return $code;
    }
    
    public function searchVisitors($searchTerm, $limit = 10) {
        try {
            $query = "SELECT id, visitor_code, full_name, email, phone, company, photo
                     FROM visitors 
                     WHERE (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ?) 
                     AND is_blacklisted = 0
                     ORDER BY full_name ASC 
                     LIMIT ?";
            
            $searchTerm = "%{$searchTerm}%";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
            $visitors = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $visitors];
            
        } catch (Exception $e) {
            error_log("Search visitors error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Search failed'];
        }
    }
    
    public function getFrequentVisitors($limit = 20) {
        try {
            $query = "SELECT v.*, vc.category_name,
                            COUNT(vis.id) as visit_count,
                            MAX(vis.check_in_time) as last_visit,
                            AVG(vis.rating) as avg_rating
                     FROM visitors v
                     LEFT JOIN visitor_categories vc ON v.category_id = vc.id
                     LEFT JOIN visits vis ON v.id = vis.visitor_id
                     WHERE v.is_blacklisted = 0
                     GROUP BY v.id
                     HAVING visit_count > 0
                     ORDER BY visit_count DESC, last_visit DESC
                     LIMIT ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$limit]);
            $visitors = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $visitors];
            
        } catch (Exception $e) {
            error_log("Get frequent visitors error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch frequent visitors'];
        }
    }
    
    public function updateVisitorPhoto($visitorId, $photoPath) {
        try {
            $query = "UPDATE visitors SET photo = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$photoPath, $visitorId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Photo updated successfully'];
            } else {
                throw new Exception('Failed to update photo');
            }
            
        } catch (Exception $e) {
            error_log("Update visitor photo error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update photo'];
        }
    }
    
    public function getVisitorStats() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_visitors,
                        COUNT(CASE WHEN is_blacklisted = 0 THEN 1 END) as active_visitors,
                        COUNT(CASE WHEN is_blacklisted = 1 THEN 1 END) as blacklisted_visitors,
                        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
                        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week,
                        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
                     FROM visitors";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            // Get category breakdown
            $categoryQuery = "SELECT vc.category_name, vc.color, COUNT(v.id) as count
                             FROM visitor_categories vc
                             LEFT JOIN visitors v ON vc.id = v.category_id AND v.is_blacklisted = 0
                             WHERE vc.is_active = 1
                             GROUP BY vc.id
                             ORDER BY count DESC";
            
            $categoryStmt = $this->db->prepare($categoryQuery);
            $categoryStmt->execute();
            $categories = $categoryStmt->fetchAll();
            
            $stats['categories'] = $categories;
            
            return ['success' => true, 'data' => $stats];
            
        } catch (Exception $e) {
            error_log("Get visitor stats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch visitor statistics'];
        }
    }
}
?>