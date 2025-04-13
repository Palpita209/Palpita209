<?php
// Include database connection
require_once 'config/db.php';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid data received: ' . json_last_error_msg());
    }
    
    // Get connection
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get form data
    $parId = $input['par_id'] ?? 0;
    $parNo = $input['par_no'] ?? '';
    $entityName = $input['entity_name'] ?? '';
    $dateAcquired = $input['date_acquired'] ?? '';
    $receivedBy = $input['received_by'] ?? '';
    $position = $input['position'] ?? '';
    $department = $input['department'] ?? '';
    $remarks = $input['remarks'] ?? '';
    $items = $input['items'] ?? [];

    // Validate required fields
    if (empty($parId) || empty($parNo) || empty($receivedBy)) {
        throw new Exception('Please fill in all required fields: PAR No., and Received By');
    }

    // Validate and format the date
    if (!empty($dateAcquired)) {
        // Ensure date is in Y-m-d format
        $originalDate = $dateAcquired;
        $dateAcquired = date('Y-m-d', strtotime($dateAcquired));
        
        // Check if date is valid
        if ($dateAcquired === '1970-01-01' && $originalDate !== '1970-01-01') {
            // If invalid, use current date
            $dateAcquired = date('Y-m-d');
            error_log("Invalid date format converted to today: $originalDate -> $dateAcquired");
        }
    } else {
        // Default to current date if empty
        $dateAcquired = date('Y-m-d');
        error_log("Empty date defaulted to today: $dateAcquired");
    }
    
    // Check if items array is empty
    if (empty($items) || !is_array($items) || count($items) === 0) {
        throw new Exception('Please add at least one item');
    }
    
    // Validate each item has the required fields
    $invalidItems = [];
    foreach ($items as $index => $item) {
        if (!isset($item['description']) || empty(trim($item['description']))) {
            $invalidItems[] = $index + 1;
        }
    }

    if (!empty($invalidItems)) {
        throw new Exception('Please add description for items: ' . implode(', ', array_unique($invalidItems)));
    }

    // Start transaction
    $conn->begin_transaction();
    
    // First, check if user exists, if not create one
    $userId = null;
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE full_name = ?");
    $stmt->bind_param("s", $receivedBy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userId = $row['user_id'];
    } else {
        // Create new user
        $stmt = $conn->prepare("INSERT INTO users (full_name, position, department) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $receivedBy, $position, $department);
        $stmt->execute();
        $userId = $conn->insert_id;
    }
    
    // Check if the PAR No. already exists and belongs to a different record
    $checkParNoStmt = $conn->prepare("SELECT par_id FROM property_acknowledgement_receipts WHERE par_no = ? AND par_id != ?");
    $checkParNoStmt->bind_param("si", $parNo, $parId);
    $checkParNoStmt->execute();
    $checkResult = $checkParNoStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        throw new Exception('PAR No. "' . $parNo . '" already exists. Please use a different PAR No.');
    }
    
    // Calculate total amount
    $totalAmount = 0;
    foreach($items as $item) {
        $quantity = intval($item['quantity'] ?? $item['qty'] ?? 1);
        $amount = floatval($item['amount'] ?? 0);
        $totalAmount += $quantity * $amount;
    }
    
    // Update PAR record
    $updateParSql = "UPDATE property_acknowledgement_receipts 
                     SET par_no = ?, entity_name = ?, date_acquired = ?, received_by = ?,
                         position = ?, department = ?, remarks = ?, total_amount = ?
                     WHERE par_id = ?";
    
    $stmt = $conn->prepare($updateParSql);
    $stmt->bind_param('sssisssdi', $parNo, $entityName, $dateAcquired, $userId, $position, $department, $remarks, $totalAmount, $parId);
    $stmt->execute();
    
    if ($stmt->affected_rows < 0) {
        throw new Exception('Failed to update PAR record');
    }
    
    // Delete existing PAR items
    $deleteItemsSql = "DELETE FROM par_items WHERE par_id = ?";
    $deleteItemsStmt = $conn->prepare($deleteItemsSql);
    $deleteItemsStmt->bind_param('i', $parId);
    $deleteItemsStmt->execute();
    
    // Process items
    if (!empty($items)) {
        foreach ($items as $item) {
            $quantity = intval($item['quantity'] ?? $item['qty'] ?? 1);
            $unit = $item['unit'] ?? '';
            $description = $item['description'] ?? '';
            $propertyNumber = $item['property_number'] ?? '';
            
            // Validate and format the item date
            if (!empty($item['date_acquired'])) {
                $originalItemDate = $item['date_acquired'];
                $itemDate = date('Y-m-d', strtotime($item['date_acquired']));
                
                // Check if date is valid
                if ($itemDate === '1970-01-01' && $originalItemDate !== '1970-01-01') {
                    // If invalid date, use the PAR date
                    $itemDate = $dateAcquired;
                    error_log("Invalid item date format converted to PAR date: $originalItemDate -> $itemDate");
                }
            } else {
                $itemDate = $dateAcquired;
                error_log("Empty item date defaulted to PAR date: $itemDate");
            }
            
            $amount = floatval($item['amount'] ?? 0);
            
            // Validate required fields
            if (empty($description)) {
                continue;
            }
            
            // Insert PAR item
            $insertItemSql = "INSERT INTO par_items 
                             (par_id, quantity, unit, description, property_number, date_acquired, amount) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $itemStmt = $conn->prepare($insertItemSql);
            $itemStmt->bind_param('iissssd', $parId, $quantity, $unit, $description, $propertyNumber, $itemDate, $amount);
            $itemStmt->execute();
            
            if ($itemStmt->affected_rows <= 0) {
                throw new Exception('Failed to add item to PAR');
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'PAR updated successfully', 'par_id' => $parId]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn) && $conn->connect_error === false) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackEx) {
            error_log('Error during rollback: ' . $rollbackEx->getMessage());
        }
    }
    
    error_log('Error in update_par.php: ' . $e->getMessage());
    
    // Set proper HTTP status code for errors
    http_response_code(500);
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn) && $conn->connect_error === false) {
    $conn->close();
}
?>