<?php
/**
 * API: Get Activity Logs
 * Returns paginated activity logs with filters
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

try {
    $db = getDB();
    $pagination = getPagination();
    
    $logType = sanitize($_GET['type'] ?? '');
    $category = sanitize($_GET['category'] ?? '');
    
    $where = [];
    $params = [];
    
    if (!empty($logType)) {
        $where[] = "log_type = ?";
        $params[] = $logType;
    }
    
    if (!empty($category)) {
        $where[] = "category = ?";
        $params[] = $category;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM activity_logs {$whereClause}");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get logs
    $params[] = $pagination['limit'];
    $params[] = $pagination['offset'];
    
    $stmt = $db->prepare(
        "SELECT * FROM activity_logs {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Parse metadata JSON
    foreach ($logs as &$log) {
        if (!empty($log['metadata'])) {
            $log['metadata'] = json_decode($log['metadata'], true);
        }
    }
    
    jsonResponse([
        'logs' => $logs,
        'total' => (int)$total,
        'page' => $pagination['page'],
        'total_pages' => ceil($total / $pagination['limit'])
    ]);
    
} catch (Exception $e) {
    writeLog('get_logs error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to fetch logs', 500);
}
