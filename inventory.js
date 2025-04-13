// Inventory Management System

// Load inventory data from the server
function loadInventoryData(searchQuery = '') {
    console.log(`Loading inventory data with search query: "${searchQuery}"`);

    // Ensure we're using a consistent endpoint path
    const url = searchQuery
        ? `./get_inventory.php?search=${encodeURIComponent(searchQuery)}`
        : './get_inventory.php';

    showLoading();

    console.log('Loading inventory data from:', url);

    fetch(url)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Server error response:', text);
                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text.substring(0, 200)}...`);
                });
            }
            return response.json();
        })
        .then(response => {
            console.log('Data received:', response);

            // Handle different response formats
            let data = response;
            if (response && response.success === false) {
                throw new Error(response.message || 'Unknown error occurred');
            } else if (response && response.data) {
                data = response.data;
            }

            // Store data globally
            window.filteredData = data;

            // Update the table
            updateInventoryTable(data);

            // Run additional functions
            checkWarrantyStatus();
            updateConditionStatus();

            hideLoading();
        })
        .catch(error => {
            console.error('Failed to load inventory data:', error);
            showError('Failed to load inventory data. Please try again.');
            hideLoading();
            
            // Set empty table with error message
            const tableBody = document.getElementById('inventoryTableBody');
            if (tableBody) {
                tableBody.innerHTML = `<tr><td colspan="11" class="text-center text-danger">
                        Error loading data. Please try again.
                    </td></tr>`;
            }
        });
}

// Initialize the inventory system
function initInventorySystem() {
    console.log('Initializing inventory system...');

    // Load initial data
    loadInventoryData();

    // Add search functionality if search input exists
    const searchInput = document.getElementById('inventorySearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function () {
            loadInventoryData(this.value);
        }, 500));
    }

    // Add refresh button functionality if it exists
    const refreshButton = document.getElementById('refreshInventory');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            loadInventoryData();
        });
    }

    console.log('Inventory system initialized');
}

// Update the inventory table with new data
function updateInventoryTable(data) {
    console.log('Updating inventory table with data:', data);

    // Get the table body element
    const tableBody = document.getElementById('inventoryTableBody');
    if (!tableBody) {
        console.error('Inventory table body not found');
        return;
    }

    // Clear existing rows
    tableBody.innerHTML = '';

    // Check if data is valid
    if (!data || (!Array.isArray(data) && !data.data)) {
        console.error('Invalid inventory data format:', data);
        tableBody.innerHTML = `<tr><td colspan="11" class="text-center">No inventory data available</td></tr>`;
        return;
    }

    // Extract the data array
    const items = Array.isArray(data) ? data : (data.data || []);

    if (items.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="11" class="text-center">No inventory items found</td></tr>`;
        return;
    }

    // Add rows for each inventory item
    items.forEach(item => {
        const row = document.createElement('tr');

        // Format dates if available
        const purchaseDate = item.purchase_date ? new Date(item.purchase_date).toLocaleDateString() : 'N/A';
        const warrantyDate = item.warranty_expiration ? new Date(item.warranty_expiration).toLocaleDateString() : 'N/A';

        // Get condition badge class
        const conditionClass = getConditionBadgeClass(item.condition || 'Unknown');
        
        // Calculate warranty status
        let warrantyStatus = 'No Warranty';
        let warrantyBadgeClass = 'bg-secondary';
        
        if (item.warranty_expiration) {
            const today = new Date();
            const warranty = new Date(item.warranty_expiration);
            
            if (warranty > today) {
                const diffTime = Math.abs(warranty - today);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays <= 30) {
                    warrantyStatus = `Expires in ${diffDays} days`;
                    warrantyBadgeClass = 'bg-warning';
                } else {
                    warrantyStatus = 'Active';
                    warrantyBadgeClass = 'bg-success';
                }
            } else {
                warrantyStatus = 'Expired';
                warrantyBadgeClass = 'bg-danger';
            }
        }

        row.innerHTML = `
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-sm btn-primary edit-item" data-id="${item.item_id}">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-sm btn-danger delete-item" data-id="${item.item_id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
            <td>${item.item_id || ''}</td>
            <td>${item.item_name || ''}</td>
            <td>${item.brand_model || ''}</td>
            <td>${item.serial_number || ''}</td>
            <td>${purchaseDate}</td>
            <td class="warranty-column">
                <span class="badge warranty-status ${warrantyBadgeClass}">${warrantyStatus}</span>
            </td>
            <td>${item.assigned_to || ''}</td>
            <td>${item.location || ''}</td>
            <td><span class="badge ${conditionClass}">${item.condition || 'Unknown'}</span></td>
            <td>${item.notes || ''}</td>
        `;

        tableBody.appendChild(row);
    });

    // Add event listeners to the action buttons
    addActionButtonListeners();

    // Check warranty status after adding rows
    checkWarrantyStatus();
}

