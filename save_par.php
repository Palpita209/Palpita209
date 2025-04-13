<?php
require_once 'config/db.php';
header('Content-Type: application/json');
// Set response headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Debug log
error_log('PAR Save Request Received');

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);
try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception('Invalid input data');
    }

    // Validate required fields
    $required = ['par_no', 'entity_name', 'date_acquired', 'received_by', 'items'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'your_database_name');
    if ($conn->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert PAR header
        $sql = "INSERT INTO par_header (
            par_no, entity_name, date_acquired, received_by, 
            position, department, remarks, total_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssssssd',
            $data['par_no'],
            $data['entity_name'],
            $data['date_acquired'],
            $data['received_by'],
            $data['position'],
            $data['department'],
            $data['remarks'],
            $data['total_amount'] ?? 0
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save PAR header');
        }
        
        $par_id = $conn->insert_id;

        // Insert PAR items
        $sql = "INSERT INTO par_items (
            par_id, quantity, unit, description, 
            property_number, date_acquired, amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        foreach ($data['items'] as $item) {
            $stmt->bind_param('iissssd',
                $par_id,
                $item['quantity'],
                $item['unit'],
                $item['description'],
                $item['property_number'],
                $item['date_acquired'],
                $item['amount']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to save PAR item');
            }
        }

        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'PAR saved successfully',
            'par_id' => $par_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
