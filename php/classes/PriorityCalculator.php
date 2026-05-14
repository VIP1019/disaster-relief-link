<?php
/**
 * Priority Calculator Class
 * Implements algorithm to prioritize barangays based on disaster severity and weather impact
 */

require_once __DIR__ . '/../config/Database.php';

class PriorityCalculator {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    /**
     * Calculate priority scores for all barangays with active disaster reports
     */
    public function calculateAllBarangayPriorities() {
        // Get all barangays with recent disaster reports
        $query = "SELECT DISTINCT b.id, b.name, b.population,
                         SUM(dr.affected_families) as total_affected_families,
                         SUM(dr.damaged_houses) as total_damaged_houses,
                         AVG(dr.temperature) as avg_temperature,
                         AVG(dr.humidity) as avg_humidity,
                         AVG(dr.wind_speed) as avg_wind_speed,
                         MAX(CASE WHEN dr.severity_level = 'critical' THEN 4 
                                  WHEN dr.severity_level = 'high' THEN 3
                                  WHEN dr.severity_level = 'medium' THEN 2
                                  ELSE 1 END) as max_severity
                  FROM barangays b
                  LEFT JOIN disaster_reports dr ON b.id = dr.barangay_id 
                  WHERE dr.id IS NOT NULL AND dr.status IN ('submitted', 'reviewed', 'prioritized')
                  GROUP BY b.id, b.name, b.population
                  ORDER BY b.id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $barangays = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $priority_data = [];

        foreach ($barangays as $barangay) {
            $priority_score = $this->calculatePriorityScore(
                $barangay['total_affected_families'],
                $barangay['total_damaged_houses'],
                $barangay['avg_temperature'],
                $barangay['avg_humidity'],
                $barangay['avg_wind_speed'],
                $barangay['max_severity']
            );

            $weather_impact = $this->calculateWeatherImpact(
                $barangay['avg_temperature'],
                $barangay['avg_humidity'],
                $barangay['avg_wind_speed']
            );

            $overall_severity = $this->determineSeverityLevel($priority_score);

            $priority_data[] = [
                'barangay_id' => $barangay['id'],
                'priority_score' => $priority_score,
                'affected_families_total' => $barangay['total_affected_families'],
                'damaged_houses_total' => $barangay['total_damaged_houses'],
                'weather_impact_score' => $weather_impact,
                'overall_severity' => $overall_severity
            ];
        }

        // Sort by priority score (descending)
        usort($priority_data, function($a, $b) {
            return $b['priority_score'] <=> $a['priority_score'];
        });

        // Assign ranking positions
        foreach ($priority_data as $key => $data) {
            $data['ranking_position'] = $key + 1;
            $this->saveBarangayPriority($data);
        }

        return $priority_data;
    }

    /**
     * Calculate overall priority score using weighted algorithm
     * Formula: (Affected Families * 0.4) + (Damaged Houses * 0.3) + (Weather Impact * 0.2) + (Severity Level * 0.1)
     */
    private function calculatePriorityScore($affected_families, $damaged_houses, $temperature, $humidity, $wind_speed, $severity_level) {
        $affected_families = $affected_families ?? 0;
        $damaged_houses = $damaged_houses ?? 0;
        $severity_level = $severity_level ?? 1;

        $weather_impact = $this->calculateWeatherImpact($temperature, $humidity, $wind_speed);

        // Normalize values to 0-100 scale
        $affected_families_norm = min(($affected_families / 1000) * 100, 100);
        $damaged_houses_norm = min(($damaged_houses / 500) * 100, 100);
        $severity_norm = ($severity_level / 4) * 100;

        // Weighted calculation
        $priority_score = 
            ($affected_families_norm * 0.4) +
            ($damaged_houses_norm * 0.3) +
            ($weather_impact * 0.2) +
            ($severity_norm * 0.1);

        return round($priority_score, 2);
    }

