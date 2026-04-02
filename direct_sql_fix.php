<?php
// Direct SQL fix for email_subscribers table
require_once __DIR__ . '/local_db_config.php';

try {
    $conn = new mysqli('localhost', 'root', '', 'bunhs_db_important', 3306);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Direct SQL Fix</h2>";
    
    // First, let's see what columns exist
    echo "<h3>Current table structure:</h3>";
    $result = $conn->query("DESCRIBE email_subscribers");
    if ($result) {
        echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Add the missing columns if they don't exist
    echo "<h3>Adding missing columns...</h3>";
    
    // Add is_active column if it doesn't exist
    $sql = "ALTER TABLE email_subscribers ADD COLUMN is_active TINYINT(1) DEFAULT 1";
    try {
        $conn->query($sql);
        echo "✅ Added is_active column<br>";
    } catch (Exception $e) {
        echo "⚠️ is_active column might already exist: " . $e->getMessage() . "<br>";
    }
    
    // Add updated_at column if it doesn't exist
    $sql = "ALTER TABLE email_subscribers ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    try {
        $conn->query($sql);
        echo "✅ Added updated_at column<br>";
    } catch (Exception $e) {
        echo "⚠️ updated_at column might already exist: " . $e->getMessage() . "<br>";
    }
    
    // Drop status column if it exists
    $sql = "ALTER TABLE email_subscribers DROP COLUMN status";
    try {
        $conn->query($sql);
        echo "✅ Dropped status column<br>";
    } catch (Exception $e) {
        echo "⚠️ status column might not exist: " . $e->getMessage() . "<br>";
    }
    
    // Add sample data if table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM email_subscribers");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $sql = "INSERT INTO email_subscribers (email, is_active) VALUES 
            ('admin@bunhs.edu.ph', 1),
            ('teacher@bunhs.edu.ph', 1),
            ('parent@bunhs.edu.ph', 1)";
        
        if ($conn->query($sql)) {
            echo "✅ Added sample subscribers<br>";
        }
    } else {
        echo "✅ Table already has data<br>";
    }
    
    // Show final structure
    echo "<h3>Final table structure:</h3>";
    $result = $conn->query("DESCRIBE email_subscribers");
    if ($result) {
        echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
    }
    
    echo "<br><strong>✅ Fix complete!</strong><br>";
    echo "<a href='admin_account/admin_dashboard.php'>Go to Admin Dashboard</a>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
