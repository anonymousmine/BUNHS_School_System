<?php
// ============================================================
//  AJAX HANDLERS FOR DYNAMIC FEATURES
//  Include this file in your main PHP files
// ============================================================

include 'db_connection.php';
include 'enhanced_functions.php';

// Include event functions
require_once 'event_functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// ============================================================
//  MAIN AJAX ROUTER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display errors in output
    
    try {
        switch ($action) {
        // Event Management
        case 'get_all_events':
            echo json_encode(['status' => 'success', 'events' => getAllEventsEnhanced()]);
            break;
            
        case 'search_events':
            $criteria = [
                'search' => $_POST['search'] ?? '',
                'categories' => $_POST['categories'] ?? [],
                'date_from' => $_POST['date_from'] ?? '',
                'date_to' => $_POST['date_to'] ?? '',
                'official_only' => isset($_POST['official_only']),
                'multi_day_only' => isset($_POST['multi_day_only']),
                'team_events_only' => isset($_POST['team_events_only']),
                'sort' => $_POST['sort'] ?? 'date-asc',
                'limit' => intval($_POST['limit'] ?? 50)
            ];
            echo json_encode(['status' => 'success', 'events' => searchEventsAdvanced($criteria)]);
            break;
            
        case 'get_event_details':
            $eventId = intval($_POST['event_id']);
            $event = getEventDetailsEnhanced($eventId);
            if ($event) {
                echo json_encode(['status' => 'success', 'event' => $event]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Event not found']);
            }
            break;
            
        // Analytics
        case 'get_analytics':
            $startDate = $_POST['start_date'] ?? null;
            $endDate = $_POST['end_date'] ?? null;
            echo json_encode(['status' => 'success', 'analytics' => getEventAnalyticsData($startDate, $endDate)]);
            break;
            
        case 'get_category_stats':
            $categoryId = $_POST['category_id'] ?? null;
            echo json_encode(['status' => 'success', 'stats' => getCategoryStats($categoryId)]);
            break;
            
        // Reminders
        case 'create_reminder':
            $userId = $_POST['user_id'] ?? 'default_user';
            $eventId = intval($_POST['event_id']);
            $reminderType = $_POST['reminder_type'];
            $reminderTime = $_POST['reminder_time'];
            echo json_encode(createEventReminder($userId, $eventId, $reminderType, $reminderTime));
            break;
            
        case 'get_user_reminders':
            $userId = $_POST['user_id'] ?? 'default_user';
            $limit = intval($_POST['limit'] ?? 10);
            echo json_encode(['status' => 'success', 'reminders' => getUserReminders($userId, $limit)]);
            break;
            
        case 'get_filtered_events':
            $category = $_POST['category'] ?? 'all';
            $official = isset($_POST['official']) && $_POST['official'] === '1';
            $page = intval($_POST['page'] ?? 1);
            $limit = intval($_POST['limit'] ?? 20); // Changed to 20 for 2-column layout
            $offset = ($page - 1) * $limit;
            
            // Get all events first
            $all_events = get_upcoming_events($conn, 100);
            
            // Apply filters
            $filtered_events = [];
            foreach ($all_events as $event) {
                $category_match = ($category === 'all') || (isset($event['category']) && $event['category'] === $category);
                $official_match = !$official || (isset($event['is_official']) && $event['is_official'] == 1);
                
                if ($category_match && $official_match) {
                    $filtered_events[] = $event;
                }
            }
            
            $total_events = count($filtered_events);
            $total_pages = ceil($total_events / $limit);
            $paginated_events = array_slice($filtered_events, $offset, $limit);
            
            echo json_encode([
                'status' => 'success', 
                'events' => $paginated_events,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_events' => $total_events,
                    'per_page' => $limit
                ]
            ]);
            break;
            
        case 'get_upcoming_events':
            $page = intval($_POST['page'] ?? 1);
            $limit = intval($_POST['limit'] ?? 20); // Changed to 20 for 2-column layout
            $offset = ($page - 1) * $limit;
            
            $events = get_upcoming_events($conn, $limit * 3); // Get more for pagination
            $total_events = count($events);
            $total_pages = ceil($total_events / $limit);
            $paginated_events = array_slice($events, $offset, $limit);
            
            echo json_encode([
                'status' => 'success', 
                'events' => $paginated_events,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_events' => $total_events,
                    'per_page' => $limit
                ]
            ]);
            break;
            
        case 'delete_reminder':
            $reminderId = intval($_POST['reminder_id']);
            $userId = $_POST['user_id'] ?? 'default_user';
            $success = deleteReminder($reminderId, $userId);
            echo json_encode(['status' => $success ? 'success' : 'error']);
            break;
            
        case 'mark_reminder_notified':
            $reminderId = intval($_POST['reminder_id']);
            $success = markReminderNotified($reminderId);
            echo json_encode(['status' => $success ? 'success' : 'error']);
            break;
            
        // Event Registration
        case 'register_for_event':
            $userId = $_POST['user_id'] ?? 'default_user';
            $eventId = intval($_POST['event_id']);
            $notes = $_POST['notes'] ?? '';
            echo json_encode(registerForEvent($userId, $eventId, $notes));
            break;
            
        case 'get_event_registrations':
            $eventId = intval($_POST['event_id'] ?? 0);
            echo json_encode(['status' => 'success', 'event' => getEventDetailsEnhanced($eventId)]);
            break;
            
        case 'get_events':
            $criteria = $_POST['criteria'] ?? [];
            if (is_string($criteria)) {
                $criteria = json_decode($criteria, true) ?: [];
            }
            
            // If date range is specified, use it
            if (isset($criteria['date_from']) && isset($criteria['date_to'])) {
                $sql = "SELECT * FROM events WHERE event_date BETWEEN ? AND ? ORDER BY event_date, event_start_time";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $criteria['date_from'], $criteria['date_to']);
                $stmt->execute();
                $result = $stmt->get_result();
                $events = [];
                while ($row = $result->fetch_assoc()) {
                    $events[] = $row;
                }
                $stmt->close();
                echo json_encode(['status' => 'success', 'events' => $events]);
            } else {
                // Default: get all events
                echo json_encode(['status' => 'success', 'events' => getAllEventsEnhanced()]);
            }
            break;
            
        case 'get_announcements':
            // Placeholder for announcements - implement as needed
            echo json_encode(['status' => 'success', 'announcements' => []]);
            break;
            
        case 'get_featured':
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT * FROM events WHERE event_date >= ? ORDER BY event_date ASC LIMIT 1");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $featured_event = $result->fetch_assoc();
            $stmt->close();
            echo json_encode(['status' => 'success', 'featured_event' => $featured_event]);
            break;
            
        // Categories
        case 'get_categories':
            echo json_encode(['status' => 'success', 'categories' => getAllCategories()]);
            break;
            
        // Templates
        case 'get_templates':
            $categoryId = $_POST['category_id'] ?? null;
            echo json_encode(['status' => 'success', 'templates' => getEventTemplates($categoryId)]);
            break;
            
        case 'create_from_template':
            $templateId = intval($_POST['template_id']);
            $eventDate = $_POST['event_date'];
            $title = $_POST['title'] ?? null;
            $description = $_POST['description'] ?? null;
            echo json_encode(createEventFromTemplate($templateId, $eventDate, $title, $description));
            break;
            
        // User Preferences
        case 'get_notification_preferences':
            $userId = $_POST['user_id'] ?? 'default_user';
            echo json_encode(['status' => 'success', 'preferences' => getUserNotificationPreferences($userId)]);
            break;
            
        case 'update_notification_preferences':
            $userId = $_POST['user_id'] ?? 'default_user';
            $preferences = [
                'email_notifications' => isset($_POST['email_notifications']),
                'browser_notifications' => isset($_POST['browser_notifications']),
                'sound_notifications' => isset($_POST['sound_notifications']),
                'reminder_default' => $_POST['reminder_default'] ?? '1hour',
                'categories_to_notify' => $_POST['categories_to_notify'] ?? null
            ];
            $success = updateUserNotificationPreferences($userId, $preferences);
            echo json_encode(['status' => $success ? 'success' : 'error']);
            break;
            
        // Conflict Detection
        case 'check_conflicts':
            $eventData = [
                'event_date' => $_POST['event_date'],
                'event_start_time' => $_POST['event_start_time'] ?? null,
                'event_end_time' => $_POST['event_end_time'] ?? null,
                'location' => $_POST['location'] ?? null
            ];
            $excludeEventId = intval($_POST['exclude_event_id'] ?? 0);
            echo json_encode(['status' => 'success', 'conflicts' => checkEventConflicts($eventData, $excludeEventId)]);
            break;
            
        // Export Functions
        case 'export_events':
            $format = $_POST['format'] ?? 'csv';
            $criteria = json_decode($_POST['criteria'] ?? '{}', true);
            $events = searchEventsAdvanced($criteria);
            
            switch ($format) {
                case 'csv':
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="events.csv"');
                    echo generateCSV($events);
                    break;
                case 'ics':
                    header('Content-Type: text/calendar');
                    header('Content-Disposition: attachment; filename="events.ics"');
                    echo generateICS($events);
                    break;
                default:
                    echo json_encode(['status' => 'error', 'message' => 'Unsupported format']);
            }
            exit;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $action]);
    }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}

