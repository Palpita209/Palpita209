<?php
// Include database configuration
include 'config/db.php';

// Get PAR ID from query string
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get the print parameter
$autoPrint = isset($_GET['print']) && $_GET['print'] === 'true';

if ($id <= 0) {
    echo "Invalid PAR ID";
    exit;
}

// Function to convert numbers to words
function numberToWords($num)
{
    $ones = array(
        0 => "ZERO",
        1 => "ONE",
        2 => "TWO",
        3 => "THREE",
        4 => "FOUR",
        5 => "FIVE",
        6 => "SIX",
        7 => "SEVEN",
        8 => "EIGHT",
        9 => "NINE",
        10 => "TEN",
        11 => "ELEVEN",
        12 => "TWELVE",
        13 => "THIRTEEN",
        14 => "FOURTEEN",
        15 => "FIFTEEN",
        16 => "SIXTEEN",
        17 => "SEVENTEEN",
        18 => "EIGHTEEN",
        19 => "NINETEEN"
    );
    
    $tens = array(
        1 => "TEN",
        2 => "TWENTY",
        3 => "THIRTY",
        4 => "FORTY",
        5 => "FIFTY",
        6 => "SIXTY",
        7 => "SEVENTY",
        8 => "EIGHTY",
        9 => "NINETY"
    );
    
    $hundreds = array(
        "HUNDRED",
        "THOUSAND",
        "MILLION",
        "BILLION",
        "TRILLION",
        "QUADRILLION"
    );

    if ($num == 0) {
        return $ones[0];
    }

    $num = number_format($num, 2, '.', ',');
    $num_arr = explode(".", $num);
    $whole = $num_arr[0];
    $fraction = $num_arr[1];

    $whole_arr = array_reverse(explode(",", $whole));
    $result = "";

    foreach ($whole_arr as $key => $value) {
        $value = (int)$value;
        if ($value) {
            $key_name = $key > 0 ? $hundreds[$key] . " " : "";
            if ($value < 20) {
                $result = $ones[$value] . " " . $key_name . $result;
            } elseif ($value < 100) {
                $result = $tens[floor($value/10)] . " " . $ones[$value%10] . " " . $key_name . $result;
            } else {
                $result = $ones[floor($value/100)] . " HUNDRED " . 
                           $tens[floor(($value%100)/10)] . " " . 
                           $ones[($value%100)%10] . " " . $key_name . $result;
            }
        }
    }

    if ($fraction > 0) {
        $result .= " AND {$fraction}/100";
    }

    return rtrim($result);
}

