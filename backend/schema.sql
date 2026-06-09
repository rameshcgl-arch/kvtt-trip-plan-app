-- Travel Agency Operations Database Schema

CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

INSERT IGNORE INTO roles (id, role_name) VALUES
(1, 'Admin'), (2, 'Coordinator'), (3, 'Manager'), (4, 'Guide'), (5, 'Driver'), (6, 'Guest');

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role_id INT,
    phone VARCHAR(20),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE IF NOT EXISTS vehicles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reg_number VARCHAR(20) UNIQUE,
    model VARCHAR(50),
    type VARCHAR(30)
);

CREATE TABLE IF NOT EXISTS drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    vehicle_id INT,
    license_number VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

CREATE TABLE IF NOT EXISTS tours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_name VARCHAR(100),
    tour_date DATE,
    driver_id INT,
    guide_id INT,
    coordinator_id INT,
    guest_id INT,
    status ENUM('Scheduled', 'In-Progress', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    FOREIGN KEY (driver_id) REFERENCES drivers(id)
);

CREATE TABLE IF NOT EXISTS tour_sightseeing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT,
    sight_name VARCHAR(100),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    expected_time DATETIME,
    sequence_order INT,
    visit_status ENUM('Pending', 'Visited', 'Delayed') DEFAULT 'Pending',
    actual_visit_time DATETIME,
    FOREIGN KEY (tour_id) REFERENCES tours(id)
);

CREATE TABLE IF NOT EXISTS driver_location_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    driver_id INT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    battery_percentage INT,
    network_status VARCHAR(20),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id)
);

CREATE TABLE IF NOT EXISTS delay_remarks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tour_id INT,
    user_id INT,
    remark TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tour_id) REFERENCES tours(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
