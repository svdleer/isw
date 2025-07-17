-- CMDB Database Schema
-- Create database and table structure for the ISW CMDB API

CREATE DATABASE IF NOT EXISTS cmdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cmdb;

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

-- Sample data
INSERT INTO devices (hostname, ip_address, description, device_type, status, location) VALUES
('GV-RC0011-CCAP003', '192.168.1.100', 'CCAP Server 003', 'server', 'active', 'Datacenter A'),
('GV-RC0012-CCAP004', '192.168.1.101', 'CCAP Server 004', 'server', 'active', 'Datacenter A'),
('AB-XY1234-CCAP001', '192.168.1.102', 'CCAP Server 001', 'server', 'active', 'Datacenter B'),
('CD-EF5678-CCAP002', '192.168.1.103', 'CCAP Server 002', 'server', 'maintenance', 'Datacenter B'),
('MN-OP9876-CCAP005', '10.0.1.50', 'CCAP Server 005', 'server', 'active', 'Datacenter C'),
('QR-ST5432-CCAP006', '10.0.1.51', 'CCAP Server 006', 'server', 'inactive', 'Datacenter C'),
('UV-WX1111-CCAP007', '172.16.1.25', 'CCAP Server 007', 'server', 'active', 'Remote Site'),
('YZ-AB2222-CCAP008', '172.16.1.26', 'CCAP Server 008', 'server', 'active', 'Remote Site');

-- API Keys table (optional - for more sophisticated key management)
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    
    INDEX idx_api_key (api_key),
    INDEX idx_is_active (is_active)
);

-- Sample API keys
INSERT INTO api_keys (key_name, api_key, description, is_active) VALUES
('Default Key', 'your-api-key-here', 'Default API key for testing', TRUE),
('Client Key', 'another-valid-key', 'Client application key', TRUE);
