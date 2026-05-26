/**
 * WhatsApp Number Validation Service
 * Checks if phone numbers are registered on WhatsApp
 */

const logger = require('../utils/logger');
const { toChatId } = require('../utils/phoneFormatter');
const whatsappService = require('./whatsapp');
const webhookService = require('./webhook');
const { sleep } = require('../utils/delay');

let isValidating = false;
let validationQueue = [];
let io = null;

/**
 * Initialize validator
 * @param {object} socketIo - Socket.io instance
 */
function init(socketIo) {
    io = socketIo;
}

/**
 * Validate a single number
 * @param {string} phone - Phone number to validate
 * @returns {Promise<object>} Validation result
 */
async function validateNumber(phone) {
    const status = whatsappService.getStatus();
    if (!status.isReady) {
        throw new Error('WhatsApp client not ready');
    }

    const chatId = toChatId(phone);

    try {
        const result = await whatsappService.checkNumber(chatId);
        const validationResult = {
            phone: phone,
            status: result.registered ? 'valid' : 'not_on_whatsapp',
            checkedAt: new Date().toISOString()
        };

        logger.info(`Validation: ${phone} -> ${validationResult.status}`);
        return validationResult;

    } catch (err) {
        logger.error(`Validation error for ${phone}:`, err.message);
        return {
            phone: phone,
            status: 'failed',
            error: err.message,
            checkedAt: new Date().toISOString()
        };
    }
}

/**
 * Validate batch of numbers
 * @param {Array} phones - Array of phone numbers
 * @returns {Promise<void>}
 */
async function validateBatch(phones) {
    if (isValidating) {
        validationQueue = validationQueue.concat(phones);
        logger.info(`Added ${phones.length} to validation queue`);
        return;
    }

    isValidating = true;
    const allPhones = [...phones, ...validationQueue];
    validationQueue = [];

    logger.info(`Starting batch validation: ${allPhones.length} numbers`);

    if (io) {
        io.emit('validation_started', {
            total: allPhones.length,
            timestamp: new Date().toISOString()
        });
    }

    let validated = 0;
    const results = [];

    for (const phone of allPhones) {
        try {
            const result = await validateNumber(phone);
            results.push(result);
            validated++;

            // Emit progress
            if (io) {
                io.emit('validation_progress', {
                    phone: phone,
                    status: result.status,
                    validated: validated,
                    total: allPhones.length
                });
            }

            // Send webhook
            await webhookService.send('validation_result', {
                phone: phone,
                status: result.status
            });

            // Small delay between checks to avoid rate limiting
            await sleep(2000);

        } catch (err) {
            logger.error(`Batch validation error for ${phone}:`, err.message);
            results.push({
                phone: phone,
                status: 'failed',
                error: err.message
            });
        }
    }

    isValidating = false;
    logger.info(`Batch validation complete: ${validated}/${allPhones.length}`);

    if (io) {
        io.emit('validation_complete', {
            total: allPhones.length,
            validated: validated,
            results: results
        });
    }

    // Process any queued numbers
    if (validationQueue.length > 0) {
        const nextBatch = [...validationQueue];
        validationQueue = [];
        await validateBatch(nextBatch);
    }

    return results;
}

/**
 * Get validation status
 * @returns {object} Status
 */
function getStatus() {
    return {
        isValidating: isValidating,
        queueLength: validationQueue.length
    };
}

module.exports = {
    init,
    validateNumber,
    validateBatch,
    getStatus
};
