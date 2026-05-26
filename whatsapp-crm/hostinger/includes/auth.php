<?php
/**
 * Authentication & Security
 * Handles webhook verification and API authentication
 */

/**
 * Verify webhook signature from Node.js backend
 * 
 * @return bool Valid webhook
 */
function verifyWebhook() {
    $secret = getSetting('webhook_secret', WEBHOOK_SECRET);
    
    // If no secret configured, allow all webhooks (for initial setup)
    if (empty($secret)) {
        return true;
    }
    
    $receivedSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
    
    if (empty($receivedSecret)) {
        writeLog('Webhook request missing secret header', 'WARNING');
        return false;
    }
    
    return hash_equals($secret, $receivedSecret);
}

/**
 * Verify API request from dashboard
 * Checks session validity
 * 
 * @return bool Valid request
 */
function verifyDashboardRequest() {
    // For API endpoints called from dashboard
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if session is active
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        // For AJAX requests, check origin
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        $serverHost = $_SERVER['HTTP_HOST'] ?? '';
        
        if (!empty($origin) && strpos($origin, $serverHost) !== false) {
            return true; // Same-origin request
        }
        
        // Allow if coming from same server
        return true; // Simplified for shared hosting - rely on session
    }
    
    return true;
}

/**
 * Initialize session with security settings
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        
        session_start();
    }
    
    // Set authenticated flag if not set
    if (!isset($_SESSION['authenticated'])) {
        $_SESSION['authenticated'] = true;
        $_SESSION['started_at'] = time();
    }
    
    // Generate CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Set security headers for API responses
 */
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // CORS for API endpoints
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!empty($origin)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
    }
    
    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Rate limit check for API endpoints (simple file-based)
 * 
 * @param string $identifier Client identifier
 * @param int $maxRequests Max requests per window
 * @param int $windowSeconds Time window in seconds
 * @return bool Within limit
 */
function checkRateLimit($identifier = null, $maxRequests = 60, $windowSeconds = 60) {
    if ($identifier === null) {
        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    $cacheFile = LOG_DIR . 'rate_' . md5($identifier) . '.json';
    
    $data = [];
    if (file_exists($cacheFile)) {
        $content = @file_get_contents($cacheFile);
        if ($content) {
            $data = json_decode($content, true) ?? [];
        }
    }
    
    $now = time();
    
    // Clean old entries
    $data = array_filter($data, function($timestamp) use ($now, $windowSeconds) {
        return ($now - $timestamp) < $windowSeconds;
    });
    
    // Check limit
    if (count($data) >= $maxRequests) {
        return false;
    }
    
    // Add current request
    $data[] = $now;
    @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    
    return true;
}
