<?php
/**
 * School Rating API
 * Handles guest rating submissions with duplicate prevention
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Include database connection
require_once '../db_connection.php';

// Get JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $data['action'];

switch ($action) {
    case 'submit_rating':
        handle_submit_rating($data, $conn);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Handle rating submission
 */
function handle_submit_rating($data, $conn) {
    // Validate required fields
    if (!isset($data['visitor_id']) || !isset($data['rating'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    $visitor_id = trim($data['visitor_id']);
    $rating = (int)$data['rating'];
    $feedback = isset($data['feedback']) ? trim($data['feedback']) : '';
    
    // Get client IP address
    $ip_address = get_client_ip();

    // Validate rating value
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
        return;
    }

    // Validate visitor ID
    if (empty($visitor_id) || strlen($visitor_id) < 10) {
        echo json_encode(['success' => false, 'message' => 'Invalid visitor ID']);
        return;
    }

    // Sanitize feedback
    $feedback = substr($feedback, 0, 1000); // Limit to 1000 characters
    $feedback = htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8');

    try {
        // Check for duplicate rating (same visitor_id and IP)
        $check_sql = "SELECT id FROM school_ratings WHERE visitor_id = ? AND ip_address = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('ss', $visitor_id, $ip_address);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'You have already rated our website']);
            return;
        }

        // Insert new rating
        $insert_sql = "INSERT INTO school_ratings (visitor_id, ip_address, rating, feedback) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param('ssis', $visitor_id, $ip_address, $rating, $feedback);
        
        if ($insert_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Thank you for your feedback!'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save rating']);
        }
        
    } catch (Exception $e) {
        error_log('Rating submission error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}

/**
 * Get client IP address
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
?>