// ============================================================
//  HELPER FUNCTIONS
// ============================================================

function getAllEventsEnhanced() {
    global $conn;
    
    $result = $conn->query("
        SELECT * FROM event_details_view 
        ORDER BY event_date ASC
    ");
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    return $events;
}

function getEventDetailsEnhanced($eventId) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM event_details_view WHERE id = ?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $event = $result->fetch_assoc();
    $stmt->close();
    
    if ($event) {
        // Add additional data
        $event['registrations'] = getEventRegistrations($eventId);
        $event['view_stats'] = getEventViewStats($eventId);
        $event['conflicts'] = checkEventConflicts([
            'event_date' => $event['event_date'],
            'event_start_time' => $event['event_start_time'],
            'event_end_time' => $event['event_end_time'],
            'location' => $event['location']
        ], $eventId);
    }
    
    return $event;
}

function generateCSV($events) {
    $headers = ['ID', 'Title', 'Description', 'Date', 'Start Time', 'End Time', 'Category', 'Location', 'Duration (Days)', 'Official', 'Source', 'Created At'];
    $csv = implode(',', $headers) . "\n";
    
    foreach ($events as $event) {
        $row = [
            $event['id'],
            '"' . str_replace('"', '""', $event['title']) . '"',
            '"' . str_replace('"', '""', $event['description'] ?? '') . '"',
            $event['event_date'],
            $event['event_start_time'] ?? '',
            $event['event_end_time'] ?? '',
            '"' . $event['category'] . '"',
            '"' . ($event['location'] ?? '') . '"',
            $event['event_days'],
            $event['is_official'] ? 'Yes' : 'No',
            '"' . ($event['source'] ?? '') . '"',
            $event['created_at']
        ];
        $csv .= implode(',', $row) . "\n";
    }
    
    return $csv;
}

