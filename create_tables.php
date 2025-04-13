<?php
// Include database connection
require_once 'config/db.php';

try {
    echo "<h2>PAR System - Database Initialization</h2>";
    
    // Get connection
    $conn = getConnection();
    if (!$conn) {
        die("<p style='color:red'>Database connection failed. Check your database credentials in config/db.php</p>");
    }
    
    echo "<p>Database connection successful. Creating/verifying tables...</p>";
    
    // Create users table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT PRIMARY KEY AUTO_INCREMENT,
            full_name VARCHAR(100) NOT NULL,
            position VARCHAR(100),
            department VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    echo "<p>✅ Users table created/verified.</p>";
    
    // Create PAR table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS property_acknowledgement_receipts (
            par_id INT PRIMARY KEY AUTO_INCREMENT,
            par_no VARCHAR(50) NOT NULL UNIQUE,
            entity_name VARCHAR(200) NOT NULL,
            date_acquired DATE NOT NULL,
            received_by INT NOT NULL,
            position VARCHAR(100),
            department VARCHAR(100),
            remarks TEXT,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (received_by) REFERENCES users(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    echo "<p>✅ Property Acknowledgement Receipts table created/verified.</p>";
    
    // Create PAR items table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS par_items (
            par_item_id INT PRIMARY KEY AUTO_INCREMENT,
            par_id INT NOT NULL,
            quantity INT DEFAULT 1,
            unit VARCHAR(50),
            description TEXT NOT NULL,
            property_number VARCHAR(100),
            date_acquired DATE,
            amount DECIMAL(15,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (par_id) REFERENCES property_acknowledgement_receipts(par_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    echo "<p>✅ PAR Items table created/verified.</p>";
    
    echo "<p style='color:green;font-weight:bold'>All database tables created/verified successfully!</p>";
    
    echo "<p>You can now <a href='index.php'>return to the application</a>.</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
} finally {
    if (isset($conn) && !$conn->connect_error) {
        $conn->close();
    }
}
?> 