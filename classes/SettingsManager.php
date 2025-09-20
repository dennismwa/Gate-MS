<?php
/**
 * Settings Manager Class
 * GatePass Pro - Smart Gate Management System
 */

class SettingsManager {
    private $db;
    private $cache = [];
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->loadSettings();
    }
    
    private function loadSettings() {
        try {
            $query = "SELECT setting_key, setting_value FROM system_settings";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            while ($row = $stmt->fetch()) {
                $this->cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Load settings error: " . $e->getMessage());
        }
    }
    
    public function get($key, $default = null) {
        return $this->cache[$key] ?? $default;
    }
    
    public function set($key, $value) {
        try {
            $query = "INSERT INTO system_settings (setting_key, setting_value) 
                     VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$key, $value, $value]);
            
            if ($result) {
                $this->cache[$key] = $value;
                return ['success' => true];
            }
            
            return ['success' => false, 'message' => 'Failed to update setting'];
            
        } catch (Exception $e) {
            error_log("Set setting error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update setting'];
        }
    }
    
    public function setBulk($settings) {
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $query = "INSERT INTO system_settings (setting_key, setting_value) 
                         VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
                
                $stmt = $this->db->prepare($query);
                $stmt->execute([$key, $value, $value]);
                
                $this->cache[$key] = $value;
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Settings updated successfully'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Bulk set settings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update settings'];
        }
    }
    
    public function getAll() {
        return $this->cache;
    }
    
    public function getByCategory($category) {
        $categorySettings = [];
        foreach ($this->cache as $key => $value) {
            if (strpos($key, $category . '_') === 0) {
                $categorySettings[$key] = $value;
            }
        }
        return $categorySettings;
    }
    
    public function delete($key) {
        try {
            $query = "DELETE FROM system_settings WHERE setting_key = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$key]);
            
            if ($result) {
                unset($this->cache[$key]);
                return ['success' => true];
            }
            
            return ['success' => false, 'message' => 'Failed to delete setting'];
            
        } catch (Exception $e) {
            error_log("Delete setting error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete setting'];
        }
    }
    
    public function getDefaultSettings() {
        return [
            // General Settings
            'site_name' => 'GatePass Pro',
            'company_name' => 'Your Company',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_website' => '',
            'timezone' => 'Africa/Nairobi',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            
            // Theme Settings
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1F2937',
            'accent_color' => '#10B981',
            'logo_url' => '',
            'favicon_url' => '',
            'theme_mode' => 'light',
            
            // Security Settings
            'max_login_attempts' => '5',
            'login_lockout_time' => '900',
            'session_lifetime' => '3600',
            'password_min_length' => '8',
            'require_2fa' => '0',
            'auto_logout_inactive' => '1',
            
            // Email Settings
            'email_notifications' => '1',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'mail_from_name' => 'GatePass Pro',
            'mail_from_address' => '',
            
            // Visit Settings
            'default_visit_duration' => '2',
            'max_visit_duration' => '24',
            'require_host_approval' => '0',
            'auto_expire_visits' => '1',
            'visitor_photo_required' => '0',
            'temperature_check_required' => '0',
            'health_declaration_required' => '1',
            
            // QR Code Settings
            'qr_code_size' => '300',
            'qr_code_format' => 'PNG',
            'qr_code_error_correction' => 'M',
            'qr_code_expiry_hours' => '24',
            
            // Badge Settings
            'badge_template' => 'default',
            'badge_include_photo' => '1',
            'badge_include_qr' => '1',
            'badge_include_company_logo' => '1',
            'auto_print_badges' => '0',
            
            // File Upload Settings
            'max_file_size' => '5242880', // 5MB
            'allowed_image_types' => 'jpg,jpeg,png,gif',
            'allowed_document_types' => 'pdf,doc,docx',
            'visitor_photo_required' => '0',
            'vehicle_photo_required' => '0',
            
            // Backup Settings
            'auto_backup' => '1',
            'backup_frequency' => 'daily',
            'backup_retention_days' => '30',
            'backup_email' => '',
            
            // Integration Settings
            'slack_webhook_url' => '',
            'google_maps_api_key' => '',
            'sms_provider' => '',
            'sms_api_key' => '',
            
            // Analytics Settings
            'analytics_enabled' => '1',
            'google_analytics_id' => '',
            'track_visitor_location' => '0',
            'anonymize_reports' => '0',
            
            // Mobile App Settings
            'mobile_app_enabled' => '1',
            'force_app_update' => '0',
            'offline_mode_enabled' => '1',
            'camera_quality' => 'high',
            
            // Maintenance Settings
            'maintenance_mode' => '0',
            'maintenance_message' => 'System is under maintenance. Please try again later.',
            'maintenance_allowed_ips' => '127.0.0.1',
            'auto_cleanup_enabled' => '1'
        ];
    }
    
    public function resetToDefaults() {
        try {
            $defaults = $this->getDefaultSettings();
            return $this->setBulk($defaults);
            
        } catch (Exception $e) {
            error_log("Reset to defaults error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reset to default settings'];
        }
    }
    
    public function exportSettings() {
        try {
            $settings = $this->getAll();
            $export = [
                'exported_at' => date('Y-m-d H:i:s'),
                'version' => APP_VERSION ?? '1.0.0',
                'settings' => $settings
            ];
            
            $filename = 'settings_export_' . date('Y-m-d_H-i-s') . '.json';
            $filepath = EXPORTS_PATH . $filename;
            
            if (!is_dir(EXPORTS_PATH)) {
                mkdir(EXPORTS_PATH, 0755, true);
            }
            
            file_put_contents($filepath, json_encode($export, JSON_PRETTY_PRINT));
            
            return [
                'success' => true,
                'file_path' => $filepath,
                'download_url' => str_replace(ROOT_PATH, BASE_URL, $filepath)
            ];
            
        } catch (Exception $e) {
            error_log("Export settings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to export settings'];
        }
    }
    
    public function importSettings($filepath) {
        try {
            if (!file_exists($filepath)) {
                return ['success' => false, 'message' => 'Import file not found'];
            }
            
            $content = file_get_contents($filepath);
            $import = json_decode($content, true);
            
            if (!$import || !isset($import['settings'])) {
                return ['success' => false, 'message' => 'Invalid import file format'];
            }
            
            $result = $this->setBulk($import['settings']);
            
            if ($result['success']) {
                logActivity(null, 'SETTINGS_IMPORTED', 'Settings imported from file: ' . basename($filepath));
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Import settings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to import settings'];
        }
    }
    
    public function validateSettings($settings) {
        $errors = [];
        
        // Validate email settings
        if (isset($settings['smtp_host']) && !empty($settings['smtp_host'])) {
            if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
                $errors[] = 'SMTP username and password are required when SMTP host is set';
            }
            
            if (!in_array($settings['smtp_port'] ?? '', ['25', '465', '587', '2525'])) {
                $errors[] = 'Invalid SMTP port';
            }
        }
        
        // Validate colors
        $colorFields = ['primary_color', 'secondary_color', 'accent_color'];
        foreach ($colorFields as $field) {
            if (isset($settings[$field]) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $settings[$field])) {
                $errors[] = "Invalid color format for {$field}";
            }
        }
        
        // Validate numeric fields
        $numericFields = [
            'max_login_attempts' => [1, 10],
            'login_lockout_time' => [60, 3600],
            'session_lifetime' => [300, 86400],
            'password_min_length' => [6, 50],
            'default_visit_duration' => [1, 24],
            'max_visit_duration' => [1, 168],
            'qr_code_size' => [100, 1000]
        ];
        
        foreach ($numericFields as $field => $range) {
            if (isset($settings[$field])) {
                $value = intval($settings[$field]);
                if ($value < $range[0] || $value > $range[1]) {
                    $errors[] = "{$field} must be between {$range[0]} and {$range[1]}";
                }
            }
        }
        
        // Validate file size
        if (isset($settings['max_file_size'])) {
            $maxSize = intval($settings['max_file_size']);
            if ($maxSize < 1024 || $maxSize > 50 * 1024 * 1024) { // 1KB to 50MB
                $errors[] = 'Max file size must be between 1KB and 50MB';
            }
        }
        
        return $errors;
    }
    
    public function testEmailSettings($settings = null) {
        try {
            $smtp_host = $settings['smtp_host'] ?? $this->get('smtp_host');
            $smtp_port = $settings['smtp_port'] ?? $this->get('smtp_port');
            $smtp_username = $settings['smtp_username'] ?? $this->get('smtp_username');
            $smtp_password = $settings['smtp_password'] ?? $this->get('smtp_password');
            $mail_from_address = $settings['mail_from_address'] ?? $this->get('mail_from_address');
            
            if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
                return ['success' => false, 'message' => 'SMTP settings not configured'];
            }
            
            $notificationManager = new NotificationManager();
            $result = $notificationManager->testEmailConfiguration();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Test email settings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Email test failed'];
        }
    }
    
    public function getSettingsStructure() {
        return [
            'general' => [
                'title' => 'General Settings',
                'fields' => [
                    'site_name' => ['type' => 'text', 'label' => 'Site Name', 'required' => true],
                    'company_name' => ['type' => 'text', 'label' => 'Company Name', 'required' => true],
                    'company_address' => ['type' => 'textarea', 'label' => 'Company Address'],
                    'company_phone' => ['type' => 'tel', 'label' => 'Company Phone'],
                    'company_email' => ['type' => 'email', 'label' => 'Company Email'],
                    'timezone' => ['type' => 'select', 'label' => 'Timezone', 'options' => $this->getTimezones()]
                ]
            ],
            'appearance' => [
                'title' => 'Appearance',
                'fields' => [
                    'primary_color' => ['type' => 'color', 'label' => 'Primary Color'],
                    'secondary_color' => ['type' => 'color', 'label' => 'Secondary Color'],
                    'accent_color' => ['type' => 'color', 'label' => 'Accent Color'],
                    'theme_mode' => ['type' => 'select', 'label' => 'Theme Mode', 'options' => ['light' => 'Light', 'dark' => 'Dark', 'auto' => 'Auto']]
                ]
            ],
            'security' => [
                'title' => 'Security Settings',
                'fields' => [
                    'max_login_attempts' => ['type' => 'number', 'label' => 'Max Login Attempts', 'min' => 1, 'max' => 10],
                    'login_lockout_time' => ['type' => 'number', 'label' => 'Lockout Time (seconds)', 'min' => 60, 'max' => 3600],
                    'session_lifetime' => ['type' => 'number', 'label' => 'Session Lifetime (seconds)', 'min' => 300, 'max' => 86400],
                    'password_min_length' => ['type' => 'number', 'label' => 'Minimum Password Length', 'min' => 6, 'max' => 50]
                ]
            ],
            'email' => [
                'title' => 'Email Settings',
                'fields' => [
                    'email_notifications' => ['type' => 'checkbox', 'label' => 'Enable Email Notifications'],
                    'smtp_host' => ['type' => 'text', 'label' => 'SMTP Host'],
                    'smtp_port' => ['type' => 'select', 'label' => 'SMTP Port', 'options' => ['25' => '25', '465' => '465', '587' => '587', '2525' => '2525']],
                    'smtp_encryption' => ['type' => 'select', 'label' => 'Encryption', 'options' => ['' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL']],
                    'smtp_username' => ['type' => 'text', 'label' => 'SMTP Username'],
                    'smtp_password' => ['type' => 'password', 'label' => 'SMTP Password'],
                    'mail_from_address' => ['type' => 'email', 'label' => 'From Email Address']
                ]
            ],
            'visits' => [
                'title' => 'Visit Settings',
                'fields' => [
                    'default_visit_duration' => ['type' => 'number', 'label' => 'Default Visit Duration (hours)', 'min' => 1, 'max' => 24],
                    'max_visit_duration' => ['type' => 'number', 'label' => 'Maximum Visit Duration (hours)', 'min' => 1, 'max' => 168],
                    'require_host_approval' => ['type' => 'checkbox', 'label' => 'Require Host Approval'],
                    'visitor_photo_required' => ['type' => 'checkbox', 'label' => 'Visitor Photo Required'],
                    'temperature_check_required' => ['type' => 'checkbox', 'label' => 'Temperature Check Required'],
                    'health_declaration_required' => ['type' => 'checkbox', 'label' => 'Health Declaration Required']
                ]
            ]
        ];
    }
    
    private function getTimezones() {
        return [
            'Africa/Nairobi' => 'Africa/Nairobi (EAT)',
            'UTC' => 'UTC',
            'America/New_York' => 'America/New_York (EST)',
            'Europe/London' => 'Europe/London (GMT)',
            'Asia/Tokyo' => 'Asia/Tokyo (JST)',
            // Add more timezones as needed
        ];
    }
    
    public function createBackup() {
        try {
            $settings = $this->getAll();
            $backup = [
                'created_at' => date('Y-m-d H:i:s'),
                'version' => APP_VERSION ?? '1.0.0',
                'settings' => $settings
            ];
            
            $filename = 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';
            $filepath = BACKUP_PATH . $filename;
            
            if (!is_dir(BACKUP_PATH)) {
                mkdir(BACKUP_PATH, 0755, true);
            }
            
            file_put_contents($filepath, json_encode($backup, JSON_PRETTY_PRINT));
            
            return [
                'success' => true,
                'file_path' => $filepath,
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            error_log("Create settings backup error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create backup'];
        }
    }
    
    public function getSystemInfo() {
        return [
            'php_version' => phpversion(),
            'mysql_version' => $this->getMySQLVersion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'date_default_timezone' => date_default_timezone_get(),
            'disk_free_space' => formatBytes(disk_free_space('.')),
            'app_version' => APP_VERSION ?? '1.0.0'
        ];
    }
    
    private function getMySQLVersion() {
        try {
            $stmt = $this->db->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            return $result['version'] ?? 'Unknown';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
}
?>