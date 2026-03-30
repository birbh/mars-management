-- Database schema for the application
CREATE DATABASE IF NOT EXISTS mars_haven;
USE mars_haven;

-- User + auth
CREATE TABLE users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','astronaut','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Solar storm data
CREATE TABLE solar_storms(
    id INT AUTO_INCREMENT PRIMARY KEY,
    intensity INT NOT NULL,
    description VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Radiation expo. data
CREATE TABLE radiation_logs(
    id INT AUTO_INCREMENT PRIMARY KEY,
    storm_id INT,
    radiation_level FLOAT NOT NULL,
    status ENUM('safe','warning','danger') NOT NULL,    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (storm_id) REFERENCES solar_storms(id) ON DELETE SET NULL
);

-- Power and energy data
CREATE TABLE power_logs(
    id INT AUTO_INCREMENT PRIMARY KEY,
    solar_output INT NOT NULL,
    battery_level INT NOT NULL,
    mode ENUM('normal','critical') NOT NULL,
    storm_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (storm_id) REFERENCES solar_storms(id) ON DELETE SET NULL
);

-- Emergency logs
CREATE TABLE events(
    id INT AUTO_INCREMENT PRIMARY KEY,
    storm_id INT,
    event_type VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (storm_id) REFERENCES solar_storms(id) ON DELETE SET NULL
);