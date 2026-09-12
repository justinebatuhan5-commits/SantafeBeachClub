-- Santa Fe Beach Club Database Schema

--CREATE DATABASE IF NOT EXISTS santafe_beach_club;
--USE santafe_beach_club;

-- Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    price_per_night DECIMAL(10, 2) NOT NULL,
    capacity INT NOT NULL,
    status VARCHAR(20) DEFAULT 'ready' -- ready, occupied, maintenance
);

-- Room Types Table
CREATE TABLE IF NOT EXISTS room_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    total_rooms INT NOT NULL DEFAULT 0,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00
);

-- Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guest_name VARCHAR(100) NOT NULL,
    guest_type VARCHAR(50) DEFAULT 'First Visit', -- First Visit, VIP Member, Corporate
    room_type_id INT DEFAULT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    guests_count INT NOT NULL,
    room_id INT,
    accommodation_name VARCHAR(100) NOT NULL,
    eta VARCHAR(10) DEFAULT '14:00',
    status VARCHAR(20) DEFAULT 'Pending', -- Pending, Checked In, Cancelled
    cancellation_token VARCHAR(64) DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);

-- Seed Rooms
INSERT INTO rooms (room_number, name, type, price_per_night, capacity, status) VALUES
('101', 'Beachview Duplex 101', 'beachview_duplex', 6900.00, 2, 'ready'),
('102', 'Seaview Duplex 102', 'seaview_duplex', 7900.00, 2, 'ready'),
('103', 'Beach Villa 103', 'beach_villa', 7900.00, 4, 'ready'),
('104', 'Beach Villa 104', 'beach_villa', 7900.00, 4, 'ready'),
('105', 'Beach Villa 105', 'beach_villa', 7900.00, 4, 'ready'),
('106', 'Standard Family Room 106', 'standard_king', 4300.00, 4, 'ready'),
('203', 'Standard Family Room 203', 'standard_king', 4300.00, 4, 'ready'),
('107', 'Standard Room 107', 'standard_room', 2900.00, 2, 'ready'),
('108', 'Standard Room 108', 'standard_room', 2900.00, 2, 'ready'),
('109', 'Standard Room 109', 'standard_room', 2900.00, 2, 'ready'),
('110', 'Standard Room 110', 'standard_room', 2900.00, 2, 'ready');

-- Seed Mock Bookings
INSERT INTO bookings (guest_name, guest_type, check_in, check_out, guests_count, room_id, accommodation_name, eta, status) VALUES
('Elena Rodriguez', 'VIP Member', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 2, 3, 'Beach Villa', '14:00', 'Pending'),
('Marcus Thorne', 'First Visit', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 4, 6, 'Standard Family Room', '15:30', 'Pending'),
('Sarah Lin', 'Corporate', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 2, 8, 'Standard Room', '16:15', 'Pending');

-- Administrators Table
CREATE TABLE IF NOT EXISTS administrators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    failed_login_count INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Receptionists Table
CREATE TABLE IF NOT EXISTS receptionists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT NULL,
    failed_login_count INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
