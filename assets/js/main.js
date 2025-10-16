// Main JavaScript file for AuctionBay

// Mobile menu toggle
function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

// Enhanced countdown timer with timezone support
function updateCountdown(element, endTime) {
    function updateTimer() {
        // Create dates in user's timezone
        const now = new Date();
        const endDate = new Date(endTime);
        
        // Calculate difference in milliseconds
        const distance = endDate.getTime() - now.getTime();

        if (distance < 0) {
            // Auction ended
            element.innerHTML = `
                <div class="col-span-4 text-center">
                    <div class="text-red-500 font-bold text-lg animate-pulse">
                        <i class="fas fa-flag-checkered mr-2"></i>
                        AUCTION ENDED
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                        Ended at ${window.TimezoneManager.formatDateTime(endTime)}
                    </div>
                </div>
            `;
            return false; // Stop the timer
        }

        // Calculate time units
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        // Update display elements
        const daysEl = element.querySelector('.countdown-days');
        const hoursEl = element.querySelector('.countdown-hours');
        const minutesEl = element.querySelector('.countdown-minutes');
        const secondsEl = element.querySelector('.countdown-seconds');

        if (daysEl) daysEl.textContent = days.toString().padStart(2, '0');
        if (hoursEl) hoursEl.textContent = hours.toString().padStart(2, '0');
        if (minutesEl) minutesEl.textContent = minutes.toString().padStart(2, '0');
        if (secondsEl) secondsEl.textContent = seconds.toString().padStart(2, '0');

        // Add urgency styling for last hour
        if (distance < 60 * 60 * 1000) { // Less than 1 hour
            element.classList.add('urgent-countdown');
            if (daysEl) daysEl.classList.add('text-red-500', 'animate-pulse');
            if (hoursEl) hoursEl.classList.add('text-red-500', 'animate-pulse');
            if (minutesEl) minutesEl.classList.add('text-red-500', 'animate-pulse');
            if (secondsEl) secondsEl.classList.add('text-red-500', 'animate-pulse');
        }

        return true; // Continue the timer
    }

    // Initial update
    if (updateTimer()) {
        // Continue updating every second
        const timer = setInterval(() => {
            if (!updateTimer()) {
                clearInterval(timer);
            }
        }, 1000);
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('AuctionBay JavaScript loaded successfully!');
    console.log('User timezone:', window.TimezoneManager?.getTimezone() || 'Not detected');

    // Initialize countdown timers
    document.querySelectorAll('[data-end-time]').forEach(element => {
        const endTime = element.getAttribute('data-end-time');
        if (endTime) {
            updateCountdown(element, endTime);
        }
    });

    // Update all displayed times to user's timezone
    updateDisplayedTimes();

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Initialize other features
    enhanceFormValidation();
    initializeTooltips();
});

// Update displayed times to user's timezone
function updateDisplayedTimes() {
    if (!window.TimezoneManager) return;

    // Update all elements with data-datetime attribute
    document.querySelectorAll('[data-datetime]').forEach(element => {
        const datetime = element.getAttribute('data-datetime');
        const format = element.getAttribute('data-format') || {};
        
        try {
            const formattedTime = window.TimezoneManager.formatDateTime(datetime, format);
            element.textContent = formattedTime;
        } catch (e) {
            console.error('Error formatting datetime:', e);
        }
    });
}

// Enhanced form validation
function enhanceFormValidation() {
    // Real-time password strength indicator
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        if (input.name === 'password') {
            input.addEventListener('input', function() {
                showPasswordStrength(this);
            });
        }
    });

    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateEmail(this);
        });
    });

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatPhoneNumber(this);
        });
    });
}

// Password strength indicator
function showPasswordStrength(input) {
    const password = input.value;
    const strength = calculatePasswordStrength(password);
    
    // Remove existing strength indicator
    const existingIndicator = input.parentNode.querySelector('.password-strength');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    // Add new strength indicator
    if (password.length > 0) {
        const indicator = document.createElement('div');
        indicator.className = 'password-strength mt-2 text-sm';
        
        let color, text;
        switch(strength) {
            case 1:
                color = 'text-red-500';
                text = 'Weak password';
                break;
            case 2:
                color = 'text-yellow-500';
                text = 'Fair password';
                break;
            case 3:
                color = 'text-blue-500';
                text = 'Good password';
                break;
            case 4:
                color = 'text-green-500';
                text = 'Strong password';
                break;
            default:
                color = 'text-gray-500';
                text = 'Enter password';
        }
        
        indicator.className += ' ' + color;
        indicator.textContent = text;
        input.parentNode.appendChild(indicator);
    }
}

// Calculate password strength
function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;
    
    return strength;
}

// Email validation
function validateEmail(input) {
    const email = input.value;
    const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    
    if (email && !isValid) {
        input.classList.add('border-red-500');
        showFieldError(input, 'Please enter a valid email address');
    } else {
        input.classList.remove('border-red-500');
        hideFieldError(input);
    }
}

// Phone number formatting
function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length >= 6) {
        value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
    } else if (value.length >= 3) {
        value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
    }
    
    input.value = value;
}

// Show field error
function showFieldError(input, message) {
    hideFieldError(input); // Remove existing error
    
    const error = document.createElement('div');
    error.className = 'field-error text-red-500 text-sm mt-1';
    error.textContent = message;
    
    input.parentNode.appendChild(error);
}

// Hide field error
function hideFieldError(input) {
    const error = input.parentNode.querySelector('.field-error');
    if (error) {
        error.remove();
    }
}

// Initialize tooltips
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Show tooltip
function showTooltip(event) {
    const element = event.target;
    const text = element.getAttribute('data-tooltip');
    
    if (!text) return;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip absolute z-50 bg-gray-900 text-white text-sm px-3 py-2 rounded-lg shadow-lg';
    tooltip.textContent = text;
    tooltip.id = 'tooltip-' + Date.now();
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    
    element.tooltipId = tooltip.id;
}

// Hide tooltip
function hideTooltip(event) {
    const element = event.target;
    if (element.tooltipId) {
        const tooltip = document.getElementById(element.tooltipId);
        if (tooltip) {
            tooltip.remove();
        }
        element.tooltipId = null;
    }
}

// Notification system
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all transform translate-x-full opacity-0`;
    
    // Set color based on type
    switch(type) {
        case 'success':
            notification.className += ' bg-green-500 text-white';
            break;
        case 'error':
            notification.className += ' bg-red-500 text-white';
            break;
        case 'warning':
            notification.className += ' bg-yellow-500 text-white';
            break;
        default:
            notification.className += ' bg-blue-500 text-white';
    }
    
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${message}</span>
            <button onclick="hideNotification(this.parentElement.parentElement)" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full', 'opacity-0');
    }, 100);
    
    // Auto-hide
    setTimeout(() => {
        hideNotification(notification);
    }, duration);
}

// Hide notification
function hideNotification(notification) {
    notification.classList.add('translate-x-full', 'opacity-0');
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

// Export functions for use in other scripts
window.AuctionBay = {
    showNotification,
    hideNotification,
    updateCountdown,
    TimezoneManager: window.TimezoneManager
};