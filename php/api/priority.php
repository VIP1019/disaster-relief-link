<?php
/**
 * Priority & Ranking API Endpoints
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/PriorityCalculator.php';
require_once __DIR__ . '/../classes/Auth.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$priority = new PriorityCalculator();

try {
    switch ($action) {
        case 'calculate_all':
            if ($request_method === 'POST' && Auth::isAdmin()) {
                $result = $priority->calculateAllBarangayPriorities();
                http_response_code(200);
                echo json_encode(['success' => true, 'priorities' => $result, 'message' => 'Priorities calculated successfully']);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'get_rankings':
            if ($request_method === 'GET') {
                $limit = $_GET['limit'] ?? null;
                $rankings = $priority->getBarangayPriorities($limit);
                http_response_code(200);
                echo json_encode(['success' => true, 'rankings' => $rankings]);
            }
            break;

        case 'get_barangay_priority':
            if ($request_method === 'GET') {
                $barangay_id = $_GET['barangay_id'] ?? 0;
                $barangay_priority = $priority->getBarangayPriority($barangay_id);
                
                if ($barangay_priority) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'priority' => $barangay_priority]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Priority data not found']);
                }
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
