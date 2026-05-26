/**
 * Campaign Manager
 * Handles campaign controls, progress, and status
 */

const CampaignManager = {
    status: 'idle',
    sentToday: 0,
    dailyLimit: 50,
    queueLength: 0,

    init() {
        this.bindEvents();
        this.refreshStatus();
    },

    bindEvents() {
        const startBtn = document.getElementById('btn-start-campaign');
        const pauseBtn = document.getElementById('btn-pause-campaign');
        const resumeBtn = document.getElementById('btn-resume-campaign');
        const clearBtn = document.getElementById('btn-clear-campaign');

        if (startBtn) startBtn.addEventListener('click', () => this.startCampaign());
        if (pauseBtn) pauseBtn.addEventListener('click', () => this.pauseCampaign());
        if (resumeBtn) resumeBtn.addEventListener('click', () => this.resumeCampaign());
        if (clearBtn) clearBtn.addEventListener('click', () => this.clearCampaign());
    },

    async startCampaign() {
        const limit = parseInt(document.getElementById('campaign-batch-size')?.value || 20);

        if (!confirm(`Start campaign with up to ${limit} leads?`)) return;

        try {
            Toast.info('Starting campaign...');
            const data = await Utils.api('start_campaign.php', 'POST', { limit });
            Toast.success(`Campaign started: ${data.queued} leads queued`);
            this.status = 'running';
            this.updateUI();
            LeadsManager.refresh();
        } catch (err) {
            Toast.error('Failed to start: ' + err.message);
        }
    },

    async pauseCampaign() {
        try {
            await Utils.api('pause_campaign.php', 'POST', { action: 'pause' });
            Toast.info('Campaign paused');
            this.status = 'paused';
            this.updateUI();
        } catch (err) {
            Toast.error('Failed to pause');
        }
    },

    async resumeCampaign() {
        try {
            await Utils.api('pause_campaign.php', 'POST', { action: 'resume' });
            Toast.success('Campaign resumed');
            this.status = 'running';
            this.updateUI();
        } catch (err) {
            Toast.error('Failed to resume');
        }
    },

    async clearCampaign() {
        if (!confirm('Clear queue and stop campaign? Queued leads will be reset.')) return;

        try {
            await Utils.api('pause_campaign.php', 'POST', { action: 'clear' });
            Toast.info('Campaign cleared');
            this.status = 'idle';
            this.queueLength = 0;
            this.updateUI();
            LeadsManager.refresh();
        } catch (err) {
            Toast.error('Failed to clear');
        }
    },

    async refreshStatus() {
        try {
            const data = await Utils.api('refresh_sync.php');
            if (data.campaign) {
                this.queueLength = data.campaign.queueLength || 0;
                this.sentToday = data.campaign.sentToday || 0;
                this.dailyLimit = data.campaign.dailyLimit || 50;
                if (data.campaign.isProcessing) this.status = 'running';
                else if (data.campaign.isPaused) this.status = 'paused';
            }
            this.updateUI();
        } catch (err) {
            // Silent fail
        }
    },

    handleProgress(data) {
        if (data.event === 'message_sent') {
            this.sentToday = data.sentToday || this.sentToday + 1;
            this.queueLength = data.queueRemaining || Math.max(0, this.queueLength - 1);
            this.dailyLimit = data.dailyLimit || this.dailyLimit;
        } else if (data.event === 'daily_limit_reached') {
            Toast.warning('Daily send limit reached');
            this.status = 'paused';
        } else if (data.event === 'cooldown') {
            Toast.info(`Cooldown: ${data.duration}s pause`);
        }
        this.updateUI();
    },

    updateProgress(data) {
        this.sentToday = data.sentToday || this.sentToday;
        this.queueLength = data.queueRemaining || this.queueLength;
        this.updateUI();
    },

    updateUI() {
        // Campaign status text
        const statusEl = document.getElementById('campaign-status-text');
        if (statusEl) {
            const statusColors = { running: 'text-primary', paused: 'text-muted', idle: 'text-muted' };
            statusEl.className = statusColors[this.status] || '';
            statusEl.textContent = this.status.charAt(0).toUpperCase() + this.status.slice(1);
        }

        // Progress bar
        const progressBar = document.getElementById('campaign-progress-bar');
        if (progressBar && this.dailyLimit > 0) {
            const pct = Math.min(100, (this.sentToday / this.dailyLimit) * 100);
            progressBar.style.width = pct + '%';
        }

        // Stats
        const sentEl = document.getElementById('campaign-sent-today');
        if (sentEl) sentEl.textContent = `${this.sentToday}/${this.dailyLimit}`;

        const queueEl = document.getElementById('queue-count');
        if (queueEl) queueEl.textContent = this.queueLength;

        // Button visibility
        const startBtn = document.getElementById('btn-start-campaign');
        const pauseBtn = document.getElementById('btn-pause-campaign');
        const resumeBtn = document.getElementById('btn-resume-campaign');

        if (startBtn) startBtn.classList.toggle('hidden', this.status === 'running');
        if (pauseBtn) pauseBtn.classList.toggle('hidden', this.status !== 'running');
        if (resumeBtn) resumeBtn.classList.toggle('hidden', this.status !== 'paused');
    }
};
