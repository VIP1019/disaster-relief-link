<?php
/**
 * Custom EmailJS REST API Integration
 * Dependency-free PHP implementation using cURL to call EmailJS send endpoint.
 */
class Mailer {
    private static $service_id = "service_6y5rd0d";
    private static $template_id = "template_z4zvd3g";
    private static $public_key = "DiJ6ZgGFANVP4wNq5"; // user_id in EmailJS
    private static $private_key = "SLY1tlpmwGrkUV5uCApmr"; // accessToken in EmailJS

    public static function sendEmail($toEmail, $toName, $subject, $htmlMessage) {
        $url = "https://api.emailjs.com/api/v1.0/email/send";

        // Construct the exact payload expected by EmailJS REST API
        $payload = [
            "service_id" => self::$service_id,
            "template_id" => self::$template_id,
            "user_id" => self::$public_key,
            "accessToken" => self::$private_key,
            "template_params" => [
                "to_email" => $toEmail,
                "to_name" => $toName,
                "subject" => $subject,
                "message" => $htmlMessage
            ]
        ];

        // Initialize cURL for the HTTP POST Request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        // Execute the API Call
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($http_code === 200) {
            return true;
        } else {
            error_log("EmailJS REST API Error: Code " . $http_code . " | Response: " . $response . " | cURL Error: " . $error);
            return false;
        }
    }
}
?>
