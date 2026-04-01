<?php

/**
 * chat_api.php
 * ─────────────────────────────────────────────────────────────
 * Unified AJAX endpoint for the chat system.
 * Works for both admin (admin_chatbox.php) and student (chatbox.php).
 *
 * Actions:
 *   fetch_conversations  – list all conversations (admin)
 *   fetch_messages       – messages for one conversation
 *   send_message         – send a new message
 *   mark_read            – mark conversation messages as read
 *   envelope_preview     – recent messages for topbar envelope dropdown (admin)
 *   get_student_conv     – get or create the student's conversation (student init)
 *
 * Phase 2 fixes applied:
 *   1. PHPMailer "use" declarations moved to file scope (were fatally inside a function).
 *   2. session_start() called explicitly before anything touches $_SESSION.
 *   3. fetch_messages now returns message_type (needed by JS to render file-request bubbles).
 *   4. fetch_conversations now returns grade_level + pending_file_requests (needed by admin JS).
 * ─────────────────────────────────────────────────────────────
 */

// ── FIX 1: session must start before session_config.php or any $_SESSION read ──
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../session_config.php';

// ── FIX 2: PHPMailer "use" declarations MUST be at file scope, never inside a function ──
// They are loaded conditionally so the file still works without Composer.
$_mailer_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($_mailer_autoload)) {
    require_once $_mailer_autoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

// Enhanced session check with fallback for testing
$is_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['admin', 'sub-admin']))
    || (isset($_SESSION['admin_id']))
    || (isset($_SESSION['session_initialized']));

// If not logged in, return error
if (!$is_logged_in) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Session not valid']);
    exit();
}

// ── Auth ──────────────────────────────────────────────────────
$is_admin   = in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']);
$is_student = isset($_SESSION['student_id']);

// CSRF Validation - Only for POST requests that modify data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);
        exit;
    }
}

include '../db_connection.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Get admin ID dynamically ──────────────────────────────────
function get_admin_id(mysqli $conn): int {
    $stmt = $conn->prepare("SELECT id FROM admin LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$result) {
        // Fallback to default if no admin found
        return 1;
    }
    
    return (int) $result['id'];
}

