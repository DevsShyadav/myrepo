<?php
/**
 * Helper Functions
 * Utility functions used across the application
 */

/**
 * Send JSON response and exit
 * 
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 * 
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 */
function errorResponse($message, $statusCode = 400) {
    jsonResponse(['error' => true, 'message' => $message], $statusCode);
}

/**
 * Send success response
 * 
 * @param array $data Response data
 * @param string $message Success message
 */
function successResponse($data = [], $message = 'Success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

/**
 * Sanitize input string
 * 
 * @param string $input Raw input
 * @return string Sanitized string
 */
function sanitize($input) {
    if ($input === null) return '';
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Clean phone number - remove non-digits, normalize to Indian format
 * 
 * @param string $phone Raw phone number
 * @return string Cleaned phone number (e.g., 919876543210)
 */
function cleanPhone($phone) {
    if (empty($phone)) return '';
    
    // Remove all non-digit characters
    $cleaned = preg_replace('/[^\d]/', '', $phone);
    
    // Handle Indian numbers
    if (strlen($cleaned) === 10 && preg_match('/^[6-9]/', $cleaned)) {
        $cleaned = '91' . $cleaned;
    } elseif (strlen($cleaned) === 11 && $cleaned[0] === '0') {
        $cleaned = '91' . substr($cleaned, 1);
    } elseif (strlen($cleaned) === 13 && substr($cleaned, 0, 3) === '+91') {
        $cleaned = substr($cleaned, 1);
    }
    
    return $cleaned;
}

/**
 * Validate phone number format
 * 
 * @param string $phone Phone number
 * @return bool Is valid
 */
function isValidPhone($phone) {
    $cleaned = cleanPhone($phone);
    return preg_match('/^91[6-9]\d{9}$/', $cleaned) === 1;
}

/**
 * Parse address into locality, city, state
 * 
 * @param string $address Full address
 * @return array Parsed components
 */
function parseAddress($address) {
    $result = [
        'locality' => '',
        'city' => '',
        'state' => ''
    ];
    
    if (empty($address)) return $result;
    
    // Common Indian states for detection
    $states = [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
        'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
        'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
        'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
        'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
        'Delhi', 'New Delhi', 'Chandigarh', 'Puducherry'
    ];
    
    // Try to extract state
    foreach ($states as $state) {
        if (stripos($address, $state) !== false) {
            $result['state'] = $state;
            break;
        }
    }
    
    // Split by comma
    $parts = array_map('trim', explode(',', $address));
    $partCount = count($parts);
    
    if ($partCount >= 3) {
        $result['locality'] = $parts[0];
        $result['city'] = $parts[$partCount - 2];
        if (empty($result['state'])) {
            $result['state'] = preg_replace('/\d+/', '', $parts[$partCount - 1]);
        }
    } elseif ($partCount === 2) {
        $result['locality'] = $parts[0];
        $result['city'] = $parts[1];
    } elseif ($partCount === 1) {
        $result['city'] = $parts[0];
    }
    
    // Clean up
    $result['locality'] = trim($result['locality']);
    $result['city'] = trim(preg_replace('/\d+/', '', $result['city']));
    $result['state'] = trim(preg_replace('/\d+/', '', $result['state']));
    
    return $result;
}

/**
 * Detect language preference based on state/city
 * 
 * @param string $state State name
 * @param string $city City name
 * @return string Language preference
 */
function detectLanguage($state, $city = '') {
    $state = strtolower(trim($state));
    $city = strtolower(trim($city));
    
    $hindiStates = ['bihar', 'uttar pradesh', 'madhya pradesh', 'rajasthan', 
                    'jharkhand', 'chhattisgarh', 'uttarakhand', 'delhi', 'new delhi', 'haryana'];
    $gujarati = ['gujarat'];
    $marathi = ['maharashtra'];
    $bengali = ['west bengal'];
    $tamil = ['tamil nadu'];
    $telugu = ['andhra pradesh', 'telangana'];
    $kannada = ['karnataka'];
    
    if (in_array($state, $hindiStates)) return 'hinglish';
    if (in_array($state, $gujarati)) return 'gujarati_english';
    if (in_array($state, $marathi)) return 'marathi_english';
    if (in_array($state, $bengali)) return 'bengali_english';
    if (in_array($state, $tamil)) return 'english';
    if (in_array($state, $telugu)) return 'english';
    if (in_array($state, $kannada)) return 'english';
    
    // Check city for hindi belt
    $hindiCities = ['patna', 'lucknow', 'jaipur', 'bhopal', 'ranchi', 'dehradun', 
                    'varanasi', 'agra', 'noida', 'gurgaon', 'faridabad', 'ghaziabad'];
    if (in_array($city, $hindiCities)) return 'hinglish';
    
    return 'english';
}

/**
 * Determine pitch type based on website status
 * 
 * @param string|null $websiteUrl Website URL
 * @return array [website_status, pitch_type]
 */
function determinePitchType($websiteUrl) {
    if (!empty($websiteUrl) && $websiteUrl !== 'N/A' && $websiteUrl !== '-' && $websiteUrl !== 'null') {
        return ['has_website', 'type_a'];
    }
    return ['no_website', 'type_b'];
}

/**
 * Log activity to database
 * 
 * @param string $type Log type (info|warning|error|success)
 * @param string $category Category
 * @param string $message Log message
 * @param array $metadata Additional data
 */
function logActivity($type, $category, $message, $metadata = []) {
    if (!defined('LOG_ENABLED') || !LOG_ENABLED) return;
    
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO activity_logs (log_type, category, message, metadata, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $type,
            $category,
            $message,
            !empty($metadata) ? json_encode($metadata) : null
        ]);
    } catch (Exception $e) {
        error_log("logActivity failed: " . $e->getMessage());
    }
}

/**
 * Write to file log
 * 
 * @param string $message Log message
 * @param string $level Log level
 */
function writeLog($message, $level = 'INFO') {
    if (!defined('LOG_DIR')) return;
    
    $logFile = LOG_DIR . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Get pagination parameters from request
 * 
 * @return array [page, limit, offset]
 */
function getPagination() {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(MAX_PAGE_SIZE, max(1, (int)($_GET['limit'] ?? DEFAULT_PAGE_SIZE)));
    $offset = ($page - 1) * $limit;
    
    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}

/**
 * Format datetime for display
 * 
 * @param string $datetime MySQL datetime
 * @return string Formatted date
 */
function formatDate($datetime) {
    if (empty($datetime)) return '';
    return date('d M Y, h:i A', strtotime($datetime));
}

/**
 * Get time ago string
 * 
 * @param string $datetime MySQL datetime
 * @return string Time ago text
 */
function timeAgo($datetime) {
    if (empty($datetime)) return 'Never';
    
    $now = time();
    $time = strtotime($datetime);
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    
    return date('d M', $time);
}

/**
 * Validate CSRF token
 * 
 * @return bool Valid
 */
function validateCSRF() {
    if (!CSRF_ENABLED) return true;
    
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    return !empty($token) && hash_equals($sessionToken, $token);
}

/**
 * Generate CSRF token
 * 
 * @return string Token
 */
function generateCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Check if request is AJAX
 * 
 * @return bool
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get request body as JSON
 * 
 * @return array Decoded JSON
 */
function getJsonBody() {
    $body = file_get_contents('php://input');
    if (empty($body)) return [];
    
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}
