/**
 * Rate limiting middleware
 * Prevents abuse of API endpoints
 */

const rateLimit = require('express-rate-limit');

const rateLimiter = rateLimit({
    windowMs: 60 * 1000, // 1 minute window
    max: 60, // 60 requests per minute
    standardHeaders: true,
    legacyHeaders: false,
    message: {
        error: 'Too many requests',
        message: 'Rate limit exceeded. Please try again later.',
        retryAfter: 60
    },
    keyGenerator: (req) => {
        return req.headers['x-api-key'] || req.ip;
    }
});

module.exports = rateLimiter;
