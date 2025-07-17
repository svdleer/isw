-- Updated CMDB Database Schema with Admin User and API Key Management
-- Create database and table structure for the ISW CMDB API

CREATE DATABASE IF NOT EXISTS cmdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cmdb;

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_is_active (is_active)
);

-- API Keys table (enhanced)
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    usage_count INT DEFAULT 0,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    
    INDEX idx_api_key (api_key),
    INDEX idx_is_active (is_active),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Devices table
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    description TEXT,
    device_type ENUM('server', 'network', 'storage', 'other') DEFAULT 'server',
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_hostname (hostname),
    INDEX idx_ip_address (ip_address),
    INDEX idx_device_type (device_type),
    INDEX idx_status (status)
);

-- Default admin user (password: admin123 - change this!)
INSERT INTO admin_users (username, password_hash, email, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', TRUE);

-- Sample API keys
INSERT INTO api_keys (key_name, api_key, description, is_active, created_by) VALUES
('Default Development Key', 'dev-key-12345', 'Development API key for testing', TRUE, 1),
('Production Key', 'prod-key-67890', 'Production API key', TRUE, 1);

-- Sample devices data
INSERT INTO devices (hostname, ip_address, description, device_type, status, location) VALUES
('GV-RC0011-CCAP003', '192.168.1.100', 'CCAP Server 003', 'server', 'active', 'Datacenter A'),
('GV-RC0012-CCAP004', '192.168.1.101', 'CCAP Server 004', 'server', 'active', 'Datacenter A'),
('AB-XY1234-CCAP001', '192.168.1.102', 'CCAP Server 001', 'server', 'active', 'Datacenter B'),
('CD-EF5678-CCAP002', '192.168.1.103', 'CCAP Server 002', 'server', 'maintenance', 'Datacenter B'),
('MN-OP9876-CCAP005', '10.0.1.50', 'CCAP Server 005', 'server', 'active', 'Datacenter C'),
('QR-ST5432-CCAP006', '10.0.1.51', 'CCAP Server 006', 'server', 'inactive', 'Datacenter C'),
('UV-WX1111-CCAP007', '172.16.1.25', 'CCAP Server 007', 'server', 'active', 'Remote Site'),
('YZ-AB2222-CCAP008', '172.16.1.26', 'CCAP Server 008', 'server', 'active', 'Remote Site');
