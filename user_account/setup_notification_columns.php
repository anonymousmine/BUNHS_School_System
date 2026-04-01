<?php

/**
 * Database Migration Script
 * Adds notification verification columns to the students table
 * Run this script once to add required columns
 */

session_start();
include '../db_connection.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if admin is logged in (for security)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$sql = "
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS phone_verified TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS verification_otp VARCHAR(5) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS verification_otp_expires INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS email_otp VARCHAR(5) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS email_otp_expires INT DEFAULT NULL
";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'Database columns added successfully']);
} else {
    // Check if columns already exist
    $check = $conn->query("SHOW COLUMNS FROM students LIKE 'phone_verified'");
    if ($check->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Columns already exist']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding columns: ' . $conn->error]);
    }
}

$conn->close();
