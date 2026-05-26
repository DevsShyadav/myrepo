/**
 * Authentication middleware
 * Validates API key for incoming requests
 * 
 * BEHAVIOR:
 * - If API_KEY env is NOT set -> allows all requests (development/initial setup)
 * - If API_KEY env IS set -> requires matching x-api-key header
 * - Also accepts api_key query parameter as fallback
 */

const logger = require('../utils/logger');

function authMiddleware(req, res, next) {
    // If no API_KEY configured on server, allow all requests
    // This is intentional for initial setup and development
    if (!process.env.API_KEY || process.env.API_KEY.trim() === '') {
        return next();
    }

    const apiKey = req.headers['x-api-key'] || req.headers['x-api-key'.toLowerCase()] || req.query.api_key || '';

    if (!apiKey || apiKey.trim() === '') {
        logger.warn('Auth: Missing API key from:', req.ip, req.path);
        return res.status(401).json({
            error: 'Authentication required',
            message: 'Missing API key. Add x-api-key header or api_key query parameter.'
        });
    }

    if (apiKey.trim() !== process.env.API_KEY.trim()) {
        logger.warn('Auth: Invalid API key from:', req.ip);
        return res.status(403).json({
            error: 'Forbidden',
            message: 'Invalid API key'
        });
    }

    next();
}

module.exports = authMiddleware;
