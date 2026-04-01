<?php

/**
 * setup_chat_tables.php - One-time chat system database initialization
 * Run this ONCE to create required tables for admin_chatbox.php
 */

require_once '../session_config.php';
require_once '../db_connection.php';

// Only admins can run this
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'sub-admin'])) {
    die('Admin access only');
}

echo "<h2>🗣️ Chat System Database Setup</h2>";
echo "<pre>";

// ── 1. CREATE chat_conversations ────────────────────────────────────────────
$tables = [
    'chat_conversations' => "
        CREATE TABLE IF NOT EXISTS `chat_conversations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `student_id` int(11) NOT NULL,
            `admin_id` int(11) NOT NULL DEFAULT 1,
            `last_message` text,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `student_id` (`student_id`),
            KEY `admin_id` (`admin_id`),
            CONSTRAINT `chat_conversations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    'chat_messages' => "
        CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `conversation_id` int(11) NOT NULL,
            `sender_id` int(11) NOT NULL,
            `sender_role` enum('student','admin') NOT NULL DEFAULT 'student',
            `receiver_id` int(11) NOT NULL,
            `message` text NOT NULL,
            `message_type` varchar(50) NOT NULL DEFAULT 'text',
            `is_read` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `conversation_id` (`conversation_id`,`sender_role`,`is_read`),
            CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    "
];

$results = [];
foreach ($tables as $table => $sql) {
    if ($conn->query($sql)) {
        $exists_before = $conn->query("SHOW TABLES LIKE '{$table}'")->num_rows;
        $results[$table] = $exists_before ? '✅ Table exists/verified' : '✅ Table created';
    } else {
        $results[$table] = '❌ FAILED: ' . $conn->error;
    }
}

// ── 2. VERIFY file_requests table (should exist from forms system) ──────────
$fr_check = $conn->query("SHOW TABLES LIKE 'file_requests'")->num_rows;
$results['file_requests'] = $fr_check ? '✅ Exists' : '⚠️  Missing (forms system incomplete)';

// ── 3. TEST DATA INSERT (optional demo conversation) ────────────────────────
if (isset($_GET['demo']) && $_GET['demo'] === '1') {
    // Insert test admin (if missing)
    $conn->query("INSERT IGNORE INTO `admin` (id, username, full_name, user_type) VALUES (1, 'system', 'System Admin', 'admin')");

    // Find/create test student
    $student_id = $conn->query("SELECT id FROM students LIMIT 1")->fetch_assoc()['id'] ?? null;
    if ($student_id) {
        $conv_id = $conn->query("SELECT id FROM chat_conversations WHERE student_id = $student_id")->fetch_assoc()['id'] ?? null;
        if (!$conv_id) {
            $conn->query("INSERT INTO chat_conversations (student_id, admin_id) VALUES ($student_id, 1)");
            $conv_id = $conn->insert_id;
        }

        // Add sample messages
        $messages = [
            ["System", "admin", "Welcome to the chat system! 👋"],
            ["Test Student", "student", "Hello admin!"]
        ];

        foreach ($messages as $msg) {
            $sender_role = $msg[1] === 'admin' ? 'admin' : 'student';
            $receiver_role = $sender_role === 'admin' ? 'student' : 'admin';
            $receiver_id = $sender_role === 'admin' ? $student_id : 1;
            $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, receiver_id, message, message_type) VALUES (?, ?, ?, ?, ?, 'text')");
            $stmt->bind_param('iisss', $conv_id, $sender_role === 'admin' ? 1 : $student_id, $sender_role, $receiver_id, $msg[2]);
            $stmt->execute();
        }

        $results['demo_data'] = '✅ Test conversation created (conv_id: ' . $conv_id . ')';
    } else {
        $results['demo_data'] = '⚠️  No students found - create students first';
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
echo "</pre>";

echo "<hr>";
if (array_sum(array_column($results, 0)) === count($results)) {
    echo '<h3 style="color:green;">🎉 ALL GOOD! Chat system ready.</h3>';
    echo '<p><a href="admin_chatbox.php" class="btn">→ Open Chatbox</a></p>';
} else {
    echo '<h3 style="color:orange;">⚠️  Some issues - review above</h3>';
}

echo '<p><small>Run <code>?demo=1</code> to add test data.</small></p>';
