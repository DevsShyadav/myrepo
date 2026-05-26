<?php
/**
 * API: Mark Messages as Read
 * Marks all unread messages for a lead as read
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('POST method required', 405);
}

try {
    $db = getDB();
    $input = getJsonBody();
    
    $leadId = (int)($input['lead_id'] ?? 0);
    
    if ($leadId <= 0) {
        errorResponse('Valid lead_id required');
    }
    
    // Mark all unread inbound messages as read
    $stmt = $db->prepare(
        "UPDATE messages SET is_read = 1 WHERE lead_id = ? AND direction = 'inbound' AND is_read = 0"
    );
    $stmt->execute([$leadId]);
    $updated = $stmt->rowCount();
    
    successResponse([
        'updated' => $updated,
        'lead_id' => $leadId
    ], "Marked {$updated} messages as read");
    
} catch (Exception $e) {
    writeLog('mark_read error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to mark as read', 500);
}
