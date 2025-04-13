<?php
// Include database connection
require_once 'config/db.php';

// Set headers for debugging
header('Content-Type: text/plain');

try {
    // Get connection
    $conn = getConnection();
    if (!$conn) {
        die("Database connection failed");
    }
    
    echo "Database connection successful\n";
    
    // Check if the PAR tables exist
    $tables = array(
        'property_acknowledgement_receipts',
        'par_items',
        'users'
    );
    
    echo "\nChecking tables:\n";
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "$table: EXISTS\n";
            
            // Get table structure
            $structResult = $conn->query("DESCRIBE $table");
            echo "  Columns:\n";
            while ($row = $structResult->fetch_assoc()) {
                echo "    - {$row['Field']} ({$row['Type']})" . 
                     ($row['Key'] == 'PRI' ? " PRIMARY KEY" : "") . 
                     ($row['Null'] == 'NO' ? " NOT NULL" : "") . "\n";
            }
        } else {
            echo "$table: MISSING\n";
        }
    }
    
    echo "\nDatabase name: " . $dbname . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    if (isset($conn) && !$conn->connect_error) {
        $conn->close();
    }
}
?> 