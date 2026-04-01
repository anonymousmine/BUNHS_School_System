-- ============================================================
--  BUNHS SCHOOL SYSTEM - ESSENTIAL DATABASE SETUP
--  Only includes tables actually used by the website
--  Based on database analysis and cleanup
-- ============================================================

-- 1. Enhanced events table with all required columns
ALTER TABLE events 
ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS organizer_name VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS organizer_position VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS organizer_contact VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS team_based TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS source VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_official TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS event_days INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS event_start_time TIME DEFAULT NULL,
ADD COLUMN IF NOT EXISTS event_end_time TIME DEFAULT NULL;

-- 2. Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_events_date_category ON events(event_date, category);
CREATE INDEX IF NOT EXISTS idx_events_official ON events(is_official, event_date);
CREATE INDEX IF NOT EXISTS idx_events_team_based ON events(team_based, event_date);
CREATE INDEX IF NOT EXISTS idx_events_title ON events(title(100));
CREATE INDEX IF NOT EXISTS idx_events_description ON events(description(100));
CREATE INDEX IF NOT EXISTS idx_events_location ON events(location(100));

-- ============================================================
-- CORE SYSTEM TABLES (Essential for Website Functionality)
-- ============================================================

-- 3. Admin authentication tables
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    title VARCHAR(100),
    school_email VARCHAR(100),
    phone VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    user_type ENUM('admin', 'sub-admin') DEFAULT 'admin',
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sub_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    department VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Student and Teacher tables
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    grade_level VARCHAR(20),
    section VARCHAR(20),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
    graduation_year INT,
    gpa DECIMAL(3,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    department VARCHAR(100),
    subject_specialization VARCHAR(200),
    teacher_subjects TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Content Management Tables
CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT,
    author VARCHAR(100),
    category VARCHAR(50) DEFAULT 'General',
    image VARCHAR(255),
    is_featured TINYINT(1) DEFAULT 0,
    is_published TINYINT(1) DEFAULT 1,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_news_published (is_published, created_at),
    INDEX idx_news_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS school_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_date DATE NOT NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    custom_message TEXT DEFAULT NULL,
    created_by VARCHAR(100) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_announcements_date (announcement_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. School Configuration
CREATE TABLE IF NOT EXISTS school_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_key VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) DEFAULT '',
    description TEXT DEFAULT '',
    icon VARCHAR(100) DEFAULT '',
    image VARCHAR(255) DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Student Activities
CREATE TABLE IF NOT EXISTS student_memories (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT '',
    image VARCHAR(500) NOT NULL DEFAULT '',
    category ENUM('Student Activities', 'Academic Excellence', 'Sports') NOT NULL DEFAULT 'Student Activities',
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_memories_category (category),
    INDEX idx_memories_uploaded (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    advisor VARCHAR(100),
    meeting_schedule VARCHAR(100),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Communication System
CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    admin_id INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_conversations_student (student_id),
    INDEX idx_conversations_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role ENUM('admin', 'student') NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'file', 'image') DEFAULT 'text',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    INDEX idx_messages_conversation (conversation_id),
    INDEX idx_messages_sender (sender_id),
    INDEX idx_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. File Management System
CREATE TABLE IF NOT EXISTS file_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    purpose TEXT,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by VARCHAR(100),
    notes TEXT,
    INDEX idx_requests_student (student_id),
    INDEX idx_requests_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Additional Features
CREATE TABLE IF NOT EXISTS school_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rater_name VARCHAR(100),
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ratings_approved (is_approved),
    INDEX idx_ratings_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_student (student_id),
    INDEX idx_logs_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parent_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) DEFAULT '',
    relationship_to_student VARCHAR(50) DEFAULT '',
    occupation VARCHAR(100) DEFAULT '',
    workplace VARCHAR(200) DEFAULT '',
    home_address TEXT DEFAULT '',
    mobile_number VARCHAR(20) DEFAULT '',
    landline_number VARCHAR(20) DEFAULT '',
    active_email VARCHAR(100) DEFAULT '',
    emergency_contact_name VARCHAR(100) DEFAULT '',
    emergency_contact_phone VARCHAR(20) DEFAULT '',
    profile_picture VARCHAR(255) DEFAULT '',
    total_outstanding_balance DECIMAL(10,2) DEFAULT 0.00,
    payment_history TEXT DEFAULT NULL,
    enrollment_documents_status TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- INITIAL DATA SETUP
-- ============================================================

-- Insert default school settings
INSERT IGNORE INTO school_settings (setting_key, setting_value) VALUES
('school_name', 'Buyoan National High School'),
('school_address', 'Buyoan, Agusan del Norte, Philippines'),
('school_phone', '+63 (0) XXX-XXXX'),
('school_email', 'info@buyoan.edu.ph'),
('school_motto', 'Quality Education for All'),
('about_photo', 'assets/img/front pic/Buyoan School.jpg'),
('cta_photo', 'assets/img/education/Students learning.jpg');

-- Insert default homepage cards
INSERT IGNORE INTO homepage_cards (card_key, title, description, icon, image) VALUES
('card1', 'Quality Education', 'Providing quality education to nurture young minds for a better future.', 'bi-mortarboard', 'assets/img/education/classroom.jpg'),
('card2', 'Dedicated Teachers', 'Our team of experienced educators committed to student success.', 'bi-people', 'assets/img/education/teachers.jpg'),
('card3', 'Modern Facilities', 'State-of-the-art facilities to support learning and development.', 'bi-building', 'assets/img/education/facilities.jpg');

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO admins (username, password_hash, full_name, title, school_email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'Administrator', 'admin@buyoan.edu.ph');

-- ============================================================
-- VERIFICATION QUERY
-- ============================================================

SELECT 
    'Essential database setup completed successfully!' as status,
    (SELECT COUNT(*) FROM admins) as admin_count,
    (SELECT COUNT(*) FROM students) as student_count,
    (SELECT COUNT(*) FROM teachers) as teacher_count,
    (SELECT COUNT(*) FROM news) as news_count,
    (SELECT COUNT(*) FROM events) as event_count,
    (SELECT COUNT(*) FROM school_settings) as settings_count;
