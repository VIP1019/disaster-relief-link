<?php
/**
 * Public Unauthenticated ReliefLink API Endpoints
 * Provides open access to aggregate transparency telemetry and verification gallery.
 */

require_once __DIR__ . '/_cors.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../config/Database.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    switch ($action) {
        case 'public_stats':
            if ($request_method === 'GET') {
                // 1. Served Barangays
                $brgy_query = "SELECT COUNT(DISTINCT barangay_id) as barangays_served FROM relief_distributions";
                $brgy_res = $conn->query($brgy_query);
                $brgy_row = $brgy_res ? $brgy_res->fetch_assoc() : [];
                $barangays_served = (int)($brgy_row['barangays_served'] ?? 0);

                // 2. Total Shipments / Distributions
                $dist_query = "SELECT COUNT(*) as total_distributions FROM relief_distributions";
                $dist_res = $conn->query($dist_query);
                $dist_row = $dist_res ? $dist_res->fetch_assoc() : [];
                $total_distributions = (int)($dist_row['total_distributions'] ?? 0);

                // 3. Total Supplies/Units Shipped
                $units_query = "SELECT SUM(quantity_distributed) as total_units_distributed FROM relief_distributions";
                $units_res = $conn->query($units_query);
                $units_row = $units_res ? $units_res->fetch_assoc() : [];
                $total_units_distributed = (int)($units_row['total_units_distributed'] ?? 0);

                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'barangays_served' => $barangays_served,
                        'total_distributions' => $total_distributions,
                        'total_units_distributed' => $total_units_distributed,
                    ]
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
            }
            break;

        case 'public_gallery':
            if ($request_method === 'GET') {
                // Fetch reports where status is relief_received and proof_of_delivery_photo is NOT NULL
                $q = "SELECT r.id, b.name as barangay_name, r.disaster_type, r.affected_families, r.submitted_at, r.delivery_confirmed_at, r.proof_of_delivery_photo, r.delivery_signature_data 
                      FROM disaster_reports r
                      INNER JOIN barangays b ON r.barangay_id = b.id
                      WHERE r.status = 'relief_received' AND (r.proof_of_delivery_photo IS NOT NULL OR r.delivery_signature_data IS NOT NULL)
                      ORDER BY r.delivery_confirmed_at DESC 
                      LIMIT 12";
                
                $stmt = $conn->prepare($q);
                $stmt->execute();
                $res = $stmt->get_result();
                
                $gallery = [];
                while ($row = $res->fetch_assoc()) {
                    $gallery[] = [
                        'id' => (int)$row['id'],
                        'barangay_name' => $row['barangay_name'],
                        'disaster_type' => $row['disaster_type'],
                        'affected_families' => (int)$row['affected_families'],
                        'submitted_at' => $row['submitted_at'],
                        'delivery_confirmed_at' => $row['delivery_confirmed_at'],
                        'proof_photo' => $row['proof_of_delivery_photo'], // base64 photo
                        'signature_data' => $row['delivery_signature_data'] // base64 signature
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'gallery' => $gallery
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
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
