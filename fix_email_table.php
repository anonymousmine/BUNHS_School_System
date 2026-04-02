<?php
// Fix the email_subscribers table structure
require_once __DIR__ . '/local_db_config.php';

try {
    $conn = new mysqli('localhost', 'root', '', 'bunhs_db_important', 3306);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Fixing email_subscribers Table</h2>";
    
    // Drop and recreate with correct structure
    $sql = "DROP TABLE IF EXISTS email_subscribers";
    if ($conn->query($sql)) {
        echo "✅ Dropped existing table<br>";
    }
    
    // Create with correct columns
    $sql = "CREATE TABLE email_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ email_subscribers table created with correct structure!<br>";
        
        // Add some sample data
        $sql = "INSERT IGNORE INTO email_subscribers (email, is_active) VALUES 
            ('admin@bunhs.edu.ph', 1),
            ('teacher@bunhs.edu.ph', 1),
            ('parent@bunhs.edu.ph', 1)";
        
        if ($conn->query($sql)) {
            echo "✅ Added sample subscribers<br>";
        }
        
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