function generateICS($events) {
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//BUNHS School System//Events Calendar//EN\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    
    foreach ($events as $event) {
        $startDate = new DateTime($event['event_date']);
        $endDate = new DateTime($event['event_date']);
        $endDate->add(new DateInterval('P' . ($event['event_days'] - 1) . 'D'));
        
        $startTime = $event['event_start_time'] ? new DateTime($event['event_start_time']) : new DateTime('09:00:00');
        $endTime = $event['event_end_time'] ? new DateTime($event['event_end_time']) : new DateTime('17:00:00');
        
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:event-" . $event['id'] . "@bunhs.edu.ph\r\n";
        $ics .= "DTSTART:" . $startDate->format('Ymd') . 'T' . $startTime->format('His') . "\r\n";
        $ics .= "DTEND:" . $endDate->format('Ymd') . 'T' . $endTime->format('His') . "\r\n";
        $ics .= "SUMMARY:" . escapeICS($event['title']) . "\r\n";
        
        if ($event['description']) {
            $ics .= "DESCRIPTION:" . escapeICS($event['description']) . "\r\n";
        }
        
        if ($event['location']) {
            $ics .= "LOCATION:" . escapeICS($event['location']) . "\r\n";
        }
        
        if ($event['is_official']) {
            $ics .= "PRIORITY:1\r\n";
        }
        
        $ics .= "END:VEVENT\r\n";
    }
    
    $ics .= "END:VCALENDAR\r\n";
    return $ics;
}

function escapeICS($text) {
    return str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], $text);
}

?>
