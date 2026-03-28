<?php

/**
 * Student Event Details
 * View event details and register for events
 * Enhanced: dynamic DB data, student registration, group/teacher view
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../session_config.php';
include '../db_connection.php';

// ── Auth guard ───────────────────────────────────────────────
// Student functionality has been removed
header('Location: ../index.php');
exit;

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$grade_level  = $_SESSION['grade_level']  ?? 'Grade 7';

// ── FETCH FULL STUDENT PROFILE (for nav) ─────────────────────
$db_student_nav = [];
try {
    $stmt_nav = $conn->prepare(
        "SELECT first_name, last_name, grade_level,
                phone, email, photo,
                notification_preference,
                phone_verified, email_verified,
                login_method
         FROM students WHERE student_id = ? LIMIT 1"
    );
    if ($stmt_nav) {
        $stmt_nav->bind_param("s", $student_id);
        $stmt_nav->execute();
        $result_nav = $stmt_nav->get_result();
        if ($row_nav = $result_nav->fetch_assoc()) {
            $db_student_nav = $row_nav;
            $student_name   = trim($row_nav['first_name'] . ' ' . $row_nav['last_name']);
            $grade_level    = $row_nav['grade_level'];
        }
        $stmt_nav->close();
    }
} catch (Exception $e) { /* fallback silently */
}

// ── PROFILE DISPLAY MODE ─────────────────────────────────────
$login_method      = $db_student_nav['login_method']   ?? null;
$phone_verified    = !empty($db_student_nav['phone_verified']);
$email_verified    = !empty($db_student_nav['email_verified']);
$profile_photo_nav = !empty($db_student_nav['photo'])  ? $db_student_nav['photo']  : null;
$student_email_nav = !empty($db_student_nav['email'])  ? $db_student_nav['email']  : null;
$student_phone_nav = !empty($db_student_nav['phone'])  ? $db_student_nav['phone']  : null;

if (!$login_method      && !empty($_SESSION['login_method']))    $login_method      = $_SESSION['login_method'];
if (!$student_email_nav && !empty($_SESSION['student_email']))   $student_email_nav = $_SESSION['student_email'];
if (!$student_phone_nav && !empty($_SESSION['student_phone']))   $student_phone_nav = $_SESSION['student_phone'];

if ($login_method === 'email' || ($email_verified && $student_email_nav)) {
    $profile_display_mode = 'email';
} elseif ($login_method === 'phone' || ($phone_verified && $student_phone_nav)) {
    $profile_display_mode = 'phone';
} elseif (!empty($_SESSION['notif_dismissed'])) {
    $profile_display_mode = 'skip';
} else {
    $profile_display_mode = 'none';
}

$user_verified = ($email_verified || $phone_verified || !empty($_SESSION['dash_email_verified']));

// ── NAV PROFILE PICTURE ──────────────────────────────────────
$nav_profile_img  = 'assets/img/person/unknown.jpg';
$nav_profile_type = 'icon';

