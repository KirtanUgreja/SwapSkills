// Admin Panel JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    initializeAdminFeatures();
});

function initializeAdminFeatures() {
    // Initialize bulk selection for skills management
    initializeBulkSelection();
    
    // Initialize admin security features
    initializeAdminSecurity();
    
    // Initialize admin notifications
    initializeAdminNotifications();
    
    // Initialize search and filters
    initializeAdminSearch();
    
    // Initialize chart animations
    initializeChartAnimations();
}

// Bulk Selection for Skills Management
function initializeBulkSelection() {
    const selectAllBtn = document.getElementById('selectAll');
    const deleteSelectedBtn = document.getElementById('deleteSelected');
    const bulkForm = document.getElementById('bulkForm');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.skill-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
            
            this.innerHTML = allChecked ? 
                '<i class="fas fa-check-square"></i> Select All' : 
                '<i class="fas fa-square"></i> Deselect All';
                
            updateBulkActionsVisibility();
        });
    }

    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function() {
            const selected = document.querySelectorAll('.skill-checkbox:checked');
            
            if (selected.length === 0) {
                showAdminAlert('Please select skills to delete.', 'warning');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${selected.length} selected skills?`)) {
                bulkForm.submit();
            }
        });
    }
    
    // Update bulk actions visibility based on selection
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('skill-checkbox')) {
            updateBulkActionsVisibility();
        }
    });
}

function updateBulkActionsVisibility() {
    const selected = document.querySelectorAll('.skill-checkbox:checked');
    const deleteBtn = document.getElementById('deleteSelected');
    
    if (deleteBtn) {
        deleteBtn.style.display = selected.length > 0 ? 'inline-block' : 'none';
        deleteBtn.textContent = `Delete Selected (${selected.length})`;
    }
}

// Admin Security Features
function initializeAdminSecurity() {
    // Disable right-click context menu on admin pages
    if (document.body.classList.contains('admin-page')) {
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
        
        // Disable F12, Ctrl+Shift+I, Ctrl+U
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.key === 'u')) {
                e.preventDefault();
            }
        });
    }
    
    // Auto-logout warning
    let warningShown = false;
    let logoutTimer;
    
    function resetLogoutTimer() {
        clearTimeout(logoutTimer);
        warningShown = false;
        
        // Warning after 25 minutes
        setTimeout(() => {
            if (!warningShown) {
                warningShown = true;
                showAdminAlert('Your session will expire in 5 minutes due to inactivity.', 'warning');
            }
        }, 25 * 60 * 1000);
        
        // Auto logout after 30 minutes
        logoutTimer = setTimeout(() => {
            showAdminAlert('Session expired due to inactivity. Redirecting to login...', 'error');
            setTimeout(() => {
                window.location.href = 'admin-login.php';
            }, 2000);
        }, 30 * 60 * 1000);
    }
    
    // Reset timer on user activity
    ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, resetLogoutTimer, { passive: true });
    });
    
    resetLogoutTimer();
}

// Admin Notifications
function initializeAdminNotifications() {
    // Auto-hide success/error messages after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
}

function showAdminAlert(message, type = 'info') {
    const alertContainer = document.querySelector('.admin-container');
    if (!alertContainer) return;
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="fas ${getAlertIcon(type)}"></i>
        ${message}
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    alertContainer.insertBefore(alert, alertContainer.firstChild);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (alert.parentElement) {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    }, 5000);
}

function getAlertIcon(type) {
    switch (type) {
        case 'success': return 'fa-check-circle';
        case 'error': return 'fa-exclamation-circle';
        case 'warning': return 'fa-exclamation-triangle';
        default: return 'fa-info-circle';
    }
}

// Admin Search and Filters
function initializeAdminSearch() {
    // Live search functionality
    const searchInputs = document.querySelectorAll('input[name="search"]');
    searchInputs.forEach(input => {
        let searchTimeout;
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500); // Debounce search
        });
    });
    
    // Enhanced table sorting
    initializeTableSorting();
}

function initializeTableSorting() {
    const tables = document.querySelectorAll('.admin-table');
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            if (header.textContent.trim() && !header.querySelector('input')) {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => sortTable(table, index));
            }
        });
    });
}

function sortTable(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const isAscending = table.dataset.sortDirection !== 'asc';
    
    rows.sort((a, b) => {
        const aValue = a.cells[column].textContent.trim();
        const bValue = b.cells[column].textContent.trim();
        
        // Try to parse as numbers
        const aNum = parseFloat(aValue);
        const bNum = parseFloat(bValue);
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? aNum - bNum : bNum - aNum;
        }
        
        // String comparison
        return isAscending ? 
            aValue.localeCompare(bValue) : 
            bValue.localeCompare(aValue);
    });
    
    // Update DOM
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort direction
    table.dataset.sortDirection = isAscending ? 'asc' : 'desc';
    
    // Update header indicators
    const headers = table.querySelectorAll('th');
    headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
    headers[column].classList.add(isAscending ? 'sort-asc' : 'sort-desc');
}

// Chart Animations
function initializeChartAnimations() {
    const charts = document.querySelectorAll('.simple-chart');
    charts.forEach(chart => {
        const bars = chart.querySelectorAll('.bar');
        bars.forEach((bar, index) => {
            const height = bar.style.height;
            bar.style.height = '0%';
            bar.style.transition = 'height 0.8s ease-in-out';
            
            setTimeout(() => {
                bar.style.height = height;
            }, index * 100);
        });
    });
}

// Export functionality
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        const rowData = Array.from(cells).map(cell => {
            return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Confirmation dialogs with enhanced styling
function confirmAdminAction(message, callback) {
    const modal = document.createElement('div');
    modal.className = 'admin-modal-overlay';
    modal.innerHTML = `
        <div class="admin-modal">
            <div class="admin-modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Confirm Action</h3>
            </div>
            <div class="admin-modal-body">
                <p>${message}</p>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAdminModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmAdminModal()">Confirm</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    window.confirmAdminModal = function() {
        callback();
        closeAdminModal();
    };
    
    window.closeAdminModal = function() {
        document.body.removeChild(modal);
        delete window.confirmAdminModal;
        delete window.closeAdminModal;
    };
}

