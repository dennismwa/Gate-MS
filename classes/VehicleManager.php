<?php
/**
 * Vehicle Management Class
 * GatePass Pro - Smart Gate Management System
 */

class VehicleManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getVehicles($filters = []) {
        try {
            $search = $filters['search'] ?? '';
            $type = $filters['type'] ?? '';
            $ownerType = $filters['owner_type'] ?? '';
            $page = max(1, intval($filters['page'] ?? 1));
            $limit = max(1, min(100, intval($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $whereConditions = ["veh.is_active = 1"];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(veh.plate_number LIKE ? OR veh.make LIKE ? OR veh.model LIKE ? OR veh.driver_name LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($type)) {
                $whereConditions[] = "veh.vehicle_type = ?";
                $params[] = $type;
            }
            
            if (!empty($ownerType)) {
                $whereConditions[] = "veh.owner_type = ?";
                $params[] = $ownerType;
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM vehicles veh {$whereClause}";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get vehicles with owner details
            $query = "SELECT veh.*, 
                            CASE 
                                WHEN veh.owner_type = 'Visitor' THEN v.full_name
                                WHEN veh.owner_type = 'Staff' THEN u.full_name
                                ELSE 'Company Vehicle'
                            END as owner_name,
                            CASE 
                                WHEN veh.owner_type = 'Visitor' THEN v.phone
                                WHEN veh.owner_type = 'Staff' THEN u.phone
                                ELSE NULL
                            END as owner_phone,
                            (SELECT COUNT(*) FROM visits vis WHERE vis.vehicle_id = veh.id) as total_visits,
                            (SELECT COUNT(*) FROM visits vis WHERE vis.vehicle_id = veh.id AND vis.status = 'Checked In') as active_visits
                     FROM vehicles veh
                     LEFT JOIN visitors v ON veh.owner_type = 'Visitor' AND veh.owner_id = v.id
                     LEFT JOIN users u ON veh.owner_type = 'Staff' AND veh.owner_id = u.id
                     {$whereClause}
                     ORDER BY veh.created_at DESC
                     LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $vehicles = $stmt->fetchAll();
            
            return [
                'success' => true,
                'data' => $vehicles,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get vehicles error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch vehicles'];
        }
    }
    
    public function getVehicle($vehicleId) {
        try {
            $query = "SELECT veh.*, 
                            CASE 
                                WHEN veh.owner_type = 'Visitor' THEN v.full_name
                                WHEN veh.owner_type = 'Staff' THEN u.full_name
                                ELSE 'Company Vehicle'
                            END as owner_name,
                            CASE 
                                WHEN veh.owner_type = 'Visitor' THEN v.phone
                                WHEN veh.owner_type = 'Staff' THEN u.phone
                                ELSE NULL
                            END as owner_phone,
                            CASE 
                                WHEN veh.owner_type = 'Visitor' THEN v.email
                                WHEN veh.owner_type = 'Staff' THEN u.email
                                ELSE NULL
                            END as owner_email
                     FROM vehicles veh
                     LEFT JOIN visitors v ON veh.owner_type = 'Visitor' AND veh.owner_id = v.id
                     LEFT JOIN users u ON veh.owner_type = 'Staff' AND veh.owner_id = u.id
                     WHERE veh.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$vehicleId]);
            $vehicle = $stmt->fetch();
            
            if (!$vehicle) {
                return ['success' => false, 'message' => 'Vehicle not found'];
            }
            
            // Get recent visits for this vehicle
            $visitsQuery = "SELECT vis.*, v.full_name as visitor_name, v.company as visitor_company
                           FROM visits vis
                           LEFT JOIN visitors v ON vis.visitor_id = v.id
                           WHERE vis.vehicle_id = ?
                           ORDER BY vis.created_at DESC
                           LIMIT 10";
            
            $visitsStmt = $this->db->prepare($visitsQuery);
            $visitsStmt->execute([$vehicleId]);
            $recentVisits = $visitsStmt->fetchAll();
            
            $vehicle['recent_visits'] = $recentVisits;
            
            return ['success' => true, 'data' => $vehicle];
            
        } catch (Exception $e) {
            error_log("Get vehicle error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch vehicle details'];
        }
    }
    
    public function createVehicle($data) {
        try {
            // Validate input
            $errors = validateInput($data, [
                'plate_number' => ['required' => true, 'max' => 20],
                'vehicle_type' => ['required' => true],
                'owner_type' => ['required' => true]
            ]);
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check for existing vehicle with same plate number
            $checkQuery = "SELECT id FROM vehicles WHERE plate_number = ? AND is_active = 1";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([strtoupper($data['plate_number'])]);
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Vehicle with this plate number already exists'];
            }
            
            $query = "INSERT INTO vehicles (plate_number, vehicle_type, make, model, color, owner_type, 
                                          owner_id, driver_name, driver_phone, driver_license) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                strtoupper($data['plate_number']),
                $data['vehicle_type'],
                $data['make'] ?? null,
                $data['model'] ?? null,
                $data['color'] ?? null,
                $data['owner_type'],
                $data['owner_id'] ?? null,
                $data['driver_name'] ?? null,
                $data['driver_phone'] ?? null,
                $data['driver_license'] ?? null
            ]);
            
            if ($result) {
                $vehicleId = $this->db->lastInsertId();
                logActivity(null, 'VEHICLE_CREATED', "New vehicle created: {$data['plate_number']}");
                
                return [
                    'success' => true,
                    'vehicle_id' => $vehicleId,
                    'message' => 'Vehicle created successfully'
                ];
            } else {
                throw new Exception('Failed to insert vehicle');
            }
            
        } catch (Exception $e) {
            error_log("Create vehicle error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create vehicle'];
        }
    }
    
