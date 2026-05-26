<?php
/**
 * API: Pause/Resume Campaign
 * Controls campaign execution state
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
    
    $action = sanitize($input['action'] ?? '');
    
    if (!in_array($action, ['pause', 'resume', 'clear'])) {
        errorResponse('Valid action required: pause, resume, or clear');
    }
    
    $result = null;
    
    switch ($action) {
        case 'pause':
            $result = nodePauseCampaign();
            $stmt = $db->prepare(
                "UPDATE campaigns SET status = 'paused', paused_at = NOW(), updated_at = NOW() 
                 WHERE id = (SELECT id FROM (SELECT id FROM campaigns ORDER BY id DESC LIMIT 1) as t)"
            );
            $stmt->execute();
            logActivity('info', 'campaign', 'Campaign paused');
            break;
            
        case 'resume':
            $result = nodeResumeCampaign();
            $stmt = $db->prepare(
                "UPDATE campaigns SET status = 'running', paused_at = NULL, updated_at = NOW() 
                 WHERE id = (SELECT id FROM (SELECT id FROM campaigns ORDER BY id DESC LIMIT 1) as t)"
            );
            $stmt->execute();
            logActivity('info', 'campaign', 'Campaign resumed');
            break;
            
        case 'clear':
            $result = nodeClearQueue();
            // Reset queued leads back to pending
            $stmt = $db->prepare("UPDATE leads SET outreach_status = 'pending' WHERE outreach_status = 'queued'");
            $stmt->execute();
            $resetCount = $stmt->rowCount();
            
            $stmt = $db->prepare(
                "UPDATE campaigns SET status = 'idle', updated_at = NOW() 
                 WHERE id = (SELECT id FROM (SELECT id FROM campaigns ORDER BY id DESC LIMIT 1) as t)"
            );
            $stmt->execute();
            logActivity('warning', 'campaign', "Campaign cleared. {$resetCount} leads reset to pending");
            break;
    }
    
    successResponse([
        'action' => $action,
        'result' => $result
    ], "Campaign {$action} successful");
    
} catch (Exception $e) {
    writeLog('pause_campaign error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Campaign action failed: ' . $e->getMessage(), 500);
}
