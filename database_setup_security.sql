-- Security Enhancements for Chatbox System
-- This file contains database structure updates for the security improvements

-- Create rate limiting table
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_action` (`user_id`, `action_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create audit log table for security events
CREATE TABLE IF NOT EXISTS `security_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `event_data` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes to existing chat tables for better performance
ALTER TABLE `chat_conversations` ADD INDEX IF NOT EXISTS `idx_student_admin` (`student_id`, `admin_id`);
ALTER TABLE `chat_conversations` ADD INDEX IF NOT EXISTS `idx_updated_at` (`updated_at`);
ALTER TABLE `chat_messages` ADD INDEX IF NOT EXISTS `idx_conversation_created` (`conversation_id`, `created_at`);
ALTER TABLE `chat_messages` ADD INDEX IF NOT EXISTS `idx_sender_role` (`sender_role`);

-- Clean up old rate limit entries (older than 1 hour)
-- This should be run periodically via cron job
-- DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Clean up old audit log entries (older than 30 days)
-- This should be run periodically via cron job  
-- DELETE FROM security_audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
