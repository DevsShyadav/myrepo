/**
 * Main Application Controller
 * Initializes all modules and coordinates them
 */

const App = {
    socketUrl: '',
    refreshInterval: null,

    async init() {
        // Get config from page data
        this.socketUrl = document.body.dataset.socketUrl || '';

        // Initialize Toast first
        Toast.init();

        // Initialize Socket connection
        SocketManager.init(this.socketUrl);

        // Initialize managers
        LeadsManager.init();
        ChatManager.init();
        CampaignManager.init();
        SettingsManager.init();

        // Load initial stats
        this.loadStats();

        // Auto-refresh stats every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.loadStats();
        }, 30000);

        // Bind global events
        this.bindGlobalEvents();

        console.log('WhatsApp CRM initialized');
    },

    bindGlobalEvents() {
        // Import CSV button
        const importBtn = document.getElementById('btn-import-csv');
        if (importBtn) {
            importBtn.addEventListener('click', () => this.openImportModal());
        }

        // Import form submit
        const importForm = document.getElementById('import-form');
        if (importForm) {
            importForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleImport();
            });
        }

        // Validate numbers button
        const validateBtn = document.getElementById('btn-validate');
        if (validateBtn) {
            validateBtn.addEventListener('click', () => this.validateNumbers());
        }

        // Settings button
        const settingsBtn = document.getElementById('btn-settings');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', () => SettingsManager.openPanel());
        }

        // QR button
        const qrBtn = document.getElementById('btn-show-qr');
        if (qrBtn) {
            qrBtn.addEventListener('click', () => {
                SocketManager.requestQR();
                const modal = document.getElementById('qr-modal');
                if (modal) modal.classList.add('active');
            });
        }

        // Close modals
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const modal = e.target.closest('.modal-overlay');
                if (modal) modal.classList.remove('active');
            });
        });

        // Close drawer
        const drawerBackdrop = document.getElementById('drawer-backdrop');
        if (drawerBackdrop) {
            drawerBackdrop.addEventListener('click', () => ChatManager.closeDrawer());
        }

        // Drawer close button
        const drawerClose = document.getElementById('drawer-close');
        if (drawerClose) {
            drawerClose.addEventListener('click', () => ChatManager.closeDrawer());
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('active');
            });
        });
    },

    async loadStats() {
        try {
            const data = await Utils.api('get_stats.php');
            this.renderStats(data);
        } catch (err) {
            // Silent fail for auto-refresh
        }
    },

    renderStats(data) {
        // Update stat cards
        const updates = {
            'stat-total-leads': Utils.formatNumber(data.total_leads),
            'stat-valid-leads': Utils.formatNumber(data.valid_leads),
            'stat-sent-today': Utils.formatNumber(data.sent_today),
            'stat-replies': Utils.formatNumber(data.total_replies),
            'stat-reply-rate': data.reply_rate + '%',
            'stat-unread': Utils.formatNumber(data.unread_count),
            'stat-pending': Utils.formatNumber(data.pending_validation)
        };

        Object.entries(updates).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        });

        // Campaign status
        if (data.campaign) {
            const statusEl = document.getElementById('campaign-db-status');
            if (statusEl) statusEl.textContent = data.campaign.status || 'idle';
        }
    },

    openImportModal() {
        const modal = document.getElementById('import-modal');
        if (modal) modal.classList.add('active');
    },

    closeImportModal() {
        const modal = document.getElementById('import-modal');
        if (modal) modal.classList.remove('active');
    },

    async handleImport() {
        const fileInput = document.getElementById('csv-file');
        if (!fileInput || !fileInput.files[0]) {
            Toast.error('Please select a CSV file');
            return;
        }

        const file = fileInput.files[0];
        if (!file.name.endsWith('.csv')) {
            Toast.error('Only CSV files are allowed');
            return;
        }

        Toast.info('Importing CSV...');

        try {
            const result = await Utils.uploadFile('import_csv.php', file);

            if (result.error) {
                Toast.error(result.message || 'Import failed');
                return;
            }

            Toast.success(`Imported ${result.imported} leads`);

            if (result.duplicates > 0) Toast.info(`${result.duplicates} duplicates skipped`);
            if (result.invalid > 0) Toast.warning(`${result.invalid} invalid numbers`);

            this.closeImportModal();
            LeadsManager.refresh();
            this.loadStats();
            fileInput.value = '';
        } catch (err) {
            Toast.error('Import failed: ' + err.message);
        }
    },

    async validateNumbers() {
        if (!confirm('Start validating pending WhatsApp numbers?')) return;

        Toast.info('Starting validation...');

        try {
            const data = await Utils.api('validate_numbers.php', 'POST', { limit: 50 });
            Toast.success(`Validation started: ${data.submitted} numbers`);
        } catch (err) {
            Toast.error('Validation failed: ' + err.message);
        }
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => App.init());
