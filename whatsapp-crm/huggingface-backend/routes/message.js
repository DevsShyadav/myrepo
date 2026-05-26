/**
 * Message routes
 * Handles sending messages and queue operations
 */

const express = require('express');
const router = express.Router();
const whatsappService = require('../services/whatsapp');
const queueService = require('../services/queue');
const { toChatId, isValidFormat } = require('../utils/phoneFormatter');
const logger = require('../utils/logger');

// POST /api/message/send - Send a single message immediately
router.post('/send', async (req, res) => {
    try {
        const { phone, message } = req.body;

        if (!phone || !message) {
            return res.status(400).json({
                error: 'Missing required fields',
                required: ['phone', 'message']
            });
        }

        if (!isValidFormat(phone)) {
            return res.status(400).json({
                error: 'Invalid phone number format'
            });
        }

        const status = whatsappService.getStatus();
        if (!status.isReady) {
            return res.status(503).json({
                error: 'WhatsApp not connected',
                waStatus: status
            });
        }

        const chatId = toChatId(phone);
        const result = await whatsappService.sendMessage(chatId, message);

        res.json({
            success: true,
            messageId: result.messageId,
            timestamp: result.timestamp
        });

    } catch (err) {
        logger.error('Send message error:', err);
        res.status(500).json({
            error: 'Failed to send message',
            message: err.message
        });
    }
});

// POST /api/message/queue - Add message to queue
router.post('/queue', (req, res) => {
    try {
        const { phone, message, leadId, businessName } = req.body;

        if (!phone || !message) {
            return res.status(400).json({
                error: 'Missing required fields',
                required: ['phone', 'message']
            });
        }

        const result = queueService.add({
            phone,
            message,
            leadId: leadId || null,
            businessName: businessName || 'Unknown'
        });

        res.json(result);

    } catch (err) {
        logger.error('Queue message error:', err);
        res.status(500).json({
            error: 'Failed to queue message',
            message: err.message
        });
    }
});

// POST /api/message/queue-batch - Add multiple messages to queue
router.post('/queue-batch', (req, res) => {
    try {
        const { messages } = req.body;

        if (!messages || !Array.isArray(messages) || messages.length === 0) {
            return res.status(400).json({
                error: 'Messages array required'
            });
        }

        const results = [];
        for (const msg of messages) {
            if (msg.phone && msg.message) {
                const result = queueService.add({
                    phone: msg.phone,
                    message: msg.message,
                    leadId: msg.leadId || null,
                    businessName: msg.businessName || 'Unknown'
                });
                results.push({ phone: msg.phone, ...result });
            }
        }

        res.json({
            success: true,
            queued: results.length,
            results: results
        });

    } catch (err) {
        logger.error('Batch queue error:', err);
        res.status(500).json({
            error: 'Failed to queue batch',
            message: err.message
        });
    }
});

// GET /api/message/queue-status - Get queue status
router.get('/queue-status', (req, res) => {
    res.json(queueService.getStatus());
});

// POST /api/message/queue-remove - Remove from queue
router.post('/queue-remove', (req, res) => {
    const { phone } = req.body;
    if (!phone) {
        return res.status(400).json({ error: 'Phone required' });
    }
    queueService.remove(phone);
    res.json({ success: true, message: `Removed ${phone} from queue` });
});

module.exports = router;
