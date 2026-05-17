-- ReliefLink feature upgrade: proof of delivery, deploy deductions, road hazards
USE relieflink;

CREATE TABLE IF NOT EXISTS road_hazards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hazard_type ENUM('landslide', 'flood', 'blocked_road') NOT NULL DEFAULT 'blocked_road',
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    label VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rh_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_hazard_type (hazard_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE disaster_reports
    ADD COLUMN proof_of_delivery_photo MEDIUMTEXT NULL COMMENT 'Delivery photo base64' AFTER evacuation_confirmation_notes,
    ADD COLUMN delivery_signature_data MEDIUMTEXT NULL AFTER proof_of_delivery_photo,
    ADD COLUMN delivery_confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER delivery_signature_data;

ALTER TABLE disaster_reports
    MODIFY COLUMN status ENUM('submitted', 'reviewed', 'prioritized', 'relief_distributed', 'relief_received') NOT NULL DEFAULT 'submitted';
