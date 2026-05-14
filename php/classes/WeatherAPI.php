<?php
/**
 * Weather integration — Open-Meteo Forecast API (current conditions).
 *
 * @see https://open-meteo.com/ — free access for non-commercial use, no API key.
 * @see https://open-meteo.com/en/docs (Forecast API → current parameters)
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/WeatherConfig.php';

class WeatherAPI {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function isKeyConfigured() {
        return WeatherConfig::getOpenMeteoForecastUrl() !== '';
    }

    public function getProvider() {
        return 'open-meteo';
    }

    /**
     * Map WMO weather code (Open-Meteo) to a short English label.
     */
    public static function wmoCodeToCondition($code) {
        $c = (int) $code;
        if ($c === 0) {
            return 'Clear';
        }
        if ($c >= 1 && $c <= 3) {
            return ['', 'Mainly clear', 'Partly cloudy', 'Overcast'][$c] ?? 'Clouds';
        }
        if ($c === 45 || $c === 48) {
            return 'Fog';
        }
        if ($c >= 51 && $c <= 57) {
            return 'Drizzle';
        }
        if ($c >= 61 && $c <= 67) {
            return 'Rain';
        }
        if ($c >= 71 && $c <= 77) {
            return 'Snow';
        }
        if ($c >= 80 && $c <= 82) {
            return 'Rain showers';
        }
        if ($c === 85 || $c === 86) {
            return 'Snow showers';
        }
        if ($c >= 95 && $c <= 99) {
            return 'Thunderstorm';
        }
        return 'Unknown';
    }

    /**
     * Simple flood-relevant index (0–100) from Open-Meteo `current` fields.
     */
    public static function computeFloodRiskFromCurrent(array $cur) {
        $score = 0;
        $prob = isset($cur['precipitation_probability']) ? (int) $cur['precipitation_probability'] : 0;
        $score += (int) round(min(45, $prob * 0.45));

        $prec = (float) ($cur['precipitation'] ?? 0) + (float) ($cur['rain'] ?? 0) + (float) ($cur['showers'] ?? 0);
        if ($prec >= 5.0) {
            $score += 38;
        } elseif ($prec >= 1.0) {
            $score += 24;
        } elseif ($prec > 0) {
            $score += 12;
        }

        $code = (int) ($cur['weather_code'] ?? -1);
        if (($code >= 61 && $code <= 67) || ($code >= 80 && $code <= 82) || ($code >= 95 && $code <= 99)) {
            $score += 28;
        } elseif ($code >= 51 && $code <= 57) {
            $score += 14;
        }

        $score = min(100, $score);
        $level = 'LOW';
        if ($score >= 75) {
            $level = 'EXTREME';
        } elseif ($score >= 50) {
            $level = 'HIGH';
        } elseif ($score >= 25) {
            $level = 'MODERATE';
        }

        return ['score' => $score, 'level' => $level];
    }

    private function httpGet($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'ReliefLink/1.0 (PHP; Open-Meteo)',
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($errno !== 0 || $body === false || $http >= 400) {
                return null;
            }
            return $body;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 25, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : $body;
    }

    private function buildForecastUrl($latitude, $longitude) {
        $base = WeatherConfig::getOpenMeteoForecastUrl();
        $lat = (float) $latitude;
        $lon = (float) $longitude;
        $params = http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'current' => implode(',', [
                'temperature_2m',
                'relative_humidity_2m',
                'wind_speed_10m',
                'weather_code',
                'surface_pressure',
                'precipitation',
                'rain',
                'showers',
                'precipitation_probability',
            ]),
            'wind_speed_unit' => 'ms',
            'timezone' => 'Asia/Manila',
        ], '', '&', PHP_QUERY_RFC3986);

        return $base . '?' . $params;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWeatherData($latitude, $longitude, $barangay_id = null) {
        $url = $this->buildForecastUrl($latitude, $longitude);
        $response = $this->httpGet($url);

        if ($response === null || $response === '') {
            return [
                'weather' => 'Unknown',
                'temperature' => null,
                'humidity' => null,
                'wind_speed' => null,
                'error' => 'Could not reach Open-Meteo (network, SSL, or firewall). Ensure outbound HTTPS to api.open-meteo.com is allowed.',
            ];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return ['weather' => 'Unknown', 'temperature' => null, 'humidity' => null, 'wind_speed' => null, 'error' => 'Invalid JSON from Open-Meteo'];
        }

        if (!empty($data['error'])) {
            $reason = isset($data['reason']) ? (string) $data['reason'] : 'Open-Meteo error';
            return ['weather' => 'Unknown', 'temperature' => null, 'humidity' => null, 'wind_speed' => null, 'error' => $reason];
        }

        $cur = $data['current'] ?? null;
        if (!is_array($cur)) {
            return ['weather' => 'Unknown', 'temperature' => null, 'humidity' => null, 'wind_speed' => null, 'error' => 'No current block in Open-Meteo response'];
        }

        $code = isset($cur['weather_code']) ? (int) $cur['weather_code'] : -1;
        $weather_condition = $code >= 0 ? self::wmoCodeToCondition($code) : 'Unknown';
        $temperature = isset($cur['temperature_2m']) ? (float) $cur['temperature_2m'] : null;
        $humidity = isset($cur['relative_humidity_2m']) ? (int) $cur['relative_humidity_2m'] : null;
        $wind_speed = isset($cur['wind_speed_10m']) ? (float) $cur['wind_speed_10m'] : null;
        $surface_pressure = isset($cur['surface_pressure']) ? (float) $cur['surface_pressure'] : null;
        $precip_mm = (float) ($cur['precipitation'] ?? 0) + (float) ($cur['rain'] ?? 0) + (float) ($cur['showers'] ?? 0);
        $precip_mm = round($precip_mm, 2);
        $precip_prob = isset($cur['precipitation_probability']) ? (int) $cur['precipitation_probability'] : null;
        $flood = self::computeFloodRiskFromCurrent($cur);

        if ($barangay_id) {
            $this->logWeatherAPICall((int) $barangay_id, $data, $temperature, $humidity, $wind_speed, $weather_condition);
        }

        return [
            'weather' => $weather_condition,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'wind_speed' => $wind_speed,
            'surface_pressure_hpa' => $surface_pressure,
            'precipitation_mm' => $precip_mm,
            'precipitation_probability' => $precip_prob,
            'flood_risk_score' => $flood['score'],
            'flood_risk_level' => $flood['level'],
        ];
    }

    public function syncAllBarangays() {
        $q = 'SELECT id, latitude, longitude, name FROM barangays ORDER BY id';
        $res = $this->conn->query($q);
        if (!$res) {
            return ['success' => false, 'message' => 'Database error listing barangays', 'synced' => 0];
        }

        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $errors = [];
        $ok = 0;

        if (count($rows) === 0) {
            return ['success' => true, 'message' => 'No barangays found to sync.', 'synced' => 0, 'errors' => []];
        }

        foreach ($rows as $row) {
            $bid = (int) $row['id'];
            $lat = (float) $row['latitude'];
            $lon = (float) $row['longitude'];
            $out = $this->getWeatherData($lat, $lon, $bid);
            if (!empty($out['error'])) {
                $errors[] = $row['name'] . ': ' . $out['error'];
            } else {
                $ok++;
            }
        }

        if ($ok === 0) {
            return [
                'success' => false,
                'message' => 'Open-Meteo did not return usable data for any barangay. Check HTTPS access to api.open-meteo.com and try again later.',
                'synced' => 0,
                'errors' => $errors,
            ];
        }

        return [
            'success' => true,
            'message' => 'Synced ' . $ok . ' of ' . count($rows) . ' barangay location(s) via Open-Meteo.',
            'synced' => $ok,
            'errors' => $errors,
        ];
    }

    private function logWeatherAPICall($barangay_id, $api_response, $temperature, $humidity, $wind_speed, $weather_condition) {
        $response_json = json_encode($api_response);
        if ($response_json === false) {
            $response_json = '{}';
        }

        $insert_query = "INSERT INTO weather_api_logs (barangay_id, api_response, temperature, humidity, wind_speed, weather_condition)
                         VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($insert_query);
        $temp = $temperature === null ? 0.0 : (float) $temperature;
        $hum = $humidity === null ? 0 : (int) $humidity;
        $wind = $wind_speed === null ? 0.0 : (float) $wind_speed;
        $stmt->bind_param('isdids', $barangay_id, $response_json, $temp, $hum, $wind, $weather_condition);
        $stmt->execute();
    }

    /**
     * Merge DB log row with parsed Open-Meteo `current` from api_response (when present).
     *
     * @return array<string, mixed>
     */
    public static function enrichLogRow($log) {
        $base = [
            'temperature' => isset($log['temperature']) ? (float) $log['temperature'] : null,
            'humidity' => isset($log['humidity']) ? (int) $log['humidity'] : null,
            'wind_speed' => isset($log['wind_speed']) ? (float) $log['wind_speed'] : null,
            'weather_condition' => $log['weather_condition'] ?? null,
            'api_call_time' => $log['api_call_time'] ?? null,
            'surface_pressure_hpa' => null,
            'precipitation_mm' => null,
            'precipitation_probability' => null,
            'flood_risk_score' => null,
            'flood_risk_level' => null,
        ];
        $parsed = json_decode($log['api_response'] ?? '', true);
        if (!is_array($parsed) || !isset($parsed['current']) || !is_array($parsed['current'])) {
            return $base;
        }
        $cur = $parsed['current'];
        if (isset($cur['surface_pressure'])) {
            $base['surface_pressure_hpa'] = (float) $cur['surface_pressure'];
        }
        $p = (float) ($cur['precipitation'] ?? 0) + (float) ($cur['rain'] ?? 0) + (float) ($cur['showers'] ?? 0);
        $base['precipitation_mm'] = round($p, 2);
        if (isset($cur['precipitation_probability'])) {
            $base['precipitation_probability'] = (int) $cur['precipitation_probability'];
        }
        $fr = self::computeFloodRiskFromCurrent($cur);
        $base['flood_risk_score'] = $fr['score'];
        $base['flood_risk_level'] = $fr['level'];

        return $base;
    }

    /**
     * One row per barangay (all in DB), latest log + parsed Open-Meteo fields.
     *
     * @return array{rows: list<array>, summary: array}
     */
    public function getDashboardWeatherByBarangay() {
        $stmt = $this->conn->prepare('SELECT id, name, latitude, longitude FROM barangays ORDER BY name ASC');
        $stmt->execute();
        $brgys = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $rows = [];

        $lq = $this->conn->prepare(
            'SELECT temperature, humidity, wind_speed, weather_condition, api_call_time, api_response
             FROM weather_api_logs WHERE barangay_id = ? ORDER BY api_call_time DESC, id DESC LIMIT 1'
        );

        foreach ($brgys as $b) {
            $bid = (int) $b['id'];
            $lq->bind_param('i', $bid);
            $lq->execute();
            $log = $lq->get_result()->fetch_assoc();

            $row = [
                'barangay_id' => $bid,
                'barangay_name' => $b['name'],
                'latitude' => (float) $b['latitude'],
                'longitude' => (float) $b['longitude'],
                'temperature' => null,
                'humidity' => null,
                'wind_speed' => null,
                'weather_condition' => null,
                'api_call_time' => null,
                'surface_pressure_hpa' => null,
                'precipitation_mm' => null,
                'precipitation_probability' => null,
                'flood_risk_score' => null,
                'flood_risk_level' => null,
            ];

            if ($log) {
                $en = self::enrichLogRow($log);
                $row['temperature'] = $en['temperature'];
                $row['humidity'] = $en['humidity'];
                $row['wind_speed'] = $en['wind_speed'];
                $row['weather_condition'] = $en['weather_condition'];
                $row['api_call_time'] = $en['api_call_time'];
                $row['surface_pressure_hpa'] = $en['surface_pressure_hpa'];
                $row['precipitation_mm'] = $en['precipitation_mm'];
                $row['precipitation_probability'] = $en['precipitation_probability'];
                $row['flood_risk_score'] = $en['flood_risk_score'];
                $row['flood_risk_level'] = $en['flood_risk_level'];
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'summary' => self::summarizeAreaDashboard($rows)];
    }

    /**
     * @param list<array> $rows
     */
    private static function summarizeAreaDashboard(array $rows) {
        $hum = [];
        $press = [];
        $scores = [];
        $levels = [];
        $winds = [];

        foreach ($rows as $r) {
            if ($r['humidity'] !== null) {
                $hum[] = (int) $r['humidity'];
            }
            if ($r['surface_pressure_hpa'] !== null) {
                $press[] = (float) $r['surface_pressure_hpa'];
            }
            if ($r['flood_risk_score'] !== null) {
                $scores[] = (int) $r['flood_risk_score'];
                $levels[] = $r['flood_risk_level'] ?? 'LOW';
            }
            if ($r['wind_speed'] !== null) {
                $winds[] = (float) $r['wind_speed'];
            }
        }

        $maxScore = count($scores) ? max($scores) : null;
        $worst = 'LOW';
        $order = ['LOW' => 0, 'MODERATE' => 1, 'HIGH' => 2, 'EXTREME' => 3];
        foreach ($levels as $lv) {
            $u = strtoupper((string) $lv);
            if (isset($order[$u]) && $order[$u] > $order[$worst]) {
                $worst = $u;
            }
        }

        $meanPress = null;
        if (count($press) > 0) {
            $meanPress = round(array_sum($press) / count($press), 1);
        }

        $pressure_note = '—';
        if ($meanPress !== null) {
            if ($meanPress < 1000.0) {
                $pressure_note = 'Below typical (unsettled)';
            } elseif ($meanPress < 1010.0) {
                $pressure_note = 'Slightly low';
            } elseif ($meanPress <= 1020.0) {
                $pressure_note = 'Near average';
            } else {
                $pressure_note = 'High (stable air)';
            }
        }

        $hum_max = count($hum) ? max($hum) : null;
        $hum_note = '—';
        if ($hum_max !== null) {
            if ($hum_max >= 85) {
                $hum_note = 'High humidity';
            } elseif ($hum_max >= 70) {
                $hum_note = 'Moderate';
            } else {
                $hum_note = 'Comfortable range';
            }
        }

        return [
            'humidity_max' => $hum_max,
            'humidity_note' => $hum_note,
            'pressure_mean_hpa' => $meanPress,
            'pressure_note' => $pressure_note,
            'flood_max_score' => $maxScore,
            'flood_worst_level' => $worst,
            'wind_max_ms' => count($winds) ? round(max($winds), 2) : null,
            'barangay_count' => count($rows),
        ];
    }

    public function getWeatherHistory($barangay_id, $limit = 10) {
        $barangay_id = (int) $barangay_id;
        $limit = max(1, min(500, (int) $limit));

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

    public function getAllRecentWeatherData($limit = 100) {
        $limit = max(1, min(500, (int) $limit));

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
}
