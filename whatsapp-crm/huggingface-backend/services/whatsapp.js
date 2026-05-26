/**
 * WhatsApp Service
 * Core WhatsApp engine using whatsapp-web.js
 * Handles: client init, QR, auth, messaging, events
 */

const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const logger = require('../utils/logger');
const webhookService = require('./webhook');
const { fromChatId } = require('../utils/phoneFormatter');

let client = null;
let io = null;
let isReady = false;
let isAuthenticated = false;
let currentQR = null;

/**
 * Initialize WhatsApp client
 * @param {object} socketIo - Socket.io instance
 */
function init(socketIo) {
    io = socketIo;

    client = new Client({
        authStrategy: new LocalAuth({
            dataPath: './wa_session'
        }),
        puppeteer: {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu',
                '--single-process',
                '--disable-extensions'
            ],
            executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined
        }
    });

    // QR Code event
    client.on('qr', async (qr) => {
        logger.info('QR Code received');
        currentQR = qr;
        isAuthenticated = false;

        try {
            const qrDataUrl = await qrcode.toDataURL(qr, {
                width: 280,
                margin: 2,
                color: {
                    dark: '#111827',
                    light: '#FFFFFF'
                }
            });

            io.emit('qr_code', { qr: qrDataUrl });
            logger.info('QR Code emitted to clients');
        } catch (err) {
            logger.error('QR generation error:', err);
        }
    });

    // Authenticated
    client.on('authenticated', () => {
        logger.info('WhatsApp authenticated');
        isAuthenticated = true;
        currentQR = null;
        io.emit('wa_authenticated', { timestamp: new Date().toISOString() });
    });

    // Auth failure
    client.on('auth_failure', (msg) => {
        logger.error('WhatsApp auth failure:', msg);
        isAuthenticated = false;
        isReady = false;
        io.emit('wa_auth_failure', { message: msg });
    });

    // Ready
    client.on('ready', () => {
        logger.info('WhatsApp client is ready');
        isReady = true;
        isAuthenticated = true;
        currentQR = null;
        io.emit('wa_connected', {
            timestamp: new Date().toISOString(),
            info: client.info
        });
    });

    // Disconnected
    client.on('disconnected', (reason) => {
        logger.warn('WhatsApp disconnected:', reason);
        isReady = false;
        isAuthenticated = false;
        io.emit('wa_disconnected', { reason, timestamp: new Date().toISOString() });

        // Attempt reconnect after 5 seconds
        setTimeout(() => {
            logger.info('Attempting to reinitialize WhatsApp client...');
            client.initialize().catch(err => {
                logger.error('Reinitialize failed:', err);
            });
        }, 5000);
    });

    // Incoming message
    client.on('message', async (msg) => {
        if (msg.from === 'status@broadcast') return;
        if (msg.fromMe) return;

        const phone = fromChatId(msg.from);
        logger.info(`Incoming message from ${phone}: ${msg.body.substring(0, 50)}...`);

        // Emit to socket
        io.emit('message_received', {
            phone: phone,
            message: msg.body,
            timestamp: new Date().toISOString(),
            messageId: msg.id._serialized
        });

        // Send webhook to Hostinger
        await webhookService.send('message_received', {
            phone: phone,
            message: msg.body,
            wa_message_id: msg.id._serialized,
            timestamp: new Date().toISOString()
        });
    });

    // Outgoing message acknowledgement
    client.on('message_ack', (msg, ack) => {
        if (!msg.fromMe) return;

        const statusMap = {
            '-1': 'error',
            '0': 'pending',
            '1': 'sent',
            '2': 'delivered',
            '3': 'read',
            '4': 'played'
        };

        const status = statusMap[ack.toString()] || 'unknown';
        const phone = fromChatId(msg.to);

        io.emit('message_ack', {
            phone: phone,
            wa_message_id: msg.id._serialized,
            status: status
        });
    });

    // Initialize client
    logger.info('Initializing WhatsApp client...');
    client.initialize().catch(err => {
        logger.error('WhatsApp initialization error:', err);
    });
}

/**
 * Send a message to a phone number
 * @param {string} chatId - WhatsApp chat ID (number@c.us)
 * @param {string} message - Message text
 * @returns {Promise<object>} Sent message info
 */
async function sendMessage(chatId, message) {
    if (!isReady) {
        throw new Error('WhatsApp client is not ready');
    }

    try {
        const sentMsg = await client.sendMessage(chatId, message);
        logger.info(`Message sent to ${chatId}`);

        return {
            success: true,
            messageId: sentMsg.id._serialized,
            timestamp: new Date().toISOString()
        };
    } catch (err) {
        logger.error(`Failed to send message to ${chatId}:`, err);
        throw err;
    }
}

/**
 * Check if a number is registered on WhatsApp
 * @param {string} numberId - Number in format: number@c.us
 * @returns {Promise<object>} Validation result
 */
async function checkNumber(numberId) {
    if (!isReady) {
        throw new Error('WhatsApp client is not ready');
    }

    try {
        const result = await client.isRegisteredUser(numberId);
        return {
            registered: result,
            numberId: numberId
        };
    } catch (err) {
        logger.error(`Number check failed for ${numberId}:`, err);
        throw err;
    }
}

/**
 * Get current client status
 * @returns {object} Status info
 */
function getStatus() {
    return {
        isReady: isReady,
        isAuthenticated: isAuthenticated,
        hasQR: currentQR !== null,
        info: isReady && client ? client.info : null
    };
}

/**
 * Get current QR code
 * @returns {string|null} QR data URL
 */
function getCurrentQR() {
    return currentQR;
}

/**
 * Destroy client connection
 */
async function destroy() {
    if (client) {
        try {
            await client.destroy();
            logger.info('WhatsApp client destroyed');
        } catch (err) {
            logger.error('Error destroying client:', err);
        }
    }
    isReady = false;
    isAuthenticated = false;
}

/**
 * Logout and clear session
 */
async function logout() {
    if (client) {
        try {
            await client.logout();
            logger.info('WhatsApp logged out');
        } catch (err) {
            logger.error('Error during logout:', err);
        }
    }
    isReady = false;
    isAuthenticated = false;
    currentQR = null;
}

module.exports = {
    init,
    sendMessage,
    checkNumber,
    getStatus,
    getCurrentQR,
    destroy,
    logout
};
