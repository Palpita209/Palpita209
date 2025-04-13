/**
 * IoT and Blockchain Integration for ICTD Inventory Management System
 * This handles the client-side functionality for IoT sensors and blockchain integration
 */

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize IoT Integration
    initializeIoT();
    
    // Initialize Blockchain functionality
    initializeBlockchain();
    
    // Initialize PO and PAR real-time predictions
    initializePredictions();
    
    // Initialize Inventory Condition Tracking
    initializeConditionTracking();
});

/**
 * Initialize IoT functionality
 */
function initializeIoT() {
    // Update IoT data on page load
    updateIoTData();
    
    // Refresh IoT data periodically
    setInterval(updateIoTData, 60000); // Every minute
    
    // Set up refresh button event listener
    const refreshButton = document.getElementById('refreshIoTData');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            updateIoTData(true); // Force refresh
        });
    }
}

/**
 * Update IoT sensor data display
 * @param {boolean} showLoading Whether to show loading indicators
 */
function updateIoTData(showLoading = false) {
    if (showLoading) {
        // Show loading indicators
        document.getElementById('activeSensors').innerHTML = '<div class="placeholder-glow"><span class="placeholder col-8"></span></div>';
        document.getElementById('dataPoints').innerHTML = '<div class="placeholder-glow"><span class="placeholder col-8"></span></div>';
        document.getElementById('lastUpdate').innerHTML = '<div class="placeholder-glow"><span class="placeholder col-8"></span></div>';
    }
    
    // Fetch IoT data from server
    fetch('iot_blockchain_integration.php?action=get_sensors_data')
        .then(response => response.json())
        .catch(error => {
            console.error('Error fetching IoT data:', error);
            return {
                active_sensors: 0,
                data_points: 0,
                last_update: 0,
                health: 0,
                sensors: []
            };
        })
        .then(data => {
            // Update the UI with IoT data
            updateIoTDisplay(data);
        });
}

/**
 * Update IoT display elements with data
 * @param {object} data IoT sensor data
 */
function updateIoTDisplay(data) {
    // Update summary statistics
    document.getElementById('activeSensors').textContent = data.active_sensors;
    document.getElementById('dataPoints').textContent = formatNumber(data.data_points);
    document.getElementById('lastUpdate').textContent = data.last_update + ' mins ago';
    
    // Update health indicator
    const healthBar = document.getElementById('iotHealthStatus');
    if (healthBar) {
        healthBar.style.width = data.health + '%';
        healthBar.className = `progress-bar ${data.health < 50 ? 'bg-danger' : data.health < 75 ? 'bg-warning' : 'bg-success'}`;
    }
    
    // Update sensor table
    updateSensorTable(data.sensors);
}

/**
 * Update the sensor data table
 * @param {array} sensors Array of sensor data objects
 */
function updateSensorTable(sensors) {
    const tableBody = document.getElementById('iotSensorTable');
    if (!tableBody || !sensors || sensors.length === 0) return;
    
    let html = '';
    
    sensors.forEach(sensor => {
        html += `
        <tr>
            <td>${sensor.id}</td>
            <td>${sensor.location}</td>
            <td>${sensor.type}</td>
            <td>${sensor.reading}</td>
            <td><span class="badge ${sensor.status === 'Normal' ? 'bg-success' : 'bg-warning'}">${sensor.status}</span></td>
            <td><code class="small">${sensor.hash}</code></td>
            <td>
                <button class="btn btn-sm btn-outline-primary view-sensor" data-sensor-id="${sensor.id}">View</button>
            </td>
        </tr>
        `;
    });
    
    tableBody.innerHTML = html;
    
    // Add event listeners for view buttons
    document.querySelectorAll('.view-sensor').forEach(button => {
        button.addEventListener('click', function() {
            const sensorId = this.getAttribute('data-sensor-id');
            viewSensorDetails(sensorId);
        });
    });
}

/**
 * Show sensor details
 * @param {string} sensorId The sensor ID
 */
function viewSensorDetails(sensorId) {
    // In a real application, this would fetch detailed sensor data
    // For demo, we'll just show an alert
    alert(`Sensor details for ${sensorId} would be displayed here in a real application.`);
}

