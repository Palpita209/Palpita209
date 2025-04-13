<?php
// Include database connection
require_once 'config/db.php';

// Set headers
header('Content-Type: application/json');

// Get JSON input
$json = file_get_contents('php://input');
$input = json_decode($json, true);

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate input
if (!isset($input['item_id']) || empty($input['item_id']) || !isset($input['item_name']) || empty($input['item_name'])) {
    echo json_encode(['success' => false, 'message' => 'Item ID and name are required']);
    exit;
}

try {
    // Get current location before update
    $stmt = $conn->prepare("SELECT location FROM inventory_items WHERE item_id = ?");
    $stmt->bind_param("s", $input['item_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentItem = $result->fetch_assoc();
    $previousLocation = $currentItem['location'];
    $stmt->close();

    // Check for duplicates excluding the current item
    $check_sql = "SELECT COUNT(*) as count FROM inventory_items
                  WHERE (item_id = ? OR serial_number = ?)
                  AND item_id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("sss",
        $input['item_id'],
        $input['serial_number'],
        $input['item_id']
    );
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $count = $result->fetch_assoc()['count'];

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Item ID or Serial Number already exists']);  
        exit;
    }

    // Prepare statement for updating inventory
    $stmt = $conn->prepare("UPDATE inventory_items
                      SET item_name = ?, brand_model = ?, serial_number = ?,
                          purchase_date = ?, warranty_expiration = ?,
                          assigned_to = ?, location = ?, notes = ?,
                          `condition` = ?
                      WHERE item_id = ?");

    // Convert empty strings to null for date fields
    $purchase_date = !empty($input['purchase_date']) ? $input['purchase_date'] : null;
    $warranty_expiration = !empty($input['warranty_expiration']) ? $input['warranty_expiration'] : null; 

    // Bind parameters
    $stmt->bind_param(
        "ssssssssss",
        $input['item_name'],
        $input['brand_model'],
        $input['serial_number'],
        $purchase_date,
        $warranty_expiration,
        $input['assigned_to'],
        $input['location'],
        $input['notes'],
        $input['condition'],
        $input['item_id']
    );

    // Execute update
    $stmt->execute();

    // If location changed, record in history
    if ($previousLocation !== $input['location']) {
        $historyStmt = $conn->prepare("INSERT INTO location_history (item_id, previous_location, new_location, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
        $changedBy = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'System';
        $notes = "Location changed from " . ($previousLocation ?: 'None') . " to " . ($input['location'] ?: 'None');
        
        $historyStmt->bind_param("sssss", 
            $input['item_id'],
            $previousLocation,
            $input['location'],
            $changedBy,
            $notes
        );
        
        $historyStmt->execute();
        $historyStmt->close();
    }

    // Return success response
    echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Close statement and connection
if (isset($stmt)) $stmt->close();
$conn->close();
?>