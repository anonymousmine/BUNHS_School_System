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

// ── Auth ──────────────────────────────────────────────────────
$is_admin   = in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']);
$is_student = isset($_SESSION['student_id']);

if (!$is_admin && !$is_student) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── CSRF validation ───────────────────────────────────────────
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_expires'])) {
        return false;
    }
    
    if (time() > $_SESSION['csrf_token_expires']) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_expires']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ── Input helpers ─────────────────────────────────────────────
function clean_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ── Main request router ───────────────────────────────────────
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_conversations':
        handle_fetch_conversations();
        break;
    case 'fetch_messages':
        handle_fetch_messages();
        break;
    case 'send_message':
        handle_send_message();
        break;
    case 'mark_read':
        handle_mark_read();
        break;
    case 'envelope_preview':
        handle_envelope_preview();
        break;
    case 'get_student_conv':
        handle_get_student_conv();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// ── Action handlers ───────────────────────────────────────────

function handle_fetch_conversations() {
    global $is_admin;
    
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        return;
    }
    
    try {
        $convs = [];
        
        // Admin conversations
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT c.id, c.student_id, c.last_message, c.last_message_time, c.is_read,
                   s.first_name, s.last_name, s.grade_level, s.section,
                   COUNT(fr.id) as pending_file_requests
            FROM conversations c
            LEFT JOIN students s ON c.student_id = s.id
            LEFT JOIN file_requests fr ON c.id = fr.conversation_id AND fr.status = 'pending'
            GROUP BY c.id
            ORDER BY c.last_message_time DESC
        ");
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $convs[] = [
                'id' => (int)$row['id'],
                'student_id' => (int)$row['student_id'],
                'student_name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'grade_level' => $row['grade_level'],
                'section' => $row['section'],
                'last_message' => $row['last_message'],
                'last_message_time' => $row['last_message_time'],
                'is_read' => (int)$row['is_read'],
                'pending_file_requests' => (int)$row['pending_file_requests']
            ];
        }
        
        echo json_encode(['success' => true, 'conversations' => $convs]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handle_fetch_messages() {
    $conv_id = (int)($_POST['conversation_id'] ?? 0);
    
    if (!$conv_id) {
        echo json_encode(['success' => false, 'message' => 'Missing conversation ID']);
        return;
    }
    
    try {
        $msgs = [];
        
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT m.id, m.message, m.sender_type, m.message_type, m.created_at,
                   fr.file_name, fr.file_path, fr.status as file_status, fr.reason as file_reason
            FROM messages m
            LEFT JOIN file_requests fr ON m.id = fr.message_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conv_id]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $msg = [
                'id' => (int)$row['id'],
                'message' => $row['message'],
                'sender_type' => $row['sender_type'],
                'message_type' => $row['message_type'],
                'created_at' => $row['created_at']
            ];
            
            if ($row['message_type'] === 'file_request' && $row['file_name']) {
                $msg['file_request'] = [
                    'file_name' => $row['file_name'],
                    'file_path' => $row['file_path'],
                    'status' => $row['file_status'],
                    'reason' => $row['file_reason']
                ];
            }
            
            $msgs[] = $msg;
        }
        
        echo json_encode(['success' => true, 'messages' => $msgs]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handle_send_message() {
    $conv_id = (int)($_POST['conversation_id'] ?? 0);
    $message = clean_input($_POST['message'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        return;
    }
    
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        return;
    }
    
    global $is_admin, $is_student;
    
    try {
        $sender_type = $is_admin ? 'admin' : 'student';
        
        // Create conversation if doesn't exist (for students)
        if (!$conv_id && $is_student) {
            $stmt = $GLOBALS['pdo']->prepare("
                INSERT INTO conversations (student_id, last_message, last_message_time, is_read)
                VALUES (?, ?, NOW(), 1)
            ");
            $stmt->execute([$_SESSION['student_id'], $message]);
            $conv_id = $GLOBALS['pdo']->lastInsertId();
        } elseif (!$conv_id) {
            echo json_encode(['success' => false, 'message' => 'Conversation ID required']);
            return;
        }
        
        // Insert message
        $stmt = $GLOBALS['pdo']->prepare("
            INSERT INTO messages (conversation_id, message, sender_type, message_type, created_at)
            VALUES (?, ?, ?, 'text', NOW())
        ");
        $stmt->execute([$conv_id, $message]);
        
        // Update conversation
        $stmt = $GLOBALS['pdo']->prepare("
            UPDATE conversations 
            SET last_message = ?, last_message_time = NOW(), is_read = ?
            WHERE id = ?
        ");
        $is_read = $is_admin ? 0 : 1;
        $stmt->execute([$message, $is_read, $conv_id]);
        
        echo json_encode(['success' => true, 'conv_id' => $conv_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

function handle_mark_read() {
    global $is_admin;
    
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        return;
    }
    
    $conv_id = (int)($_POST['conversation_id'] ?? 0);
    
    if (!$conv_id) {
        echo json_encode(['success' => false, 'message' => 'Missing conversation ID']);
        return;
    }
    
    try {
        $stmt = $GLOBALS['pdo']->prepare("
            UPDATE conversations SET is_read = 1 WHERE id = ?
        ");
        $stmt->execute([$conv_id]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handle_envelope_preview() {
    global $is_admin;
    
    if (!$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        return;
    }
    
    try {
        $msgs = [];
        
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT c.id, c.student_id, c.last_message, c.last_message_time, c.is_read,
                   s.first_name, s.last_name
            FROM conversations c
            LEFT JOIN students s ON c.student_id = s.id
            ORDER BY c.last_message_time DESC
            LIMIT 5
        ");
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $msgs[] = [
                'id' => (int)$row['id'],
                'student_name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'message' => $row['last_message'],
                'time' => $row['last_message_time'],
                'is_read' => (int)$row['is_read']
            ];
        }
        
        echo json_encode(['success' => true, 'messages' => $msgs]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function handle_get_student_conv() {
    global $is_student;
    
    if (!$is_student) {
        echo json_encode(['success' => false, 'message' => 'Student only']);
        return;
    }
    
    try {
        $stmt = $GLOBALS['pdo']->prepare("
            SELECT id FROM conversations WHERE student_id = ?
        ");
        $stmt->execute([$_SESSION['student_id']]);
        
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conv) {
            echo json_encode(['success' => true, 'conversation_id' => (int)$conv['id']]);
        } else {
            echo json_encode(['success' => true, 'conversation_id' => null]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
