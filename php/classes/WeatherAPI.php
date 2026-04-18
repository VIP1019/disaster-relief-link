<?php
/**
 * Weather API Integration Class
 * Handles OpenWeather API calls and data processing
 */

require_once __DIR__ . '/../config/Database.php';

class WeatherAPI {
    private $api_key = 'YOUR_OPENWEATHER_API_KEY'; // Replace with actual API key
    private $base_url = 'https://api.openweathermap.org/data/2.5/weather';
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    /**
     * Fetch weather data from OpenWeather API
     */
    public function getWeatherData($latitude, $longitude, $barangay_id = null) {
        // Build API URL
        $url = $this->base_url . "?lat={$latitude}&lon={$longitude}&appid={$this->api_key}&units=metric";

        // Make API request
        $response = @file_get_contents($url);
        
        if ($response === false) {
            return ['weather' => 'Unknown', 'temperature' => null, 'humidity' => null, 'wind_speed' => null];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['weather' => 'Unknown', 'temperature' => null, 'humidity' => null, 'wind_speed' => null];
        }

        // Extract relevant data
        $weather_condition = $data['weather'][0]['main'] ?? 'Unknown';
        $temperature = $data['main']['temp'] ?? null;
        $humidity = $data['main']['humidity'] ?? null;
        $wind_speed = $data['wind']['speed'] ?? null;

        // Log the API call
        if ($barangay_id) {
            $this->logWeatherAPICall($barangay_id, $data, $temperature, $humidity, $wind_speed, $weather_condition);
        }

        return [
            'weather' => $weather_condition,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'wind_speed' => $wind_speed
        ];
    }

    /**
     * Log weather API calls to database
     */
    private function logWeatherAPICall($barangay_id, $api_response, $temperature, $humidity, $wind_speed, $weather_condition) {
        $response_json = json_encode($api_response);
        
        $insert_query = "INSERT INTO weather_api_logs (barangay_id, api_response, temperature, humidity, wind_speed, weather_condition)
                         VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($insert_query);
        $stmt->bind_param('isdiis', $barangay_id, $response_json, $temperature, $humidity, $wind_speed, $weather_condition);
        $stmt->execute();
    }

    /**
     * Get historical weather data for a barangay
     */
    public function getWeatherHistory($barangay_id, $limit = 10) {
        $query = "SELECT temperature, humidity, wind_speed, weather_condition, api_call_time
                  FROM weather_api_logs
                  WHERE barangay_id = ?
                  ORDER BY api_call_time DESC
                  LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $barangay_id, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all recent weather data across all barangays
     */
    public function getAllRecentWeatherData($limit = 100) {
        $query = "SELECT b.name as barangay_name, w.temperature, w.humidity, w.wind_speed, w.weather_condition, w.api_call_time
                  FROM weather_api_logs w
                  JOIN barangays b ON w.barangay_id = b.id
                  ORDER BY w.api_call_time DESC
                  LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Set API key
     */
    public function setApiKey($api_key) {
        $this->api_key = $api_key;
    }
}
?>
