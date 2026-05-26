/**
 * Campaign routes
 * Controls campaign operations: start, pause, resume, stop
 */

const express = require('express');
const router = express.Router();
const queueService = require('../services/queue');
const logger = require('../utils/logger');

// POST /api/campaign/pause - Pause campaign
router.post('/pause', (req, res) => {
    try {
        queueService.pause();
        logger.info('Campaign paused via API');
        res.json({ success: true, message: 'Campaign paused' });
    } catch (err) {
        logger.error('Campaign pause error:', err);
        res.status(500).json({ error: err.message });
    }
});

// POST /api/campaign/resume - Resume campaign
router.post('/resume', (req, res) => {
    try {
        queueService.resume();
        logger.info('Campaign resumed via API');
        res.json({ success: true, message: 'Campaign resumed' });
    } catch (err) {
        logger.error('Campaign resume error:', err);
        res.status(500).json({ error: err.message });
    }
});

// POST /api/campaign/clear - Clear queue
router.post('/clear', (req, res) => {
    try {
        queueService.clear();
        logger.info('Campaign queue cleared via API');
        res.json({ success: true, message: 'Queue cleared' });
    } catch (err) {
        logger.error('Campaign clear error:', err);
        res.status(500).json({ error: err.message });
    }
});

// GET /api/campaign/status - Get campaign/queue status
router.get('/status', (req, res) => {
    res.json(queueService.getStatus());
});

// POST /api/campaign/config - Update queue config
router.post('/config', (req, res) => {
    try {
        const { minDelay, maxDelay, dailyLimit, maxConsecutive, cooldownDuration } = req.body;
        const newConfig = {};

        if (minDelay !== undefined) newConfig.minDelay = parseInt(minDelay);
        if (maxDelay !== undefined) newConfig.maxDelay = parseInt(maxDelay);
        if (dailyLimit !== undefined) newConfig.dailyLimit = parseInt(dailyLimit);
        if (maxConsecutive !== undefined) newConfig.maxConsecutive = parseInt(maxConsecutive);
        if (cooldownDuration !== undefined) newConfig.cooldownDuration = parseInt(cooldownDuration);

        queueService.updateConfig(newConfig);
        res.json({ success: true, config: newConfig });
    } catch (err) {
        logger.error('Campaign config error:', err);
        res.status(500).json({ error: err.message });
    }
});

module.exports = router;
