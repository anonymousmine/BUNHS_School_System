-- ============================================================
--  DEPED CALENDAR INTEGRATION - DATABASE SETUP (FIXED)
--  Fixed MySQL index length issues
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

-- 2. Event reminders table for notification system
CREATE TABLE IF NOT EXISTS event_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    event_id INT NOT NULL,
    reminder_time DATETIME NOT NULL,
    reminder_type ENUM('15min', '30min', '1hour', '1day', '2days', '1week') NOT NULL,
    is_notified TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_user_reminders (user_id, reminder_time),
    INDEX idx_event_reminders (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Event analytics cache table for performance
CREATE TABLE IF NOT EXISTS event_analytics_cache (
    cache_key VARCHAR(100) PRIMARY KEY,
    cache_data JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Event categories table for dynamic category management
CREATE TABLE IF NOT EXISTS event_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#1abc9c',
    icon VARCHAR(50) DEFAULT 'bi-calendar-event',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Insert default categories if they don't exist
INSERT IGNORE INTO event_categories (name, color, icon, sort_order) VALUES
('Academic', '#1abc9c', 'bi-book', 1),
('Sports', '#e74c3c', 'bi-trophy', 2),
('Cultural', '#9b59b6', 'bi-palette', 3),
('Workshops', '#3498db', 'bi-tools', 4),
('Conferences', '#2c3e50', 'bi-people', 5),
('Academic Calendar', '#16a085', 'bi-calendar-check', 6),
('Holidays', '#c0392b', 'bi-umbrella', 7),
('Health & Nutrition', '#27ae60', 'bi-heart-pulse', 8),
('Governance & Elections', '#8e44ad', 'bi-bank', 9),
('Assessments', '#f39c12', 'bi-clipboard-check', 10),
('Professional Development', '#2980b9', 'bi-mortarboard', 11),
('Remedial & Intervention', '#d35400', 'bi-arrow-clockwise', 12);

-- 6. Event subscriptions table for user preferences
CREATE TABLE IF NOT EXISTS event_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    category_id INT,
    is_official_only TINYINT(1) DEFAULT 0,
    notification_preferences JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_subscription (user_id, category_id),
    INDEX idx_user_subscriptions (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Event views tracking for analytics
CREATE TABLE IF NOT EXISTS event_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id VARCHAR(50),
    view_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_views (event_id),
    INDEX idx_view_date (view_date),
    INDEX idx_user_views (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Event registrations tracking
CREATE TABLE IF NOT EXISTS event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'cancelled', 'attended') DEFAULT 'registered',
    notes TEXT,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_registration (event_id, user_id),
    INDEX idx_event_registrations (event_id),
    INDEX idx_user_registrations (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Event conflicts detection table
CREATE TABLE IF NOT EXISTS event_conflicts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event1_id INT NOT NULL,
    event2_id INT NOT NULL,
    conflict_type ENUM('time_overlap', 'resource_conflict', 'double_booking') NOT NULL,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    resolved TINYINT(1) DEFAULT 0,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by VARCHAR(50) NULL,
    FOREIGN KEY (event1_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (event2_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_conflict_events (event1_id, event2_id),
    INDEX idx_conflict_resolved (resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Event templates for recurring events
CREATE TABLE IF NOT EXISTS event_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    default_duration INT DEFAULT 1,
    default_location VARCHAR(255),
    recurrence_pattern JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_by VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES event_categories(id),
    INDEX idx_template_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Insert some useful event templates
INSERT IGNORE INTO event_templates (name, category_id, description, default_duration, recurrence_pattern) VALUES
('Monthly Faculty Meeting', 11, 'Regular faculty meeting for academic coordination', 1, '{"type": "monthly", "day_of_week": 1, "week_of_month": 1}'),
('Weekly Sports Practice', 2, 'Regular practice session for sports teams', 1, '{"type": "weekly", "days": [2, 4]}'),
('Quarterly Assessment', 10, 'Regular quarterly assessment period', 5, '{"type": "quarterly"}'),
('Parent-Teacher Conference', 11, 'Regular parent-teacher meetings', 1, '{"type": "monthly", "day_of_week": 5, "week_of_month": 2}');

-- 12. Create indexes for better performance (FIXED - removed problematic search index)
CREATE INDEX IF NOT EXISTS idx_events_date_category ON events(event_date, category);
CREATE INDEX IF NOT EXISTS idx_events_official ON events(is_official, event_date);
-- REMOVED: idx_events_date_range (duplicate column issue)
CREATE INDEX IF NOT EXISTS idx_events_team_based ON events(team_based, event_date);

-- Alternative search indexes that won't exceed length limits
CREATE INDEX IF NOT EXISTS idx_events_title ON events(title(100));
CREATE INDEX IF NOT EXISTS idx_events_description ON events(description(100));
CREATE INDEX IF NOT EXISTS idx_events_location ON events(location(100));

-- 13. Create stored procedures for analytics
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS GetEventAnalytics(IN start_date DATE, IN end_date DATE)
BEGIN
    SELECT 
        COUNT(*) as total_events,
        COUNT(CASE WHEN event_date >= CURDATE() THEN 1 END) as upcoming_events,
        COUNT(CASE WHEN is_official = 1 THEN 1 END) as official_events,
        COUNT(CASE WHEN event_days > 1 THEN 1 END) as multi_day_events,
        ROUND(COUNT(CASE WHEN is_official = 1 THEN 1 END) * 100.0 / COUNT(*), 2) as official_percentage,
        ROUND(COUNT(CASE WHEN event_days > 1 THEN 1 END) * 100.0 / COUNT(*), 2) as multi_day_percentage
    FROM events 
    WHERE event_date BETWEEN start_date AND end_date;
END//

CREATE PROCEDURE IF NOT EXISTS GetCategoryBreakdown(IN start_date DATE, IN end_date DATE)
BEGIN
    SELECT 
        ec.name as category,
        ec.color,
        COUNT(e.id) as total_count,
        COUNT(CASE WHEN e.is_official = 1 THEN 1 END) as official_count,
        COUNT(CASE WHEN e.event_date >= CURDATE() THEN 1 END) as upcoming_count,
        COUNT(CASE WHEN e.event_days > 1 THEN 1 END) as multi_day_count
    FROM events e
    LEFT JOIN event_categories ec ON e.category = ec.name
    WHERE e.event_date BETWEEN start_date AND end_date
    GROUP BY ec.name, ec.color
    ORDER BY total_count DESC;
END//

CREATE PROCEDURE IF NOT EXISTS GetMonthlyTrends(IN months_back INT)
BEGIN
    SELECT 
        DATE_FORMAT(event_date, '%Y-%m') as month_key,
        COUNT(*) as total_events,
        COUNT(CASE WHEN is_official = 1 THEN 1 END) as official_events
    FROM events 
    WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL months_back MONTH)
    GROUP BY DATE_FORMAT(event_date, '%Y-%m')
    ORDER BY month_key;
END//

DELIMITER ;

-- 14. Create triggers for analytics updates
DELIMITER //

CREATE TRIGGER IF NOT EXISTS update_analytics_cache_after_insert
AFTER INSERT ON events
FOR EACH ROW
BEGIN
    DELETE FROM event_analytics_cache WHERE cache_key LIKE 'analytics_%';
END//

CREATE TRIGGER IF NOT EXISTS update_analytics_cache_after_update
AFTER UPDATE ON events
FOR EACH ROW
BEGIN
    DELETE FROM event_analytics_cache WHERE cache_key LIKE 'analytics_%';
END//

CREATE TRIGGER IF NOT EXISTS update_analytics_cache_after_delete
AFTER DELETE ON events
FOR EACH ROW
BEGIN
    DELETE FROM event_analytics_cache WHERE cache_key LIKE 'analytics_%';
END//

DELIMITER ;

-- 15. Create view for enhanced event listings (FIXED collation issue)
CREATE OR REPLACE VIEW event_details_view AS
SELECT 
    e.id,
    e.title,
    e.description,
    e.event_date,
    e.event_start_time,
    e.event_end_time,
    e.event_days,
    e.category,
    e.location,
    e.organizer_name,
    e.organizer_position,
    e.organizer_contact,
    e.team_based,
    e.source,
    e.is_official,
    e.image,
    e.created_at,
    ec.color as category_color,
    ec.icon as category_icon,
    CASE 
        WHEN e.event_date >= CURDATE() THEN 'upcoming'
        WHEN e.event_date < CURDATE() THEN 'past'
        ELSE 'ongoing'
    END as event_status,
    CASE 
        WHEN e.event_date = CURDATE() THEN 'today'
        WHEN e.event_date = CURDATE() + INTERVAL 1 DAY THEN 'tomorrow'
        ELSE NULL
    END as special_status,
    DATEDIFF(e.event_date, CURDATE()) as days_until,
    (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id AND er.status = 'registered' COLLATE utf8mb4_unicode_ci) as registration_count
FROM events e
LEFT JOIN event_categories ec ON e.category = ec.name COLLATE utf8mb4_unicode_ci;

-- 16. Create function for conflict detection
DELIMITER //

CREATE FUNCTION IF NOT EXISTS CheckEventConflict(
    p_event_date DATE,
    p_start_time TIME,
    p_end_time TIME,
    p_location VARCHAR(255),
    p_exclude_event_id INT
) RETURNS BOOLEAN
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE conflict_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO conflict_count
    FROM events 
    WHERE event_date = p_event_date 
    AND id != p_exclude_event_id
    AND (
        (event_start_time <= p_start_time AND event_end_time >= p_start_time) OR
        (event_start_time <= p_end_time AND event_end_time >= p_end_time) OR
        (event_start_time >= p_start_time AND event_end_time <= p_end_time)
    )
    AND (p_location IS NOT NULL AND location = p_location OR p_location IS NULL);
    
    RETURN conflict_count > 0;
END//

DELIMITER ;

-- 17. Populate existing events with proper categories if needed (FIXED collation issue)
UPDATE events e 
SET e.category = (
    SELECT name FROM event_categories ec 
    WHERE ec.name = e.category COLLATE utf8mb4_unicode_ci
    LIMIT 1
) 
WHERE e.category IS NOT NULL AND e.category != '';

-- 18. Create user notification preferences table
CREATE TABLE IF NOT EXISTS user_notification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL UNIQUE,
    email_notifications TINYINT(1) DEFAULT 1,
    browser_notifications TINYINT(1) DEFAULT 1,
    sound_notifications TINYINT(1) DEFAULT 1,
    reminder_default ENUM('15min', '30min', '1hour', '1day', '2days', '1week') DEFAULT '1hour',
    categories_to_notify JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Insert default notification preferences
INSERT IGNORE INTO user_notification_preferences (user_id) 
SELECT DISTINCT 'default_user' WHERE NOT EXISTS (SELECT 1 FROM user_notification_preferences WHERE user_id = 'default_user');

-- 20. Final verification query
SELECT 
    'Database setup completed successfully!' as status,
    (SELECT COUNT(*) FROM events) as total_events,
    (SELECT COUNT(*) FROM event_categories) as total_categories,
    (SELECT COUNT(*) FROM event_reminders) as total_reminders,
    (SELECT COUNT(*) FROM event_analytics_cache) as cached_analytics;
