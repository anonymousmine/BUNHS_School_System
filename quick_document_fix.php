<?php
// Quick fix - create just the document_requests table
require_once __DIR__ . '/local_db_config.php';

try {
    $conn = new mysqli('localhost', 'root', '', 'bunhs_db_important', 3306);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Quick Fix - Creating document_requests Table</h2>";
    
    // Create document_requests table
    $sql = "CREATE TABLE IF NOT EXISTS document_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT,
        document_type VARCHAR(100),
        purpose TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_date TIMESTAMP NULL,
        processed_by VARCHAR(100),
        notes TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ document_requests table created successfully!<br>";
        
        // Add sample data
        $sql = "INSERT IGNORE INTO document_requests (student_id, document_type, purpose, status) VALUES 
            (1, 'Transcript of Records', 'For college application', 'pending'),
            (2, 'Certificate of Good Moral', 'For employment', 'approved'),
            (3, 'Form 137', 'For transfer', 'pending')";
        
        if ($conn->query($sql)) {
            echo "✅ Added sample document requests<br>";
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
