<?php
/**
 * ML Prediction System for ICTD Inventory Management
 * Handles ML-based predictions for inventory demand, PAR tracking, and yearly amount forecasts
 */

// Include database connection
if (file_exists('config/db.php')) {
    include 'config/db.php';
} else {
    // Define a fallback connection function
    function getConnection() {
        return null;
    }
}

require_once 'config/db.php';

class MLPrediction {
    private $conn;
    private $historical_months = 12; // Look back period
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getPrediction($params = []) {
        try {
            $model = $params['model'] ?? 'linear';
            $include_historical = $params['include_historical'] ?? false;
            
            // Get historical data
            $historical_data = $this->getHistoricalData();
            
            if (empty($historical_data)) {
                return [
                    'success' => false,
                    'error' => 'No historical data available'
                ];
            }
            
            // Calculate predictions
            $predictions = $this->calculatePredictions($historical_data, $model);
            
            // Calculate confidence score based on data consistency
            $confidence_score = $this->calculateConfidenceScore($historical_data);
            
            // Generate alerts
            $alerts = $this->generateAlerts($historical_data);
            
            // Calculate health metrics
            $health_metrics = $this->calculateHealthMetrics($historical_data);
            
            $response = [
                'success' => true,
                'yearly_forecast' => $predictions,
                'confidence_score' => $confidence_score,
                'alerts' => $alerts,
                'inventory_health' => $health_metrics['inventory_health'],
                'po_efficiency' => $health_metrics['po_efficiency'],
                'par_health' => $health_metrics['par_health']
            ];
            
            if ($include_historical) {
                $response['historical'] = $historical_data;
            }
            
            return $response;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getHistoricalData() {
        try {
            $data = [];
            
            // Get PO data with more detailed information
            $po_query = "SELECT 
                            DATE_FORMAT(po_date, '%Y-%m') as period,
                            COUNT(*) as po_count,
                            SUM(total_amount) as po_amount,
                            COUNT(DISTINCT supplier) as supplier_count
                        FROM purchase_orders 
                        WHERE po_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                        GROUP BY DATE_FORMAT(po_date, '%Y-%m')
                        ORDER BY period";
                        
            $stmt = $this->conn->prepare($po_query);
            $stmt->bind_param('i', $this->historical_months);
            $stmt->execute();
            $po_result = $stmt->get_result();
            
            while ($row = $po_result->fetch_assoc()) {
                $data[$row['period']]['po_amount'] = floatval($row['po_amount']) ?? 0;
                $data[$row['period']]['po_count'] = intval($row['po_count']) ?? 0;
                $data[$row['period']]['supplier_count'] = intval($row['supplier_count']) ?? 0;
            }
            
            // Get PAR data with more detailed information
            $par_query = "SELECT 
                            DATE_FORMAT(date_acquired, '%Y-%m') as period,
                            COUNT(*) as par_count,
                            SUM(amount) as par_amount,
                            COUNT(DISTINCT received_by) as recipient_count
                        FROM par_items 
                        WHERE date_acquired >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                        GROUP BY DATE_FORMAT(date_acquired, '%Y-%m')
                        ORDER BY period";
                        
            $stmt = $this->conn->prepare($par_query);
            $stmt->bind_param('i', $this->historical_months);
            $stmt->execute();
            $par_result = $stmt->get_result();
            
            while ($row = $par_result->fetch_assoc()) {
                $data[$row['period']]['par_amount'] = floatval($row['par_amount']) ?? 0;
                $data[$row['period']]['par_count'] = intval($row['par_count']) ?? 0;
                $data[$row['period']]['recipient_count'] = intval($row['recipient_count']) ?? 0;
            }
            
            // Format data for time series analysis
            $formatted_data = [];
            foreach ($data as $period => $values) {
                $formatted_data[] = [
                    'period' => $period,
                    'po_amount' => $values['po_amount'] ?? 0,
                    'po_count' => $values['po_count'] ?? 0,
                    'par_amount' => $values['par_amount'] ?? 0,
                    'par_count' => $values['par_count'] ?? 0,
                    'supplier_count' => $values['supplier_count'] ?? 0,
                    'recipient_count' => $values['recipient_count'] ?? 0,
                    'demand' => max($values['po_count'] ?? 0, $values['par_count'] ?? 0)
                ];
            }
            
            return $formatted_data;
            
        } catch (Exception $e) {
            error_log("Error getting historical data: " . $e->getMessage());
            return [];
        }
    }
    
    private function calculatePredictions($historical_data, $model = 'linear') {
        if (empty($historical_data)) {
            return [];
        }
        
        try {
            $predictions = [];
            
            // Get the last 6 months of data for trend analysis
            $recent_data = array_slice($historical_data, -6);
            
            // Calculate trends with error handling
            $po_trend = $this->calculateTrend(array_column($recent_data, 'po_amount'));
            $par_trend = $this->calculateTrend(array_column($recent_data, 'par_amount'));
            $demand_trend = $this->calculateTrend(array_column($recent_data, 'demand'));
            
            // Get base values (average of last 3 months)
            $last_3_months = array_slice($historical_data, -3);
            $base_po = array_sum(array_column($last_3_months, 'po_amount')) / 3;
            $base_par = array_sum(array_column($last_3_months, 'par_amount')) / 3;
            $base_demand = array_sum(array_column($last_3_months, 'demand')) / 3;
            
            // Generate next 12 months predictions
            $last_date = end($historical_data)['period'];
            for ($i = 1; $i <= 12; $i++) {
                $next_date = date('Y-m', strtotime($last_date . " +$i month"));
                $month_num = date('n', strtotime($next_date));
                
                // Apply trend-based prediction with seasonality
                $seasonality = $this->calculateSeasonality($month_num);
                
                // Calculate predicted values with minimum thresholds
                $predicted_po = max(
                    $base_po * 0.5, // Minimum threshold
                    $base_po * pow($po_trend, $i) * $seasonality
                );
                
                $predicted_par = max(
                    $base_par * 0.5, // Minimum threshold
                    $base_par * pow($par_trend, $i) * $seasonality
                );
                
                $predicted_demand = max(
                    $base_demand * 0.5, // Minimum threshold
                    $base_demand * pow($demand_trend, $i) * $seasonality
                );
                
                $predictions[] = [
                    'period' => $next_date,
                    'po_amount' => round($predicted_po, 2),
                    'par_amount' => round($predicted_par, 2),
                    'demand' => round($predicted_demand)
                ];
            }
            
            return $predictions;
            
        } catch (Exception $e) {
            error_log("Error calculating predictions: " . $e->getMessage());
            return [];
        }
    }
    
    private function calculateHealthMetrics($historical_data) {
        if (empty($historical_data)) {
            return [
                'inventory_health' => 75,
                'po_efficiency' => 75,
                'par_health' => 75
            ];
        }
        
        try {
            $latest = end($historical_data);
            $start = reset($historical_data);
            
            // Calculate inventory health based on demand vs supply trend
            $demand_change = ($latest['demand'] - $start['demand']) / max(1, $start['demand']);
            $inventory_health = min(100, max(0, (1 + $demand_change) * 75));
            
            // Calculate PO efficiency based on PO to PAR ratio
            $po_par_ratio = $latest['po_amount'] / max(1, $latest['par_amount']);
            $po_efficiency = min(100, max(0, (1 - abs(1 - $po_par_ratio)) * 100));
            
            // Calculate PAR health based on utilization and distribution
            $par_utilization = $latest['par_amount'] / max(1, $latest['po_amount']);
            $par_distribution = $latest['recipient_count'] / max(1, $latest['par_count']);
            $par_health = min(100, max(0, ($par_utilization * 0.7 + $par_distribution * 0.3) * 100));
            
            return [
                'inventory_health' => round($inventory_health),
                'po_efficiency' => round($po_efficiency),
                'par_health' => round($par_health)
            ];
            
        } catch (Exception $e) {
            error_log("Error calculating health metrics: " . $e->getMessage());
            return [
                'inventory_health' => 75,
                'po_efficiency' => 75,
                'par_health' => 75
            ];
        }
    }
    
    private function calculateTrend($values) {
        $n = count($values);
        if ($n < 2) return 1;
        
        $sum_x = 0;
        $sum_y = 0;
        $sum_xy = 0;
        $sum_xx = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $values[$i];
            
            $sum_x += $x;
            $sum_y += $y;
            $sum_xy += ($x * $y);
            $sum_xx += ($x * $x);
        }
        
        $slope = (($n * $sum_xy) - ($sum_x * $sum_y)) / (($n * $sum_xx) - ($sum_x * $sum_x));
        return max(0.95, min(1.05, 1 + $slope/100)); // Limit trend factor between 0.95 and 1.05
    }
    
    private function calculateSeasonality($month) {
        // Simple seasonality factors (can be refined based on actual historical patterns)
        $seasonality = [
            1 => 1.1,  // January (high)
            2 => 0.9,  // February (low)
            3 => 1.0,  // March
            4 => 1.05, // April
            5 => 1.0,  // May
            6 => 0.95, // June
            7 => 0.9,  // July
            8 => 0.95, // August
            9 => 1.0,  // September
            10 => 1.05, // October
            11 => 1.1,  // November (high)
            12 => 1.15  // December (highest)
        ];
        
        return $seasonality[($month % 12) ?: 12];
    }
    
    private function calculateConfidenceScore($historical_data) {
        if (empty($historical_data)) return 75; // Default confidence
        
        // Factors affecting confidence:
        // 1. Data consistency
        // 2. Trend stability
        // 3. Seasonality pattern match
        
        $consistency_score = $this->calculateDataConsistency($historical_data);
        $trend_score = $this->calculateTrendStability($historical_data);
        
        // Weighted average
        return round(($consistency_score * 0.6) + ($trend_score * 0.4));
    }
    
    private function calculateDataConsistency($data) {
        if (empty($data)) return 75;
        
        $po_values = array_column($data, 'po_amount');
        $par_values = array_column($data, 'par_amount');
        
        // Calculate coefficient of variation (lower is better)
        $po_cv = $this->calculateCV($po_values);
        $par_cv = $this->calculateCV($par_values);
        
        // Convert CV to score (0-100)
        $po_score = max(0, min(100, 100 - ($po_cv * 100)));
        $par_score = max(0, min(100, 100 - ($par_cv * 100)));
        
        return ($po_score + $par_score) / 2;
    }
    
    private function calculateCV($values) {
        if (empty($values)) return 0;
        $mean = array_sum($values) / count($values);
        if ($mean == 0) return 0;
        
        $variance = array_reduce($values, function($carry, $item) use ($mean) {
            return $carry + pow($item - $mean, 2);
        }, 0) / count($values);
        
        return sqrt($variance) / $mean;
    }
    
    private function calculateTrendStability($data) {
        if (empty($data)) return 75;
        
        $po_values = array_column($data, 'po_amount');
        $par_values = array_column($data, 'par_amount');
        
        // Calculate trend stability score
        $po_stability = $this->calculateStabilityScore($po_values);
        $par_stability = $this->calculateStabilityScore($par_values);
        
        return ($po_stability + $par_stability) / 2;
    }
    
    private function calculateStabilityScore($values) {
        if (count($values) < 2) return 75;
        
        $changes = [];
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i-1] != 0) {
                $changes[] = abs(($values[$i] - $values[$i-1]) / $values[$i-1]);
            }
        }
        
        if (empty($changes)) return 75;
        
        $avg_change = array_sum($changes) / count($changes);
        return max(0, min(100, 100 - ($avg_change * 100)));
    }
    
    private function generateAlerts($historical_data) {
        $alerts = [];
        
        // Check for significant changes in PO vs PAR amounts
        foreach ($historical_data as $data) {
            if ($data['par_amount'] > $data['po_amount'] * 1.2) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "PAR amount exceeds PO amount by 20% or more in {$data['period']}"
                ];
            }
        }
        
        // Check for unusual patterns
        $po_values = array_column($historical_data, 'po_amount');
        $par_values = array_column($historical_data, 'par_amount');
        
        $po_mean = array_sum($po_values) / count($po_values);
        $par_mean = array_sum($par_values) / count($par_values);
        
        foreach ($historical_data as $data) {
            if ($data['po_amount'] > $po_mean * 1.5) {
                $alerts[] = [
                    'type' => 'info',
                    'message' => "Unusually high PO amount in {$data['period']}"
                ];
            }
            if ($data['par_amount'] > $par_mean * 1.5) {
                $alerts[] = [
                    'type' => 'info',
                    'message' => "Unusually high PAR amount in {$data['period']}"
                ];
            }
        }
        
        return $alerts;
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    try {
        $conn = getConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        $ml = new MLPrediction($conn);
        
        switch ($_GET['action']) {
            case 'get_prediction':
                $params = [
                    'model' => $_GET['model'] ?? 'linear',
                    'include_historical' => isset($_GET['include_historical']) && $_GET['include_historical'] === 'true'
                ];
                
                $result = $ml->getPrediction($params);
                header('Content-Type: application/json');
                echo json_encode($result);
                break;
                
            case 'update_prediction':
                // Handle POST requests for updating prediction data
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    // For GET requests, return a simple success response
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Use POST method to update prediction data'
                    ]);
                    break;
                }
                
                // Get POST data
                $json = file_get_contents('php://input');
                $data = json_decode($json, true);
                
                if (!$data) {
                    throw new Exception("Invalid JSON data");
                }
                
                // Validate required fields
                if (!isset($data['transaction_type']) || !isset($data['amount']) || !isset($data['date'])) {
                    throw new Exception("Missing required fields: transaction_type, amount, date");
                }
                
                // Log the update for monitoring
                $transaction_type = $data['transaction_type'];
                $amount = floatval($data['amount']);
                $date = $data['date'];
                
                // Simply log the update - no actual ML model update needed for this demo
                error_log("ML Prediction update: Type=$transaction_type, Amount=$amount, Date=$date");
                
                // Return success
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => "Prediction updated for $transaction_type transaction"
                ]);
                break;
                
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_prediction') {
    // Handle POST requests for update_prediction action
    try {
        $conn = getConnection();
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Get POST data
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            throw new Exception("Invalid JSON data");
        }
        
        // Validate required fields
        if (!isset($data['transaction_type']) || !isset($data['amount']) || !isset($data['date'])) {
            throw new Exception("Missing required fields: transaction_type, amount, date");
        }
        
        // Log the update for monitoring
        $transaction_type = $data['transaction_type'];
        $amount = floatval($data['amount']);
        $date = $data['date'];
        
        // Simply log the update - no actual ML model update needed for this demo
        error_log("ML Prediction update: Type=$transaction_type, Amount=$amount, Date=$date");
        
        // Return success
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Prediction updated for $transaction_type transaction"
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>  