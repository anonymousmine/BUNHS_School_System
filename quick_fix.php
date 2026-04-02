<?php
// Quick fix - create just the email_subscribers table
require_once __DIR__ . '/local_db_config.php';

try {
    $conn = new mysqli('localhost', 'root', '', 'bunhs_db_important', 3306);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Quick Fix - Creating email_subscribers Table</h2>";
    
    // Create email_subscribers table
    $sql = "CREATE TABLE IF NOT EXISTS email_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ email_subscribers table created successfully!<br>";
        echo "<br><strong>Fixed!</strong> You can now access the admin dashboard.<br>";
        echo "<a href='admin_account/admin_dashboard.php'>Go to Admin Dashboard</a>";
    } else {
        echo "❌ Error creating table: " . $conn->error . "<br>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
