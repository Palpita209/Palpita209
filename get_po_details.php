<?php
// Prevent any output before headers
ob_start();

// Include configuration
require_once 'config/db.php';

// Set proper headers
header('Content-Type: application/json');

// Function to get PO details
function getPODetails($poId) {
    global $conn;
    
    try {
        // Check if connection is valid
        if (!isset($conn) || $conn->connect_error) {
            return ['success' => false, 'message' => 'Database connection error'];
        }
        
        // Get PO header information
        $poQuery = "SELECT *
                   FROM purchase_orders
                   WHERE po_id = ?";
        
        $stmt = $conn->prepare($poQuery);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database query preparation failed: ' . $conn->error];
        }
        
        $stmt->bind_param("i", $poId);
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Failed to execute query: ' . $stmt->error];
        }
        
        $poResult = $stmt->get_result();
        
        if ($poResult->num_rows === 0) {
            return ['success' => false, 'message' => 'Purchase Order not found'];
        }
        
        $poData = $poResult->fetch_assoc();
        
        // Get PO items
        $itemsQuery = "SELECT pi.*, ii.item_name
                      FROM po_items pi
                      LEFT JOIN inventory_items ii ON pi.po_item_id = ii.item_id
                      WHERE pi.po_id = ?";
        
        $stmt = $conn->prepare($itemsQuery);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database query preparation failed: ' . $conn->error];
        }
        
        $stmt->bind_param("i", $poId);
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Failed to execute query: ' . $stmt->error];
        }
        
        $itemsResult = $stmt->get_result();
        
        $items = [];
        $totalAmount = 0;
        
        while ($item = $itemsResult->fetch_assoc()) {
            $amount = floatval($item['quantity']) * floatval($item['unit_cost']);
            $totalAmount += $amount;
            
            $items[] = [
                'id' => $item['po_item_id'],
                'item_number' => $item['po_item_id'],
                'item_name' => $item['item_name'] ?? 'Unknown Item',
                'unit' => $item['unit'] ?? 'pc',
                'description' => $item['item_description'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'amount' => $amount
            ];
        }
        
        // Format the response
        $response = [
            'success' => true,
            'data' => [
                'po_id' => $poData['po_id'],
                'po_no' => $poData['po_no'],
                'po_number' => $poData['po_no'],
                'date' => $poData['po_date'],
                'ref_no' => $poData['ref_no'],
                'ref_number' => $poData['ref_no'],
                'supplier' => $poData['supplier_name'],
                'supplier_name' => $poData['supplier_name'],
                'supplier_address' => 'N/A', // Not in your schema, but included for compatibility
                'supplier_email' => 'N/A', // Not in your schema, but included for compatibility
                'mode_of_procurement' => $poData['mode_of_procurement'],
                'procurement_mode' => $poData['mode_of_procurement'],
                'pr_no' => $poData['pr_no'],
                'pr_number' => $poData['pr_no'],
                'pr_date' => $poData['pr_date'],
                'place_of_delivery' => $poData['place_of_delivery'],
                'delivery_place' => $poData['place_of_delivery'],
                'delivery_date' => $poData['delivery_date'],
                'payment_term' => $poData['payment_term'],
                'delivery_term' => $poData['delivery_term'],
                'obligation_number' => $poData['obligation_request_no'],
                'obligation_request_no' => $poData['obligation_request_no'],
                'obligation_amount' => $poData['obligation_amount'] ?? $totalAmount,
                'items' => $items,
                'total_amount' => $poData['total_amount'] ?? $totalAmount
            ]
        ];
        
        return $response;
        
    } catch (Exception $e) {
        error_log("Error in getPODetails: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error retrieving PO details: ' . $e->getMessage()];
    }
}

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Handle the request
try {
    // Accept both po_id and id parameters for backward compatibility
    $poId = null;
    
    if (isset($_GET['po_id']) && !empty($_GET['po_id'])) {
        $poId = intval($_GET['po_id']);
    } elseif (isset($_GET['id']) && !empty($_GET['id'])) {
        $poId = intval($_GET['id']);
    }
    
    if ($poId === null) {
        echo json_encode(['success' => false, 'message' => 'PO ID is required']);
        exit;
    }

    $result = getPODetails($poId);
    
    // If successful, directly output the data in the format expected by frontend
    if (isset($result['success']) && $result['success'] === true && isset($result['data'])) {
        echo json_encode($result['data']);
    } else {
        // Keep the original error response
        echo json_encode($result);
    }

} catch (Exception $e) {
    error_log("Error in get_po_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()]);
}
?>