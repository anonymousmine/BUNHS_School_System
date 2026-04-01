<?php
/**
 * Railway Status Check Page
 * Use this to verify deployment status and configuration
 */

echo "<!DOCTYPE html>\n<html>\n<head>\n";
echo "<title>BUNHS School System - Railway Status</title>\n";
echo "<style>\n";
echo "body { font-family: Arial, sans-serif; margin: 20px; }\n";
echo ".status { padding: 10px; margin: 10px 0; border-radius: 5px; }\n";
echo ".success { background: #d4edda; color: #155724; }\n";
echo ".error { background: #f8d7da; color: #721c24; }\n";
echo ".warning { background: #fff3cd; color: #856404; }\n";
echo ".info { background: #d1ecf1; color: #0c5460; }\n";
echo "pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }\n";
echo "</style>\n</head>\n<body>\n";

echo "<h1>🚀 BUNHS School System - Railway Status</h1>\n";

// PHP Version
echo "<div class='status info'>\n";
echo "<h3>PHP Environment</h3>\n";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>\n";
echo "<p><strong>Server API:</strong> " . PHP_SAPI . "</p>\n";
echo "<p><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</p>\n";
echo "<p><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . "s</p>\n";
echo "</div>\n";

// Environment Variables
echo "<div class='status info'>\n";
echo "<h3>Environment Variables</h3>\n";
$env_vars = ['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME', 'DB_PORT', 'APP_DEBUG', 'RAILWAY_ENVIRONMENT'];

foreach ($env_vars as $var) {
    $value = getenv($var);
    if ($value !== false) {
        $display_value = in_array($var, ['DB_PASSWORD']) ? '***SET***' : htmlspecialchars($value);
        echo "<p><strong>$var:</strong> <span style='color: green;'>$display_value</span></p>\n";
    } else {
        echo "<p><strong>$var:</strong> <span style='color: red;'>NOT SET</span></p>\n";
    }
}
echo "</div>\n";

// MySQLi Extension
echo "<div class='status info'>\n";
echo "<h3>MySQLi Extension</h3>\n";
if (function_exists('mysqli_connect')) {
    echo "<p style='color: green;'>✓ MySQLi extension is loaded</p>\n";
    
    // Try to get MySQL client info
    if (function_exists('mysqli_get_client_info')) {
        echo "<p><strong>MySQLi Client Version:</strong> " . mysqli_get_client_info() . "</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ MySQLi extension is NOT loaded</p>\n";
}
echo "</div>\n";

// Database Connection Test
echo "<div class='status info'>\n";
echo "<h3>Database Connection Test</h3>\n";

$host    = getenv('DB_HOST')    ?: 'localhost';
$db_user = getenv('DB_USER')    ?: 'root';
$db_name = getenv('DB_NAME')    ?: 'bunhs_db_important';
$db_port = getenv('DB_PORT')    ?: 3306;

echo "<p>Testing connection to: <strong>$host:$db_port/$db_name</strong> with user <strong>$db_user</strong></p>\n";

if (function_exists('mysqli_connect')) {
    $conn = @mysqli_connect($host, $db_user, getenv('DB_PASSWORD') ?: '', $db_name, $db_port);
    
    if ($conn) {
        echo "<p style='color: green;'>✓ Database connection successful!</p>\n";
        echo "<p><strong>Server Info:</strong> " . mysqli_get_server_info($conn) . "</p>\n";
        
        // Test query
        $result = @mysqli_query($conn, "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '$db_name'");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            echo "<p><strong>Tables Count:</strong> " . $row['count'] . "</p>\n";
        }
        
        mysqli_close($conn);
    } else {
        echo "<p style='color: red;'>✗ Database connection failed!</p>\n";
        echo "<p><strong>Error:</strong> " . mysqli_connect_error() . "</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ Cannot test - MySQLi extension not available</p>\n";
}

echo "</div>\n";

// File System Check
echo "<div class='status info'>\n";
echo "<h3>File System</h3>\n";
$important_files = [
    'index.php' => 'Main entry point',
    'db_connection.php' => 'Database configuration',
    'composer.json' => 'Dependencies',
    'Procfile' => 'Railway deployment',
    'session_config.php' => 'Session management'
];

foreach ($important_files as $file => $description) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file - $description</p>\n";
    } else {
        echo "<p style='color: red;'>✗ $file - $description (MISSING)</p>\n";
    }
}
echo "</div>\n";

// Recommendations
echo "<div class='status warning'>\n";
echo "<h3>🔧 Railway Recommendations</h3>\n";
echo "<ol>\n";
echo "<li><strong>Environment Variables:</strong> Ensure all DB_* variables are set in Railway dashboard</li>\n";
echo "<li><strong>Database:</strong> Verify MySQL service is running and database exists</li>\n";
echo "<li><strong>Schema:</strong> Import database schema if tables are missing</li>\n";
echo "<li><strong>Debug Mode:</strong> Set APP_DEBUG=true for detailed logging</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<div style='margin-top: 30px; text-align: center; color: #666;'>\n";
echo "<small>This status page helps diagnose Railway deployment issues. Delete after troubleshooting.</small>\n";
echo "</div>\n";

echo "</body>\n</html>\n";
?>
