/**
 * PAR.js - Property Acknowledgement Receipt management functions
 * This file handles all PAR-related functionality including CRUD operations
 */

// Global variables
let parItems = [];
let parTotal = 0;

/**
 * Initialize PAR functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    // Load PAR data if on the PAR page
    if (document.getElementById('parTable') || document.querySelector('.par-table')) {
        loadPARData();
    }

    // Initialize PAR form events
    initPARFormEvents();

    // Run cleanup function for specific PAR items
    removeSpecificParItem();
    
    // Add listener for modals to rerun cleanup
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', removeSpecificParItem);
    });
    
    // Add global event delegation for dynamically created elements
    document.addEventListener('click', function(e) {
        // Handle dynamically created PAR buttons
        if (e.target.closest('.view-par')) {
            const parId = e.target.closest('.view-par').getAttribute('data-par-id');
            viewPAR(parId);
        } else if (e.target.closest('.edit-par')) {
            const parId = e.target.closest('.edit-par').getAttribute('data-par-id');
            editPAR(parId);
        } else if (e.target.closest('.delete-par')) {
            const parId = e.target.closest('.delete-par').getAttribute('data-par-id');
            deletePAR(parId);
        } else if (e.target.closest('.remove-par-row')) {
            handleRemoveParRow(e.target.closest('.remove-par-row'));
        }
    });
    
    // Add global input event delegation
    document.addEventListener('input', function(e) {
        // Handle dynamically created PAR amount inputs
        if (e.target.matches('.par-amount, .amount, [name="amount[]"]')) {
            const value = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = formatNumber(value);
            calculateParTotal();
        }
        // Handle dynamically created PAR quantity inputs
        else if (e.target.matches('.par-qty, .qty, [name="quantity[]"]')) {
            calculateParTotal();
        }
    });
});

/**
 * Load PAR data from the server
 */
function loadPARData() {
    showLoading();
    
    // Try to fetch PAR data
    fetch('get_par.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status} ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayPARData(data.data);
            } else {
                showError(data.message || 'Failed to load PAR data');
            }
        })
        .catch(error => {
            console.error('Error loading PAR data:', error);
            showError('Error loading PAR data: ' + error.message);
        })
        .finally(() => {
            hideLoading();
        });
}

/**
 * Display PAR data in the table
 */
