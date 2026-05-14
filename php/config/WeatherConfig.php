<?php
/**
 * Open-Meteo API base URL (optional override).
 *
 * Default: public https://api.open-meteo.com/v1/forecast — no API key required
 * for non-commercial use per https://open-meteo.com/
 *
 * Override via:
 * - Environment variable OPEN_METEO_FORECAST_URL (full URL without trailing query)
 * - php/config/weather.local.php → ['open_meteo_forecast_url' => 'https://...']
 */

class WeatherConfig {
    public static function getOpenMeteoForecastUrl() {
        $fromEnv = getenv('OPEN_METEO_FORECAST_URL');
        if ($fromEnv !== false && trim($fromEnv) !== '') {
            return rtrim(trim($fromEnv), '/');
        }

        $path = __DIR__ . '/weather.local.php';
        if (is_readable($path)) {
            $cfg = include $path;
            if (is_array($cfg) && !empty($cfg['open_meteo_forecast_url'])) {
                return rtrim(trim((string) $cfg['open_meteo_forecast_url']), '/');
            }
        }

        return 'https://api.open-meteo.com/v1/forecast';
    }
}
