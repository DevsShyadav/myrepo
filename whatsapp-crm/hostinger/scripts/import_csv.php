<?php
/**
 * Script: CSV Import (CLI/Cron)
 * Can be run from command line or cron for batch imports
 * Usage: php scripts/import_csv.php /path/to/file.csv
 */

// Set execution context
define('CLI_MODE', php_sapi_name() === 'cli');

if (!CLI_MODE) {
    die('This script must be run from command line');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// Get file path from arguments
$filePath = $argv[1] ?? null;

if (!$filePath || !file_exists($filePath)) {
    echo "Usage: php import_csv.php /path/to/file.csv\n";
    echo "Error: File not found or not specified.\n";
    exit(1);
}

echo "=== WhatsApp CRM CSV Import ===\n";
echo "File: {$filePath}\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        echo "ERROR: Cannot open file\n";
        exit(1);
    }

    // Read header
    $header = fgetcsv($handle);
    if (!$header) {
        echo "ERROR: Empty or invalid CSV\n";
        fclose($handle);
        exit(1);
    }

    // Normalize headers
    $header = array_map(function($h) {
        return strtolower(trim(str_replace([' ', '-', '.'], '_', $h)));
    }, $header);

    echo "Columns found: " . implode(', ', $header) . "\n\n";

    // Column mapping
    $columnMap = [
        'business_name' => ['business_name', 'name', 'company', 'business', 'company_name'],
        'address' => ['address', 'full_address', 'location', 'addr'],
        'phone' => ['phone', 'phone_number', 'mobile', 'contact', 'tel', 'number', 'phone_no'],
        'website' => ['website', 'website_url', 'url', 'web', 'site'],
        'rating' => ['rating', 'stars', 'google_rating'],
        'reviews' => ['reviews', 'review_count', 'review', 'no_of_reviews', 'total_reviews']
    ];

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

    if (!isset($indexes['phone'])) {
        echo "ERROR: No phone column found\n";
        fclose($handle);
        exit(1);
    }

    echo "Mapped columns:\n";
    foreach ($indexes as $field => $idx) {
        echo "  {$field} => column {$idx} ({$header[$idx]})\n";
    }
    echo "\nImporting...\n";

    $db = getDB();
    $imported = 0;
    $skipped = 0;
    $duplicates = 0;
    $invalid = 0;
    $rowNum = 1;

    $insertSql = "INSERT INTO leads (business_name, address, locality, city, state, phone_number, 
                  website_url, website_status, rating, review_count, pitch_type, language_preference, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                  ON DUPLICATE KEY UPDATE updated_at = NOW()";
    $insertStmt = $db->prepare($insertSql);
    $checkStmt = $db->prepare("SELECT id FROM leads WHERE phone_number = ?");

    $db->beginTransaction();

    while (($row = fgetcsv($handle)) !== false) {
        $rowNum++;

        if (empty(array_filter($row))) continue;

        $businessName = isset($indexes['business_name']) ? trim($row[$indexes['business_name']] ?? '') : '';
        $address = isset($indexes['address']) ? trim($row[$indexes['address']] ?? '') : '';
        $phone = isset($indexes['phone']) ? trim($row[$indexes['phone']] ?? '') : '';
        $website = isset($indexes['website']) ? trim($row[$indexes['website']] ?? '') : '';
        $rating = isset($indexes['rating']) ? trim($row[$indexes['rating']] ?? '') : '';
        $reviews = isset($indexes['reviews']) ? trim($row[$indexes['reviews']] ?? '') : '0';

        $cleanedPhone = cleanPhone($phone);
        if (empty($cleanedPhone) || !isValidPhone($cleanedPhone)) {
            $invalid++;
            continue;
        }

        $checkStmt->execute([$cleanedPhone]);
        if ($checkStmt->fetch()) {
            $duplicates++;
            continue;
        }

        $parsed = parseAddress($address);
        list($websiteStatus, $pitchType) = determinePitchType($website);
        $language = detectLanguage($parsed['state'], $parsed['city']);

        $businessName = $businessName ?: 'Unknown Business';
        $ratingVal = is_numeric($rating) ? min(5.0, max(0, (float)$rating)) : null;
        $reviewCount = (int)preg_replace('/[^\d]/', '', $reviews);
        $websiteUrl = ($websiteStatus === 'has_website') ? $website : null;

        try {
            $insertStmt->execute([
                $businessName, $address, $parsed['locality'], $parsed['city'],
                $parsed['state'], $cleanedPhone, $websiteUrl, $websiteStatus,
                $ratingVal, $reviewCount, $pitchType, $language
            ]);
            $imported++;

            if ($imported % 100 === 0) {
                echo "  Imported: {$imported}...\n";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $duplicates++;
            } else {
                $skipped++;
                echo "  Row {$rowNum} error: " . $e->getMessage() . "\n";
            }
        }
    }

    fclose($handle);
    $db->commit();

    echo "\n=== Import Complete ===\n";
    echo "Total rows: " . ($rowNum - 1) . "\n";
    echo "Imported: {$imported}\n";
    echo "Duplicates: {$duplicates}\n";
    echo "Invalid phones: {$invalid}\n";
    echo "Skipped: {$skipped}\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";

    logActivity('success', 'import', "CLI import: {$imported} leads from " . basename($filePath), [
        'imported' => $imported,
        'duplicates' => $duplicates,
        'invalid' => $invalid
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