function displayPARData(pars) {
    const parTable = document.getElementById('parTable') || document.querySelector('.par-table');
    if (!parTable) return;
    
    const tbody = parTable.querySelector('tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (pars.length === 0) {
        const emptyRow = document.createElement('tr');
        emptyRow.innerHTML = `<td colspan="6" class="text-center">No PAR records found</td>`;
        tbody.appendChild(emptyRow);
        return;
    }
    
    pars.forEach(par => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${par.par_no || ''}</td>
            <td>${par.date_acquired || ''}</td>
            <td>${par.property_number || ''}</td>
            <td>${par.received_by_name || ''}</td>
            <td>${formatNumber(par.total_amount || 0)}</td>
            <td>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info view-par" data-par-id="${par.par_id}">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary edit-par" data-par-id="${par.par_id}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger delete-par" data-par-id="${par.par_id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

/**
 * Add event listeners to PAR action buttons
 */
function addPARButtonEventListeners() {
    // This function is now supplementary to the global event delegation
    // The existing code can remain as a backup for static elements
    
    // View PAR
    document.querySelectorAll('.view-par').forEach(button => {
        button.addEventListener('click', function() {
            const parId = this.getAttribute('data-par-id');
            viewPAR(parId);
        });
    });
    
    // Edit PAR
    document.querySelectorAll('.edit-par').forEach(button => {
        button.addEventListener('click', function() {
            const parId = this.getAttribute('data-par-id');
            editPAR(parId);
        });
    });
    
    // Delete PAR
    document.querySelectorAll('.delete-par').forEach(button => {
        button.addEventListener('click', function() {
            const parId = this.getAttribute('data-par-id');
            deletePAR(parId);
        });
    });
}

/**
 * Initialize PAR form events
 */
function initPARFormEvents() {
    // Add PAR item row button
    const addParRowBtn = document.getElementById('addParRowBtn');
    if (addParRowBtn) {
        addParRowBtn.addEventListener('click', addParRow);
    }
    
    // Form submission
    const parForm = document.getElementById('parForm');
    if (parForm) {
        parForm.addEventListener('submit', function(e) {
            e.preventDefault();
            savePAR();
        });
    }

    // Add initial PAR row if table is empty
    const parItemsTable = document.getElementById('parItemsTable');
    if (parItemsTable) {
        const tbody = parItemsTable.querySelector('tbody');
        if (tbody && tbody.children.length === 0) {
            addInitialParRow();
        }
    }
}

/**
 * View PAR details
 */
function viewPAR(parId) {
    window.location.href = `ViewPAR.php?id=${parId}`;
}

/**
 * Print PAR
 */
function printPAR(parId) {
    window.open(`ViewPAR.php?id=${parId}&print=true`, '_blank');
}

/**
 * Edit PAR
 */
function editPAR(parId) {
    showLoading();
    
    fetch(`get_par.php?id=${parId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modalElement = document.getElementById('parModal');
                if (!modalElement) {
                    throw new Error('Modal element not found');
                }

                let parModal;
                try {
                    parModal = new bootstrap.Modal(modalElement);
                } catch (e) {
                    parModal = bootstrap.Modal.getInstance(modalElement);
                }

                const parForm = document.getElementById('parForm');
                if (!parForm) {
                    throw new Error('PAR form not found');
                }

                // Reset form
                parForm.reset();
                
                // Set form fields
                parForm.querySelector('[name="par_id"]').value = data.data.par_id;
                parForm.querySelector('[name="par_no"]').value = data.data.par_no;
                parForm.querySelector('[name="entity_name"]').value = data.data.entity_name;
                parForm.querySelector('[name="received_by"]').value = data.data.received_by_name;
                
                const positionField = parForm.querySelector('[name="position"]');
                if (positionField) positionField.value = data.data.position || '';
                
                const departmentField = parForm.querySelector('[name="department"]');
                if (departmentField) departmentField.value = data.data.department || '';
                
                parForm.querySelector('[name="date_acquired"]').value = data.data.date_acquired || '';
                
                const remarksField = parForm.querySelector('[name="remarks"]');
                if (remarksField) remarksField.value = data.data.remarks || '';
                
                // Clear and populate items
                const tbody = document.getElementById('parItemsTable')?.querySelector('tbody');
                if (tbody) {
                    tbody.innerHTML = '';
                    if (data.data.items?.length > 0) {
                        data.data.items.forEach(item => addParRowWithData(item));
                    } else {
                        addInitialParRow();
                    }
                }
                
                calculateParTotal();
                
                // Update modal title and show
                const titleElement = document.getElementById('parModalTitle');
                if (titleElement) titleElement.textContent = 'Edit Property Acknowledgement Receipt';
                
                if (parModal) parModal.show();
            } else {
                throw new Error(data.message || 'Failed to load PAR data');
            }
        })
        .catch(error => {
            console.error('Error loading PAR data:', error);
            showError(error.message);
        })
        .finally(hideLoading);
}

/**
 * Delete PAR
 */
function deletePAR(parId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('delete_par.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    par_id: parId
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Deleted!',
                        'PAR has been deleted.',
                        'success'
                    );
                    loadPARData();
                } else {
                    showError(data.message || 'Failed to delete PAR');
                }
            })
            .catch(error => {
                console.error('Error deleting PAR:', error);
                showError('Error deleting PAR: ' + error.message);
            })
            .finally(() => {
                hideLoading();
            });
        }
    });
}

/**
 * Add a new PAR item row
 */
function addParRow() {
    const tbody = document.getElementById('parItemsTable').querySelector('tbody');
    const newRow = document.createElement('tr');
    
    newRow.innerHTML = `
        <td><input type="number" class="form-control par-qty qty" name="quantity[]" value="1" min="1"></td>
        <td><input type="text" class="form-control" name="unit[]" placeholder="Unit"></td>
        <td><textarea class="form-control" name="description[]" placeholder="Description" required></textarea></td>
        <td><input type="text" class="form-control" name="property_number[]" placeholder="Property Number"></td>
        <td><input type="date" class="form-control par-item-date" name="date_acquired[]" value="${new Date().toISOString().split('T')[0]}"></td>
        <td><input type="text" class="form-control par-amount amount" name="amount[]" value="0" onkeyup="this.value=formatNumber(this.value.replace(/[^0-9]/g,''))"></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-par-row"><i class="bi bi-trash"></i></button></td>
    `;
    
    tbody.appendChild(newRow);
    addParRowEventListeners(newRow);
    calculateParTotal();
}

/**
 * Add a PAR item row with data
 */
function addParRowWithData(item) {
    const tbody = document.getElementById('parItemsTable').querySelector('tbody');
    const newRow = document.createElement('tr');
    
    newRow.innerHTML = `
        <td>
            <input type="number" class="form-control par-qty qty" name="quantity[]" value="${item.quantity || 1}" min="1">
        </td>
        <td>
            <input type="text" class="form-control" name="unit[]" value="${item.unit || ''}" placeholder="Unit">
        </td>
        <td>
            <textarea class="form-control" name="description[]" placeholder="Description" required>${item.description || ''}</textarea>
        </td>
        <td>
            <input type="text" class="form-control" name="property_number[]" value="${item.property_number || ''}" placeholder="Property Number">
        </td>
        <td>
            <input type="date" class="form-control par-item-date" name="date_acquired[]" value="${item.date_acquired || new Date().toISOString().split('T')[0]}">
        </td>
        <td>
            <input type="text" class="form-control par-amount amount" name="amount[]" value="${formatNumber(item.amount) || '0.00'}">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm remove-par-row">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(newRow);
    
    // Add event listeners to new row
    addParRowEventListeners(newRow);
}

/**
 * Add initial PAR row if table is empty
 */
function addInitialParRow() {
    const tbody = document.getElementById('parItemsTable').querySelector('tbody');
    if (tbody && tbody.children.length === 0) {
        addParRow();
    }
}

/**
 * Add event listeners to PAR row
 */
function addParRowEventListeners(row) {
    // This function is maintained for compatibility, but the main functionality
    // is now handled by event delegation in the document ready function
    
    // Remove row button (keeping this for backward compatibility)
    row.querySelector('.remove-par-row').addEventListener('click', function() {
        handleRemoveParRow(this);
    });
    
    // Amount input event (keeping this for backward compatibility)
    const amountInput = row.querySelector('.par-amount');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            // Format the input to currency
            const value = this.value.replace(/[^0-9]/g, '');
            this.value = formatNumber(value);
            
            // Recalculate total
            calculateParTotal();
        });
    }
    
    // Quantity input event (keeping this for backward compatibility)
    const qtyInput = row.querySelector('.par-qty');
    if (qtyInput) {
        qtyInput.addEventListener('input', function() {
            calculateParTotal();
        });
    }
}

