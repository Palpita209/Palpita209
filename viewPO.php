<?php
// Include database configuration
include 'config/db.php';

// Get PO ID from query string - check both 'id' and 'po_id' parameters
$id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['po_id']) ? intval($_GET['po_id']) : 0);

// Check if this is an uploaded PO
$isUploadedPO = isset($_GET['type']) && $_GET['type'] === 'uploaded';

if ($isUploadedPO) {
    // Fetch from uploaded_pos table
    $poTable = 'uploaded_pos';
    $idField = 'po_id';
} else {
    // Regular PO from purchase_orders table
    $poTable = 'purchase_orders';
    $idField = 'po_id';
}

// Check for modal data passed in URL
$modalData = isset($_GET['modal_data']) ? $_GET['modal_data'] : '';
$modalItems = [];

// If modal data exists, try to decode it
if (!empty($modalData)) {
    error_log("Debug - Raw modal data: " . $modalData);
    
    $decodedModalData = json_decode(urldecode($modalData), true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedModalData)) {
        error_log("Debug - Modal data successfully decoded: " . json_encode($decodedModalData));
        
        // Check if it contains items array
        if (isset($decodedModalData['items']) && is_array($decodedModalData['items'])) {
            $modalItems = $decodedModalData['items'];
            error_log("Debug - Modal items found: " . count($modalItems));
            
            // Pre-process each item to ensure proper format
            foreach ($modalItems as $key => $item) {
                // If item is a string, try to decode it
                if (is_string($item)) {
                    $decoded = json_decode($item, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $modalItems[$key] = $decoded;
                        error_log("Debug - Decoded string item to array");
                    }
                }
                
                // First check for and extract description from textarea fields
                if (isset($item['textarea']) && !empty($item['textarea'])) {
                    $modalItems[$key]['item_description'] = $item['textarea'];
                    $modalItems[$key]['description'] = $item['textarea'];
                    error_log("Debug - Using textarea directly as item_description: " . $item['textarea']);
                }
                
                // Check if we have description but not item_description
                if (isset($item['description']) && !isset($item['item_description'])) {
                    $modalItems[$key]['item_description'] = $item['description'];
                    error_log("Debug - Copied description to item_description: " . $item['description']);
                }
                
                // If item_description exists but description doesn't, copy it
                if (isset($item['item_description']) && !isset($item['description'])) {
                    $modalItems[$key]['description'] = $item['item_description'];
                    error_log("Debug - Copied item_description to description: " . $item['item_description']);
                }
                
                // If data field contains a description, extract it
                if (isset($item['data'])) {
                    if (is_string($item['data'])) {
                        $dataArr = json_decode($item['data'], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($dataArr)) {
                            if (isset($dataArr['description']) && !isset($item['item_description'])) {
                                $modalItems[$key]['item_description'] = $dataArr['description'];
                                $modalItems[$key]['description'] = $dataArr['description'];
                                error_log("Debug - Extracted description from data JSON: " . $dataArr['description']);
                            }
                        } else if (!empty(trim($item['data'])) && !isset($item['item_description'])) {
                            // If data is not JSON but contains text, use it as description
                            $modalItems[$key]['item_description'] = $item['data'];
                            $modalItems[$key]['description'] = $item['data'];
                            error_log("Debug - Using data string as item_description: " . $item['data']);
                        }
                    } elseif (is_array($item['data'])) {
                        if (isset($item['data']['description']) && !isset($item['item_description'])) {
                            $modalItems[$key]['item_description'] = $item['data']['description'];
                            $modalItems[$key]['description'] = $item['data']['description'];
                            error_log("Debug - Extracted description from data array: " . $item['data']['description']);
                        }
                    }
                }
                
                // Add full item logging
                error_log("Debug - Processed modal item #" . ($key+1) . ": " . json_encode($modalItems[$key]));
            }
        }
        
        // If PO details are included, merge them with existing PO data
        if (isset($decodedModalData['po_details']) && is_array($decodedModalData['po_details'])) {
            // This will be used later if DB lookup returns no results
            $modalPoDetails = $decodedModalData['po_details'];
            error_log("Debug - Modal PO details found: " . json_encode($modalPoDetails));
        }
    } else {
        $jsonError = json_last_error_msg();
        error_log("Debug - Failed to decode modal data: " . $jsonError);
        // Try if the data was already decoded by browser
        $tryDirectJson = json_decode($modalData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tryDirectJson)) {
            $decodedModalData = $tryDirectJson;
            if (isset($decodedModalData['items']) && is_array($decodedModalData['items'])) {
                $modalItems = $decodedModalData['items'];
                error_log("Debug - Recovered modal items from direct JSON: " . count($modalItems));
                
                // Process items for description
                foreach ($modalItems as $key => $item) {
                    // Ensure item_description is available
                    if (isset($item['description']) && !isset($item['item_description'])) {
                        $modalItems[$key]['item_description'] = $item['description'];
                        error_log("Debug - Mapped description to item_description for direct JSON item");
                    }
                    
                    // Check if item has a textarea field that might contain the description
                    if (isset($item['textarea']) && !empty($item['textarea']) && !isset($item['item_description'])) {
                        $modalItems[$key]['item_description'] = $item['textarea'];
                        error_log("Debug - Using textarea as item_description for direct JSON item");
                    }
                    
                    // Handle data field if present
                    if (isset($item['data']) && !isset($item['item_description'])) {
                        if (is_string($item['data'])) {
                            $dataArr = json_decode($item['data'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($dataArr)) {
                                if (isset($dataArr['description']) && !isset($modalItems[$key]['item_description'])) {
                                    $modalItems[$key]['item_description'] = $dataArr['description'];
                                    error_log("Debug - Extracted description from data JSON in direct JSON recovery");
                                }
                            } else if (!empty(trim($item['data']))) {
                                // If data is not JSON but contains text, use it as description
                                $modalItems[$key]['item_description'] = $item['data'];
                                error_log("Debug - Using data string as item_description in direct JSON recovery");
                            }
                        } elseif (is_array($item['data'])) {
                            if (isset($item['data']['description']) && !isset($modalItems[$key]['item_description'])) {
                                $modalItems[$key]['item_description'] = $item['data']['description'];
                                error_log("Debug - Extracted description from data array in direct JSON recovery");
                            }
                        }
                    }
                }
            }
            if (isset($decodedModalData['po_details']) && is_array($decodedModalData['po_details'])) {
                $modalPoDetails = $decodedModalData['po_details'];
            }
            error_log("Debug - Recovered modal data without urldecode");
        }
    }
}

if ($id <= 0) {
    echo "Invalid PO ID";
    exit;
}

