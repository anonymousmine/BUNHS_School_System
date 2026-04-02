<?php
// Create missing tables for the admin dashboard
require_once __DIR__ . '/local_db_config.php';

try {
    $conn = new mysqli('localhost', 'root', '', 'bunhs_db_important', 3306);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Creating Missing Tables</h2>";
    
    // Create email_subscribers table
    $sql = "CREATE TABLE IF NOT EXISTS email_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) UNIQUE NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ email_subscribers table created/exists<br>";
    } else {
        echo "❌ Error creating email_subscribers table: " . $conn->error . "<br>";
    }
    
    // Create students table
    $sql = "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lrn VARCHAR(12) UNIQUE,
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        middle_name VARCHAR(50),
        grade_level INT,
        section VARCHAR(50),
        status ENUM('active', 'inactive', 'graduated', 'completers') DEFAULT 'active',
        graduation_year INT,
        email VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ students table created/exists<br>";
    } else {
        echo "❌ Error creating students table: " . $conn->error . "<br>";
    }
    
    // Create teachers table
    $sql = "CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id VARCHAR(20) UNIQUE,
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        middle_name VARCHAR(50),
        department VARCHAR(50),
        position VARCHAR(50),
        teacher_subjects TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        email VARCHAR(100),
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ teachers table created/exists<br>";
    } else {
        echo "❌ Error creating teachers table: " . $conn->error . "<br>";
    }
    
    // Create events table
    $sql = "CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        event_date DATE,
        event_time TIME,
        location VARCHAR(100),
        image VARCHAR(255),
        status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ events table created/exists<br>";
    } else {
        echo "❌ Error creating events table: " . $conn->error . "<br>";
    }
    
    // Create clubs table
    $sql = "CREATE TABLE IF NOT EXISTS clubs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        logo VARCHAR(255),
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ clubs table created/exists<br>";
    } else {
        echo "❌ Error creating clubs table: " . $conn->error . "<br>";
    }
    
    // Create school_ratings table
    $sql = "CREATE TABLE IF NOT EXISTS school_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        reviewer_name VARCHAR(100),
        reviewer_email VARCHAR(100),
        status ENUM('approved', 'pending') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ school_ratings table created/exists<br>";
    } else {
        echo "❌ Error creating school_ratings table: " . $conn->error . "<br>";
    }
    
    // Create student_memories table
    $sql = "CREATE TABLE IF NOT EXISTS student_memories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL DEFAULT '',
        image VARCHAR(500) NOT NULL DEFAULT '',
        category ENUM('Student Activities','Academic Excellence','Sports') NOT NULL DEFAULT 'Student Activities',
        uploaded_by VARCHAR(100) DEFAULT 'admin',
        uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ student_memories table created/exists<br>";
    } else {
        echo "❌ Error creating student_memories table: " . $conn->error . "<br>";
    }
    
    // Create school_settings table
    $sql = "CREATE TABLE IF NOT EXISTS school_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ school_settings table created/exists<br>";
    } else {
        echo "❌ Error creating school_settings table: " . $conn->error . "<br>";
    }
    
    // Create homepage_cards table
    $sql = "CREATE TABLE IF NOT EXISTS homepage_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        card_key VARCHAR(100) NOT NULL UNIQUE,
        title VARCHAR(200) DEFAULT '',
        description TEXT DEFAULT '',
        icon VARCHAR(100) DEFAULT '',
        image VARCHAR(255) DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql)) {
        echo "✅ homepage_cards table created/exists<br>";
    } else {
        echo "❌ Error creating homepage_cards table: " . $conn->error . "<br>";
    }
    
    echo "<br><strong>✅ Database setup complete!</strong><br>";
    echo "You can now access the admin dashboard without errors.<br>";
    echo "<a href='admin_account/admin_dashboard.php'>Go to Admin Dashboard</a>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
