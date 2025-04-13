<?php
/**
 * Save Purchase Order Handler
 * Handles saving PO data to database and updating prediction system
 */

// Include database connection
if (file_exists('config/db.php')) {
    include 'config/db.php';
} else {
    // Define a fallback function if db.php doesn't exist
    function getConnection() {
        return null;
    }
}

// Set response headers
header('Content-Type: application/json');

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate data
if (!$data || !isset($data['po_no']) || !isset($data['supplier']) || !isset($data['po_date'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Connect to database
$conn = getConnection();

// If no connection, simulate success for testing purposes
if (!$conn || $conn->connect_error) {
    // Log error but return success for demo purposes
    if ($conn && $conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
    } else {
        error_log("No database connection available");
    }
    
    // Also send the data to the prediction system for processing
    $result = simulateUpdatePrediction('po', $data['total_amount'], $data['po_date']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order simulated successfully (no database connection)',
        'po_id' => 'DEMO-' . rand(1000, 9999),
        'prediction_update' => $result
    ]);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Insert purchase order
    $sql = "INSERT INTO purchase_orders (po_no, supplier_name, po_date, total_amount) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssd', 
        $data['po_no'], 
        $data['supplier'], 
        $data['po_date'], 
        $data['total_amount']
    );
    
    $stmt->execute();
    $po_id = $stmt->insert_id;
    $stmt->close();
    
    // Insert PO items if available
    if (isset($data['items']) && is_array($data['items']) && count($data['items']) > 0) {
        $sql = "INSERT INTO po_items (po_id, item_name, unit, description, quantity, unit_cost, amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        foreach ($data['items'] as $item) {
            $stmt->bind_param('isssddd', 
                $po_id, 
                $item['name'], 
                $item['unit'], 
                $item['description'], 
                $item['quantity'], 
                $item['unit_cost'], 
                $item['amount']
            );
            
            $stmt->execute();
        }
        
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Update prediction system
    updatePrediction('po', $data['total_amount'], $data['po_date']);
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order saved successfully',
        'po_id' => $po_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error saving PO: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error saving Purchase Order: ' . $e->getMessage()
    ]);
}

// Close connection
$conn->close();
/**
 * Update prediction system with transaction data
 */
function updatePrediction($type, $amount, $date) {
    // Call ML prediction API to update with new transaction
    $endpoint = 'ml_prediction.php?action=update_prediction';
    
    $data = json_encode([
        'transaction_type' => $type,
        'amount' => $amount,
        'date' => $date
    ]);
    
    // Create stream context for POST request
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $data
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Send request
    $result = @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $endpoint, false, $context);
    
    if ($result === false) {
        error_log("Error updating prediction system");
        return false;
    }
    
    return json_decode($result, true);
}

/**
 * Simulate prediction update for testing without database
 */
function simulateUpdatePrediction($type, $amount, $date) {
    // Send to prediction system directly
    $endpoint = 'ml_prediction.php?action=update_prediction';
    
    $data = json_encode([
        'transaction_type' => $type,
        'amount' => $amount,
        'date' => $date
    ]);
    
    // Create stream context for POST request
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $data
        ]
    ];
    
    $context = stream_context_create($options);
    
    // Send request
    $result = @file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $endpoint, false, $context);
    
    if ($result === false) {
        error_log("Error simulating prediction update");
        return ['success' => false, 'message' => 'Error simulating prediction update'];
    }
    
    return json_decode($result, true);
}