/**
 * Initialize blockchain functionality
 */
function initializeBlockchain() {
    // Update blockchain data on page load
    updateBlockchainData();
    
    // Set up blockchain details button
    const detailsButton = document.getElementById('viewBlockchainDetails');
    if (detailsButton) {
        detailsButton.addEventListener('click', viewBlockchainDetails);
    }
}

/**
 * Update blockchain data display
 */
function updateBlockchainData() {
    fetch('iot_blockchain_integration.php?action=get_blockchain_status')
        .then(response => response.json())
        .catch(error => {
            console.error('Error fetching blockchain status:', error);
            return {
                total_transactions: 0,
                chain_health: 0,
                last_block: '#0',
                recent_transactions: []
            };
        })
        .then(data => {
            // Update blockchain display elements
            document.getElementById('totalTransactions').textContent = data.total_transactions;
            document.getElementById('chainHealth').textContent = data.chain_health + '%';
            document.getElementById('lastBlock').textContent = data.last_block;
            
            // Update recent transactions if there's a container for them
            const transactionsContainer = document.getElementById('recentTransactions');
            if (transactionsContainer && data.recent_transactions) {
                updateRecentTransactions(data.recent_transactions);
            }
        });
}

/**
 * Update recent blockchain transactions display
 * @param {array} transactions Recent transactions
 */
