<?php
/**
 * Report Manager Class
 * GatePass Pro - Smart Gate Management System
 */

class ReportManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function generateVisitorReport($filters = []) {
        try {
            $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $filters['date_to'] ?? date('Y-m-d');
            $category = $filters['category'] ?? '';
            $format = $filters['format'] ?? 'json';
            
            $whereConditions = ["DATE(v.created_at) BETWEEN ? AND ?"];
            $params = [$dateFrom, $dateTo];
            
            if (!empty($category)) {
                $whereConditions[] = "vc.category_name = ?";
                $params[] = $category;
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "SELECT 
                        v.id,
                        v.visitor_code,
                        v.full_name,
                        v.email,
                        v.phone,
                        v.company,
                        vc.category_name,
                        v.created_at,
                        COUNT(vis.id) as total_visits,
                        MAX(vis.check_in_time) as last_visit,
                        AVG(vis.rating) as avg_rating,
                        SUM(CASE WHEN vis.status = 'Checked In' THEN 1 ELSE 0 END) as active_visits
                     FROM visitors v
                     LEFT JOIN visitor_categories vc ON v.category_id = vc.id
                     LEFT JOIN visits vis ON v.id = vis.visitor_id
                     {$whereClause}
                     GROUP BY v.id
                     ORDER BY v.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            $report = [
                'title' => 'Visitor Report',
                'date_range' => "{$dateFrom} to {$dateTo}",
                'generated_at' => date('Y-m-d H:i:s'),
                'total_records' => count($data),
                'data' => $data
            ];
            
            if ($format === 'csv') {
                return $this->exportToCSV($report, 'visitor_report');
            } elseif ($format === 'excel') {
                return $this->exportToExcel($report, 'visitor_report');
            } elseif ($format === 'pdf') {
                return $this->exportToPDF($report, 'visitor_report');
            }
            
            return ['success' => true, 'data' => $report];
            
        } catch (Exception $e) {
            error_log("Generate visitor report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate visitor report'];
        }
    }
    
    public function generateVisitReport($filters = []) {
        try {
            $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $filters['date_to'] ?? date('Y-m-d');
            $status = $filters['status'] ?? '';
            $format = $filters['format'] ?? 'json';
            
            $whereConditions = ["DATE(vis.created_at) BETWEEN ? AND ?"];
            $params = [$dateFrom, $dateTo];
            
            if (!empty($status)) {
                $whereConditions[] = "vis.status = ?";
                $params[] = $status;
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "SELECT 
                        vis.id,
                        vis.visit_code,
                        vis.badge_number,
                        v.full_name as visitor_name,
                        v.phone as visitor_phone,
                        v.company as visitor_company,
                        vis.host_name,
                        vis.host_department,
                        vis.purpose,
                        vis.check_in_time,
                        vis.check_out_time,
                        vis.status,
                        vis.rating,
                        vis.feedback,
                        TIMESTAMPDIFF(MINUTE, vis.check_in_time, COALESCE(vis.check_out_time, NOW())) as duration_minutes,
                        veh.plate_number,
                        veh.vehicle_type,
                        u1.full_name as checked_in_by,
                        u2.full_name as checked_out_by
                     FROM visits vis
                     LEFT JOIN visitors v ON vis.visitor_id = v.id
                     LEFT JOIN vehicles veh ON vis.vehicle_id = veh.id
                     LEFT JOIN users u1 ON vis.check_in_by = u1.id
                     LEFT JOIN users u2 ON vis.check_out_by = u2.id
                     {$whereClause}
                     ORDER BY vis.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Calculate statistics
            $totalVisits = count($data);
            $checkedIn = count(array_filter($data, function($visit) { return $visit['status'] === 'Checked In'; }));
            $checkedOut = count(array_filter($data, function($visit) { return $visit['status'] === 'Checked Out'; }));
            $avgDuration = array_sum(array_column($data, 'duration_minutes')) / max($totalVisits, 1);
            $avgRating = array_sum(array_filter(array_column($data, 'rating'))) / max(count(array_filter(array_column($data, 'rating'))), 1);
            
            $report = [
                'title' => 'Visit Report',
                'date_range' => "{$dateFrom} to {$dateTo}",
                'generated_at' => date('Y-m-d H:i:s'),
                'statistics' => [
                    'total_visits' => $totalVisits,
                    'checked_in' => $checkedIn,
                    'checked_out' => $checkedOut,
                    'avg_duration_minutes' => round($avgDuration, 2),
                    'avg_rating' => round($avgRating, 2)
                ],
                'data' => $data
            ];
            
            if ($format === 'csv') {
                return $this->exportToCSV($report, 'visit_report');
            } elseif ($format === 'excel') {
                return $this->exportToExcel($report, 'visit_report');
            } elseif ($format === 'pdf') {
                return $this->exportToPDF($report, 'visit_report');
            }
            
            return ['success' => true, 'data' => $report];
            
        } catch (Exception $e) {
            error_log("Generate visit report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate visit report'];
        }
    }
    
    public function generateVehicleReport($filters = []) {
        try {
            $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $filters['date_to'] ?? date('Y-m-d');
            $vehicleType = $filters['vehicle_type'] ?? '';
            $format = $filters['format'] ?? 'json';
            
            $whereConditions = ["DATE(veh.created_at) BETWEEN ? AND ?"];
            $params = [$dateFrom, $dateTo];
            
            if (!empty($vehicleType)) {
                $whereConditions[] = "veh.vehicle_type = ?";
                $params[] = $vehicleType;
            }
            
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
            
            $query = "SELECT 
                        veh.id,
                        veh.plate_number,
                        veh.vehicle_type,
                        veh.make,
                        veh.model,
                        veh.color,
                        veh.owner_type,
                        CASE 
                            WHEN veh.owner_type = 'Visitor' THEN v.full_name
                            WHEN veh.owner_type = 'Staff' THEN u.full_name
                            ELSE 'Company Vehicle'
                        END as owner_name,
                        veh.driver_name,
                        veh.created_at,
                        COUNT(vis.id) as total_visits,
                        MAX(vis.check_in_time) as last_visit,
                        SUM(CASE WHEN vis.status = 'Checked In' THEN 1 ELSE 0 END) as active_visits
                     FROM vehicles veh
                     LEFT JOIN visitors v ON veh.owner_type = 'Visitor' AND veh.owner_id = v.id
                     LEFT JOIN users u ON veh.owner_type = 'Staff' AND veh.owner_id = u.id
                     LEFT JOIN visits vis ON veh.id = vis.vehicle_id
                     {$whereClause}
                     GROUP BY veh.id
                     ORDER BY veh.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            $report = [
                'title' => 'Vehicle Report',
                'date_range' => "{$dateFrom} to {$dateTo}",
                'generated_at' => date('Y-m-d H:i:s'),
                'total_records' => count($data),
                'data' => $data
            ];
            
            if ($format === 'csv') {
                return $this->exportToCSV($report, 'vehicle_report');
            } elseif ($format === 'excel') {
                return $this->exportToExcel($report, 'vehicle_report');
            } elseif ($format === 'pdf') {
                return $this->exportToPDF($report, 'vehicle_report');
            }
            
            return ['success' => true, 'data' => $report];
            
        } catch (Exception $e) {
            error_log("Generate vehicle report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate vehicle report'];
        }
    }
    
    public function generateSecurityReport($filters = []) {
        try {
            $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $filters['date_to'] ?? date('Y-m-d');
            $format = $filters['format'] ?? 'json';
            
            // Security incidents and alerts
            $securityQuery = "SELECT 
                                al.id,
                                al.user_id,
                                al.action,
                                al.description,
                                al.ip_address,
                                al.created_at,
                                u.full_name as user_name
                             FROM activity_logs al
                             LEFT JOIN users u ON al.user_id = u.id
                             WHERE DATE(al.created_at) BETWEEN ? AND ?
                             AND al.action IN ('LOGIN_FAILED', 'VISITOR_BLACKLISTED', 'UNAUTHORIZED_ACCESS', 'SECURITY_ALERT')
                             ORDER BY al.created_at DESC";
            
            $securityStmt = $this->db->prepare($securityQuery);
            $securityStmt->execute([$dateFrom, $dateTo]);
            $securityData = $securityStmt->fetchAll();
            
            // Blacklisted visitors
            $blacklistQuery = "SELECT 
                                 v.id,
                                 v.full_name,
                                 v.phone,
                                 v.company,
                                 v.blacklist_reason,
                                 v.updated_at as blacklisted_at
                              FROM visitors v
                              WHERE v.is_blacklisted = 1
                              AND DATE(v.updated_at) BETWEEN ? AND ?
                              ORDER BY v.updated_at DESC";
            
            $blacklistStmt = $this->db->prepare($blacklistQuery);
            $blacklistStmt->execute([$dateFrom, $dateTo]);
            $blacklistData = $blacklistStmt->fetchAll();
            
            // Failed login attempts
            $loginAttemptsQuery = "SELECT 
                                     al.ip_address,
                                     COUNT(*) as failed_attempts,
                                     MAX(al.created_at) as last_attempt
                                  FROM activity_logs al
                                  WHERE al.action = 'LOGIN_FAILED'
                                  AND DATE(al.created_at) BETWEEN ? AND ?
                                  GROUP BY al.ip_address
                                  ORDER BY failed_attempts DESC";
            
            $loginStmt = $this->db->prepare($loginAttemptsQuery);
            $loginStmt->execute([$dateFrom, $dateTo]);
            $loginData = $loginStmt->fetchAll();
            
            $report = [
                'title' => 'Security Report',
                'date_range' => "{$dateFrom} to {$dateTo}",
                'generated_at' => date('Y-m-d H:i:s'),
                'security_incidents' => $securityData,
                'blacklisted_visitors' => $blacklistData,
                'failed_login_attempts' => $loginData,
                'statistics' => [
                    'total_incidents' => count($securityData),
                    'new_blacklisted' => count($blacklistData),
                    'failed_logins' => array_sum(array_column($loginData, 'failed_attempts'))
                ]
            ];
            
            if ($format === 'csv') {
                return $this->exportToCSV($report, 'security_report');
            } elseif ($format === 'excel') {
                return $this->exportToExcel($report, 'security_report');
            } elseif ($format === 'pdf') {
                return $this->exportToPDF($report, 'security_report');
            }
            
            return ['success' => true, 'data' => $report];
            
        } catch (Exception $e) {
            error_log("Generate security report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate security report'];
        }
    }
    
    public function generateDashboardReport($filters = []) {
        try {
            $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $dateTo = $filters['date_to'] ?? date('Y-m-d');
            
            // Daily statistics
            $dailyStatsQuery = "SELECT 
                                  DATE(vis.check_in_time) as date,
                                  COUNT(*) as total_visits,
                                  COUNT(DISTINCT vis.visitor_id) as unique_visitors,
                                  COUNT(DISTINCT vis.vehicle_id) as vehicles,
                                  AVG(TIMESTAMPDIFF(MINUTE, vis.check_in_time, vis.check_out_time)) as avg_duration,
                                  AVG(vis.rating) as avg_rating
                               FROM visits vis
                               WHERE DATE(vis.check_in_time) BETWEEN ? AND ?
                               GROUP BY DATE(vis.check_in_time)
                               ORDER BY date ASC";
            
            $dailyStmt = $this->db->prepare($dailyStatsQuery);
            $dailyStmt->execute([$dateFrom, $dateTo]);
            $dailyStats = $dailyStmt->fetchAll();
            
            // Hourly distribution
            $hourlyQuery = "SELECT 
                              HOUR(vis.check_in_time) as hour,
                              COUNT(*) as visits
                           FROM visits vis
                           WHERE DATE(vis.check_in_time) BETWEEN ? AND ?
                           GROUP BY HOUR(vis.check_in_time)
                           ORDER BY hour ASC";
            
            $hourlyStmt = $this->db->prepare($hourlyQuery);
            $hourlyStmt->execute([$dateFrom, $dateTo]);
            $hourlyStats = $hourlyStmt->fetchAll();
            
            // Top visitors
            $topVisitorsQuery = "SELECT 
                                   v.full_name,
                                   v.company,
                                   COUNT(vis.id) as visit_count,
                                   AVG(vis.rating) as avg_rating
                                FROM visitors v
                                JOIN visits vis ON v.id = vis.visitor_id
                                WHERE DATE(vis.check_in_time) BETWEEN ? AND ?
                                GROUP BY v.id
                                ORDER BY visit_count DESC
                                LIMIT 10";
            
            $topVisitorsStmt = $this->db->prepare($topVisitorsQuery);
            $topVisitorsStmt->execute([$dateFrom, $dateTo]);
            $topVisitors = $topVisitorsStmt->fetchAll();
            
            // Category breakdown
            $categoryQuery = "SELECT 
                                vc.category_name,
                                COUNT(vis.id) as visits,
                                COUNT(DISTINCT vis.visitor_id) as unique_visitors
                             FROM visits vis
                             JOIN visitors v ON vis.visitor_id = v.id
                             LEFT JOIN visitor_categories vc ON v.category_id = vc.id
                             WHERE DATE(vis.check_in_time) BETWEEN ? AND ?
                             GROUP BY vc.category_name
                             ORDER BY visits DESC";
            
            $categoryStmt = $this->db->prepare($categoryQuery);
            $categoryStmt->execute([$dateFrom, $dateTo]);
            $categoryStats = $categoryStmt->fetchAll();
            
            $report = [
                'title' => 'Dashboard Analytics Report',
                'date_range' => "{$dateFrom} to {$dateTo}",
                'generated_at' => date('Y-m-d H:i:s'),
                'daily_statistics' => $dailyStats,
                'hourly_distribution' => $hourlyStats,
                'top_visitors' => $topVisitors,
                'category_breakdown' => $categoryStats,
                'summary' => [
                    'total_visits' => array_sum(array_column($dailyStats, 'total_visits')),
                    'unique_visitors' => array_sum(array_column($dailyStats, 'unique_visitors')),
                    'avg_daily_visits' => count($dailyStats) > 0 ? array_sum(array_column($dailyStats, 'total_visits')) / count($dailyStats) : 0,
                    'peak_hour' => count($hourlyStats) > 0 ? $hourlyStats[array_search(max(array_column($hourlyStats, 'visits')), array_column($hourlyStats, 'visits'))]['hour'] : null
                ]
            ];
            
            return ['success' => true, 'data' => $report];
            
        } catch (Exception $e) {
            error_log("Generate dashboard report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate dashboard report'];
        }
    }
    
    private function exportToCSV($report, $filename) {
        try {
            $filepath = EXPORTS_PATH . $filename . '_' . date('Y-m-d_H-i-s') . '.csv';
            
            if (!is_dir(EXPORTS_PATH)) {
                mkdir(EXPORTS_PATH, 0755, true);
            }
            
            $file = fopen($filepath, 'w');
            
            // Write UTF-8 BOM
            fwrite($file, "\xEF\xBB\xBF");
            
            // Write header
            fputcsv($file, ['Report: ' . $report['title']]);
            fputcsv($file, ['Date Range: ' . $report['date_range']]);
            fputcsv($file, ['Generated: ' . $report['generated_at']]);
            fputcsv($file, []); // Empty row
            
            // Write data
            if (!empty($report['data'])) {
                $headers = array_keys($report['data'][0]);
                fputcsv($file, $headers);
                
                foreach ($report['data'] as $row) {
                    fputcsv($file, $row);
                }
            }
            
            fclose($file);
            
            return [
                'success' => true,
                'file_path' => $filepath,
                'download_url' => str_replace(ROOT_PATH, BASE_URL, $filepath)
            ];
            
        } catch (Exception $e) {
            error_log("CSV export error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to export to CSV'];
        }
    }
    
    private function exportToExcel($report, $filename) {
        // Note: This is a simplified implementation
        // For full Excel support, consider using PhpSpreadsheet library
        
        try {
            $filepath = EXPORTS_PATH . $filename . '_' . date('Y-m-d_H-i-s') . '.xls';
            
            if (!is_dir(EXPORTS_PATH)) {
                mkdir(EXPORTS_PATH, 0755, true);
            }
            
            $file = fopen($filepath, 'w');
            
            // Basic Excel XML format
            fwrite($file, "<?xml version=\"1.0\"?>\n");
            fwrite($file, "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\">\n");
            fwrite($file, "<Worksheet ss:Name=\"Report\">\n");
            fwrite($file, "<Table>\n");
            
            // Header rows
            fwrite($file, "<Row><Cell><Data ss:Type=\"String\">{$report['title']}</Data></Cell></Row>\n");
            fwrite($file, "<Row><Cell><Data ss:Type=\"String\">Date Range: {$report['date_range']}</Data></Cell></Row>\n");
            fwrite($file, "<Row><Cell><Data ss:Type=\"String\">Generated: {$report['generated_at']}</Data></Cell></Row>\n");
            fwrite($file, "<Row></Row>\n");
            
            // Data
            if (!empty($report['data'])) {
                $headers = array_keys($report['data'][0]);
                fwrite($file, "<Row>");
                foreach ($headers as $header) {
                    fwrite($file, "<Cell><Data ss:Type=\"String\">" . htmlspecialchars($header) . "</Data></Cell>");
                }
                fwrite($file, "</Row>\n");
                
                foreach ($report['data'] as $row) {
                    fwrite($file, "<Row>");
                    foreach ($row as $cell) {
                        $type = is_numeric($cell) ? 'Number' : 'String';
                        fwrite($file, "<Cell><Data ss:Type=\"{$type}\">" . htmlspecialchars($cell) . "</Data></Cell>");
                    }
                    fwrite($file, "</Row>\n");
                }
            }
            
            fwrite($file, "</Table>\n");
            fwrite($file, "</Worksheet>\n");
            fwrite($file, "</Workbook>\n");
            
            fclose($file);
            
            return [
                'success' => true,
                'file_path' => $filepath,
                'download_url' => str_replace(ROOT_PATH, BASE_URL, $filepath)
            ];
            
        } catch (Exception $e) {
            error_log("Excel export error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to export to Excel'];
        }
    }
    
    private function exportToPDF($report, $filename) {
        // Note: This is a simplified implementation
        // For full PDF support, consider using TCPDF or similar library
        
        try {
            $filepath = EXPORTS_PATH . $filename . '_' . date('Y-m-d_H-i-s') . '.html';
            
            if (!is_dir(EXPORTS_PATH)) {
                mkdir(EXPORTS_PATH, 0755, true);
            }
            
            $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>{$report['title']}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .header { margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; }
        .meta { color: #666; margin: 5px 0; }
    </style>
</head>
<body>
    <div class='header'>
        <div class='title'>{$report['title']}</div>
        <div class='meta'>Date Range: {$report['date_range']}</div>
        <div class='meta'>Generated: {$report['generated_at']}</div>
    </div>";
            
            if (!empty($report['data'])) {
                $html .= "<table>";
                $headers = array_keys($report['data'][0]);
                $html .= "<tr>";
                foreach ($headers as $header) {
                    $html .= "<th>" . htmlspecialchars($header) . "</th>";
                }
                $html .= "</tr>";
                
                foreach ($report['data'] as $row) {
                    $html .= "<tr>";
                    foreach ($row as $cell) {
                        $html .= "<td>" . htmlspecialchars($cell) . "</td>";
                    }
                    $html .= "</tr>";
                }
                $html .= "</table>";
            }
            
            $html .= "</body></html>";
            
            file_put_contents($filepath, $html);
            
            return [
                'success' => true,
                'file_path' => $filepath,
                'download_url' => str_replace(ROOT_PATH, BASE_URL, $filepath),
                'note' => 'HTML format generated. Use browser print-to-PDF for PDF conversion.'
            ];
            
        } catch (Exception $e) {
            error_log("PDF export error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to export to PDF'];
        }
    }
    
    public function getReportHistory($userId = null) {
        try {
            $whereClause = $userId ? "WHERE generated_by = ?" : "";
            $params = $userId ? [$userId] : [];
            
            $query = "SELECT r.*, u.full_name as generated_by_name
                     FROM reports r
                     LEFT JOIN users u ON r.generated_by = u.id
                     {$whereClause}
                     ORDER BY r.created_at DESC
                     LIMIT 50";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $reports = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $reports];
            
        } catch (Exception $e) {
            error_log("Get report history error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch report history'];
        }
    }
    
    public function deleteOldReports($daysBefore = 30) {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-{$daysBefore} days"));
            
            // Get file paths before deletion
            $query = "SELECT file_path FROM reports WHERE DATE(created_at) < ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$cutoffDate]);
            $reports = $stmt->fetchAll();
            
            // Delete files
            foreach ($reports as $report) {
                if (!empty($report['file_path']) && file_exists($report['file_path'])) {
                    unlink($report['file_path']);
                }
            }
            
            // Delete database records
            $deleteQuery = "DELETE FROM reports WHERE DATE(created_at) < ?";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $deleteStmt->execute([$cutoffDate]);
            
            $deletedCount = $deleteStmt->rowCount();
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Deleted {$deletedCount} old reports"
            ];
            
        } catch (Exception $e) {
            error_log("Delete old reports error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete old reports'];
        }
    }
}