if ($profile_display_mode === 'email') {
    if ($profile_photo_nav) {
        $nav_profile_img  = htmlspecialchars($profile_photo_nav, ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    } elseif (!empty($_SESSION['google_avatar'])) {
        $nav_profile_img  = htmlspecialchars($_SESSION['google_avatar'], ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    }
}

switch ($profile_display_mode) {
    case 'email':
        $nav_display_label = $student_email_nav
            ? htmlspecialchars($student_email_nav, ENT_QUOTES, 'UTF-8')
            : htmlspecialchars($student_name,      ENT_QUOTES, 'UTF-8');
        break;
    case 'phone':
        $nav_display_label = $student_phone_nav
            ? htmlspecialchars($student_phone_nav, ENT_QUOTES, 'UTF-8')
            : htmlspecialchars($student_name,      ENT_QUOTES, 'UTF-8');
        break;
    default:
        $nav_display_label = '';
        break;
}

// ── Helpers ──────────────────────────────────────────────────
function ensure_feature_tables_details($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS event_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        registrant_type VARCHAR(50) DEFAULT 'student',
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        UNIQUE KEY uq_application (event_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        group_name VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        FOREIGN KEY (group_id) REFERENCES event_groups(id) ON DELETE CASCADE,
        UNIQUE KEY uq_group_student (group_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS group_teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        teacher_name VARCHAR(255) NOT NULL,
        FOREIGN KEY (group_id) REFERENCES event_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_highlights (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        highlight VARCHAR(500) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        time_slot VARCHAR(100) NOT NULL,
        activity VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ensure_feature_tables_details($conn);

// ── Handle AJAX registration ─────────────────────────────────
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'register') {
            $event_id  = intval($_POST['event_id']);
            $s_id      = trim($_POST['student_id']);
            $s_name    = trim($_POST['student_name']);
            $email     = trim($_POST['email']);
            $phone     = trim($_POST['phone']);
            $reg_type  = trim($_POST['registrant_type'] ?? 'student');

            // Check duplicate
            $chk = $conn->prepare("SELECT id, status FROM event_applications WHERE event_id=? AND student_id=?");
            $chk->bind_param("is", $event_id, $s_id);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            if ($existing) {
                echo json_encode(['status' => 'already', 'app_status' => $existing['status'], 'message' => 'You have already applied for this event.']);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO event_applications (event_id, student_id, student_name, email, phone, registrant_type) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isssss", $event_id, $s_id, $s_name, $email, $phone, $reg_type);
            $ok = $stmt->execute();
            $stmt->close();

            echo json_encode(
                $ok
                    ? ['status' => 'success', 'message' => 'Application submitted! Your status is Pending.']
                    : ['status' => 'error',   'message' => 'Could not submit application. Please try again.']
            );
            exit;
        }
    }
    exit;
}

// ── Get event_id ─────────────────────────────────────────────
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Load event from DB
$event = null;
if ($event_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fallback static values when no DB event
$ev_title       = $event['title']              ?? 'Annual Science Exhibition';
$ev_date        = $event['event_date']         ?? '2025-10-24';
$ev_start       = $event['event_start_time']   ?? '15:00';
$ev_end         = $event['event_end_time']     ?? '18:00';
$ev_location    = $event['location']           ?? 'Main Auditorium';
$ev_description = $event['description']        ?? '';
$ev_image       = $event['image']              ?? null;
$ev_org_name    = $event['organizer_name']     ?? 'Prof. Michael Anderson';
$ev_org_pos     = $event['organizer_position'] ?? 'Head of Science Department';
$ev_org_contact = $event['organizer_contact']  ?? 'admin@bunhs.edu';
$ev_source      = $event['source']              ?? null;
$ev_is_official = $event['is_official']        ?? 0;

// Format date & time for display
$date_obj     = new DateTime($ev_date);
$ev_date_disp = $date_obj->format('F j, Y');      // October 24, 2025

function fmt_time($t)
{
    if (!$t) return '';
    $dt = DateTime::createFromFormat('H:i', $t) ?: DateTime::createFromFormat('H:i:s', $t);
    return $dt ? $dt->format('g:i A') : $t;
}
$ev_time_disp = fmt_time($ev_start) . ($ev_end ? ' - ' . fmt_time($ev_end) : '');

// Load highlights from DB
$highlights = [];
if ($event_id > 0) {
    $s = $conn->prepare("SELECT highlight FROM event_highlights WHERE event_id=? ORDER BY sort_order ASC");
    $s->bind_param("i", $event_id);
    $s->execute();
    $r = $s->get_result();
    while ($row = $r->fetch_assoc()) $highlights[] = $row['highlight'];
    $s->close();
}
// Fallback highlights
if (empty($highlights)) {
    $highlights = [
        'Interactive student presentations of scientific experiments',
        'Special lecture by renowned physicist Dr. Robert Jenkins',
        'Robotics competition with prizes for top three teams',
        'Science demonstrations by faculty members',
        'Exhibition of innovative student projects',
    ];
}

// Load schedule from DB
$schedule = [];
if ($event_id > 0) {
    $s = $conn->prepare("SELECT * FROM event_schedule WHERE event_id=? ORDER BY sort_order ASC");
    $s->bind_param("i", $event_id);
    $s->execute();
    $r = $s->get_result();
    while ($row = $r->fetch_assoc()) $schedule[] = $row;
    $s->close();
}
// Fallback schedule
if (empty($schedule)) {
    $schedule = [
        ['time_slot' => '3:00 PM - 3:30 PM',  'activity' => 'Opening Ceremony',         'description' => 'Welcome address by Principal and introduction to the event'],
        ['time_slot' => '3:30 PM - 4:30 PM',  'activity' => 'Student Project Presentations', 'description' => 'Selected students showcase their scientific innovations'],
        ['time_slot' => '4:30 PM - 5:15 PM',  'activity' => 'Guest Lecture',             'description' => 'Special lecture on "Future of Quantum Computing"'],
        ['time_slot' => '5:15 PM - 5:45 PM',  'activity' => 'Robotics Demonstration',    'description' => 'Live demonstration of student-built robots'],
        ['time_slot' => '5:45 PM - 6:00 PM',  'activity' => 'Award Ceremony & Closing',  'description' => 'Distribution of certificates and recognition'],
    ];
}

// Related events
$related = [];
if ($event_id > 0 && !empty($ev_category)) {
    $s = $conn->prepare("SELECT id, title, event_date, location FROM events WHERE category = ? AND id != ? AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3");
    $s->bind_param("is", $ev_category, $event_id);
    $s->execute();
    $r = $s->get_result();
    while ($row = $r->fetch_assoc()) $related[] = $row;
    $s->close();
}

// Check if student already applied
$my_application = null;
if ($event_id > 0) {
    $s = $conn->prepare("SELECT id, status FROM event_applications WHERE event_id=? AND student_id=?");
    $s->bind_param("is", $event_id, $student_id);
    $s->execute();
    $my_application = $s->get_result()->fetch_assoc();
    $s->close();
}

// Student group for this event
$my_group = null;
if ($event_id > 0) {
    $s = $conn->prepare(
        "SELECT eg.id AS group_id, eg.group_name
         FROM group_members gm
         JOIN event_groups eg ON gm.group_id = eg.id
         WHERE eg.event_id=? AND gm.student_id=?"
    );
    $s->bind_param("is", $event_id, $student_id);
    $s->execute();
    $my_group = $s->get_result()->fetch_assoc();
    $s->close();
}

$group_members  = [];
$group_teachers = [];
if ($my_group) {
    $gid = $my_group['group_id'];

    $s = $conn->prepare("SELECT student_name, student_id FROM group_members WHERE group_id=?");
    $s->bind_param("i", $gid);
    $s->execute();
    $r = $s->get_result();
    while ($row = $r->fetch_assoc()) $group_members[] = $row;
    $s->close();

    $s = $conn->prepare("SELECT teacher_name FROM group_teachers WHERE group_id=?");
    $s->bind_param("i", $gid);
    $s->execute();
    $r = $s->get_result();
    while ($row = $r->fetch_assoc()) $group_teachers[] = $row;
    $s->close();
}

// Build event image src
$event_img_src = $ev_image
    ? '../assets/img/events/' . htmlspecialchars($ev_image)
    : '../assets/img/education/events-9.webp';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Details - Buyoan National High School</title>
    <link rel="stylesheet" href="../admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <style>
        /* ═══════════════════════════════════════════════════════
           ORIGINAL CSS — COMPLETELY UNCHANGED
        ═══════════════════════════════════════════════════════ */
        :root {
            --primary-color: #8a9a5b;
            --primary-dark: #6d7a48;
            --secondary-color: #10b981;
            --sidebar-width: 280px;
            --bg-light: #f8fafc;
            --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* sidebar loaded from Student_nav.php */

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 24px;
            background: var(--bg-light);
        }

        /* Page Title */
        .page-title {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 40px 24px;
            margin: -24px -24px 32px;
            color: #fff;
        }

        .page-title h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-title p {
            font-size: 14px;
            opacity: .9;
            max-width: 600px;
        }

        .breadcrumbs {
            margin-top: 16px;
            font-size: 13px;
        }

        .breadcrumbs a {
            color: rgba(255, 255, 255, .8);
            text-decoration: none;
        }

        .breadcrumbs a:hover {
            color: #fff;
        }

        .breadcrumbs span {
            color: rgba(255, 255, 255, .6);
            margin: 0 8px;
        }

        .breadcrumbs current {
            color: #fff;
        }

        /* Event Content */
        .event-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .event-main {
            background: #fff;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .event-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }

        .event-meta {
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
        }

        .meta-item i {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 16px;
        }

        .meta-item span {
            font-weight: 600;
            color: #1e293b;
        }

        .event-body {
            padding: 32px;
        }

        .event-header-section {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .event-body h2 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .official-event-indicator {
            display: flex;
            align-items: center;
        }

        .official-badge {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .official-badge i {
            font-size: 11px;
        }

        .source-information {
            background: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 12px 16px;
            margin-bottom: 24px;
            border-radius: 8px;
        }

        .source-information p {
            margin: 0;
            color: #1e40af;
            font-size: 14px;
        }

        .source-information i {
            color: #3b82f6;
            margin-right: 8px;
        }

        .official-event-notice {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .official-event-notice i {
            color: #0284c7;
            margin-bottom: 8px;
            font-size: 20px;
        }

        .official-event-notice p {
            margin: 0;
            color: #0c4a6e;
            font-size: 14px;
            line-height: 1.5;
        }
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .event-body p {
            font-size: 15px;
            line-height: 1.7;
            color: #64748b;
            margin-bottom: 24px;
        }

        .event-body h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 32px 0 16px;
        }

        .event-highlights {
            list-style: none;
            padding: 0;
        }

        .event-highlights li {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #64748b;
        }

        .event-highlights li:last-child {
            border-bottom: none;
        }

        .event-highlights i {
            color: var(--primary-color);
            font-size: 16px;
        }

        /* Schedule */
        .schedule-table {
            background: #f8fafc;
            border-radius: 12px;
            overflow: hidden;
        }

        .schedule-row {
            display: flex;
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .schedule-row:last-child {
            border-bottom: none;
        }

        .schedule-time {
            width: 160px;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
        }

        .schedule-activity h4 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .schedule-activity p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        /* Sidebar cards */
        .event-sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .sidebar-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 24px;
        }

        .sidebar-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-color);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color .2s;
            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
        }

        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(138, 154, 91, .4);
        }

        .btn-register:disabled {
            opacity: .7;
            cursor: not-allowed;
            transform: none;
        }

        /* Organizer */
        .organizer-details {
            display: flex;
            gap: 16px;
        }

        .organizer-image {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .organizer-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .organizer-content h4 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .organizer-position {
            font-size: 13px;
            color: var(--primary-color);
            margin-bottom: 12px;
        }

        .organizer-contact {
            font-size: 12px;
            color: #64748b;
        }

        .organizer-contact p {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .organizer-contact i {
            color: var(--primary-color);
        }

        /* Related Events */
        .related-event-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: all .2s;
        }

        .related-event-item:last-child {
            border-bottom: none;
        }

        .related-event-item:hover {
            padding-left: 8px;
        }

        .related-event-date {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
        }

        .related-event-date .day {
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
        }

        .related-event-date .month {
            font-size: 10px;
            text-transform: uppercase;
        }

        .related-event-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .related-event-info p {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Responsive */
        @media(max-width:1024px) {
            .event-container {
                grid-template-columns: 1fr;
            }

            .event-meta {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width:768px) {
            .main-content {
                margin-left: 0;
            }

            .page-title h1 {
                font-size: 24px;
            }

            .event-image {
                height: 250px;
            }
        }

        /* ═══════════════════════════════════════════════════════
           NEW STYLES — Group View Section (appended, no conflicts)
        ═══════════════════════════════════════════════════════ */
        .group-view-section {
            background: #fff;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 32px;
            margin-top: 24px;
        }

        .group-view-section h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .group-view-section h2 i {
            color: var(--primary-color);
        }

        .group-info-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #fff;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .group-info-card .group-icon {
            font-size: 28px;
        }

        .group-info-card .group-label {
            font-size: 12px;
            opacity: .85;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .group-info-card .group-name-disp {
            font-size: 22px;
            font-weight: 700;
        }

        .group-subsection {
            margin-bottom: 20px;
        }

        .group-subsection h4 {
            font-size: 14px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .7px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .group-subsection h4 i {
            color: var(--primary-color);
        }

        .member-chip,
        .teacher-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            padding: 8px 16px;
            margin: 4px;
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
        }

        .teacher-chip {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .member-chip .avatar-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }

        .teacher-chip .avatar-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
        }

        .no-group-notice {
            text-align: center;
            padding: 32px;
            color: #94a3b8;
        }

        .no-group-notice i {
            font-size: 48px;
            margin-bottom: 12px;
            display: block;
        }

        /* Alert messages */
        .reg-alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reg-alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .reg-alert.warning {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }

        .reg-alert.error {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fca5a5;
        }

        .reg-alert.info {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #93c5fd;
        }
    </style>
</head>

<body
    data-student-name="<?php echo htmlspecialchars($student_name); ?>"
    data-grade-level="<?php echo htmlspecialchars($grade_level); ?>"
    data-profile-mode="<?php echo htmlspecialchars($profile_display_mode); ?>"
    data-profile-img="<?php echo ($nav_profile_type === 'img') ? $nav_profile_img : ''; ?>"
    data-profile-label="<?php echo $nav_display_label; ?>"
    data-user-verified="<?php echo $user_verified ? '1' : '0'; ?>">

    <!-- ── STUDENT NAV (loaded via JS fetch at bottom) ── -->
    <div id="nav-placeholder"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Title (ORIGINAL — unchanged) -->
        <div class="page-title">
            <div class="container">
                <h1>Event Details</h1>
                <p>View event information and register to attend</p>
                <div class="breadcrumbs">
                    <a href="../Dashboard.php">Home</a>
                    <span>/</span>
                    <a href="../events.php">Events</a>
                    <span>/</span>
                    <current>Event Details</current>
                </div>
            </div>
        </div>

        <!-- Event Content (ORIGINAL layout — only content is now dynamic) -->
        <div class="event-container">
            <!-- Event Main -->
            <div class="event-main">
                <img src="<?php echo $event_img_src; ?>" alt="Event" class="event-image">

                <div class="event-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo htmlspecialchars($ev_date_disp); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo htmlspecialchars($ev_time_disp); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($ev_location); ?></span>
                    </div>
                </div>

                <div class="event-body">
                    <div class="event-header-section">
                        <h2><?php echo htmlspecialchars($ev_title); ?></h2>
                        <?php if ($ev_is_official): ?>
                            <div class="official-event-indicator">
                                <span class="official-badge">
                                    <i class="fas fa-certificate"></i>
                                    Official DepEd Event
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($ev_source): ?>
                        <div class="source-information">
                            <p><i class="fas fa-info-circle"></i> <strong>Source:</strong> <?php echo htmlspecialchars($ev_source); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($ev_description): ?>
                        <p><?php echo nl2br(htmlspecialchars($ev_description)); ?></p>
                    <?php else: ?>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam non mauris maximus, finibus dui eget, rhoncus diam. Suspendisse blandit diam at nisi rutrum, non blandit magna molestie.</p>
                        <p>Donec cursus, sapien vel convallis lobortis, dolor nisl pharetra est, ac facilisis ligula sapien vel justo. Curabitur sed semper risus, non tempus lorem.</p>
                    <?php endif; ?>

                    <h3>Event Highlights</h3>
                    <ul class="event-highlights">
                        <?php foreach ($highlights as $hl): ?>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo htmlspecialchars($hl); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h3>Event Schedule</h3>
                    <div class="schedule-table">
                        <?php foreach ($schedule as $row): ?>
                            <div class="schedule-row">
                                <div class="schedule-time"><?php echo htmlspecialchars($row['time_slot']); ?></div>
                                <div class="schedule-activity">
                                    <h4><?php echo htmlspecialchars($row['activity']); ?></h4>
                                    <?php if (!empty($row['description'])): ?>
                                        <p><?php echo htmlspecialchars($row['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── NEW: Student Group View ────────────────────────── -->
                <?php if ($my_group): ?>
                    <div class="group-view-section">
                        <h2><i class="fas fa-users"></i> Your Group</h2>
                        <div class="group-info-card">
                            <span class="group-icon"><i class="fas fa-layer-group"></i></span>
                            <div>
                                <div class="group-label">Assigned Group</div>
                                <div class="group-name-disp"><?php echo htmlspecialchars($my_group['group_name']); ?></div>
                            </div>
                        </div>

                        <div class="group-subsection">
                            <h4><i class="fas fa-user-graduate"></i> Group Members</h4>
                            <div>
                                <?php foreach ($group_members as $m): ?>
                                    <span class="member-chip">
                                        <span class="avatar-dot"><?php echo strtoupper(substr($m['student_name'], 0, 1)); ?></span>
                                        <?php echo htmlspecialchars($m['student_name']); ?>
                                        <?php if ($m['student_id'] === $student_id): ?>
                                            <strong style="color:var(--primary-color);font-size:11px;">(You)</strong>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if (!empty($group_teachers)): ?>
                            <div class="group-subsection">
                                <h4><i class="fas fa-chalkboard-teacher"></i> Teacher / Coach</h4>
                                <div>
                                    <?php foreach ($group_teachers as $t): ?>
                                        <span class="teacher-chip">
                                            <span class="avatar-dot"><i class="fas fa-chalkboard-teacher" style="font-size:10px;"></i></span>
                                            <?php echo htmlspecialchars($t['teacher_name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($my_application && $my_application['status'] === 'Approved'): ?>
                    <div class="group-view-section">
                        <div class="no-group-notice">
                            <i class="fas fa-users"></i>
                            <p>You have been approved for this event. Group assignments will be available once the admin assigns groups.</p>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- ── end group view ─────────────────────────────────── -->
            </div>

            <!-- Event Sidebar (ORIGINAL layout — form now submits to DB) -->
            <div class="event-sidebar">
                <!-- Registration Form -->
                <div class="sidebar-card">
                    <h3>Register for this Event</h3>

                    <?php if ($ev_is_official): ?>
                        <!-- Official Event Notice -->
                        <div class="official-event-notice">
                            <i class="fas fa-info-circle"></i>
                            <p><strong>This is an official DepEd event.</strong> Registration may be automatically managed based on your student status and enrollment.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ($my_application): ?>
                        <!-- Already applied -->
                        <div class="reg-alert <?php echo $my_application['status'] === 'Approved' ? 'success' : ($my_application['status'] === 'Rejected' ? 'error' : 'warning'); ?>">
                            <i class="fas fa-<?php echo $my_application['status'] === 'Approved' ? 'check-circle' : ($my_application['status'] === 'Rejected' ? 'times-circle' : 'clock'); ?>"></i>
                            Your application status: <strong><?php echo $my_application['status']; ?></strong>
                        </div>
                    <?php elseif ($ev_is_official): ?>
                        <!-- Official events may not require manual registration -->
                        <div class="reg-alert info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Automatic Registration</strong><br>
                            This official DepEd event may not require manual registration. Check with your school administration for participation details.
                        </div>
                    <?php else: ?>
                        <div id="regAlert" style="display:none;"></div>
                        <form id="registrationForm">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="student_name" value="<?php echo htmlspecialchars($student_name); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="your@email.com" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" placeholder="09123456789">
                            </div>
                            <div class="form-group">
                                <label for="student-type">You are a</label>
                                <select id="student-type" name="registrant_type">
                                    <option value="student" selected>Student</option>
                                    <option value="parent">Parent</option>
                                    <option value="teacher">Teacher</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-register" id="registerBtn">
                                <i class="fas fa-user-plus"></i>
                                Register Now
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Event Organizer -->
                <div class="sidebar-card">
                    <h3>Event Organizer</h3>
                    <div class="organizer-details">
                        <div class="organizer-image">
                            <img src="../assets/img/person/school head.jpg" alt="Organizer">
                        </div>
                        <div class="organizer-content">
                            <h4><?php echo htmlspecialchars($ev_org_name); ?></h4>
                            <p class="organizer-position"><?php echo htmlspecialchars($ev_org_pos); ?></p>
                            <div class="organizer-contact">
                                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($ev_org_contact); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related Events -->
                <div class="sidebar-card">
                    <h3>Related Events</h3>
                    <?php if (!empty($related)): ?>
                        <?php foreach ($related as $rel):
                            $rdate = new DateTime($rel['event_date']);
                        ?>
                            <div class="related-event-item" onclick="window.location='event-details.php?id=<?php echo $rel['id']; ?>'">
                                <div class="related-event-date">
                                    <span class="day"><?php echo $rdate->format('d'); ?></span>
                                    <span class="month"><?php echo $rdate->format('M'); ?></span>
                                </div>
                                <div class="related-event-info">
                                    <h4><?php echo htmlspecialchars($rel['title']); ?></h4>
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($rel['location'] ?? 'TBA'); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Static fallback if no DB events -->
                        <div class="related-event-item">
                            <div class="related-event-date"><span class="day">15</span><span class="month">Nov</span></div>
                            <div class="related-event-info">
                                <h4>Mathematics Olympiad</h4>
                                <p><i class="fas fa-map-marker-alt"></i> Room 203</p>
                            </div>
                        </div>
                        <div class="related-event-item">
                            <div class="related-event-date"><span class="day">05</span><span class="month">Dec</span></div>
                            <div class="related-event-info">
                                <h4>Literature Festival</h4>
                                <p><i class="fas fa-map-marker-alt"></i> Central Library</p>
                            </div>
                        </div>
                        <div class="related-event-item">
                            <div class="related-event-date"><span class="day">18</span><span class="month">Dec</span></div>
                            <div class="related-event-info">
                                <h4>Annual Sports Meet</h4>
                                <p><i class="fas fa-map-marker-alt"></i> School Ground</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // ── Registration Form Handler ─────────────────────────────
        const regForm = document.getElementById('registrationForm');
        if (regForm) {
            regForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const btn = document.getElementById('registerBtn');
                const alertEl = document.getElementById('regAlert');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

                fetch('', {
                        method: 'POST',
                        body: new URLSearchParams(new FormData(this)),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        alertEl.style.display = 'flex';
                        if (data.status === 'success') {
                            alertEl.className = 'reg-alert success';
                            alertEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                            regForm.style.display = 'none';
                        } else if (data.status === 'already') {
                            alertEl.className = 'reg-alert info';
                            alertEl.innerHTML = '<i class="fas fa-info-circle"></i> ' + data.message + ' Status: <strong>' + data.app_status + '</strong>';
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-user-plus"></i> Register Now';
                        } else {
                            alertEl.className = 'reg-alert error';
                            alertEl.innerHTML = '<i class="fas fa-times-circle"></i> ' + data.message;
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-user-plus"></i> Register Now';
                        }
                    })
                    .catch(() => {
                        alertEl.style.display = 'flex';
                        alertEl.className = 'reg-alert error';
                        alertEl.innerHTML = '<i class="fas fa-times-circle"></i> An error occurred. Please try again.';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-user-plus"></i> Register Now';
                    });
            });
        }
    </script>

    <!-- ══ STUDENT NAV LOADER ══════════════════════════════════════════════
         Fetches Student_nav.php, resolves all data-nav-href links relative
         to the current page directory, then injects into #nav-placeholder.
    ══════════════════════════════════════════════════════════════════════ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var placeholder = document.getElementById('nav-placeholder');
            if (!placeholder) {
                console.warn('[NavLoader] #nav-placeholder not found');
                return;
            }

            // Compute the directory of the current page so relative links resolve correctly
            var pageDir = window.location.pathname.replace(/\/[^\/]*$/, '/');

            fetch('Student_nav.php')
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.text();
                })
                .then(function(html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;

                    // ── Resolve data-nav-href → real href using current page directory ──
                    tmp.querySelectorAll('[data-nav-href]').forEach(function(el) {
                        var rel = el.getAttribute('data-nav-href');
                        if (rel.startsWith('../')) {
                            var parentDir = pageDir.replace(/\/[^\/]+\/$/, '/');
                            el.setAttribute('href', parentDir + rel.slice(3));
                        } else {
                            el.setAttribute('href', pageDir + rel);
                        }
                        el.removeAttribute('data-nav-href');
                    });

                    // ── Resolve relative image src paths ──
                    tmp.querySelectorAll('img[src]').forEach(function(img) {
                        var src = img.getAttribute('src');
                        if (src && !src.startsWith('/') && !src.startsWith('http')) {
                            img.setAttribute('src', pageDir + src);
                        }
                    });

                    // ── Move <style> tags into <head> ──
                    tmp.querySelectorAll('style').forEach(function(styleEl) {
                        document.head.appendChild(styleEl.cloneNode(true));
                        styleEl.remove();
                    });

                    // ── Insert markup before placeholder then remove it ──
                    while (tmp.firstChild) {
                        placeholder.parentNode.insertBefore(tmp.firstChild, placeholder);
                    }
                    placeholder.remove();

                    // ── Re-execute <script> tags (innerHTML does not run them) ──
                    tmp.querySelectorAll('script').forEach(function(oldScript) {
                        var newScript = document.createElement('script');
                        newScript.textContent = oldScript.textContent;
                        document.body.appendChild(newScript);
                    });

                    // ── Populate student name & grade from body data attributes ──
                    var nameEl = document.getElementById('navStudentName');
                    var gradeEl = document.getElementById('navGradeLevel');
                    if (nameEl && document.body.dataset.studentName) nameEl.textContent = document.body.dataset.studentName;
                    if (gradeEl && document.body.dataset.gradeLevel) gradeEl.textContent = document.body.dataset.gradeLevel;

                    // ── Boot the profile chip (avatar + label) ────────────────────
                    (function waitForStudentNav(attempts) {
                        if (window.StudentNav && typeof window.StudentNav.bootProfileFromBody === 'function') {
                            window.StudentNav.bootProfileFromBody();
                        } else if (attempts > 0) {
                            setTimeout(function() {
                                waitForStudentNav(attempts - 1);
                            }, 60);
                        }
                    })(20);

                    // ── Highlight the current page link ──
                    var current = window.location.pathname.split('/').pop() || 'event-details.php';
                    document.querySelectorAll('.sidebar .menu-item').forEach(function(item) {
                        var href = (item.getAttribute('href') || '').split('/').pop();
                        item.classList.toggle('active', href === current);
                    });

                    console.log('[NavLoader] Student_nav.php loaded — base dir: ' + pageDir);
                })
                .catch(function(err) {
                    console.error('[NavLoader] Failed to load Student_nav.php:', err);
                });
        });
    </script>
</body>

</html>