/**
 * ML-based predictions for IoT and Blockchain integration
 * This file handles the prediction models and calculations for the inventory system
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize any prediction models that need setup
    initializePredictionModels();
});

/**
 * Initialize ML prediction models
 */
function initializePredictionModels() {
    // In a real implementation, this would load and prepare ML models
    console.log('ML prediction models initialized');
}

/**
 * Predict PAR amount based on PO
 * @param {number} poAmount - Purchase Order amount
 * @returns {object} Prediction results including PAR amount and confidence
 */
function predictPARFromPO(poAmount) {
    // Simple linear model with some randomness to simulate ML prediction
    // In a real implementation, this would use trained ML models
    
    // Base ratio is between 60-90% of PO with some randomness
    const baseRatio = 0.6 + (Math.random() * 0.3);
    
    // Add some "intelligence" by having larger POs get smaller PAR ratios
    // (economy of scale concept)
    let adjustedRatio = baseRatio;
    if (poAmount > 50000) {
        adjustedRatio = baseRatio * 0.95; // 5% reduction for large POs
    } else if (poAmount > 100000) {
        adjustedRatio = baseRatio * 0.9; // 10% reduction for very large POs
    }
    
    const predictedPAR = poAmount * adjustedRatio;
    
    // Calculate prediction confidence (higher for moderate amounts, lower for very small or large amounts)
    const confidence = calculatePredictionConfidence(poAmount);
    
    // Health score based on the ratio
    const healthScore = calculateHealthScore(adjustedRatio);
    
    return {
        originalAmount: poAmount,
        predictedAmount: predictedPAR,
        ratio: adjustedRatio,
        confidence: confidence,
        healthScore: healthScore
    };
}

/**
 * Predict PO relation based on PAR
 * @param {number} parAmount - Property Acknowledgement Receipt amount
 * @returns {object} Prediction results including related PO amount and utilization
 */
function predictPOFromPAR(parAmount) {
    // Simple linear model with some randomness to simulate ML prediction
    // In a real implementation, this would use trained ML models
    
    // Base ratio is between 110-150% of PAR with some randomness
    const baseRatio = 1.1 + (Math.random() * 0.4);
    
    // Add some "intelligence" by having larger PARs get smaller PO ratios
    // (economy of scale concept)
    let adjustedRatio = baseRatio;
    if (parAmount > 50000) {
        adjustedRatio = baseRatio * 0.95; // 5% reduction for large PARs
    } else if (parAmount > 100000) {
        adjustedRatio = baseRatio * 0.9; // 10% reduction for very large PARs
    }
    
    const relatedPO = parAmount * adjustedRatio;
    
    // Calculate utilization percentage
    const utilization = parAmount > 0 ? (parAmount / relatedPO) * 100 : 0;
    
    // Calculate prediction confidence
    const confidence = calculatePredictionConfidence(parAmount);
    
    // Health score based on the utilization
    const healthScore = calculateParHealthScore(utilization);
    
    return {
        originalAmount: parAmount,
        relatedAmount: relatedPO,
        ratio: adjustedRatio,
        utilization: utilization,
        confidence: confidence,
        healthScore: healthScore
    };
}

/**
 * Calculate prediction confidence based on amount
 * @param {number} amount - Amount to base confidence on
 * @returns {number} Confidence percentage (0-100)
 */
function calculatePredictionConfidence(amount) {
    // Very small or very large amounts have lower confidence
    if (amount < 1000) {
        return 50 + (amount / 1000 * 20); // 50-70% confidence for small amounts
    } else if (amount > 100000) {
        return 90 - ((amount - 100000) / 900000 * 30); // 60-90% confidence for large amounts
    } else {
        // Sweet spot is between 10k-50k with highest confidence
        const sweetSpot = amount >= 10000 && amount <= 50000;
        return sweetSpot ? 95 : 85;
    }
}

/**
 * Calculate health score for PO/PAR ratio
 * @param {number} ratio - PAR to PO ratio
 * @returns {number} Health score (0-100)
 */
function calculateHealthScore(ratio) {
    if (ratio > 0.9) {
        return 45; // PAR almost equal to PO - concerning
    } else if (ratio > 0.8) {
        return 65; // PAR higher than ideal - warning
    } else if (ratio > 0.7) {
        return 85; // PAR close to ideal - good
    } else {
        return 95; // PAR much less than PO - excellent
    }
}

/**
 * Calculate PAR health score based on utilization percentage
 * @param {number} utilization - PAR utilization percentage
 * @returns {number} Health score (0-100)
 */
function calculateParHealthScore(utilization) {
    if (utilization > 100) {
        // PAR exceeds expectations - could be over-utilized
        return Math.max(0, 100 - ((utilization - 100) * 0.5));
    } else if (utilization < 50) {
        // PAR under-utilized - wasteful
        return Math.max(0, utilization);
    } else {
        // Ideal range: 50-100% utilization
        return 75 + (utilization * 0.25);
    }
}

/**
 * Predict future inventory needs based on current trends
 * @param {Array} historicalData - Array of historical inventory data
 * @param {number} periods - Number of periods to predict
 * @returns {Array} Predicted values for future periods
 */
function predictFutureNeeds(historicalData, periods = 3) {
    if (!historicalData || historicalData.length < 3) {
        console.error('Insufficient historical data for prediction');
        return [];
    }
    
    // Extract values for linear regression
    const x = [];
    const y = [];
    
    historicalData.forEach((item, index) => {
        x.push(index);
        y.push(item.value);
    });
    
    // Simple linear regression
    const n = x.length;
    let sumX = 0;
    let sumY = 0;
    let sumXY = 0;
    let sumXX = 0;
    
    for (let i = 0; i < n; i++) {
        sumX += x[i];
        sumY += y[i];
        sumXY += x[i] * y[i];
        sumXX += x[i] * x[i];
    }
    
    const slope = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);
    const intercept = (sumY - slope * sumX) / n;
    
    // Generate predictions
    const predictions = [];
    for (let i = 1; i <= periods; i++) {
        const predictedX = x.length + i - 1;
        const predictedY = slope * predictedX + intercept;
        
        // Add some randomness to simulate real-world variance
        const variance = predictedY * 0.1; // 10% variance
        const randomFactor = (Math.random() * variance * 2) - variance;
        
        predictions.push({
            period: `Period ${x.length + i}`,
            value: Math.max(0, predictedY + randomFactor),
            isProjection: true
        });
    }
    
    return predictions;
} 