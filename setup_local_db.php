<?php
// Include local database configuration
require_once __DIR__ . '/local_db_config.php';

// Simple database setup script for local testing
try {
    // Connect to MySQL without specifying database first
    $conn = new mysqli('localhost', 'root', '', '', 3306);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Connected to MySQL successfully<br>";
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS bunhs_db_important";
    if ($conn->query($sql)) {
        echo "✅ Database 'bunhs_db_important' created/exists<br>";
    } else {
        echo "❌ Error creating database: " . $conn->error . "<br>";
    }
    
    // Select the database
    $conn->select_db('bunhs_db_important');
    
    // Create admin table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        school_email VARCHAR(100),
        full_name VARCHAR(100),
        principal_title VARCHAR(100),
        profile_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ Admin table created/exists<br>";
    } else {
        echo "❌ Error creating admin table: " . $conn->error . "<br>";
    }
    
    // Create sub_admin table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS sub_admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        role VARCHAR(50),
        status ENUM('pending', 'approved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ Sub_admin table created/exists<br>";
    } else {
        echo "❌ Error creating sub_admin table: " . $conn->error . "<br>";
    }
    
    // Check if admin user exists
    $sql = "SELECT id FROM admin WHERE username = 'Admin_SchoolHead_BUNHS'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        // Create admin user
        $password_hash = password_hash('BUNHS_Admin_DEPED_buyoan', PASSWORD_DEFAULT);
        $sql = "INSERT INTO admin (username, password_hash, school_email, full_name, principal_title) 
                VALUES ('Admin_SchoolHead_BUNHS', ?, 'admin@bunhs.edu.ph', 'School Head Administrator', 'Principal')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $password_hash);
        
        if ($stmt->execute()) {
            echo "✅ Admin user 'Admin_SchoolHead_BUNHS' created successfully<br>";
        } else {
            echo "❌ Error creating admin user: " . $stmt->error . "<br>";
        }
        $stmt->close();
    } else {
        echo "✅ Admin user 'Admin_SchoolHead_BUNHS' already exists<br>";
    }
    
    echo "<br><strong>Setup complete!</strong><br>";
    echo "You can now test the login at: <a href='test_login.php'>test_login.php</a><br>";
    echo "Username: <strong>Admin_SchoolHead_BUNHS</strong><br>";
    echo "Password: <strong>BUNHS_Admin_DEPED_buyoan</strong><br>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Make sure XAMPP MySQL is running and accessible.<br>";
}
?>