    public function updateVehicle($vehicleId, $data) {
        try {
            // Validate input
            $errors = validateInput($data, [
                'plate_number' => ['required' => true, 'max' => 20],
                'vehicle_type' => ['required' => true],
                'owner_type' => ['required' => true]
            ]);
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if vehicle exists
            $checkQuery = "SELECT id FROM vehicles WHERE id = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$vehicleId]);
            
            if (!$checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Vehicle not found'];
            }
            
            // Check for duplicate plate number (excluding current vehicle)
            $duplicateQuery = "SELECT id FROM vehicles WHERE plate_number = ? AND id != ? AND is_active = 1";
            $duplicateStmt = $this->db->prepare($duplicateQuery);
            $duplicateStmt->execute([strtoupper($data['plate_number']), $vehicleId]);
            
            if ($duplicateStmt->fetch()) {
                return ['success' => false, 'message' => 'Another vehicle with this plate number already exists'];
            }
            
            $query = "UPDATE vehicles SET 
                        plate_number = ?, vehicle_type = ?, make = ?, model = ?, color = ?,
                        owner_type = ?, owner_id = ?, driver_name = ?, driver_phone = ?, driver_license = ?,
                        updated_at = NOW()
                     WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                strtoupper($data['plate_number']),
                $data['vehicle_type'],
                $data['make'] ?? null,
                $data['model'] ?? null,
                $data['color'] ?? null,
                $data['owner_type'],
                $data['owner_id'] ?? null,
                $data['driver_name'] ?? null,
                $data['driver_phone'] ?? null,
                $data['driver_license'] ?? null,
                $vehicleId
            ]);
            
