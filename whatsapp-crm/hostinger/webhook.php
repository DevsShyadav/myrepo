<?php
/**
 * Webhook Receiver
 * Receives events from Hugging Face Node.js backend
 * 
 * Events handled:
 * - message_received: Inbound message from lead
 * - message_sent: Outbound message confirmation
 * - message_failed: Message send failure
 * - validation_result: Number validation result
 * - status_update: WhatsApp status change
 * - connection_change: WhatsApp connection state
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Verify webhook authenticity
if (!verifyWebhook()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    writeLog('Webhook auth failed from: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'WARNING');
    exit;
}

// Get payload
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!$payload || !isset($payload['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$event = $payload['event'];
$data = $payload['data'] ?? [];
$timestamp = $payload['timestamp'] ?? date('Y-m-d H:i:s');

try {
    $db = getDB();
    
    switch ($event) {
        
        case 'message_received':
            handleMessageReceived($db, $data);
            break;
            
        case 'message_sent':
            handleMessageSent($db, $data);
            break;
            
        case 'message_failed':
            handleMessageFailed($db, $data);
            break;
            
        case 'validation_result':
            handleValidationResult($db, $data);
            break;
            
        case 'status_update':
        case 'connection_change':
            handleStatusUpdate($db, $event, $data);
            break;
            
        default:
            writeLog("Unknown webhook event: {$event}", 'WARNING');
            break;
    }
    
    echo json_encode(['success' => true, 'event' => $event]);
    
} catch (Exception $e) {
    writeLog("Webhook error ({$event}): " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}

// ============================================================
// EVENT HANDLERS
// ============================================================

/**
 * Handle incoming message from a lead
 */
