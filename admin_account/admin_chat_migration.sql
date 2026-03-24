-- Admin Chat System Migration
-- Adds support for admin-to-admin and sub-admin messaging

-- Create admin conversations table
CREATE TABLE IF NOT EXISTS admin_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    participant_a_id INT NOT NULL,
    participant_b_id INT NOT NULL,
    last_message TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_participants (participant_a_id, participant_b_id),
    INDEX idx_updated (updated_at),
    INDEX idx_participant_a (participant_a_id),
    INDEX idx_participant_b (participant_b_id)
);

-- Create admin messages table
CREATE TABLE IF NOT EXISTS admin_chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'file', 'system') DEFAULT 'text',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_receiver_read (receiver_id, is_read),
    INDEX idx_created (created_at),
    FOREIGN KEY (conversation_id) REFERENCES admin_conversations(id) ON DELETE CASCADE
);

-- Create admin chat settings table for preferences
CREATE TABLE IF NOT EXISTS admin_chat_settings (
    user_id INT PRIMARY KEY,
    enable_notifications BOOLEAN DEFAULT TRUE,
    auto_mark_read BOOLEAN DEFAULT FALSE,
    sound_enabled BOOLEAN DEFAULT TRUE,
    theme VARCHAR(20) DEFAULT 'default',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin(id) ON DELETE CASCADE
);

-- Insert default settings for existing admins
INSERT IGNORE INTO admin_chat_settings (user_id) 
SELECT id FROM admin WHERE id NOT IN (SELECT user_id FROM admin_chat_settings);

-- Add chat status to admin table (for online/offline status)
ALTER TABLE admin 
ADD COLUMN IF NOT EXISTS chat_status ENUM('online', 'offline', 'away', 'busy') DEFAULT 'offline',
ADD COLUMN IF NOT EXISTS last_chat_activity TIMESTAMP NULL;

-- Add chat status to sub_admin table
ALTER TABLE sub_admin 
ADD COLUMN IF NOT EXISTS chat_status ENUM('online', 'offline', 'away', 'busy') DEFAULT 'offline',
ADD COLUMN IF NOT EXISTS last_chat_activity TIMESTAMP NULL;