// Real-time status updates
function updateUserStatus(userId, status) {
    fetch('admin-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_status&user_id=${userId}&status=${status}&csrf_token=${getCSRFToken()}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAdminAlert(data.message, 'success');
            // Update UI
            location.reload();
        } else {
            showAdminAlert(data.message, 'error');
        }
    })
    .catch(error => {
        showAdminAlert('Network error occurred', 'error');
    });
}

function getCSRFToken() {
    const token = document.querySelector('input[name="csrf_token"]');
    return token ? token.value : '';
}

// Keyboard shortcuts for admin panel
document.addEventListener('keydown', function(e) {
    // Ctrl+Shift+U for users page
    if (e.ctrlKey && e.shiftKey && e.key === 'U') {
        e.preventDefault();
        window.location.href = 'admin-users.php';
    }
    
    // Ctrl+Shift+S for skills page
    if (e.ctrlKey && e.shiftKey && e.key === 'S') {
        e.preventDefault();
        window.location.href = 'admin-skills.php';
    }
    
    // Ctrl+Shift+R for reports page
    if (e.ctrlKey && e.shiftKey && e.key === 'R') {
        e.preventDefault();
        window.location.href = 'admin-reports.php';
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const modal = document.querySelector('.admin-modal-overlay');
        if (modal && window.closeAdminModal) {
            window.closeAdminModal();
        }
    }
});

// Initialize tooltips for admin interface
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'admin-tooltip';
    tooltip.textContent = e.target.dataset.tooltip;
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + 'px';
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
}

function hideTooltip() {
    const tooltip = document.querySelector('.admin-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Initialize all features when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAdminFeatures);
} else {
    initializeAdminFeatures();
}