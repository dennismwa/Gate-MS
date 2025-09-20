<?php
/**
 * Notification Manager Class
 * GatePass Pro - Smart Gate Management System
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class NotificationManager {
    private $db;
    private $mailer;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->initializeMailer();
    }
    
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;
            
            // Set default from
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
        } catch (Exception $e) {
            error_log("Mailer initialization error: " . $e->getMessage());
        }
    }
    
    public function createNotification($userId, $type, $title, $message, $data = null) {
        try {
            $query = "INSERT INTO notifications (user_id, type, title, message, data) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $data ? json_encode($data) : null
            ]);
            
            if ($result) {
                $notificationId = $this->db->lastInsertId();
                return ['success' => true, 'notification_id' => $notificationId];
            }
            
            return ['success' => false, 'message' => 'Failed to create notification'];
            
        } catch (Exception $e) {
            error_log("Create notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create notification'];
        }
    }
    
    public function getUserNotifications($userId, $unreadOnly = false) {
        try {
            $whereClause = "WHERE user_id = ?";
            $params = [$userId];
            
            if ($unreadOnly) {
                $whereClause .= " AND is_read = 0";
            }
            
            $query = "SELECT * FROM notifications {$whereClause} ORDER BY created_at DESC LIMIT 50";
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll();
            
            return ['success' => true, 'data' => $notifications];
            
        } catch (Exception $e) {
            error_log("Get user notifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch notifications'];
        }
    }
    
    public function markAsRead($notificationId, $userId) {
        try {
            $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$notificationId, $userId]);
            
            return ['success' => $result, 'message' => $result ? 'Marked as read' : 'Failed to mark as read'];
            
        } catch (Exception $e) {
            error_log("Mark notification as read error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to mark as read'];
        }
    }
    
    public function markAllAsRead($userId) {
        try {
            $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$userId]);
            
            return ['success' => $result, 'message' => $result ? 'All notifications marked as read' : 'Failed to mark all as read'];
            
        } catch (Exception $e) {
            error_log("Mark all notifications as read error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to mark all as read'];
        }
    }
    
    public function sendVisitNotification($visitId, $type) {
        try {
            // Get visit details
            $query = "SELECT vis.*, v.full_name as visitor_name, v.email as visitor_email, v.phone as visitor_phone,
                            v.company as visitor_company
                     FROM visits vis
                     LEFT JOIN visitors v ON vis.visitor_id = v.id
                     WHERE vis.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$visitId]);
            $visit = $stmt->fetch();
            
            if (!$visit) {
                return ['success' => false, 'message' => 'Visit not found'];
            }
            
            switch ($type) {
                case 'visit_scheduled':
                    return $this->sendVisitScheduledNotification($visit);
                    
                case 'visitor_checkin':
                    return $this->sendVisitorCheckinNotification($visit);
                    
                case 'visitor_checkout':
                    return $this->sendVisitorCheckoutNotification($visit);
                    
                default:
                    return ['success' => false, 'message' => 'Unknown notification type'];
            }
            
        } catch (Exception $e) {
            error_log("Send visit notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send notification'];
        }
    }
    
    private function sendVisitScheduledNotification($visit) {
        try {
            $subject = "Visit Scheduled - " . SITE_NAME;
            $hostEmail = $visit['host_email'];
            $visitorEmail = $visit['visitor_email'];
            
            // Notification to host
            if ($hostEmail) {
                $hostMessage = $this->getVisitScheduledTemplate($visit, 'host');
                $this->sendEmail($hostEmail, $subject, $hostMessage);
            }
            
            // Notification to visitor
            if ($visitorEmail) {
                $visitorMessage = $this->getVisitScheduledTemplate($visit, 'visitor');
                $this->sendEmail($visitorEmail, $subject, $visitorMessage);
            }
            
            // Create system notification for security staff
            $this->createNotificationForRole('Security', 'visit_scheduled', 'New Visit Scheduled', 
                "New visit scheduled for {$visit['visitor_name']} on {$visit['expected_date']}", 
                ['visit_id' => $visit['id']]);
            
            return ['success' => true, 'message' => 'Visit scheduled notifications sent'];
            
        } catch (Exception $e) {
            error_log("Send visit scheduled notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send visit scheduled notification'];
        }
    }
    
    private function sendVisitorCheckinNotification($visit) {
        try {
            $subject = "Visitor Checked In - " . SITE_NAME;
            $hostEmail = $visit['host_email'];
            
            // Notification to host
            if ($hostEmail) {
                $message = $this->getVisitorCheckinTemplate($visit);
                $this->sendEmail($hostEmail, $subject, $message);
            }
            
            // Create system notification for host
            $this->createNotificationForRole('Admin', 'visitor_checkin', 'Visitor Checked In', 
                "{$visit['visitor_name']} from {$visit['visitor_company']} has checked in", 
                ['visit_id' => $visit['id']]);
            
            return ['success' => true, 'message' => 'Check-in notifications sent'];
            
        } catch (Exception $e) {
            error_log("Send visitor check-in notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send check-in notification'];
        }
    }
    
    private function sendVisitorCheckoutNotification($visit) {
        try {
            $subject = "Visitor Checked Out - " . SITE_NAME;
            $hostEmail = $visit['host_email'];
            
            // Notification to host
            if ($hostEmail) {
                $message = $this->getVisitorCheckoutTemplate($visit);
                $this->sendEmail($hostEmail, $subject, $message);
            }
            
            // Create system notification
            $this->createNotificationForRole('Admin', 'visitor_checkout', 'Visitor Checked Out', 
                "{$visit['visitor_name']} has checked out", 
                ['visit_id' => $visit['id']]);
            
            return ['success' => true, 'message' => 'Check-out notifications sent'];
            
        } catch (Exception $e) {
            error_log("Send visitor check-out notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send check-out notification'];
        }
    }
    
    public function sendPreRegistrationNotification($registrationId) {
        try {
            // Get pre-registration details
            $query = "SELECT * FROM pre_registrations WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch();
            
            if (!$registration || !$registration['visitor_email']) {
                return ['success' => false, 'message' => 'Pre-registration not found or no email'];
            }
            
            $subject = "Pre-registration Confirmation - " . SITE_NAME;
            $message = $this->getPreRegistrationTemplate($registration);
            
            $result = $this->sendEmail($registration['visitor_email'], $subject, $message);
            
            // Create system notification
            $this->createNotificationForRole('Receptionist', 'pre_registration', 'New Pre-registration', 
                "New pre-registration from {$registration['visitor_name']}", 
                ['registration_id' => $registrationId]);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Send pre-registration notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send pre-registration notification'];
        }
    }
    
    public function sendPreRegistrationApproval($registrationId) {
        try {
            // Get pre-registration details
            $query = "SELECT pr.*, u.full_name as approved_by_name 
                     FROM pre_registrations pr
                     LEFT JOIN users u ON pr.approved_by = u.id
                     WHERE pr.id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch();
            
            if (!$registration || !$registration['visitor_email']) {
                return ['success' => false, 'message' => 'Pre-registration not found or no email'];
            }
            
            $subject = "Pre-registration Approved - " . SITE_NAME;
            $message = $this->getPreRegistrationApprovalTemplate($registration);
            
            return $this->sendEmail($registration['visitor_email'], $subject, $message);
            
        } catch (Exception $e) {
            error_log("Send pre-registration approval error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send approval notification'];
        }
    }
    
    public function sendPreRegistrationRejection($registrationId, $reason) {
        try {
            // Get pre-registration details
            $query = "SELECT * FROM pre_registrations WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch();
            
            if (!$registration || !$registration['visitor_email']) {
                return ['success' => false, 'message' => 'Pre-registration not found or no email'];
            }
            
            $subject = "Pre-registration Update - " . SITE_NAME;
            $message = $this->getPreRegistrationRejectionTemplate($registration, $reason);
            
            return $this->sendEmail($registration['visitor_email'], $subject, $message);
            
        } catch (Exception $e) {
            error_log("Send pre-registration rejection error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send rejection notification'];
        }
    }
    
    private function sendEmail($to, $subject, $message) {
        try {
            if (!SMTP_USERNAME || !SMTP_PASSWORD) {
                error_log("SMTP credentials not configured");
                return ['success' => false, 'message' => 'Email not configured'];
            }
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $message;
            
            $this->mailer->send();
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (PHPMailerException $e) {
            error_log("Email sending error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email'];
        }
    }
    
    private function createNotificationForRole($roleName, $type, $title, $message, $data = null) {
        try {
            // Get users with the specified role
            $query = "SELECT u.id FROM users u 
                     LEFT JOIN user_roles ur ON u.role_id = ur.id 
                     WHERE ur.role_name = ? AND u.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$roleName]);
            $users = $stmt->fetchAll();
            
            foreach ($users as $user) {
                $this->createNotification($user['id'], $type, $title, $message, $data);
            }
            
        } catch (Exception $e) {
            error_log("Create notification for role error: " . $e->getMessage());
        }
    }
    
    // Email Templates
    private function getVisitScheduledTemplate($visit, $recipient = 'host') {
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3B82F6; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #3B82F6; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . SITE_NAME . '</h1>
                    <h2>Visit Scheduled</h2>
                </div>
                <div class="content">';
        
        if ($recipient === 'host') {
            $template .= '<p>Dear ' . htmlspecialchars($visit['host_name']) . ',</p>
                         <p>A new visit has been scheduled for you:</p>';
        } else {
            $template .= '<p>Dear ' . htmlspecialchars($visit['visitor_name']) . ',</p>
                         <p>Your visit has been scheduled successfully:</p>';
        }
        
        $template .= '
                    <div class="info-box">
                        <strong>Visitor:</strong> ' . htmlspecialchars($visit['visitor_name']) . '<br>
                        <strong>Company:</strong> ' . htmlspecialchars($visit['visitor_company']) . '<br>
                        <strong>Date:</strong> ' . formatDateTime($visit['expected_date'], 'M j, Y') . '<br>
                        <strong>Time:</strong> ' . formatDateTime($visit['expected_time_in'], 'g:i A') . '<br>
                        <strong>Purpose:</strong> ' . htmlspecialchars($visit['purpose']) . '<br>
                        <strong>Visit Code:</strong> ' . htmlspecialchars($visit['visit_code']) . '
                    </div>
                    
                    <p>Please keep this visit code handy for quick check-in.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    private function getVisitorCheckinTemplate($visit) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #EF4444; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #EF4444; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . SITE_NAME . '</h1>
                    <h2>Visitor Checked Out</h2>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($visit['host_name']) . ',</p>
                    <p>Your visitor has checked out:</p>
                    
                    <div class="info-box">
                        <strong>Visitor:</strong> ' . htmlspecialchars($visit['visitor_name']) . '<br>
                        <strong>Company:</strong> ' . htmlspecialchars($visit['visitor_company']) . '<br>
                        <strong>Check-out Time:</strong> ' . formatDateTime($visit['check_out_time'], 'M j, Y g:i A') . '<br>
                        <strong>Visit Duration:</strong> ' . $duration . '
                    </div>
                    
                    <p>Thank you for hosting this visitor.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function getPreRegistrationTemplate($registration) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #8B5CF6; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #8B5CF6; }
                .qr-code { text-align: center; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . SITE_NAME . '</h1>
                    <h2>Pre-registration Confirmation</h2>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($registration['visitor_name']) . ',</p>
                    <p>Your visit has been pre-registered successfully. Here are the details:</p>
                    
                    <div class="info-box">
                        <strong>Registration Code:</strong> ' . htmlspecialchars($registration['registration_code']) . '<br>
                        <strong>Host:</strong> ' . htmlspecialchars($registration['host_name']) . '<br>
                        <strong>Visit Date:</strong> ' . formatDateTime($registration['visit_date'], 'M j, Y') . '<br>
                        <strong>Visit Time:</strong> ' . formatDateTime($registration['visit_time'], 'g:i A') . '<br>
                        <strong>Purpose:</strong> ' . htmlspecialchars($registration['purpose']) . '<br>
                        <strong>Status:</strong> Pending Approval
                    </div>
                    
                    <p>You will receive another email once your visit is approved. Please keep your registration code ready for check-in.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function getPreRegistrationApprovalTemplate($registration) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #10B981; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #10B981; }
                .approval-box { background: #D1FAE5; padding: 15px; margin: 10px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . SITE_NAME . '</h1>
                    <h2>Visit Approved!</h2>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($registration['visitor_name']) . ',</p>
                    
                    <div class="approval-box">
                        <h3 style="color: #059669; margin: 0;">🎉 Your visit has been approved!</h3>
                    </div>
                    
                    <div class="info-box">
                        <strong>Registration Code:</strong> ' . htmlspecialchars($registration['registration_code']) . '<br>
                        <strong>Host:</strong> ' . htmlspecialchars($registration['host_name']) . '<br>
                        <strong>Visit Date:</strong> ' . formatDateTime($registration['visit_date'], 'M j, Y') . '<br>
                        <strong>Visit Time:</strong> ' . formatDateTime($registration['visit_time'], 'g:i A') . '<br>
                        <strong>Approved by:</strong> ' . htmlspecialchars($registration['approved_by_name']) . '
                    </div>
                    
                    <p>Please arrive on time and present your registration code at the reception for quick check-in.</p>
                    
                    <p><strong>What to bring:</strong></p>
                    <ul>
                        <li>Valid ID (National ID, Passport, or Driver\'s License)</li>
                        <li>This registration code: <strong>' . htmlspecialchars($registration['registration_code']) . '</strong></li>
                    </ul>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function getPreRegistrationRejectionTemplate($registration, $reason) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #EF4444; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #EF4444; }
                .rejection-box { background: #FEE2E2; padding: 15px; margin: 10px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . SITE_NAME . '</h1>
                    <h2>Visit Update</h2>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($registration['visitor_name']) . ',</p>
                    
                    <div class="rejection-box">
                        <h3 style="color: #DC2626; margin: 0;">Visit Request Update</h3>
                        <p style="margin: 10px 0 0 0;">Unfortunately, your visit request could not be approved at this time.</p>
                    </div>
                    
                    <div class="info-box">
                        <strong>Registration Code:</strong> ' . htmlspecialchars($registration['registration_code']) . '<br>
                        <strong>Requested Date:</strong> ' . formatDateTime($registration['visit_date'], 'M j, Y') . '<br>
                        <strong>Requested Time:</strong> ' . formatDateTime($registration['visit_time'], 'g:i A') . '<br>
                        <strong>Reason:</strong> ' . htmlspecialchars($reason) . '
                    </div>
                    
                    <p>If you need to reschedule or have questions, please contact your host or submit a new pre-registration request.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    public function sendBulkNotification($userIds, $type, $title, $message, $data = null) {
        try {
            $successCount = 0;
            
            foreach ($userIds as $userId) {
                $result = $this->createNotification($userId, $type, $title, $message, $data);
                if ($result['success']) {
                    $successCount++;
                }
            }
            
            return [
                'success' => true,
                'sent_count' => $successCount,
                'total_count' => count($userIds),
                'message' => "Sent {$successCount} notifications"
            ];
            
        } catch (Exception $e) {
            error_log("Send bulk notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send bulk notifications'];
        }
    }
    
    public function getNotificationStats($userId = null) {
        try {
            $stats = [];
            
            if ($userId) {
                // User-specific stats
                $userQuery = "SELECT 
                                COUNT(*) as total_notifications,
                                COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_notifications,
                                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_notifications
                             FROM notifications 
                             WHERE user_id = ?";
                
                $userStmt = $this->db->prepare($userQuery);
                $userStmt->execute([$userId]);
                $stats = $userStmt->fetch();
            } else {
                // System-wide stats
                $systemQuery = "SELECT 
                                  COUNT(*) as total_notifications,
                                  COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_notifications,
                                  COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_notifications,
                                  COUNT(DISTINCT user_id) as users_with_notifications
                               FROM notifications";
                
                $systemStmt = $this->db->prepare($systemQuery);
                $systemStmt->execute();
                $stats = $systemStmt->fetch();
                
                // Get notification types breakdown
                $typesQuery = "SELECT type, COUNT(*) as count 
                              FROM notifications 
                              GROUP BY type 
                              ORDER BY count DESC";
                
                $typesStmt = $this->db->prepare($typesQuery);
                $typesStmt->execute();
                $stats['types'] = $typesStmt->fetchAll();
            }
            
            return ['success' => true, 'data' => $stats];
            
        } catch (Exception $e) {
            error_log("Get notification stats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to get notification statistics'];
        }
    }
    
    public function cleanupOldNotifications($daysBefore = 90) {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-{$daysBefore} days"));
            
            $query = "DELETE FROM notifications WHERE DATE(created_at) < ? AND is_read = 1";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$cutoffDate]);
            
            $deletedCount = $stmt->rowCount();
            
            if ($deletedCount > 0) {
                logActivity(null, 'NOTIFICATIONS_CLEANUP', "Cleaned up {$deletedCount} old notifications");
            }
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Cleaned up {$deletedCount} old notifications"
            ];
            
        } catch (Exception $e) {
            error_log("Cleanup old notifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to cleanup old notifications'];
        }
    }
    
    public function testEmailConfiguration() {
        try {
            if (!SMTP_USERNAME || !SMTP_PASSWORD) {
                return ['success' => false, 'message' => 'SMTP credentials not configured'];
            }
            
            $testEmail = SMTP_FROM_EMAIL;
            $subject = 'Email Configuration Test - ' . SITE_NAME;
            $message = '
            <html>
            <body>
                <h2>Email Configuration Test</h2>
                <p>This is a test email to verify that the email configuration is working correctly.</p>
                <p>If you receive this email, your SMTP settings are properly configured.</p>
                <p>Sent at: ' . date('Y-m-d H:i:s') . '</p>
            </body>
            </html>';
            
            return $this->sendEmail($testEmail, $subject, $message);
            
        } catch (Exception $e) {
            error_log("Test email configuration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Email configuration test failed'];
        }
    }
    
    public function deleteNotification($notificationId, $userId) {
        try {
            $query = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$notificationId, $userId]);
            
            return ['success' => $result, 'message' => $result ? 'Notification deleted' : 'Failed to delete notification'];
            
        } catch (Exception $e) {
            error_log("Delete notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete notification'];
        }
    }
}
?>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #10B981; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #10B981; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . SITE_NAME . '</h1>
                    <h2>Visitor Checked In</h2>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($visit['host_name']) . ',</p>
                    <p>Your visitor has successfully checked in:</p>
                    
                    <div class="info-box">
                        <strong>Visitor:</strong> ' . htmlspecialchars($visit['visitor_name']) . '<br>
                        <strong>Company:</strong> ' . htmlspecialchars($visit['visitor_company']) . '<br>
                        <strong>Check-in Time:</strong> ' . formatDateTime($visit['check_in_time'], 'M j, Y g:i A') . '<br>
                        <strong>Badge Number:</strong> ' . htmlspecialchars($visit['badge_number']) . '
                    </div>
                    
                    <p>The visitor is now in the building and will be directed to your location.</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function getVisitorCheckoutTemplate($visit) {
        $duration = '';
        if ($visit['check_in_time'] && $visit['check_out_time']) {
            $checkinTime = new DateTime($visit['check_in_time']);
            $checkoutTime = new DateTime($visit['check_out_time']);
            $interval = $checkinTime->diff($checkoutTime);
            $duration = $interval->format('%h hours %i minutes');
        }
        
        return '
        <!DOCTYPE html>