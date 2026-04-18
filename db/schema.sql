-- ReliefLink Database Schema
-- MySQL Database for Disaster Relief Distribution and Priority Management System

-- Create Users Table (Barangay Officials & Admin)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    barangay_name VARCHAR(100),
    user_type ENUM('barangay_official', 'admin') NOT NULL,
    phone_number VARCHAR(20),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_type (user_type),
    INDEX idx_barangay (barangay_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Barangays Table
CREATE TABLE barangays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    municipality VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    population INT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Disaster Reports Table
CREATE TABLE disaster_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    barangay_id INT NOT NULL,
    disaster_type VARCHAR(100) NOT NULL,
    affected_families INT NOT NULL,
    damaged_houses INT NOT NULL,
    injured_count INT DEFAULT 0,
    death_count INT DEFAULT 0,
    description TEXT,
    weather_condition VARCHAR(200),
    temperature DECIMAL(5, 2),
    humidity INT,
    wind_speed DECIMAL(6, 2),
    severity_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('submitted', 'reviewed', 'prioritized', 'relief_distributed') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    INDEX idx_status (status),
    INDEX idx_barangay_id (barangay_id),
    INDEX idx_severity (severity_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Relief Inventory Table
CREATE TABLE relief_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    unit_of_measure VARCHAR(20),
    description TEXT,
    reorder_level INT,
    cost_per_unit DECIMAL(10, 2),
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Relief Distribution Table
CREATE TABLE relief_distributions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_id INT NOT NULL,
    barangay_id INT NOT NULL,
    inventory_id INT NOT NULL,
    quantity_distributed INT NOT NULL,
    distribution_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    distributed_by INT,
    notes TEXT,
    FOREIGN KEY (report_id) REFERENCES disaster_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    FOREIGN KEY (inventory_id) REFERENCES relief_inventory(id),
    FOREIGN KEY (distributed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_barangay_id (barangay_id),
    INDEX idx_report_id (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Barangay Priority Ranking Table
CREATE TABLE barangay_priority_ranking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barangay_id INT NOT NULL,
    priority_score DECIMAL(10, 2),
    affected_families_total INT,
    damaged_houses_total INT,
    weather_impact_score DECIMAL(10, 2),
    overall_severity VARCHAR(20),
    ranking_position INT,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    UNIQUE KEY unique_barangay (barangay_id),
    INDEX idx_priority_score (priority_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Notifications Table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    report_id INT,
    notification_type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (report_id) REFERENCES disaster_reports(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Weather API Logs Table
CREATE TABLE weather_api_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    barangay_id INT,
    api_response JSON,
    temperature DECIMAL(5, 2),
    humidity INT,
    wind_speed DECIMAL(6, 2),
    weather_condition VARCHAR(200),
    api_call_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE SET NULL,
    INDEX idx_api_call_time (api_call_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create System Logs Table
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action VARCHAR(255) NOT NULL,
    user_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
