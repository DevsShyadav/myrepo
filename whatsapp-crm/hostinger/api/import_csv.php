<?php
/**
 * API: Import CSV
 * Handles CSV file upload, parsing, and lead import
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
    // Check file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        ];
        $errorCode = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        errorResponse($errors[$errorCode] ?? 'Upload failed');
    }
    
    $file = $_FILES['csv_file'];
    
    // Validate file
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        errorResponse('Only CSV files are allowed');
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        errorResponse('File too large. Maximum 5MB allowed');
    }
    
    // Read CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        errorResponse('Failed to read file');
    }
    
    // Get header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        errorResponse('Empty or invalid CSV file');
    }
    
    // Normalize header names
    $header = array_map(function($h) {
        return strtolower(trim(str_replace([' ', '-', '.'], '_', $h)));
    }, $header);
    
    // Map common column names
    $columnMap = [
        'business_name' => ['business_name', 'name', 'company', 'business', 'company_name'],
        'address' => ['address', 'full_address', 'location', 'addr'],
        'phone' => ['phone', 'phone_number', 'mobile', 'contact', 'tel', 'number', 'phone_no'],
        'website' => ['website', 'website_url', 'url', 'web', 'site'],
        'rating' => ['rating', 'stars', 'google_rating'],
        'reviews' => ['reviews', 'review_count', 'review', 'no_of_reviews', 'total_reviews'],
        'status' => ['status', 'business_status']
    ];
    
    // Find column indexes
    $indexes = [];
    foreach ($columnMap as $field => $possibleNames) {
        foreach ($possibleNames as $name) {
            $idx = array_search($name, $header);
            if ($idx !== false) {
                $indexes[$field] = $idx;
                break;
            }
        }
    }
    
    // Phone column is required
    if (!isset($indexes['phone'])) {
        fclose($handle);
        errorResponse('CSV must contain a phone/mobile/contact column');
    }
    
    $db = getDB();
    $imported = 0;
    $skipped = 0;
    $duplicates = 0;
    $invalid = 0;
    $errors = [];
    $row_num = 1;
    
    // Prepare insert statement
    $insertSql = "INSERT INTO leads (business_name, address, locality, city, state, phone_number, 
                  website_url, website_status, rating, review_count, pitch_type, language_preference, 
                  whatsapp_status, outreach_status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
                  ON DUPLICATE KEY UPDATE updated_at = NOW()";
    $insertStmt = $db->prepare($insertSql);
    
    $db->beginTransaction();
    
    while (($row = fgetcsv($handle)) !== false) {
        $row_num++;
        
        // Skip empty rows
        if (empty(array_filter($row))) continue;
        
        // Extract data
        $businessName = isset($indexes['business_name']) ? trim($row[$indexes['business_name']] ?? '') : '';
        $address = isset($indexes['address']) ? trim($row[$indexes['address']] ?? '') : '';
        $phone = isset($indexes['phone']) ? trim($row[$indexes['phone']] ?? '') : '';
        $website = isset($indexes['website']) ? trim($row[$indexes['website']] ?? '') : '';
        $rating = isset($indexes['rating']) ? trim($row[$indexes['rating']] ?? '') : '';
        $reviews = isset($indexes['reviews']) ? trim($row[$indexes['reviews']] ?? '') : '0';
        
        // Validate phone
        $cleanedPhone = cleanPhone($phone);
        if (empty($cleanedPhone) || !isValidPhone($cleanedPhone)) {
            $invalid++;
            continue;
        }
        
        // Check duplicate
        $checkStmt = $db->prepare("SELECT id FROM leads WHERE phone_number = ?");
        $checkStmt->execute([$cleanedPhone]);
        if ($checkStmt->fetch()) {
            $duplicates++;
            continue;
        }
        
        // Parse address
        $parsed = parseAddress($address);
        
        // Determine pitch type
        list($websiteStatus, $pitchType) = determinePitchType($website);
        
        // Detect language
        $language = detectLanguage($parsed['state'], $parsed['city']);
        
        // Clean data
        $businessName = sanitize($businessName ?: 'Unknown Business');
        $rating = is_numeric($rating) ? min(5.0, max(0, (float)$rating)) : null;
        $reviewCount = (int)preg_replace('/[^\d]/', '', $reviews);
        $websiteUrl = ($websiteStatus === 'has_website') ? $website : null;
        
        try {
            $insertStmt->execute([
                $businessName,
                $address,
                $parsed['locality'],
                $parsed['city'],
                $parsed['state'],
                $cleanedPhone,
                $websiteUrl,
                $websiteStatus,
                $rating,
                $reviewCount,
                $pitchType,
                $language
            ]);
            $imported++;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $duplicates++;
            } else {
                $skipped++;
                $errors[] = "Row {$row_num}: " . $e->getMessage();
            }
        }
    }
    
    fclose($handle);
    $db->commit();
    
    // Save uploaded file
    $savedName = 'import_' . date('Y-m-d_His') . '.csv';
    move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $savedName);
    
    logActivity('success', 'import', "CSV imported: {$imported} leads", [
        'file' => $file['name'],
        'imported' => $imported,
        'duplicates' => $duplicates,
        'invalid' => $invalid,
        'skipped' => $skipped
    ]);
    
    successResponse([
        'imported' => $imported,
        'duplicates' => $duplicates,
        'invalid' => $invalid,
        'skipped' => $skipped,
        'total_rows' => $row_num - 1,
        'errors' => array_slice($errors, 0, 10)
    ], "Successfully imported {$imported} leads");
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    writeLog('import_csv error: ' . $e->getMessage(), 'ERROR');
    errorResponse('Import failed: ' . $e->getMessage(), 500);
}
