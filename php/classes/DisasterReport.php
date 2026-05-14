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
     *
     * @param array $extras Optional keys: severity_level (low|medium|high|critical), incident_latitude, incident_longitude, geographic_sector_label
     */
    public function submitReport($user_id, $barangay_id, $disaster_type, $affected_families, $damaged_houses, $description, $injured_count = 0, $death_count = 0, array $extras = []) {
        $affected_families = (int) $affected_families;
        $damaged_houses = (int) $damaged_houses;
        $injured_count = (int) $injured_count;
        $death_count = (int) $death_count;
        // Note: empty(0) is true in PHP — use numeric checks so 0 affected / 0 damaged is allowed.
        if (empty($user_id) || empty($barangay_id) || $disaster_type === '' || $affected_families < 0 || $damaged_houses < 0) {
            return ['success' => false, 'message' => 'All required fields must be filled'];
        }

        // Get weather data from API
        $weather_data = $this->getWeatherDataForBarangay($barangay_id);

        $weather_condition = $weather_data['weather'] ?? 'Unknown';
        $temperature = $weather_data['temperature'] ?? null;
        $humidity = $weather_data['humidity'] ?? null;
        $wind_speed = $weather_data['wind_speed'] ?? null;

        $sevIn = strtolower(trim((string) ($extras['severity_level'] ?? '')));
        if (in_array($sevIn, ['low', 'medium', 'high', 'critical'], true)) {
            $severity_level = $sevIn;
        } else {
            $severity_level = $this->calculateSeverity($affected_families, $damaged_houses);
        }

        $center = $this->getBarangayCenterById((int) $barangay_id);
        $defLat = $center ? (float) $center['latitude'] : null;
        $defLon = $center ? (float) $center['longitude'] : null;

        $ilat = null;
        $ilon = null;
        if (isset($extras['incident_latitude']) && is_numeric($extras['incident_latitude'])) {
            $ilat = (float) $extras['incident_latitude'];
        }
        if (isset($extras['incident_longitude']) && is_numeric($extras['incident_longitude'])) {
            $ilon = (float) $extras['incident_longitude'];
        }
        if ($ilat === null || $ilon === null) {
            $ilat = $defLat;
            $ilon = $defLon;
        }

        $sectorLabel = trim((string) ($extras['geographic_sector_label'] ?? ''));
        if ($sectorLabel === '' && $center) {
            $sectorLabel = $center['name'] . ' — incident area';
        }

        $temperature = $temperature === null ? 0.0 : (float) $temperature;
        $humidity = $humidity === null ? 0 : (int) $humidity;
        $wind_speed = $wind_speed === null ? 0.0 : (float) $wind_speed;
        $ilat = $ilat === null ? 0.0 : (float) $ilat;
        $ilon = $ilon === null ? 0.0 : (float) $ilon;

        $insert_query = "INSERT INTO {$this->table} 
                         (user_id, barangay_id, disaster_type, affected_families, damaged_houses, injured_count, death_count, description, 
                          weather_condition, temperature, humidity, wind_speed, severity_level,
                          incident_latitude, incident_longitude, geographic_sector_label)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($insert_query);
        $stmt->bind_param(
            'iisiiiissdidsdds',
            $user_id,
            $barangay_id,
            $disaster_type,
            $affected_families,
            $damaged_houses,
            $injured_count,
            $death_count,
            $description,
            $weather_condition,
            $temperature,
            $humidity,
            $wind_speed,
            $severity_level,
            $ilat,
            $ilon,
            $sectorLabel
        );

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Disaster report submitted successfully', 'report_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'message' => 'Report submission failed: ' . $this->conn->error];
        }
    }

    /**
     * @return array{id:int,name:string,latitude:float,longitude:float}|null
     */
    public function getBarangayCenterById($barangay_id) {
        $bid = (int) $barangay_id;
        if ($bid <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare('SELECT id, name, latitude, longitude FROM barangays WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $bid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
        ];
    }

    /**
     * Resolve barangay id from the logged-in user's barangay_name (must match barangays.name).
     */
    public function getBarangayIdForUser($user_id) {
        $query = "SELECT b.id FROM barangays b
                  INNER JOIN users u ON LOWER(TRIM(u.barangay_name)) = LOWER(TRIM(b.name))
                  WHERE u.id = ?
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Get all disaster reports (for admin)
     */
    public function getAllReports($status = null, $barangay_id = null) {
        $query = "SELECT dr.*, b.name as barangay_name, b.latitude AS barangay_latitude, b.longitude AS barangay_longitude,
                         COALESCE(dr.incident_latitude, b.latitude) AS map_latitude,
                         COALESCE(dr.incident_longitude, b.longitude) AS map_longitude,
                         ec.center_name AS suggested_evacuation_center_name,
                         ec.address AS suggested_evacuation_center_address,
                         ec.capacity AS suggested_evacuation_center_capacity,
                         ec.current_occupancy AS suggested_evacuation_center_occupancy,
                         ec.contact_person AS suggested_evacuation_contact_person,
                         ec.contact_phone AS suggested_evacuation_contact_phone,
                         ec.facilities AS suggested_evacuation_facilities,
                         ec.status AS suggested_evacuation_center_status,
                         u.full_name as submitted_by
                  FROM {$this->table} dr
                  JOIN barangays b ON dr.barangay_id = b.id
                  JOIN users u ON dr.user_id = u.id
                  LEFT JOIN evacuation_centers ec ON dr.suggested_evacuation_center_id = ec.id
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
        $query = "SELECT dr.*, b.name as barangay_name,
                         b.latitude AS barangay_latitude, b.longitude AS barangay_longitude,
                         COALESCE(dr.incident_latitude, b.latitude) AS map_latitude,
                         COALESCE(dr.incident_longitude, b.longitude) AS map_longitude,
                         ec.center_name AS suggested_evacuation_center_name,
                         ec.address AS suggested_evacuation_center_address,
                         ec.capacity AS suggested_evacuation_center_capacity,
                         ec.current_occupancy AS suggested_evacuation_center_occupancy,
                         ec.contact_person AS suggested_evacuation_contact_person,
                         ec.contact_phone AS suggested_evacuation_contact_phone,
                         ec.facilities AS suggested_evacuation_facilities,
                         ec.status AS suggested_evacuation_center_status
                  FROM {$this->table} dr
                  JOIN barangays b ON dr.barangay_id = b.id
                  LEFT JOIN evacuation_centers ec ON dr.suggested_evacuation_center_id = ec.id
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
        $query = "SELECT dr.*, b.name as barangay_name,
                         b.latitude AS barangay_latitude, b.longitude AS barangay_longitude,
                         COALESCE(dr.incident_latitude, b.latitude) AS map_latitude,
                         COALESCE(dr.incident_longitude, b.longitude) AS map_longitude,
                         ec.center_name AS suggested_evacuation_center_name,
                         ec.address AS suggested_evacuation_center_address,
                         ec.capacity AS suggested_evacuation_center_capacity,
                         ec.current_occupancy AS suggested_evacuation_center_occupancy,
                         ec.contact_person AS suggested_evacuation_contact_person,
                         ec.contact_phone AS suggested_evacuation_contact_phone,
                         ec.facilities AS suggested_evacuation_facilities,
                         ec.status AS suggested_evacuation_center_status,
                         u.full_name as submitted_by
                  FROM {$this->table} dr
                  JOIN barangays b ON dr.barangay_id = b.id
                  JOIN users u ON dr.user_id = u.id
                  LEFT JOIN evacuation_centers ec ON dr.suggested_evacuation_center_id = ec.id
                  WHERE dr.id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Admin: suggest an evacuation center for a submitted disaster report.
     */
    public function suggestEvacuationCenter($report_id, $center_id, $notes = '') {
        $rid = (int) $report_id;
        $cid = (int) $center_id;
        $noteText = trim((string) $notes);
        if ($rid <= 0 || $cid <= 0) {
            return ['success' => false, 'message' => 'Report and evacuation center are required'];
        }

        $check = $this->conn->prepare(
            "SELECT dr.id, dr.user_id, dr.barangay_id, ec.center_name, ec.address, ec.status
             FROM {$this->table} dr
             INNER JOIN evacuation_centers ec ON ec.id = ? AND ec.barangay_id = dr.barangay_id
             WHERE dr.id = ?
             LIMIT 1"
        );
        $check->bind_param('ii', $cid, $rid);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if (!$row) {
            return ['success' => false, 'message' => 'Choose an evacuation center from the report barangay'];
        }
        if ($row['status'] === 'closed') {
            return ['success' => false, 'message' => 'Closed evacuation centers cannot be suggested'];
        }

        $q = "UPDATE {$this->table}
              SET suggested_evacuation_center_id = ?, evacuation_suggestion_notes = ?, evacuation_suggested_at = CURRENT_TIMESTAMP,
                  evacuation_confirmed_at = NULL, evacuation_confirmation_notes = NULL
              WHERE id = ?";
        $stmt = $this->conn->prepare($q);
        $stmt->bind_param('isi', $cid, $noteText, $rid);
        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Evacuation center suggested',
                'user_id' => (int) $row['user_id'],
                'center_name' => $row['center_name'],
                'center_address' => $row['address'],
            ];
        }
        return ['success' => false, 'message' => 'Evacuation suggestion failed'];
    }

    /**
     * Barangay official: confirm that the affected users evacuated to the suggested center.
     */
    public function confirmEvacuation($user_id, $report_id, $notes = '') {
        $uid = (int) $user_id;
        $rid = (int) $report_id;
        $noteText = trim((string) $notes);
        if ($uid <= 0 || $rid <= 0) {
            return ['success' => false, 'message' => 'Invalid report'];
        }

        $check = $this->conn->prepare(
            "SELECT suggested_evacuation_center_id FROM {$this->table} WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $check->bind_param('ii', $rid, $uid);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if (!$row) {
            return ['success' => false, 'message' => 'Report not found'];
        }
        if (empty($row['suggested_evacuation_center_id'])) {
            return ['success' => false, 'message' => 'No evacuation center has been suggested for this report yet'];
        }

        $q = "UPDATE {$this->table}
              SET evacuation_confirmed_at = CURRENT_TIMESTAMP, evacuation_confirmation_notes = ?
              WHERE id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($q);
        $stmt->bind_param('sii', $noteText, $rid, $uid);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Evacuation confirmation sent'];
        }
        return ['success' => false, 'message' => 'Confirmation failed'];
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
     * Barangay official: update own report while still submitted.
     */
    public function updateOwnReport($user_id, $report_id, $data) {
        $rid = (int) $report_id;
        $uid = (int) $user_id;
        if ($rid <= 0 || $uid <= 0) {
            return ['success' => false, 'message' => 'Invalid report'];
        }

        $check = $this->conn->prepare("SELECT id, status FROM {$this->table} WHERE id = ? AND user_id = ? LIMIT 1");
        $check->bind_param('ii', $rid, $uid);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        if (!$row || $row['status'] !== 'submitted') {
            return ['success' => false, 'message' => 'Report not found or cannot be edited (only submitted reports can be updated).'];
        }

        $disaster_type = trim((string) ($data['disaster_type'] ?? ''));
        $affected_families = (int) ($data['affected_families'] ?? 0);
        $damaged_houses = (int) ($data['damaged_houses'] ?? 0);
        $injured_count = (int) ($data['injured_count'] ?? 0);
        $death_count = (int) ($data['death_count'] ?? 0);
        $description = (string) ($data['description'] ?? '');

        if ($disaster_type === '' || $affected_families < 0 || $damaged_houses < 0) {
            return ['success' => false, 'message' => 'Required fields invalid'];
        }

        $barangay_id = (int) ($this->getReportById($rid)['barangay_id'] ?? 0);
        $weather_data = $this->getWeatherDataForBarangay($barangay_id);
        $weather_condition = $weather_data['weather'] ?? 'Unknown';
        $temperature = $weather_data['temperature'] ?? null;
        $humidity = $weather_data['humidity'] ?? null;
        $wind_speed = $weather_data['wind_speed'] ?? null;
        $severity_level = $this->calculateSeverity($affected_families, $damaged_houses);

        $humidity = $humidity === null ? 0 : (int) $humidity;
        $temperature = $temperature === null ? 0.0 : (float) $temperature;
        $wind_speed = $wind_speed === null ? 0.0 : (float) $wind_speed;

        $q = "UPDATE {$this->table} SET disaster_type = ?, affected_families = ?, damaged_houses = ?, injured_count = ?, death_count = ?, description = ?,
              weather_condition = ?, temperature = ?, humidity = ?, wind_speed = ?, severity_level = ?
              WHERE id = ? AND user_id = ? AND status = 'submitted'";
        $stmt = $this->conn->prepare($q);
        $stmt->bind_param(
            'siiiissdidsii',
            $disaster_type,
            $affected_families,
            $damaged_houses,
            $injured_count,
            $death_count,
            $description,
            $weather_condition,
            $temperature,
            $humidity,
            $wind_speed,
            $severity_level,
            $rid,
            $uid
        );

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Report updated'];
        }
        return ['success' => false, 'message' => 'Update failed'];
    }

    /**
     * Barangay official: delete own report only while submitted.
     */
    public function deleteOwnReport($user_id, $report_id) {
        $rid = (int) $report_id;
        $uid = (int) $user_id;
        if ($rid <= 0 || $uid <= 0) {
            return ['success' => false, 'message' => 'Invalid report'];
        }

        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ? AND user_id = ? AND status = 'submitted'");
        $stmt->bind_param('ii', $rid, $uid);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Report deleted'];
        }
        return ['success' => false, 'message' => 'Report not found or cannot be deleted'];
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
