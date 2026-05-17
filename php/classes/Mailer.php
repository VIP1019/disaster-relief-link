<?php
/**
 * Custom Mailtrap REST API Integration
 * Fulfills strict "Third-Party API Integration" requirement using cURL and JSON payloads.
 */
class Mailer {
    // Extracted from your Mailtrap URL (mailtrap.io/sandboxes/4635098/...)
    private static $inbox_id = "4635098"; 
    
    // REPLACE THIS WITH YOUR MAILTRAP API TOKEN
    private static $api_token = "e4aeb708ce035e1b828c461cb3b70cc6";

    public static function sendEmail($toEmail, $toName, $subject, $htmlMessage) {
        if (self::$api_token === 'PASTE_YOUR_API_TOKEN_HERE') {
            error_log("Mailtrap API token missing. Email skipped.");
            return false;
        }

        // Mailtrap Sandbox REST API Endpoint
        $url = "https://sandbox.api.mailtrap.io/api/send/" . self::$inbox_id;

        // Construct the JSON Payload exactly as the API requires
        $data = [
            "from" => ["email" => "no-reply@relieflink.gov.ph", "name" => "ReliefLink Command"],
            "to" => [
                ["email" => $toEmail, "name" => $toName]
            ],
            "subject" => $subject,
            "html" => $htmlMessage
        ];

        // Initialize cURL for the HTTP POST Request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        // Pass the API Bearer Token in the headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . self::$api_token,
            "Content-Type: application/json"
        ]);

        // Execute the API Call
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            return true;
        } else {
            error_log("Mailtrap API Error: " . $response . " | cURL Error: " . $error);
            return false;
        }
    }
}
?>
