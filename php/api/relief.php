<?php
/**
 * Relief Management API Endpoints
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

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
            if (in_array($request_method, ['PUT', 'POST'], true) && Auth::isAdmin()) {
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
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $category = $_GET['category'] ?? null;
                $items = $relief->getAllInventoryItems($category);
                http_response_code(200);
                echo json_encode(['success' => true, 'inventory' => $items]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'inventory_get':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $item_id = $_GET['item_id'] ?? '';
                $item = $relief->getInventoryItem($item_id);
                
                if ($item) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'item' => $item]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Item not found']);
                }
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'inventory_summary':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $summary = $relief->getInventorySummary();
                http_response_code(200);
                echo json_encode(['success' => true, 'summary' => $summary]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'low_stock':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $items = $relief->getLowStockItems();
                http_response_code(200);
                echo json_encode(['success' => true, 'items' => $items]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'restock_request':
            if (in_array($request_method, ['POST', 'PUT'], true) && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $item_id = isset($data['item_id']) ? (int) $data['item_id'] : null;
                $result = $relief->createRestockRequest((int) $current_user['id'], $item_id);
                http_response_code($result['success'] ? 201 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'barangays_list':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $rows = $relief->getAllBarangays();
                http_response_code(200);
                echo json_encode(['success' => true, 'barangays' => $rows]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'inventory_delete':
            if (in_array($request_method, ['DELETE', 'POST'], true) && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $item_id = (int) ($data['item_id'] ?? $_GET['item_id'] ?? 0);
                $result = $relief->deleteInventoryItem($item_id);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        // ===== DISTRIBUTION ENDPOINTS =====

        case 'distribute':
        case 'record_distribution':
            if ($request_method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                $report_id = (int) ($data['report_id'] ?? 0);
                $barangay_id = (int) ($data['barangay_id'] ?? 0);
                if ($barangay_id <= 0 && !empty($data['barangay_name'])) {
                    $resolved = $relief->resolveBarangayIdByName($data['barangay_name']);
                    $barangay_id = $resolved ?: 0;
                }
                if ($report_id > 0 && $barangay_id <= 0) {
                    $barangay_id = (int) ($relief->getBarangayIdForReport($report_id) ?: 0);
                }
                if ($report_id <= 0 && $barangay_id > 0) {
                    $report_id = (int) ($relief->getLatestActiveReportIdForBarangay($barangay_id) ?: 0);
                }

                if ($report_id > 0) {
                    $from_report = $relief->getBarangayIdForReport($report_id);
                    if ($from_report !== null && $from_report > 0) {
                        $barangay_id = $from_report;
                    }
                }

                $notes = (string) ($data['notes'] ?? '');

                if ($report_id <= 0 || $barangay_id <= 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Could not record distribution. Ensure the barangay has an active disaster report (submitted/reviewed/prioritized).'
                    ]);
                    break;
                }

                // If items array is provided, process multiple items atomically
                if (isset($data['items']) && is_array($data['items']) && count($data['items']) > 0) {
                    $relief->beginTransaction();
                    $success_count = 0;
                    $errors = [];

                    foreach ($data['items'] as $item) {
                        $inventory_id = (int) ($item['inventory_id'] ?? 0);
                        $qty = (int) ($item['quantity'] ?? $item['quantity_distributed'] ?? 0);

                        if ($inventory_id <= 0 || $qty <= 0) {
                            $errors[] = "Invalid item ID or quantity in batch request.";
                            continue;
                        }

                        $result = $relief->recordDistribution(
                            $report_id,
                            $barangay_id,
                            $inventory_id,
                            $qty,
                            (int) $current_user['id'],
                            $notes ?: 'Relief distribution shipment deployment'
                        );

                        if (!$result['success']) {
                            $errors[] = $result['message'] ?? "Stock deduction failed for item ID {$inventory_id}.";
                        } else {
                            $success_count++;
                        }
                    }

                    if (count($errors) > 0) {
                        $relief->rollback();
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Shipment aborted. Errors: ' . implode(' | ', $errors)
                        ]);
                    } else {
                        $relief->commit();
                        http_response_code(201);
                        echo json_encode([
                            'success' => true,
                            'message' => "Shipment of {$success_count} item(s) completed successfully."
                        ]);
                    }
                } else {
                    // Backward compatibility single-item mode
                    $inventory_id = (int) ($data['inventory_id'] ?? 0);
                    $qty = (int) ($data['quantity_distributed'] ?? $data['quantity'] ?? 0);

                    if ($inventory_id <= 0 || $qty <= 0) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Please select an item and a valid quantity.'
                        ]);
                        break;
                    }

                    $result = $relief->recordDistribution(
                        $report_id,
                        $barangay_id,
                        $inventory_id,
                        $qty,
                        (int) $current_user['id'],
                        $notes
                    );

                    http_response_code($result['success'] ? 201 : 400);
                    echo json_encode($result);
                }
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'distribution_list':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $barangay_id = $_GET['barangay_id'] ?? null;
                $report_id = $_GET['report_id'] ?? null;
                
                $distributions = $relief->getAllDistributions($barangay_id, $report_id);
                http_response_code(200);
                echo json_encode(['success' => true, 'distributions' => $distributions]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'distribution_barangay':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $barangay_id = $_GET['barangay_id'] ?? 0;
                $history = $relief->getBarangayDistributionHistory($barangay_id);
                http_response_code(200);
                echo json_encode(['success' => true, 'history' => $history]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'distribution_stats':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $stats = $relief->getDistributionStatistics();
                http_response_code(200);
                echo json_encode(['success' => true, 'statistics' => $stats]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'deploy_status':
            if ($request_method === 'GET' && Auth::isLoggedIn()) {
                $snap = $relief->getDeployStatusSnapshot();
                http_response_code(200);
                echo json_encode(['success' => true, 'deploy' => $snap]);
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