// ── Rate limiting ──────────────────────────────────────────────
function check_rate_limit(mysqli $conn, int $user_id, string $action): bool {
    $time_window = 60; // 1 minute
    $max_requests = 30; // 30 requests per minute
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as request_count 
        FROM rate_limits 
        WHERE user_id = ? AND action_type = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->bind_param('isi', $user_id, $action, $time_window);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $request_count = $result['request_count'] ?? 0;
    
    if ($request_count >= $max_requests) {
        return false;
    }
    
    // Log this request
    $stmt = $conn->prepare("
        INSERT INTO rate_limits (user_id, action_type, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->bind_param('is', $user_id, $action);
    $stmt->execute();
    $stmt->close();
    
    return true;
}

// ── Get or create conversation ────────────────────────────────
function get_or_create_conversation(mysqli $conn, int $student_id): int
{
    $admin_id = get_admin_id($conn);
    
    $s = $conn->prepare(
        "SELECT id FROM chat_conversations WHERE student_id = ? AND admin_id = ? LIMIT 1"
    );
    $s->bind_param('ii', $student_id, $admin_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if ($row) return (int) $row['id'];

    $s = $conn->prepare(
        "INSERT INTO chat_conversations (student_id, admin_id, last_message, updated_at)
         VALUES (?, ?, '', NOW())"
    );
    $s->bind_param('ii', $student_id, $admin_id);
    $s->execute();
    $id = (int) $conn->insert_id;
    $s->close();
    return $id;
}

// ── fetch_conversations (admin) ───────────────────────────────
if ($action === 'fetch_conversations') {
    if (!$is_admin) {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            cc.id,
            cc.student_id, 
            cc.last_message,
            cc.updated_at,
            s.first_name, 
            s.last_name,
            COALESCE(s.grade_level, '') AS grade_level,
            COALESCE(
                (SELECT COUNT(*) 
                 FROM chat_messages cm 
                 WHERE cm.conversation_id = cc.id 
                   AND cm.is_read = 0 
                   AND cm.sender_role = 'student'
                ), 0
            ) AS unread_count,
            COALESCE(
                (SELECT COUNT(*) 
                 FROM file_requests fr 
                 WHERE fr.student_id = cc.student_id 
                   AND fr.status = 'pending'
                ), 0
            ) AS has_file_request,
            0 AS is_club_group
        FROM chat_conversations cc
        JOIN students s ON s.id = cc.student_id
        ORDER BY cc.updated_at DESC
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error]);
        exit;
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $r['student_name'] = htmlspecialchars($r['first_name'] . ' ' . $r['last_name'], ENT_QUOTES, 'UTF-8');
        $r['last_message'] = htmlspecialchars($r['last_message'] ?? 'No messages yet', ENT_QUOTES, 'UTF-8');
        $r['time_ago'] = time_ago($r['updated_at']);
        $r['avatar_letter'] = strtoupper(substr($r['first_name'], 0, 1));
        $r['unread'] = (int) $r['unread_count'];
        $r['has_file_request'] = (int) $r['has_file_request'];
        $r['is_club_group'] = (int) $r['is_club_group'];
        $r['unread_count'] = $r['unread']; // alias for JS
    }
    unset($r);

    echo json_encode(['success' => true, 'conversations' => $rows]);
    exit;
}

// ── fetch_messages ────────────────────────────────────────────
if ($action === 'fetch_messages') {
    $conv_id = (int) ($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
    if (!$conv_id) {
        echo json_encode(['success' => false, 'message' => 'Missing conversation_id']);
        exit;
    }

    // Security: students may only fetch their own conversation
    if ($is_student) {
        $sid = (int) $_SESSION['student_id'];
        $s   = $conn->prepare("SELECT id FROM chat_conversations WHERE id = ? AND student_id = ? LIMIT 1");
        $s->bind_param('ii', $conv_id, $sid);
        $s->execute();
        if (!$s->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
        $s->close();
    }

    // FIX 4: added message_type so JS can render file-request bubbles correctly.
    $stmt = $conn->prepare(
        "SELECT cm.id, cm.sender_id, cm.sender_role, cm.message,
                cm.message_type, cm.is_read, cm.created_at
         FROM chat_messages cm
         WHERE cm.conversation_id = ?
         ORDER BY cm.created_at ASC
         LIMIT 200"
    );
    $stmt->bind_param('i', $conv_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$r) {
        $r['message']    = htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8');
        $r['time_label'] = date('h:i A', strtotime($r['created_at']));
        $r['time_ago']   = time_ago($r['created_at']);
    }
    unset($r);

    echo json_encode(['success' => true, 'messages' => $rows]);
    exit;
}

// ── send_message ──────────────────────────────────────────────
if ($action === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    
    // Enhanced input validation
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Empty message']);
        exit;
    }
    if (strlen($message) > 2000) {
        echo json_encode(['success' => false, 'message' => 'Message too long (max 2000 characters)']);
        exit;
    }
    if (strlen($message) < 1) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit;
    }

    if ($is_student) {
        $sender_id   = (int) $_SESSION['student_id'];
        $sender_role = 'student';
        
        // Rate limiting check
        if (!check_rate_limit($conn, $sender_id, 'send_message')) {
            echo json_encode(['success' => false, 'message' => 'Too many messages. Please wait a moment.']);
            exit;
        }
        
        $conv_id     = get_or_create_conversation($conn, $sender_id);
    } else {
        // Admin sending
        $conv_id = (int) ($_POST['conversation_id'] ?? 0);
        if (!$conv_id) {
            echo json_encode(['success' => false, 'message' => 'Missing conversation_id']);
            exit;
        }

        $s = $conn->prepare("SELECT student_id, admin_id FROM chat_conversations WHERE id = ? LIMIT 1");
        $s->bind_param('i', $conv_id);
        $s->execute();
        $conv = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$conv) {
            echo json_encode(['success' => false, 'message' => 'Conversation not found']);
            exit;
        }

        $sender_id   = (int) $_SESSION['user_id'];
        $sender_role = 'admin';
        $receiver_id = (int) $conv['student_id'];
        
        // Rate limiting check for admins
        if (!check_rate_limit($conn, $sender_id, 'send_message')) {
            echo json_encode(['success' => false, 'message' => 'Too many messages. Please wait a moment.']);
            exit;
        }
    }

    // Insert message
    $stmt = $conn->prepare(
        "INSERT INTO chat_messages (conversation_id, sender_id, sender_role, receiver_id, message)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iisis', $conv_id, $sender_id, $sender_role, $receiver_id, $message);
    $stmt->execute();
    $msg_id = (int) $conn->insert_id;
    $stmt->close();

    // Update conversation summary
    $preview = mb_substr($message, 0, 100);
    $s = $conn->prepare("UPDATE chat_conversations SET last_message = ?, updated_at = NOW() WHERE id = ?");
    $s->bind_param('si', $preview, $conv_id);
    $s->execute();
    $s->close();

    // Send email notification (best-effort — never blocks or crashes the response)
    send_chat_email_notification($conn, $sender_role, $sender_id, $receiver_id, $message);

    echo json_encode([
        'success'    => true,
        'message_id' => $msg_id,
        'conv_id'    => $conv_id,
        'time_label' => date('h:i A'),
    ]);
    exit;
}

// ── mark_read ─────────────────────────────────────────────────
if ($action === 'mark_read') {
    $conv_id     = (int) ($_POST['conversation_id'] ?? 0);
    $reader_role = $is_admin ? 'student' : 'admin'; // mark messages FROM the other side as read
    if ($conv_id) {
        $stmt = $conn->prepare(
            "UPDATE chat_messages SET is_read = 1
             WHERE conversation_id = ? AND sender_role = ? AND is_read = 0"
        );
        $stmt->bind_param('is', $conv_id, $reader_role);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['success' => true]);
    exit;
}

// ── envelope_preview (topbar dropdown) ───────────────────────
if ($action === 'envelope_preview') {
    if (!$is_admin) {
        echo json_encode(['success' => false]);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT cc.id AS conv_id, cc.last_message, cc.updated_at,
                s.first_name, s.last_name,
                (SELECT COUNT(*) FROM chat_messages cm
                 WHERE cm.conversation_id = cc.id AND cm.is_read = 0 AND cm.sender_role = 'student') AS unread,
                (SELECT cm.sender_role FROM chat_messages cm 
                 WHERE cm.conversation_id = cc.id 
                 ORDER BY cm.created_at DESC LIMIT 1) AS last_sender_role
         FROM chat_conversations cc
         JOIN students s ON s.id = cc.student_id
         WHERE cc.last_message != ''
         ORDER BY cc.updated_at DESC
         LIMIT 8"
    );
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_unread = 0;
    foreach ($rows as &$r) {
        $r['student_name']  = htmlspecialchars($r['first_name'] . ' ' . $r['last_name'], ENT_QUOTES, 'UTF-8');
        $r['last_message']  = htmlspecialchars(mb_substr($r['last_message'] ?? '', 0, 60), ENT_QUOTES, 'UTF-8');
        $r['time_ago']      = time_ago($r['updated_at']);
        $r['avatar_letter'] = strtoupper(substr($r['first_name'], 0, 1));
        $r['sender_role']   = $r['last_sender_role'] ?? 'student'; // Default to student for safety
        $total_unread      += (int) $r['unread'];
    }
    unset($r);

    echo json_encode(['success' => true, 'previews' => $rows, 'total_unread' => $total_unread]);
    exit;
}

// ── get_student_conv (student chatbox init) ───────────────────
if ($action === 'get_student_conv') {
    if (!$is_student) {
        echo json_encode(['success' => false]);
        exit;
    }
    $sid     = (int) $_SESSION['student_id'];
    $conv_id = get_or_create_conversation($conn, $sid, 1);
    echo json_encode(['success' => true, 'conv_id' => $conv_id]);
    exit;
}

// ── process_file_request (admin) ────────────────────────────────────────────
if ($action === 'process_file_request') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit;
    }

    $request_id = (int) ($_POST['request_id'] ?? 0);
    $decision = trim($_POST['decision'] ?? '');

    if (!$request_id || !in_array($decision, ['approve', 'reject'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    // Get request details first
    $stmt = $conn->prepare("
        SELECT fr.id, fr.student_id, fr.form_id, fr.status, s.first_name, s.last_name
        FROM file_requests fr 
        JOIN students s ON fr.student_id = s.id 
        WHERE fr.id = ?
    ");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request || $request['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Request not found or already processed']);
        exit;
    }

    $student_id = (int) $request['student_id'];
    $new_status = $decision === 'approve' ? 'approved' : 'rejected';

    // Update file request
    if ($decision === 'approve') {
        $stmt = $conn->prepare("UPDATE file_requests SET status = 'approved', approval_date = NOW() WHERE id = ?");
        $stmt->bind_param('i', $request_id);
    } else {
        $reject_reason = trim($_POST['reject_reason'] ?? 'No reason provided');
        $stmt = $conn->prepare("UPDATE file_requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->bind_param('si', $reject_reason, $request_id);
    }

    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Notify student via chat
        $conv_id = get_or_create_conversation($conn, $student_id);
        $notif_msg = $decision === 'approve' ?
            "✅ File request APPROVED. You can download '{$request['form_id']}' now." :
            "❌ File request REJECTED.";

        $stmt = $conn->prepare("
            INSERT INTO chat_messages (conversation_id, sender_id, sender_role, receiver_id, message, message_type)
            VALUES (?, 1, 'admin', ?, ?, 'file_request_response')
        ");
        $stmt->bind_param('iis', $conv_id, $student_id, $notif_msg);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => "File request $decision successfully",
            'student_name' => $request['first_name'] . ' ' . $request['last_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);

// ─────────────────────────────────────────────────────────────
// Email helper
// FIX 1 (cont.): "use" statements are now at file scope above.
//                This function just uses the classes directly.
//                If vendor/autoload.php is absent the function
//                returns early at the file_exists check above,
//                so PHPMailer is never referenced at all.
// ─────────────────────────────────────────────────────────────
function send_chat_email_notification(
    mysqli $conn,
    string $sender_role,
    int    $sender_id,
    int    $receiver_id,
    string $message
): void {
    // If PHPMailer was not loaded (no vendor dir) bail out silently
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) return;

    $to_email = $to_name = $from_name = '';
    $preview  = mb_substr($message, 0, 120);

    if ($sender_role === 'student') {
        // Student → Admin: notify admin
        $s = $conn->prepare("SELECT personal_email, full_name FROM `admin` WHERE id = ? LIMIT 1");
        $s->bind_param('i', $receiver_id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        $to_email  = $row['personal_email'] ?? '';
        $to_name   = $row['full_name']       ?? 'Admin';

        $s2 = $conn->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM students WHERE id = ? LIMIT 1");
        $s2->bind_param('i', $sender_id);
        $s2->execute();
        $r2 = $s2->get_result()->fetch_assoc();
        $s2->close();
        $from_name = $r2['name'] ?? 'Student';
    } else {
        // Admin → Student: notify student
        $s = $conn->prepare("SELECT email, CONCAT(first_name,' ',last_name) AS name FROM students WHERE id = ? LIMIT 1");
        $s->bind_param('i', $receiver_id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        $to_email  = $row['email'] ?? '';
        $to_name   = $row['name']  ?? 'Student';
        $from_name = 'BUNHS Admin';
    }

    if (empty($to_email)) return;

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bunhs.deped@gmail.com';
        $mail->Password   = 'msqncrybbxlxhmbn';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('bunhs.deped@gmail.com', 'Buyoan National High School');
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = "New message from {$from_name} – BUNHS";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;
                        padding:30px;border:1px solid #e0e0e0;border-radius:12px;'>
                <h2 style='color:#6d7a48;'>New Message</h2>
                <p style='color:#555;'>You have a new message from <strong>"
            . htmlspecialchars($from_name, ENT_QUOTES) . "</strong>:</p>
                <blockquote style='background:#f9f9f9;padding:16px;border-left:4px solid #8a9a5b;
                                   border-radius:4px;color:#333;font-style:italic;'>
                    " . htmlspecialchars($preview, ENT_QUOTES) . "
                </blockquote>
                <p style='color:#888;font-size:13px;'>Log in to reply at the BUNHS portal.</p>
            </div>";
        $mail->AltBody = "New message from {$from_name}: {$preview}";
        $mail->send();
    } catch (\Exception $e) {
        error_log('Chat email error: ' . $e->getMessage());
    }
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
