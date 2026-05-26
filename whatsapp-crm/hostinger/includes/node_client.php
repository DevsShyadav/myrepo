<?php
/**
 * Node.js API Client
 * Wrapper for communicating with Hugging Face backend
 */

/**
 * Send request to Node.js backend
 * 
 * @param string $endpoint API endpoint (e.g., /api/message/send)
 * @param string $method HTTP method (GET|POST)
 * @param array $data Request body data
 * @return array Response data
 */
function nodeRequest($endpoint, $method = 'GET', $data = []) {
    $baseUrl = getSetting('node_api_url', NODE_API_URL);
    $apiKey = getSetting('node_api_key', NODE_API_KEY);
    
    if (empty($baseUrl)) {
        return ['error' => true, 'message' => 'Node API URL not configured. Set it in Settings > API Configuration.'];
    }
    
    // Clean URL - remove trailing slash, ensure proper format
    $baseUrl = rtrim($baseUrl, '/');
    $url = $baseUrl . '/' . ltrim($endpoint, '/');
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    // Only add API key header if it's configured
    if (!empty($apiKey)) {
        $headers[] = 'X-Api-Key: ' . $apiKey;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        writeLog("Node API error: {$error} for {$endpoint}", 'ERROR');
        return ['error' => true, 'message' => "Connection failed: {$error}"];
    }
    
    $decoded = json_decode($response, true);
    
    if ($httpCode >= 400) {
        $errorMsg = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
        writeLog("Node API HTTP {$httpCode}: {$errorMsg} for {$endpoint}", 'ERROR');
        return ['error' => true, 'message' => $errorMsg, 'http_code' => $httpCode];
    }
    
    return $decoded ?? ['error' => true, 'message' => 'Empty response'];
}

/**
 * Send WhatsApp message via Node.js
 * 
 * @param string $phone Phone number
 * @param string $message Message text
 * @return array Result
 */
function nodeSendMessage($phone, $message) {
    return nodeRequest('/api/message/send', 'POST', [
        'phone' => $phone,
        'message' => $message
    ]);
}

/**
 * Queue message for campaign sending
 * 
 * @param string $phone Phone number
 * @param string $message Message text
 * @param int $leadId Lead ID
 * @param string $businessName Business name
 * @return array Result
 */
function nodeQueueMessage($phone, $message, $leadId, $businessName) {
    return nodeRequest('/api/message/queue', 'POST', [
        'phone' => $phone,
        'message' => $message,
        'leadId' => $leadId,
        'businessName' => $businessName
    ]);
}

/**
 * Queue batch of messages
 * 
 * @param array $messages Array of {phone, message, leadId, businessName}
 * @return array Result
 */
function nodeQueueBatch($messages) {
    return nodeRequest('/api/message/queue-batch', 'POST', [
        'messages' => $messages
    ]);
}

/**
 * Validate WhatsApp number
 * 
 * @param string $phone Phone number
 * @return array Result with 'registered' key
 */
function nodeCheckNumber($phone) {
    return nodeRequest('/api/validation/check', 'POST', [
        'phone' => $phone
    ]);
}

/**
 * Validate batch of numbers
 * 
 * @param array $phones Array of phone numbers
 * @return array Result
 */
function nodeValidateBatch($phones) {
    return nodeRequest('/api/validation/batch', 'POST', [
        'phones' => $phones
    ]);
}

/**
 * Pause campaign
 * 
 * @return array Result
 */
function nodePauseCampaign() {
    return nodeRequest('/api/campaign/pause', 'POST');
}

/**
 * Resume campaign
 * 
 * @return array Result
 */
function nodeResumeCampaign() {
    return nodeRequest('/api/campaign/resume', 'POST');
}

/**
 * Clear campaign queue
 * 
 * @return array Result
 */
function nodeClearQueue() {
    return nodeRequest('/api/campaign/clear', 'POST');
}

/**
 * Get campaign status
 * 
 * @return array Status
 */
function nodeGetCampaignStatus() {
    return nodeRequest('/api/campaign/status', 'GET');
}

/**
 * Get queue status
 * 
 * @return array Status
 */
function nodeGetQueueStatus() {
    return nodeRequest('/api/message/queue-status', 'GET');
}

/**
 * Get Node.js health status
 * 
 * @return array Health data
 */
function nodeHealthCheck() {
    return nodeRequest('/api/health', 'GET');
}

/**
 * Get WhatsApp connection status
 * 
 * @return array Status
 */
function nodeGetWAStatus() {
    return nodeRequest('/api/health/wa-status', 'GET');
}

/**
 * Update campaign config on Node.js
 * 
 * @param array $config Config data
 * @return array Result
 */
function nodeUpdateConfig($config) {
    return nodeRequest('/api/campaign/config', 'POST', $config);
}
