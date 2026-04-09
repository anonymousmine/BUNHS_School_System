-- ============================================================
--  BUNHS SCHOOL SYSTEM - DATABASE SCHEMA EXPORT
--  Database: bunhs_db_important
--  Generated: 2026-04-09
-- ============================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `bunhs_db_important` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bunhs_db_important`;

-- ============================================================
--  ADMIN TABLES
-- ============================================================

-- Admin users table
CREATE TABLE IF NOT EXISTS `admin` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` varchar(255) NOT NULL,
    `full_name` varchar(100) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `profile_image` varchar(255) DEFAULT NULL,
    `principal_title` varchar(100) DEFAULT 'Administrator',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sub-admin users table
CREATE TABLE IF NOT EXISTS `sub_admin` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` varchar(255) NOT NULL,
    `full_name` varchar(100) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `role` enum('news_admin','announcement_admin','student_admin','teacher_admin','club_admin','super_sub_admin','forms_admin') DEFAULT NULL,
    `profile_image` varchar(255) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  STUDENT & TEACHER TABLES
-- ============================================================

-- Students table
CREATE TABLE IF NOT EXISTS `students` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `lrn` varchar(12) DEFAULT NULL,
    `first_name` varchar(50) DEFAULT NULL,
    `last_name` varchar(50) DEFAULT NULL,
    `middle_name` varchar(50) DEFAULT NULL,
    `grade_level` int(11) DEFAULT NULL,
    `section` varchar(50) DEFAULT NULL,
    `status` enum('active','inactive','graduated','completers') DEFAULT 'active',
    `graduation_year` int(11) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `lrn` (`lrn`),
    KEY `status` (`status`),
    KEY `graduation_year` (`graduation_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teachers table
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `employee_id` varchar(20) DEFAULT NULL,
    `first_name` varchar(50) DEFAULT NULL,
    `last_name` varchar(50) DEFAULT NULL,
    `middle_name` varchar(50) DEFAULT NULL,
    `department` varchar(50) DEFAULT NULL,
    `position` varchar(50) DEFAULT NULL,
    `teacher_subjects` text DEFAULT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `email` varchar(100) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `employee_id` (`employee_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  EVENTS & ANNOUNCEMENTS
-- ============================================================

-- Events table
CREATE TABLE IF NOT EXISTS `events` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(200) NOT NULL,
    `description` text DEFAULT NULL,
    `event_date` date DEFAULT NULL,
    `event_time` time DEFAULT NULL,
    `event_start_time` time DEFAULT NULL,
    `event_end_time` time DEFAULT NULL,
    `location` varchar(255) DEFAULT NULL,
    `image` varchar(255) DEFAULT NULL,
    `category` varchar(50) DEFAULT 'General',
    `organizer_name` varchar(255) DEFAULT NULL,
    `organizer_position` varchar(255) DEFAULT NULL,
    `organizer_contact` varchar(255) DEFAULT NULL,
    `team_based` tinyint(1) DEFAULT 0,
    `source` varchar(255) DEFAULT NULL,
    `is_official` tinyint(1) DEFAULT 0,
    `event_days` int(11) DEFAULT 1,
    `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_date` (`event_date`),
    KEY `status` (`status`),
    KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- School announcements table
CREATE TABLE IF NOT EXISTS `school_announcements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `announcement_date` date NOT NULL,
    `is_closed` tinyint(1) NOT NULL DEFAULT 0,
    `custom_message` text DEFAULT NULL,
    `created_by` varchar(100) DEFAULT 'admin',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `announcement_date` (`announcement_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  CLUBS & ACTIVITIES
-- ============================================================

-- Clubs table
CREATE TABLE IF NOT EXISTS `clubs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `logo` varchar(255) DEFAULT NULL,
    `status` enum('Active','Inactive') DEFAULT 'Active',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Club members table
CREATE TABLE IF NOT EXISTS `club_members` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `club_id` int(11) NOT NULL,
    `student_id` int(11) DEFAULT NULL,
    `member_name` varchar(100) DEFAULT NULL,
    `role` varchar(50) DEFAULT 'Member',
    `joined_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `club_id` (`club_id`),
    KEY `student_id` (`student_id`),
    FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student memories table
CREATE TABLE IF NOT EXISTS `student_memories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(200) NOT NULL,
    `image` varchar(255) DEFAULT NULL,
    `category` enum('Student Activities','Academic Excellence','Sports') DEFAULT 'Student Activities',
    `description` text DEFAULT NULL,
    `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `category` (`category`),
    KEY `uploaded_at` (`uploaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  NEWS & CONTENT
-- ============================================================

-- News table
CREATE TABLE IF NOT EXISTS `news` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(200) NOT NULL,
    `content` text DEFAULT NULL,
    `news_date` date DEFAULT NULL,
    `author` varchar(100) DEFAULT NULL,
    `image` varchar(255) DEFAULT NULL,
    `status` enum('published','draft') DEFAULT 'published',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `news_date` (`news_date`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SCHOOL SETTINGS
-- ============================================================

-- School settings table
CREATE TABLE IF NOT EXISTS `school_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text DEFAULT NULL,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Homepage cards table
CREATE TABLE IF NOT EXISTS `homepage_cards` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `card_key` varchar(50) NOT NULL,
    `title` varchar(200) DEFAULT NULL,
    `description` text DEFAULT NULL,
    `icon` varchar(100) DEFAULT NULL,
    `image` varchar(255) DEFAULT NULL,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `card_key` (`card_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  EMAIL SUBSCRIPTIONS
-- ============================================================

-- Email subscribers table
CREATE TABLE IF NOT EXISTS `email_subscribers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(100) NOT NULL,
    `status` enum('active','inactive') DEFAULT 'active',
    `subscribed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  RATINGS & FEEDBACK
-- ============================================================

-- School ratings table
CREATE TABLE IF NOT EXISTS `school_ratings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `rating` decimal(2,1) NOT NULL,
    `review` text DEFAULT NULL,
    `reviewer_name` varchar(100) DEFAULT NULL,
    `reviewer_email` varchar(100) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `rating` (`rating`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  INSERT DEFAULT DATA
-- ============================================================

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO `admin` (`username`, `password`, `full_name`, `email`, `principal_title`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@bunhs.edu.ph', 'Principal');

-- Insert default school settings
INSERT IGNORE INTO `school_settings` (`setting_key`, `setting_value`) VALUES
('school_founding_year', '2017'),
('about_photo', 'assets/img/front pic/Buyoan School.jpg'),
('cta_photo', 'assets/img/education/Students learning.jpg'),
('school_name', 'Buyoan National High School'),
('school_address', 'Buyoan, Nagcarlan, Laguna'),
('school_phone', '(049) 566-1234'),
('school_email', 'info@bunhs.edu.ph');

-- Insert default homepage cards
INSERT IGNORE INTO `homepage_cards` (`card_key`, `title`, `description`, `icon`) VALUES
('leadership', 'Leadership Development', 'Developing future leaders through comprehensive student government and leadership programs.', 'fa-users'),
('cultural', 'Cultural Excellence', 'Celebrating Filipino culture and arts through various cultural activities and presentations.', 'fa-palette'),
('innovation', 'Innovation & Technology', 'Embracing technology and innovation to enhance learning experiences.', 'fa-laptop-code'),
('cert_card1', 'Academic Excellence', 'Consistently maintaining high academic standards and achievements.', 'fa-graduation-cap'),
('cert_card2', 'Sports Development', 'Comprehensive sports program promoting physical fitness and teamwork.', 'fa-trophy'),
('cert_card3', 'Community Service', 'Instilling values of community service and social responsibility.', 'fa-hands-helping');

-- Insert sample news
INSERT IGNORE INTO `news` (`title`, `content`, `news_date`, `author`, `image`) VALUES
('BUNHS Celebrates 25th Founding Anniversary', 'Buyoan National High School marks a quarter-century of educational excellence with a week-long celebration featuring various activities including cultural presentations, academic competitions, and community service projects.', '2024-06-01', 'Buyoan National High School', 'blog-post-1.jpg'),
('Students Excel in Regional Science Fair', 'Three BUNHS students brought home medals from the Regional Science and Technology Fair, showcasing innovative projects that address real-world problems in agriculture and environmental sustainability.', '2024-05-28', 'Science Department', 'blog-post-2.jpg'),
('New Computer Lab Inauguration', 'The school inaugurated a state-of-the-art computer laboratory to enhance digital literacy among students, featuring 50 modern workstations with high-speed internet access.', '2024-05-20', 'IT Department', 'congrats sir mark.jpg');

-- Insert sample clubs
INSERT IGNORE INTO `clubs` (`name`, `description`) VALUES
('Student Government', 'Lead student initiatives and represent your peers in school governance.'),
('Science Club', 'Explore scientific discoveries and participate in science fairs and competitions.'),
('Arts & Culture', 'Express your creativity through various art forms and cultural activities.'),
('Sports Club', 'Develop athletic skills and promote teamwork through various sports activities.'),
('Debate Society', 'Enhance your public speaking and critical thinking skills through debates.'),
('Environmental Club', 'Promote environmental awareness and sustainability initiatives in school.');

-- Insert sample events
INSERT IGNORE INTO `events` (`title`, `description`, `event_date`, `event_start_time`, `location`, `category`, `status`) VALUES
('Graduation Ceremony 2024', 'Annual commencement ceremony for graduating students celebrating their academic achievements.', '2024-04-05', '08:00:00', 'School Auditorium', 'Academic', 'completed'),
('Science Fair Exhibition', 'Students showcase their innovative science projects and research findings.', '2024-03-15', '09:00:00', 'Science Building', 'Academic', 'completed'),
('Sports Festival', 'Annual sports competition featuring various athletic events and team sports.', '2024-02-10', '07:00:00', 'School Grounds', 'Sports', 'completed');

-- ============================================================
--  DATABASE SETUP COMPLETE
-- ============================================================