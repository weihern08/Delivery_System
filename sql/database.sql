-- Delivery System Database Schema
CREATE DATABASE IF NOT EXISTS `synergy1_weihern.Delivery-System` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `synergy1_weihern.Delivery-System`;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(120) NOT NULL UNIQUE,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','rider') NOT NULL DEFAULT 'rider',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS riders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    vehicle_number VARCHAR(50) DEFAULT NULL,
    status ENUM('online','offline') NOT NULL DEFAULT 'offline',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parcels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(60) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    status ENUM('pending','out_for_delivery','delivered','failed_delivery') NOT NULL DEFAULT 'pending',
    assigned_rider_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_rider_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parcel_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parcel_id INT NOT NULL,
    status ENUM('pending','out_for_delivery','delivered','failed_delivery') NOT NULL,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rider_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rider_id INT NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS delivery_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parcel_id INT NOT NULL,
    rider_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO users (name, username, email, password_hash, role)
VALUES
('admin', 'admin', 'admin@delivery.local', '$2y$10$.jybGnOavhJchBJE3F.eGuIzZi.8ZhvMhyI1WOM5mvh9jTbUjZSaG', 'admin'),
('Delivery Rider', 'rider', 'rider@delivery.local', '$2y$10$/gFpe0W9hEl2Ayko/WRZZeXMDzqLxoFWrfSsN7HBlLpy1cK02P6r6', 'rider')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    role = VALUES(role);

INSERT INTO riders (user_id, phone, vehicle_number, status)
VALUES
(2, '+2348012345678', 'RDR-001', 'offline')
ON DUPLICATE KEY UPDATE
    phone = VALUES(phone),
    vehicle_number = VALUES(vehicle_number),
    status = VALUES(status);

INSERT INTO parcels (tracking_number, address, status, assigned_rider_id)
VALUES
('TRK000001', 'Sunway Pyramid, Bandar Sunway, Selangor, Malaysia', 'pending', 2),
('TRK000002', 'Mid Valley Megamall, Kuala Lumpur, Malaysia', 'pending', 2),
('TRK000003', 'Pavilion KL, Bukit Bintang, Kuala Lumpur, Malaysia', 'out_for_delivery', 2),
('TRK000004', 'Bangsar Shopping Centre, Kuala Lumpur, Malaysia', 'pending', NULL),
('TRK000005', '1 Utama Shopping Centre, Petaling Jaya, Selangor, Malaysia', 'pending', NULL),
('TRK000006', 'Jalan SS 15, Subang Jaya, Selangor, Malaysia', 'pending', NULL),
('TRK000007', 'Plaza Gurney, George Town, Penang, Malaysia', 'pending', NULL),
('TRK000008', 'Prangin Mall, George Town, Penang, Malaysia', 'pending', 2)
ON DUPLICATE KEY UPDATE
    address = VALUES(address),
    status = VALUES(status),
    assigned_rider_id = VALUES(assigned_rider_id);

INSERT INTO parcel_status_history (parcel_id, status, remarks)
VALUES
(1, 'pending', 'Parcel assigned to rider'),
(2, 'pending', 'Parcel assigned to rider'),
(3, 'out_for_delivery', 'Out for delivery'),
(4, 'pending', 'Available for claiming'),
(5, 'pending', 'Available for claiming'),
(6, 'pending', 'Available for claiming'),
(7, 'pending', 'Available for claiming'),
(8, 'pending', 'Parcel assigned to rider')
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    remarks = VALUES(remarks);

INSERT INTO activity_logs (user_id, action, ip_address)
VALUES
(1, 'system_initialized', '127.0.0.1'),
(2, 'rider_logged_in', '127.0.0.1')
ON DUPLICATE KEY UPDATE
    action = VALUES(action),
    ip_address = VALUES(ip_address);
