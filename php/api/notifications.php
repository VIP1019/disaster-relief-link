<?php
/**
 * Notifications API Endpoints
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

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
            if ($request_method === 'PUT') {
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $notification->markAsRead($data['notification_id'] ?? 0);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            }
            break;

        case 'mark_all_read':
            if ($request_method === 'PUT') {
                $result = $notification->markAllAsRead($current_user['id']);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            }
            break;

        case 'delete':
            if ($request_method === 'DELETE') {
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $notification->deleteNotification($data['notification_id'] ?? 0);
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

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
