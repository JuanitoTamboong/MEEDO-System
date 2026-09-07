-- MEEDO System database
-- MariaDB/MySQL schema and initial login accounts.
-- Import this file once in phpMyAdmin or the MySQL client.

CREATE DATABASE IF NOT EXISTS meedo_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE meedo_system;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS login (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('Administrator', 'Meedo Personnel', 'Treasury') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_login_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE login
    MODIFY role ENUM('Administrator', 'Meedo Personnel', 'Treasury') NOT NULL;

CREATE TABLE IF NOT EXISTS sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_name VARCHAR(100) NOT NULL,
    icon_class VARCHAR(50) NOT NULL DEFAULT 'Store',
    display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section_name (section_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stalls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stall_number VARCHAR(20) NOT NULL,
    section_id INT UNSIGNED NULL,
    tenant_name VARCHAR(100) NULL,
    status ENUM('Occupied', 'Vacant') NOT NULL DEFAULT 'Vacant',
    monthly_rent DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stall_number (stall_number),
    KEY idx_stalls_section (section_id),
    KEY idx_stalls_status (status),
    CONSTRAINT fk_stalls_section
        FOREIGN KEY (section_id) REFERENCES sections (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NULL,
    contact_number VARCHAR(20) NOT NULL,
    street VARCHAR(100) NULL,
    barangay VARCHAR(50) NULL,
    city VARCHAR(50) NULL,
    stall_id INT UNSIGNED NULL,
    business_name VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tenants_stall (stall_id),
    KEY idx_tenants_status (status),
    KEY idx_tenants_name (full_name),
    CONSTRAINT fk_tenants_stall
        FOREIGN KEY (stall_id) REFERENCES stalls (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stall_id INT UNSIGNED NULL,
    tenant_name VARCHAR(100) NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATE NULL,
    due_date DATE NOT NULL,
    receipt_number VARCHAR(50) NULL,
    month_covered DATE NULL,
    status ENUM('Paid', 'Pending', 'Overdue') NOT NULL DEFAULT 'Pending',
    penalty DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payments_stall_month (stall_id, month_covered),
    KEY idx_payments_status (status),
    KEY idx_payments_date (payment_date),
    KEY idx_payments_stall (stall_id),
    CONSTRAINT fk_payments_stall
        FOREIGN KEY (stall_id) REFERENCES stalls (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    stall_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Active', 'Expired', 'Terminated') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_contract_tenant_stall (tenant_id, stall_id),
    KEY idx_contracts_stall (stall_id),
    KEY idx_contracts_end_date (end_date),
    CONSTRAINT fk_contracts_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_contracts_stall
        FOREIGN KEY (stall_id) REFERENCES stalls (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_extensions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,
    previous_end_date DATE NOT NULL,
    new_end_date DATE NOT NULL,
    months INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_extensions_contract (contract_id),
    CONSTRAINT fk_extensions_contract
        FOREIGN KEY (contract_id) REFERENCES contracts (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    username VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created_at (created_at),
    KEY idx_audit_role (role),
    KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO login (username, password, role)
VALUES
    ('admin', '$2y$10$.ahwmub1WGDt44dsT1Ddqe0y6dhWYIUXBDX.WLrP4XCnbv1Cg0FcC', 'Administrator'),
    ('meedopersonnel', '$2y$10$kkBimm4GcWz575ahwk/qNe/L6lgya6vXpStrj7FM1MpqevFahKoBK', 'Meedo Personnel'),
    ('treasury', '$2y$10$BNxB09eLarkFe1hlKpurtekRczXFNA.kKThx6F3XQMYg5Io.kW7fO', 'Treasury')
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    role = VALUES(role);

SET FOREIGN_KEY_CHECKS = 1;
