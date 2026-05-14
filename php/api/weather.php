<?php
/**
 * Weather API Endpoints
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

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
                    echo json_encode([
                        'success' => true,
                        'weather' => $weather_data,
                        'weather_provider' => $weather->getProvider(),
                        'weather_ready' => $weather->isKeyConfigured(),
                        'openweather_configured' => $weather->isKeyConfigured(),
                    ]);
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
                echo json_encode([
                    'success' => true,
                    'weather_data' => $recent_data,
                    'weather_provider' => $weather->getProvider(),
                    'weather_ready' => $weather->isKeyConfigured(),
                    'openweather_configured' => $weather->isKeyConfigured(),
                ]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'barangay_dashboard':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                $dash = $weather->getDashboardWeatherByBarangay();
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'rows' => $dash['rows'],
                    'summary' => $dash['summary'],
                    'weather_provider' => $weather->getProvider(),
                    'weather_ready' => $weather->isKeyConfigured(),
                    'openweather_configured' => $weather->isKeyConfigured(),
                ]);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'sync_barangays':
            if (in_array($request_method, ['POST', 'GET'], true) && Auth::isAdmin()) {
                $result = $weather->syncAllBarangays();
                http_response_code($result['success'] ? 200 : 503);
                echo json_encode($result);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
            }
            break;

        case 'config_status':
            if ($request_method === 'GET' && Auth::isAdmin()) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'weather_provider' => $weather->getProvider(),
                    'weather_ready' => $weather->isKeyConfigured(),
                    'openweather_configured' => $weather->isKeyConfigured(),
                ]);
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
