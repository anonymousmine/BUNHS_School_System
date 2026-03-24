<?php
/**
 * search_api.php - Advanced Search and Filter API
 * Provides search functionality across all admin modules
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../../session_config.php';
require_once '../../db_connection.php';

// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'sub-admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate CSRF token
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

    $action = $_GET['action'] ?? $_POST['action'] ?? 'search';
    $query = trim($_GET['query'] ?? $_POST['query'] ?? '');
    $module = $_GET['module'] ?? $_POST['module'] ?? 'all';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'Search query is required']);
        exit();
    }

    switch ($action) {
        case 'search':
            echo json_encode(performSearch($conn, $query, $module, $limit, $offset));
            break;
            
        case 'suggestions':
            echo json_encode(getSearchSuggestions($conn, $query, $module));
            break;
            
        case 'advanced_filter':
            echo json_encode(performAdvancedFilter($conn, $_GET));
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Search API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
} finally {
    if (isset($conn)) $conn->close();
}

/**
 * Perform search across specified modules
 */
function performSearch($conn, $query, $module, $limit, $offset) {
    $results = [];
    $searchTerm = '%' . $query . '%';
    
    // Search students
    if ($module === 'all' || $module === 'students') {
        $stmt = $conn->prepare(
            "SELECT 'student' as type, id, CONCAT(first_name, ' ', last_name) as title, 
                    lrn as subtitle, email as detail, 'students.php' as link
             FROM students 
             WHERE (first_name LIKE ? OR last_name LIKE ? OR lrn LIKE ? OR email LIKE ?)
             ORDER BY last_name, first_name
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ssssii', $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }
    
    // Search teachers
    if ($module === 'all' || $module === 'teachers') {
        $stmt = $conn->prepare(
            "SELECT 'teacher' as type, teacher_id as id, CONCAT(first_name, ' ', last_name) as title,
                    teacher_id as subtitle, email as detail, 'teachers.php' as link
             FROM teachers 
             WHERE (first_name LIKE ? OR last_name LIKE ? OR teacher_id LIKE ? OR email LIKE ?)
             ORDER BY last_name, first_name
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ssssii', $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }
    
    // Search clubs
    if ($module === 'all' || $module === 'clubs') {
        $stmt = $conn->prepare(
            "SELECT 'club' as type, id, club_name as title, 
                    adviser as subtitle, description as detail, 'clubs.php' as link
             FROM clubs 
             WHERE (club_name LIKE ? OR adviser LIKE ? OR description LIKE ?)
             ORDER BY club_name
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('sssii', $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }
    
    // Search finance records
    if ($module === 'all' || $module === 'finance') {
        $stmt = $conn->prepare(
            "SELECT 'finance' as type, id, CONCAT('Payment - ', description) as title,
                    DATE_FORMAT(transaction_date, '%M %d, %Y') as subtitle, 
                    CONCAT('₱', FORMAT(amount, 2)) as detail, 'finance.php' as link
             FROM finance_records 
             WHERE (description LIKE ? OR reference_number LIKE ?)
             ORDER BY transaction_date DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ssii', $searchTerm, $searchTerm, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }
    
    return [
        'success' => true,
        'data' => $results,
        'total' => count($results),
        'query' => $query,
        'module' => $module
    ];
}

/**
 * Get search suggestions
 */
function getSearchSuggestions($conn, $query, $module) {
    $suggestions = [];
    $searchTerm = $query . '%';
    
    // Get student name suggestions
    if ($module === 'all' || $module === 'students') {
        $stmt = $conn->prepare(
            "SELECT DISTINCT CONCAT(first_name, ' ', last_name) as suggestion
             FROM students 
             WHERE CONCAT(first_name, ' ', last_name) LIKE ?
             LIMIT 5"
        );
        $stmt->bind_param('s', $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = [
                'text' => $row['suggestion'],
                'type' => 'student',
                'icon' => 'fa-user-graduate'
            ];
        }
        $stmt->close();
    }
    
    // Get teacher name suggestions
    if ($module === 'all' || $module === 'teachers') {
        $stmt = $conn->prepare(
            "SELECT DISTINCT CONCAT(first_name, ' ', last_name) as suggestion
             FROM teachers 
             WHERE CONCAT(first_name, ' ', last_name) LIKE ?
             LIMIT 5"
        );
        $stmt->bind_param('s', $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = [
                'text' => $row['suggestion'],
                'type' => 'teacher',
                'icon' => 'fa-chalkboard-teacher'
            ];
        }
        $stmt->close();
    }
    
    return [
        'success' => true,
        'data' => $suggestions
    ];
}

/**
 * Perform advanced filtering
 */
function performAdvancedFilter($conn, $filters) {
    $results = [];
    $conditions = [];
    $params = [];
    $types = '';
    
    // Build dynamic query based on filters
    $baseQuery = "SELECT * FROM students WHERE 1=1";
    
    if (!empty($filters['status'])) {
        $conditions[] = "status = ?";
        $params[] = $filters['status'];
        $types .= 's';
    }
    
    if (!empty($filters['section'])) {
        $conditions[] = "section LIKE ?";
        $params[] = '%' . $filters['section'] . '%';
        $types .= 's';
    }
    
    if (!empty($filters['grade_level'])) {
        $conditions[] = "grade_level = ?";
        $params[] = $filters['grade_level'];
        $types .= 's';
    }
    
    if (!empty($filters['date_from'])) {
        $conditions[] = "date_enrolled >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (!empty($filters['date_to'])) {
        $conditions[] = "date_enrolled <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    if (!empty($conditions)) {
        $baseQuery .= " AND " . implode(' AND ', $conditions);
    }
    
    $baseQuery .= " ORDER BY last_name, first_name LIMIT 50";
    
    $stmt = $conn->prepare($baseQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
    
    return [
        'success' => true,
        'data' => $results,
        'total' => count($results),
        'filters' => $filters
    ];
}
?>
