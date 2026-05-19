<?php
/**
 * Notifications API Endpoints
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Notification.php';
require_once __DIR__ . '/../classes/SystemSettings.php';
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
                
                // Semaphore SMS Hook for manual targeted notification
                if ($result['success'] && !empty($data['message'])) {
                    require_once __DIR__ . '/../classes/SmsService.php';
                    $userId = (int) ($data['user_id'] ?? 0);
                    $db = new Database();
                    $conn = $db->getConnection();
                    $q = "SELECT phone_number FROM users WHERE id = ?";
                    $st = $conn->prepare($q);
                    $st->bind_param('i', $userId);
                    $st->execute();
                    $uRes = $st->get_result()->fetch_assoc();
                    if ($uRes && !empty($uRes['phone_number'])) {
                        SmsService::sendSms($uRes['phone_number'], $data['message']);
                    }
                }
                
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

        case 'emergency_broadcast':
            if ($request_method === 'GET') {
                $settings = new SystemSettings();
                http_response_code(200);
                echo json_encode(['success' => true, 'broadcast' => $settings->getEmergencyBroadcast()]);
            }
            break;

        case 'emergency_broadcast_set':
            if ($request_method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $settings = new SystemSettings();
                
                $active = !empty($data['active']);
                $title = $data['title'] ?? '';
                $body = $data['body'] ?? '';
                $url = $data['protocol_url'] ?? '';
                
                $settings->setEmergencyBroadcast([
                    'active' => $active,
                    'title' => $title,
                    'body' => $body,
                    'protocol_url' => $url,
                ]);

                // Semaphore SMS Broadcast Hook if emergency broadcast is newly activated
                if ($active && !empty($body)) {
                    require_once __DIR__ . '/../classes/SmsService.php';
                    $officials = $notification->getBarangayOfficialRecipients();
                    $smsMessage = "EMERGENCY BROADCAST: " . $title . " - " . $body;
                    if (!empty($url)) {
                        $smsMessage .= " Info: " . $url;
                    }
                    foreach ($officials as $official) {
                        if (!empty($official['phone_number'])) {
                            SmsService::sendSms($official['phone_number'], $smsMessage);
                        }
                    }
                }

                http_response_code(200);
                echo json_encode(['success' => true, 'broadcast' => $settings->getEmergencyBroadcast()]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'get_sms_settings':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $settings = new SystemSettings();
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'semaphore_sms_enabled' => $settings->get('semaphore_sms_enabled', '0') === '1',
                    'semaphore_api_key' => $settings->get('semaphore_api_key', ''),
                    'semaphore_sender_name' => $settings->get('semaphore_sender_name', 'SEMAPHORE')
                ]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'save_sms_settings':
            if ($request_method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $settings = new SystemSettings();
                
                $enabled = !empty($data['semaphore_sms_enabled']) ? '1' : '0';
                $apiKey = $data['semaphore_api_key'] ?? '';
                $senderName = $data['semaphore_sender_name'] ?? 'SEMAPHORE';
                
                $settings->set('semaphore_sms_enabled', $enabled);
                $settings->set('semaphore_api_key', $apiKey);
                $settings->set('semaphore_sender_name', $senderName);
                
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'SMS Settings saved successfully.']);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'send_test_sms':
            if ($request_method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $phone = $data['phone_number'] ?? '';
                $msg = $data['message'] ?? 'Test SMS from ReliefLink Daet Command Center.';
                
                if (empty($phone)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
                    exit;
                }
                
                require_once __DIR__ . '/../classes/SmsService.php';
                $res = SmsService::sendSms($phone, $msg);
                
                http_response_code($res['success'] ? 200 : 400);
                echo json_encode($res);
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
