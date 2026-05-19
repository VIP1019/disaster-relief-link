<?php
/**
 * Database Initialization for Semaphore SMS Settings
 */

require_once __DIR__ . '/../php/classes/SystemSettings.php';

try {
    $settings = new SystemSettings();
    
    // Check and set defaults if not already present
    if ($settings->get('semaphore_sms_enabled') === null) {
        $settings->set('semaphore_sms_enabled', '0');
        echo "Initialized semaphore_sms_enabled = 0\n";
    }
    
    if ($settings->get('semaphore_api_key') === null) {
        $settings->set('semaphore_api_key', '');
        echo "Initialized semaphore_api_key = ''\n";
    }
    
    if ($settings->get('semaphore_sender_name') === null) {
        $settings->set('semaphore_sender_name', 'SEMAPHORE');
        echo "Initialized semaphore_sender_name = 'SEMAPHORE'\n";
    }
    
    echo "Database patch successfully checked/applied.\n";
} catch (Exception $e) {
    echo "Error applying patch: " . $e->getMessage() . "\n";
    exit(1);
}
?>
