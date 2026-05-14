<?php
/**
 * Notifications API Endpoints
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../classes/Auth.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$notification = new Notification();
$current_user = Auth::getCurrentUser();

try {
    switch ($action) {
        case 'get_notifications':
        case 'list_user':
            if ($request_method === 'GET') {
                $unread_only = $_GET['unread_only'] ?? false;
                $notifications = $notification->getUserNotifications($current_user['id'], filter_var($unread_only, FILTER_VALIDATE_BOOLEAN));
                http_response_code(200);
                echo json_encode(['success' => true, 'notifications' => $notifications]);
            }
            break;

        case 'get_unread_count':
            if ($request_method === 'GET') {
                $count = $notification->getUnreadCount($current_user['id']);
                http_response_code(200);
                echo json_encode(['success' => true, 'unread_count' => $count]);
            }
            break;

        case 'mark_read':
            if (in_array($request_method, ['PUT', 'POST'], true)) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $result = $notification->markAsRead((int) ($data['notification_id'] ?? 0), (int) $current_user['id']);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            }
            break;

        case 'mark_all_read':
            if (in_array($request_method, ['PUT', 'POST'], true)) {
                $result = $notification->markAllAsRead($current_user['id']);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            }
            break;

        case 'delete':
            if (in_array($request_method, ['DELETE', 'POST'], true)) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $isAdmin = Auth::isAdmin();
                $result = $notification->deleteNotification((int) ($data['notification_id'] ?? 0), (int) $current_user['id'], $isAdmin);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            }
            break;

        case 'create':
            if ($request_method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $notification->createNotification(
                    $data['user_id'] ?? 0,
                    $data['notification_type'] ?? '',
                    $data['message'] ?? '',
                    $data['report_id'] ?? null
                );
                http_response_code($result['success'] ? 201 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'list_all':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $limit = (int) ($_GET['limit'] ?? 200);
                $rows = $notification->getAllNotifications($limit);
                http_response_code(200);
                echo json_encode(['success' => true, 'notifications' => $rows]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'list_recipients':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $rows = $notification->getBarangayOfficialRecipients();
                http_response_code(200);
                echo json_encode(['success' => true, 'recipients' => $rows]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
