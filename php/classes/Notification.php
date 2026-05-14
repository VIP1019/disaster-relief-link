<?php
/**
 * Notification Class
 * Handles notification creation and management
 */

require_once __DIR__ . '/../config/Database.php';

class Notification {
    private $conn;
    private $table = 'notifications';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    /**
     * Create a notification
     */
    public function createNotification($user_id, $notification_type, $message, $report_id = null) {
        if (empty($user_id) || empty($notification_type) || empty($message)) {
            return ['success' => false, 'message' => 'Required fields missing'];
        }

        if ($report_id === null || $report_id === '') {
            $insert_query = "INSERT INTO {$this->table} (user_id, notification_type, message)
                             VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($insert_query);
            $stmt->bind_param('iss', $user_id, $notification_type, $message);
        } else {
            $insert_query = "INSERT INTO {$this->table} (user_id, report_id, notification_type, message)
                             VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($insert_query);
            $rid = (int) $report_id;
            $stmt->bind_param('iiss', $user_id, $rid, $notification_type, $message);
        }

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Notification created', 'notification_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'message' => 'Failed to create notification'];
        }
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications($user_id, $unread_only = false) {
        $query = "SELECT n.*, dr.disaster_type
                  FROM {$this->table} n
                  LEFT JOIN disaster_reports dr ON n.report_id = dr.id
                  WHERE n.user_id = ?";

        if ($unread_only) {
            $query .= " AND n.is_read = FALSE";
        }

        $query .= " ORDER BY n.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get unread notification count for user
     */
    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as unread_count FROM {$this->table} WHERE user_id = ? AND is_read = FALSE";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['unread_count'];
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        $nid = (int) $notification_id;
        $uid = (int) $user_id;
        if ($nid <= 0 || $uid <= 0) {
            return ['success' => false, 'message' => 'Invalid notification'];
        }
        $update_query = "UPDATE {$this->table} SET is_read = TRUE WHERE id = ? AND user_id = ?";

        $stmt = $this->conn->prepare($update_query);
        $stmt->bind_param('ii', $nid, $uid);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Notification marked as read'];
        } else {
            return ['success' => false, 'message' => 'Update failed or notification not found'];
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($user_id) {
        $update_query = "UPDATE {$this->table} SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE";
        
        $stmt = $this->conn->prepare($update_query);
        $stmt->bind_param('i', $user_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'All notifications marked as read'];
        } else {
            return ['success' => false, 'message' => 'Update failed'];
        }
    }

    /**
     * Delete notification
     */
    public function deleteNotification($notification_id, $user_id, $admin = false) {
        $nid = (int) $notification_id;
        if ($nid <= 0) {
            return ['success' => false, 'message' => 'Invalid notification'];
        }

        if ($admin) {
            $delete_query = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->conn->prepare($delete_query);
            $stmt->bind_param('i', $nid);
        } else {
            $uid = (int) $user_id;
            if ($uid <= 0) {
                return ['success' => false, 'message' => 'Invalid user'];
            }
            $delete_query = "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?";
            $stmt = $this->conn->prepare($delete_query);
            $stmt->bind_param('ii', $nid, $uid);
        }

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Notification deleted'];
        } else {
            return ['success' => false, 'message' => 'Delete failed or not found'];
        }
    }

    /**
     * Notify all barangays about relief distribution update
     */
    public function notifyDistributionUpdate($barangay_id, $relief_items, $admin_id) {
        // Get all barangay officials from the affected barangay
        $query = "SELECT id FROM users WHERE barangay_name = (SELECT name FROM barangays WHERE id = ?) AND user_type = 'barangay_official'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $barangay_id);
        $stmt->execute();
        $officials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $message = "Relief items have been distributed to your barangay: " . implode(', ', $relief_items);

        foreach ($officials as $official) {
            $this->createNotification($official['id'], 'relief_distribution', $message);
        }

        return ['success' => true, 'message' => 'Notifications sent to ' . count($officials) . ' barangay officials'];
    }

    /**
     * Notify about report status change
     */
    public function notifyReportStatusChange($user_id, $report_id, $new_status) {
        $status_messages = [
            'reviewed' => 'Your disaster report has been reviewed by administrators.',
            'prioritized' => 'Your barangay has been prioritized for relief distribution.',
            'relief_distributed' => 'Relief goods have been distributed to your barangay.'
        ];

        $message = $status_messages[$new_status] ?? 'Your report status has been updated.';

        return $this->createNotification($user_id, 'report_status_change', $message, $report_id);
    }

    /**
     * Admin: all notifications with recipient name
     */
    public function getAllNotifications($limit = 200) {
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 200;
        }
        $query = "SELECT n.*, u.full_name AS recipient_name, u.username AS recipient_username
                  FROM {$this->table} n
                  INNER JOIN users u ON n.user_id = u.id
                  ORDER BY n.created_at DESC
                  LIMIT " . $limit;

        $result = $this->conn->query($query);
        if (!$result) {
            return [];
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Admin: barangay officials for notification targeting.
     */
    public function getBarangayOfficialRecipients() {
        $query = "SELECT id, full_name, username, barangay_name, email FROM users WHERE user_type = 'barangay_official' AND is_active = 1 ORDER BY barangay_name, full_name";
        $result = $this->conn->query($query);
        if (!$result) {
            return [];
        }
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>
