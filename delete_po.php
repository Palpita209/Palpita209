<?php
// Force JSON content type for all responses
header('Content-Type: application/json');

// Error reporting for debugging - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in the output

try {
    // Get database connection
    require_once 'config/db.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        throw new Exception('PO ID is required');
    }
    
    $poId = intval($data['id']);
    
    // Log deletion attempt for debugging
    error_log("Attempting to delete PO ID: $poId");
    
    // Start transaction
    $conn->begin_transaction();
    
    // Delete PO items first
    $stmt = $conn->prepare("DELETE FROM po_items WHERE po_id = ?");
    $stmt->bind_param("i", $poId);
    $stmt->execute();
    
    // Then delete the PO
    $stmt = $conn->prepare("DELETE FROM purchase_orders WHERE po_id = ?");
    $stmt->bind_param("i", $poId);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    error_log("Successfully deleted PO ID: $poId");
    echo json_encode([
        'success' => true,
        'message' => 'Purchase Order deleted successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction if it was started
    if (isset($conn)) {
        $conn->rollback();
    }
    
    error_log("Error deleting PO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close connection
if (isset($conn)) {
    $conn->close();
}
exit;
?>