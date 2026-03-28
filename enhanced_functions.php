<?php
// ============================================================
//  ENHANCED EVENT MANAGEMENT FUNCTIONS
//  These functions work with the enhanced database structure
// ============================================================

include 'db_connection.php';

// ============================================================
//  ANALYTICS FUNCTIONS
// ============================================================

function getEventAnalyticsData($startDate = null, $endDate = null) {
    global $conn;
    
    $startDate = $startDate ?? date('Y-m-01'); // First day of current month
    $endDate = $endDate ?? date('Y-m-t');     // Last day of current month
    
    // Check cache first
    $cacheKey = 'analytics_' . $startDate . '_' . $endDate;
    $cached = getCachedAnalytics($cacheKey);
    
    if ($cached) {
        return $cached;
    }
    
    // Get summary statistics
    $stmt = $conn->prepare("CALL GetEventAnalytics(?, ?)");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    $stmt->close();
    
    // Get category breakdown
    $stmt = $conn->prepare("CALL GetCategoryBreakdown(?, ?)");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
    
    // Get monthly trends
    $stmt = $conn->prepare("CALL GetMonthlyTrends(6)");
    $stmt->execute();
    $result = $stmt->get_result();
    $trends = [];
    while ($row = $result->fetch_assoc()) {
        $trends[] = $row;
    }
    $stmt->close();
    
    $analytics = [
        'summary' => $summary,
        'categories' => $categories,
        'trends' => $trends,
        'date_range' => ['start' => $startDate, 'end' => $endDate],
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    // Cache the results
    cacheAnalytics($cacheKey, $analytics, 3600); // Cache for 1 hour
    
    return $analytics;
}

function getCachedAnalytics($cacheKey) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT cache_data FROM event_analytics_cache WHERE cache_key = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $cacheKey);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return json_decode($row['cache_data'], true);
    }
    
    return null;
}

function cacheAnalytics($cacheKey, $data, $ttlSeconds) {
    global $conn;
    
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $jsonData = json_encode($data);
    
    $stmt = $conn->prepare("INSERT INTO event_analytics_cache (cache_key, cache_data, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = VALUES(expires_at)");
    $stmt->bind_param("sss", $cacheKey, $jsonData, $expiresAt);
    $stmt->execute();
    $stmt->close();
}

// ============================================================
//  REMINDER FUNCTIONS
// ============================================================

function createEventReminder($userId, $eventId, $reminderType, $reminderTime) {
    global $conn;
    
    // Check if reminder already exists
    $stmt = $conn->prepare("SELECT id FROM event_reminders WHERE user_id = ? AND event_id = ? AND reminder_type = ?");
    $stmt->bind_param("sis", $userId, $eventId, $reminderType);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Reminder already exists'];
    }
    
    $stmt = $conn->prepare("INSERT INTO event_reminders (user_id, event_id, reminder_time, reminder_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $userId, $eventId, $reminderTime, $reminderType);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        return ['success' => true, 'message' => 'Reminder created successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to create reminder'];
    }
}

function getUserReminders($userId, $limit = 10) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT r.*, e.title, e.event_date, e.category, e.is_official 
        FROM event_reminders r 
        JOIN events e ON r.event_id = e.id 
        WHERE r.user_id = ? AND r.is_notified = 0 AND r.reminder_time >= NOW()
        ORDER BY r.reminder_time ASC 
        LIMIT ?
    ");
    $stmt->bind_param("si", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reminders = [];
    while ($row = $result->fetch_assoc()) {
        $reminders[] = $row;
    }
    
    $stmt->close();
    return $reminders;
}

function markReminderNotified($reminderId) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE event_reminders SET is_notified = 1 WHERE id = ?");
    $stmt->bind_param("i", $reminderId);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

function deleteReminder($reminderId, $userId) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM event_reminders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("is", $reminderId, $userId);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

// ============================================================
//  EVENT CONFLICT DETECTION
// ============================================================

