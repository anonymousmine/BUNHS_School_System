<?php

/**
 * notification_api.php
 * AJAX endpoint for the bell-icon notification dropdown.
 * Expects:  ?action=fetch | mark_read | mark_all_read
 */

require_once '../session_config.php';

$_notif_auth = (isset($_SESSION['user_id']) && in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']))
    || isset($_SESSION['admin_id']);
if (!$_notif_auth) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../db_connection.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';

// ── Fetch latest notifications ────────────────────────────────
if ($action === 'fetch') {
    $stmt = $conn->prepare(
        "SELECT id, sub_admin_name, sub_admin_email, role, edited_module,
                edit_description, is_read, created_at
         FROM admin_notifications
         ORDER BY created_at DESC
         LIMIT 30"
    );
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Count unread
    $s = $conn->query("SELECT COUNT(*) AS cnt FROM admin_notifications WHERE is_read = 0");
    $unread = (int) $s->fetch_assoc()['cnt'];

    // Format timestamps
    foreach ($rows as &$row) {
        $row['time_ago']    = time_ago($row['created_at']);
        $row['created_at']  = htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8');
        $row['sub_admin_name']  = htmlspecialchars($row['sub_admin_name'],  ENT_QUOTES, 'UTF-8');
        $row['sub_admin_email'] = htmlspecialchars($row['sub_admin_email'], ENT_QUOTES, 'UTF-8');
        $row['role']            = htmlspecialchars($row['role'],            ENT_QUOTES, 'UTF-8');
        $row['edited_module']   = htmlspecialchars($row['edited_module'],   ENT_QUOTES, 'UTF-8');
        $row['edit_description'] = htmlspecialchars($row['edit_description'], ENT_QUOTES, 'UTF-8');
    }
    unset($row);

    echo json_encode(['success' => true, 'notifications' => $rows, 'unread_count' => $unread]);
    exit;
}

// ── Mark single notification as read ─────────────────────────
if ($action === 'mark_read' && isset($_POST['id'])) {
    $id   = (int) $_POST['id'];
    $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// ── Mark all as read ──────────────────────────────────────────
if ($action === 'mark_all_read') {
    $conn->query("UPDATE admin_notifications SET is_read = 1");
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);

// ── Helper ────────────────────────────────────────────────────
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