// Add event listeners to action buttons
function addActionButtonListeners() {
    document.querySelectorAll('.edit-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            editItem(itemId);
        });
    });

    document.querySelectorAll('.delete-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-id');
            deleteItem(itemId);
        });
    });
}

// Check warranty status for all items
function checkWarrantyStatus() {
    const warrantyColumns = document.querySelectorAll('.warranty-column');
    
    if (warrantyColumns.length === 0) return;
    
    warrantyColumns.forEach(column => {
        const badge = column.querySelector('.warranty-status');
        if (!badge) return;
        
        const status = badge.textContent.trim();
        
        if (status === 'Expired') {
            badge.classList.add('bg-danger');
            badge.classList.remove('bg-success', 'bg-warning', 'bg-secondary');
        } else if (status === 'Active') {
            badge.classList.add('bg-success');
            badge.classList.remove('bg-danger', 'bg-warning', 'bg-secondary');
        } else if (status.includes('Expires in')) {
            badge.classList.add('bg-warning');
            badge.classList.remove('bg-danger', 'bg-success', 'bg-secondary');
        } else {
            badge.classList.add('bg-secondary');
            badge.classList.remove('bg-danger', 'bg-success', 'bg-warning');
        }
    });
}

// Update condition status badges
function updateConditionStatus() {
    const conditionColumns = document.querySelectorAll('#inventoryTableBody tr td:nth-child(10)');
    
    if (conditionColumns.length === 0) return;
    
    conditionColumns.forEach(column => {
        const badge = column.querySelector('.badge');
        if (!badge) return;
        
        const condition = badge.textContent.trim();
        const badgeClass = getConditionBadgeClass(condition);
        
        // Remove all existing color classes
        badge.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'bg-secondary');
        
        // Add the appropriate class
        badge.classList.add(badgeClass);
    });
}

// Get the appropriate badge class for a condition
function getConditionBadgeClass(condition) {
    switch (condition.toLowerCase()) {
        case 'excellent':
            return 'bg-success';
        case 'good':
            return 'bg-info';
        case 'fair':
            return 'bg-warning';
        case 'poor':
            return 'bg-danger';
        case 'damaged':
            return 'bg-danger';
        case 'retired':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

// Apply filters to the inventory data
function applyFilters() {
    // Get filter values
    const conditionFilter = document.getElementById('conditionFilter')?.value;
    const locationFilter = document.getElementById('locationFilter')?.value;
    const warrantyFilter = document.getElementById('warrantyFilter')?.value;
    
    // Check if we have data to filter
    if (!window.filteredData) {
        console.error('No inventory data available for filtering');
        return;
    }
    
    // Start with the full dataset
    let filteredItems = window.filteredData;
    
    // Apply condition filter if selected
    if (conditionFilter && conditionFilter !== 'all') {
        filteredItems = filteredItems.filter(item => 
            (item.condition && item.condition.toLowerCase() === conditionFilter.toLowerCase())
        );
    }
    
    // Apply location filter if selected
    if (locationFilter && locationFilter !== 'all') {
        filteredItems = filteredItems.filter(item => 
            (item.location && item.location.toLowerCase() === locationFilter.toLowerCase())
        );
    }
    
    // Apply warranty filter if selected
    if (warrantyFilter && warrantyFilter !== 'all') {
        const today = new Date();
        
        if (warrantyFilter === 'active') {
            filteredItems = filteredItems.filter(item => {
                if (!item.warranty_expiration) return false;
                return new Date(item.warranty_expiration) > today;
            });
        } else if (warrantyFilter === 'expired') {
            filteredItems = filteredItems.filter(item => {
                if (!item.warranty_expiration) return true; // No warranty = expired
                return new Date(item.warranty_expiration) <= today;
            });
        }
    }
    
    // Update the table with filtered data
    updateInventoryTable(filteredItems);
}

// Save a new inventory item
function saveInventoryItem() {
    // Get form data
    const form = document.getElementById('addInventoryForm');
    if (!form) {
        showError('Inventory form not found');
        return;
    }
    
    // Get all form fields
    const formData = new FormData(form);
    const inventoryData = {};
    
    // Convert to JSON object
    for (const [key, value] of formData.entries()) {
        inventoryData[key] = value;
    }
    
    // Validate required fields
    if (!inventoryData.item_name || !inventoryData.serial_number) {
        showError('Please fill in all required fields');
        return;
    }
    
    // Show loading state
    showLoading();
    
    // Send to server
    fetch('./save_inventory.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(inventoryData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Close modal if exists
            const modal = bootstrap.Modal.getInstance(document.getElementById('addInventoryModal'));
            if (modal) modal.hide();
            
            // Clear form
            form.reset();
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Inventory item saved successfully',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Refresh data
            loadInventoryData();
        } else {
            throw new Error(data.message || 'Unknown error occurred');
        }
    })
    .catch(error => {
        console.error('Error saving inventory item:', error);
        showError('Failed to save inventory item: ' + error.message);
    })
    .finally(() => {
        hideLoading();
    });
}

