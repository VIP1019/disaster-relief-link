<?php
/**
 * Weather API Endpoints
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once __DIR__ . '/../classes/WeatherAPI.php';
require_once __DIR__ . '/../classes/Auth.php';

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$weather = new WeatherAPI();

try {
    switch ($action) {
        case 'get_weather':
            if ($request_method === 'GET') {
                $latitude = $_GET['latitude'] ?? null;
                $longitude = $_GET['longitude'] ?? null;
                $barangay_id = $_GET['barangay_id'] ?? null;
                
                if ($latitude && $longitude) {
                    $weather_data = $weather->getWeatherData($latitude, $longitude, $barangay_id);
                    http_response_code(200);
                    echo json_encode(['success' => true, 'weather' => $weather_data]);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Latitude and longitude required']);
                }
            }
            break;

        case 'weather_history':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $barangay_id = $_GET['barangay_id'] ?? 0;
                $limit = $_GET['limit'] ?? 10;
                
                $history = $weather->getWeatherHistory($barangay_id, $limit);
                http_response_code(200);
                echo json_encode(['success' => true, 'history' => $history]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'all_recent_weather':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $limit = $_GET['limit'] ?? 100;
                
                $recent_data = $weather->getAllRecentWeatherData($limit);
                http_response_code(200);
                echo json_encode(['success' => true, 'weather_data' => $recent_data]);
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
