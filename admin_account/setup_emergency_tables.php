<?php

/**
 * Emergency System Database Setup
 * Run this file once to create the necessary tables for the emergency notification system
 */

include 'db_connection.php';

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sqls = [];

// 1. Create emergency_contacts table
$sqls[] = "CREATE TABLE IF NOT EXISTS emergency_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_type ENUM('student_parent', 'teacher', 'staff', 'admin') DEFAULT 'student_parent',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    grade_level VARCHAR(20) DEFAULT NULL,
    notification_preference ENUM('phone', 'email', 'both', 'none') DEFAULT 'none',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// 2. Create emergency_alerts table
$sqls[] = "CREATE TABLE IF NOT EXISTS emergency_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disaster_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    recipient_count INT DEFAULT 0,
    sms_sent INT DEFAULT 0,
    email_sent INT DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_by VARCHAR(100) DEFAULT 'System'
)";

// 3. Add parent_phone and parent_email columns to students table (if not exist)
$sqls[] = "ALTER TABLE students ADD COLUMN IF NOT EXISTS parent_phone VARCHAR(20) DEFAULT NULL";
$sqls[] = "ALTER TABLE students ADD COLUMN IF NOT EXISTS parent_email VARCHAR(100) DEFAULT NULL";
$sqls[] = "ALTER TABLE students ADD COLUMN IF NOT EXISTS notification_preference ENUM('phone', 'email', 'both', 'none') DEFAULT 'none'";

// Execute each SQL statement
$success = true;
$messages = [];

foreach ($sqls as $sql) {
    try {
        if (mysqli_query($conn, $sql)) {
            $messages[] = "Success: " . substr($sql, 0, 50) . "...";
        } else {
            // Ignore duplicate column errors
            if (mysqli_errno($conn) == 1060) {
                $messages[] = "Column already exists (OK): " . substr($sql, 0, 50) . "...";
            } else {
                $messages[] = "Error: " . mysqli_error($conn) . " - " . substr($sql, 0, 50) . "...";
                $success = false;
            }
        }
    } catch (Exception $e) {
        $messages[] = "Exception: " . $e->getMessage();
    }
}

mysqli_close($conn);

// Output results
echo "<h1>Emergency System Database Setup</h1>";
echo "<pre>";
foreach ($messages as $msg) {
    echo htmlspecialchars($msg) . "\n";
}
echo "</pre>";

if ($success) {
    echo "<p style='color: green; font-weight: bold;'>Setup completed successfully!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Some errors occurred. Please check the logs.</p>";
}
