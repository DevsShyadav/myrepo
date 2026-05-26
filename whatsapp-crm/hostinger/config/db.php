<?php
/**
 * Database Configuration
 * PDO MySQL Connection
 */

// Database credentials - Update after deployment
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'whatsapp_crm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection
 * Uses singleton pattern for connection reuse
 * 
 * @return PDO
 * @throws PDOException
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_PERSISTENT => false
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                throw $e;
            }
            error_log('Database connection failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    
    return $pdo;
}

/**
 * Get a setting value from database
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value
 */
function getSetting($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        
        if (!$row) return $default;
        
        switch ($row['setting_type']) {
            case 'number':
                return (int) $row['setting_value'];
            case 'boolean':
                return $row['setting_value'] === 'true' || $row['setting_value'] === '1';
            case 'json':
                return json_decode($row['setting_value'], true);
            default:
                return $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log('getSetting error: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Update a setting value in database
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success
 */
function updateSetting($key, $value) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        return $stmt->execute([$value, $key]);
    } catch (Exception $e) {
        error_log('updateSetting error: ' . $e->getMessage());
        return false;
    }
}
