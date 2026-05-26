/**
 * Socket.io Connection Manager
 * Handles realtime communication with HF backend
 */

const SocketManager = {
    socket: null,
    connected: false,
    socketUrl: '',
    reconnectAttempts: 0,
    maxReconnectAttempts: 50,

    init(url) {
        this.socketUrl = url;
        if (!url) {
            console.warn('Socket URL not configured');
            this.updateConnectionUI(false);
            return;
        }

        this.connect();
    },

    connect() {
        if (!this.socketUrl) return;

        try {
            this.socket = io(this.socketUrl, {
                transports: ['websocket', 'polling'],
                reconnection: true,
                reconnectionAttempts: this.maxReconnectAttempts,
                reconnectionDelay: 1000,
                reconnectionDelayMax: 30000,
                timeout: 20000
            });

            this.setupEvents();
        } catch (err) {
            console.error('Socket connection error:', err);
            this.updateConnectionUI(false);
        }
    },

    setupEvents() {
        const s = this.socket;

        s.on('connect', () => {
            this.connected = true;
            this.reconnectAttempts = 0;
            console.log('Socket connected');
            this.updateConnectionUI(true);
            Toast.success('Realtime connected');
        });

        s.on('disconnect', (reason) => {
            this.connected = false;
            console.log('Socket disconnected:', reason);
            this.updateConnectionUI(false);
        });

        s.on('reconnect_attempt', (attempt) => {
            this.reconnectAttempts = attempt;
        });

        s.on('reconnect_failed', () => {
            Toast.error('Realtime connection failed');
        });

        // WhatsApp events
        s.on('qr_code', (data) => {
            this.handleQR(data);
        });

        s.on('wa_connected', (data) => {
            this.handleWAConnected(data);
        });

        s.on('wa_disconnected', (data) => {
            this.handleWADisconnected(data);
        });

        s.on('wa_authenticated', () => {
            Toast.success('WhatsApp authenticated');
            this.hideQRModal();
        });

        // Message events
        s.on('message_received', (data) => {
            this.handleInboundMessage(data);
        });

        s.on('message_sent', (data) => {
            this.handleOutboundMessage(data);
        });

        s.on('message_failed', (data) => {
            Toast.error(`Message failed: ${data.phone}`);
        });

        // Campaign events
        s.on('campaign_progress', (data) => {
            this.handleCampaignProgress(data);
        });

        s.on('queue_update', (data) => {
            this.handleQueueUpdate(data);
        });

        // Validation events
        s.on('validation_progress', (data) => {
            this.handleValidationProgress(data);
        });

        s.on('validation_complete', (data) => {
            Toast.success(`Validation complete: ${data.validated}/${data.total}`);
            if (typeof LeadsManager !== 'undefined') LeadsManager.refresh();
        });

        // Status
        s.on('status_update', (data) => {
            this.handleStatusUpdate(data);
        });

        // Heartbeat
        s.on('heartbeat', (data) => {
            this.updateWAStatus(data.waReady);
        });
    },

    handleQR(data) {
        const qrImg = document.getElementById('qr-image');
        const qrModal = document.getElementById('qr-modal');
        if (qrImg) qrImg.src = data.qr;
        if (qrModal) qrModal.classList.add('active');
        Toast.info('Scan QR code to connect WhatsApp');
    },

    hideQRModal() {
        const qrModal = document.getElementById('qr-modal');
        if (qrModal) qrModal.classList.remove('active');
    },

    handleWAConnected(data) {
        Toast.success('WhatsApp connected!');
        this.hideQRModal();
        this.updateWAStatus(true);
    },

    handleWADisconnected(data) {
        Toast.warning('WhatsApp disconnected');
        this.updateWAStatus(false);
    },

    handleInboundMessage(data) {
        Toast.info(`New message from ${data.phone}`);
        if (typeof ChatManager !== 'undefined') {
            ChatManager.onNewMessage(data, 'inbound');
        }
        if (typeof LeadsManager !== 'undefined') {
            LeadsManager.refresh();
        }
    },

    handleOutboundMessage(data) {
        if (typeof ChatManager !== 'undefined') {
            ChatManager.onNewMessage(data, 'outbound');
        }
        if (typeof CampaignManager !== 'undefined') {
            CampaignManager.updateProgress(data);
        }
    },

    handleCampaignProgress(data) {
        if (typeof CampaignManager !== 'undefined') {
            CampaignManager.handleProgress(data);
        }
    },

    handleQueueUpdate(data) {
        const queueEl = document.getElementById('queue-count');
        if (queueEl) queueEl.textContent = data.queueLength || 0;
    },

    handleValidationProgress(data) {
        const el = document.getElementById('validation-status');
        if (el) el.textContent = `Validating: ${data.validated}/${data.total}`;
    },

    handleStatusUpdate(data) {
        if (data.whatsapp) {
            this.updateWAStatus(data.whatsapp.isReady);
        }
    },

    updateConnectionUI(connected) {
        const badge = document.getElementById('connection-status');
        if (badge) {
            badge.className = `connection-badge ${connected ? 'connected' : 'disconnected'}`;
            badge.innerHTML = `<span class="status-dot ${connected ? 'online' : 'offline'}"></span>
                              ${connected ? 'Realtime Connected' : 'Disconnected'}`;
        }
    },

    updateWAStatus(ready) {
        const badge = document.getElementById('wa-status');
        if (badge) {
            badge.className = `connection-badge ${ready ? 'connected' : 'disconnected'}`;
            badge.innerHTML = `<span class="status-dot ${ready ? 'online' : 'offline'}"></span>
                              WhatsApp: ${ready ? 'Connected' : 'Disconnected'}`;
        }
    },

    requestStatus() {
        if (this.socket && this.connected) {
            this.socket.emit('request_status');
        }
    },

    requestQR() {
        if (this.socket && this.connected) {
            this.socket.emit('request_qr');
        }
    }
};
