<?php
require_once 'config/db.php';

header('Content-Type: application/json');

try {
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn ? $conn->connect_error : 'Connection not established'));
    }

    // First check if table exists
    $checkTable = "SHOW TABLES LIKE 'purchase_orders'";
    $tableExists = $conn->query($checkTable);

    if (!$tableExists) {
        throw new Exception("Error checking table existence: " . $conn->error);
    }

    if ($tableExists->num_rows === 0) {
        throw new Exception("The purchase_orders table does not exist in the database");
    }

    // Get table structure
    $query = "DESCRIBE purchase_orders";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Error getting table structure: " . $conn->error);
    }

    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }

    // Also get a sample row to verify data
    $sampleQuery = "SELECT * FROM purchase_orders LIMIT 1";
    $sampleResult = $conn->query($sampleQuery);
    $sampleData = $sampleResult ? $sampleResult->fetch_assoc() : null;

    echo json_encode([
        'success' => true, 
        'columns' => $columns,
        'sample_data' => $sampleData,
        'database_name' => $dbname
    ]);

} catch (Exception $e) {
    error_log("Error in check_po_table.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'database_name' => $dbname
    ]);
}
?> 