function checkEventConflicts($eventData, $excludeEventId = null) {
    global $conn;
    
    $conflicts = [];
    
    // Check time conflicts
    $stmt = $conn->prepare("
        SELECT id, title, event_date, event_start_time, event_end_time, location 
        FROM events 
        WHERE event_date = ? AND id != ? 
        AND (
            (event_start_time <= ? AND event_end_time >= ?) OR
            (event_start_time <= ? AND event_end_time >= ?) OR
            (event_start_time >= ? AND event_end_time <= ?)
        )
    ");
    
    $startTime = $eventData['event_start_time'] ?? '00:00:00';
    $endTime = $eventData['event_end_time'] ?? '23:59:59';
    $excludeId = $excludeEventId ?? 0;
    
    $stmt->bind_param("sisssss", $eventData['event_date'], $excludeId, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $conflicts[] = [
            'type' => 'time_overlap',
            'event' => $row,
            'severity' => 'high'
        ];
    }
    
    $stmt->close();
    
    // Check location conflicts if location is specified
    if (!empty($eventData['location'])) {
        $stmt = $conn->prepare("
            SELECT id, title, event_date, location 
            FROM events 
            WHERE event_date = ? AND id != ? AND location = ?
        ");
        
        $stmt->bind_param("sis", $eventData['event_date'], $excludeId, $eventData['location']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $conflicts[] = [
                'type' => 'resource_conflict',
                'event' => $row,
                'severity' => 'medium'
            ];
        }
        
        $stmt->close();
    }
    
    return $conflicts;
}

function logEventConflict($event1Id, $event2Id, $conflictType, $severity = 'medium') {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO event_conflicts (event1_id, event2_id, conflict_type, severity) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE severity = VALUES(severity), resolved = 0
    ");
    $stmt->bind_param("iiss", $event1Id, $event2Id, $conflictType, $severity);
    $stmt->execute();
    $stmt->close();
}

// ============================================================
//  EVENT REGISTRATION AND TRACKING
// ============================================================

function registerForEvent($userId, $eventId, $notes = '') {
    global $conn;
    
    // Check if already registered
    $stmt = $conn->prepare("SELECT id FROM event_registrations WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("si", $userId, $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Already registered for this event'];
    }
    
    $stmt = $conn->prepare("INSERT INTO event_registrations (user_id, event_id, notes) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $userId, $eventId, $notes);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Track view for analytics
        trackEventView($eventId, $userId);
        
        return ['success' => true, 'message' => 'Registration successful'];
    } else {
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

function getEventRegistrations($eventId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT user_id, registration_date, status, notes 
        FROM event_registrations 
        WHERE event_id = ? 
        ORDER BY registration_date DESC
    ");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $registrations = [];
    while ($row = $result->fetch_assoc()) {
        $registrations[] = $row;
    }
    
    $stmt->close();
    return $registrations;
}

function trackEventView($eventId, $userId = null) {
    global $conn;
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare("
        INSERT INTO event_views (event_id, user_id, ip_address, user_agent) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $eventId, $userId, $ipAddress, $userAgent);
    $stmt->execute();
    $stmt->close();
}

function getEventViewStats($eventId, $days = 30) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_views,
            COUNT(DISTINCT user_id) as unique_views,
            COUNT(DISTINCT ip_address) as unique_ips,
            DATE(view_date) as view_date
        FROM event_views 
        WHERE event_id = ? AND view_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(view_date)
        ORDER BY view_date DESC
    ");
    $stmt->bind_param("ii", $eventId, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    $stmt->close();
    return $stats;
}

// ============================================================
//  CATEGORY MANAGEMENT
// ============================================================

function getAllCategories() {
    global $conn;
    
    $result = $conn->query("SELECT * FROM event_categories WHERE is_active = 1 ORDER BY sort_order, name");
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}

function getCategoryStats($categoryId = null) {
    global $conn;
    
    if ($categoryId) {
        $stmt = $conn->prepare("
            SELECT 
                ec.name,
                ec.color,
                COUNT(e.id) as total_events,
                COUNT(CASE WHEN e.is_official = 1 THEN 1 END) as official_events,
                COUNT(CASE WHEN e.event_date >= CURDATE() THEN 1 END) as upcoming_events
            FROM event_categories ec
            LEFT JOIN events e ON ec.name = e.category
            WHERE ec.id = ? AND ec.is_active = 1
            GROUP BY ec.id, ec.name, ec.color
        ");
        $stmt->bind_param("i", $categoryId);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                ec.name,
                ec.color,
                COUNT(e.id) as total_events,
                COUNT(CASE WHEN e.is_official = 1 THEN 1 END) as official_events,
                COUNT(CASE WHEN e.event_date >= CURDATE() THEN 1 END) as upcoming_events
            FROM event_categories ec
            LEFT JOIN events e ON ec.name = e.category
            WHERE ec.is_active = 1
            GROUP BY ec.id, ec.name, ec.color
            ORDER BY total_events DESC
        ");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    $stmt->close();
    return $stats;
}

// ============================================================
//  EVENT TEMPLATES
// ============================================================

function getEventTemplates($categoryId = null) {
    global $conn;
    
    if ($categoryId) {
        $stmt = $conn->prepare("SELECT * FROM event_templates WHERE category_id = ? AND is_active = 1 ORDER BY name");
        $stmt->bind_param("i", $categoryId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM event_templates WHERE is_active = 1 ORDER BY name");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    
    $stmt->close();
    return $templates;
}

function createEventFromTemplate($templateId, $eventDate, $title = null, $description = null) {
    global $conn;
    
    // Get template details
    $stmt = $conn->prepare("SELECT * FROM event_templates WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $templateId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($template = $result->fetch_assoc()) {
        $finalTitle = $title ?? $template['name'];
        $finalDescription = $description ?? $template['description'];
        
        $stmt = $conn->prepare("
            INSERT INTO events (title, description, event_date, category, event_days, location, created_at) 
            VALUES (?, ?, ?, (SELECT name FROM event_categories WHERE id = ?), ?, ?, NOW())
        ");
        $stmt->bind_param("sssisi", $finalTitle, $finalDescription, $eventDate, $template['category_id'], $template['default_duration'], $template['default_location']);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            return ['success' => true, 'event_id' => $conn->insert_id];
        }
    }
    
    $stmt->close();
    return ['success' => false, 'message' => 'Template not found or inactive'];
}

// ============================================================
//  USER PREFERENCES
// ============================================================

function getUserNotificationPreferences($userId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM user_notification_preferences WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $preferences = $result->fetch_assoc() ?: [
        'email_notifications' => 1,
        'browser_notifications' => 1,
        'sound_notifications' => 1,
        'reminder_default' => '1hour',
        'categories_to_notify' => null
    ];
    
    $stmt->close();
    return $preferences;
}

function updateUserNotificationPreferences($userId, $preferences) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO user_notification_preferences 
        (user_id, email_notifications, browser_notifications, sound_notifications, reminder_default, categories_to_notify) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        email_notifications = VALUES(email_notifications),
        browser_notifications = VALUES(browser_notifications),
        sound_notifications = VALUES(sound_notifications),
        reminder_default = VALUES(reminder_default),
        categories_to_notify = VALUES(categories_to_notify)
    ");
    
    $stmt->bind_param("iiisss", 
        $userId, 
        $preferences['email_notifications'], 
        $preferences['browser_notifications'], 
        $preferences['sound_notifications'], 
        $preferences['reminder_default'], 
        json_encode($preferences['categories_to_notify'] ?? [])
    );
    
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

// ============================================================
//  ENHANCED EVENT SEARCH
// ============================================================

function searchEventsAdvanced($criteria) {
    global $conn;
    
    $sql = "SELECT * FROM event_details_view WHERE 1=1";
    $params = [];
    $types = '';
    
    // Search term - use individual LIKE clauses instead of combined index
    if (!empty($criteria['search'])) {
        $sql .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
        $searchTerm = '%' . $criteria['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }
    
    // Category filter
    if (!empty($criteria['categories']) && is_array($criteria['categories'])) {
        $placeholders = str_repeat('?,', count($criteria['categories']) - 1);
        $sql .= " AND category IN ($placeholders?)";
        foreach ($criteria['categories'] as $category) {
            $params[] = $category;
            $types .= 's';
        }
    }
    
    // Date range filter
    if (!empty($criteria['date_from'])) {
        $sql .= " AND event_date >= ?";
        $params[] = $criteria['date_from'];
        $types .= 's';
    }
    
    if (!empty($criteria['date_to'])) {
        $sql .= " AND event_date <= ?";
        $params[] = $criteria['date_to'];
        $types .= 's';
    }
    
    // Official events only
    if (!empty($criteria['official_only'])) {
        $sql .= " AND is_official = 1";
    }
    
    // Multi-day events only
    if (!empty($criteria['multi_day_only'])) {
        $sql .= " AND event_days > 1";
    }
    
    // Team-based events only
    if (!empty($criteria['team_events_only'])) {
        $sql .= " AND team_based = 1";
    }
    
    // Sorting
    $orderBy = 'event_date ASC';
    if (!empty($criteria['sort'])) {
        switch ($criteria['sort']) {
            case 'date-desc':
                $orderBy = 'event_date DESC';
                break;
            case 'title-asc':
                $orderBy = 'title ASC';
                break;
            case 'title-desc':
                $orderBy = 'title DESC';
                break;
            case 'category':
                $orderBy = 'category ASC, event_date ASC';
                break;
        }
    }
    $sql .= " ORDER BY $orderBy";
    
    // Limit
    if (!empty($criteria['limit'])) {
        $sql .= " LIMIT ?";
        $params[] = $criteria['limit'];
        $types .= 'i';
    }
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    $stmt->close();
    return $events;
}

// ============================================================
//  UTILITY FUNCTIONS
// ============================================================

function validateEventData($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['title'])) {
        $errors[] = 'Event title is required';
    }
    
    if (empty($data['event_date'])) {
        $errors[] = 'Event date is required';
    }
    
    if (empty($data['category'])) {
        $errors[] = 'Event category is required';
    }
    
    // Validate date
    if (!empty($data['event_date']) && !strtotime($data['event_date'])) {
        $errors[] = 'Invalid event date format';
    }
    
    // Validate time
    if (!empty($data['event_start_time']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['event_start_time'])) {
        $errors[] = 'Invalid start time format';
    }
    
    if (!empty($data['event_end_time']) && !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['event_end_time'])) {
        $errors[] = 'Invalid end time format';
    }
    
    // Validate event days
    if (!empty($data['event_days']) && (!is_numeric($data['event_days']) || $data['event_days'] < 1)) {
        $errors[] = 'Event days must be a positive number';
    }
    
    return $errors;
}

function formatEventDuration($startDate, $endDate, $startTime = null, $endTime = null) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    $interval = $start->diff($end);
    $days = $interval->days + 1;
    
    if ($days == 1) {
        $dateStr = $start->format('F j, Y');
        if ($startTime && $endTime) {
            $dateStr .= ' from ' . date('g:i A', strtotime($startTime)) . ' to ' . date('g:i A', strtotime($endTime));
        }
        return $dateStr;
    } else {
        return $start->format('F j') . ' - ' . $end->format('F j, Y') . " ($days days)";
    }
}

function getEventStatus($eventDate) {
    $today = new DateTime();
    $eventDt = new DateTime($eventDate);
    
    if ($eventDt < $today) {
        return 'past';
    } elseif ($eventDt == $today) {
        return 'today';
    } else {
        $tomorrow = new DateTime();
        $tomorrow->modify('+1 day');
        if ($eventDt == $tomorrow) {
            return 'tomorrow';
        }
        return 'upcoming';
    }
}

?>
