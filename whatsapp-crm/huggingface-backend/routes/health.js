/**
 * Health check routes
 * Used for monitoring and keeping HF Space awake
 */

const express = require('express');
const router = express.Router();
const whatsappService = require('../services/whatsapp');
const queueService = require('../services/queue');
const sessionService = require('../services/session');
const validatorService = require('../services/validator');

// GET /api/health
router.get('/', (req, res) => {
    const waStatus = whatsappService.getStatus();
    const queueStatus = queueService.getStatus();
    const sessionStatus = sessionService.getStatus();
    const validatorStatus = validatorService.getStatus();

    res.json({
        status: 'ok',
        service: 'WhatsApp CRM Engine',
        uptime: process.uptime(),
        timestamp: new Date().toISOString(),
        memory: process.memoryUsage(),
        whatsapp: waStatus,
        queue: queueStatus,
        session: sessionStatus,
        validator: validatorStatus
    });
});

// GET /api/health/ping - Lightweight ping for keep-alive
router.get('/ping', (req, res) => {
    res.json({ pong: true, timestamp: Date.now() });
});

// GET /api/health/wa-status
router.get('/wa-status', (req, res) => {
    const status = whatsappService.getStatus();
    res.json(status);
});

module.exports = router;
