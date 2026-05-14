<?php
/**
 * Evacuation centers API (admin CRUD; barangay officials may list read-only).
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/EvacuationCenter.php';
require_once __DIR__ . '/../classes/Auth.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$ec = new EvacuationCenter();

try {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                $bid = $_GET['barangay_id'] ?? null;
                $rows = $ec->listAll($bid);
                http_response_code(200);
                echo json_encode(['success' => true, 'centers' => $rows]);
            }
            break;

        case 'get':
            if ($method === 'GET') {
                $id = (int) ($_GET['id'] ?? 0);
                $row = $ec->getById($id);
                if ($row) {
                    http_response_code(200);
                    echo json_encode(['success' => true, 'center' => $row]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Not found']);
                }
            }
            break;

        case 'create':
            if ($method === 'POST' && Auth::isAdmin()) {
                $d = json_decode(file_get_contents('php://input'), true) ?: [];
                $res = $ec->create(
                    (int) ($d['barangay_id'] ?? 0),
                    $d['center_name'] ?? '',
                    $d['address'] ?? '',
                    (int) ($d['capacity'] ?? 0),
                    (int) ($d['current_occupancy'] ?? 0),
                    $d['contact_person'] ?? '',
                    $d['contact_phone'] ?? '',
                    $d['facilities'] ?? '',
                    $d['status'] ?? 'open',
                    $d['latitude'] ?? null,
                    $d['longitude'] ?? null
                );
                http_response_code($res['success'] ? 201 : 400);
                echo json_encode($res);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'update':
            if (in_array($method, ['PUT', 'POST'], true) && Auth::isAdmin()) {
                $d = json_decode(file_get_contents('php://input'), true) ?: [];
                $res = $ec->update(
                    (int) ($d['id'] ?? 0),
                    (int) ($d['barangay_id'] ?? 0),
                    $d['center_name'] ?? '',
                    $d['address'] ?? '',
                    (int) ($d['capacity'] ?? 0),
                    (int) ($d['current_occupancy'] ?? 0),
                    $d['contact_person'] ?? '',
                    $d['contact_phone'] ?? '',
                    $d['facilities'] ?? '',
                    $d['status'] ?? 'open',
                    $d['latitude'] ?? null,
                    $d['longitude'] ?? null
                );
                http_response_code($res['success'] ? 200 : 400);
                echo json_encode($res);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'delete':
            if (in_array($method, ['DELETE', 'POST'], true) && Auth::isAdmin()) {
                $d = json_decode(file_get_contents('php://input'), true) ?: [];
                $res = $ec->delete((int) ($d['id'] ?? 0));
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
