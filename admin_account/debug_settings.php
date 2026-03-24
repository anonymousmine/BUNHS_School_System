<?php
require_once '../session_config.php';
require_once '../db_connection.php';

echo "<h2>Debug Information</h2>";

echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Database Connection:</h3>";
echo "Connection status: " . ($conn ? "Connected" : "Not connected") . "<br>";
if ($conn) {
    echo "Connection ping: " . ($conn->ping() ? "Success" : "Failed") . "<br>";
}

echo "<h3>Authentication Check:</h3>";
$is_logged_in = (isset($_SESSION['user_id']) && in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']))
    || isset($_SESSION['admin_id']);
echo "Is logged in: " . ($is_logged_in ? "Yes" : "No") . "<br>";

if ($is_logged_in) {
    echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
    echo "User Type: " . ($_SESSION['user_type'] ?? 'Not set') . "<br>";
    echo "Admin ID: " . ($_SESSION['admin_id'] ?? 'Not set') . "<br>";
}

echo "<h3>Test Loading Helper Classes:</h3>";
try {
    require_once __DIR__ . '/helpers/auth_helper.php';
    echo "AuthHelper loaded successfully<br>";
    
    require_once __DIR__ . '/helpers/permission_manager.php';
    echo "PermissionManager loaded successfully<br>";
    
    require_once __DIR__ . '/helpers/database_helper.php';
    echo "DatabaseHelper loaded successfully<br>";
    
    require_once __DIR__ . '/helpers/validation_helper.php';
    echo "ValidationHelper loaded successfully<br>";
    
} catch (Exception $e) {
    echo "Error loading helpers: " . $e->getMessage() . "<br>";
}

echo "<h3>Test AuthHelper:</h3>";
try {
    if ($conn) {
        $user_data = AuthHelper::requireAuth($conn);
        echo "AuthHelper::requireAuth() passed<br>";
    } else {
        echo "No database connection for AuthHelper test<br>";
    }
} catch (Exception $e) {
    echo "AuthHelper error: " . $e->getMessage() . "<br>";
    echo "Error details: " . $e->getTraceAsString() . "<br>";
}

echo "<hr>";
echo "<a href='settings.php'>Try Settings Page</a><br>";
echo "<a href='../index.php'>Go to Index</a>";
?>
