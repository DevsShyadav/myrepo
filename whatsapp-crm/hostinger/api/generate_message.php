<?php
/**
 * API: Generate AI Message
 * Generates a personalized outreach message for a specific lead
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/groq.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('POST method required', 405);
}

try {
    $db = getDB();
    $input = getJsonBody();
    
    $leadId = (int)($input['lead_id'] ?? 0);
    $regenerate = (bool)($input['regenerate'] ?? false);
    
    if ($leadId <= 0) {
        errorResponse('Valid lead_id required');
    }
    
    // Get lead data
    $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        errorResponse('Lead not found', 404);
    }
    
    // Check if message already exists and regeneration not requested
    if (!empty($lead['ai_message']) && !$regenerate) {
        jsonResponse([
            'success' => true,
            'message' => $lead['ai_message'],
            'reasoning' => $lead['ai_reasoning'],
            'cached' => true
        ]);
    }
    
    // Generate new message
    $result = generateOutreachMessage($lead);
    
    if (!$result['success'] && empty($result['message'])) {
        errorResponse('Failed to generate message');
    }
    
    // Save to database
    $stmt = $db->prepare(
        "UPDATE leads SET ai_message = ?, ai_reasoning = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->execute([$result['message'], $result['reasoning'], $leadId]);
    
    jsonResponse([
        'success' => true,
        'message' => $result['message'],
        'reasoning' => $result['reasoning'],
        'cached' => false,
        'ai_generated' => $result['success']
    ]);
    
} catch (Exception $e) {
    writeLog('generate_message error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Message generation failed: ' . $e->getMessage(), 500);
}
