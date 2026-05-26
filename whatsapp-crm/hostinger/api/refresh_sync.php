<?php
/**
 * API: Refresh Sync
 * Syncs status with Node.js backend and returns current state
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/node_client.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

try {
    // Get health from Node.js
    $health = nodeHealthCheck();
    
    // Get campaign status
    $campaignStatus = nodeGetCampaignStatus();
    
    $response = [
        'node_connected' => !isset($health['error']),
        'health' => $health,
        'campaign' => $campaignStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // If we got WhatsApp status, include it
    if (isset($health['whatsapp'])) {
        $response['whatsapp'] = $health['whatsapp'];
    }
    
    jsonResponse($response);
    
} catch (Exception $e) {
    writeLog('refresh_sync error: ' . $e->getMessage(), 'ERROR');
    jsonResponse([
        'node_connected' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
