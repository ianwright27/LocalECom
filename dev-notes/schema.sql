CREATE DATABASE wrightcommerce;
USE wrightcommerce;

-- Core tables (create these first)
CREATE TABLE businesses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    domain VARCHAR(255),
    logo VARCHAR(255),
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2),
    stock INT DEFAULT 0,
    sku VARCHAR(100),
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    customer_id INT,
    order_number VARCHAR(50) UNIQUE,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'paid', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT,
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    gateway VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Run this in phpMyAdmin
ALTER TABLE payments 
ADD COLUMN business_id INT NOT NULL AFTER id,
ADD COLUMN reference VARCHAR(255) NULL AFTER order_id,
ADD COLUMN currency VARCHAR(10) DEFAULT 'KES' AFTER amount,
ADD COLUMN payment_method VARCHAR(50) DEFAULT 'paystack' AFTER currency,
ADD COLUMN paid_at DATETIME NULL AFTER status,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE payments 
ADD FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE;

ALTER TABLE payments ADD INDEX idx_reference (reference);
ALTER TABLE payments ADD INDEX idx_business_id (business_id);


CREATE TABLE IF NOT EXISTS notification_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    event VARCHAR(50) NOT NULL,
    channel VARCHAR(20) NULL,
    recipient VARCHAR(255) NULL,
    status ENUM('sent', 'failed', 'skipped') DEFAULT 'sent',
    response TEXT NULL,
    data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_id (business_id),
    INDEX idx_event (event),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add settings column to businesses table if it doesn't exist
ALTER TABLE businesses 
ADD COLUMN IF NOT EXISTS settings JSON NULL AFTER description;

-- Verify tables
DESCRIBE notification_logs;
DESCRIBE businesses;
