<?php
header('Content-Type: application/json');

// Include database connection
include 'config/db.php';

// Get JSON input
$json = file_get_contents('php://input');

// Validate JSON
$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO inventory_items(
        item_id,
        item_name,
        brand_model,
        serial_number,
        purchase_date,
        warranty_expiration,
        assigned_to,
        location,
        `condition`,
        notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssssssss",
        $data['item_id'],
        $data['item_name'],
        $data['brand_model'],
        $data['serial_number'],
        $data['purchase_date'],
        $data['warranty_expiration'],
        $data['assigned_to'],
        $data['location'],
        $data['condition'],
        $data['notes']
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>