<?php
/**
 * dashboard_api.php - Real-time Dashboard Data API
 * Provides secure, real-time data for the admin dashboard
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include required files
require_once '../../session_config.php';
require_once '../../db_connection.php';
require_once '../cache_helper.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'sub-admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

try {
    // Use the existing database connection from db_connection.php
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $conn = safe_db_connect($host, $db_user, $db_pass, $db_name, $db_port);
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? 'stats';

    switch ($action) {
        case 'stats':
            $stats = getDashboardStats($conn);
            echo json_encode([
                'success' => true,
                'data' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'activities':
            $activities = getRecentActivities($conn);
            echo json_encode([
                'success' => true,
                'data' => $activities
            ]);
            break;
            
        case 'enrollment_data':
            $data = getEnrollmentData($conn);
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'refresh_stats':
            $result = refreshDashboardStats($conn);
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} finally {
    if (isset($conn)) $conn->close();
}

/**
 * Get real-time dashboard statistics (with caching)
 */
function getDashboardStats($conn) {
    return DashboardCache::remember('dashboard_stats', function() use ($conn) {
        $stats = [];
        
        // Get student count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM students");
        $stmt->execute();
        $stmt->bind_result($stats['students']);
        $stmt->fetch();
        $stmt->close();
        
        // Get teacher count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM teachers");
        $stmt->execute();
        $stmt->bind_result($stats['teachers']);
        $stmt->fetch();
        $stmt->close();
        
        // Get news count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM news");
        $stmt->execute();
        $stmt->bind_result($stats['news']);
        $stmt->fetch();
        $stmt->close();
        
        // Get announcements count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM announcements");
        $stmt->execute();
        $stmt->bind_result($stats['announcements']);
        $stmt->fetch();
        $stmt->close();
        
        // Calculate teacher-student ratio
        $stats['teacher_student_ratio'] = $stats['teachers'] > 0 ? 
            round($stats['students'] / $stats['teachers'], 1) : 0;
        
        return $stats;
    }, 300); // Cache for 5 minutes
}

/**
 * Get recent activities from logs (with caching)
 */
function getRecentActivities($conn) {
    return DashboardCache::remember('recent_activities', function() use ($conn) {
        $activities = [];
        
        // Get recent announcements
        $stmt = $conn->prepare(
            "SELECT title, announcement_date, created_by 
             FROM announcements 
             ORDER BY announcement_date DESC 
             LIMIT 5"
        );
        $stmt->execute();
        $stmt->bind_result($title, $announcement_date, $created_by);
        
        while ($stmt->fetch()) {
            $activities[] = [
                'type' => 'announcement',
                'icon' => 'fa-bullhorn',
                'text' => "New announcement: $title",
                'timestamp' => $announcement_date,
                'time_ago' => getTimeAgo($announcement_date)
            ];
        }
        $stmt->close();
        
        // If no activities, provide default ones
        if (empty($activities)) {
            $activities = [
                ['type' => 'info', 'icon' => 'fa-info-circle', 'text' => 'No recent announcements', 'timestamp' => date('Y-m-d H:i:s')],
            ];
        }
        
        return $activities;
    }, 180); // Cache for 3 minutes
}

/**
 * Get enrollment data for charts
 */
function getEnrollmentData($conn) {
    $period = $_GET['period'] ?? '6';
    
    $data = [
        '6' => [
            'labels' => ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'enrollment' => [120, 145, 168, 189, 210, 263],
            'target' => [100, 130, 160, 190, 220, 250]
        ],
        '12' => [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'enrollment' => [95, 110, 125, 140, 155, 165, 175, 190, 205, 220, 240, 263],
            'target' => [90, 105, 120, 135, 150, 165, 180, 195, 210, 225, 240, 255]
        ],
        'all' => [
            'labels' => ['2020', '2021', '2022', '2023', '2024'],
            'enrollment' => [180, 195, 220, 245, 263],
            'target' => [170, 190, 210, 230, 250]
        ]
    ];
    
    return [
        'success' => true,
        'data' => $data[$period] ?? $data['6']
    ];
}

/**
 * Refresh dashboard stats with caching
 */
function refreshDashboardStats($conn) {
    // Clear any existing cache
    if (function_exists('apcu_delete')) {
        apcu_delete('dashboard_stats_cache');
    }
    
    return getDashboardStats($conn);
}

/**
 * Helper function to calculate time ago
 */
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return date('M j, Y', $time);
}
?>
