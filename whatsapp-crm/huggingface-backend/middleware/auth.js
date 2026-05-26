/**
 * Authentication middleware
 * Validates API key for incoming requests
 */

const logger = require('../utils/logger');

function authMiddleware(req, res, next) {
    const apiKey = req.headers['x-api-key'] || req.query.api_key;

    if (!process.env.API_KEY) {
        logger.warn('API_KEY not configured - allowing request');
        return next();
    }

    if (!apiKey) {
        return res.status(401).json({
            error: 'Authentication required',
            message: 'Missing API key in x-api-key header'
        });
    }

    if (apiKey !== process.env.API_KEY) {
        logger.warn('Invalid API key attempt from:', req.ip);
        return res.status(403).json({
            error: 'Forbidden',
            message: 'Invalid API key'
        });
    }

    next();
}

module.exports = authMiddleware;
