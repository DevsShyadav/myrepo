<?php
/**
 * API: Start Campaign
 * Generates AI messages for valid leads and queues them for sending
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/groq.php';
require_once __DIR__ . '/../includes/node_client.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('POST method required', 405);
}

try {
    $db = getDB();
    $input = getJsonBody();
    
    $limit = min(50, max(1, (int)($input['limit'] ?? 20)));
    $filters = $input['filters'] ?? [];
    
    // Check if campaign already running
    $nodeStatus = nodeGetCampaignStatus();
    if (isset($nodeStatus['isProcessing']) && $nodeStatus['isProcessing']) {
        errorResponse('Campaign already running. Pause first before starting new batch.');
    }
    
    // Get eligible leads: valid WhatsApp, pending outreach, not already contacted
    $where = ["whatsapp_status = 'valid'", "outreach_status = 'pending'"];
    $params = [];
    
    if (!empty($filters['city'])) {
        $where[] = "city LIKE ?";
        $params[] = "%" . $filters['city'] . "%";
    }
    
    if (!empty($filters['state'])) {
        $where[] = "state LIKE ?";
        $params[] = "%" . $filters['state'] . "%";
    }
    
    if (!empty($filters['pitch_type'])) {
        $where[] = "pitch_type = ?";
        $params[] = $filters['pitch_type'];
    }
    
    $whereClause = implode(' AND ', $where);
    $params[] = $limit;
    
    $stmt = $db->prepare(
        "SELECT * FROM leads WHERE {$whereClause} ORDER BY created_at ASC LIMIT ?"
    );
    $stmt->execute($params);
    $leads = $stmt->fetchAll();
    
    if (empty($leads)) {
        errorResponse('No eligible leads found. Check if leads are validated and pending.');
    }
    
    $queued = 0;
    $messagesToQueue = [];
    $failed = 0;
    
    foreach ($leads as $lead) {
        // Generate AI message
        $aiResult = generateOutreachMessage($lead);
        $message = $aiResult['message'];
        
        if (empty($message)) {
            $failed++;
            continue;
        }
        
        // Store AI message in lead record
        $stmt = $db->prepare(
            "UPDATE leads SET ai_message = ?, ai_reasoning = ?, outreach_status = 'queued', updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$message, $aiResult['reasoning'] ?? '', $lead['id']]);
        
        // Prepare for queue
        $messagesToQueue[] = [
            'phone' => $lead['phone_number'],
            'message' => $message,
            'leadId' => $lead['id'],
            'businessName' => $lead['business_name']
        ];
        
        $queued++;
    }
    
    // Send batch to Node.js queue
    $queueResult = null;
    if (!empty($messagesToQueue)) {
        $queueResult = nodeQueueBatch($messagesToQueue);
    }
    
    // Update campaign record
    $stmt = $db->prepare(
        "UPDATE campaigns SET status = 'running', total_leads = total_leads + ?, 
         started_at = COALESCE(started_at, NOW()), updated_at = NOW() 
         WHERE id = (SELECT id FROM (SELECT id FROM campaigns ORDER BY id DESC LIMIT 1) as t)"
    );
    $stmt->execute([$queued]);
    
    logActivity('success', 'campaign', "Campaign started: {$queued} leads queued", [
        'queued' => $queued,
        'failed' => $failed,
        'total_eligible' => count($leads)
    ]);
    
    successResponse([
        'queued' => $queued,
        'failed' => $failed,
        'total_eligible' => count($leads),
        'queue_result' => $queueResult
    ], "Campaign started with {$queued} leads");
    
} catch (Exception $e) {
    writeLog('start_campaign error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to start campaign: ' . $e->getMessage(), 500);
}
