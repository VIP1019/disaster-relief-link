<?php
/**
 * Disaster Reports API Endpoints
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

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

                $barangay_id = isset($data['barangay_id']) ? (int) $data['barangay_id'] : 0;
                if ($barangay_id <= 0) {
                    $resolved = $report->getBarangayIdForUser($current_user['id']);
                    if ($resolved) {
                        $barangay_id = $resolved;
                    } else {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'No barangay linked to your profile. Use Registration with a barangay name that matches the master list, or ask MDRRMO to update your account.',
                        ]);
                        break;
                    }
                }

                $result = $report->submitReport(
                    $current_user['id'],
                    $barangay_id,
                    $data['disaster_type'] ?? '',
                    $data['affected_families'] ?? 0,
                    $data['damaged_houses'] ?? 0,
                    $data['description'] ?? '',
                    $data['injured_count'] ?? 0,
                    $data['death_count'] ?? 0,
                    [
                        'severity_level' => $data['severity_level'] ?? '',
                        'incident_latitude' => $data['incident_latitude'] ?? null,
                        'incident_longitude' => $data['incident_longitude'] ?? null,
                        'geographic_sector_label' => $data['geographic_sector_label'] ?? '',
                    ]
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

        case 'update_own':
            if (in_array($request_method, ['PUT', 'POST'], true) && ($current_user['user_type'] ?? '') === 'barangay_official') {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $rid = (int) ($data['report_id'] ?? 0);
                $result = $report->updateOwnReport((int) $current_user['id'], $rid, $data);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'delete_own':
            if (in_array($request_method, ['DELETE', 'POST'], true) && ($current_user['user_type'] ?? '') === 'barangay_official') {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $rid = (int) ($data['report_id'] ?? 0);
                $result = $report->deleteOwnReport((int) $current_user['id'], $rid);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'suggest_evacuation':
            if (in_array($request_method, ['PUT', 'POST'], true) && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $rid = (int) ($data['report_id'] ?? 0);
                $center_id = (int) ($data['evacuation_center_id'] ?? $data['center_id'] ?? 0);
                $result = $report->suggestEvacuationCenter($rid, $center_id, $data['notes'] ?? '');
                if ($result['success']) {
                    $message = 'MDRRMO recommends evacuation to ' . $result['center_name'] . '.';
                    if (!empty($result['center_address'])) {
                        $message .= ' Address: ' . $result['center_address'] . '.';
                    }
                    $notification->createNotification((int) $result['user_id'], 'evacuation_suggestion', $message, $rid);
                }
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'confirm_evacuation':
            if (in_array($request_method, ['PUT', 'POST'], true) && ($current_user['user_type'] ?? '') === 'barangay_official') {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $rid = (int) ($data['report_id'] ?? 0);
                $result = $report->confirmEvacuation((int) $current_user['id'], $rid, $data['notes'] ?? '');
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'my_barangay_geo':
            if ($request_method === 'GET' && ($current_user['user_type'] ?? '') === 'barangay_official') {
                $bid = $report->getBarangayIdForUser((int) $current_user['id']);
                if (!$bid) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'No barangay linked to your profile.']);
                    break;
                }
                $geo = $report->getBarangayCenterById($bid);
                if (!$geo) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Barangay not found']);
                    break;
                }
                http_response_code(200);
                echo json_encode(['success' => true, 'barangay' => $geo]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'get':
            if ($request_method === 'GET') {
                if (!$report_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'report_id required']);
                    break;
                }
                $result = $report->getReportById($report_id);
                if (!$result) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Report not found']);
                    break;
                }
                if (!Auth::isAdmin() && (int) $result['user_id'] !== (int) $current_user['id']) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Forbidden']);
                    break;
                }
                http_response_code(200);
                echo json_encode(['success' => true, 'report' => $result]);
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
            if (in_array($request_method, ['PUT', 'POST'], true) && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $report_id = (int) ($data['report_id'] ?? $data['id'] ?? 0);
                $status = trim((string) ($data['status'] ?? ''));
                $norm = strtolower($status);
                if ($norm === 'approved' || $norm === 'approve') {
                    $status = 'reviewed';
                } elseif (in_array($norm, ['reject', 'rejected', 'dismiss'], true)) {
                    $status = 'submitted';
                }

                $result = $report->updateReportStatus($report_id, $status);

                if ($result['success']) {
                    $report_data = $report->getReportById($report_id);
                    if ($report_data) {
                        $notification->notifyReportStatusChange($report_data['user_id'], $report_data['id'], $status);
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
