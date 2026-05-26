/**
 * Webhook Service
 * Sends events to Hostinger PHP backend
 * Includes retry logic and error handling
 */

const axios = require('axios');
const logger = require('../utils/logger');

const MAX_RETRIES = 3;
const RETRY_DELAY = 5000;

/**
 * Send webhook event to Hostinger
 * @param {string} event - Event name
 * @param {object} data - Event data
 * @returns {Promise<boolean>} Success status
 */
async function send(event, data) {
    const webhookUrl = process.env.WEBHOOK_URL;
    const webhookSecret = process.env.WEBHOOK_SECRET;

    if (!webhookUrl) {
        logger.warn('WEBHOOK_URL not configured, skipping webhook');
        return false;
    }

    const payload = {
        event: event,
        data: data,
        timestamp: new Date().toISOString()
    };

    for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
        try {
            const response = await axios.post(webhookUrl, payload, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Webhook-Secret': webhookSecret || '',
                    'X-Webhook-Event': event
                },
                timeout: 10000
            });

            if (response.status >= 200 && response.status < 300) {
                logger.debug(`Webhook sent: ${event} (attempt ${attempt})`);
                return true;
            }

        } catch (err) {
            logger.error(`Webhook failed (attempt ${attempt}/${MAX_RETRIES}): ${event}`, {
                error: err.message,
                status: err.response ? err.response.status : null
            });

            if (attempt < MAX_RETRIES) {
                await new Promise(resolve => setTimeout(resolve, RETRY_DELAY * attempt));
            }
        }
    }

    logger.error(`Webhook permanently failed after ${MAX_RETRIES} attempts: ${event}`);
    return false;
}

module.exports = {
    send
};
