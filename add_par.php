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
    // Debug log
    error_log('add_par.php - Request received: ' . file_get_contents('php://input'));
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid data received: ' . json_last_error_msg());
    }
    
    // Get connection
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Validate required fields
    $requiredFields = ['par_no', 'entity_name', 'date_acquired', 'received_by'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception('Please fill in required fields: ' . implode(', ', $missingFields));
    }
    
    // Validate and format the date
    if (isset($data['date_acquired']) && !empty($data['date_acquired'])) {
        // Ensure date is in Y-m-d format
        $originalDate = $data['date_acquired'];
        $dateAcquired = date('Y-m-d', strtotime($data['date_acquired']));
        
        // Check if date is valid
        if ($dateAcquired === '1970-01-01' && $originalDate !== '1970-01-01') {
            // If invalid, use current date
            $dateAcquired = date('Y-m-d');
            error_log("Invalid date format converted to today: $originalDate -> $dateAcquired");
        }
        $data['date_acquired'] = $dateAcquired;
    } else {
        // Default to current date if empty
        $data['date_acquired'] = date('Y-m-d');
        error_log("Empty date defaulted to today: " . $data['date_acquired']);
    }
    
    // Check if items array is empty
    if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
        throw new Exception('Please add at least one item');
    }
    
    // Validate each item has the required fields
    $invalidItems = [];
    foreach ($data['items'] as $index => $item) {
        if (!isset($item['description']) || empty(trim($item['description']))) {
            $invalidItems[] = $index + 1;
        }
    }

    if (!empty($invalidItems)) {
        throw new Exception('Please add description for items: ' . implode(', ', array_unique($invalidItems)));
    }
    
    // Check if PAR_NO already exists
    $checkStmt = $conn->prepare("SELECT par_id FROM property_acknowledgement_receipts WHERE par_no = ?");
    $checkStmt->bind_param("s", $data['par_no']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('PAR No. "' . $data['par_no'] . '" already exists. Please use a different PAR No.');
    }

    // First, check if user exists or create a new one
    $userId = null;
    
    if (is_numeric($data['received_by'])) {
        // If it's already a numeric ID, use it directly
        $userId = intval($data['received_by']);
        
        // Verify this user ID exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // If user ID doesn't exist, treat as a name
            $userId = null;
        }
    }
    
    // If userId is still null, look for user by name or create new 
    if ($userId === null) {
        // Check if this user exists by name
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE full_name = ?");
        $stmt->bind_param("s", $data['received_by']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $userId = $row['user_id'];
        } else {
            // Create new user
            $stmt = $conn->prepare("INSERT INTO users (full_name, position, department) VALUES (?, ?, ?)");
            $position = isset($data['position']) ? $data['position'] : '';
            $department = isset($data['department']) ? $data['department'] : '';
            
            $stmt->bind_param("sss", 
                $data['received_by'], 
                $position, 
                $department
            );
            $stmt->execute();
            $userId = $conn->insert_id;
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Calculate total amount
    $totalAmount = 0;
    foreach ($data['items'] as $item) {
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $amount = isset($item['amount']) ? floatval($item['amount']) : 0;
        $totalAmount += $quantity * $amount;
    }
    
    // Insert PAR
    $stmt = $conn->prepare("INSERT INTO property_acknowledgement_receipts (
        par_no, entity_name, date_acquired, received_by, position, department, 
        remarks, total_amount
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $position = isset($data['position']) ? $data['position'] : '';
    $department = isset($data['department']) ? $data['department'] : '';
    $remarks = isset($data['remarks']) ? $data['remarks'] : '';
    
    $stmt->bind_param(
        "ssissssd",
        $data['par_no'],
        $data['entity_name'],
        $data['date_acquired'],
        $userId,
        $position,
        $department,
        $remarks,
        $totalAmount
    );
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        if (strpos($error, 'Duplicate entry') !== false && strpos($error, 'par_no') !== false) {
            throw new Exception('PAR No. "' . $data['par_no'] . '" already exists. Please use a different PAR No.');
        } else {
            throw new Exception('Failed to insert PAR: ' . $error);
        }
    }
    
    $parId = $conn->insert_id;
    
    // Insert PAR items
    $itemStmt = $conn->prepare("INSERT INTO par_items (
        par_id, quantity, unit, description, property_number, date_acquired, amount
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($data['items'] as $item) {
        // Only process items with a description
        if (empty($item['description'])) {
            continue;
        }
        
        // Get item values with defaults for missing properties
        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
        $unit = isset($item['unit']) ? $item['unit'] : '';
        $description = $item['description'];
        $propertyNumber = isset($item['property_number']) ? $item['property_number'] : '';
        
        // Validate and format the item date
        if (!empty($item['date_acquired'])) {
            $originalItemDate = $item['date_acquired'];
            $itemDate = date('Y-m-d', strtotime($item['date_acquired']));
            
            // Check if date is valid
            if ($itemDate === '1970-01-01' && $originalItemDate !== '1970-01-01') {
                // If invalid date, use the PAR date
                $itemDate = $data['date_acquired'];
                error_log("Invalid item date format converted to PAR date: $originalItemDate -> $itemDate");
            }
        } else {
            $itemDate = $data['date_acquired'];
            error_log("Empty item date defaulted to PAR date: $itemDate");
        }
        
        $amount = isset($item['amount']) ? floatval($item['amount']) : 0;
        
        $itemStmt->bind_param(
            "iissssd",
            $parId,
            $quantity,
            $unit,
            $description,
            $propertyNumber,
            $itemDate,
            $amount
        );
        
        if (!$itemStmt->execute()) {
            throw new Exception('Failed to insert PAR item: ' . $itemStmt->error);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log success
    error_log("PAR saved successfully - PAR ID: $parId");
    
    echo json_encode([
        'success' => true,
        'message' => 'PAR saved successfully',
        'par_id' => $parId
    ]);
    
} catch (Exception $e) {
    error_log('Error in add_par.php: ' . $e->getMessage());
    
    if (isset($conn) && $conn->connect_error === false) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackEx) {
            error_log('Error during rollback: ' . $rollbackEx->getMessage());
        }
    }
    
    http_response_code(500); // Set proper HTTP status code
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn) && $conn->connect_error === false) {
    $conn->close();
}
?>