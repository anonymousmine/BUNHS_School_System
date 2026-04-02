<?php
// Check database structure to see what columns exist
require_once __DIR__ . '/local_db_config.php';

try {
    $conn = new mysqli('localhost', 'root', '', 'bunhs_db_important', 3306);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Database Structure Check</h2>";
    
    // Check admin table structure
    echo "<h3>Admin Table:</h3>";
    $result = $conn->query("DESCRIBE admin");
    if ($result) {
        echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Check sub_admin table structure
    echo "<h3>Sub_Admin Table:</h3>";
    $result = $conn->query("DESCRIBE sub_admin");
    if ($result) {
        echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Check if there are any admin users
    echo "<h3>Existing Admin Users:</h3>";
    $result = $conn->query("SELECT username, id FROM admin LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'><tr><th>ID</th><th>Username</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['username']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "No admin users found.";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
