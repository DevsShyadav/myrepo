/**
 * Session Management Service
 * Handles WhatsApp session persistence for ephemeral environments
 */

const fs = require('fs');
const path = require('path');
const logger = require('../utils/logger');

const SESSION_DIR = path.join(__dirname, '..', 'wa_session');

/**
 * Check if session data exists
 * @returns {boolean} Has session
 */
function hasSession() {
    try {
        const sessionPath = path.join(SESSION_DIR, 'session');
        return fs.existsSync(sessionPath);
    } catch (err) {
        return false;
    }
}

/**
 * Get session status
 * @returns {object} Session info
 */
function getStatus() {
    const exists = hasSession();
    let lastModified = null;

    if (exists) {
        try {
            const sessionPath = path.join(SESSION_DIR, 'session');
            const stats = fs.statSync(sessionPath);
            lastModified = stats.mtime.toISOString();
        } catch (err) {
            // Ignore
        }
    }

    return {
        hasSession: exists,
        lastModified: lastModified,
        sessionDir: SESSION_DIR
    };
}

/**
 * Clear session data (for re-authentication)
 * @returns {boolean} Success
 */
function clearSession() {
    try {
        if (fs.existsSync(SESSION_DIR)) {
            fs.rmSync(SESSION_DIR, { recursive: true, force: true });
            fs.mkdirSync(SESSION_DIR, { recursive: true });
            logger.info('Session cleared successfully');
            return true;
        }
        return true;
    } catch (err) {
        logger.error('Failed to clear session:', err);
        return false;
    }
}

/**
 * Ensure session directory exists
 */
function ensureSessionDir() {
    if (!fs.existsSync(SESSION_DIR)) {
        fs.mkdirSync(SESSION_DIR, { recursive: true });
        logger.info('Session directory created');
    }
}

// Ensure directory on load
ensureSessionDir();

module.exports = {
    hasSession,
    getStatus,
    clearSession,
    ensureSessionDir
};
