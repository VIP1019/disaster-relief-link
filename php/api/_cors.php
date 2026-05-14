<?php
/**
 * Shared CORS + OPTIONS preflight for ReliefLink JSON APIs.
 * Include at the top of each endpoint file (before auth checks).
 */
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
