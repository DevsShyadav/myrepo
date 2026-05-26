<?php
/**
 * API: Get Settings
 * Returns all application settings grouped by category
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

setSecurityHeaders();

try {
    $db = getDB();
    
    $category = sanitize($_GET['category'] ?? '');
    
    if (!empty($category)) {
        $stmt = $db->prepare("SELECT * FROM settings WHERE category = ? ORDER BY id");
        $stmt->execute([$category]);
    } else {
        $stmt = $db->query("SELECT * FROM settings ORDER BY category, id");
    }
    
    $settings = $stmt->fetchAll();
    
    // Group by category
    $grouped = [];
    foreach ($settings as $setting) {
        $cat = $setting['category'];
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        
        // Cast values based on type
        $value = $setting['setting_value'];
        switch ($setting['setting_type']) {
            case 'number':
                $value = (int)$value;
                break;
            case 'boolean':
                $value = $value === 'true' || $value === '1';
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
        }
        
        $grouped[$cat][] = [
            'id' => (int)$setting['id'],
            'key' => $setting['setting_key'],
            'value' => $value,
            'type' => $setting['setting_type'],
            'category' => $cat,
            'description' => $setting['description'],
            'updated_at' => $setting['updated_at']
        ];
    }
    
    jsonResponse([
        'settings' => $grouped,
        'categories' => array_keys($grouped)
    ]);
    
} catch (Exception $e) {
    writeLog('settings error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to fetch settings', 500);
}
