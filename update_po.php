<?php
header('Content-Type: application/json');
require_once 'config/db.php';

try {
    // Check if supplier_address column exists, add if it doesn't
    $check = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'supplier_address'");
    if ($check->num_rows == 0) {
        $addColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN supplier_address VARCHAR(255) AFTER supplier_name");
        if (!$addColumn) {
            throw new Exception('Error adding supplier_address column: ' . $conn->error);
        }
    }
    
    // Check if email column exists, add if it doesn't
    $checkEmail = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'email'");
    if ($checkEmail->num_rows == 0) {
        $addEmailColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN email VARCHAR(255) AFTER supplier_address");
        if (!$addEmailColumn) {
            throw new Exception('Error adding email column: ' . $conn->error);
        }
    }
    
    // Check if tel column exists, add if it doesn't
    $checkTel = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'tel'");
    if ($checkTel->num_rows == 0) {
        $addTelColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN tel VARCHAR(50) AFTER email");
        if (!$addTelColumn) {
            throw new Exception('Error adding tel column: ' . $conn->error);
        }
    }
    
    // Check if place_of_delivery column exists, add if it doesn't
    $checkPlaceOfDelivery = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'place_of_delivery'");
    if ($checkPlaceOfDelivery->num_rows == 0) {
        $addPlaceOfDeliveryColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN place_of_delivery VARCHAR(255) AFTER pr_date");
        if (!$addPlaceOfDeliveryColumn) {
            throw new Exception('Error adding place_of_delivery column: ' . $conn->error);
        }
    }
    
    // Check if delivery_date column exists, add if it doesn't
    $checkDeliveryDate = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'delivery_date'");
    if ($checkDeliveryDate->num_rows == 0) {
        $addDeliveryDateColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN delivery_date DATE AFTER place_of_delivery");
        if (!$addDeliveryDateColumn) {
            throw new Exception('Error adding delivery_date column: ' . $conn->error);
        }
    }
    
    // Check if payment_term column exists, add if it doesn't
    $checkPaymentTerm = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'payment_term'");
    if ($checkPaymentTerm->num_rows == 0) {
        $addPaymentTermColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN payment_term VARCHAR(255) AFTER delivery_date");
        if (!$addPaymentTermColumn) {
            throw new Exception('Error adding payment_term column: ' . $conn->error);
        }
    }
    
    // Check if delivery_term column exists, add if it doesn't
    $checkDeliveryTerm = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'delivery_term'");
    if ($checkDeliveryTerm->num_rows == 0) {
        $addDeliveryTermColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN delivery_term VARCHAR(255) AFTER payment_term");
        if (!$addDeliveryTermColumn) {
            throw new Exception('Error adding delivery_term column: ' . $conn->error);
        }
    }
    
    // Check if obligation_request_no column exists, add if it doesn't
    $checkObligation = $conn->query("SHOW COLUMNS FROM purchase_orders LIKE 'obligation_request_no'");
    if ($checkObligation->num_rows == 0) {
        $addObligationColumn = $conn->query("ALTER TABLE purchase_orders ADD COLUMN obligation_request_no VARCHAR(255) AFTER delivery_term");
        if (!$addObligationColumn) {
            throw new Exception('Error adding obligation_request_no column: ' . $conn->error);
        }
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id']) && !isset($data['po_id'])) {
        throw new Exception('PO ID is required');
    }
    
    // Use whichever ID is provided
    $poId = isset($data['id']) ? $data['id'] : $data['po_id'];

    $conn->begin_transaction();
    
    // Check if 'id' column exists
    $checkColumnStmt = $conn->prepare("SHOW COLUMNS FROM purchase_orders LIKE 'id'");
    $checkColumnStmt->execute();
    $idColumnExists = $checkColumnStmt->get_result()->num_rows > 0;
    
    // Check if 'po_id' column exists
    $checkPoIdColumnStmt = $conn->prepare("SHOW COLUMNS FROM purchase_orders LIKE 'po_id'");
    $checkPoIdColumnStmt->execute();
    $poIdColumnExists = $checkPoIdColumnStmt->get_result()->num_rows > 0;
    
    // Construct SQL based on which column exists
    if ($idColumnExists) {
        $whereClause = "WHERE id = ?";
    } elseif ($poIdColumnExists) {
        $whereClause = "WHERE po_id = ?";
    } else {
        throw new Exception("Could not find ID column in purchase_orders table");
    }
    
    // Normalize field names to match database structure
    $placeOfDelivery = isset($data['place_of_delivery']) ? $data['place_of_delivery'] : 
                      (isset($data['delivery_place']) ? $data['delivery_place'] : '');

    // Update PO header
    $sql = "UPDATE purchase_orders SET 
            po_no = ?, ref_no = ?, supplier_name = ?, supplier_address = ?, 
            email = ?, tel = ?, po_date = ?, mode_of_procurement = ?, 
            pr_no = ?, pr_date = ?, place_of_delivery = ?, delivery_date = ?, 
            payment_term = ?, delivery_term = ?, 
            obligation_request_no = ?, total_amount = ? 
            $whereClause";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssssssdi", 
        $data['po_no'],
        $data['ref_no'],
        $data['supplier_name'],
        $data['supplier_address'],
        $data['email'],
        $data['tel'],
        $data['po_date'],
        $data['mode_of_procurement'],
        $data['pr_no'],
        $data['pr_date'],
        $placeOfDelivery,
        $data['delivery_date'],
        $data['payment_term'],
        $data['delivery_term'],
        $data['obligation_request_no'],
        $data['total_amount'],
        $poId
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating PO: " . $stmt->error);
    }

    // Delete existing items
    $sql = "DELETE FROM po_items WHERE po_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $poId);
    
    if (!$stmt->execute()) {
        throw new Exception("Error deleting existing PO items: " . $stmt->error);
    }

    // Insert updated items
    $sql = "INSERT INTO po_items (po_id, item_description, unit, quantity, unit_cost, amount) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($data['items'] as $item) {
        // Use the correct field names (item_description, not description)
        $itemDesc = isset($item['item_description']) ? $item['item_description'] : 
                  (isset($item['description']) ? $item['description'] : '');
                  
        $unit = $item['unit'] ?? '';
        $quantity = isset($item['quantity']) ? $item['quantity'] : 
                  (isset($item['qty']) ? $item['qty'] : 0);
        $unitCost = $item['unit_cost'] ?? 0;
        $amount = $item['amount'] ?? 0;
        
        $stmt->bind_param("issidd", 
            $poId,
            $itemDesc,
            $unit,
            $quantity,
            $unitCost,
            $amount
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error inserting PO item: " . $stmt->error);
        }
    }

    // Commit the transaction
    if (!$conn->commit()) {
        throw new Exception("Error committing transaction: " . $conn->error);
    }
    
    // Get the updated PO data to return to the client
    $po = [
        'po_id' => $poId,
        'po_no' => $data['po_no'],
        'supplier_name' => $data['supplier_name'],
        'po_date' => $data['po_date'],
        'total_amount' => $data['total_amount'],
        'ref_no' => $data['ref_no'] ?? '',
        'status' => $status ?? 'Pending'
    ];
    
    // Return success response with the updated PO
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order updated successfully',
        'po' => $po
    ]);

} catch (Exception $e) {
    if ($conn->connect_error === false) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>