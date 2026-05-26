/**
 * Settings Manager
 * Handles settings panel, load/save
 */

const SettingsManager = {
    settings: {},

    init() {
        this.bindEvents();
    },

    bindEvents() {
        const saveBtn = document.getElementById('btn-save-settings');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveSettings());
        }
    },

    async loadSettings() {
        try {
            const data = await Utils.api('settings.php');
            this.settings = data.settings || {};
            this.renderSettings();
        } catch (err) {
            Toast.error('Failed to load settings');
        }
    },

    renderSettings() {
        const container = document.getElementById('settings-content');
        if (!container) return;

        let html = '';

        // API Settings
        html += this.renderSection('API Configuration', 'api', [
            { key: 'groq_api_key', label: 'Groq API Key', type: 'password' },
            { key: 'groq_model', label: 'Groq Model', type: 'text' },
            { key: 'node_api_url', label: 'Node.js Backend URL', type: 'text' },
            { key: 'node_api_key', label: 'Node API Key', type: 'password' },
            { key: 'webhook_secret', label: 'Webhook Secret', type: 'password' },
            { key: 'socket_url', label: 'Socket.io URL', type: 'text' }
        ]);

        // Campaign Settings
        html += this.renderSection('Campaign Settings', 'campaign', [
            { key: 'min_delay', label: 'Min Delay (seconds)', type: 'number' },
            { key: 'max_delay', label: 'Max Delay (seconds)', type: 'number' },
            { key: 'daily_limit', label: 'Daily Send Limit', type: 'number' },
            { key: 'max_consecutive', label: 'Max Consecutive', type: 'number' },
            { key: 'cooldown_duration', label: 'Cooldown Duration (sec)', type: 'number' },
            { key: 'business_hours_start', label: 'Business Hours Start', type: 'number' },
            { key: 'business_hours_end', label: 'Business Hours End', type: 'number' },
            { key: 'retry_limit', label: 'Retry Limit', type: 'number' }
        ]);

        // System Settings
        html += this.renderSection('System Settings', 'system', [
            { key: 'logging_enabled', label: 'Enable Logging', type: 'boolean' },
            { key: 'webhook_retry_enabled', label: 'Webhook Retry', type: 'boolean' },
            { key: 'duplicate_check_enabled', label: 'Duplicate Check', type: 'boolean' }
        ]);

        // UI Settings
        html += this.renderSection('UI Settings', 'ui', [
            { key: 'notification_sound', label: 'Notification Sounds', type: 'boolean' },
            { key: 'auto_refresh_interval', label: 'Auto Refresh (seconds)', type: 'number' }
        ]);

        container.innerHTML = html;
    },

    renderSection(title, category, fields) {
        const categorySettings = this.settings[category] || [];

        let fieldsHtml = fields.map(field => {
            const setting = categorySettings.find(s => s.key === field.key);
            const value = setting ? setting.value : '';

            if (field.type === 'boolean') {
                const isActive = value === true || value === 'true' || value === '1';
                return `<div class="detail-row">
                    <span class="label">${field.label}</span>
                    <div class="toggle ${isActive ? 'active' : ''}" data-key="${field.key}" onclick="SettingsManager.toggleSetting(this)"></div>
                </div>`;
            }

            return `<div class="form-group">
                <label>${field.label}</label>
                <input type="${field.type}" class="form-control setting-input" data-key="${field.key}" value="${Utils.escapeHtml(String(value || ''))}" />
            </div>`;
        }).join('');

        return `<div class="settings-section">
            <h3>${title}</h3>
            ${fieldsHtml}
        </div>`;
    },

    toggleSetting(el) {
        el.classList.toggle('active');
    },

    async saveSettings() {
        const settings = {};

        // Collect text/number inputs
        document.querySelectorAll('.setting-input').forEach(input => {
            const key = input.dataset.key;
            let value = input.value;
            if (input.type === 'number') value = parseInt(value) || 0;
            settings[key] = value;
        });

        // Collect toggles
        document.querySelectorAll('.toggle[data-key]').forEach(toggle => {
            settings[toggle.dataset.key] = toggle.classList.contains('active');
        });

        try {
            const data = await Utils.api('update_settings.php', 'POST', { settings });
            Toast.success(`Settings saved (${data.updated} updated)`);
        } catch (err) {
            Toast.error('Failed to save settings');
        }
    },

    openPanel() {
        const modal = document.getElementById('settings-modal');
        if (modal) {
            modal.classList.add('active');
            this.loadSettings();
        }
    },

    closePanel() {
        const modal = document.getElementById('settings-modal');
        if (modal) modal.classList.remove('active');
    }
};
