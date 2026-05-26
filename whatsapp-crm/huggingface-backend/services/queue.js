/**
 * Message Queue Service
 * Handles sequential message sending with anti-ban pacing
 */

const logger = require('../utils/logger');
const { randomDelay, sleep } = require('../utils/delay');
const { toChatId } = require('../utils/phoneFormatter');
const whatsappService = require('./whatsapp');
const webhookService = require('./webhook');

let queue = [];
let isProcessing = false;
let isPaused = false;
let sentToday = 0;
let consecutiveCount = 0;
let io = null;

// Config defaults
let config = {
    minDelay: 120,
    maxDelay: 300,
    dailyLimit: 50,
    maxConsecutive: 10,
    cooldownDuration: 900
};

/**
 * Initialize queue with socket.io instance
 * @param {object} socketIo - Socket.io instance
 */
function init(socketIo) {
    io = socketIo;

    // Reset daily counter at midnight
    const resetDaily = () => {
        const now = new Date();
        const midnight = new Date(now);
        midnight.setHours(24, 0, 0, 0);
        const msToMidnight = midnight.getTime() - now.getTime();

        setTimeout(() => {
            sentToday = 0;
            logger.info('Daily send counter reset');
            resetDaily();
        }, msToMidnight);
    };
    resetDaily();
}

/**
 * Update queue configuration
 * @param {object} newConfig - New configuration
 */
function updateConfig(newConfig) {
    config = { ...config, ...newConfig };
    logger.info('Queue config updated:', config);
}

/**
 * Add message to queue
 * @param {object} item - Queue item {phone, message, leadId, businessName}
 */
function add(item) {
    // Duplicate check
    const exists = queue.find(q => q.phone === item.phone);
    if (exists) {
        logger.warn(`Duplicate in queue: ${item.phone}`);
        return { success: false, reason: 'duplicate' };
    }

    queue.push({
        ...item,
        addedAt: new Date().toISOString(),
        attempts: 0
    });

    logger.info(`Added to queue: ${item.phone} (${item.businessName})`);

    if (io) {
        io.emit('queue_update', {
            action: 'added',
            queueLength: queue.length,
            item: { phone: item.phone, businessName: item.businessName }
        });
    }

    // Start processing if not already
    if (!isProcessing && !isPaused) {
        processQueue();
    }

    return { success: true, queueLength: queue.length };
}

/**
 * Process queue sequentially
 */
async function processQueue() {
    if (isProcessing || isPaused) return;
    if (queue.length === 0) {
        logger.info('Queue empty');
        if (io) io.emit('queue_update', { action: 'empty', queueLength: 0 });
        return;
    }

    isProcessing = true;

    while (queue.length > 0 && !isPaused) {
        // Check daily limit
        if (sentToday >= config.dailyLimit) {
            logger.warn('Daily limit reached');
            if (io) io.emit('campaign_progress', { event: 'daily_limit_reached', sentToday });
            break;
        }

        // Cooldown after consecutive messages
        if (consecutiveCount >= config.maxConsecutive) {
            logger.info(`Cooldown: ${config.cooldownDuration}s after ${consecutiveCount} messages`);
            if (io) io.emit('campaign_progress', { event: 'cooldown', duration: config.cooldownDuration });
            await sleep(config.cooldownDuration * 1000);
            consecutiveCount = 0;
        }

        const item = queue.shift();

        try {
            // Check if WhatsApp is ready
            const status = whatsappService.getStatus();
            if (!status.isReady) {
                logger.warn('WhatsApp not ready, pausing queue');
                queue.unshift(item);
                isPaused = true;
                if (io) io.emit('campaign_progress', { event: 'wa_not_ready' });
                break;
            }

            // Send message
            const chatId = toChatId(item.phone);
            const result = await whatsappService.sendMessage(chatId, item.message);

            sentToday++;
            consecutiveCount++;

            logger.info(`Message sent: ${item.phone} (${sentToday}/${config.dailyLimit} today)`);

            // Emit progress
            if (io) {
                io.emit('message_sent', {
                    phone: item.phone,
                    leadId: item.leadId,
                    businessName: item.businessName,
                    messageId: result.messageId,
                    timestamp: result.timestamp,
                    sentToday: sentToday,
                    queueRemaining: queue.length
                });

                io.emit('campaign_progress', {
                    event: 'message_sent',
                    sentToday: sentToday,
                    queueRemaining: queue.length,
                    dailyLimit: config.dailyLimit
                });
            }

            // Webhook to Hostinger
            await webhookService.send('message_sent', {
                phone: item.phone,
                lead_id: item.leadId,
                wa_message_id: result.messageId,
                message: item.message,
                timestamp: result.timestamp
            });

            // Random delay before next message
            if (queue.length > 0 && !isPaused) {
                const delay = await randomDelay(config.minDelay, config.maxDelay);
                logger.info(`Waiting ${Math.round(delay / 1000)}s before next message`);
            }

        } catch (err) {
            logger.error(`Failed to send to ${item.phone}:`, err.message);
            item.attempts++;

            if (item.attempts < 3) {
                queue.push(item);
                logger.info(`Re-queued ${item.phone} (attempt ${item.attempts})`);
            } else {
                logger.error(`Permanently failed: ${item.phone}`);
                if (io) {
                    io.emit('message_failed', {
                        phone: item.phone,
                        leadId: item.leadId,
                        error: err.message
                    });
                }
                await webhookService.send('message_failed', {
                    phone: item.phone,
                    lead_id: item.leadId,
                    error: err.message
                });
            }

            // Wait a bit after failure
            await sleep(5000);
        }
    }

    isProcessing = false;
}

/**
 * Pause queue processing
 */
function pause() {
    isPaused = true;
    isProcessing = false;
    logger.info('Queue paused');
    if (io) io.emit('queue_update', { action: 'paused', queueLength: queue.length });
}

/**
 * Resume queue processing
 */
function resume() {
    isPaused = false;
    logger.info('Queue resumed');
    if (io) io.emit('queue_update', { action: 'resumed', queueLength: queue.length });
    processQueue();
}

/**
 * Clear queue
 */
function clear() {
    queue = [];
    isProcessing = false;
    logger.info('Queue cleared');
    if (io) io.emit('queue_update', { action: 'cleared', queueLength: 0 });
}

/**
 * Remove specific lead from queue
 * @param {string} phone - Phone number to remove
 */
function remove(phone) {
    const initialLength = queue.length;
    queue = queue.filter(item => item.phone !== phone);
    if (queue.length < initialLength) {
        logger.info(`Removed ${phone} from queue`);
    }
}

/**
 * Get queue status
 * @returns {object} Queue status
 */
function getStatus() {
    return {
        queueLength: queue.length,
        isProcessing: isProcessing,
        isPaused: isPaused,
        sentToday: sentToday,
        consecutiveCount: consecutiveCount,
        dailyLimit: config.dailyLimit,
        config: config
    };
}

module.exports = {
    init,
    updateConfig,
    add,
    pause,
    resume,
    clear,
    remove,
    getStatus
};