function handleMessageReceived($db, $data) {
    $phone = $data['phone'] ?? '';
    $message = $data['message'] ?? '';
    $waMessageId = $data['wa_message_id'] ?? '';
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    
    if (empty($phone) || empty($message)) {
        writeLog('message_received: Missing phone or message', 'WARNING');
        return;
    }
    
    // Find lead by phone - try exact match first, then normalized
    $stmt = $db->prepare("SELECT id, business_name, outreach_status FROM leads WHERE phone_number = ? OR phone_number = ? OR phone_number = ?");
    $cleanPhone = preg_replace('/[^\d]/', '', $phone);
    $withoutCountry = (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') ? substr($cleanPhone, 2) : $cleanPhone;
    $withCountry = (strlen($cleanPhone) === 10) ? '91' . $cleanPhone : $cleanPhone;
    $stmt->execute([$cleanPhone, $withoutCountry, $withCountry]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        writeLog("message_received: Unknown phone {$phone}", 'INFO');
        return;
    }
    
    // Check duplicate message
    if (!empty($waMessageId)) {
        $checkStmt = $db->prepare("SELECT id FROM messages WHERE wa_message_id = ?");
        $checkStmt->execute([$waMessageId]);
        if ($checkStmt->fetch()) {
            writeLog("message_received: Duplicate message {$waMessageId}", 'INFO');
            return;
        }
    }
    
    // Store inbound message
    $stmt = $db->prepare(
        "INSERT INTO messages (lead_id, sender, message_text, wa_message_id, direction, is_read, status, timestamp) 
         VALUES (?, 'lead', ?, ?, 'inbound', 0, 'delivered', ?)"
    );
    $stmt->execute([$lead['id'], $message, $waMessageId, $timestamp]);
    
    // CRITICAL: Stop automation for this lead - they replied
    if (in_array($lead['outreach_status'], ['sent', 'queued'])) {
        $stmt = $db->prepare(
            "UPDATE leads SET outreach_status = 'replied', updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$lead['id']]);
        
        // Update campaign replied count
        $stmt = $db->prepare(
            "UPDATE campaigns SET replied_count = replied_count + 1, updated_at = NOW() 
             WHERE id = (SELECT id FROM (SELECT id FROM campaigns WHERE status IN ('running', 'paused') ORDER BY id DESC LIMIT 1) as t)"
        );
        $stmt->execute();
        
        logActivity('success', 'reply', "Lead replied: {$lead['business_name']}", [
            'lead_id' => $lead['id'],
            'phone' => $phone
        ]);
    }
    
    writeLog("Inbound message stored for lead {$lead['id']} ({$lead['business_name']})", 'INFO');
}

/**
 * Handle outbound message confirmation
 */
function handleMessageSent($db, $data) {
    $phone = $data['phone'] ?? '';
    $leadId = $data['lead_id'] ?? null;
    $waMessageId = $data['wa_message_id'] ?? '';
    $message = $data['message'] ?? '';
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    
    if (empty($phone)) return;
    
    // Find lead with flexible phone matching
    if (!$leadId) {
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        $withoutCountry = (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') ? substr($cleanPhone, 2) : $cleanPhone;
        $withCountry = (strlen($cleanPhone) === 10) ? '91' . $cleanPhone : $cleanPhone;
        $stmt = $db->prepare("SELECT id FROM leads WHERE phone_number = ? OR phone_number = ? OR phone_number = ?");
        $stmt->execute([$cleanPhone, $withoutCountry, $withCountry]);
        $lead = $stmt->fetch();
        $leadId = $lead ? $lead['id'] : null;
    }
    
    if (!$leadId) {
        writeLog("message_sent: Lead not found for {$phone}", 'WARNING');
        return;
    }
    
    // Check duplicate
    if (!empty($waMessageId)) {
        $checkStmt = $db->prepare("SELECT id FROM messages WHERE wa_message_id = ?");
        $checkStmt->execute([$waMessageId]);
        if ($checkStmt->fetch()) {
            // Update existing message status
            $stmt = $db->prepare("UPDATE messages SET status = 'sent' WHERE wa_message_id = ?");
            $stmt->execute([$waMessageId]);
            return;
        }
    }
    
    // Store outbound message
    if (!empty($message)) {
        $stmt = $db->prepare(
            "INSERT INTO messages (lead_id, sender, message_text, wa_message_id, direction, is_read, status, timestamp) 
             VALUES (?, 'system', ?, ?, 'outbound', 1, 'sent', ?)"
        );
        $stmt->execute([$leadId, $message, $waMessageId, $timestamp]);
    }
    
    // Update lead status
    $stmt = $db->prepare(
        "UPDATE leads SET outreach_status = 'sent', last_contacted_at = ?, updated_at = NOW() WHERE id = ? AND outreach_status IN ('pending', 'queued')"
    );
    $stmt->execute([$timestamp, $leadId]);
    
    // Update campaign sent count
    $stmt = $db->prepare(
        "UPDATE campaigns SET sent_count = sent_count + 1, updated_at = NOW() 
         WHERE id = (SELECT id FROM (SELECT id FROM campaigns WHERE status IN ('running', 'paused') ORDER BY id DESC LIMIT 1) as t)"
    );
    $stmt->execute();
    
    writeLog("Outbound confirmed for lead {$leadId}: {$waMessageId}", 'INFO');
}

/**
 * Handle message send failure
 */
function handleMessageFailed($db, $data) {
    $phone = $data['phone'] ?? '';
    $leadId = $data['lead_id'] ?? null;
    $error = $data['error'] ?? 'Unknown error';
    
    if (!$leadId && !empty($phone)) {
        $stmt = $db->prepare("SELECT id FROM leads WHERE phone_number = ?");
        $stmt->execute([$phone]);
        $lead = $stmt->fetch();
        $leadId = $lead ? $lead['id'] : null;
    }
    
    if ($leadId) {
        $stmt = $db->prepare(
            "UPDATE leads SET outreach_status = 'failed', updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$leadId]);
        
        // Update campaign failed count
        $stmt = $db->prepare(
            "UPDATE campaigns SET failed_count = failed_count + 1, updated_at = NOW() 
             WHERE id = (SELECT id FROM (SELECT id FROM campaigns WHERE status IN ('running', 'paused') ORDER BY id DESC LIMIT 1) as t)"
        );
        $stmt->execute();
        
        // Store failed message record
        $stmt = $db->prepare(
            "INSERT INTO messages (lead_id, sender, message_text, direction, status, timestamp) 
             VALUES (?, 'system', ?, 'outbound', 'failed', NOW())"
        );
        $stmt->execute([$leadId, "[FAILED] Error: {$error}"]);
    }
    
    logActivity('error', 'message', "Message failed for lead {$leadId}: {$error}", [
        'lead_id' => $leadId,
        'phone' => $phone,
        'error' => $error
    ]);
}

/**
 * Handle WhatsApp number validation result
 */
function handleValidationResult($db, $data) {
    $phone = $data['phone'] ?? '';
    $status = $data['status'] ?? 'failed';
    
    if (empty($phone)) return;
    
    // Valid statuses
    $validStatuses = ['valid', 'invalid', 'not_on_whatsapp', 'failed'];
    if (!in_array($status, $validStatuses)) {
        $status = 'failed';
    }
    
    // Update lead
    $stmt = $db->prepare(
        "UPDATE leads SET whatsapp_status = ?, updated_at = NOW() WHERE phone_number = ?"
    );
    $stmt->execute([$status, $phone]);
    
    writeLog("Validation result: {$phone} -> {$status}", 'INFO');
}

/**
 * Handle WhatsApp status/connection changes
 */
function handleStatusUpdate($db, $event, $data) {
    $status = $data['status'] ?? $data['reason'] ?? 'unknown';
    
    logActivity('info', 'system', "WhatsApp {$event}: {$status}", $data);
    writeLog("WhatsApp {$event}: " . json_encode($data), 'INFO');
}
