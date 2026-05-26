<?php
/**
 * API: Get Messages
 * Returns conversation messages for a specific lead
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

try {
    $db = getDB();
    
    $leadId = (int)($_GET['lead_id'] ?? 0);
    
    if ($leadId <= 0) {
        errorResponse('Valid lead_id required');
    }
    
    // Verify lead exists
    $stmt = $db->prepare("SELECT id, business_name, phone_number FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        errorResponse('Lead not found', 404);
    }
    
    // Get messages ordered by timestamp
    $limit = min(200, max(20, (int)($_GET['limit'] ?? 100)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    
    $stmt = $db->prepare(
        "SELECT * FROM messages 
         WHERE lead_id = ? 
         ORDER BY timestamp ASC 
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$leadId, $limit, $offset]);
    $messages = $stmt->fetchAll();
    
    // Get total message count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE lead_id = ?");
    $stmt->execute([$leadId]);
    $total = $stmt->fetch()['total'];
    
    jsonResponse([
        'lead' => $lead,
        'messages' => $messages,
        'total' => (int)$total,
        'has_more' => ($offset + $limit) < $total
    ]);
    
} catch (Exception $e) {
    writeLog('get_messages error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to fetch messages', 500);
}
