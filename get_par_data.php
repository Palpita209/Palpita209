<?php
// Include database connection
require_once 'config/db.php';

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Verify connection
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection not available");
    }
    
    // Prepare SQL query
    $sql = "SELECT par.par_id as id, 
                  par.par_no, 
                  CASE 
                      WHEN par.date_acquired IS NULL OR par.date_acquired = '0000-00-00' THEN CURDATE()
                      ELSE DATE_FORMAT(par.date_acquired, '%Y-%m-%d') 
                  END as date_acquired,
                  GROUP_CONCAT(pi.property_number SEPARATOR ', ') as property_number,
                  u.full_name as issued_to,
                  par.total_amount,
                  'Active' as status
           FROM property_acknowledgement_receipts par
           LEFT JOIN users u ON par.received_by = u.user_id
           LEFT JOIN par_items pi ON par.par_id = pi.par_id
           GROUP BY par.par_id
           ORDER BY par.date_acquired DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Error executing query: " . $conn->error);
    }
    
    $pars = [];
    while ($row = $result->fetch_assoc()) {
        $pars[] = $row;
    }
    
    // Use the same response format as other APIs
    echo json_encode([
        'success' => true,
        'data' => $pars
    ]);
    
} catch (Exception $e) {
    // Return error response with HTTP status code
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Do not close connection here, it will be closed when script ends
exit;
?>