<?php
/**
 * API: Update Settings
 * Updates one or multiple settings
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
    
    if (empty($input) || !isset($input['settings'])) {
        errorResponse('Settings data required');
    }
    
    $updates = $input['settings'];
    $updated = 0;
    $errors = [];
    
    $db->beginTransaction();
    
    foreach ($updates as $key => $value) {
        // Validate key exists
        $stmt = $db->prepare("SELECT id, setting_type FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            $errors[] = "Unknown setting: {$key}";
            continue;
        }
        
        // Format value based on type
        $formattedValue = $value;
        if ($existing['setting_type'] === 'boolean') {
            $formattedValue = $value ? 'true' : 'false';
        } elseif ($existing['setting_type'] === 'json') {
            $formattedValue = is_string($value) ? $value : json_encode($value);
        } else {
            $formattedValue = (string)$value;
        }
        
        $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        $stmt->execute([$formattedValue, $key]);
        $updated++;
    }
    
    $db->commit();
    
    // Sync campaign config to Node.js if relevant settings changed
    $campaignKeys = ['min_delay', 'max_delay', 'daily_limit', 'max_consecutive', 'cooldown_duration'];
    $needsSync = false;
    foreach ($campaignKeys as $ck) {
        if (isset($updates[$ck])) {
            $needsSync = true;
            break;
        }
    }
    
    if ($needsSync) {
        $syncConfig = [];
        if (isset($updates['min_delay'])) $syncConfig['minDelay'] = (int)$updates['min_delay'];
        if (isset($updates['max_delay'])) $syncConfig['maxDelay'] = (int)$updates['max_delay'];
        if (isset($updates['daily_limit'])) $syncConfig['dailyLimit'] = (int)$updates['daily_limit'];
        if (isset($updates['max_consecutive'])) $syncConfig['maxConsecutive'] = (int)$updates['max_consecutive'];
        if (isset($updates['cooldown_duration'])) $syncConfig['cooldownDuration'] = (int)$updates['cooldown_duration'];
        
        nodeUpdateConfig($syncConfig);
    }
    
    logActivity('success', 'settings', "Updated {$updated} settings", ['keys' => array_keys($updates)]);
    
    successResponse([
        'updated' => $updated,
        'errors' => $errors
    ], "Updated {$updated} settings");
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    writeLog('update_settings error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Failed to update settings', 500);
}
