<?php
/**
 * Relief Management API Endpoints
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

require_once __DIR__ . '/../classes/ReliefManagement.php';
require_once __DIR__ . '/../classes/Auth.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$relief = new ReliefManagement();
$current_user = Auth::getCurrentUser();

try {
    switch ($action) {
        // ===== INVENTORY ENDPOINTS =====
        
        case 'inventory_add':
            if ($request_method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $result = $relief->addInventoryItem(
                    $data['item_name'] ?? '',
                    $data['category'] ?? '',
                    $data['quantity'] ?? 0,
                    $data['unit_of_measure'] ?? '',
                    $data['cost_per_unit'] ?? 0,
                    $data['description'] ?? '',
                    $current_user['id']
                );

                http_response_code($result['success'] ? 201 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'inventory_update':
            if ($request_method === 'PUT' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $result = $relief->updateInventoryItem(
                    $data['item_id'] ?? 0,
                    $data['item_name'] ?? '',
                    $data['category'] ?? '',
                    $data['quantity'] ?? 0,
                    $data['unit_of_measure'] ?? '',
                    $data['cost_per_unit'] ?? 0,
                    $data['description'] ?? ''
                );

                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'inventory_list':
            if ($request_method === 'GET') {
                $category = $_GET['category'] ?? null;
                $items = $relief->getAllInventoryItems($category);
                http_response_code(200);
                echo json_encode(['success' => true, 'inventory' => $items]);
            }
            break;

        case 'inventory_get':
            if ($request_method === 'GET') {
                $item_id = $_GET['item_id'] ?? '';
                $item = $relief->getInventoryItem($item_id);
                
                if ($item) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'item' => $item]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Item not found']);
                }
            }
            break;

        case 'inventory_summary':
            if ($request_method === 'GET') {
                $summary = $relief->getInventorySummary();
                http_response_code(200);
                echo json_encode(['success' => true, 'summary' => $summary]);
            }
            break;

        // ===== DISTRIBUTION ENDPOINTS =====

        case 'distribute':
            if ($request_method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $result = $relief->recordDistribution(
                    $data['report_id'] ?? 0,
                    $data['barangay_id'] ?? 0,
                    $data['inventory_id'] ?? 0,
                    $data['quantity_distributed'] ?? 0,
                    $current_user['id'],
                    $data['notes'] ?? ''
                );

                http_response_code($result['success'] ? 201 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'distribution_list':
            if ($request_method === 'GET') {
                $barangay_id = $_GET['barangay_id'] ?? null;
                $report_id = $_GET['report_id'] ?? null;
                
                $distributions = $relief->getAllDistributions($barangay_id, $report_id);
                http_response_code(200);
                echo json_encode(['success' => true, 'distributions' => $distributions]);
            }
            break;

        case 'distribution_barangay':
            if ($request_method === 'GET') {
                $barangay_id = $_GET['barangay_id'] ?? 0;
                $history = $relief->getBarangayDistributionHistory($barangay_id);
                http_response_code(200);
                echo json_encode(['success' => true, 'history' => $history]);
            }
            break;

        case 'distribution_stats':
            if ($request_method === 'GET') {
                $stats = $relief->getDistributionStatistics();
                http_response_code(200);
                echo json_encode(['success' => true, 'statistics' => $stats]);
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
