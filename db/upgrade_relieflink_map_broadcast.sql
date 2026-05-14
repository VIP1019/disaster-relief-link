-- Run once on an existing ReliefLink DB (before new installs from full schema.sql).
USE relieflink;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('emergency_broadcast_active', '0'),
('emergency_broadcast_title', 'No active region-wide hazard'),
('emergency_broadcast_body', 'When MDRRMO activates a broadcast, the title and advisory appear here for all barangay accounts.'),
('emergency_broadcast_protocol_url', 'https://www.pagasa.dost.gov.ph/tropical-cyclone-bulletin');

-- If columns already exist, skip or run individual statements:
ALTER TABLE disaster_reports
    ADD COLUMN incident_latitude DECIMAL(10, 8) NULL DEFAULT NULL COMMENT 'Pin from geographic sector map' AFTER severity_level,
    ADD COLUMN incident_longitude DECIMAL(11, 8) NULL DEFAULT NULL AFTER incident_latitude,
    ADD COLUMN geographic_sector_label VARCHAR(200) NULL DEFAULT NULL AFTER incident_longitude;

ALTER TABLE disaster_reports
    ADD COLUMN suggested_evacuation_center_id INT NULL DEFAULT NULL AFTER geographic_sector_label,
    ADD COLUMN evacuation_suggestion_notes TEXT NULL DEFAULT NULL AFTER suggested_evacuation_center_id,
    ADD COLUMN evacuation_suggested_at TIMESTAMP NULL DEFAULT NULL AFTER evacuation_suggestion_notes,
    ADD COLUMN evacuation_confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER evacuation_suggested_at,
    ADD COLUMN evacuation_confirmation_notes TEXT NULL DEFAULT NULL AFTER evacuation_confirmed_at,
    ADD INDEX idx_suggested_evacuation_center (suggested_evacuation_center_id),
    ADD CONSTRAINT fk_dr_suggested_evacuation FOREIGN KEY (suggested_evacuation_center_id) REFERENCES evacuation_centers(id) ON DELETE SET NULL;
