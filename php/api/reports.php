<?php
/**
 * Disaster Reports API Endpoints
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

require_once __DIR__ . '/../classes/DisasterReport.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Notification.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$report_id = $_GET['report_id'] ?? '';

$report = new DisasterReport();
$notification = new Notification();
$current_user = Auth::getCurrentUser();

try {
    switch ($action) {
        case 'submit':
            if ($request_method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $result = $report->submitReport(
                    $current_user['id'],
                    $data['barangay_id'] ?? '',
                    $data['disaster_type'] ?? '',
                    $data['affected_families'] ?? 0,
                    $data['damaged_houses'] ?? 0,
                    $data['description'] ?? '',
                    $data['injured_count'] ?? 0,
                    $data['death_count'] ?? 0
                );

                if ($result['success']) {
                    // Create notification for admins
                    $notification_text = "New disaster report submitted by " . $current_user['full_name'] . " from " . $current_user['barangay_name'];
                    // Notify all admin users
                    // (Implementation would require getting all admin users)
                }

                http_response_code($result['success'] ? 201 : 400);
                echo json_encode($result);
            }
            break;

        case 'get':
            if ($request_method === 'GET') {
                if ($report_id) {
                    $result = $report->getReportById($report_id);
                    if ($result) {
                        http_response_code(200);
                        echo json_encode(['success' => true, 'report' => $result]);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'message' => 'Report not found']);
                    }
                }
            }
            break;

        case 'list_user':
            if ($request_method === 'GET') {
                $reports = $report->getUserReports($current_user['id']);
                http_response_code(200);
                echo json_encode(['success' => true, 'reports' => $reports]);
            }
            break;

        case 'list_all':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $status = $_GET['status'] ?? null;
                $barangay_id = $_GET['barangay_id'] ?? null;
                
                $reports = $report->getAllReports($status, $barangay_id);
                http_response_code(200);
                echo json_encode(['success' => true, 'reports' => $reports]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'update_status':
            if ($request_method === 'PUT' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $result = $report->updateReportStatus($data['report_id'] ?? '', $data['status'] ?? '');

                if ($result['success']) {
                    // Notify the reporting official
                    $report_data = $report->getReportById($data['report_id']);
                    if ($report_data) {
                        $notification->notifyReportStatusChange($report_data['user_id'], $report_data['id'], $data['status']);
                    }
                }

                http_response_code($result['success'] ? 200 : 400);
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
