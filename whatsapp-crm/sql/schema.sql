-- ============================================================
-- WhatsApp CRM + Cold Outreach Operating System
-- Database Schema - MySQL 8.0+
-- Production-Ready | Indexed | Optimized
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+05:30";

-- ============================================================
-- TABLE: leads
-- ============================================================
CREATE TABLE IF NOT EXISTS `leads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `business_name` VARCHAR(255) NOT NULL,
    `address` TEXT DEFAULT NULL,
    `locality` VARCHAR(150) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `phone_number` VARCHAR(20) NOT NULL,
    `website_url` VARCHAR(500) DEFAULT NULL,
    `website_status` ENUM('has_website', 'no_website') NOT NULL DEFAULT 'no_website',
    `rating` DECIMAL(2,1) DEFAULT NULL,
    `review_count` INT UNSIGNED DEFAULT 0,
    `whatsapp_status` ENUM('pending', 'valid', 'invalid', 'not_on_whatsapp', 'failed') NOT NULL DEFAULT 'pending',
    `outreach_status` ENUM('pending', 'queued', 'sent', 'replied', 'stopped', 'failed') NOT NULL DEFAULT 'pending',
    `pitch_type` ENUM('type_a', 'type_b') NOT NULL DEFAULT 'type_b',
    `language_preference` VARCHAR(50) NOT NULL DEFAULT 'english',
    `tags` JSON DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `ai_message` TEXT DEFAULT NULL,
    `ai_reasoning` TEXT DEFAULT NULL,
    `last_contacted_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_phone` (`phone_number`),
    INDEX `idx_whatsapp_status` (`whatsapp_status`),
    INDEX `idx_outreach_status` (`outreach_status`),
    INDEX `idx_city_state` (`city`, `state`),
    INDEX `idx_pitch_type` (`pitch_type`),
    INDEX `idx_website_status` (`website_status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_last_contacted` (`last_contacted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: messages
-- ============================================================
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` INT UNSIGNED NOT NULL,
    `sender` ENUM('system', 'lead') NOT NULL,
    `message_text` TEXT NOT NULL,
    `wa_message_id` VARCHAR(100) DEFAULT NULL,
    `direction` ENUM('outbound', 'inbound') NOT NULL,
    `is_read` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'pending',
    `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_lead_id` (`lead_id`),
    INDEX `idx_direction` (`direction`),
    INDEX `idx_wa_message_id` (`wa_message_id`),
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_lead_direction` (`lead_id`, `direction`),
    CONSTRAINT `fk_messages_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: campaigns
-- ============================================================
CREATE TABLE IF NOT EXISTS `campaigns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT 'Default Campaign',
    `status` ENUM('idle', 'running', 'paused', 'completed', 'failed') NOT NULL DEFAULT 'idle',
    `total_leads` INT UNSIGNED NOT NULL DEFAULT 0,
    `sent_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `replied_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `daily_limit` INT UNSIGNED NOT NULL DEFAULT 50,
    `min_delay` INT UNSIGNED NOT NULL DEFAULT 120,
    `max_delay` INT UNSIGNED NOT NULL DEFAULT 300,
    `started_at` DATETIME DEFAULT NULL,
    `paused_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
    `category` VARCHAR(50) NOT NULL DEFAULT 'general',
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: activity_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `log_type` ENUM('info', 'warning', 'error', 'success') NOT NULL DEFAULT 'info',
    `category` VARCHAR(50) NOT NULL DEFAULT 'system',
    `message` TEXT NOT NULL,
    `metadata` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_log_type` (`log_type`),
    INDEX `idx_category` (`category`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_type_category` (`log_type`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
