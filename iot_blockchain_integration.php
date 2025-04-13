<?php
/**
 * IoT and Blockchain Integration for ICTD Inventory Management System
 * This file handles the IoT device interaction and blockchain transaction processing
 */

// Include database connection
require_once 'config/db.php';

class IoTBlockchainIntegration {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    /**
     * Get IoT sensor data
     * @return array Sensor data with status
     */
    public function getSensorsData() {
        try {
            // Get inventory, PAR, and PO data instead of simulated sensor data
            $data = [
                'active_sensors' => 0,
                'data_points' => 0,
                'last_update' => rand(1, 10),
                'health' => rand(90, 100),
                'sensors' => []
            ];
            
            // Get real data from inventory, PO, and PAR tables
            $inventoryItems = $this->getInventoryItemsForIoT();
            $poItems = $this->getPOItemsForIoT();
            $parItems = $this->getPARItemsForIoT();
            
            // Combine all items for display
            $sensors = array_merge($inventoryItems, $poItems, $parItems);
            $data['active_sensors'] = count($sensors);
            $data['data_points'] = count($sensors) * rand(5, 15); // Some arbitrary calculation
            $data['sensors'] = $sensors;
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Error getting sensor data: " . $e->getMessage());
            return [
                'error' => 'Failed to retrieve sensor data',
                'active_sensors' => 0,
                'data_points' => 0,
                'last_update' => 0,
                'health' => 0,
                'sensors' => []
            ];
        }
    }
    
    /**
     * Get blockchain status and transaction data
     * @return array Blockchain status information
     */
    public function getBlockchainStatus() {
        try {
            // In a real implementation, this would connect to your blockchain service
            // For demo purposes, we'll generate simulated data
            
            $transactions = $this->getRecentTransactions();
            
            return [
                'total_transactions' => count($transactions),
                'chain_health' => rand(95, 100),
                'last_block' => '#' . rand(24000, 25000),
                'recent_transactions' => array_slice($transactions, 0, 5) // Return last 5 transactions
            ];
            
        } catch (Exception $e) {
            error_log("Error getting blockchain status: " . $e->getMessage());
            return [
                'error' => 'Failed to retrieve blockchain data',
                'total_transactions' => 0,
                'chain_health' => 0,
                'last_block' => '#0',
                'recent_transactions' => []
            ];
        }
    }
    
