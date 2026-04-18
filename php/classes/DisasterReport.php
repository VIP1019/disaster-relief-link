<?php
/**
 * Disaster Report Class
 * Handles disaster report submission and management
 */

require_once __DIR__ . '/../config/Database.php';

class DisasterReport {
    private $conn;
    private $table = 'disaster_reports';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    /**
     * Submit a new disaster report
     */
    public function submitReport($user_id, $barangay_id, $disaster_type, $affected_families, $damaged_houses, $description, $injured_count = 0, $death_count = 0) {
        // Validate inputs
        if (empty($user_id) || empty($barangay_id) || empty($disaster_type) || empty($affected_families) || empty($damaged_houses)) {
            return ['success' => false, 'message' => 'All required fields must be filled'];
        }

        // Get weather data from API
        $weather_data = $this->getWeatherDataForBarangay($barangay_id);

        $weather_condition = $weather_data['weather'] ?? 'Unknown';
        $temperature = $weather_data['temperature'] ?? null;
        $humidity = $weather_data['humidity'] ?? null;
        $wind_speed = $weather_data['wind_speed'] ?? null;

        // Determine severity based on affected families and houses
        $severity_level = $this->calculateSeverity($affected_families, $damaged_houses);

        $insert_query = "INSERT INTO {$this->table} 
                         (user_id, barangay_id, disaster_type, affected_families, damaged_houses, injured_count, death_count, description, 
                          weather_condition, temperature, humidity, wind_speed, severity_level)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($insert_query);
        $stmt->bind_param('iisiiissdii s', 
            $user_id, $barangay_id, $disaster_type, $affected_families, $damaged_houses, 
            $injured_count, $death_count, $description, $temperature, $humidity, $wind_speed, 
            $weather_condition, $severity_level
        );

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Disaster report submitted successfully', 'report_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'message' => 'Report submission failed: ' . $this->conn->error];
        }
    }

    /**
     * Get all disaster reports (for admin)
     */
    public function getAllReports($status = null, $barangay_id = null) {
        $query = "SELECT dr.*, b.name as barangay_name, u.full_name as submitted_by
                  FROM {$this->table} dr
                  JOIN barangays b ON dr.barangay_id = b.id
                  JOIN users u ON dr.user_id = u.id
                  WHERE 1=1";

        if ($status) {
            $query .= " AND dr.status = ?";
        }
        if ($barangay_id) {
            $query .= " AND dr.barangay_id = ?";
        }

        $query .= " ORDER BY dr.submitted_at DESC";

        $stmt = $this->conn->prepare($query);

        if ($status && $barangay_id) {
            $stmt->bind_param('si', $status, $barangay_id);
        } elseif ($status) {
            $stmt->bind_param('s', $status);
        } elseif ($barangay_id) {
            $stmt->bind_param('i', $barangay_id);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get reports for a specific barangay official
     */
    public function getUserReports($user_id) {
        $query = "SELECT dr.*, b.name as barangay_name
                  FROM {$this->table} dr
                  JOIN barangays b ON dr.barangay_id = b.id
                  WHERE dr.user_id = ?
                  ORDER BY dr.submitted_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get single report details
     */
    public function getReportById($report_id) {
        $query = "SELECT dr.*, b.name as barangay_name, u.full_name as submitted_by
                  FROM {$this->table} dr
                  JOIN barangays b ON dr.barangay_id = b.id
                  JOIN users u ON dr.user_id = u.id
                  WHERE dr.id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Update report status
     */
    public function updateReportStatus($report_id, $status) {
        $valid_statuses = ['submitted', 'reviewed', 'prioritized', 'relief_distributed'];
        if (!in_array($status, $valid_statuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }

        $update_query = "UPDATE {$this->table} SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($update_query);
        $stmt->bind_param('si', $status, $report_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Report status updated'];
        } else {
            return ['success' => false, 'message' => 'Update failed'];
        }
    }

    /**
     * Calculate severity based on affected families and damaged houses
     */
    private function calculateSeverity($affected_families, $damaged_houses) {
        $severity_score = ($affected_families * 0.6) + ($damaged_houses * 0.4);

        if ($severity_score >= 500) {
            return 'critical';
        } elseif ($severity_score >= 250) {
            return 'high';
        } elseif ($severity_score >= 100) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get weather data for a barangay
     */
    private function getWeatherDataForBarangay($barangay_id) {
        require_once __DIR__ . '/WeatherAPI.php';
        
        $weather = new WeatherAPI();
        $barangay_query = "SELECT latitude, longitude FROM barangays WHERE id = ?";
        
        $stmt = $this->conn->prepare($barangay_query);
        $stmt->bind_param('i', $barangay_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            return $weather->getWeatherData($result['latitude'], $result['longitude'], $barangay_id);
        }

        return ['weather' => 'Unknown', 'temperature' => null, 'humidity' => null, 'wind_speed' => null];
    }
}
?>