/**
 * Handle removing a PAR row
 */
function handleRemoveParRow(button) {
    const row = button.closest('tr');
    if (document.querySelectorAll('#parItemsTable tbody tr').length > 1) {
        row.remove();
        calculateParTotal();
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Cannot Remove',
            text: 'At least one item is required',
            timer: 3000
        });
    }
}

/**
 * Calculate PAR total amount
 */
function calculateParTotal() {
    let total = 0;
    document.querySelectorAll('#parItemsTable tbody tr').forEach(row => {
        const qty = parseInt(row.querySelector('[name="quantity[]"]').value) || 0;
        const amount = parseInt(row.querySelector('[name="amount[]"]').value.replace(/[^0-9]/g, '')) || 0;
        total += qty * amount;
    });
    
    // Update total display
    const totalElement = document.getElementById('parTotal');
    if (totalElement) {
        totalElement.textContent = formatNumber(total);
    }
    
    // Also update hidden total input if it exists
    const totalInput = document.querySelector('[name="total_amount"]');
    if (totalInput) {
        totalInput.value = total;
    }
    
    return total;
}

/**
 * Save PAR data to server
 */
function savePAR() {
    // Validate form
    const parForm = document.getElementById('parForm');
    if (!parForm.checkValidity()) {
        parForm.reportValidity();
        return;
    }
    
    showLoading();
    
    // Collect form data
    const parId = parForm.querySelector('[name="par_id"]').value;
    const formData = {
        par_id: parId || null,
        par_no: parForm.querySelector('[name="par_no"]').value,
        entity_name: parForm.querySelector('[name="entity_name"]').value,
        received_by: parForm.querySelector('[name="received_by"]').value,
        position: parForm.querySelector('[name="position"]')?.value || '',
        department: parForm.querySelector('[name="department"]')?.value || '',
        date_acquired: parForm.querySelector('[name="date_acquired"]').value,
        remarks: parForm.querySelector('[name="remarks"]')?.value || '',
        items: []
    };
    
    // Collect items data
    document.querySelectorAll('#parItemsTable tbody tr').forEach(row => {
        const item = {
            quantity: parseInt(row.querySelector('[name="quantity[]"]').value) || 0,
            unit: row.querySelector('[name="unit[]"]').value,
            description: row.querySelector('[name="description[]"]').value,
            property_number: row.querySelector('[name="property_number[]"]').value,
            date_acquired: row.querySelector('[name="date_acquired[]"]').value,
            amount: parseInt(row.querySelector('[name="amount[]"]').value.replace(/[^0-9]/g, '')) || 0
        };
        
        // Skip empty items
        if (item.description.trim()) {
            formData.items.push(item);
        }
    });
    
    // Check if we have items
    if (formData.items.length === 0) {
        hideLoading();
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please add at least one item',
        });
        return;
    }
    
    // Determine endpoint - either update or create
    const endpoint = parId ? 'update_par.php' : 'get_par.php';
    const method = parId ? 'PUT' : 'POST';
    
    // Send data to server
    fetch(endpoint, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal properly
            const modalElement = document.getElementById('parModal');
            if (modalElement) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                    setTimeout(() => {
                        document.body.classList.remove('modal-open');
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    }, 150);
                }
            }

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: formData.par_id ? 'PAR updated successfully' : 'PAR created successfully',
                timer: 2000
            });

            loadPARData();
            
            // Reset form and items
            const parForm = document.getElementById('parForm');
            if (parForm) {
                parForm.reset();
                const tbody = document.getElementById('parItemsTable')?.querySelector('tbody');
                if (tbody) {
                    tbody.innerHTML = '';
                    addInitialParRow();
                }
            }
        } else {
            throw new Error(data.message || 'Failed to save PAR');
        }
    })
    .catch(error => {
        console.error('Error saving PAR:', error);
        showError(error.message);
    })
    .finally(hideLoading);
}

