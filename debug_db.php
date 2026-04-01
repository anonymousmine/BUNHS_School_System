<?php
/**
 * Database Connection Debug Script for Railway
 * This will help identify the exact connection issue
 */

echo "<h2>Railway Database Connection Debug</h2>\n";

// Check environment variables
echo "<h3>Environment Variables Status:</h3>\n";
$env_vars = ['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME', 'DB_PORT'];

foreach ($env_vars as $var) {
    $value = getenv($var);
    if ($value !== false) {
        echo "<p style='color: green;'>✓ $var: " . (in_array($var, ['DB_PASSWORD']) ? '***SET***' : $value) . "</p>\n";
    } else {
        echo "<p style='color: red;'>✗ $var: NOT SET</p>\n";
    }
}

// Check mysqli extension
echo "<h3>MySQLi Extension:</h3>\n";
if (function_exists('mysqli_connect')) {
    echo "<p style='color: green;'>✓ MySQLi extension is loaded</p>\n";
} else {
    echo "<p style='color: red;'>✗ MySQLi extension is NOT loaded</p>\n";
}

// Test connection with different approaches
echo "<h3>Connection Tests:</h3>\n";

$host    = getenv('DB_HOST')    ?: 'localhost';
$db_user = getenv('DB_USER')    ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_NAME')    ?: 'bunhs_db_important';
$db_port = getenv('DB_PORT')    ?: 3306;

echo "<p>Attempting connection to: $host:$db_port/$db_name with user: $db_user</p>\n";

// Test 1: Standard connection
echo "<h4>Test 1: Standard MySQLi Connection</h4>\n";
try {
    $conn = mysqli_connect($host, $db_user, $db_pass, $db_name, $db_port);
    if ($conn) {
        echo "<p style='color: green;'>✓ Connection successful!</p>\n";
        echo "<p>MySQL Server Info: " . mysqli_get_server_info($conn) . "</p>\n";
        mysqli_close($conn);
    } else {
        echo "<p style='color: red;'>✗ Connection failed: " . mysqli_connect_error() . "</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>\n";
}

// Test 2: Connection without database first
echo "<h4>Test 2: Connect to MySQL Server (no database)</h4>\n";
try {
    $conn = mysqli_connect($host, $db_user, $db_pass, '', $db_port);
    if ($conn) {
        echo "<p style='color: green;'>✓ Server connection successful!</p>\n";
        
        // List databases
        $result = mysqli_query($conn, "SHOW DATABASES");
        if ($result) {
            echo "<p>Available databases:</p>\n<ul>\n";
            while ($row = mysqli_fetch_row($result)) {
                echo "<li>" . htmlspecialchars($row[0]) . "</li>\n";
            }
            echo "</ul>\n";
        }
        
        mysqli_close($conn);
    } else {
        echo "<p style='color: red;'>✗ Server connection failed: " . mysqli_connect_error() . "</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Exception: " . $e->getMessage() . "</p>\n";
}

// Test 3: PDO Connection (if available)
echo "<h4>Test 3: PDO MySQL Connection</h4>\n";
if (class_exists('PDO')) {
    try {
        $dsn = "mysql:host=$host;port=$db_port;dbname=$db_name;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p style='color: green;'>✓ PDO Connection successful!</p>\n";
        $pdo = null;
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ PDO Connection failed: " . $e->getMessage() . "</p>\n";
    }
} else {
    echo "<p style='color: orange;'>⚠ PDO MySQL extension not available</p>\n";
}

// Recommendations
echo "<h3>Recommendations for Railway:</h3>\n";
echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #0066cc;'>\n";
echo "<h4>If environment variables are missing:</h4>\n";
echo "<ol>\n";
echo "<li>Go to your Railway project dashboard</li>\n";
echo "<li>Click on your service → Variables tab</li>\n";
echo "<li>Add these environment variables:</li>\n";
echo "<ul>\n";
echo "<li><strong>DB_HOST</strong>: your-mysql-host.railway.app</li>\n";
echo "<li><strong>DB_PORT</strong>: 3306 (or your MySQL port)</li>\n";
echo "<li><strong>DB_USER</strong>: your MySQL username</li>\n";
echo "<li><strong>DB_PASSWORD</strong>: your MySQL password</li>\n";
echo "<li><strong>DB_NAME</strong>: bunhs_db_important</li>\n";
echo "</ul>\n";
echo "</ol>\n";
echo "<h4>If connection still fails:</h4>\n";
echo "<ol>\n";
echo "<li>Verify MySQL service is running on Railway</li>\n";
echo "<li>Check if database 'bunhs_db_important' exists</li>\n";
echo "<li>Try connecting without specifying database first</li>\n";
echo "<li>Import the database schema if needed</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<p><small>This script helps diagnose Railway database connection issues. Delete after use.</small></p>\n";
?>