// Delete an inventory item
function deleteItem(itemId) {
    if (!itemId) {
        showError('Item ID is required');
        return;
    }
    
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('./delete_inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ item_id: itemId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Deleted!',
                        'The inventory item has been deleted.',
                        'success'
                    );
                    loadInventoryData();
                } else {
                    throw new Error(data.message || 'Failed to delete item');
                }
            })
            .catch(error => {
                console.error('Error deleting item:', error);
                showError('Failed to delete item: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }
    });
}

// Edit an inventory item
function editItem(itemId) {
    if (!itemId) {
        showError('Item ID is required');
        return;
    }
    
    showLoading();
    
    fetch(`./get_inventory_item.php?id=${itemId}`)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (!data || !data.item_id) {
            throw new Error('Invalid item data received');
        }
        
        // Populate form with item data
        const form = document.getElementById('addInventoryForm');
        if (!form) {
            throw new Error('Inventory form not found');
        }
        
        // Add the item ID to the form
        const itemIdField = document.createElement('input');
        itemIdField.type = 'hidden';
        itemIdField.name = 'item_id';
        itemIdField.value = data.item_id;
        form.appendChild(itemIdField);
        
        // Fill all available fields
        for (const key in data) {
            const field = form.elements[key];
            if (field) {
                field.value = data[key];
            }
        }
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('addInventoryModal'));
        modal.show();
        
        // Update modal title
        const modalTitle = document.querySelector('#addInventoryModal .modal-title');
        if (modalTitle) {
            modalTitle.textContent = 'Edit Inventory Item';
        }
        
        // Update save button text
        const saveButton = document.querySelector('#addInventoryForm .btn-primary');
        if (saveButton) {
            saveButton.textContent = 'Update Item';
        }
    })
    .catch(error => {
        console.error('Error loading item data:', error);
        showError('Failed to load item data: ' + error.message);
    })
    .finally(() => {
        hideLoading();
    });
}

// View location history of an item
function viewLocationHistory(itemId) {
    if (!itemId) {
        showError('Item ID is required');
        return;
    }
    
    showLoading();
    
    fetch(`./get_location_history.php?id=${itemId}`)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        const historyModal = document.getElementById('locationHistoryModal');
        const historyBody = historyModal.querySelector('.modal-body');
        
        if (!historyBody) {
            throw new Error('Modal body not found');
        }
        
        if (!data || !Array.isArray(data) || data.length === 0) {
            historyBody.innerHTML = '<p class="text-center">No location history available for this item.</p>';
        } else {
            const tableHtml = `
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Previous Location</th>
                            <th>New Location</th>
                            <th>Updated By</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.map(record => `
                            <tr>
                                <td>${new Date(record.timestamp).toLocaleString()}</td>
                                <td>${record.previous_location || 'N/A'}</td>
                                <td>${record.new_location}</td>
                                <td>${record.updated_by || 'System'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            
            historyBody.innerHTML = tableHtml;
        }
        
        // Show the modal
        const modal = new bootstrap.Modal(historyModal);
        modal.show();
    })
    .catch(error => {
        console.error('Error loading location history:', error);
        showError('Failed to load location history: ' + error.message);
    })
    .finally(() => {
        hideLoading();
    });
}

// Initialize inventory system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the inventory page
    if (document.getElementById('inventoryTableBody')) {
        initInventorySystem();
        
        // Add event listeners for filters
        const conditionFilter = document.getElementById('conditionFilter');
        const locationFilter = document.getElementById('locationFilter');
        const warrantyFilter = document.getElementById('warrantyFilter');
        
        if (conditionFilter) conditionFilter.addEventListener('change', applyFilters);
        if (locationFilter) locationFilter.addEventListener('change', applyFilters);
        if (warrantyFilter) warrantyFilter.addEventListener('change', applyFilters);
    }
});

// Make functions available globally
window.loadInventoryData = loadInventoryData;
window.initInventorySystem = initInventorySystem;
window.updateInventoryTable = updateInventoryTable;
window.checkWarrantyStatus = checkWarrantyStatus;
window.updateConditionStatus = updateConditionStatus;
window.getConditionBadgeClass = getConditionBadgeClass;
window.applyFilters = applyFilters;
window.saveInventoryItem = saveInventoryItem;
window.deleteItem = deleteItem;
window.editItem = editItem;
window.viewLocationHistory = viewLocationHistory; 