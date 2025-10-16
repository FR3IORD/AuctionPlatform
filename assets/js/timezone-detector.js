// Timezone Detection and Management
class TimezoneManager {
    constructor() {
        this.userTimezone = this.detectTimezone();
        this.sendTimezoneToServer();
    }

    detectTimezone() {
        try {
            // Get user's timezone using Intl API
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch (e) {
            // Fallback for older browsers
            const offset = new Date().getTimezoneOffset();
            const hours = Math.abs(Math.floor(offset / 60));
            const minutes = Math.abs(offset % 60);
            const sign = offset > 0 ? '-' : '+';
            
            // Common timezone mappings
            const timezoneMap = {
                '-240': 'Asia/Tbilisi',  // UTC+4 (Georgia)
                '-180': 'Europe/Moscow', // UTC+3 (Moscow)
                '0': 'UTC',              // UTC
                '60': 'Europe/London',   // UTC+1
                '120': 'Europe/Berlin'   // UTC+2
            };
            
            return timezoneMap[offset.toString()] || 'UTC';
        }
    }

    sendTimezoneToServer() {
        // Send timezone to server via AJAX
        const formData = new FormData();
        formData.append('user_timezone', this.userTimezone);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).catch(e => {
            console.log('Timezone detection failed:', e);
        });
    }

    formatDateTime(datetime, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: this.userTimezone
        };
        
        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            return new Intl.DateTimeFormat('en-US', finalOptions).format(new Date(datetime));
        } catch (e) {
            return new Date(datetime).toLocaleString();
        }
    }

    getTimezone() {
        return this.userTimezone;
    }
}

// Initialize timezone manager
const timezoneManager = new TimezoneManager();

// Export for global use
window.TimezoneManager = timezoneManager;