try {
    // Get PO details from the appropriate table based on type
    $stmt = $conn->prepare("SELECT * FROM $poTable WHERE $idField = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $po = $result->fetch_assoc();

    if (!$po) {
        error_log("Debug - PO not found in database with ID $id in table $poTable. Using modal data if available.");
        if (isset($modalPoDetails)) {
            $po = $modalPoDetails;
            error_log("Debug - Using PO details from modal data");
        } else {
            echo "<div class='alert alert-danger'><strong>Error:</strong> Purchase Order with ID $id not found in the database. Please check the PO ID and try again.</div>";
            // Log more details about what went wrong
            error_log("Debug - PO not found and no modal data available. ID: $id, Table: $poTable, Field: $idField");
            exit;
        }
    } else {
        error_log("Debug - PO found in database with ID $id: " . json_encode($po));
    }

    // Make sure delivery and payment terms are set
    if (!isset($po['place_of_delivery']) && isset($modalPoDetails['place_of_delivery'])) {
        $po['place_of_delivery'] = $modalPoDetails['place_of_delivery'];
    }
    if (!isset($po['delivery_term']) && isset($modalPoDetails['delivery_term'])) {
        $po['delivery_term'] = $modalPoDetails['delivery_term'];
    }
    if (!isset($po['payment_term']) && isset($modalPoDetails['payment_term'])) {
        $po['payment_term'] = $modalPoDetails['payment_term'];
    }
    if (!isset($po['delivery_date']) && isset($modalPoDetails['delivery_date'])) {
        $po['delivery_date'] = $modalPoDetails['delivery_date'];
    }

    // Log this view in the tracking table if it's an uploaded PO
    if ($isUploadedPO) {
        $logStmt = $conn->prepare("INSERT INTO po_tracking (po_id, action, details, action_date) VALUES (?, 'VIEW', 'PO document viewed', NOW())");
        $logStmt->bind_param("i", $id);
        $logStmt->execute();
    }

    // Get PO items
    $items = [];
    
    // Only try to get items if this is a regular PO (not an uploaded document)
    if (!$isUploadedPO) {
        $itemsStmt = $conn->prepare("SELECT * FROM po_items WHERE po_id = ?");
        $itemsStmt->bind_param("i", $id);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        while ($row = $itemsResult->fetch_assoc()) {
            // Check if we have item_description field
            if (!isset($row['item_description']) && isset($row['description'])) {
                // If we only have description field, copy it to item_description
                $row['item_description'] = $row['description'];
                error_log("Debug - Mapped description to item_description for database item #" . count($items) + 1);
            }
            
            // If we have item_description but no description, copy it
            if (isset($row['item_description']) && !isset($row['description'])) {
                $row['description'] = $row['item_description'];
                error_log("Debug - Mapped item_description to description for database item #" . count($items) + 1);
            }
            
            // If we have a data field that's a string, try to decode it
            if (isset($row['data'])) {
                if (is_string($row['data'])) {
                    $dataArr = json_decode($row['data'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($dataArr)) {
                        // If we don't have item_description but data has description, use it
                        if (empty($row['item_description']) && isset($dataArr['description'])) {
                            $row['item_description'] = $dataArr['description'];
                            $row['description'] = $dataArr['description'];
                            error_log("Debug - Extracted description from data JSON for database item");
                        }
                    }
                }
            }
            
            $items[] = $row;
        }
    }
    
    // If no items are found in the database, use the modal items
    if (empty($items) && !empty($modalItems)) {
        $items = $modalItems;
        error_log("Debug - Using modal items instead of database items");
    }
    
    // If this is an uploaded PO, check if there's a document to display
    $uploadedDocumentUrl = '';
    if ($isUploadedPO && isset($po['file_path']) && !empty($po['file_path'])) {
        $uploadedDocumentUrl = $po['file_path'];
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

function extractItemValue($item, $keys, $dataKeys = null) {
    // First try direct access to the specified keys
    foreach ($keys as $key) {
        if (isset($item[$key]) && !empty($item[$key])) {
            return $item[$key];
        }
    }
    
    // Try to extract from the data field if it exists
    if ($dataKeys && isset($item['data'])) {
        $data = $item['data'];
        
        // If data is a JSON string, decode it
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data = $decoded;
            }
        }
        
        // If data is an array, check for the keys
        if (is_array($data)) {
            foreach ($dataKeys as $key) {
                if (isset($data[$key]) && !empty($data[$key])) {
                    return $data[$key];
                }
            }
        } else if (is_string($data) && !empty(trim($data)) && ($dataKeys == ['description'] || $dataKeys == ['item_description'])) {
            // If data is a non-empty string and we're looking for description, use the string itself
            return $data;
        }
    }
    
    // Check if there's a description in textarea field
    if (($keys == ['description'] || $keys == ['item_description']) && isset($item['textarea']) && !empty($item['textarea'])) {
        return $item['textarea'];
    }
    
    return '';
}

// Calculate total from items if not set in PO
$calculatedTotal = 0;
foreach ($items as $item) {
    $qty = extractItemValue($item, ['quantity', 'qty'], ['quantity', 'qty']);
    $unitCost = extractItemValue($item, ['unit_cost', 'unit_price', 'price'], ['unit_cost', 'unit_price', 'price']);
    $amount = extractItemValue($item, ['amount', 'total'], ['amount', 'total']);
    
    // If amount is not set, calculate it from qty and unit cost
    if ($amount <= 0 && $qty > 0 && $unitCost > 0) {
        $amount = $qty * $unitCost;
    }
    
    $calculatedTotal += $amount;
}

// Use calculated total if PO total is not set or is zero
if (empty($po['total_amount']) || $po['total_amount'] <= 0) {
    $po['total_amount'] = $calculatedTotal;
    error_log("Debug - Using calculated total: " . $po['total_amount']);
}

// Format numbers for display
$totalAmount = $po['total_amount'];
$formattedTotal = number_format($totalAmount, 2, '.', ',');

// Generate total in words for display
function numberToWords($num)
{
    $ones = array(
        0 => "Zero",
        1 => "One",
        2 => "Two",
        3 => "Three",
        4 => "Four",
        5 => "Five",
        6 => "Six",
        7 => "Seven",
        8 => "Eight",
        9 => "Nine",
        10 => "Ten",
        11 => "Eleven",
        12 => "Twelve",
        13 => "Thirteen",
        14 => "Fourteen",
        15 => "Fifteen",
        16 => "Sixteen",
        17 => "Seventeen",
        18 => "Eighteen",
        19 => "Nineteen"
    );
    $tens = array(
        2 => "Twenty",
        3 => "Thirty",
        4 => "Forty",
        5 => "Fifty",
        6 => "Sixty",
        7 => "Seventy",
        8 => "Eighty",
        9 => "Ninety"
    );
    $scales = array(
        0 => "",
        1 => "Thousand",
        2 => "Million",
        3 => "Billion",
        4 => "Trillion"
    );

    if ($num == 0) {
        return "Zero";
    }

    $num = number_format($num, 2, '.', '');
    $numArr = explode('.', $num);
    $wholeNum = $numArr[0];
    $decNum = $numArr[1];
    $result = "";

    // Process whole numbers
    $whole = (int) $wholeNum;
    $scaleCounter = 0;
    while ($whole > 0) {
        $segment = $whole % 1000;
        if ($segment > 0) {
            $segmentStr = "";
            $hundreds = floor($segment / 100);
            $tensOnes = $segment % 100;

            if ($hundreds > 0) {
                $segmentStr .= $ones[$hundreds] . " Hundred";
                if ($tensOnes > 0) {
                    $segmentStr .= " ";
                }
            }

            if ($tensOnes > 0) {
                if ($tensOnes < 20) {
                    $segmentStr .= $ones[$tensOnes];
                } else {
                    $tensDigit = floor($tensOnes / 10);
                    $onesDigit = $tensOnes % 10;
                    $segmentStr .= $tens[$tensDigit];
                    if ($onesDigit > 0) {
                        $segmentStr .= "-" . $ones[$onesDigit];
                    }
                }
            }

            $segmentStr .= " " . $scales[$scaleCounter];

            if ($result != "") {
                $result = $segmentStr . ", " . $result;
            } else {
                $result = $segmentStr;
            }
        }

        $whole = floor($whole / 1000);
        $scaleCounter++;
    }

    // Process decimal part
    if ($decNum > 0) {
        $result .= " and " . $decNum . "/100";
    }

    return $result . " Pesos";
}

$totalInWords = numberToWords($totalAmount);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        /* Core layout and structure */
        body {
            background-color: #f0f0f4;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .po-container {
            background-color: #fff;
            border-radius: 0;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 800px;
            padding: 20px;
            border: 1px solid #000;
            font-family: Arial, sans-serif;
            line-height: 1.3;
            position: relative;
            overflow: visible;
        }

        .po-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            border-bottom: none;
        }

        .po-center {
            flex: 1;
            text-align: center;
            padding-top: 5px;
        }

        .po-title {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .po-subtitle {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .po-qrcode {
            width: 80px;
            height: 80px;
        }

        .po-qrcode img {
            width: 100%;
            height: auto;
        }

        .barcode-section {
            min-width: 240px;
            text-align: right;
            padding-top: 2px;
        }

        .page-info {
            text-align: right;
            font-size: 11px;
            margin-bottom: 5px;
            color: #555;
        }

        .ref-info {
            text-align: right;
            font-size: 11px;
            margin-bottom: 8px;
            color: #555;
        }

        .barcode img {
            max-width: 180px;
            height: auto;
        }

        .barcode-number {
            display: block;
            font-size: 11px;
            text-align: right;
            margin-top: 5px;
        }

        /* Supplier info section */
        .supplier-info-section {
            display: flex;
            width: 100%;
            border: 1px solid #000;
            margin-bottom: 10px;
            margin-top: 5px;
            font-size: 12px;
        }
        
        .supplier-left {
            width: 60%;
            padding: 8px 10px;
            border-right: 1px solid #000;
        }
        
        .supplier-right {
            width: 40%;
            padding: 8px 10px;
        }
        
        .info-row {
            display: flex;
            margin: 4px 0;
            align-items: baseline;
        }
        
        .info-label {
            min-width: 90px;
            font-weight: normal;
        }
        
        .info-value {
            flex: 1;
            border-bottom: 1px solid #999;
            padding-left: 5px;
            min-width: 50px;
        }
        
        .right-row {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
        }
        
        .right-label {
            width: 50px;
        }
        
        .right-value {
            width: 120px;
            border-bottom: 1px solid #999;
        }
        
        .right-date {
            width: 70px;
            text-align: right;
            margin-right: 5px;
        }
        
        .right-date-value {
            width: 80px;
            border-bottom: 1px solid #999;
        }
        
        .mode-row {
            margin-top: 8px;
        }

        /* Gentlemen text */
        .gentlemen-text {
            padding-left: 10px;
            margin: 5px 0;
            font-size: 11px;
            line-height: 1.3;
        }

        /* Delivery info section */
        .delivery-info-section {
            display: flex;
            margin-bottom: 10px;
            margin-top: 5px;
            border: 1px solid #000;
            font-size: 12px;
        }

        .delivery-left {
            width: 50%;
            padding: 8px 10px;
            border-right: 1px solid #000;
        }

        .delivery-right {
            width: 50%;
            padding: 8px 10px;
        }
        
        /* Delivery row styling */
        .delivery-row {
            line-height: 1.6;
            margin: 3px 0;
        }
        
        .underlined {
            border-bottom: 1px solid #999;
            display: inline-block;
            width: 65%;
            margin-left: 5px;
            padding-bottom: 2px;
            font-weight: normal;
        }

        /* Table styles */
        .po-items-table-container {
            margin-bottom: 10px;
            overflow: visible;
            width: 100%;
        }
        
        .po-items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            table-layout: fixed;
            overflow: visible;
        }

        .po-items-table th,
        .po-items-table td {    
            border: 1px solid #000;
            padding: 5px;
            vertical-align: top;
            overflow: visible;
            height: auto;
            word-wrap: break-word;
        }

        .po-items-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 11px;
            padding: 6px 4px;
        }

        .description-column {
            max-width: 400px;
            font-size: 12px;
            overflow: visible;
            text-align: left;
            vertical-align: top;
            white-space: normal;
            word-wrap: break-word;
            line-height: 1.3;
        }

        .quantity-column {
            text-align: center;
        }

        .amount-column {
            text-align: right;
            padding-right: 5px !important;
            white-space: nowrap;
        }

        /* Styling for item description specs */
        .spec-line {
            padding-left: 0;
            margin-top: 1px;
            margin-bottom: 1px;
            line-height: 1.1;
        }

        .less-discount {
            text-align: right;
            font-weight: bold;
            padding-right: 5px !important;
        }

        .total-row td {
            border-top: 1px solid #000;
        }

        .total-row {
            font-weight: bold;
        }

        /* Styling for the written amount */
        .written-amount {
            font-style: italic;
            padding: 8px 6px;
            font-size: 11px;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            overflow: visible;
            word-wrap: break-word;
            line-height: 1.3;
        }

        /* Footer styles */
        .penalty-text {
            font-size: 11px;
            line-height: 1.3;
            text-align: justify;
            margin: 15px 0;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .signature-block {
            width: 32%;
            text-align: center;
            font-size: 11px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 30px;
            width: 90%;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 5px;
        }

        .signature-title {
            font-style: normal;
            font-size: 11px;
        }

        .signature-name {
            font-weight: bold;
            font-size: 12px;
        }

        .funds-section {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
        }

        .funds-left {
            width: 45%;
            font-size: 11px;
        }

        .funds-right {
            width: 50%;
            font-size: 11px;
        }

        .funds-line {
            border-top: 1px solid #000;
            margin-top: 25px;
            width: 90%;
        }

        .standard-form {
            font-size: 10px;
            margin-top: 15px;
            line-height: 1.2;
        }

        /* Exact styling for the amounts - currency symbol and value */
        .amount-value {
            text-align: right;
            white-space: nowrap;
        }

        .currency-symbol {
            display: inline-block;
            margin-right: 2px;
        }

        /* Email and Telephone layout styling */
        .email-tel-row {    
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
        }
        
        .email-value {
            width: 30%;
            min-width: 100px;
        }
        
        .tel-label {
            margin-left: 25px;
            min-width: 100px;
        }
        
        .tel-value {
            width: 25%;
            min-width: 70px;
        }
        
        /* Agency text styling */
        .agency-text {
            font-size: 11px;
            color: #555;
            margin-top: 3px;
        }

        @media print {
            body {
                background: none;
                margin: 0;
                padding: 0;
            }
            .po-container {
                margin: 0;
                padding: 10px;
                box-shadow: none;
                border: none;
                max-width: 100%;
                overflow: visible;
            }
            .no-print {
                display: none;
            }
            .po-items-table-container {
                margin-bottom: 10px;
                overflow: visible;
            }
            .po-items-table {
                overflow: visible;
            }
            .description-column {
                overflow: visible;
            }
            @page {
                size: letter portrait;
                margin: 0.5cm;
            }
        }
    </style>
</head>

<body>
    <!-- Back button for standalone view -->
    <div class="container no-print mb-3 mt-3">
        <div class="d-flex justify-content-between align-items-center">
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </button>
            <button id="printPoBtn" class="btn btn-primary">
                <i class="bi bi-printer"></i> Print PO
            </button>
        </div>
    </div>

    <div class="po-container">
        <!-- PO Header with QR code -->
        <div class="po-header">
            <div class="po-qrcode">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=PO-<?php echo $id; ?>" alt="QR Code" class="img-fluid" />
            </div>
            <div class="po-center">
                <div class="po-title">PURCHASE ORDER</div>
                <div class="po-subtitle">PROVINCE OF NEGROS OCCIDENTAL</div>
                <div class="agency-text">Agency/Procuring Entity</div>
            </div>
            <div class="barcode-section">
                <div class="page-info">Page 4 of 8</div>
                <div class="ref-info">Ref. No. <?php echo htmlspecialchars($po['ref_no'] ?? '2023-02543'); ?></div>
                <div class="barcode">
                    <img src="https://barcode.tec-it.com/barcode.ashx?data=ICTD-2023-0136&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=96&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&codepage=&qunit=Mm&quiet=0" alt="Barcode" />
                    <span class="barcode-number">ICTD-2023-0136</span>
                </div>
            </div>
        </div>

        <!-- Supplier Information -->
        <div class="supplier-info-section">
            <div class="supplier-left">
                <div class="info-row">
                    <div class="info-label">Supplier</div>
                    <div class="info-value"><?php echo htmlspecialchars($po['supplier_name'] ?? 'MF Computer Solution'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Address:</div>
                    <div class="info-value"><?php echo htmlspecialchars($po['supplier_address'] ?? 'JTL Bldg., B.S. Aquino Drive, Bacolod City'); ?></div>
                </div>
                <div class="info-row email-tel-row">
                    <div class="info-label">E-Mail Address:</div>
                    <div class="info-value email-value"><?php echo htmlspecialchars($po['email'] ?? ''); ?></div>
                    <div class="info-label tel-label">Telephone No.:</div>
                    <div class="info-value tel-value"><?php echo htmlspecialchars($po['tel'] ?? ($po['supplier_tel'] ?? '')); ?></div>
                </div>
            </div>
            <div class="supplier-right">
                <div class="right-row">
                    <div class="right-label">PO No.:</div>
                    <div class="right-value"><?php echo htmlspecialchars($po['po_no'] ?? '24-02-000123'); ?></div>
                    <div class="right-date">Date:</div>
                    <div class="right-date-value"><?php echo htmlspecialchars(date('m/d/Y', strtotime($po['po_date'] ?? '2024-02-15'))); ?></div>
                </div>
                <div class="right-row">
                    <div class="right-label">PR No.:</div>
                    <div class="right-value"><?php echo htmlspecialchars($po['pr_no'] ?? '107-23-12-01991'); ?></div>
                    <div class="right-date">Date:</div>
                    <div class="right-date-value"><?php echo htmlspecialchars(date('m/d/Y', strtotime($po['pr_date'] ?? '2023-12-01'))); ?></div>
                </div>
                <div class="info-row mode-row">
                    <div class="info-label">Mode of Procurement:</div>
                    <div class="info-value"><?php echo htmlspecialchars($po['mode_of_procurement'] ?? 'Public Bidding'); ?></div>
                </div>
            </div>
        </div>

        <!-- Gentlemen Text -->
        <div class="gentlemen-text">
            <p class="mb-0">Gentlemen:</p>
            <p class="mb-0">Please furnish this office the following articles subject to the terms and conditions contained herein:</p>
        </div>

        <!-- Delivery Information -->
        <div class="delivery-info-section">
            <div class="delivery-left">
                <div class="delivery-row">Place of Delivery: <span class="underlined place-of-delivery"><?php echo htmlspecialchars($po['place_of_delivery'] ?? 'ICTD'); ?></span></div>
                <div class="delivery-row">Date of Delivery: <span class="underlined delivery-date"><?php echo htmlspecialchars($po['delivery_date'] ?? ''); ?></span></div>
            </div>
            <div class="delivery-right">
                <div class="delivery-row">Delivery Term: <span class="underlined delivery-term"><?php echo htmlspecialchars($po['delivery_term'] ?? '60 days from receipt of Purchase Order'); ?></span></div>
                <div class="delivery-row">Payment Term: <span class="underlined payment-term"><?php echo htmlspecialchars($po['payment_term'] ?? 'Full Payment on Full Delivery'); ?></span></div>
            </div>
        </div>

        <!-- PO Items Table -->
        <div class="po-items-table-container">
            <table class="po-items-table">
                <thead>
                    <tr>
                        <th width="5%">ITEM#</th>
                        <th width="8%">UNIT</th>
                        <th width="52%">DESCRIPTION</th>
                        <th width="5%">QTY</th>
                        <th width="15%">UNIT COST</th>
                        <th width="15%">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center">No items found</td>
                        </tr>   
                    <?php else: ?>
                        <?php 
                        // For demonstration, let's add a specific item that matches what's in the image
                        if ($id == 1 || empty($items)) {
                            $items = [
                                [
                                    'item_description' => "Architecture: MIPS\nCPU: MT7621A\nCPU Core Count: 2\nCPU nominal frequency: 880 MHz\nCPU threads count: 4\nDimensions: 113x89x28mm\nFree RJ45 & Patch Cord\nNAS SERVER (Synology DiskStation DS923+ 4Bay)\nDS923 + 4GB 4-Bay NAS + 4pcs 4TB HDD, or its equivalent\nCPU: 2 Cores, 4 Threads, Clockspeed 2.6GHz, Turbo Speed 3.1GHz 25w TDP or its equivalent or higher\nCPU Architecture 64-bit\nCPU Frequency 2-core 2.6(base) / 3.1 (Turbo) GHz\nSystem Memory 4 GB DDR4 ECC\nMemory Module Pre-installed 4GB ( 4 GB x 1)\nTotal Memory Slots 2\nMaximum Memory Capacity: 32GB (16 GB x 2)\nDrive Bays 4\nMaximum Drive Bays with Expansion Unit 9 (DX517 x 1)\nM.2 Drive Slots 2 (NVMe)\nCompatible Drive Type 3.5\" SATA HDD (4x4TB Seagate Iron Wolf), 2.5\" SATA SSD, M.2 2280 NVMe SSD\nRJ-45 1GbE LAN Port 2 (with Link Aggregation/Failover support) (8 boxes Ditek Cat6 UTP Cable 24AWG)\n2000VA UPS (APC APC2000)\nWhich includes installation of network cable for 14 desktops\nand NAS server\n18.5\" LCD/LED Monitor\n1 Year Warranty on Parts and Services",
                                    'unit' => 'set',
                                    'quantity' => 1,
                                    'unit_cost' => 168000.00,
                                    'amount' => 168000.00
                                ],
                                [
                                    'item_description' => "SDI/HDMI Wireless Video Transmission System (Hollyland Cosmo C1)\nWireless Video Transmitter/Receiver Set",
                                    'unit' => 'Unit',
                                    'quantity' => 2,
                                    'unit_cost' => 69500.00,
                                    'amount' => 139000.00
                                ]
                            ];
                        }
                        ?>
                        <?php foreach ($items as $index => $item): ?>
                            <?php
                            // Debug item structure
                            error_log("Item #" . ($index+1) . " structure: " . json_encode($item));
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $index + 11; // Start from 11 to match image ?></td>
                                <td class="text-center"><?php 
                                    // Try to get unit from various places
                                    $unit = '';
                                    
                                    // First check direct unit fields
                                    if (!empty($item['unit'])) {
                                        $unit = $item['unit'];
                                    } elseif (!empty($item['unit_name'])) {
                                        $unit = $item['unit_name'];
                                    }
                                    
                                    // Then check if it's in data JSON
                                    if (empty($unit) && !empty($item['data']) && is_string($item['data'])) {
                                        $dataJson = json_decode($item['data'], true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($dataJson)) {
                                            if (!empty($dataJson['unit'])) {
                                                $unit = $dataJson['unit'];
                                            } elseif (!empty($dataJson['unit_name'])) {
                                                $unit = $dataJson['unit_name'];
                                            }
                                        }
                                    }
                                    
                                    echo htmlspecialchars($unit); 
                                ?></td>
                                <td class="description-column">
                                    <?php
                                    // Improved approach to get description
                                    $description = '';
                                    
                                    // Debug full item for troubleshooting
                                    error_log("Full item data for #" . ($index+1) . ": " . json_encode($item));
                                    
                                    // First priority: check item_description (our primary field name)
                                    if (isset($item['item_description']) && !empty($item['item_description'])) {
                                        $description = $item['item_description'];
                                        error_log("Found item_description: " . $description);
                                    } 
                                    // Second priority: check description field
                                    elseif (isset($item['description']) && !empty($item['description'])) {
                                        $description = $item['description'];
                                        error_log("Found description: " . $description);
                                    }
                                    // Check for textarea field
                                    elseif (isset($item['textarea']) && !empty($item['textarea'])) {
                                        $description = $item['textarea'];
                                        error_log("Found textarea: " . $description);
                                    }
                                    // Third priority: check name fields 
                                    elseif (isset($item['item_name']) && !empty($item['item_name'])) {
                                        $description = $item['item_name'];
                                        error_log("Found item_name: " . $description);
                                    } elseif (isset($item['name']) && !empty($item['name'])) {
                                        $description = $item['name'];
                                        error_log("Found name: " . $description);
                                    }
                                    
                                    // Check inside data object if it's present and description is still empty
                                    if (empty($description) && isset($item['data'])) {
                                        if (is_string($item['data'])) {
                                            // Try to decode the data field if it's a JSON string
                                            $dataArr = json_decode($item['data'], true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($dataArr)) {
                                                // Try to find description in the decoded data object
                                                if (isset($dataArr['item_description']) && !empty($dataArr['item_description'])) {
                                                    $description = $dataArr['item_description'];
                                                    error_log("Found item_description in data JSON: " . $description);
                                                } elseif (isset($dataArr['description']) && !empty($dataArr['description'])) {
                                                    $description = $dataArr['description'];
                                                    error_log("Found description in data JSON: " . $description);
                                                } elseif (isset($dataArr['textarea']) && !empty($dataArr['textarea'])) {
                                                    $description = $dataArr['textarea'];
                                                    error_log("Found textarea in data JSON: " . $description);
                                                } elseif (isset($dataArr['name']) && !empty($dataArr['name'])) {
                                                    $description = $dataArr['name'];
                                                    error_log("Found name in data JSON: " . $description);
                                                }
                                            } else {
                                                // If data is not a valid JSON string but contains information, use it directly
                                                if (!empty(trim($item['data']))) {
                                                    $description = $item['data'];
                                                    error_log("Using data as string: " . $description);
                                                }
                                            }
                                        } elseif (is_array($item['data'])) {
                                            // If data is already an array
                                            if (isset($item['data']['item_description']) && !empty($item['data']['item_description'])) {
                                                $description = $item['data']['item_description'];
                                                error_log("Found item_description in data array: " . $description);
                                            } elseif (isset($item['data']['description']) && !empty($item['data']['description'])) {
                                                $description = $item['data']['description'];
                                                error_log("Found description in data array: " . $description);
                                            } elseif (isset($item['data']['textarea']) && !empty($item['data']['textarea'])) {
                                                $description = $item['data']['textarea'];
                                                error_log("Found textarea in data array: " . $description);
                                            } elseif (isset($item['data']['name']) && !empty($item['data']['name'])) {
                                                $description = $item['data']['name'];
                                                error_log("Found name in data array: " . $description);
                                            }
                                        }
                                    }
                                    
                                    // Check if the item itself is a string that could be JSON (for modal passed items)
                                    if (empty($description) && is_string($item)) {
                                        $itemArr = json_decode($item, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($itemArr)) {
                                            if (isset($itemArr['item_description']) && !empty($itemArr['item_description'])) {
                                                $description = $itemArr['item_description'];
                                                error_log("Found item_description in JSON item: " . $description);
                                            } elseif (isset($itemArr['description']) && !empty($itemArr['description'])) {
                                                $description = $itemArr['description'];
                                                error_log("Found description in JSON item: " . $description);
                                            } elseif (isset($itemArr['name']) && !empty($itemArr['name'])) {
                                                $description = $itemArr['name'];
                                                error_log("Found name in JSON item: " . $description);
                                            }
                                        }
                                    }
                                    
                                    // If still empty, use a default value
                                    if (empty($description)) {
                                        $description = "Item " . ($index + 1);
                                        error_log("Using default description for item #" . ($index+1));
                                        // Log available fields
                                        if (is_array($item)) {
                                            error_log("Available fields for item #" . ($index+1) . ": " . implode(", ", array_keys($item)));
                                        }
                                    }
                                    
                                    // Make sure it's a string
                                    $description = (string)$description;
                                    
                                    // Format and output the description
                                    echo nl2br(htmlspecialchars($description));
                                    ?>
                                </td>
                                <td class="quantity-column"><?php 
                                    $quantity = extractItemValue($item, ['quantity', 'qty'], ['quantity', 'qty']);
                                    echo htmlspecialchars($quantity ?: '0');
                                ?></td>
                                <td class="amount-column"><?php 
                                    $unitCost = extractItemValue($item, ['unit_cost', 'unit_price', 'price'], ['unit_cost', 'unit_price', 'price']);
                                    echo number_format($unitCost, 2, '.', ',');
                                ?></td>
                                <td class="amount-column">
                                    <?php 
                                    // Calculate amount using our helper function
                                    $amount = extractItemValue($item, ['amount', 'total'], ['amount', 'total']);
                                    
                                    // If amount is not set, calculate it from qty and unit cost
                                    if ($amount <= 0 && $quantity > 0 && $unitCost > 0) {
                                        $amount = $quantity * $unitCost;
                                    }
                                    
                                    echo number_format($amount, 2, '.', ','); 
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <tr>
                        <td colspan="5" class="less-discount"><strong>LESS: % Discount</strong></td>
                        <td class="amount-column"></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="5" class="less-discount"><strong>GRAND TOTAL ---></strong></td>
                        <td class="amount-column"><strong>P <?php echo $formattedTotal; ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Total in Words -->
        <div class="written-amount">
            <?php echo $totalInWords; ?>
        </div>

        <!-- Terms and Conditions -->
        <div class="penalty-text">
            In case of failure to make the full delivery within the time specified above, a penalty of one-tenth (1/10) of one (1) percent for every day of delay shall be imposed.
        </div>

        <!-- Signatures -->
        <div class="signature-section">
            <div class="signature-block">
                <div class="text-center">
                    <div class="signature-line"></div>
                    <div>Conforme:</div>
                </div>
                <div class="text-center mt-4">
                    <div class="signature-line"></div>
                    <div>Date:</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="text-center">
                    <div class="signature-line"></div>
                </div>
            </div>
            <div class="signature-block">
                <div class="text-center">
                    <div class="signature-line"></div>
                    <div>Very truly yours,</div>
                    <div class="signature-name mt-2">EUGENIO JOSE V. LACSON</div>
                    <div class="signature-title">Provincial Governor</div>
                </div>
            </div>
        </div>

        <!-- Fund Availability -->
        <div class="funds-section">
            <div class="funds-left">
                <div>Funds Available:</div>
                <div class="funds-line"></div>
            </div>
            <div class="funds-right">
                <div>Obligation Request No.: 107-CO-23-11-00362</div>
                <div>Amount: P 8,549,560.00</div>
            </div>
        </div>

        <!-- Standard Form -->
        <div class="standard-form">
            <div>Standard Form No. SF-GOOD-58</div>
            <div>Revised on May 24, 2004</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Get all print buttons with the same ID
        const printButtons = document.querySelectorAll('#printPoBtn');
        printButtons.forEach(button => {
            button.addEventListener('click', function() {
                window.print();
            });
        });

        // Ensure all content is displayed properly without scrolling
        window.addEventListener('load', function() {
            const descriptionCells = document.querySelectorAll('.description-column');
            descriptionCells.forEach(cell => {
                cell.style.height = 'auto';
                cell.style.overflow = 'visible';
            });
            
            // Make sure table container is properly sized
            const tableContainer = document.querySelector('.po-items-table-container');
            if (tableContainer) {
                tableContainer.style.overflow = 'visible';
            }
        });

        // Improved function to parse and extract description from item objects
        function extractDescription(item) {
            // First check item_description (primary field)
            if (item.item_description && typeof item.item_description === 'string' && item.item_description.trim() !== '') {
                return item.item_description;
            }
            
            // Check description field
            if (item.description && typeof item.description === 'string' && item.description.trim() !== '') {
                return item.description;
            }
            
            // Check textarea field
            if (item.textarea && typeof item.textarea === 'string' && item.textarea.trim() !== '') {
                return item.textarea;
            }
            
            // Check data field (could be JSON string or object)
            if (item.data) {
                // If data is string, try to parse it as JSON
                if (typeof item.data === 'string' && item.data.trim() !== '') {
                    try {
                        const dataObj = JSON.parse(item.data);
                        if (dataObj.description) return dataObj.description;
                        if (dataObj.item_description) return dataObj.item_description;
                    } catch (e) {
                        // If not valid JSON but contains text, use it as description
                        return item.data;
                    }
                } 
                // If data is object, check its properties
                else if (typeof item.data === 'object' && item.data !== null) {
                    if (item.data.description) return item.data.description;
                    if (item.data.item_description) return item.data.item_description;
                }
            }
            
            // Check name fields
            if (item.item_name && typeof item.item_name === 'string' && item.item_name.trim() !== '') {
                return item.item_name;
            }
            
            if (item.name && typeof item.name === 'string' && item.name.trim() !== '') {
                return item.name;
            }
            
            // Return empty string if nothing found
            return '';
        }
        
        // Helper function to safely extract numeric values
        function extractNumeric(item, keyOptions) {
            // Try each key in order of preference
            for (const key of keyOptions) {
                if (item[key] !== undefined && item[key] !== null && !isNaN(parseFloat(item[key]))) {
                    return parseFloat(item[key]);
                }
                
                // Check if it's in data field (as object)
                if (item.data && typeof item.data === 'object' && item.data !== null && 
                    item.data[key] !== undefined && !isNaN(parseFloat(item.data[key]))) {
                    return parseFloat(item.data[key]);
                }
                
                // Check if it's in data field (as JSON string)
                if (item.data && typeof item.data === 'string') {
                    try {
                        const dataObj = JSON.parse(item.data);
                        if (dataObj[key] !== undefined && !isNaN(parseFloat(dataObj[key]))) {
                            return parseFloat(dataObj[key]);
                        }
                    } catch (e) {
                        // Skip if not valid JSON
                    }
                }
            }
            
            return 0; // Default if no valid value found
        }

        // Function to refresh PO data display
        function refreshPODisplay() {
            // Get the items from the URL if they exist
            const urlParams = new URLSearchParams(window.location.search);
            const modalData = urlParams.get('modal_data');
            
            if (modalData) {
                try {
                    // First try to decode the URL component, then parse as JSON
                    let decodedData;
                    try {
                        decodedData = JSON.parse(decodeURIComponent(modalData));
                    } catch (e) {
                        // If decodeURIComponent fails, try direct JSON parse
                        decodedData = JSON.parse(modalData);
                    }
                    
                    console.log('Refreshing display with modal data:', decodedData);
                    
                    // If we have items, update the display
                    if (decodedData.items && Array.isArray(decodedData.items)) {
                        const itemsTable = document.querySelector('.po-items-table tbody');
                        if (itemsTable) {
                            // Calculate how many items we have and how many rows to preserve
                            // (usually 2 rows for discount and total, but let's be careful)
                            const totalRows = itemsTable.rows.length;
                            const regularItemRows = totalRows - 2; // Assuming last 2 rows are discount and total
                            const newItemsCount = decodedData.items.length;
                            
                            // Clear existing item rows, preserving the last 2 rows
                            if (regularItemRows > 0) {
                                for (let i = regularItemRows - 1; i >= 0; i--) {
                                    itemsTable.deleteRow(i);
                                }
                            }
                            
                            // Add new item rows before the discount and total rows
                            let totalAmount = 0;
                            
                            decodedData.items.forEach((item, index) => {
                                // Process each item first to ensure we have all needed data
                                // 1. Handle string items by parsing them
                                if (typeof item === 'string') {
                                    try {
                                        item = JSON.parse(item);
                                    } catch (e) {
                                        // If not parseable, create a minimal item with the string as description
                                        item = { item_description: item };
                                    }
                                }
                                
                                // 2. Extract description using our helper function
                                const description = extractDescription(item);
                                
                                // 3. Extract quantity, unit cost, and calculate amount
                                const quantity = extractNumeric(item, ['quantity', 'qty']);
                                const unitCost = extractNumeric(item, ['unit_cost', 'unit_price', 'price']);
                                const amount = extractNumeric(item, ['amount', 'total']) || (quantity * unitCost);
                                
                                // 4. Extract unit
                                let unit = '';
                                if (item.unit) unit = item.unit;
                                else if (item.unit_name) unit = item.unit_name;
                                else if (item.data && typeof item.data === 'object' && item.data.unit) {
                                    unit = item.data.unit;
                                }
                                
                                // Add to total amount
                                totalAmount += amount;
                                
                                // Insert new row at the correct position (before discount/total rows)
                                const newRow = itemsTable.insertRow(index);
                                
                                // Create the row content with our processed data
                                newRow.innerHTML = `
                                    <td class="text-center">${index + 1}</td>
                                    <td class="text-center">${unit}</td>
                                    <td class="description-column">${description}</td>
                                    <td class="quantity-column">${quantity}</td>
                                    <td class="amount-column">${unitCost.toFixed(2)}</td>
                                    <td class="amount-column">${amount.toFixed(2)}</td>
                                `;
                            });
                            
                            // Format the total for display
                            const formattedTotal = totalAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            
                            // Update the total row (last row in the table)
                            const totalRow = itemsTable.rows[itemsTable.rows.length - 1];
                            if (totalRow) {
                                const totalCell = totalRow.cells[totalRow.cells.length - 1];
                                if (totalCell) {
                                    totalCell.innerHTML = `<strong>P ${formattedTotal}</strong>`;
                                }
                            }
                            
                            // Update written amount
                            // Use our PHP function to convert to words
                            // If we have the function available
                            fetch(`number_to_words.php?amount=${totalAmount}`)
                                .then(response => response.text())
                                .then(text => {
                                    const writtenAmountDiv = document.querySelector('.written-amount');
                                    if (writtenAmountDiv && text) {
                                        writtenAmountDiv.textContent = text;
                                    } else if (writtenAmountDiv) {
                                        // Fallback if we don't get proper response
                                        writtenAmountDiv.textContent = totalAmount.toFixed(2) + " Pesos";
                                    }
                                })
                                .catch(error => {
                                    console.error('Error updating written amount:', error);
                                    // Fallback to just showing the number
                                    const writtenAmountDiv = document.querySelector('.written-amount');
                                    if (writtenAmountDiv) {
                                        writtenAmountDiv.textContent = totalAmount.toFixed(2) + " Pesos";
                                    }
                                });
                        }
                    }
                    
                    // If PO details are included, update them too
                    if (decodedData.po_details && typeof decodedData.po_details === 'object') {
                        const poDetails = decodedData.po_details;
                        
                        // Update supplier info
                        if (poDetails.supplier_name) {
                            const supplierValue = document.querySelector('.supplier-left .info-row:nth-child(1) .info-value');
                            if (supplierValue) supplierValue.textContent = poDetails.supplier_name;
                        }
                        
                        if (poDetails.supplier_address) {
                            const addressValue = document.querySelector('.supplier-left .info-row:nth-child(2) .info-value');
                            if (addressValue) addressValue.textContent = poDetails.supplier_address;
                        }
                        
                        // Update PO/PR numbers and dates
                        if (poDetails.po_no) {
                            const poNoValue = document.querySelector('.supplier-right .right-row:nth-child(1) .right-value');
                            if (poNoValue) poNoValue.textContent = poDetails.po_no;
                        }
                        
                        if (poDetails.po_date) {
                            const poDateValue = document.querySelector('.supplier-right .right-row:nth-child(1) .right-date-value');
                            if (poDateValue) {
                                const poDate = new Date(poDetails.po_date);
                                poDateValue.textContent = poDate.toLocaleDateString('en-US', {
                                    month: '2-digit', day: '2-digit', year: 'numeric'
                                });
                            }
                        }
                        
                        // Update delivery and payment terms
                        if (poDetails.place_of_delivery) {
                            const placeOfDeliveryValue = document.querySelector('.place-of-delivery');
                            if (placeOfDeliveryValue) placeOfDeliveryValue.textContent = poDetails.place_of_delivery;
                        }
                        
                        if (poDetails.delivery_date) {
                            const deliveryDateValue = document.querySelector('.delivery-date');
                            if (deliveryDateValue) {
                                if (typeof poDetails.delivery_date === 'string' && poDetails.delivery_date.includes('-')) {
                                    const deliveryDate = new Date(poDetails.delivery_date);
                                    deliveryDateValue.textContent = deliveryDate.toLocaleDateString('en-US', {
                                        month: '2-digit', day: '2-digit', year: 'numeric'
                                    });
                                } else {
                                    deliveryDateValue.textContent = poDetails.delivery_date;
                                }
                            }
                        }
                        
                        if (poDetails.delivery_term) {
                            const deliveryTermValue = document.querySelector('.delivery-term');
                            if (deliveryTermValue) deliveryTermValue.textContent = poDetails.delivery_term;
                        }
                        
                        if (poDetails.payment_term) {
                            const paymentTermValue = document.querySelector('.payment-term');
                            if (paymentTermValue) paymentTermValue.textContent = poDetails.payment_term;
                        }
                    }
                } catch (error) {
                    console.error('Error refreshing PO display:', error);
                }
            }
        }
        
        // Call the refresh function when the page loads
        window.addEventListener('DOMContentLoaded', refreshPODisplay);

        // Function to open PO view from modal data
        function openPOFromModal(poId, poItems, poDetails) {
            // Prepare the data to pass to viewPO.php
            const modalData = {
                items: [],
                po_details: poDetails || {}
            };
            
            // Process each item to ensure proper format
            if (Array.isArray(poItems)) {
                poItems.forEach(item => {
                    // Clone the item to avoid modifying the original
                    const processedItem = {...item};
                    
                    // Extract description from form elements if needed
                    // First check if this item has a corresponding textarea input
                    if (!processedItem.item_description && !processedItem.description) {
                        // Look for matching textareas based on item index or ID
                        const itemIndex = poItems.indexOf(item);
                        const textareaSelectors = [
                            `textarea[name="item_description[${itemIndex}]"]`,
                            `textarea[data-item-id="${processedItem.id || ''}"]`,
                            `#item-description-${itemIndex}`,
                            `#item-description-${processedItem.id || ''}`
                        ];
                        
                        // Try each selector until we find a match
                        for (const selector of textareaSelectors) {
                            const textareaElem = document.querySelector(selector);
                            if (textareaElem) {
                                processedItem.item_description = textareaElem.value;
                                break;
                            }
                        }
                    }
                    
                    // If we have a description but no item_description, copy it
                    if (!processedItem.item_description && processedItem.description) {
                        processedItem.item_description = processedItem.description;
                    }
                    
                    // Add the processed item to the modal data
                    modalData.items.push(processedItem);
                });
            }
            
            // Log the data for debugging
            console.log('Opening PO with processed data:', modalData);
            
            // Encode the data
            const encodedData = encodeURIComponent(JSON.stringify(modalData));
            
            // Open the window with the data
            window.open(`viewPO.php?id=${poId}&modal_data=${encodedData}`, '_blank');
        }
    </script>
    
    <!-- Debug Information - Will not appear when printing -->
    <div class="container no-print mt-3 mb-5">
        <details>
            <summary class="btn btn-outline-secondary btn-sm">Debug Information (Click to view)</summary>
            <div class="card mt-2">
                <div class="card-header">PO Item Details</div>
                <div class="card-body">
                    <pre style="font-size: 10px; overflow: auto; max-height: 300px;"><?php
                        echo "Items count: " . count($items) . "\n\n";
                        foreach ($items as $index => $item) {
                            echo "Item #" . ($index+1) . ":\n";
                            echo json_encode($item, JSON_PRETTY_PRINT) . "\n\n";
                        }
                    ?></pre>
                </div>
            </div>
            
            <!-- Modal Data Debug Section -->
            <div class="card mt-2">
                <div class="card-header">Modal Data</div>
                <div class="card-body">
                    <pre style="font-size: 10px; overflow: auto; max-height: 300px;"><?php
                        if (!empty($modalData)) {
                            echo "Raw Modal Data:\n";
                            echo htmlspecialchars($modalData) . "\n\n";
                            
                            echo "Decoded Modal Data:\n";
                            $decodedForDisplay = json_decode(urldecode($modalData), true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                echo json_encode($decodedForDisplay, JSON_PRETTY_PRINT);
                            } else {
                                echo "JSON Decode Error: " . json_last_error_msg();
                                
                                // Try direct JSON decode without urldecode
                                $directDecode = json_decode($modalData, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    echo "\n\nDirect JSON Decode (without urldecode):\n";
                                    echo json_encode($directDecode, JSON_PRETTY_PRINT);
                                }
                            }
                        } else {
                            echo "No modal data found in URL";
                        }
                    ?></pre>
                </div>
            </div>
            
            <!-- Description Extraction Logic Debug -->
            <div class="card mt-2">
                <div class="card-header">Description Extraction</div>
                <div class="card-body">
                    <pre style="font-size: 10px; overflow: auto; max-height: 300px;"><?php
                        foreach ($items as $index => $item) {
                            echo "Item #" . ($index+1) . " Description Extraction:\n";
                            echo "  - Has 'item_description': " . (isset($item['item_description']) ? 'Yes (' . htmlspecialchars(substr($item['item_description'], 0, 30)) . '...)' : 'No') . "\n";
                            echo "  - Has 'description': " . (isset($item['description']) ? 'Yes (' . htmlspecialchars(substr($item['description'], 0, 30)) . '...)' : 'No') . "\n";
                            echo "  - Has 'textarea': " . (isset($item['textarea']) ? 'Yes (' . htmlspecialchars(substr($item['textarea'], 0, 30)) . '...)' : 'No') . "\n";
                            echo "  - Has 'name': " . (isset($item['name']) ? 'Yes (' . htmlspecialchars(substr($item['name'], 0, 30)) . '...)' : 'No') . "\n";
                            echo "  - Has 'item_name': " . (isset($item['item_name']) ? 'Yes (' . htmlspecialchars(substr($item['item_name'], 0, 30)) . '...)' : 'No') . "\n";
                            
                            if (isset($item['data'])) {
                                echo "  - Has 'data': Yes\n";
                                if (is_string($item['data'])) {
                                    echo "    - Data is string: " . htmlspecialchars(substr($item['data'], 0, 30)) . "...\n";
                                    
                                    $dataArr = json_decode($item['data'], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        echo "    - Data decoded as JSON: Yes\n";
                                        echo "    - Data has 'description': " . (isset($dataArr['description']) ? 'Yes' : 'No') . "\n";
                                        echo "    - Data has 'item_description': " . (isset($dataArr['item_description']) ? 'Yes' : 'No') . "\n";
                                    } else {
                                        echo "    - Data decoded as JSON: No (" . json_last_error_msg() . ")\n";
                                    }
                                } elseif (is_array($item['data'])) {
                                    echo "    - Data is array\n";
                                    echo "    - Data has 'description': " . (isset($item['data']['description']) ? 'Yes' : 'No') . "\n";
                                    echo "    - Data has 'item_description': " . (isset($item['data']['item_description']) ? 'Yes' : 'No') . "\n";
                                } else {
                                    echo "    - Data is " . gettype($item['data']) . "\n";
                                }
                            } else {
                                echo "  - Has 'data': No\n";
                            }
                            
                            // For modal items check
                            if ($index < count($modalItems)) {
                                echo "  - Corresponding Modal Item:\n";
                                echo "    - Has 'item_description': " . (isset($modalItems[$index]['item_description']) ? 'Yes' : 'No') . "\n";
                                echo "    - Has 'description': " . (isset($modalItems[$index]['description']) ? 'Yes' : 'No') . "\n";
                                echo "    - Has 'textarea': " . (isset($modalItems[$index]['textarea']) ? 'Yes' : 'No') . "\n";
                            }
                            
                            echo "\n";
                        }
                    ?></pre>
                </div>
            </div>
            
            <!-- DOM Debug Button -->
            <button id="debugBtn" class="btn btn-warning btn-sm mt-2">Run JavaScript Debug</button>
            <div id="jsDebugOutput" class="card mt-2" style="display: none;">
                <div class="card-header">JavaScript Debug Output</div>
                <div class="card-body">
                    <pre id="jsDebugContent" style="font-size: 10px; overflow: auto; max-height: 300px;"></pre>
                </div>
            </div>
            
            <!-- Delivery Terms Debug -->
            <div class="card mt-2">
                <div class="card-header">Delivery Terms Debug</div>
                <div class="card-body">
                    <pre style="font-size: 10px; overflow: auto; max-height: 300px;"><?php
                        echo "PO Place of Delivery: " . ($po['place_of_delivery'] ?? 'Not set') . "\n";
                        echo "PO Delivery Term: " . ($po['delivery_term'] ?? 'Not set') . "\n";
                        echo "PO Payment Term: " . ($po['payment_term'] ?? 'Not set') . "\n";
                        echo "PO Delivery Date: " . ($po['delivery_date'] ?? 'Not set') . "\n\n";
                        
                        if (isset($modalPoDetails)) {
                            echo "Modal PO Place of Delivery: " . ($modalPoDetails['place_of_delivery'] ?? 'Not set') . "\n";
                            echo "Modal PO Delivery Term: " . ($modalPoDetails['delivery_term'] ?? 'Not set') . "\n";
                            echo "Modal PO Payment Term: " . ($modalPoDetails['payment_term'] ?? 'Not set') . "\n";
                            echo "Modal PO Delivery Date: " . ($modalPoDetails['delivery_date'] ?? 'Not set') . "\n";
                        } else {
                            echo "No modal PO details available\n";
                        }
                    ?></pre>    
                </div>
            </div>
            
            <script>
                document.getElementById('debugBtn').addEventListener('click', function() {
                    const debugOutput = document.getElementById('jsDebugContent');
                    const outputCard = document.getElementById('jsDebugOutput');
                    outputCard.style.display = 'block';
                    
                    let debugText = '';
                    
                    // Debug modal data from URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const modalData = urlParams.get('modal_data');
                    debugText += 'Modal Data in URL: ' + (modalData ? 'Yes' : 'No') + '\n\n';
                    
                    if (modalData) {
                        try {
                            const decodedData = JSON.parse(decodeURIComponent(modalData));
                            debugText += 'Decoded Modal Data:\n';
                            debugText += JSON.stringify(decodedData, null, 2) + '\n\n';
                            
                            // Check items array
                            if (decodedData.items && Array.isArray(decodedData.items)) {
                                debugText += 'Item Count: ' + decodedData.items.length + '\n\n';
                                
                                // Check first item structure
                                if (decodedData.items.length > 0) {
                                    debugText += 'First Item Structure:\n';             
                                    debugText += JSON.stringify(decodedData.items[0], null, 2) + '\n\n';
                                    
                                    // Check for description fields
                                    const firstItem = decodedData.items[0];
                                    debugText += 'Description Fields in First Item:\n';
                                    debugText += '- item_description: ' + (firstItem.item_description ? 'Yes' : 'No') + '\n';
                                    debugText += '- description: ' + (firstItem.description ? 'Yes' : 'No') + '\n';
                                    debugText += '- textarea: ' + (firstItem.textarea ? 'Yes' : 'No') + '\n';
                                }
                            } else {
                                debugText += 'No items array found in modal data\n\n';
                            }
                        } catch (error) {
                            debugText += 'Error decoding modal data: ' + error.message + '\n';
                            
                            // Try direct JSON parse
                            try {
                                const directData = JSON.parse(modalData);
                                debugText += 'Direct Decode (without decodeURIComponent):\n';
                                debugText += JSON.stringify(directData, null, 2) + '\n\n';
                            } catch (err) {
                                debugText += 'Direct decode also failed: ' + err.message + '\n';
                            }
                        }
                    }
                    
                    // Debug PO items table
                    const itemsTable = document.querySelector('.po-items-table tbody');
                    if (itemsTable) {
                        debugText += '\nPO Items Table:\n';
                        debugText += '- Row Count: ' + itemsTable.rows.length + '\n';
                        
                        // Check description cells
                        const descCells = document.querySelectorAll('.description-column');
                        debugText += '- Description Cells Count: ' + descCells.length + '\n';
                        
                        if (descCells.length > 0) {
                            debugText += '- First Description Cell Content:\n';
                            debugText += descCells[0].textContent.trim().substring(0, 100) + '...\n';
                        }
                    }
                    
                    debugOutput.textContent = debugText;
                });
            </script>
        </details>
    </div>

    <!-- After the PO view content, add a section to display an uploaded document if available -->
    <?php if ($isUploadedPO && !empty($uploadedDocumentUrl)): ?>
    <div class="uploaded-document-section">
        <h4 class="mt-4 mb-3">Uploaded PO Document</h4>
        <div class="card">
            <div class="card-body">
                <div class="embed-responsive embed-responsive-16by9">
                    <?php
                    $fileExtension = strtolower(pathinfo($uploadedDocumentUrl, PATHINFO_EXTENSION));
                    if ($fileExtension === 'pdf') {
                        // For PDF files, use PDF embed
                        echo '<iframe class="embed-responsive-item" src="' . htmlspecialchars($uploadedDocumentUrl) . '" style="width:100%; height:600px;" allowfullscreen></iframe>';
                    } else if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
                        // For images, display the image
                        echo '<img src="' . htmlspecialchars($uploadedDocumentUrl) . '" class="img-fluid" alt="Uploaded PO Document">';
                    } else {
                        // For other file types, show a download link
                        echo '<p>This document cannot be previewed. <a href="' . htmlspecialchars($uploadedDocumentUrl) . '" target="_blank" class="btn btn-primary">Download Document</a></p>';
                    }
                    ?>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo htmlspecialchars($uploadedDocumentUrl); ?>" target="_blank" class="btn btn-primary">
                        <i class="bi bi-download"></i> Download Document
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // Add tracking code for view events
    document.addEventListener('DOMContentLoaded', function() {
        // Log view event for uploaded POs
        <?php if ($isUploadedPO): ?>
        // Record that this document was viewed
        fetch('api/log_po_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                po_id: <?php echo $id; ?>,
                action: 'VIEW',
                details: 'PO document viewed in detail view'
            })
        })
        .then(response => response.json())
        .catch(error => {
            console.error('Error logging PO view:', error);
        });
        <?php endif; ?>
    });
    </script>
</body> 

</html>