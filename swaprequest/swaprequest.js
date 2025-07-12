// Skill Swap Platform JavaScript

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize event listeners
    initializeEventListeners();
    
    // Initialize search functionality
    initializeSearch();
    
    // Initialize filter functionality
    initializeFilter();
    
    // Initialize action buttons
    initializeActionButtons();
    
    // Initialize pagination
    initializePagination();
});

function initializeEventListeners() {
    console.log('Skill Swap Platform initialized');
}

function initializeSearch() {
    const searchInput = document.querySelector('.search-input');
    const searchButton = document.querySelector('.search-button');
    
    if (searchInput && searchButton) {
        // Search on button click
        searchButton.addEventListener('click', function() {
            performSearch(searchInput.value);
        });
        
        // Search on Enter key press
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch(searchInput.value);
            }
        });
        
        // Real-time search as user types (optional)
        searchInput.addEventListener('input', function() {
            // Debounce search to avoid too many calls
            clearTimeout(searchInput.searchTimeout);
            searchInput.searchTimeout = setTimeout(() => {
                performSearch(searchInput.value);
            }, 300);
        });
    }
}

function performSearch(query) {
    const requestCards = document.querySelectorAll('.request-card');
    const searchTerm = query.toLowerCase().trim();
    
    requestCards.forEach(card => {
        const userName = card.querySelector('.user-name');
        const skillTags = card.querySelectorAll('.skill-tag');
        let matchFound = false;
        
        // Check if user name matches
        if (userName && userName.textContent.toLowerCase().includes(searchTerm)) {
            matchFound = true;
        }
        
        // Check if any skill tags match
        skillTags.forEach(tag => {
            if (tag.textContent.toLowerCase().includes(searchTerm)) {
                matchFound = true;
            }
        });
        
        // Show/hide card based on match
        if (searchTerm === '' || matchFound) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
    
    console.log('Search performed for:', query);
}

function initializeFilter() {
    const statusSelect = document.querySelector('.status-select');
    
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            filterByStatus(this.value);
        });
    }
}

function filterByStatus(status) {
    const requestCards = document.querySelectorAll('.request-card');
    
    requestCards.forEach(card => {
        const statusValue = card.querySelector('.status-value');
        
        if (status === 'Pending' || status === 'Accepted' || status === 'Rejected') {
            if (statusValue && statusValue.textContent.trim() === status) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        } else {
            // Show all if no specific filter
            card.style.display = 'flex';
        }
    });
    
    console.log('Filter applied:', status);
}

function initializeActionButtons() {
    const acceptButtons = document.querySelectorAll('.accept-btn');
    const rejectButtons = document.querySelectorAll('.reject-btn');
    
    acceptButtons.forEach(button => {
        button.addEventListener('click', function() {
            handleAccept(this);
        });
    });
    
    rejectButtons.forEach(button => {
        button.addEventListener('click', function() {
            handleReject(this);
        });
    });
}

function handleAccept(button) {
    const requestCard = button.closest('.request-card');
    const statusValue = requestCard.querySelector('.status-value');
    const actionButtons = requestCard.querySelector('.action-buttons');
    const userName = requestCard.querySelector('.user-name').textContent;
    
    // Update status
    statusValue.textContent = 'Accepted';
    statusValue.className = 'status-value accepted';
    
    // Hide action buttons
    actionButtons.style.display = 'none';
    
    // Show confirmation message
    showNotification(`Request from ${userName} has been accepted!`, 'success');
    
    console.log('Request accepted for:', userName);
}

function handleReject(button) {
    const requestCard = button.closest('.request-card');
    const statusValue = requestCard.querySelector('.status-value');
    const actionButtons = requestCard.querySelector('.action-buttons');
    const userName = requestCard.querySelector('.user-name').textContent;
    
    // Update status
    statusValue.textContent = 'Rejected';
    statusValue.className = 'status-value rejected';
    
    // Hide action buttons
    actionButtons.style.display = 'none';
    
    // Show confirmation message
    showNotification(`Request from ${userName} has been rejected.`, 'error');
    
    console.log('Request rejected for:', userName);
}

function initializePagination() {
    const paginationLinks = document.querySelectorAll('.pagination-link');
    
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            handlePagination(this);
        });
    });
}

function handlePagination(link) {
    // Remove active class from all links
    document.querySelectorAll('.pagination-link').forEach(l => {
        l.classList.remove('active');
    });
    
    // Add active class to clicked link (if it's a number)
    if (!link.querySelector('.material-icons')) {
        link.classList.add('active');
    }
    
    const pageText = link.textContent.trim();
    console.log('Page navigation:', pageText);
    
    // Here you would typically load new page content
    // For demo purposes, we'll just show a message
    if (pageText && !isNaN(pageText)) {
        showNotification(`Loading page ${pageText}...`, 'info');
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 24px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    // Set background color based on type
    switch (type) {
        case 'success':
            notification.style.backgroundColor = '#10b981';
            break;
        case 'error':
            notification.style.backgroundColor = '#ef4444';
            break;
        case 'info':
            notification.style.backgroundColor = '#3b82f6';
            break;
        default:
            notification.style.backgroundColor = '#6b7280';
    }
    
    // Add to document
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export functions for testing (if needed)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        performSearch,
        filterByStatus,
        handleAccept,
        handleReject,
        showNotification
    };
}