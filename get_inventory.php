<?php
// Database connection settings
require_once 'config/db.php';

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Use existing connection from db.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }
    
    // Check for search query
    $searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Prepare SQL query with optional search
    if (!empty($searchQuery)) {
        $searchTerm = "%" . $conn->real_escape_string($searchQuery) . "%";
        $sql = "SELECT * FROM inventory_items WHERE 
                item_name LIKE ? OR 
                brand_model LIKE ? OR 
                serial_number LIKE ? OR 
                assigned_to LIKE ? OR 
                location LIKE ? OR 
                notes LIKE ?
                ORDER BY item_id DESC";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    } else {
        $sql = "SELECT * FROM inventory_items ORDER BY item_id DESC";
        $stmt = $conn->prepare($sql);
    }
    
    // Execute query
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process results
    $items = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Format date fields for consistency
            if (isset($row['purchase_date']) && $row['purchase_date']) {
                $row['purchase_date'] = date('Y-m-d', strtotime($row['purchase_date']));
            }
            if (isset($row['warranty_expiration']) && $row['warranty_expiration']) {
                $row['warranty_expiration'] = date('Y-m-d', strtotime($row['warranty_expiration']));
            }
            
            $items[] = $row;
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $items
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Do not close the connection here as it might be used by other code
    // It will be closed when the script finishes
}

// Ensure no trailing output
exit;
?>