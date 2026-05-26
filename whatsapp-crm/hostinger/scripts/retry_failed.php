<?php
/**
 * Script: Retry Failed Messages (Cron Job)
 * Run every 10 minutes: * /10 * * * * php /path/to/scripts/retry_failed.php
 * 
 * This script:
 * 1. Finds leads with 'failed' outreach status
 * 2. Checks retry limit
 * 3. Re-queues for sending
 */

define('CLI_MODE', true);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/node_client.php';

writeLog('Retry failed script started', 'INFO');

try {
    $db = getDB();
    
    $retryLimit = (int)getSetting('retry_limit', 3);
    $retryEnabled = getSetting('webhook_retry_enabled', true);
    
    if (!$retryEnabled) {
        writeLog('Retry disabled in settings', 'INFO');
        exit(0);
    }
    
    // Get failed leads that haven't exceeded retry limit
    // We track retries by counting failed messages for the lead
    $stmt = $db->prepare(
        "SELECT l.*, 
         (SELECT COUNT(*) FROM messages WHERE lead_id = l.id AND status = 'failed') as fail_count
         FROM leads l 
         WHERE l.outreach_status = 'failed' 
         HAVING fail_count < ?
         LIMIT 10"
    );
    $stmt->execute([$retryLimit]);
    $failedLeads = $stmt->fetchAll();
    
    if (empty($failedLeads)) {
        writeLog('No failed leads to retry', 'INFO');
        exit(0);
    }
    
    $retried = 0;
    $messagesToQueue = [];
    
    foreach ($failedLeads as $lead) {
        $message = $lead['ai_message'];
        
        if (empty($message)) {
            writeLog("No AI message for failed lead {$lead['id']}, skipping", 'WARNING');
            continue;
        }
        
        // Reset status to queued
        $stmt = $db->prepare("UPDATE leads SET outreach_status = 'queued', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$lead['id']]);
        
        $messagesToQueue[] = [
            'phone' => $lead['phone_number'],
            'message' => $message,
            'leadId' => $lead['id'],
            'businessName' => $lead['business_name']
        ];
        
        $retried++;
    }
    
    // Queue for retry
    if (!empty($messagesToQueue)) {
        $result = nodeQueueBatch($messagesToQueue);
        writeLog("Retried {$retried} failed messages. Response: " . json_encode($result), 'INFO');
    }
    
    logActivity('info', 'retry', "Retried {$retried} failed messages", ['count' => $retried]);

} catch (Exception $e) {
    writeLog('Retry script error: ' . $e->getMessage(), 'ERROR');
    exit(1);
}
