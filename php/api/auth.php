<?php
/**
 * Authentication API Endpoints
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Auth.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$auth = new Auth();

try {
    switch ($action) {
        case 'register':
            if ($request_method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $result = $auth->register(
                    $data['username'] ?? '',
                    $data['email'] ?? '',
                    $data['password'] ?? '',
                    $data['full_name'] ?? '',
                    $data['barangay_name'] ?? '',
                    $data['phone_number'] ?? '',
                    $data['address'] ?? ''
                );

                http_response_code($result['success'] ? 201 : 400);
                echo json_encode($result);
            }
            break;

        case 'login':
            if ($request_method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $result = $auth->login($data['username'] ?? '', $data['password'] ?? '');

                http_response_code($result['success'] ? 200 : 401);
                echo json_encode($result);
            }
            break;

        case 'logout':
            $result = $auth->logout();
            http_response_code(200);
            echo json_encode($result);
            break;

        case 'check':
            $is_logged_in = Auth::isLoggedIn();
            if ($is_logged_in) {
                $user = Auth::getCurrentUser();
                http_response_code(200);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Not logged in']);
            }
            break;

        case 'get_profile':
            if (Auth::isLoggedIn()) {
                $user = Auth::getCurrentUser();
                $result = $auth->getProfile($user['id']);
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            }
            break;

        case 'update_profile':
            if ($request_method === 'POST' && Auth::isLoggedIn()) {
                $user = Auth::getCurrentUser();
                $data = json_decode(file_get_contents('php://input'), true) ?: [];
                $result = $auth->updateProfile(
                    $user['id'],
                    $data['email'] ?? '',
                    $data['full_name'] ?? '',
                    $data['phone_number'] ?? null,
                    $data['address'] ?? null,
                    !empty($data['password']) ? $data['password'] : null
                );
                http_response_code($result['success'] ? 200 : 400);
                echo json_encode($result);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
