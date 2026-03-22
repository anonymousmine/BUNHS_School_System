<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  student_notification_api.php — CACHED VERSION
//  PHP 5.4+ compatible — no ?? operator, no type hints
//  Fixed: db path resolution, missing-column safety, 500 errors
//
//  CACHE INTEGRATION:
//    get_preference  → served from cache (120 s TTL) on repeated AJAX calls
//    save_preference → invalidates cache immediately
//    skip            → invalidates cache immediately
// ═══════════════════════════════════════════════════════════════════════════════

function _sna_shutdown()
{
    $e = error_get_last();
    if ($e && ($e['type'] === E_ERROR   || $e['type'] === E_PARSE ||
        $e['type'] === E_CORE_ERROR || $e['type'] === E_COMPILE_ERROR)) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'Server error: ' . $e['message'] . ' (line ' . $e['line'] . ')',
        ));
    }
}
register_shutdown_function('_sna_shutdown');

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   0);
ini_set('session.use_only_cookies', 1);
session_start();

// ── DB: walk UP the directory tree until db_connection.php is found ───────────
$_db     = null;
$_search = dirname(__DIR__);
for ($_i = 0; $_i < 6; $_i++) {
    $_cand = $_search . '/db_connection.php';
    if (file_exists($_cand)) {
        $_db = $_cand;
        break;
    }
    $_parent = dirname($_search);
    if ($_parent === $_search) break;
    $_search = $_parent;
}
if (!$_db) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Cannot find db_connection.php. Searched from: ' . __DIR__,
    ));
    exit;
}
include $_db;

// ── Load caching layer (walk up same way) ─────────────────────────────────────
$_ch     = null;
$_search = dirname(__DIR__);
for ($_i = 0; $_i < 6; $_i++) {
    $_cand = $_search . '/cache_helper.php';
    if (file_exists($_cand)) {
        $_ch = $_cand;
        break;
    }
    $_parent = dirname($_search);
    if ($_parent === $_search) break;
    $_search = $_parent;
}
if ($_ch) {
    include_once $_ch;
} else {
    // APCu not available — define no-op stubs so the rest of the file works
    if (!function_exists('cache_get')) {
        function cache_get($k)
        {
            return false;
        }
    }
    if (!function_exists('cache_set')) {
        function cache_set($k, $v, $t) {}
    }
    if (!function_exists('cache_delete')) {
        function cache_delete($k) {}
    }
}

// ── Session guard ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['student_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Session expired. Please log in again.'));
    exit;
}

$studentId  = $_SESSION['student_id'];
$action     = isset($_POST['action'])     ? trim($_POST['action'])     : '';
$preference = isset($_POST['preference']) ? trim($_POST['preference']) : '';
$phone      = isset($_POST['phone'])      ? trim($_POST['phone'])      : '';
$email      = isset($_POST['email'])      ? trim($_POST['email'])      : '';

// ── Ensure required columns exist (zero cost when already present) ────────────
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS notification_preference VARCHAR(10)  DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS phone_verified          TINYINT(1)   DEFAULT 0");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS email_verified          TINYINT(1)   DEFAULT 0");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS phone                   VARCHAR(20)  DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS email                   VARCHAR(255) DEFAULT NULL");


// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: skip
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'skip') {

    $_SESSION['notif_dismissed'] = true;

    $stmt = $conn->prepare("UPDATE students SET notification_preference = 'none' WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $stmt->close();
    }

    // Invalidate cached preference — student has now set it to 'none'
    cache_delete("notif:{$studentId}");

    echo json_encode(array('success' => true));
    exit;
}


// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: save_preference
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'save_preference') {

    if ($preference !== 'phone' && $preference !== 'email' && $preference !== 'both') {
        echo json_encode(array('success' => false, 'message' => 'Invalid notification preference.'));
        exit;
    }

    $phoneNeeded = ($preference === 'phone' || $preference === 'both');
    $emailNeeded = ($preference === 'email' || $preference === 'both');

    if ($phoneNeeded && $phone === '') {
        echo json_encode(array('success' => false, 'message' => 'Phone number is required.'));
        exit;
    }
    if ($emailNeeded && $email === '') {
        echo json_encode(array('success' => false, 'message' => 'Email address is required.'));
        exit;
    }
    if ($emailNeeded && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid email address format.'));
        exit;
    }

    $setParts  = array('notification_preference = ?', 'phone_verified = 0', 'email_verified = 0');
    $bindTypes = 's';
    $bindVals  = array($preference);

    if ($phoneNeeded && $phone !== '') {
        $setParts[]  = 'phone = ?';
        $bindTypes  .= 's';
        $bindVals[]  = $phone;
    }
    if ($emailNeeded && $email !== '') {
        $setParts[]  = 'email = ?';
        $bindTypes  .= 's';
        $bindVals[]  = $email;
    }

    $bindTypes .= 's';
    $bindVals[] = $studentId;

    $sql  = 'UPDATE students SET ' . implode(', ', $setParts) . ' WHERE student_id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(array('success' => false, 'message' => 'DB prepare error: ' . $conn->error));
        exit;
    }

    $args = array($bindTypes);
    foreach ($bindVals as $k => $v) {
        $args[] = &$bindVals[$k];
    }
    call_user_func_array(array($stmt, 'bind_param'), $args);

    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $_SESSION['notification_preference'] = $preference;

        // Invalidate stale preference cache — next get_preference will re-fetch from DB
        cache_delete("notif:{$studentId}");

        echo json_encode(array('success' => true));
    } else {
        echo json_encode(array('success' => false, 'message' => 'Failed to save preference. Please try again.'));
    }
    exit;
}


// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: get_preference
//  Most frequently called action — cached aggressively (120 s TTL).
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'get_preference') {

    $cache_key = "notif:{$studentId}";

    // ── Try cache first ───────────────────────────────────────────────────────
    $cached = cache_get($cache_key);
    if ($cached !== false) {
        // CACHE HIT — return stored preference object without touching DB
        echo json_encode($cached);
        exit;
    }

    // ── CACHE MISS — query DB ─────────────────────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT notification_preference, phone, email, phone_verified, email_verified
         FROM students WHERE student_id = ? LIMIT 1"
    );
    if (!$stmt) {
        echo json_encode(array('success' => false, 'message' => 'Database error: ' . $conn->error));
        exit;
    }
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $payload = array(
            'success'        => true,
            'preference'     => $row['notification_preference'],
            'phone'          => $row['phone'],
            'email'          => $row['email'],
            'phone_verified' => (bool)$row['phone_verified'],
            'email_verified' => (bool)$row['email_verified'],
        );

        // Store in cache — next AJAX call skips the SELECT entirely
        cache_set($cache_key, $payload, CACHE_TTL_NOTIF_PREF);

        echo json_encode($payload);
    } else {
        echo json_encode(array('success' => false, 'message' => 'Student not found.'));
    }
    exit;
}


echo json_encode(array('success' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)));