function updateRecentTransactions(transactions) {
    const container = document.getElementById('recentTransactions');
    if (!container || !transactions || transactions.length === 0) return;
    
    let html = '';
    transactions.slice(0, 5).forEach(tx => {
        html += `
        <div class="transaction-item d-flex justify-content-between align-items-center border-bottom py-2">
            <div>
                <span class="badge bg-info me-2">${tx.type}</span>
                <code class="small">${tx.hash}</code>
            </div>
            <small class="text-muted">${formatTimeAgo(new Date(tx.timestamp))}</small>
        </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * Show blockchain explorer details
 */
function viewBlockchainDetails() {
    // In a real application, this would open a blockchain explorer
    // For demo, we'll just show an alert
    alert('Blockchain explorer would open here, showing transaction details, blocks, and verification data.');
}

/**
 * Initialize predictive functionality for PO and PAR
 */
function initializePredictions() {
    // Set up PO form for real-time predictions
    setupPOPredictions();
    
    // Set up PAR form for real-time predictions
    setupPARPredictions();
}

/**
 * Set up PO form for real-time predictions
 */
function setupPOPredictions() {
    const poForm = document.getElementById('poForm');
    if (!poForm) return;
    
    // Listen for changes on quantity and unit cost inputs
    poForm.addEventListener('input', function(event) {
        if (event.target.classList.contains('qty') || event.target.classList.contains('unit-cost')) {
            updatePoItemAmount(event.target);
            calculatePoTotal();
            updatePoPrediction();
        }
    });
    
    // Set up initial calculations
    calculatePoTotal();
    updatePoPrediction();
}

/**
 * Update individual PO item amount based on quantity and unit cost
 * @param {HTMLElement} target The changed input element
 */
function updatePoItemAmount(target) {
    const row = target.closest('tr');
    const qtyInput = row.querySelector('.qty');
    const unitCostInput = row.querySelector('.unit-cost');
    const amountInput = row.querySelector('.amount');
    
    if (qtyInput && unitCostInput && amountInput) {
        const qty = parseFloat(qtyInput.value) || 0;
        const unitCost = parseFloat(unitCostInput.value) || 0;
        const amount = qty * unitCost;
        
        amountInput.value = amount.toFixed(2);
    }
}

/**
 * Calculate PO total amount
 * @return {number} The total amount
 */
function calculatePoTotal() {
    let total = 0;
    document.querySelectorAll('#poItemsTable .amount').forEach(function(element) {
        total += parseFloat(element.value) || 0;
    });
    
    // Update displayed total
    document.getElementById('totalAmount').value = '₱' + total.toFixed(2);
    
    // Update real-time prediction display
    if (document.getElementById('currentPOAmount')) {
        document.getElementById('currentPOAmount').textContent = '₱' + formatNumber(total.toFixed(2));
    }
    
    return total;
}

/**
 * Update PO prediction based on the current total
 */
function updatePoPrediction() {
    const poAmount = calculatePoTotal();
    
    // Prepare data for the prediction API
    const data = {
        amount: poAmount
    };
    
    // Call the prediction API
    fetch('iot_blockchain_integration.php?action=calculate_prediction', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `data=${JSON.stringify(data)}&type=po`
    })
    .then(response => response.json())
    .catch(error => {
        console.error('Error calculating prediction:', error);
        return {
            po_amount: poAmount,
            predicted_par: poAmount * 0.75,
            ratio: 0.75,
            health_score: 85
        };
    })
    .then(result => {
        // Update prediction display elements
        if (document.getElementById('predictedPARAmount')) {
            document.getElementById('predictedPARAmount').textContent = '₱' + formatNumber(result.predicted_par.toFixed(2));
        }
        
        if (document.getElementById('poPARRatioValue')) {
            document.getElementById('poPARRatioValue').textContent = result.ratio.toFixed(2);
        }
        
        // Update health indicator
        updatePoHealthIndicator(result.health_score);
    });
}

/**
 * Update PO health indicator based on health score
 * @param {number} healthScore Health score (0-100)
 */
function updatePoHealthIndicator(healthScore) {
    const healthBar = document.getElementById('poParHealthBar');
    const healthText = document.getElementById('poParHealthText');
    
    if (!healthBar || !healthText) return;
    
    healthBar.style.width = healthScore + '%';
    
    if (healthScore < 50) {
        healthBar.className = 'progress-bar bg-danger';
        healthText.textContent = 'Critical';
        healthText.className = 'small text-danger';
    } else if (healthScore < 75) {
        healthBar.className = 'progress-bar bg-warning';
        healthText.textContent = 'Warning';
        healthText.className = 'small text-warning';
    } else {
        healthBar.className = 'progress-bar bg-success';
        healthText.textContent = 'Excellent';
        healthText.className = 'small text-success';
    }
}

/**
 * Set up PAR form for real-time predictions
 */
function setupPARPredictions() {
    const parForm = document.getElementById('parForm');
    if (!parForm) return;
    
    // Listen for changes on PAR amount and quantity inputs
    parForm.addEventListener('input', function(event) {
        if (event.target.classList.contains('par-amount') || event.target.classList.contains('par-qty')) {
            calculateParTotal();
            updateParPrediction();
        }
    });
    
    // Set up initial calculations
    calculateParTotal();
    updateParPrediction();
}

/**
 * Calculate PAR total amount
 * @return {number} The total amount
 */
function calculateParTotal() {
    let total = 0;
    document.querySelectorAll('#parItemsTable .par-amount').forEach(function(element) {
        const row = element.closest('tr');
        const qtyInput = row.querySelector('.par-qty');
        const qty = parseFloat(qtyInput.value) || 1;
        const amount = parseFloat(element.value) || 0;
        
        total += amount * qty;
    });
    
    // Update displayed total
    document.getElementById('parTotalAmount').value = '₱' + total.toFixed(2);
    
    // Update real-time prediction display
    if (document.getElementById('currentPARAmount')) {
        document.getElementById('currentPARAmount').textContent = '₱' + formatNumber(total.toFixed(2));
    }
    
    return total;
}

/**
 * Update PAR prediction based on the current total
 */
function updateParPrediction() {
    const parAmount = calculateParTotal();
    
    // Prepare data for the prediction API
    const data = {
        amount: parAmount
    };
    
    // Call the prediction API
    fetch('iot_blockchain_integration.php?action=calculate_prediction', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `data=${JSON.stringify(data)}&type=par`
    })
    .then(response => response.json())
    .catch(error => {
        console.error('Error calculating PAR prediction:', error);
        return {
            par_amount: parAmount,
            related_po: parAmount * 1.25,
            utilization: 80,
            health_score: 75
        };
    })
    .then(result => {
        // Update prediction display elements
        if (document.getElementById('relatedPOAmount')) {
            document.getElementById('relatedPOAmount').textContent = '₱' + formatNumber(result.related_po.toFixed(2));
        }
        
        if (document.getElementById('parPOUtilization')) {
            document.getElementById('parPOUtilization').textContent = Math.round(result.utilization) + '%';
        }
        
        // Update health indicator
        updateParHealthIndicator(result.health_score);
    });
}

/**
 * Update PAR health indicator based on health score
 * @param {number} healthScore Health score (0-100)
 */
function updateParHealthIndicator(healthScore) {
    const healthBar = document.getElementById('parHealthBar');
    const healthText = document.getElementById('parHealthText');
    
    if (!healthBar || !healthText) return;
    
    healthBar.style.width = healthScore + '%';
    
    if (healthScore < 50) {
        healthBar.className = 'progress-bar bg-danger';
        healthText.textContent = 'Over-utilized';
        healthText.className = 'small text-danger';
    } else if (healthScore < 75) {
        healthBar.className = 'progress-bar bg-warning';
        healthText.textContent = 'Moderate';
        healthText.className = 'small text-warning';
    } else {
        healthBar.className = 'progress-bar bg-success';
        healthText.textContent = 'Optimal';
        healthText.className = 'small text-success';
    }
}

/**
 * Initialize inventory condition tracking
 */
function initializeConditionTracking() {
    // Watch for changes to the inventory table body (when items are loaded)
    setupInventoryObserver();
}

/**
 * Set up mutation observer for inventory table changes
 */
function setupInventoryObserver() {
    const inventoryTable = document.getElementById('inventoryTableBody');
    if (!inventoryTable) return;
    
    // Create a MutationObserver to watch for table updates
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                // New inventory items have been added to the table
                checkInventoryConditions();
            }
        });
    });
    
    // Start observing the target node for configured mutations
    observer.observe(inventoryTable, { childList: true });
    
    // Also check on page load after a short delay
    setTimeout(checkInventoryConditions, 1000);
}

/**
 * Check and update inventory conditions
 */
function checkInventoryConditions() {
    // Get all inventory rows
    const inventoryRows = document.querySelectorAll('#inventoryTableBody tr');
    
    inventoryRows.forEach(row => {
        // Get condition and status cells
        const conditionCell = row.querySelector('td:nth-child(10)'); // Condition column
        const statusCell = row.querySelector('td:nth-child(11)'); // Status column
        
        if (conditionCell && statusCell) {
            const condition = conditionCell.textContent.trim();
            const itemId = row.querySelector('td:nth-child(2)').textContent.trim(); // Item ID column
            const itemName = row.querySelector('td:nth-child(3)').textContent.trim(); // Item Name column
            
            // Prepare item data for condition tracking
            const itemData = {
                item_id: itemId,
                item_name: itemName,
                condition: condition
            };
            
            // Call the condition tracking API
            fetch('iot_blockchain_integration.php?action=track_condition', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `item_data=${JSON.stringify(itemData)}`
            })
            .then(response => response.json())
            .catch(error => {
                console.error('Error tracking condition:', error);
                return {
                    text: 'Error',
                    level: 'error',
                    class: 'bg-secondary'
                };
            })
            .then(result => {
                // Update the status cell with the result
                statusCell.innerHTML = `<span class="badge ${result.class}">${result.text}</span>`;
                
                // If status is critical, highlight the row
                if (result.level === 'critical') {
                    row.classList.add('table-danger');
                } else if (result.level === 'warning') {
                    row.classList.add('table-warning');
                } else {
                    row.classList.remove('table-danger', 'table-warning');
                }
            });
        }
    });
}

/**
 * Format a number with thousands separators
 * @param {number|string} num The number to format
 * @return {string} Formatted number
 */
function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

/**
 * Format a date as a relative time string (e.g., "2 hours ago")
 * @param {Date} date The date to format
 * @return {string} Relative time string
 */
function formatTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) {
        return interval + " year" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) {
        return interval + " month" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) {
        return interval + " day" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) {
        return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
    }
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) {
        return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
    }
    
    return Math.floor(seconds) + " second" + (seconds === 1 ? "" : "s") + " ago";
}   