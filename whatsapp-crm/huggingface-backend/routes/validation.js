/**
 * Validation routes
 * WhatsApp number validation endpoints
 */

const express = require('express');
const router = express.Router();
const validatorService = require('../services/validator');
const { isValidFormat } = require('../utils/phoneFormatter');
const logger = require('../utils/logger');

// POST /api/validation/check - Check single number
router.post('/check', async (req, res) => {
    try {
        const { phone } = req.body;

        if (!phone) {
            return res.status(400).json({ error: 'Phone number required' });
        }

        if (!isValidFormat(phone)) {
            return res.status(400).json({
                error: 'Invalid phone format',
                phone: phone
            });
        }

        const result = await validatorService.validateNumber(phone);
        res.json(result);

    } catch (err) {
        logger.error('Validation check error:', err);
        res.status(500).json({
            error: 'Validation failed',
            message: err.message
        });
    }
});

// POST /api/validation/batch - Check batch of numbers
router.post('/batch', async (req, res) => {
    try {
        const { phones } = req.body;

        if (!phones || !Array.isArray(phones) || phones.length === 0) {
            return res.status(400).json({ error: 'Phones array required' });
        }

        // Filter valid format numbers
        const validPhones = phones.filter(p => isValidFormat(p));
        const invalidPhones = phones.filter(p => !isValidFormat(p));

        if (validPhones.length === 0) {
            return res.status(400).json({
                error: 'No valid phone numbers in batch',
                invalidCount: invalidPhones.length
            });
        }

        // Start async batch validation
        validatorService.validateBatch(validPhones);

        res.json({
            success: true,
            message: 'Batch validation started',
            validCount: validPhones.length,
            invalidCount: invalidPhones.length,
            invalidPhones: invalidPhones
        });

    } catch (err) {
        logger.error('Batch validation error:', err);
        res.status(500).json({
            error: 'Batch validation failed',
            message: err.message
        });
    }
});

// GET /api/validation/status - Get validation status
router.get('/status', (req, res) => {
    res.json(validatorService.getStatus());
});

module.exports = router;
