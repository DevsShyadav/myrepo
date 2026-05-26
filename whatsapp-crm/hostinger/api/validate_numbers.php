<?php
/**
 * API: Validate WhatsApp Numbers
 * Sends batch of numbers to Node.js for validation
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/node_client.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('POST method required', 405);
}

try {
    $db = getDB();
    $input = getJsonBody();
    
    $limit = min(100, max(1, (int)($input['limit'] ?? 50)));
    $leadIds = $input['lead_ids'] ?? null;
    
    // Get pending leads for validation
    if (!empty($leadIds) && is_array($leadIds)) {
        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
        $stmt = $db->prepare("SELECT id, phone_number FROM leads WHERE id IN ({$placeholders}) AND whatsapp_status = 'pending'");
        $stmt->execute($leadIds);
    } else {
        $stmt = $db->prepare("SELECT id, phone_number FROM leads WHERE whatsapp_status = 'pending' LIMIT ?");
        $stmt->execute([$limit]);
    }
    
    $leads = $stmt->fetchAll();
    
    if (empty($leads)) {
        errorResponse('No pending leads to validate');
    }
    
    // Extract phone numbers
    $phones = array_column($leads, 'phone_number');
    
    // Send to Node.js for validation
    $result = nodeValidateBatch($phones);
    
    if (isset($result['error']) && $result['error']) {
        errorResponse('Validation failed: ' . ($result['message'] ?? 'Unknown error'));
    }
    
    logActivity('info', 'validation', "Batch validation started: " . count($phones) . " numbers", [
        'count' => count($phones)
    ]);
    
    successResponse([
        'submitted' => count($phones),
        'result' => $result
    ], 'Validation started for ' . count($phones) . ' numbers');
    
} catch (Exception $e) {
    writeLog('validate_numbers error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Validation failed: ' . $e->getMessage(), 500);
}
