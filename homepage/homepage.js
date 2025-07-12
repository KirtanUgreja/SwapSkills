// Basic JavaScript functionality for the Skill Swap Platform

document.addEventListener('DOMContentLoaded', function() {
    // Login button functionality
    const loginBtn = document.querySelector('.login-btn');
    loginBtn.addEventListener('click', function() {
        alert('Login functionality would be implemented here');
    });

    // Search functionality
    const searchBtn = document.querySelector('.search-btn');
    const searchInput = document.querySelector('.search-input');
    const availabilitySelect = document.querySelector('.availability-select');

    searchBtn.addEventListener('click', function() {
        const searchTerm = searchInput.value.trim();
        const availability = availabilitySelect.value;
        
        if (searchTerm) {
            console.log('Searching for:', searchTerm);
            console.log('Availability filter:', availability);
            // Here you would implement the actual search functionality
            alert(`Searching for: ${searchTerm}`);
        } else {
            alert('Please enter a search term');
        }
    });

    // Allow search on Enter key press
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchBtn.click();
        }
    });

    // Request button functionality
    const requestBtns = document.querySelectorAll('.request-btn');
    requestBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const profileCard = this.closest('.profile-card');
            const profileName = profileCard.querySelector('.profile-name').textContent;
            
            if (confirm(`Send a skill swap request to ${profileName}?`)) {
                alert(`Request sent to ${profileName}!`);
                // Here you would implement the actual request functionality
            }
        });
    });

    // Pagination functionality
    const paginationLinks = document.querySelectorAll('.pagination-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            paginationLinks.forEach(l => l.classList.remove('pagination-active'));
            
            // Add active class to clicked link (if it's a number)
            const linkText = this.textContent.trim();
            if (!isNaN(linkText)) {
                this.classList.add('pagination-active');
                console.log('Loading page:', linkText);
                // Here you would implement the actual pagination functionality
            } else if (linkText === '<') {
                console.log('Previous page');
            } else if (linkText === '>') {
                console.log('Next page');
            }
        });
    });

    // Add hover effects and animations
    const profileCards = document.querySelectorAll('.profile-card');
    profileCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Simple form validation for search
    function validateSearch() {
        const searchTerm = searchInput.value.trim();
        if (searchTerm.length < 2) {
            searchInput.style.borderColor = '#ef4444';
            return false;
        } else {
            searchInput.style.borderColor = '#d1d5db';
            return true;
        }
    }

    searchInput.addEventListener('input', validateSearch);
});

// Utility function to simulate API calls
function simulateApiCall(action, data = {}) {
    return new Promise((resolve) => {
        setTimeout(() => {
            console.log(`API Call: ${action}`, data);
            resolve({ success: true, data });
        }, 1000);
    });
}

// Example usage of the API simulation
async function sendSkillSwapRequest(userId, targetUserId) {
    try {
        const response = await simulateApiCall('sendRequest', {
            from: userId,
            to: targetUserId,
            timestamp: new Date().toISOString()
        });
        
        if (response.success) {
            console.log('Request sent successfully');
        }
    } catch (error) {
        console.error('Error sending request:', error);
    }
}