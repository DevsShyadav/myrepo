<?php
/**
 * API: Get Lead Details
 * Returns complete lead profile with activity timeline
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
    
    // Get lead with all details
    $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        errorResponse('Lead not found', 404);
    }
    
    // Get message stats
    $stmt = $db->prepare(
        "SELECT 
            COUNT(*) as total_messages,
            SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as sent_count,
            SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as received_count,
            MIN(timestamp) as first_contact,
            MAX(timestamp) as last_contact
         FROM messages WHERE lead_id = ?"
    );
    $stmt->execute([$leadId]);
    $messageStats = $stmt->fetch();
    
    // Get activity timeline (last 20 activities for this lead)
    $stmt = $db->prepare(
        "SELECT * FROM activity_logs 
         WHERE JSON_EXTRACT(metadata, '$.lead_id') = ? 
         ORDER BY created_at DESC LIMIT 20"
    );
    $stmt->execute([$leadId]);
    $activities = $stmt->fetchAll();
    
    // Get last 5 messages preview
    $stmt = $db->prepare(
        "SELECT * FROM messages WHERE lead_id = ? ORDER BY timestamp DESC LIMIT 5"
    );
    $stmt->execute([$leadId]);
    $recentMessages = array_reverse($stmt->fetchAll());
    
    // Parse tags
    $tags = [];
    if (!empty($lead['tags'])) {
        $tags = json_decode($lead['tags'], true) ?? [];
    }
    
    jsonResponse([
        'lead' => $lead,
        'tags' => $tags,
        'message_stats' => $messageStats,
        'recent_messages' => $recentMessages,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    writeLog('get_lead_details error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to fetch lead details', 500);
}
