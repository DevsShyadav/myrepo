<?php
/**
 * Application Configuration
 * WhatsApp CRM + Cold Outreach System
 * 
 * Update these values after deployment
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Application Settings
define('APP_NAME', 'WhatsApp CRM');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'production'); // production | development
define('APP_DEBUG', false);
define('APP_TIMEZONE', 'Asia/Kolkata');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Node.js Backend (Hugging Face Spaces)
define('NODE_API_URL', ''); // e.g., https://username-spacename.hf.space
define('NODE_API_KEY', ''); // Must match HF backend API_KEY env

// Groq AI Configuration
define('GROQ_API_KEY', '');
define('GROQ_MODEL', 'llama-3.1-70b-versatile');
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_MAX_TOKENS', 700);
define('GROQ_TEMPERATURE', 0.9);

// Webhook Configuration
define('WEBHOOK_SECRET', ''); // Must match HF backend WEBHOOK_SECRET env

// Socket.io Configuration
define('SOCKET_URL', ''); // Same as NODE_API_URL for HF Spaces

// Campaign Defaults
define('DEFAULT_MIN_DELAY', 120);
define('DEFAULT_MAX_DELAY', 300);
define('DEFAULT_DAILY_LIMIT', 50);
define('DEFAULT_MAX_CONSECUTIVE', 10);
define('DEFAULT_COOLDOWN', 900);
define('BUSINESS_HOURS_START', 9);
define('BUSINESS_HOURS_END', 19);

// Upload Configuration
define('UPLOAD_DIR', APP_ROOT . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['csv']);

// Logging
define('LOG_DIR', APP_ROOT . '/logs/');
define('LOG_ENABLED', true);

// Security
define('CSRF_ENABLED', true);
define('SESSION_LIFETIME', 86400); // 24 hours

// Pagination
define('DEFAULT_PAGE_SIZE', 50);
define('MAX_PAGE_SIZE', 200);