try {
    // Get database connection
    $conn = getConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Get PAR details
    $stmt = $conn->prepare("SELECT par.par_id, par.par_no, 
                            par.entity_name, 
                            CASE 
                                WHEN par.date_acquired IS NULL OR par.date_acquired = '0000-00-00' THEN CURDATE()
                                ELSE DATE_FORMAT(par.date_acquired, '%Y-%m-%d')
                            END as date_acquired, 
                            u.full_name as received_by_name, 
                            par.position, 
                            par.department, 
                            par.total_amount, 
                            par.remarks, 
                            'Province of Negros Occidental' as fund
                            FROM property_acknowledgement_receipts par 
                            LEFT JOIN users u ON par.received_by = u.user_id 
                            WHERE par.par_id = ?");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $par = $result->fetch_assoc();

    if (!$par) {
        echo "<div class='alert alert-danger'>Property Acknowledgement Receipt not found</div>";
        exit;
    }

    // Get PAR items
    $itemsStmt = $conn->prepare("SELECT quantity, 
                                unit, 
                                description, 
                                property_number, 
                                CASE 
                                    WHEN date_acquired IS NULL OR date_acquired = '0000-00-00' THEN CURDATE()
                                    ELSE DATE_FORMAT(date_acquired, '%Y-%m-%d')
                                END as date_acquired, 
                                amount 
                                FROM par_items 
                                WHERE par_id = ?");
    
    if (!$itemsStmt) {
        throw new Exception("Failed to prepare items statement: " . $conn->error);
    }
    
    $itemsStmt->bind_param("i", $id);
    if (!$itemsStmt->execute()) {
        throw new Exception("Failed to execute items query: " . $itemsStmt->error);
    }
    
    $itemsResult = $itemsStmt->get_result();
    $items = [];
    $totalAmount = 0;
    while ($item = $itemsResult->fetch_object()) {
        $items[] = $item;
        $totalAmount += floatval($item->quantity) * floatval($item->amount);
    }

    // If total_amount is zero or null, update it with the calculated total
    if (empty($par['total_amount']) && $totalAmount > 0) {
        $updateStmt = $conn->prepare("UPDATE property_acknowledgement_receipts SET total_amount = ? WHERE par_id = ?");
        $updateStmt->bind_param("di", $totalAmount, $id);
        $updateStmt->execute();
        $par['total_amount'] = $totalAmount;
    }

    // Format date
    $dateFormatted = $par['date_acquired'] ?? date('m-d-Y');
    
} catch (Exception $e) {
    error_log("Error in viewPAR.php: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    exit;
}

// Auto print if requested
$printScript = '';
if ($autoPrint) {
    $printScript = '<script>window.onload = function() { window.print(); }</script>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Acknowledgement Receipt - <?php echo htmlspecialchars($par['par_no']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php echo $printScript; ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            background-color: #f5f5f5;
        }
        .page {
            width: 8.5in;
            min-height: 11in;
            padding: 0.5in;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: visible;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
        }
        .appendix {
            position: absolute;
            top: 0;
            right: 0;
            font-size: 10px;
            color: #333;
        }
        .title {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 10px 0;
        }
        .par-details {
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .par-number {
            font-weight: bold;
            font-size: 13px;
        }
        .table-container {
            width: 100%;
            margin-bottom: 15px;
            overflow: visible;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            page-break-inside: auto;
            overflow: visible;
            table-layout: fixed;
        }
        .table-items th, .table-items td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 11px;
            vertical-align: top;
            height: auto;
            overflow: visible;
            word-wrap: break-word;
        }
        .table-items th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .description-column {
            max-width: 300px;
            overflow: visible;
            text-align: left;
        }
        .par-footer {
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .signature-block {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            font-weight: bold;
            width: 200px;
            margin-left: auto;
            margin-right: auto;
        }
        .position {
            font-size: 11px;
            font-style: italic;
        }
        .entity-title {
            font-weight: bold;
            font-size: 13px;
            text-align: center;
            margin-bottom: 5px;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            opacity: 0.05;
            pointer-events: none;
            z-index: 0;
        }
        .control-buttons {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .page-number {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 10px;
            color: #666;
        }
        .instructions-box {
            border: 1px solid #ccc;
            padding: 8px;
            font-size: 10px;
            background-color: #f9f9f9;
            margin-top: 10px;
        }
        .currency {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .quantity {
            text-align: center;
        }
        .unit {
            text-align: center;
            text-transform: uppercase;
        }
        .date-cell {
            text-align: center;
            white-space: nowrap;
        }
        .property-number {
            font-size: 10px;
            word-break: break-all;
        }
        .notary-seal {
            font-size: 10px;
            margin-top: 15px;
            color: #555;
        }
        .serial-numbers {
            font-size: 8px;
            color: #666;
            word-break: break-all;
            margin-top: 5px;
            line-height: 1.2;
        }
        .signature-name {
            font-weight: bold;
            margin-bottom: 0;
        }
        .signature-position {
            font-style: italic;
            font-size: 10px;
        }
        @media print {
            body {
                background: none;
                margin: 0;
                padding: 0;
            }
            .page {
                box-shadow: none;
                margin: 0;
                padding: 0.2in;
                overflow: visible;
                width: 100%;
                min-height: auto;
            }
            .control-buttons, .no-print {
                display: none !important;
            }
            .watermark {
                display: none;
            }
            .table-container, .table-items, .table-items th, .table-items td {
                overflow: visible !important;
            }
            @page {
                size: letter portrait;
                margin: 0.5cm;
            }
        }
    </style>
</head>
<body>
    <div class="no-print container mb-3 mt-3">
        <div class="d-flex justify-content-between align-items-center">
            <button class="btn btn-secondary" onclick="window.close()">
                <i class="bi bi-arrow-left"></i> Back
            </button>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print PAR
            </button>
        </div>
    </div>

    <div class="page">
        <div class="header">
            <p class="appendix mb-0">Appendix 61</p>
            <h5 class="title">PROPERTY ACKNOWLEDGEMENT RECEIPT</h5>
        </div>

        <div class="par-details">
            <div class="row mb-3">
                <div class="col-6">
                    <strong>LGU:</strong> <?php echo $par['fund'] ?? 'Province of Negros Occidental'; ?>
                </div>
                <div class="col-6 text-end">
                    <strong>PAR No:</strong> <span class="par-number"><?php echo $par['par_no'] ?? ''; ?></span>
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="table-items">
                <thead>
                    <tr>
                        <th width="5%">QTY</th>
                        <th width="5%">Unit</th>
                        <th width="50%">Description</th>
                        <th width="15%">Property Number</th>
                        <th width="10%">Date Acquired</th>
                        <th width="15%">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $displayTotal = 0;
                    if (!empty($items)): 
                        foreach ($items as $item): 
                            $itemAmount = floatval($item->amount);
                            $itemQty = floatval($item->quantity);
                            $rowTotal = $itemQty * $itemAmount;
                            $displayTotal += $rowTotal;
                    ?>
                    <tr>
                        <td class="quantity"><?php echo $item->quantity; ?></td>
                        <td class="unit"><?php echo $item->unit; ?></td>
                        <td><?php echo $item->description; ?></td>
                        <td class="property-number"><?php echo $item->property_number; ?></td>
                        <td class="date-cell"><?php echo $item->date_acquired; ?></td>
                        <td class="currency">₱<?php echo number_format($itemAmount, 2); ?></td>
                    </tr>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                    <tr>
                        <td colspan="6" class="text-center">No items found</td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Total row -->
                    <tr>
                        <td colspan="5" class="text-end"><strong>Total Amount:</strong></td>
                        <td class="currency"><strong>₱<?php echo number_format($displayTotal, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="row">
            <div class="col-6">
                <div class="instructions-box">
                    <p class="mb-0"><strong>Pls. assign property number corresponding to the item descriptions (serial, tag colors, et al.) as reflected (left to right/top to bottom respectively).</strong></p>
                </div>
            </div>
        </div>

        <div class="footer row mt-5">
            <div class="col-6">
                <p><strong>ISSUED BY:</strong></p>
                <div class="text-center mt-4">
                    <div class="signature-line mx-auto"></div>
                    <p class="signature-name mb-0">ARNEL D. ARGUSAR, MPA</p>
                    <p class="signature-position">Provincial General Services Officer</p>
                </div>
            </div>
            <div class="col-6">
                <p><strong>RECEIVED BY:</strong></p>
                <div class="text-center mt-4">
                    <div class="signature-line mx-auto"></div>
                    <p class="signature-name mb-0"><?php echo strtoupper($par['received_by_name'] ?? 'JOSE LARRY L. SANOR'); ?></p>
                    <p class="signature-position"><?php echo $par['position'] ?? 'OIC-ITD'; ?></p>
                </div>
            </div>
        </div>
        
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Automatically trigger print dialog when print parameter is set
            <?php if ($autoPrint): ?>
            setTimeout(function() {
                window.print();
            }, 500); // Small delay to ensure page is fully loaded
            <?php endif; ?>
        });
    </script>
</body>
</html> 