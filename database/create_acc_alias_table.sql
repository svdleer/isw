-- Create the reporting.acc_alias table for alias-to-CCAP hostname mappings
-- This table maps ABR/DBR/CBR aliases to their corresponding CCAP hostnames

-- Create the reporting database if it doesn't exist
CREATE DATABASE IF NOT EXISTS reporting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE reporting;

-- Create the acc_alias table
CREATE TABLE IF NOT EXISTS acc_alias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alias VARCHAR(255) NOT NULL,
    ccap_name VARCHAR(255) NOT NULL,
    description TEXT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_alias (alias),
    INDEX idx_ccap_name (ccap_name),
    INDEX idx_active (active),
    UNIQUE KEY unique_alias (alias)
);

-- Sample data for testing alias-to-CCAP mappings
-- These are example mappings - replace with your actual data
INSERT INTO acc_alias (alias, ccap_name, description, active) VALUES
('HV01ABR001', 'HLEN-LC0023-CCAP001', 'ABR device mapped to CCAP001', TRUE),
('HV01DBR002', 'HLEN-LC0023-CCAP002', 'DBR device mapped to CCAP002', TRUE),
('HV01CBR003', 'HLEN-LC0023-CCAP003', 'CBR device mapped to CCAP003', TRUE),
('GV01ABR001', 'GV-RC0011-CCAP003', 'ABR device mapped to GV CCAP003', TRUE),
('GV01DBR001', 'GV-RC0012-CCAP004', 'DBR device mapped to GV CCAP004', TRUE)
ON DUPLICATE KEY UPDATE 
    ccap_name = VALUES(ccap_name),
    description = VALUES(description),
    active = VALUES(active),
    updated_at = CURRENT_TIMESTAMP;

-- Show the created table structure
DESCRIBE acc_alias;

-- Show sample data
SELECT * FROM acc_alias ORDER BY alias;