    /**
     * Calculate weather impact score
     * Considers temperature extremes, high humidity, and strong winds
     */
    private function calculateWeatherImpact($temperature, $humidity, $wind_speed) {
        $temperature = $temperature ?? 25;
        $humidity = $humidity ?? 50;
        $wind_speed = $wind_speed ?? 0;

        $temp_impact = 0;
        $humidity_impact = 0;
        $wind_impact = 0;

        // Temperature impact (extreme temperatures increase impact)
        if ($temperature < 10 || $temperature > 40) {
            $temp_impact = 40;
        } elseif ($temperature < 15 || $temperature > 35) {
            $temp_impact = 25;
        } else {
            $temp_impact = 10;
        }

        // Humidity impact (high humidity = more disease risk)
        if ($humidity > 85) {
            $humidity_impact = 30;
        } elseif ($humidity > 70) {
            $humidity_impact = 15;
        } else {
            $humidity_impact = 5;
        }

        // Wind impact (strong winds increase property damage risk)
        if ($wind_speed > 25) {
            $wind_impact = 30;
        } elseif ($wind_speed > 15) {
            $wind_impact = 20;
        } elseif ($wind_speed > 10) {
            $wind_impact = 10;
        } else {
            $wind_impact = 0;
        }

        return round(($temp_impact + $humidity_impact + $wind_impact) / 3, 2);
    }

    /**
     * Determine overall severity level based on priority score
     */
    private function determineSeverityLevel($priority_score) {
        if ($priority_score >= 75) {
            return 'Critical';
        } elseif ($priority_score >= 50) {
            return 'High';
        } elseif ($priority_score >= 25) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }

    /**
     * Save barangay priority ranking to database
     */
    private function saveBarangayPriority($priority_data) {
        // Check if priority record exists
        $check_query = "SELECT id FROM barangay_priority_ranking WHERE barangay_id = ?";
        $stmt = $this->conn->prepare($check_query);
        $stmt->bind_param('i', $priority_data['barangay_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing record
            $update_query = "UPDATE barangay_priority_ranking 
                             SET priority_score = ?, affected_families_total = ?, damaged_houses_total = ?,
                                 weather_impact_score = ?, overall_severity = ?, ranking_position = ?, calculated_at = CURRENT_TIMESTAMP
                             WHERE barangay_id = ?";

            $stmt = $this->conn->prepare($update_query);
            $stmt->bind_param(
                'diidsii',
                $priority_data['priority_score'],
                $priority_data['affected_families_total'],
                $priority_data['damaged_houses_total'],
                $priority_data['weather_impact_score'],
                $priority_data['overall_severity'],
                $priority_data['ranking_position'],
                $priority_data['barangay_id']
            );
        } else {
            // Insert new record
            $insert_query = "INSERT INTO barangay_priority_ranking 
                             (barangay_id, priority_score, affected_families_total, damaged_houses_total, 
                              weather_impact_score, overall_severity, ranking_position)
                             VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($insert_query);
            $stmt->bind_param(
                'idiidsi',
                $priority_data['barangay_id'],
                $priority_data['priority_score'],
                $priority_data['affected_families_total'],
                $priority_data['damaged_houses_total'],
                $priority_data['weather_impact_score'],
                $priority_data['overall_severity'],
                $priority_data['ranking_position']
            );
        }

        $stmt->execute();
    }

    /**
     * Get barangay priority ranking
     */
    public function getBarangayPriorities($limit = null) {
        $query = "SELECT bpr.*, b.name as barangay_name
                  FROM barangay_priority_ranking bpr
                  JOIN barangays b ON bpr.barangay_id = b.id
                  ORDER BY bpr.ranking_position ASC";

        if ($limit) {
            $query .= " LIMIT ?";
        }

        $stmt = $this->conn->prepare($query);
        if ($limit) {
            $stmt->bind_param('i', $limit);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get specific barangay priority details
     */
    public function getBarangayPriority($barangay_id) {
        $query = "SELECT bpr.*, b.name as barangay_name, b.population
                  FROM barangay_priority_ranking bpr
                  JOIN barangays b ON bpr.barangay_id = b.id
                  WHERE bpr.barangay_id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $barangay_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
?>
