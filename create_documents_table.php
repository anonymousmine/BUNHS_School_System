<?php
/**
 * Create documents table for forms management
 */

include 'db_connection.php';

echo "Creating documents table...\n";

$sql = "CREATE TABLE IF NOT EXISTS `documents` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `category` varchar(100) DEFAULT 'other',
    `file_path` varchar(500) DEFAULT NULL,
    `original_filename` varchar(255) DEFAULT NULL,
    `file_type` varchar(100) DEFAULT NULL,
    `file_size` int(11) DEFAULT 0,
    `requires_approval` tinyint(1) DEFAULT 0,
    `status` enum('active','inactive','archived') DEFAULT 'active',
    `created_by` int(11) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    echo "✅ Documents table created successfully!\n";
} else {
    echo "❌ Error creating documents table: " . $conn->error . "\n";
}

$conn->close();
?>
