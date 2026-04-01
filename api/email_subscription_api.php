<?php
header('Content-Type: application/json');
include '../db_connection.php';
include '../email_notification_functions.php';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
        exit;
    }
    
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'subscribe':
            $email = $data['email'] ?? '';
            if (empty($email)) {
                echo json_encode(['status' => 'error', 'message' => 'Email is required']);
                exit;
            }
            
            $result = save_email_subscriber($conn, $email);
            echo json_encode($result);
            break;
            
        case 'unsubscribe':
            $email = $data['email'] ?? '';
            if (empty($email)) {
                echo json_encode(['status' => 'error', 'message' => 'Email is required']);
                exit;
            }
            
            // Deactivate subscription
            $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
            $stmt = $conn->prepare("UPDATE email_subscribers SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
            $stmt->bind_param("s", $email);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success && $stmt->affected_rows > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Email unsubscribed successfully']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Email not found or already unsubscribed']);
            }
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
