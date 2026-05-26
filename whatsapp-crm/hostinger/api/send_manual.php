<?php
/**
 * API: Send Manual Message
 * Sends a message manually from the chat interface
 * Used for manual follow-up after lead replies
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
    
    $leadId = (int)($input['lead_id'] ?? 0);
    $message = trim($input['message'] ?? '');
    
    if ($leadId <= 0) {
        errorResponse('Valid lead_id required');
    }
    
    if (empty($message)) {
        errorResponse('Message text required');
    }
    
    // Get lead
    $stmt = $db->prepare("SELECT id, phone_number, business_name, outreach_status FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        errorResponse('Lead not found', 404);
    }
    
    // Send via Node.js
    $result = nodeSendMessage($lead['phone_number'], $message);
    
    if (isset($result['error']) && $result['error']) {
        errorResponse('Failed to send: ' . ($result['message'] ?? 'Unknown error'), 500);
    }
    
    // Store message in database
    $stmt = $db->prepare(
        "INSERT INTO messages (lead_id, sender, message_text, wa_message_id, direction, status, timestamp) 
         VALUES (?, 'system', ?, ?, 'outbound', 'sent', NOW())"
    );
    $stmt->execute([
        $leadId,
        $message,
        $result['messageId'] ?? null
    ]);
    
    // Update lead last contacted
    $stmt = $db->prepare("UPDATE leads SET last_contacted_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->execute([$leadId]);
    
    // Log activity
    logActivity('success', 'message', "Manual message sent to {$lead['business_name']}", [
        'lead_id' => $leadId,
        'message_length' => strlen($message)
    ]);
    
    successResponse([
        'message_id' => $db->lastInsertId(),
        'wa_message_id' => $result['messageId'] ?? null,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Message sent successfully');
    
} catch (Exception $e) {
    writeLog('send_manual error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to send message', 500);
}