    /**
     * Track inventory condition and log issues to blockchain
     * @param array $item Inventory item data
     * @return array Status and hash if logged to blockchain
     */
    public function trackInventoryCondition($item) {
        try {
            $condition = $item['condition'] ?? 'Unknown';
            $status = $this->evaluateConditionStatus($condition);
            
            // Add prediction data
            $predictionData = $this->predictItemMaintenance($item);
            $status['prediction'] = $predictionData;
            
            // Log to blockchain if condition is concerning
            if ($status['level'] === 'warning' || $status['level'] === 'critical') {
                $data = [
                    'type' => 'condition_warning',
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'condition' => $condition,
                    'status' => $status['text'],
                    'prediction' => $predictionData,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                $hash = $this->logToBlockchain($data);
                $status['hash'] = $hash;
                
                // In a real implementation, you might trigger alerts or notifications here
            }
            
            return $status;
            
        } catch (Exception $e) {
            error_log("Error tracking inventory condition: " . $e->getMessage());
            return [
                'text' => 'Error',
                'level' => 'error',
                'class' => 'bg-secondary'
            ];
        }
    }
    
    /**
     * Predict item maintenance needs and expiration
     * @param array $item Item data
     * @return array Prediction data
     */
    private function predictItemMaintenance($item) {
        try {
            $condition = strtolower($item['condition'] ?? 'good');
            
            // Get days since item was acquired/updated
            $lastUpdated = !empty($item['last_updated']) ? strtotime($item['last_updated']) : time();
            $daysSinceUpdate = floor((time() - $lastUpdated) / (60 * 60 * 24));
            
            // Get date added if available
            $purchaseDate = !empty($item['purchase_date']) ? strtotime($item['purchase_date']) : $lastUpdated;
            $ageInDays = floor((time() - $purchaseDate) / (60 * 60 * 24));
            
            // Predict days until maintenance needed based on condition
            $daysUntilMaintenance = 0;
            $maintenanceUrgency = 'none';
            $expiryStatus = 'none';
            
            switch ($condition) {
                case 'new':
                    // New items typically need maintenance after ~180 days
                    $daysUntilMaintenance = 180 - $ageInDays;
                    break;
                case 'good':
                    // Good items might need maintenance after ~90 more days
                    $daysUntilMaintenance = 90 - $daysSinceUpdate;
                    break;
                case 'fair':
                    // Fair items need maintenance within ~30 days
                    $daysUntilMaintenance = 30 - $daysSinceUpdate;
                    break;
                case 'poor':
                    // Poor items need immediate maintenance
                    $daysUntilMaintenance = 0;
                    break;
                default:
                    $daysUntilMaintenance = 90; // Default value
            }
            
            // Determine maintenance urgency
            if ($daysUntilMaintenance <= 0) {
                $maintenanceUrgency = 'immediate';
            } elseif ($daysUntilMaintenance <= 30) {
                $maintenanceUrgency = 'soon';
            } elseif ($daysUntilMaintenance <= 90) {
                $maintenanceUrgency = 'upcoming';
            } else {
                $maintenanceUrgency = 'none';
            }
            
            // Predict expiration based on item type/category if available
            // This is just a simple example - in a real system you would have 
            // different expiration profiles for different types of items
            $itemCategory = strtolower($item['category'] ?? 'equipment');
            $expiryDays = 0;
            
            switch ($itemCategory) {
                case 'consumable':
                    $expiryDays = 365; // 1 year
                    break;
                case 'equipment':
                    $expiryDays = 1825; // 5 years
                    break;
                case 'furniture':
                    $expiryDays = 3650; // 10 years
                    break;
                case 'electronics':
                    $expiryDays = 1095; // 3 years
                    break;
                default:
                    $expiryDays = 1825; // 5 years default
            }
            
            $daysUntilExpiry = $expiryDays - $ageInDays;
            
            // Determine expiry status
            if ($daysUntilExpiry <= 0) {
                $expiryStatus = 'expired';
            } elseif ($daysUntilExpiry <= 90) {
                $expiryStatus = 'critical';
            } elseif ($daysUntilExpiry <= 180) {
                $expiryStatus = 'warning';
            } else {
                $expiryStatus = 'normal';
            }
            
            // Return prediction data
            return [
                'maintenance' => [
                    'days_until_needed' => max(0, $daysUntilMaintenance),
                    'urgency' => $maintenanceUrgency,
                    'date' => date('Y-m-d', strtotime("+{$daysUntilMaintenance} days")),
                    'message' => $this->getMaintenanceMessage($maintenanceUrgency, $daysUntilMaintenance)
                ],
                'expiry' => [
                    'days_until_expiry' => max(0, $daysUntilExpiry),
                    'status' => $expiryStatus,
                    'date' => date('Y-m-d', strtotime("+{$daysUntilExpiry} days")),
                    'message' => $this->getExpiryMessage($expiryStatus, $daysUntilExpiry)
                ],
                'age' => [
                    'days' => $ageInDays,
                    'date_added' => date('Y-m-d', $purchaseDate)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Error predicting item maintenance: " . $e->getMessage());
            return [
                'error' => 'Failed to predict maintenance needs',
                'maintenance' => ['urgency' => 'unknown'],
                'expiry' => ['status' => 'unknown']
            ];
        }
    }
    
    /**
     * Get a human-readable message about maintenance urgency
     * @param string $urgency Maintenance urgency level
     * @param int $days Days until maintenance
     * @return string Message about maintenance
     */
    private function getMaintenanceMessage($urgency, $days) {
        switch ($urgency) {
            case 'immediate':
                return 'Maintenance required immediately';
            case 'soon':
                return "Maintenance required within {$days} days";
            case 'upcoming':
                return "Maintenance scheduled in {$days} days";
            default:
                return "No maintenance needed at this time";
        }
    }
    
    /**
     * Get a human-readable message about expiry status
     * @param string $status Expiry status
     * @param int $days Days until expiry
     * @return string Message about expiry
     */
    private function getExpiryMessage($status, $days) {
        switch ($status) {
            case 'expired':
                return 'Item has exceeded its expected lifespan';
            case 'critical':
                return "Item will reach end-of-life in {$days} days";
            case 'warning':
                return "Item approaching end-of-life in {$days} days";
            default:
                return "Item within normal lifespan";
        }
    }
    
    /**
     * Calculate real-time prediction for PO/PAR
     * @param array $data Current form data
     * @param string $type Either 'po' or 'par'
     * @return array Prediction results
     */
    public function calculatePrediction($data, $type = 'po') {
        try {
            if ($type === 'po') {
                $poAmount = floatval($data['amount'] ?? 0);
                
                // In a real implementation, this would use ML model from ml_prediction.php
                // For demo, we'll use a simple calculation
                $predictedPAR = $poAmount * (0.6 + (rand(0, 30) / 100)); // 60-90% of PO amount
                $ratio = $poAmount > 0 ? $predictedPAR / $poAmount : 0;
                
                $healthScore = $this->calculateHealthScore($ratio);
                
                return [
                    'po_amount' => $poAmount,
                    'predicted_par' => $predictedPAR,
                    'ratio' => $ratio,
                    'health_score' => $healthScore
                ];
                
            } else { // PAR
                $parAmount = floatval($data['amount'] ?? 0);
                
                // For demo, we'll use a simple calculation
                $relatedPO = $parAmount * (1.1 + (rand(0, 40) / 100)); // 110-150% of PAR amount
                $utilization = $relatedPO > 0 ? ($parAmount / $relatedPO) * 100 : 0;
                
                return [
                    'par_amount' => $parAmount,
                    'related_po' => $relatedPO,
                    'utilization' => $utilization,
                    'health_score' => $this->calculateParHealthScore($utilization)
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error calculating prediction: " . $e->getMessage());
            return [
                'error' => 'Failed to calculate prediction',
                'health_score' => 50
            ];
        }
    }
    
    /**
     * Log data to the blockchain
     * @param array $data Data to log
     * @return string Generated hash
     */
    private function logToBlockchain($data) {
        // In a real implementation, this would interact with your blockchain provider
        // For demo purposes, we'll just generate a hash and store locally
        
        $hash = $this->generateBlockchainHash(json_encode($data));
        
        // Check if blockchain_transactions table exists before trying to insert
        if ($this->conn) {
            try {
                // Check if the table exists
                $tableCheck = $this->conn->query("SHOW TABLES LIKE 'blockchain_transactions'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    // Table exists, try to insert
                    $query = "INSERT INTO blockchain_transactions (hash, data_type, data_json, timestamp) 
                             VALUES (?, ?, ?, NOW())";
                    
                    $stmt = $this->conn->prepare($query);
                    $dataType = $data['type'] ?? 'unknown';
                    $dataJson = json_encode($data);
                    $stmt->bind_param('sss', $hash, $dataType, $dataJson);
                    $stmt->execute();
                } else {
                    // Table doesn't exist, log the issue
                    error_log("blockchain_transactions table does not exist. Hash generated but not stored.");
                }
            } catch (Exception $e) {
                error_log("Error storing blockchain transaction: " . $e->getMessage());
                // Continue even if DB storage fails - in a real blockchain this wouldn't matter
            }
        }
        
        return $hash;
    }
    
    /**
     * Get registered IoT sensors
     * @return array List of sensors
     */
    private function getRegisteredSensors() {
        // In a real implementation, this would query your database
        // For demo, we'll return simulated data
        
        return [
            ['id' => 'SEN-001', 'location' => 'Warehouse A', 'type' => 'Temperature'],
            ['id' => 'SEN-002', 'location' => 'Office B', 'type' => 'Humidity'],
            ['id' => 'SEN-003', 'location' => 'Storage C', 'type' => 'Motion'],
            ['id' => 'SEN-004', 'location' => 'Server Room', 'type' => 'Temperature']
        ];
    }
    
    /**
     * Generate a sensor reading based on type
     * @param string $type Sensor type
     * @return string Formatted reading
     */
    private function generateSensorReading($type) {
        switch ($type) {
            case 'Temperature':
                return (15 + rand(0, 150) / 10) . '°C';
            case 'Humidity':
                return rand(30, 80) . '%';
            case 'Motion':
                return rand(0, 1) ? 'Detected (' . rand(1, 10) . 'm ago)' : 'None';
            default:
                return 'Unknown';
        }
    }
    
    /**
     * Get recent blockchain transactions
     * @return array List of transactions
     */
    private function getRecentTransactions() {
        // In a real implementation, this would query your blockchain
        // For demo, we'll generate random transactions
        
        $transactions = [];
        $types = ['sensor_reading', 'condition_warning', 'po_creation', 'par_creation'];
        
        for ($i = 0; $i < rand(100, 150); $i++) {
            $transactions[] = [
                'hash' => $this->generateBlockchainHash('tx' . $i),
                'type' => $types[array_rand($types)],
                'timestamp' => date('Y-m-d H:i:s', time() - rand(0, 604800)) // Within last week
            ];
        }
        
        return $transactions;
    }
    
    /**
     * Generate a blockchain hash
     * @param string $data Input data
     * @return string Formatted hash
     */
    private function generateBlockchainHash($data) {
        return hash('sha256', $data . microtime());
    }
    
    /**
     * Evaluate condition status of an inventory item
     * @param string $condition Condition value (Poor, Fair, Good, New)
     * @return array Status information
     */
    private function evaluateConditionStatus($condition) {
        switch (strtolower($condition)) {
            case 'poor':
                return [
                    'text' => 'Critical - Needs Replacement',
                    'level' => 'critical',
                    'class' => 'bg-danger'
                ];
            case 'fair':
                return [
                    'text' => 'Warning - Maintenance Required',
                    'level' => 'warning',
                    'class' => 'bg-warning'
                ];
            case 'good':
                return [
                    'text' => 'Good Condition',
                    'level' => 'good',
                    'class' => 'bg-success'
                ];
            case 'new':
                return [
                    'text' => 'Excellent Condition',
                    'level' => 'excellent',
                    'class' => 'bg-info'
                ];
            default:
                return [
                    'text' => 'Unknown Condition',
                    'level' => 'unknown',
                    'class' => 'bg-secondary'
                ];
        }
    }
    
    /**
     * Calculate health score based on PO/PAR ratio
     * @param float $ratio PAR to PO ratio
     * @return int Health score (0-100)
     */
    private function calculateHealthScore($ratio) {
        if ($ratio > 1) {
            // PAR amount exceeds PO amount - critical
            return max(0, 100 - (($ratio - 1) * 100));
        } else if ($ratio < 0.6) {
            // PAR utilization below 60% - might be wasteful
            return max(0, 60 + ($ratio * 40));
        } else {
            // Ideal range: PAR is 60-100% of PO
            return 95;
        }
    }
    
    /**
     * Calculate PAR health score based on utilization percentage
     * @param float $utilization PAR utilization percentage
     * @return int Health score (0-100)
     */
    private function calculateParHealthScore($utilization) {
        if ($utilization > 100) {
            // PAR exceeds expectations - could be over-utilized
            return max(0, 100 - (($utilization - 100) * 0.5));
        } else if ($utilization < 50) {
            // PAR under-utilized - wasteful
            return max(0, $utilization);
        } else {
            // Ideal range: 50-100% utilization
            return 75 + ($utilization * 0.25);
        }
    }
    
    /**
     * Get inventory items for IoT tracking
     * @return array Inventory items formatted for IoT display
     */
    private function getInventoryItemsForIoT() {
        $items = [];
        
        try {
            if ($this->conn) {
                $query = "SELECT * FROM inventory_items ORDER BY date_added DESC LIMIT 10";
                $result = $this->conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $hash = $this->generateBlockchainHash($row['item_id'] . $row['item_name'] . time());
                        
                        $items[] = [
                            'id' => $row['item_id'],
                            'location' => 'Inventory',
                            'type' => 'Item',
                            'reading' => $row['item_name'] . ' (Qty: ' . $row['quantity'] . ')',
                            'status' => 'Normal',
                            'hash' => $hash
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting inventory items: " . $e->getMessage());
        }
        
        return $items;
    }
    
    /**
     * Get PO items for IoT tracking
     * @return array PO items formatted for IoT display
     */
    private function getPOItemsForIoT() {
        $items = [];
        
        try {
            if ($this->conn) {
                $query = "SELECT pi.*, po.po_number 
                          FROM po_items pi 
                          JOIN purchase_orders po ON pi.po_id = po.po_id 
                          ORDER BY po.date_created DESC LIMIT 10";
                $result = $this->conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $hash = $this->generateBlockchainHash($row['item_id'] . $row['po_number'] . time());
                        
                        $items[] = [
                            'id' => 'PO-' . $row['item_id'],
                            'location' => 'PO #' . $row['po_number'],
                            'type' => 'Purchase',
                            'reading' => $row['description'] . ' (Qty: ' . $row['quantity'] . ')',
                            'status' => 'Normal',
                            'hash' => $hash
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting PO items: " . $e->getMessage());
        }
        
        return $items;
    }
    
    /**
     * Get PAR items for IoT tracking
     * @return array PAR items formatted for IoT display
     */
    private function getPARItemsForIoT() {
        $items = [];
        
        try {
            if ($this->conn) {
                $query = "SELECT pi.*, p.par_number 
                          FROM par_items pi 
                          JOIN property_acknowledgement_receipts p ON pi.par_id = p.par_id 
                          ORDER BY p.date_created DESC LIMIT 10";
                $result = $this->conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $hash = $this->generateBlockchainHash($row['item_id'] . $row['par_number'] . time());
                        
                        $items[] = [
                            'id' => 'PAR-' . $row['item_id'],
                            'location' => 'PAR #' . $row['par_number'],
                            'type' => 'Receipt',
                            'reading' => $row['description'] . ' (Qty: ' . $row['quantity'] . ')',
                            'status' => 'Normal',
                            'hash' => $hash
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting PAR items: " . $e->getMessage());
        }
        
        return $items;
    }
}

// Process API requests
if (isset($_GET['action'])) {
    $integration = new IoTBlockchainIntegration();
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_sensors_data':
            echo json_encode($integration->getSensorsData());
            break;
            
        case 'get_blockchain_status':
            echo json_encode($integration->getBlockchainStatus());
            break;
            
        case 'calculate_prediction':
            // Get POST data
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data) {
                echo json_encode(['error' => 'Invalid request data']);
                break;
            }
            
            $type = $data['type'] ?? 'po';
            echo json_encode($integration->calculatePrediction($data, $type));
            break;
            
        case 'track_condition':
            // Get POST data
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            
            if (!$data || !isset($data['item_id']) || !isset($data['condition'])) {
                echo json_encode(['error' => 'Invalid condition data']);
                break;
            }
            
            echo json_encode($integration->trackInventoryCondition($data));
            break;
            
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    
    exit;
}
?> 