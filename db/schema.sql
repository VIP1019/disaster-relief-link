-- ============================================================================
-- ReliefLink — full MySQL schema + seed data (fits PHP app in /php)
--
-- phpMyAdmin: paste this whole file in the SQL tab and Go (no need to pick a
--   database first — CREATE + USE below selects `relieflink`).
-- CLI:  mysql -u root -p < db/schema.sql
--   or  mysql -u root -p relieflink < db/schema.sql
--
-- Demo password for ALL seeded logins: Demo@2026
-- ============================================================================

CREATE DATABASE IF NOT EXISTS relieflink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE relieflink;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS relief_distributions;
DROP TABLE IF EXISTS disaster_reports;
DROP TABLE IF EXISTS weather_api_logs;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS barangay_priority_ranking;
DROP TABLE IF EXISTS relief_inventory;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS evacuation_centers;
DROP TABLE IF EXISTS barangays;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Tables
-- ----------------------------------------------------------------------------

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    barangay_name VARCHAR(100) DEFAULT NULL COMMENT 'Must match barangays.name for report auto-link',
    user_type ENUM('barangay_official', 'admin') NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    address TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_barangay (barangay_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE barangays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    population INT DEFAULT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evacuation / shelter sites per barangay (MDRRMO can extend with PHP CRUD later)
CREATE TABLE evacuation_centers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barangay_id INT NOT NULL,
    center_name VARCHAR(150) NOT NULL,
    address TEXT,
    capacity INT NOT NULL DEFAULT 0 COMMENT 'Maximum persons',
    current_occupancy INT NOT NULL DEFAULT 0,
    contact_person VARCHAR(100) DEFAULT NULL,
    contact_phone VARCHAR(20) DEFAULT NULL,
    facilities TEXT DEFAULT NULL COMMENT 'e.g. kitchen, medical, toilets',
    status ENUM('open', 'full', 'closed') NOT NULL DEFAULT 'open',
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ec_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE CASCADE,
    INDEX idx_ec_barangay (barangay_id),
    INDEX idx_ec_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    status ENUM('submitted', 'reviewed', 'prioritized', 'relief_distributed') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_dr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_dr_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    INDEX idx_status (status),
    INDEX idx_barangay_id (barangay_id),
    INDEX idx_severity (severity_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE relief_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    unit_of_measure VARCHAR(20) DEFAULT NULL,
    description TEXT,
    reorder_level INT DEFAULT NULL,
    cost_per_unit DECIMAL(10, 2) DEFAULT NULL,
    added_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_user FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE relief_distributions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_id INT NOT NULL,
    barangay_id INT NOT NULL,
    inventory_id INT NOT NULL,
    quantity_distributed INT NOT NULL,
    distribution_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    distributed_by INT DEFAULT NULL,
    notes TEXT,
    CONSTRAINT fk_rd_report FOREIGN KEY (report_id) REFERENCES disaster_reports(id) ON DELETE CASCADE,
    CONSTRAINT fk_rd_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    CONSTRAINT fk_rd_inventory FOREIGN KEY (inventory_id) REFERENCES relief_inventory(id),
    CONSTRAINT fk_rd_user FOREIGN KEY (distributed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_barangay_id (barangay_id),
    INDEX idx_report_id (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE barangay_priority_ranking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barangay_id INT NOT NULL,
    priority_score DECIMAL(10, 2) DEFAULT NULL,
    affected_families_total INT DEFAULT NULL,
    damaged_houses_total INT DEFAULT NULL,
    weather_impact_score DECIMAL(10, 2) DEFAULT NULL,
    overall_severity VARCHAR(20) DEFAULT NULL,
    ranking_position INT DEFAULT NULL,
    calculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bpr_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    UNIQUE KEY unique_barangay (barangay_id),
    INDEX idx_priority_score (priority_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    report_id INT DEFAULT NULL,
    notification_type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_report FOREIGN KEY (report_id) REFERENCES disaster_reports(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE weather_api_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barangay_id INT DEFAULT NULL,
    api_response LONGTEXT DEFAULT NULL COMMENT 'Open-Meteo JSON payload (LONGTEXT for broad MariaDB/XAMPP support)',
    temperature DECIMAL(5, 2) DEFAULT NULL,
    humidity INT DEFAULT NULL,
    wind_speed DECIMAL(6, 2) DEFAULT NULL,
    weather_condition VARCHAR(200) DEFAULT NULL,
    api_call_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wal_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE SET NULL,
    INDEX idx_api_call_time (api_call_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(255) NOT NULL,
    user_id INT DEFAULT NULL,
    description TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_slog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Seed: barangays — Municipality of Daet, Camarines Norte (demo coordinates)
-- ----------------------------------------------------------------------------

-- 26 rows: official Daet barangays (per PSA / LGU lists) plus “Poblacion” as a demo downtown label (id 1) used by seeded captain account.
INSERT INTO barangays (name, municipality, province, population, latitude, longitude) VALUES
('Poblacion', 'Daet', 'Camarines Norte', 4200, 14.11280000, 122.95590000),
('Camambugan', 'Daet', 'Camarines Norte', 3100, 14.10500000, 122.94800000),
('Bagasbas', 'Daet', 'Camarines Norte', 5800, 14.13800000, 122.98200000),
('Alawihao', 'Daet', 'Camarines Norte', 2100, 14.12400000, 122.93800000),
('Awitan', 'Daet', 'Camarines Norte', 2800, 14.11800000, 122.92800000),
('Barangay I', 'Daet', 'Camarines Norte', 3200, 14.11450000, 122.95250000),
('Barangay II', 'Daet', 'Camarines Norte', 3000, 14.11400000, 122.95420000),
('Barangay III', 'Daet', 'Camarines Norte', 2900, 14.11320000, 122.95600000),
('Barangay IV', 'Daet', 'Camarines Norte', 2700, 14.11250000, 122.95780000),
('Barangay V', 'Daet', 'Camarines Norte', 2600, 14.11180000, 122.95920000),
('Barangay VI', 'Daet', 'Camarines Norte', 2500, 14.11100000, 122.96050000),
('Barangay VII', 'Daet', 'Camarines Norte', 2400, 14.11020000, 122.96180000),
('Barangay VIII', 'Daet', 'Camarines Norte', 2300, 14.10940000, 122.96300000),
('Bibirao', 'Daet', 'Camarines Norte', 1800, 14.10200000, 122.97000000),
('Borabod', 'Daet', 'Camarines Norte', 2200, 14.09800000, 122.96200000),
('Calasgasan', 'Daet', 'Camarines Norte', 3500, 14.12800000, 122.96800000),
('Cobangbang', 'Daet', 'Camarines Norte', 1900, 14.11600000, 122.97200000),
('Dogongan', 'Daet', 'Camarines Norte', 1700, 14.10000000, 122.95200000),
('Gahonon', 'Daet', 'Camarines Norte', 2400, 14.10600000, 122.95800000),
('Gubat', 'Daet', 'Camarines Norte', 2600, 14.10400000, 122.96500000),
('Lag-on', 'Daet', 'Camarines Norte', 2000, 14.12000000, 122.94800000),
('Magang', 'Daet', 'Camarines Norte', 3100, 14.11500000, 122.94000000),
('Mambalite', 'Daet', 'Camarines Norte', 1600, 14.09600000, 122.94000000),
('Mancruz', 'Daet', 'Camarines Norte', 1500, 14.09200000, 122.95500000),
('Pamorangon', 'Daet', 'Camarines Norte', 1400, 14.09000000, 122.96800000),
('San Isidro', 'Daet', 'Camarines Norte', 3300, 14.13200000, 122.99000000);

-- ----------------------------------------------------------------------------
-- Seed: evacuation centers (linked to barangay_id 1–3)
-- ----------------------------------------------------------------------------

INSERT INTO evacuation_centers (barangay_id, center_name, address, capacity, current_occupancy, contact_person, contact_phone, facilities, status, latitude, longitude) VALUES
(1, 'Daet Evacuation Center (Covered Court)', 'Rizal Street, Poblacion, Daet, Camarines Norte', 400, 220, 'Elena Ramos', '09171230000', 'Kitchen area, first-aid, separate toilets', 'open', 14.11300000, 122.95620000),
(1, 'Daet Central School', 'Vinzons Avenue, Poblacion, Daet', 600, 310, 'Elena Ramos', '09171230000', 'Classrooms, generator, water tanks', 'open', 14.11250000, 122.95550000),
(2, 'Camambugan Barangay Hall Annex', 'Camambugan, Daet', 150, 48, 'Maria Santos', '09181230001', 'Open hall, shared toilets', 'open', 14.10520000, 122.94820000),
(3, 'Bagasbas Multi-Purpose Hall', 'Bagasbas, Daet', 200, 0, 'Pedro Reyes', '09181230002', 'Stage area, parking for logistics', 'open', 14.13820000, 122.98220000);

-- ----------------------------------------------------------------------------
-- Seed: users (password for every account: Demo@2026)
-- ----------------------------------------------------------------------------

INSERT INTO users (username, email, password_hash, full_name, barangay_name, user_type, phone_number, address, is_active) VALUES
('admin', 'mdrrmo@daet.gov.ph', '$2y$10$j8KHna83VYN07qd.ma7WfOqr20iwiB763y.DmvpJOAywjuY3ITn1e', 'MDRRMO Administrator', 'MDRRMO', 'admin', NULL, NULL, 1),
('brgyuser', 'captain@poblacion-daet.gov.ph', '$2y$10$j8KHna83VYN07qd.ma7WfOqr20iwiB763y.DmvpJOAywjuY3ITn1e', 'Juan Dela Cruz', 'Poblacion', 'barangay_official', '09171234567', 'Poblacion Hall, Daet', 1),
('captain_cb', 'captain@camambugan-daet.gov.ph', '$2y$10$j8KHna83VYN07qd.ma7WfOqr20iwiB763y.DmvpJOAywjuY3ITn1e', 'Maria Santos', 'Camambugan', 'barangay_official', '09181230001', 'Camambugan Hall, Daet', 1),
('captain_bg', 'captain@bagasbas-daet.gov.ph', '$2y$10$j8KHna83VYN07qd.ma7WfOqr20iwiB763y.DmvpJOAywjuY3ITn1e', 'Pedro Reyes', 'Bagasbas', 'barangay_official', '09181230002', 'Bagasbas Hall, Daet', 1);

-- ----------------------------------------------------------------------------
-- Seed: relief inventory (added_by = admin id 1)
-- ----------------------------------------------------------------------------

INSERT INTO relief_inventory (item_name, category, quantity, unit_of_measure, description, reorder_level, cost_per_unit, added_by) VALUES
('Rice (25kg)', 'Food', 200, 'sack', 'Emergency rice packs', 40, 1200.00, 1),
('Bottled water (500ml)', 'Water', 5000, 'bottle', 'Drinking water', 1000, 10.00, 1),
('Hygiene kits', 'NFIs', 350, 'kit', 'Soap, toothpaste, sanitary supplies', 80, 250.00, 1),
('Family kits', 'NFIs', 120, 'kit', 'Blankets, mats, basic cookware', 30, 800.00, 1);

-- ----------------------------------------------------------------------------
-- Seed: disaster reports (multiple barangays → priority module has data)
-- ----------------------------------------------------------------------------

INSERT INTO disaster_reports (user_id, barangay_id, disaster_type, affected_families, damaged_houses, injured_count, death_count, description, weather_condition, temperature, humidity, wind_speed, severity_level, status) VALUES
(2, 1, 'Flood', 120, 45, 3, 0, 'Flooding along Vinzons Avenue and near public market; evacuation at Daet Central School.', 'Rain', 28.50, 88, 6.20, 'high', 'reviewed'),
(2, 1, 'Storm', 30, 12, 0, 0, 'Roof damage from strong winds; power lines affected in Poblacion sitio.', 'Clouds', 27.00, 82, 11.00, 'medium', 'submitted'),
(3, 2, 'Flood', 55, 22, 1, 0, 'River overflow in Camambugan; roads not passable to inner sitios.', 'Rain', 27.20, 90, 5.50, 'medium', 'submitted'),
(4, 3, 'Storm', 18, 8, 0, 0, 'Minor structural damage in Bagasbas; clearing operations ongoing.', 'Clouds', 26.80, 78, 9.00, 'low', 'reviewed');

-- ----------------------------------------------------------------------------
-- Seed: notifications
-- ----------------------------------------------------------------------------

INSERT INTO notifications (user_id, report_id, notification_type, message, is_read) VALUES
(2, 1, 'report_status', 'Your disaster report #1 has been marked as reviewed.', 0),
(2, 1, 'relief', 'Relief assessment scheduled for Poblacion. Stand by for updates.', 1),
(3, 3, 'report_status', 'Your disaster report has been received by MDRRMO.', 0),
(4, 4, 'report_status', 'Your disaster report has been reviewed.', 1),
(1, NULL, 'system', 'ReliefLink (Daet, Camarines Norte) seeded. Weather: Open-Meteo (no key) — use Admin → Weather → Force API Sync.', 1);

-- ----------------------------------------------------------------------------
-- Seed: priority ranking (initial snapshot; admin can recalculate via API)
-- ----------------------------------------------------------------------------

INSERT INTO barangay_priority_ranking (barangay_id, priority_score, affected_families_total, damaged_houses_total, weather_impact_score, overall_severity, ranking_position) VALUES
(1, 72.50, 150, 57, 18.00, 'High', 1),
(2, 45.20, 55, 22, 16.50, 'Medium', 2),
(3, 28.10, 18, 8, 12.00, 'Low', 3),
(4, 12.00, 0, 0, 8.00, 'Low', 4),
(5, 11.50, 0, 0, 8.00, 'Low', 5),
(6, 11.00, 0, 0, 8.00, 'Low', 6),
(7, 10.50, 0, 0, 8.00, 'Low', 7),
(8, 10.00, 0, 0, 8.00, 'Low', 8),
(9, 9.50, 0, 0, 8.00, 'Low', 9),
(10, 9.00, 0, 0, 8.00, 'Low', 10),
(11, 8.50, 0, 0, 8.00, 'Low', 11),
(12, 8.00, 0, 0, 8.00, 'Low', 12),
(13, 7.50, 0, 0, 8.00, 'Low', 13),
(14, 7.00, 0, 0, 8.00, 'Low', 14),
(15, 6.50, 0, 0, 8.00, 'Low', 15),
(16, 6.00, 0, 0, 8.00, 'Low', 16),
(17, 5.50, 0, 0, 8.00, 'Low', 17),
(18, 5.00, 0, 0, 8.00, 'Low', 18),
(19, 4.50, 0, 0, 8.00, 'Low', 19),
(20, 4.00, 0, 0, 8.00, 'Low', 20),
(21, 3.50, 0, 0, 8.00, 'Low', 21),
(22, 3.00, 0, 0, 8.00, 'Low', 22),
(23, 2.50, 0, 0, 8.00, 'Low', 23),
(24, 2.00, 0, 0, 8.00, 'Low', 24),
(25, 1.50, 0, 0, 8.00, 'Low', 25),
(26, 1.00, 0, 0, 8.00, 'Low', 26);

-- ----------------------------------------------------------------------------
-- Seed: one distribution (inventory row 1 quantity already reflects app logic if you re-distribute — keep qty consistent)
-- Note: first import — rice qty 200; this row records 40 sacks out (manual alignment with business logic)
-- ----------------------------------------------------------------------------

INSERT INTO relief_distributions (report_id, barangay_id, inventory_id, quantity_distributed, distributed_by, notes) VALUES
(1, 1, 1, 40, 1, 'Initial rice allocation to Poblacion evacuation center.');

UPDATE relief_inventory SET quantity = quantity - 40 WHERE id = 1;

-- ----------------------------------------------------------------------------
-- Seed: weather API logs + system logs (admin / weather pages)
-- ----------------------------------------------------------------------------

INSERT INTO weather_api_logs (barangay_id, api_response, temperature, humidity, wind_speed, weather_condition) VALUES
(1, '{"source":"seed","note":"Daet — Poblacion"}', 28.50, 88, 6.20, 'Rain'),
(2, '{"source":"seed","note":"Daet — Camambugan"}', 27.20, 90, 5.50, 'Rain'),
(3, '{"source":"seed","note":"Daet — Bagasbas"}', 26.80, 78, 9.00, 'Clouds');

INSERT INTO system_logs (action, user_id, description, ip_address) VALUES
('login', 1, 'Admin login (seed).', '127.0.0.1'),
('report_submit', 2, 'Disaster report submitted (seed).', '127.0.0.1');

-- ============================================================================
-- End of ReliefLink schema + seed
-- ============================================================================
