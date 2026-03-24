<?php

/**
 * admin_chat_api.php
 * ─────────────────────────────────────────────────────────────
 * Enhanced chat API supporting admin-to-admin messaging
 * Works alongside existing chat_api.php for student-admin communication
 *
 * Actions:
 *   fetch_admin_conversations  – list admin-to-admin conversations
 *   fetch_admin_messages      – messages for admin conversation
 *   send_admin_message        – send admin-to-admin message
 *   start_admin_conversation   – initiate new admin chat
 *   mark_admin_read          – mark admin messages as read
 *   get_online_admins         – get list of online admins
 *   update_chat_status        – update user's online status
 * ─────────────────────────────────────────────────────────────
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../session_config.php';

// Auth - only admins and sub-admins can use this API
$is_admin   = in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']);
if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
    exit;
}

include '../db_connection.php';
header('Content-Type: application/json');

$current_user_id = (int) $_SESSION['user_id'];
$current_user_type = $_SESSION['user_type'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper function to get or create admin conversation
function get_or_create_admin_conversation(mysqli $conn, int $user_a_id, int $user_b_id): int
{
    // Ensure consistent ordering (smaller ID first)
    $participant_a = min($user_a_id, $user_b_id);
    $participant_b = max($user_a_id, $user_b_id);
    
    $s = $conn->prepare(
        "SELECT id FROM admin_conversations 
         WHERE participant_a_id = ? AND participant_b_id = ? LIMIT 1"
    );
    $s->bind_param('ii', $participant_a, $participant_b);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if ($row) return (int) $row['id'];

    // Create new conversation
    $s = $conn->prepare(
        "INSERT INTO admin_conversations (participant_a_id, participant_b_id, last_message, updated_at)
         VALUES (?, ?, '', NOW())"
    );
    $s->bind_param('ii', $participant_a, $participant_b);
    $s->execute();
    $id = (int) $conn->insert_id;
    $s->close();
    return $id;
}

// Get list of available admins for chat
function get_available_admins(mysqli $conn, int $current_user_id): array
{
    $admins = [];
    
    // Get main admins
    $stmt = $conn->prepare("
        SELECT id, full_name, personal_email, 'admin' as user_type, chat_status
        FROM admin 
        WHERE id != ? 
        ORDER BY full_name
    ");
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    $stmt->close();
    
    // Get sub-admins
    $stmt = $conn->prepare("
        SELECT id, full_name, email as personal_email, 'sub-admin' as user_type, chat_status
        FROM sub_admin 
        WHERE id != ? AND status = 'approved'
        ORDER BY full_name
    ");
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    $stmt->close();
    
    return $admins;
}

// ── fetch_admin_conversations ─────────────────────────────────
if ($action === 'fetch_admin_conversations') {
    $stmt = $conn->prepare("
        SELECT ac.id, ac.participant_a_id, ac.participant_b_id, 
               ac.last_message, ac.updated_at,
               CASE 
                   WHEN ac.participant_a_id = ? THEN ac.participant_b_id
                   ELSE ac.participant_a_id
               END as other_user_id,
               (SELECT COUNT(*) FROM admin_chat_messages acm 
                WHERE acm.conversation_id = ac.id 
                  AND acm.receiver_id = ? 
                  AND acm.is_read = 0) AS unread_count,
               (SELECT sender_id FROM admin_chat_messages acm 
                WHERE acm.conversation_id = ac.id 
                ORDER BY acm.created_at DESC LIMIT 1) AS last_sender_id
        FROM admin_conversations ac
        WHERE (ac.participant_a_id = ? OR ac.participant_b_id = ?)
        ORDER BY ac.updated_at DESC
    ");
    
    $stmt->bind_param('iiii', $current_user_id, $current_user_id, $current_user_id, $current_user_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conversations = [];
    foreach ($rows as $row) {
        // Get other user's info
        $other_user_id = $row['other_user_id'];
        $other_user = null;
        
        // Check if it's admin or sub-admin
        $check_stmt = $conn->prepare("SELECT full_name, personal_email FROM admin WHERE id = ? LIMIT 1");
        $check_stmt->bind_param('i', $other_user_id);
        $check_stmt->execute();
        $admin_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($admin_result) {
            $other_user = [
                'name' => $admin_result['full_name'],
                'email' => $admin_result['personal_email'],
                'type' => 'admin'
            ];
        } else {
            $check_stmt = $conn->prepare("SELECT full_name, email FROM sub_admin WHERE id = ? LIMIT 1");
            $check_stmt->bind_param('i', $other_user_id);
            $check_stmt->execute();
            $sub_admin_result = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();
            
            if ($sub_admin_result) {
                $other_user = [
                    'name' => $sub_admin_result['full_name'],
                    'email' => $sub_admin_result['email'],
                    'type' => 'sub-admin'
                ];
            }
        }
        
        if ($other_user) {
            $conversations[] = [
                'conversation_id' => $row['id'],
                'other_user_id' => $other_user_id,
                'other_user_name' => htmlspecialchars($other_user['name'], ENT_QUOTES, 'UTF-8'),
                'other_user_type' => $other_user['type'],
                'last_message' => htmlspecialchars($row['last_message'] ?? 'No messages yet', ENT_QUOTES, 'UTF-8'),
                'unread_count' => (int) $row['unread_count'],
                'last_sender_id' => (int) $row['last_sender_id'],
                'time_ago' => time_ago($row['updated_at']),
                'avatar_letter' => strtoupper(substr($other_user['name'], 0, 1))
            ];
        }
    }

    echo json_encode(['success' => true, 'conversations' => $conversations]);
    exit;
}

// ── fetch_admin_messages ────────────────────────────────────
if ($action === 'fetch_admin_messages') {
    $conv_id = (int) ($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
    if (!$conv_id) {
        echo json_encode(['success' => false, 'message' => 'Missing conversation_id']);
        exit;
    }

    // Security: ensure user is part of this conversation
    $check_stmt = $conn->prepare("
        SELECT id FROM admin_conversations 
        WHERE id = ? AND (participant_a_id = ? OR participant_b_id = ?) LIMIT 1
    ");
    $check_stmt->bind_param('iii', $conv_id, $current_user_id, $current_user_id);
    $check_stmt->execute();
    if (!$check_stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Forbidden - Not part of this conversation']);
        exit;
    }
    $check_stmt->close();

    $stmt = $conn->prepare(
        "SELECT acm.id, acm.sender_id, acm.receiver_id, acm.message,
                acm.message_type, acm.is_read, acm.created_at,
                CASE 
                    WHEN a.full_name IS NOT NULL THEN a.full_name
                    ELSE sa.full_name
                END as sender_name
         FROM admin_chat_messages acm
         LEFT JOIN admin a ON a.id = acm.sender_id AND a.id IS NOT NULL
         LEFT JOIN sub_admin sa ON sa.id = acm.sender_id AND sa.id IS NOT NULL
         WHERE acm.conversation_id = ?
         ORDER BY acm.created_at ASC
         LIMIT 200"
    );
    $stmt->bind_param('i', $conv_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $r['message'] = htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8');
        $r['sender_name'] = htmlspecialchars($r['sender_name'], ENT_QUOTES, 'UTF-8');
        $r['time_label'] = date('h:i A', strtotime($r['created_at']));
        $r['time_ago'] = time_ago($r['created_at']);
        $r['is_mine'] = ($r['sender_id'] == $current_user_id);
    }
    unset($r);

    echo json_encode(['success' => true, 'messages' => $rows]);
    exit;
}

// ── send_admin_message ──────────────────────────────────────
if ($action === 'send_admin_message') {
    $message = trim($_POST['message'] ?? '');
    $conv_id = (int) ($_POST['conversation_id'] ?? 0);
    $receiver_id = (int) ($_POST['receiver_id'] ?? 0);

    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Empty message']);
        exit;
    }
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message too long']);
        exit;
    }

    // Get or create conversation
    if ($conv_id === 0 && $receiver_id > 0) {
        $conv_id = get_or_create_admin_conversation($conn, $current_user_id, $receiver_id);
    }

    if ($conv_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid conversation']);
        exit;
    }

    // Security: ensure user is part of conversation
    $check_stmt = $conn->prepare("
        SELECT participant_a_id, participant_b_id FROM admin_conversations 
        WHERE id = ? LIMIT 1
    ");
    $check_stmt->bind_param('i', $conv_id);
    $check_stmt->execute();
    $conv = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$conv || ($conv['participant_a_id'] != $current_user_id && $conv['participant_b_id'] != $current_user_id)) {
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }

    // Determine receiver
    $receiver_id = ($conv['participant_a_id'] == $current_user_id) ? $conv['participant_b_id'] : $conv['participant_a_id'];

    // Insert message
    $stmt = $conn->prepare(
        "INSERT INTO admin_chat_messages (conversation_id, sender_id, receiver_id, message)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('iiis', $conv_id, $current_user_id, $receiver_id, $message);
    $stmt->execute();
    $msg_id = (int) $conn->insert_id;
    $stmt->close();

    // Update conversation summary
    $preview = mb_substr($message, 0, 100);
    $update_stmt = $conn->prepare("UPDATE admin_conversations SET last_message = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param('si', $preview, $conv_id);
    $update_stmt->execute();
    $update_stmt->close();

    echo json_encode([
        'success' => true,
        'message_id' => $msg_id,
        'conv_id' => $conv_id,
        'time_label' => date('h:i A'),
    ]);
    exit;
}

// ── mark_admin_read ─────────────────────────────────────────
if ($action === 'mark_admin_read') {
    $conv_id = (int) ($_POST['conversation_id'] ?? 0);
    if ($conv_id) {
        $stmt = $conn->prepare(
            "UPDATE admin_chat_messages SET is_read = 1
             WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0"
        );
        $stmt->bind_param('ii', $conv_id, $current_user_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
    exit;
}

// ── get_available_admins ───────────────────────────────────
if ($action === 'get_available_admins') {
    $admins = get_available_admins($conn, $current_user_id);
    
    foreach ($admins as &$admin) {
        $admin['full_name'] = htmlspecialchars($admin['full_name'], ENT_QUOTES, 'UTF-8');
        $admin['avatar_letter'] = strtoupper(substr($admin['full_name'], 0, 1));
    }
    unset($admin);
    
    echo json_encode(['success' => true, 'admins' => $admins]);
    exit;
}

// ── update_chat_status ─────────────────────────────────────
if ($action === 'update_chat_status') {
    $status = $_POST['status'] ?? 'offline';
    $valid_statuses = ['online', 'offline', 'away', 'busy'];
    
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    $table = ($current_user_type === 'admin') ? 'admin' : 'sub_admin';
    $stmt = $conn->prepare("UPDATE $table SET chat_status = ?, last_chat_activity = NOW() WHERE id = ?");
    $stmt->bind_param('si', $status, $current_user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'status' => $status]);
    exit;
}

// ── get_online_admins ─────────────────────────────────────
if ($action === 'get_online_admins') {
    $admins = [];
    
    // Get online main admins
    $stmt = $conn->prepare("
        SELECT id, full_name, chat_status, last_chat_activity
        FROM admin 
        WHERE chat_status IN ('online', 'away', 'busy') 
        AND id != ?
        ORDER BY full_name
    ");
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['user_type'] = 'admin';
        $row['full_name'] = htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8');
        $row['avatar_letter'] = strtoupper(substr($row['full_name'], 0, 1));
        $admins[] = $row;
    }
    $stmt->close();
    
    // Get online sub-admins
    $stmt = $conn->prepare("
        SELECT id, full_name, chat_status, last_chat_activity
        FROM sub_admin 
        WHERE chat_status IN ('online', 'away', 'busy') 
        AND status = 'approved'
        AND id != ?
        ORDER BY full_name
    ");
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['user_type'] = 'sub-admin';
        $row['full_name'] = htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8');
        $row['avatar_letter'] = strtoupper(substr($row['full_name'], 0, 1));
        $admins[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'admins' => $admins]);
    exit;
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>
