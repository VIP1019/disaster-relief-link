-- Fix: Table 'relieflink.disaster_reports' doesn't exist in engine
-- Run in phpMyAdmin (SQL tab) or: mysql -u root -p relieflink < db/repair_disaster_reports.sql

USE relieflink;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS disaster_reports;

CREATE TABLE disaster_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    barangay_id INT NOT NULL,
    disaster_type VARCHAR(100) NOT NULL,
    affected_families INT NOT NULL,
    damaged_houses INT NOT NULL,
    injured_count INT NOT NULL DEFAULT 0,
    death_count INT NOT NULL DEFAULT 0,
    description TEXT,
    weather_condition VARCHAR(200) DEFAULT NULL,
    temperature DECIMAL(5, 2) DEFAULT NULL,
    humidity INT DEFAULT NULL,
    wind_speed DECIMAL(6, 2) DEFAULT NULL,
    severity_level ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    incident_latitude DECIMAL(10, 8) DEFAULT NULL COMMENT 'Pin from geographic sector map',
    incident_longitude DECIMAL(11, 8) DEFAULT NULL,
    geographic_sector_label VARCHAR(200) DEFAULT NULL,
    suggested_evacuation_center_id INT DEFAULT NULL,
    evacuation_suggestion_notes TEXT DEFAULT NULL,
    evacuation_suggested_at TIMESTAMP NULL DEFAULT NULL,
    evacuation_confirmed_at TIMESTAMP NULL DEFAULT NULL,
    evacuation_confirmation_notes TEXT DEFAULT NULL,
    proof_of_delivery_photo MEDIUMTEXT DEFAULT NULL,
    delivery_signature_data MEDIUMTEXT DEFAULT NULL,
    delivery_confirmed_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('submitted', 'reviewed', 'prioritized', 'relief_distributed', 'relief_received') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_dr_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    CONSTRAINT fk_dr_suggested_evacuation FOREIGN KEY (suggested_evacuation_center_id) REFERENCES evacuation_centers(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_barangay_id (barangay_id),
    INDEX idx_suggested_evacuation_center (suggested_evacuation_center_id),
    INDEX idx_severity (severity_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
