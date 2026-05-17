<?php

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/RoadHazard.php';
require_once __DIR__ . '/../classes/Auth.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$hazards = new RoadHazard();
$user = Auth::getCurrentUser();

try {
    switch ($action) {
        case 'list':
            if ($method === 'GET') {
                echo json_encode(['success' => true, 'hazards' => $hazards->listAll()]);
            }
            break;

        case 'save':
            if ($method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $result = $hazards->save($data, (int) $user['id']);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'delete':
            if ($method === 'POST' && Auth::isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $result = $hazards->delete((int) ($data['id'] ?? 0));
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
