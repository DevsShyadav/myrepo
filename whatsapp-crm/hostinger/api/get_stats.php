<?php
/**
 * API: Get Dashboard Statistics
 * Returns KPI cards data, campaign stats, and overview metrics
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

try {
    $db = getDB();
    
    // Total leads
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads");
    $totalLeads = $stmt->fetch()['total'];
    
    // WhatsApp valid leads
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE whatsapp_status = 'valid'");
    $validLeads = $stmt->fetch()['total'];
    
    // Invalid/not on WhatsApp
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE whatsapp_status IN ('invalid', 'not_on_whatsapp')");
    $invalidLeads = $stmt->fetch()['total'];
    
    // Pending validation
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE whatsapp_status = 'pending'");
    $pendingValidation = $stmt->fetch()['total'];
    
    // Messages sent today
    $stmt = $db->query("SELECT COUNT(*) as total FROM messages WHERE direction = 'outbound' AND DATE(timestamp) = CURDATE()");
    $sentToday = $stmt->fetch()['total'];
    
    // Total messages sent
    $stmt = $db->query("SELECT COUNT(*) as total FROM messages WHERE direction = 'outbound'");
    $totalSent = $stmt->fetch()['total'];
    
    // Replies received
    $stmt = $db->query("SELECT COUNT(*) as total FROM messages WHERE direction = 'inbound'");
    $totalReplies = $stmt->fetch()['total'];
    
    // Replied leads
    $stmt = $db->query("SELECT COUNT(*) as total FROM leads WHERE outreach_status = 'replied'");
    $repliedLeads = $stmt->fetch()['total'];
    
    // Outreach statuses breakdown
    $stmt = $db->query("SELECT outreach_status, COUNT(*) as count FROM leads GROUP BY outreach_status");
    $outreachBreakdown = [];
    while ($row = $stmt->fetch()) {
        $outreachBreakdown[$row['outreach_status']] = (int)$row['count'];
    }
    
    // Leads by pitch type
    $stmt = $db->query("SELECT pitch_type, COUNT(*) as count FROM leads GROUP BY pitch_type");
    $pitchBreakdown = [];
    while ($row = $stmt->fetch()) {
        $pitchBreakdown[$row['pitch_type']] = (int)$row['count'];
    }
    
    // Unread messages count
    $stmt = $db->query("SELECT COUNT(*) as total FROM messages WHERE direction = 'inbound' AND is_read = 0");
    $unreadCount = $stmt->fetch()['total'];
    
    // Reply rate
    $replyRate = $totalSent > 0 ? round(($totalReplies / $totalSent) * 100, 1) : 0;
    
    // Active campaign status
    $stmt = $db->query("SELECT * FROM campaigns ORDER BY id DESC LIMIT 1");
    $campaign = $stmt->fetch();
    
    // Recent activity (last 5)
    $stmt = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $recentActivity = $stmt->fetchAll();
    
    jsonResponse([
        'total_leads' => (int)$totalLeads,
        'valid_leads' => (int)$validLeads,
        'invalid_leads' => (int)$invalidLeads,
        'pending_validation' => (int)$pendingValidation,
        'sent_today' => (int)$sentToday,
        'total_sent' => (int)$totalSent,
        'total_replies' => (int)$totalReplies,
        'replied_leads' => (int)$repliedLeads,
        'unread_count' => (int)$unreadCount,
        'reply_rate' => $replyRate,
        'outreach_breakdown' => $outreachBreakdown,
        'pitch_breakdown' => $pitchBreakdown,
        'campaign' => $campaign,
        'recent_activity' => $recentActivity
    ]);
    
} catch (Exception $e) {
    writeLog('get_stats error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to fetch statistics', 500);
}
