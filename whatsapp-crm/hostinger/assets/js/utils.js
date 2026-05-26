/**
 * Utility Functions
 * Common helpers used across all JS modules
 */

const Utils = {
    /**
     * Make API request
     */
    async api(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`api/${endpoint}`, options);
            const json = await response.json();

            if (!response.ok) {
                throw new Error(json.message || json.error || `HTTP ${response.status}`);
            }

            return json;
        } catch (err) {
            console.error(`API Error (${endpoint}):`, err);
            throw err;
        }
    },

    /**
     * Format time ago
     */
    timeAgo(datetime) {
        if (!datetime) return 'Never';
        const now = new Date();
        const time = new Date(datetime);
        const diff = Math.floor((now - time) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return time.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
    },

    /**
     * Format date for display
     */
    formatDate(datetime) {
        if (!datetime) return '';
        const d = new Date(datetime);
        return d.toLocaleDateString('en-IN', {
            day: 'numeric', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    },

    /**
     * Get initials from name
     */
    getInitials(name) {
        if (!name) return '?';
        const parts = name.trim().split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    },

    /**
     * Sanitize HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    },

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return (num || 0).toLocaleString('en-IN');
    },

    /**
     * Truncate text
     */
    truncate(text, length = 50) {
        if (!text) return '';
        return text.length > length ? text.substring(0, length) + '...' : text;
    },

    /**
     * Upload file via FormData
     */
    async uploadFile(endpoint, file, extraData = {}) {
        const formData = new FormData();
        formData.append('csv_file', file);

        Object.keys(extraData).forEach(key => {
            formData.append(key, extraData[key]);
        });

        const response = await fetch(`api/${endpoint}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });

        return await response.json();
    }
};
