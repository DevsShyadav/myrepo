/**
 * Socket.io Handler
 * Manages realtime connections and events
 */

const logger = require('../utils/logger');
const whatsappService = require('../services/whatsapp');
const queueService = require('../services/queue');
const validatorService = require('../services/validator');
const qrcode = require('qrcode');

let connectedClients = 0;

/**
 * Initialize socket handler
 * @param {object} io - Socket.io server instance
 */
function init(io) {
    // Initialize dependent services with io
    queueService.init(io);
    validatorService.init(io);

    io.on('connection', (socket) => {
        connectedClients++;
        logger.info(`Client connected: ${socket.id} (total: ${connectedClients})`);

        // Send current status on connect
        const waStatus = whatsappService.getStatus();
        socket.emit('status_update', {
            whatsapp: waStatus,
            queue: queueService.getStatus(),
            connectedClients: connectedClients
        });

        // If QR exists, send it
        const currentQR = whatsappService.getCurrentQR();
        if (currentQR) {
            qrcode.toDataURL(currentQR, {
                width: 280,
                margin: 2,
                color: { dark: '#111827', light: '#FFFFFF' }
            }).then(qrDataUrl => {
                socket.emit('qr_code', { qr: qrDataUrl });
            }).catch(() => {});
        }

        // Heartbeat
        const heartbeatInterval = setInterval(() => {
            socket.emit('heartbeat', {
                timestamp: Date.now(),
                waReady: whatsappService.getStatus().isReady
            });
        }, 25000);

        // Client requests status
        socket.on('request_status', () => {
            socket.emit('status_update', {
                whatsapp: whatsappService.getStatus(),
                queue: queueService.getStatus(),
                connectedClients: connectedClients
            });
        });

        // Client requests QR refresh
        socket.on('request_qr', () => {
            const qr = whatsappService.getCurrentQR();
            if (qr) {
                qrcode.toDataURL(qr, {
                    width: 280,
                    margin: 2,
                    color: { dark: '#111827', light: '#FFFFFF' }
                }).then(qrDataUrl => {
                    socket.emit('qr_code', { qr: qrDataUrl });
                }).catch(() => {});
            } else {
                socket.emit('no_qr', { message: 'No QR available. Already connected or initializing.' });
            }
        });

        // Disconnect
        socket.on('disconnect', (reason) => {
            connectedClients--;
            clearInterval(heartbeatInterval);
            logger.info(`Client disconnected: ${socket.id} (${reason}). Total: ${connectedClients}`);
        });

        // Error
        socket.on('error', (err) => {
            logger.error(`Socket error (${socket.id}):`, err);
        });
    });

    logger.info('Socket.io handler initialized');
}

/**
 * Get connected clients count
 * @returns {number}
 */
function getConnectedClients() {
    return connectedClients;
}

module.exports = {
    init,
    getConnectedClients
};