            if ($result) {
                logActivity(null, 'VEHICLE_UPDATED', "Vehicle updated: {$data['plate_number']} (ID: {$vehicleId})");
                return ['success' => true, 'message' => 'Vehicle updated successfully'];
            } else {
                throw new Exception('Failed to update vehicle');
            }
            
        } catch (Exception $e) {
            error_log("Update vehicle error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update vehicle'];
        }
    }
    
    public function deleteVehicle($vehicleId) {
        try {
            // Check if vehicle has active visits
            $activeVisitsQuery = "SELECT COUNT(*) as count FROM visits WHERE vehicle_id = ? AND status = 'Checked In'";
            $activeStmt = $this->db->prepare($activeVisitsQuery);
            $activeStmt->execute([$vehicleId]);
            $activeCount = $activeStmt->fetch()['count'];
            
            if ($activeCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete vehicle with active visits'];
            }
            
            // Get vehicle info for logging
            $vehicleQuery = "SELECT plate_number FROM vehicles WHERE id = ?";
            $vehicleStmt = $this->db->prepare($vehicleQuery);
            $vehicleStmt->execute([$vehicleId]);
            $vehicle = $vehicleStmt->fetch();
            
            if (!$vehicle) {
                return ['success' => false, 'message' => 'Vehicle not found'];
            }
            
            // Soft delete vehicle
            $query = "UPDATE vehicles SET is_active = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$vehicleId]);
            
            if ($result) {
                logActivity(null, 'VEHICLE_DELETED', "Vehicle deleted: {$vehicle['plate_number']} (ID: {$vehicleId})");
                return ['success' => true, 'message' => 'Vehicle deleted successfully'];
            } else {
                throw new Exception('Failed to delete vehicle');
            }
            
        } catch (Exception $e) {
            error_log("Delete vehicle error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete vehicle'];
        }
    }
    
    public function getVehicleByPlateNumber($plateNumber) {
        try {
            $query = "SELECT veh.*, 
                            CASE 
                                WHEN veh.owner_type = 'Visitor' THEN v.full_name
                                WHEN veh.owner_type = 'Staff' THEN u.full_name
                                ELSE 'Company Vehicle'
                            END as owner_name
                     FROM vehicles veh
                     LEFT JOIN visitors v ON veh.owner_type = 'Visitor' AND veh.owner_id = v.id
                     LEFT JOIN users u ON veh.owner_type = 'Staff' AND veh.owner_id = u.id
                     WHERE veh.plate_number = ? AND veh.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([strtoupper($plateNumber)]);
            $vehicle = $stmt->fetch();
            
            return $vehicle ? ['success' => true, 'data' => $vehicle] : ['success' => false, 'message' => 'Vehicle not found'];
            
        } catch (Exception $e) {
            error_log("Get vehicle by plate error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to find vehicle'];
        }
    }
    
    public function getActiveVehiclesCount() {
        try {
            $query = "SELECT COUNT(*) as count FROM vehicles WHERE is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] ?? 0;
            
        } catch (Exception $e) {
            error_log("Get active vehicles count error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getVehiclesReport($dateFrom, $dateTo) {
        try {
            $query = "SELECT 
                        DATE(veh.created_at) as date,
                        COUNT(*) as new_vehicles,
                        veh.vehicle_type,
                        COUNT(CASE WHEN veh.vehicle_type IS NOT NULL THEN 1 END) as type_count,
                        veh.owner_type,
                        COUNT(CASE WHEN veh.owner_type IS NOT NULL THEN 1 END) as owner_type_count
                     FROM vehicles veh
                     WHERE DATE(veh.created_at) BETWEEN ? AND ? AND veh.is_active = 1
                     GROUP BY DATE(veh.created_at), veh.vehicle_type, veh.owner_type
                     ORDER BY date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll();
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Vehicles report error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getVehicleTypes() {
        try {
            $types = ['Car', 'Motorcycle', 'Van', 'Truck', 'Bus', 'Other'];
            return ['success' => true, 'data' => $types];
            
        } catch (Exception $e) {
            error_log("Get vehicle types error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch vehicle types'];
        }
    }
    
    public function searchVehicles($searchTerm, $limit = 10) {
        try {
            $query = "SELECT veh.id, veh.plate_number, veh.vehicle_type, veh.make, veh.model, veh.color,
                            CASE 
                                WHEN veh.owner_type = 'Visitor' THEN v.full_name
                                WHEN veh.owner_type = 'Staff' THEN u.full_name
                                ELSE 'Company Vehicle'
                            END as owner_name
                     FROM vehicles veh
                     LEFT JOIN visitors v ON veh.owner_type = 'Visitor' AND veh.owner_id = v.id
                     LEFT JOIN users u ON veh.owner_type = 'Staff' AND veh.owner_id = u.id
                     WHERE (veh.plate_number LIKE ? OR veh.make LIKE ? OR veh.model LIKE ?) 
                     AND veh.is_active = 1
                     ORDER BY veh.plate_number ASC 
                     LIMIT ?";
            
            $searchTerm = "%{$searchTerm}%";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
            $vehicles = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $vehicles];
            
        } catch (Exception $e) {
            error_log("Search vehicles error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Search failed'];
        }
    }
    
    public function getVehicleStats() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_vehicles,
                        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_vehicles,
                        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_vehicles,
                        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
                        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week,
                        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
                     FROM vehicles";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            // Get type breakdown
            $typeQuery = "SELECT vehicle_type, COUNT(*) as count
                         FROM vehicles 
                         WHERE is_active = 1
                         GROUP BY vehicle_type
                         ORDER BY count DESC";
            
            $typeStmt = $this->db->prepare($typeQuery);
            $typeStmt->execute();
            $types = $typeStmt->fetchAll();
            
            // Get owner type breakdown
            $ownerQuery = "SELECT owner_type, COUNT(*) as count
                          FROM vehicles 
                          WHERE is_active = 1
                          GROUP BY owner_type
                          ORDER BY count DESC";
            
            $ownerStmt = $this->db->prepare($ownerQuery);
            $ownerStmt->execute();
            $ownerTypes = $ownerStmt->fetchAll();
            
            $stats['types'] = $types;
            $stats['owner_types'] = $ownerTypes;
            
            return ['success' => true, 'data' => $stats];
            
        } catch (Exception $e) {
            error_log("Get vehicle stats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch vehicle statistics'];
        }
    }
    
    public function assignVehicleToVisitor($vehicleId, $visitorId) {
        try {
            $query = "UPDATE vehicles SET owner_type = 'Visitor', owner_id = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$visitorId, $vehicleId]);
            
            if ($result) {
                logActivity(null, 'VEHICLE_ASSIGNED', "Vehicle assigned to visitor (Vehicle ID: {$vehicleId}, Visitor ID: {$visitorId})");
                return ['success' => true, 'message' => 'Vehicle assigned successfully'];
            } else {
                throw new Exception('Failed to assign vehicle');
            }
            
        } catch (Exception $e) {
            error_log("Assign vehicle error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to assign vehicle'];
        }
    }
}
?>