/**
 * Format number with commas
 */
function formatNumber(number) {
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

/**
 * Show loading indicator
 */
function showLoading() {
    // Check for existing loading overlay
    let loadingOverlay = document.getElementById('loadingOverlay');
    
    if (!loadingOverlay) {
        // Create loading overlay
        loadingOverlay = document.createElement('div');
        loadingOverlay.id = 'loadingOverlay';
        loadingOverlay.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        `;
        
        // Apply styles
        loadingOverlay.style.position = 'fixed';
        loadingOverlay.style.top = '0';
        loadingOverlay.style.left = '0';
        loadingOverlay.style.width = '100%';
        loadingOverlay.style.height = '100%';
        loadingOverlay.style.display = 'flex';
        loadingOverlay.style.alignItems = 'center';
        loadingOverlay.style.justifyContent = 'center';
        loadingOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        loadingOverlay.style.zIndex = '9999';
        
        // Add to body
        document.body.appendChild(loadingOverlay);
    } else {
        // Show existing overlay
        loadingOverlay.style.display = 'flex';
    }
}

/**
 * Hide loading indicator
 */
function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'none';
    }
}

/**
 * Show error message
 */
function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        timer: 5000
    });
}

/**
 * Remove specific PAR item row with QTY=1, AMOUNT=0, Date Acquired=10/04/25
 */
function removeSpecificParItem() {
    // Look for all rows in PAR items tables
    const parRows = document.querySelectorAll('#parItemsTable tbody tr, .par-table tbody tr, table tbody tr');
    
    parRows.forEach(row => {
        // Check if this is the row we want to remove
        const qtyElement = row.querySelector('.par-qty, .qty, [name="quantity[]"], td.quantity');
        const amountElement = row.querySelector('.par-amount, .amount, [name="amount[]"]');
        const dateElement = row.querySelector('.par-item-date, [name="date_acquired[]"], .date-cell');
        
        if (qtyElement && amountElement && dateElement) {
            // Get values from elements
            let qty = qtyElement.tagName === 'TD' ? qtyElement.textContent.trim() : qtyElement.value;
            let amount = amountElement.tagName === 'TD' ? amountElement.textContent.trim() : amountElement.value;
            let date = dateElement.tagName === 'TD' ? dateElement.textContent.trim() : dateElement.value;
            
            // Check for exact match with the values we want to remove
            if (qty == '1' && amount == '0' && (date == '10/04/25' || date == '2025-04-10')) {
                console.log('Removing specific PAR item row:', row);
                row.remove();
                
                // Recalculate PAR total if needed
                if (typeof calculateParTotal === 'function') {
                    calculateParTotal();
                }
            }
        }
    });
}
// Function to handle PAR form submission
document.getElementById('saveParBtn')?.addEventListener('click', function () {
    // Don't handle the form submission here - delegate to PAR.js
    console.log('PAR save button clicked in script.js - delegating to PAR.js savePAR()');
    
    // Check if savePAR function exists in PAR.js
    if (typeof savePAR === 'function') {
        savePAR();
    } else {
        console.error('savePAR function not found. Make sure PAR.js is loaded properly.');
        Swal.fire({
            title: 'Error!',
            text: 'Could not save PAR - savePAR function not found.',
            icon: 'error'
        });
    }
});
