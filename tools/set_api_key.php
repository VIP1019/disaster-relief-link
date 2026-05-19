<?php
/**
 * Update Semaphore API Key in database system_settings.
 */

require_once __DIR__ . '/../php/classes/SystemSettings.php';

try {
    $settings = new SystemSettings();
    
    // Set API Key, enable SMS, and set Sender Name
    $settings->set('semaphore_api_key', '3d81194ec2cf0d9b33c8221724d35887');
    $settings->set('semaphore_sms_enabled', '1');
    $settings->set('semaphore_sender_name', 'SEMAPHORE');
    
    echo "SUCCESS: Semaphore SMS configured and enabled successfully in the system settings.\n";
    echo "semaphore_api_key = '3d81194ec2cf0d9b33c8221724d35887'\n";
    echo "semaphore_sms_enabled = '1'\n";
    echo "semaphore_sender_name = 'SEMAPHORE'\n";
} catch (Exception $e) {
    echo "ERROR updating settings: " . $e->getMessage() . "\n";
    exit(1);
}
?>
