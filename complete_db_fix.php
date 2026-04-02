<?php
// Complete database fix - create all missing tables
require_once __DIR__ . '/local_db_config.php';

try {
    $conn = new mysqli('localhost', 'root', '', 'bunhs_db_important', 3306);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Complete Database Fix</h2>";
    
    $tables = [
        'document_requests' => "
            CREATE TABLE IF NOT EXISTS document_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT,
                document_type VARCHAR(100),
                purpose TEXT,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_date TIMESTAMP NULL,
                processed_by VARCHAR(100),
                notes TEXT,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'school_announcements' => "
            CREATE TABLE IF NOT EXISTS school_announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                announcement_date DATE NOT NULL,
                is_closed TINYINT(1) NOT NULL DEFAULT 0,
                custom_message TEXT DEFAULT NULL,
                created_by VARCHAR(100) DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'students' => "
            CREATE TABLE IF NOT EXISTS students (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'teachers' => "
            CREATE TABLE IF NOT EXISTS teachers (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'events' => "
            CREATE TABLE IF NOT EXISTS events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                event_date DATE,
                event_time TIME,
                location VARCHAR(100),
                image VARCHAR(255),
                status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'clubs' => "
            CREATE TABLE IF NOT EXISTS clubs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                logo VARCHAR(255),
                status ENUM('Active', 'Inactive') DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'club_members' => "
            CREATE TABLE IF NOT EXISTS club_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                club_id INT,
                student_id INT,
                role ENUM('member', 'officer', 'advisor') DEFAULT 'member',
                joined_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'school_ratings' => "
            CREATE TABLE IF NOT EXISTS school_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                comment TEXT,
                reviewer_name VARCHAR(100),
                reviewer_email VARCHAR(100),
                status ENUM('approved', 'pending') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'student_memories' => "
            CREATE TABLE IF NOT EXISTS student_memories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL DEFAULT '',
                image VARCHAR(500) NOT NULL DEFAULT '',
                category ENUM('Student Activities','Academic Excellence','Sports') NOT NULL DEFAULT 'Student Activities',
                uploaded_by VARCHAR(100) DEFAULT 'admin',
                uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'school_settings' => "
            CREATE TABLE IF NOT EXISTS school_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ",
        
        'homepage_cards' => "
            CREATE TABLE IF NOT EXISTS homepage_cards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                card_key VARCHAR(100) NOT NULL UNIQUE,
                title VARCHAR(200) DEFAULT '',
                description TEXT DEFAULT '',
                icon VARCHAR(100) DEFAULT '',
                image VARCHAR(255) DEFAULT '',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        "
    ];
    
    foreach ($tables as $table_name => $sql) {
        try {
            if ($conn->query($sql)) {
                echo "✅ Created/verified table: $table_name<br>";
            }
        } catch (Exception $e) {
            echo "⚠️ Table $table_name issue: " . $e->getMessage() . "<br>";
        }
    }
    
    // Add sample data for essential tables
    echo "<h3>Adding sample data...</h3>";
    
    // Sample students
    $conn->query("INSERT IGNORE INTO students (lrn, first_name, last_name, grade_level, section, status) VALUES 
        ('123456789012', 'Juan', 'Santos', 10, 'A', 'active'),
        ('123456789013', 'Maria', 'Reyes', 11, 'B', 'active'),
        ('123456789014', 'Jose', 'Cruz', 12, 'A', 'active')");
    echo "✅ Added sample students<br>";
    
    // Sample teachers
    $conn->query("INSERT IGNORE INTO teachers (employee_id, first_name, last_name, department, position, teacher_subjects) VALUES 
        ('T001', 'Ana', 'Garcia', 'Science', 'Science Teacher', 'Biology, Chemistry'),
        ('T002', 'Carlos', 'Rodriguez', 'Math', 'Math Teacher', 'Algebra, Geometry'),
        ('T003', 'Elena', 'Martinez', 'English', 'English Teacher', 'Grammar, Literature')");
    echo "✅ Added sample teachers<br>";
    
    // Sample events
    $conn->query("INSERT IGNORE INTO events (title, description, event_date, location, status) VALUES 
        ('Science Fair', 'Annual science exhibition', '2026-05-15', 'School Auditorium', 'upcoming'),
        ('Sports Festival', 'Inter-class sports competition', '2026-06-20', 'School Grounds', 'upcoming'),
        ('Graduation Ceremony', 'Commencement exercises', '2026-04-30', 'School Auditorium', 'upcoming')");
    echo "✅ Added sample events<br>";
    
    // Sample clubs
    $conn->query("INSERT IGNORE INTO clubs (name, description, status) VALUES 
        ('Science Club', 'For students interested in science and research', 'Active'),
        ('Math Club', 'For mathematics enthusiasts', 'Active'),
        ('English Club', 'For literature and language lovers', 'Active')");
    echo "✅ Added sample clubs<br>";
    
    echo "<br><strong>✅ Complete database fix finished!</strong><br>";
    echo "All required tables have been created with sample data.<br>";
    echo "<a href='admin_account/admin_dashboard.php'>Go to Admin Dashboard</a>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
