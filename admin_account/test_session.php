<?php
// Simple test to check session and API status
require_once 'session_config.php';

// Start session properly
session_start();

// Check what session data we have
echo "<h2>Session Debug Information</h2>";
echo "<pre>";
echo "Session Status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";
echo "</pre>";

// Test API calls
echo "<h2>API Test Results</h2>";

// Test notification API
echo "<h3>Notification API Test:</h3>";
$notif_response = file_get_contents('http://127.0.0.1:61681/BUNHS_School_System/admin_account/notification_api.php?action=fetch');
echo "<pre>" . $notif_response . "</pre>";

// Test dashboard API
echo "<h3>Dashboard API Test:</h3>";
$dash_response = file_get_contents('http://127.0.0.1:61681/BUNHS_School_System/admin_account/api/dashboard_api.php?action=stats');
echo "<pre>" . $dash_response . "</pre>";

// Test chat API
echo "<h3>Chat API Test:</h3>";
$chat_response = file_get_contents('http://127.0.0.1:61681/BUNHS_School_System/admin_account/chat_api.php');
echo "<pre>" . $chat_response . "</pre>";
?>
