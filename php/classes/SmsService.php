<?php
/**
 * SmsService Class
 * Custom Semaphore SMS API Integration
 * Dependency-free PHP implementation using cURL to call Semaphore messages endpoint.
 */

require_once __DIR__ . '/SystemSettings.php';

class SmsService {
    
    /**
     * Clean and format Philippine phone numbers
     * Converts +639xx, 639xx, or 09xx into standard 11-digit 09xxxxxxxxx format or 639xxxxxxxxx format.
     * Semaphore accepts 09xxxxxxxx or 639xxxxxxxxx.
     */
    public static function formatPhoneNumber($phone) {
        // Strip all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);
        
        // Handle standard formats
        if (strpos($digits, '639') === 0 && strlen($digits) === 12) {
            return $digits; // 639xxxxxxxxx
        }
        if (strpos($digits, '09') === 0 && strlen($digits) === 11) {
            return $digits; // 09xxxxxxxxx
        }
        if (strpos($digits, '9') === 0 && strlen($digits) === 10) {
            return '0' . $digits; // Convert 9xxxxxxxxx to 09xxxxxxxxx
        }
        
        return $digits;
    }

    /**
     * Send an SMS message using the Semaphore API
     * 
     * @param string $toPhoneNumber Recipient's phone number
     * @param string $message The text message body (max 160 chars per SMS segment)
     * @return array{success: bool, message: string}
     */
    public static function sendSms($toPhoneNumber, $message) {
        $settings = new SystemSettings();
        
        $enabled = $settings->get('semaphore_sms_enabled', '0') === '1';
        $apiKey = $settings->get('semaphore_api_key', '');
        $senderName = $settings->get('semaphore_sender_name', 'SEMAPHORE');
        
        if (!$enabled) {
            return ['success' => false, 'message' => 'SMS notifications are currently disabled in settings.'];
        }
        
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Semaphore API key is not configured.'];
        }
        
        $cleanNumber = self::formatPhoneNumber($toPhoneNumber);
        if (empty($cleanNumber) || strlen($cleanNumber) < 10) {
            return ['success' => false, 'message' => 'Invalid phone number format.'];
        }
        
        // Semaphore REST API endpoint
        $url = "https://api.semaphore.co/api/v4/messages";
        
        // Post fields as expected by Semaphore API
        $fields = [
            'apikey' => $apiKey,
            'number' => $cleanNumber,
            'message' => $message
        ];
        
        if (!empty($senderName) && strtoupper($senderName) !== 'SEMAPHORE') {
            $fields['sendername'] = $senderName;
        }
        
        // Initialize cURL for the HTTP POST Request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        // Execute the API Call
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200 || $http_code === 201) {
            $resData = json_decode($response, true);
            if (is_array($resData) && !empty($resData)) {
                return [
                    'success' => true, 
                    'message' => 'SMS sent successfully.', 
                    'response' => $resData
                ];
            }
            return [
                'success' => true, 
                'message' => 'SMS request completed successfully, empty response.'
            ];
        } else {
            $errorMsg = "Semaphore API Error: HTTP " . $http_code . " | Response: " . $response . " | cURL Error: " . $error;
            error_log($errorMsg);
            
            // Try to extract dynamic error message if present in json
            $resData = json_decode($response, true);
            $reason = '';
            if (is_array($resData) && isset($resData['message'])) {
                $reason = ': ' . $resData['message'];
            }
            
            return [
                'success' => false, 
                'message' => 'Failed to send SMS via Semaphore' . $reason
            ];
        }
    }
}
?>
