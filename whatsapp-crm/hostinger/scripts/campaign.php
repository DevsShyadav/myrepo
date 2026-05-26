<?php
/**
 * Script: Campaign Processor (Cron Job)
 * Run every 3 minutes via cron: * /3 * * * * php /path/to/scripts/campaign.php
 * 
 * This script:
 * 1. Checks if campaign is running
 * 2. Checks business hours
 * 3. Checks daily limit
 * 4. Picks next pending lead
 * 5. Generates AI message (if not already generated)
 * 6. Queues message via Node.js
 * 
 * This is a BACKUP mechanism. Primary sending is via Node.js queue.
 * This cron ensures leads get queued even if dashboard isn't open.
 */

define('CLI_MODE', true);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/groq.php';
require_once __DIR__ . '/../includes/node_client.php';

// Log start
writeLog('Campaign cron started', 'INFO');

try {
    $db = getDB();
    
    // Get active campaign
    $stmt = $db->query("SELECT * FROM campaigns WHERE status = 'running' ORDER BY id DESC LIMIT 1");
    $campaign = $stmt->fetch();
    
    if (!$campaign) {
        writeLog('No running campaign found', 'INFO');
        exit(0);
    }
    
    // Check business hours
    $currentHour = (int)date('G');
    $startHour = (int)getSetting('business_hours_start', BUSINESS_HOURS_START);
    $endHour = (int)getSetting('business_hours_end', BUSINESS_HOURS_END);
    
    if ($currentHour < $startHour || $currentHour >= $endHour) {
        writeLog("Outside business hours ({$startHour}:00 - {$endHour}:00). Current: {$currentHour}:00", 'INFO');
        exit(0);
    }
    
    // Check daily limit
    $stmt = $db->query(
        "SELECT COUNT(*) as sent FROM messages WHERE direction = 'outbound' AND DATE(timestamp) = CURDATE()"
    );
    $sentToday = (int)$stmt->fetch()['sent'];
    $dailyLimit = (int)getSetting('daily_limit', DEFAULT_DAILY_LIMIT);
    
    if ($sentToday >= $dailyLimit) {
        writeLog("Daily limit reached ({$sentToday}/{$dailyLimit})", 'INFO');
        exit(0);
    }
    
    // Check Node.js queue status
    $queueStatus = nodeGetQueueStatus();
    if (isset($queueStatus['queueLength']) && $queueStatus['queueLength'] > 5) {
        writeLog("Queue already has {$queueStatus['queueLength']} items. Skipping.", 'INFO');
        exit(0);
    }
    
    // Get next batch of eligible leads (max 5 per cron run)
    $batchSize = min(5, $dailyLimit - $sentToday);
    
    $stmt = $db->prepare(
        "SELECT * FROM leads 
         WHERE whatsapp_status = 'valid' 
         AND outreach_status = 'pending' 
         ORDER BY created_at ASC 
         LIMIT ?"
    );
    $stmt->execute([$batchSize]);
    $leads = $stmt->fetchAll();
    
    if (empty($leads)) {
        writeLog('No eligible leads for campaign', 'INFO');
        
        // Check if campaign is complete
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM leads WHERE outreach_status = 'pending' AND whatsapp_status = 'valid'");
        $remaining = (int)$stmt->fetch()['cnt'];
        
        if ($remaining === 0) {
            $stmt = $db->prepare("UPDATE campaigns SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$campaign['id']]);
            writeLog('Campaign completed - no more eligible leads', 'INFO');
            logActivity('success', 'campaign', 'Campaign completed');
        }
        
        exit(0);
    }
    
    $queued = 0;
    $messagesToQueue = [];
    
    foreach ($leads as $lead) {
        // Generate AI message if not exists
        if (empty($lead['ai_message'])) {
            $aiResult = generateOutreachMessage($lead);
            $message = $aiResult['message'];
            
            // Save to DB
            $stmt = $db->prepare("UPDATE leads SET ai_message = ?, ai_reasoning = ? WHERE id = ?");
            $stmt->execute([$message, $aiResult['reasoning'] ?? '', $lead['id']]);
        } else {
            $message = $lead['ai_message'];
        }
        
        if (empty($message)) {
            writeLog("Failed to generate message for lead {$lead['id']}", 'ERROR');
            continue;
        }
        
        // Mark as queued
        $stmt = $db->prepare("UPDATE leads SET outreach_status = 'queued', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$lead['id']]);
        
        $messagesToQueue[] = [
            'phone' => $lead['phone_number'],
            'message' => $message,
            'leadId' => $lead['id'],
            'businessName' => $lead['business_name']
        ];
        
        $queued++;
    }
    
    // Send batch to Node.js
    if (!empty($messagesToQueue)) {
        $result = nodeQueueBatch($messagesToQueue);
        writeLog("Queued {$queued} messages via cron. Node response: " . json_encode($result), 'INFO');
        
        // Update campaign counts
        $stmt = $db->prepare("UPDATE campaigns SET total_leads = total_leads + ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$queued, $campaign['id']]);
    }
    
    logActivity('info', 'campaign', "Cron: Queued {$queued} messages", ['batch_size' => $queued]);

} catch (Exception $e) {
    writeLog('Campaign cron error: ' . $e->getMessage(), 'ERROR');
    logActivity('error', 'campaign', 'Cron error: ' . $e->getMessage());
    exit(1);
}
