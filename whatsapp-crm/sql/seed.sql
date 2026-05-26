-- ============================================================
-- WhatsApp CRM - Seed Data
-- Default settings for fresh installation
-- ============================================================

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `category`, `description`) VALUES
('groq_api_key', '', 'string', 'api', 'Groq API Key for AI message generation'),
('groq_model', 'llama-3.1-70b-versatile', 'string', 'api', 'Groq model to use'),
('node_api_url', '', 'string', 'api', 'Hugging Face Spaces Node.js backend URL'),
('node_api_key', '', 'string', 'api', 'API key for Node.js backend authentication'),
('webhook_secret', '', 'string', 'api', 'Shared secret for webhook verification'),
('socket_url', '', 'string', 'api', 'Socket.io server URL for realtime'),
('min_delay', '120', 'number', 'campaign', 'Minimum delay between messages in seconds'),
('max_delay', '300', 'number', 'campaign', 'Maximum delay between messages in seconds'),
('daily_limit', '50', 'number', 'campaign', 'Maximum messages per day'),
('max_consecutive', '10', 'number', 'campaign', 'Max consecutive messages before cooldown'),
('cooldown_duration', '900', 'number', 'campaign', 'Cooldown duration in seconds after max consecutive'),
('business_hours_start', '9', 'number', 'campaign', 'Campaign start hour (24h format)'),
('business_hours_end', '19', 'number', 'campaign', 'Campaign end hour (24h format)'),
('retry_limit', '3', 'number', 'campaign', 'Maximum retry attempts for failed messages'),
('app_name', 'WhatsApp CRM', 'string', 'general', 'Application name'),
('notification_sound', 'true', 'boolean', 'ui', 'Enable notification sounds'),
('auto_refresh_interval', '30', 'number', 'ui', 'Auto refresh interval in seconds'),
('logging_enabled', 'true', 'boolean', 'system', 'Enable activity logging'),
('webhook_retry_enabled', 'true', 'boolean', 'system', 'Enable webhook retry on failure'),
('duplicate_check_enabled', 'true', 'boolean', 'system', 'Enable duplicate message prevention');

-- Insert default campaign
INSERT INTO `campaigns` (`name`, `status`, `daily_limit`, `min_delay`, `max_delay`) VALUES
('Default Campaign', 'idle', 50, 120, 300);
