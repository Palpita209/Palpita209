<?php
require_once 'config/db.php';

// Set the proper content type for JSON responses
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data received']);
        exit;
    }

    // Ensure necessary fields are present
    $requiredFields = ['po_no', 'supplier_name', 'po_date', 'items'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Calculate total_amount if not provided
    if (!isset($data['total_amount']) || empty($data['total_amount'])) {
        $data['total_amount'] = 0;
        // Calculate from items if available
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $amount = isset($item['amount']) ? floatval($item['amount']) : 0;
                if ($amount <= 0 && isset($item['quantity']) && isset($item['unit_cost'])) {
                    $amount = floatval($item['quantity']) * floatval($item['unit_cost']);
                }
                $data['total_amount'] += $amount;
            }
        }
    }

    // Start transaction
    $conn->begin_transaction();

    // Insert PO
    $stmt = $conn->prepare("INSERT INTO purchase_orders (
        po_no, ref_no, supplier_name, po_date, mode_of_procurement,
        pr_no, pr_date, place_of_delivery, delivery_date,
        payment_term, delivery_term, obligation_request_no,
        obligation_amount, total_amount
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "sssssssssssssd",
        $data['po_no'],
        $data['ref_no'],
        $data['supplier_name'],
        $data['po_date'],
        $data['mode_of_procurement'],
        $data['pr_no'],
        $data['pr_date'],
        $data['place_of_delivery'],
        $data['delivery_date'],
        $data['payment_term'],
        $data['delivery_term'],
        $data['obligation_request_no'],
        $data['obligation_amount'],
        $data['total_amount']
    );

    $stmt->execute();
    $poId = $conn->insert_id;

    // Insert PO items
    $itemStmt = $conn->prepare("INSERT INTO po_items (
        po_id, item_description, unit, quantity, unit_cost, amount
    ) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($data['items'] as $item) {
        // Use item_description if available, otherwise fall back to description
        $itemDescription = isset($item['item_description']) ? $item['item_description'] : 
                          (isset($item['description']) ? $item['description'] : null);
        
        $itemStmt->bind_param(
            "issidd",
            $poId,
            $itemDescription,
            $item['unit'],
            $item['quantity'],
            $item['unit_cost'],
            $item['amount']
        );
        $itemStmt->execute();
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order saved successfully',
        'po_id' => $poId
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error saving PO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save PO: ' . $e->getMessage()
    ]);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}

// Ensure no trailing output
exit;
?>
