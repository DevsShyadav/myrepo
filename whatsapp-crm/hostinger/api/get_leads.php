<?php
/**
 * API: Get Leads
 * Returns paginated, filterable, searchable leads list
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

try {
    $db = getDB();
    $pagination = getPagination();
    
    // Filters
    $search = sanitize($_GET['search'] ?? '');
    $whatsappStatus = sanitize($_GET['whatsapp_status'] ?? '');
    $outreachStatus = sanitize($_GET['outreach_status'] ?? '');
    $pitchType = sanitize($_GET['pitch_type'] ?? '');
    $city = sanitize($_GET['city'] ?? '');
    $state = sanitize($_GET['state'] ?? '');
    $hasUnread = $_GET['has_unread'] ?? '';
    $sortBy = sanitize($_GET['sort_by'] ?? 'created_at');
    $sortOrder = strtoupper(sanitize($_GET['sort_order'] ?? 'DESC'));
    
    // Validate sort order
    if (!in_array($sortOrder, ['ASC', 'DESC'])) $sortOrder = 'DESC';
    
    // Allowed sort columns
    $allowedSorts = ['created_at', 'business_name', 'last_contacted_at', 'rating', 'review_count'];
    if (!in_array($sortBy, $allowedSorts)) $sortBy = 'created_at';
    
    // Build query
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(business_name LIKE ? OR phone_number LIKE ? OR city LIKE ? OR locality LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($whatsappStatus)) {
        $where[] = "whatsapp_status = ?";
        $params[] = $whatsappStatus;
    }
    
    if (!empty($outreachStatus)) {
        $where[] = "outreach_status = ?";
        $params[] = $outreachStatus;
    }
    
    if (!empty($pitchType)) {
        $where[] = "pitch_type = ?";
        $params[] = $pitchType;
    }
    
    if (!empty($city)) {
        $where[] = "city LIKE ?";
        $params[] = "%{$city}%";
    }
    
    if (!empty($state)) {
        $where[] = "state LIKE ?";
        $params[] = "%{$state}%";
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM leads {$whereClause}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get leads with last message preview
    $sql = "SELECT l.*, 
            (SELECT message_text FROM messages WHERE lead_id = l.id ORDER BY timestamp DESC LIMIT 1) as last_message,
            (SELECT timestamp FROM messages WHERE lead_id = l.id ORDER BY timestamp DESC LIMIT 1) as last_message_time,
            (SELECT COUNT(*) FROM messages WHERE lead_id = l.id AND direction = 'inbound' AND is_read = 0) as unread_count
            FROM leads l 
            {$whereClause} 
            ORDER BY {$sortBy} {$sortOrder} 
            LIMIT ? OFFSET ?";
    
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();
    
    jsonResponse([
        'leads' => $leads,
        'total' => (int)$total,
        'page' => $pagination['page'],
        'limit' => $pagination['limit'],
        'total_pages' => ceil($total / $pagination['limit'])
    ]);
    
} catch (Exception $e) {
    writeLog('get_leads error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to fetch leads', 500);
}
