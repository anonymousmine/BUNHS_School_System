<?php



/**

 * chatbox.php  (student-side)

 * Enhanced: file request system, club group chats, redesigned UI.

 * UI Redesign: Deep Forest #102C26 header, improved chat visibility

 */



if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../session_config.php';



if (!isset($_SESSION['student_id'])) {

    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

    header('Location: index.php');

    exit;

}



include '../db_connection.php';



$student_id   = $_SESSION['student_id'];

$student_name = $_SESSION['student_name'] ?? 'Student';

$grade_level  = $_SESSION['grade_level']  ?? 'Grade 10';

$initials     = strtoupper(substr(strip_tags($student_name), 0, 1));

$notification_preference = null;



$chatApiPath    = '../admin_account/chat_api.php';



// ── FETCH FULL STUDENT PROFILE ────────────────────────────────────────────────

$db_student = [];

try {

    $stmt = $conn->prepare(

        "SELECT first_name, last_name, grade_level,

                phone, email, photo,

                notification_preference,

                phone_verified, email_verified,

                login_method

         FROM students WHERE student_id = ? LIMIT 1"

    );

    if ($stmt) {

        $stmt->bind_param("s", $student_id);

        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            $db_student   = $row;

            $student_name = trim($row['first_name'] . ' ' . $row['last_name']);

            $grade_level  = $row['grade_level'];

            $notification_preference = $row['notification_preference'];

            $initials = strtoupper(substr($student_name, 0, 1));

        }

        $stmt->close();

    }

} catch (Exception $e) {

    try {

        $stmt = $conn->prepare("SELECT first_name, last_name, grade_level, notification_preference FROM students WHERE student_id = ?");

        if ($stmt) {

            $stmt->bind_param("s", $student_id);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {

                $db_student   = $row;

                $student_name = trim($row['first_name'] . ' ' . $row['last_name']);

                $grade_level  = $row['grade_level'];

                $notification_preference = $row['notification_preference'];

                $initials = strtoupper(substr($student_name, 0, 1));

            }

            $stmt->close();

        }

    } catch (Exception $e2) {

        error_log("DB error: " . $e2->getMessage());

    }

}



$login_method   = isset($db_student['login_method']) ? $db_student['login_method'] : null;

$phone_verified = !empty($db_student['phone_verified']);

$email_verified = !empty($db_student['email_verified']);

$profile_photo  = !empty($db_student['photo']) ? $db_student['photo'] : null;

$student_email  = !empty($db_student['email'])  ? $db_student['email']  : null;

$student_phone  = !empty($db_student['phone'])  ? $db_student['phone']  : null;



if (!$login_method && !empty($_SESSION['login_method']))   $login_method  = $_SESSION['login_method'];

if (!$student_email && !empty($_SESSION['student_email'])) $student_email = $_SESSION['student_email'];

if (!$student_phone && !empty($_SESSION['student_phone'])) $student_phone = $_SESSION['student_phone'];



if ($login_method === 'email' || ($email_verified && $student_email)) {

    $profile_display_mode = 'email';

} elseif ($login_method === 'phone' || ($phone_verified && $student_phone)) {

    $profile_display_mode = 'phone';

} elseif (!empty($_SESSION['notif_dismissed'])) {

    $profile_display_mode = 'skip';

} else {

    $profile_display_mode = 'none';

}



$user_verified = ($email_verified || $phone_verified || !empty($_SESSION['dash_email_verified']));



$nav_profile_img  = 'assets/img/person/unknown.jpg';

$nav_profile_type = 'icon';

if ($profile_display_mode === 'email') {

    if ($profile_photo) {

        $nav_profile_img = htmlspecialchars($profile_photo, ENT_QUOTES, 'UTF-8');

        $nav_profile_type = 'img';

    } elseif (!empty($_SESSION['google_avatar'])) {

        $nav_profile_img = htmlspecialchars($_SESSION['google_avatar'], ENT_QUOTES, 'UTF-8');

        $nav_profile_type = 'img';

    }

}



switch ($profile_display_mode) {

    case 'email':

        $nav_display_label = $student_email ? htmlspecialchars($student_email, ENT_QUOTES, 'UTF-8') : htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');

        break;

    case 'phone':

        $nav_display_label = $student_phone ? htmlspecialchars($student_phone, ENT_QUOTES, 'UTF-8') : htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');

        break;

    default:

        $nav_display_label = '';

        break;

}

?>

<!DOCTYPE html>

<html lang="en">



<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Messages – BUNHS Student Portal</title>

    <meta name="csrf-token" content="<?= getCSRFToken() ?>">

    <link rel="stylesheet" href="../admin_account/admin_assets/cs/admin_style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">



    <style>

        /* ════════════════════════════════════════════

           ROOT VARIABLES

        ════════════════════════════════════════════ */

        :root {

            /* Deep Forest Palette */

            --forest: #102C26;

            --forest-mid: #1a4a40;

            --forest-light: #245c50;

            --forest-pale: rgba(16, 44, 38, .08);

            --forest-glow: rgba(16, 44, 38, .18);



            /* Accent */

            --accent: #3db88a;

            --accent-soft: rgba(61, 184, 138, .12);

            --accent-border: rgba(61, 184, 138, .3);



            /* Legacy alias (used in older CSS) */

            --moss: #3db88a;

            --moss-dark: #2a9970;

            --moss-ultra: rgba(61, 184, 138, .1);



            /* Surface */

            --sidebar-w: 280px;

            --bg: #f0f4f2;

            --surface: #ffffff;

            --surface2: #f5f9f7;

            --border: #dde8e4;

            --border2: #c8d8d3;



            /* Text */

            --text: #0d1f1b;

            --text-2: #3a5248;

            --muted: #6b8c82;



            /* Status */

            --success: #10b981;

            --warning: #f59e0b;

            --danger: #ef4444;



            /* Shadows */

            --shadow-sm: 0 1px 4px rgba(16, 44, 38, .07);

            --shadow-md: 0 4px 20px rgba(16, 44, 38, .10);

            --shadow-lg: 0 12px 40px rgba(16, 44, 38, .14);



            /* Shape */

            --radius: 16px;

            --radius-sm: 10px;



            /* Typography */

            --font: 'Plus Jakarta Sans', sans-serif;

            --font-display: 'Outfit', sans-serif;

        }



        *,

        *::before,

        *::after {

            box-sizing: border-box;

            margin: 0;

            padding: 0;

        }



        body {

            font-family: var(--font);

            background: var(--bg);

            min-height: 100vh;

            color: var(--text);

        }



        /* ════════════════════════════════════════════

           MAIN LAYOUT

        ════════════════════════════════════════════ */

        .main-content {

            margin-left: var(--sidebar-w);

            margin-right: 0;

            min-height: 100vh;

            padding: 22px 24px;

            display: flex;

            flex-direction: column;

            width: calc(100% - var(--sidebar-w));

        }



        /* ════════════════════════════════════════════

           PAGE HEADER — DEEP FOREST

        ════════════════════════════════════════════ */

        .page-header {

            background: var(--forest);

            border-radius: var(--radius);

            padding: 18px 24px;

            margin-bottom: 20px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow: var(--shadow-md);

            position: relative;

            overflow: hidden;

        }



        .page-header::before {

            content: '';

            position: absolute;

            top: -50px;

            right: -30px;

            width: 180px;

            height: 180px;

            border-radius: 50%;

            background: rgba(61, 184, 138, .06);

            pointer-events: none;

        }



        .page-header h1 {

            font-family: var(--font-display);

            font-size: 22px;

            font-weight: 700;

            color: #fff;

            display: flex;

            align-items: center;

            gap: 12px;

        }



        .page-header h1 i {

            width: 38px;

            height: 38px;

            background: rgba(255, 255, 255, .12);

            border-radius: 9px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 16px;

            color: var(--accent);

        }



        .page-header p {

            color: rgba(255, 255, 255, .5);

            font-size: 12.5px;

            margin-top: 2px;

            padding-left: 50px;

        }



        .header-date {

            display: flex;

            align-items: center;

            gap: 7px;

            padding: 8px 14px;

            background: rgba(255, 255, 255, .1);

            border: 1px solid rgba(255, 255, 255, .15);

            border-radius: var(--radius-sm);

            color: rgba(255, 255, 255, .75);

            font-size: 12.5px;

            white-space: nowrap;

        }



        .header-date i {

            color: var(--accent);

        }



        /* ════════════════════════════════════════════

           CHAT LAYOUT

        ════════════════════════════════════════════ */

        .chat-wrap {

            display: grid;

            grid-template-columns: 290px 1fr;

            gap: 18px;

            flex: 1;

            height: calc(100vh - 148px);

            min-height: 0;

        }



        /* ── Sidebar panel ── */

        .chat-panel {

            background: var(--surface);

            border-radius: var(--radius);

            box-shadow: var(--shadow-md);

            border: 1px solid var(--border);

            display: flex;

            flex-direction: column;

            overflow: hidden;

        }



        .panel-header {

            padding: 14px 18px 10px;

            border-bottom: 1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            background: var(--forest);

        }



        .panel-header h3 {

            font-family: var(--font-display);

            font-size: 13.5px;

            font-weight: 700;

            color: #fff;

        }



        .panel-badge {

            background: var(--accent);

            color: #fff;

            font-size: 10px;

            font-weight: 700;

            padding: 2px 8px;

            border-radius: 20px;

        }



        .panel-search {

            padding: 10px 14px;

            border-bottom: 1px solid var(--border);

            background: var(--surface2);

        }



        .panel-search input {

            width: 100%;

            padding: 8px 12px;

            border: 1.5px solid var(--border);

            border-radius: var(--radius-sm);

            font-size: 13px;

            outline: none;

            font-family: var(--font);

            background: var(--surface);

            transition: border-color .15s;

            color: var(--text);

        }



        .panel-search input:focus {

            border-color: var(--forest);

        }



        .contact-list {

            flex: 1;

            overflow-y: auto;

        }



        .contact-list::-webkit-scrollbar {

            width: 3px;

        }



        .contact-list::-webkit-scrollbar-thumb {

            background: var(--border2);

            border-radius: 3px;

        }



        .conv-section-label {

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 1px;

            text-transform: uppercase;

            color: var(--muted);

            padding: 10px 18px 4px;

            background: var(--surface2);

        }



        .conv-item {

            display: flex;

            align-items: center;

            gap: 11px;

            padding: 12px 16px;

            cursor: pointer;

            border-bottom: 1px solid #f0f5f2;

            transition: background .14s;

            position: relative;

        }



        .conv-item:hover {

            background: var(--accent-soft);

        }



        .conv-item.active {

            background: var(--forest-pale);

            border-left: 3px solid var(--forest);

        }



        .conv-avatar {

            width: 42px;

            height: 42px;

            border-radius: 11px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #fff;

            font-weight: 700;

            font-size: 15px;

        }



        .conv-avatar.admin-av {

            background: linear-gradient(135deg, var(--forest-mid), var(--forest));

            box-shadow: 0 3px 10px rgba(16, 44, 38, .2);

        }



        .conv-avatar.club-av {

            background: linear-gradient(135deg, #2d7a60, #1a4a38);

            border: 2px solid rgba(61, 184, 138, .3);

            position: relative;

        }



        .conv-avatar.club-av::after {

            content: '';

            position: absolute;

            bottom: -2px;

            right: -2px;

            width: 11px;

            height: 11px;

            background: var(--accent);

            border-radius: 50%;

            border: 2px solid var(--surface);

        }



        .conv-info {

            flex: 1;

            min-width: 0;

        }



        .conv-name {

            font-weight: 600;

            font-size: 13.5px;

            color: var(--text);

            margin-bottom: 2px;

            display: flex;

            align-items: center;

            gap: 6px;

        }



        .club-tag {

            background: var(--accent-soft);

            color: var(--forest);

            font-size: 9px;

            font-weight: 700;

            letter-spacing: .5px;

            text-transform: uppercase;

            padding: 1px 6px;

            border-radius: 20px;

            border: 1px solid var(--accent-border);

        }



        .conv-preview {

            font-size: 12px;

            color: var(--muted);

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }



        .conv-time {

            font-size: 10px;

            color: #aab;

            white-space: nowrap;

            margin-left: 4px;

        }



        .conv-unread {

            background: var(--forest);

            color: #fff;

            font-size: 10px;

            font-weight: 700;

            padding: 2px 7px;

            border-radius: 10px;

            position: absolute;

            top: 12px;

            right: 14px;

        }



        /* ════════════════════════════════════════════

           CHAT WINDOW

        ════════════════════════════════════════════ */

        .chat-win {

            background: var(--surface);

            border-radius: var(--radius);

            box-shadow: var(--shadow-md);

            border: 1px solid var(--border);

            display: flex;

            flex-direction: column;

            overflow: hidden;

        }



        /* Header — deep forest */

        .chat-header {

            padding: 14px 20px;

            border-bottom: 1px solid var(--border);

            display: flex;

            align-items: center;

            gap: 13px;

            background: var(--forest);

        }



        .chat-header-avatar {

            width: 42px;

            height: 42px;

            border-radius: 11px;

            background: rgba(255, 255, 255, .15);

            border: 2px solid rgba(255, 255, 255, .18);

            display: flex;

            align-items: center;

            justify-content: center;

            color: #fff;

            font-weight: 700;

            font-size: 16px;

        }



        .chat-header-info h3 {

            font-size: 15px;

            font-weight: 700;

            color: #fff;

        }



        .online-dot {

            display: inline-block;

            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: var(--accent);

            margin-right: 5px;

        }



        .chat-header-status {

            font-size: 12px;

            color: rgba(255, 255, 255, .55);

            margin-top: 1px;

        }



        .club-header-badge {

            margin-left: auto;

            background: rgba(255, 255, 255, .12);

            color: rgba(255, 255, 255, .8);

            border: 1px solid rgba(255, 255, 255, .2);

            padding: 4px 12px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

            display: flex;

            align-items: center;

            gap: 5px;

        }



        /* Messages */

        .chat-messages {

            flex: 1;

            padding: 20px;

            overflow-y: auto;

            display: flex;

            flex-direction: column;

            gap: 12px;

            background: #f4f8f6;

            background-image: radial-gradient(circle at 5% 10%, rgba(16, 44, 38, .025) 0%, transparent 40%);

        }



        .chat-messages::-webkit-scrollbar {

            width: 4px;

        }



        .chat-messages::-webkit-scrollbar-thumb {

            background: var(--border2);

            border-radius: 4px;

        }



        /* Date divider */

        .date-sep {

            text-align: center;

            font-size: 11px;

            color: var(--muted);

            position: relative;

            margin: 6px 0;

        }



        .date-sep span {

            background: #f4f8f6;

            padding: 0 12px;

            position: relative;

            z-index: 1;

        }



        .date-sep::before {

            content: '';

            position: absolute;

            left: 0;

            right: 0;

            top: 50%;

            height: 1px;

            background: var(--border);

        }



        /* Bubbles */

        .msg-row {

            display: flex;

            gap: 9px;

            max-width: 76%;

            animation: bubbleIn .2s ease;

        }



        @keyframes bubbleIn {

            from {

                opacity: 0;

                transform: translateY(6px);

            }



            to {

                opacity: 1;

                transform: translateY(0);

            }

        }



        .msg-row.sent {

            align-self: flex-end;

            flex-direction: row-reverse;

        }



        .msg-av {

            width: 32px;

            height: 32px;

            border-radius: 9px;

            flex-shrink: 0;

            align-self: flex-end;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #fff;

            font-weight: 700;

            font-size: 12px;

            background: linear-gradient(135deg, var(--forest-mid), var(--forest));

            box-shadow: 0 2px 8px rgba(16, 44, 38, .2);

        }



        .msg-row.sent .msg-av {

            background: linear-gradient(135deg, var(--accent), #2a9970);

        }



        .msg-bubble {

            background: var(--surface);

            padding: 10px 14px;

            border-radius: 14px 14px 14px 4px;

            box-shadow: var(--shadow-sm);

            border: 1px solid var(--border);

        }



        .msg-row.sent .msg-bubble {

            background: var(--forest);

            color: #fff;

            border-radius: 14px 14px 4px 14px;

            border: none;

            box-shadow: 0 3px 12px rgba(16, 44, 38, .22);

        }



        .msg-text {

            font-size: 13.5px;

            line-height: 1.55;

            word-break: break-word;

        }



        .msg-time {

            font-size: 10.5px;

            color: var(--muted);

            margin-top: 4px;

            display: flex;

            align-items: center;

            gap: 3px;

        }



        .msg-row.sent .msg-time {

            color: rgba(255, 255, 255, .5);

            justify-content: flex-end;

        }



        /* File request bubble */

        .msg-bubble.file-req-bubble {

            background: #fffdf0;

            border: 1.5px solid #ffe58a;

            border-radius: 14px 14px 14px 4px;

        }



        .msg-row.sent .msg-bubble.file-req-bubble {

            background: linear-gradient(135deg, var(--forest-mid), var(--forest));

            border: none;

        }



        .file-req-header {

            display: flex;

            align-items: center;

            gap: 7px;

            margin-bottom: 8px;

        }



        .file-req-icon {

            width: 28px;

            height: 28px;

            border-radius: 7px;

            background: linear-gradient(135deg, var(--forest-mid), var(--forest));

            display: flex;

            align-items: center;

            justify-content: center;

            color: #fff;

            font-size: 12px;

        }



        .file-req-label {

            font-size: 11px;

            font-weight: 700;

            letter-spacing: .3px;

            color: var(--forest);

        }



        .msg-row.sent .file-req-label {

            color: rgba(255, 255, 255, .75);

        }



        .file-req-name {

            font-size: 13px;

            font-weight: 600;

            margin-bottom: 4px;

        }



        .file-req-reason {

            font-size: 12.5px;

            opacity: .75;

        }



        .req-status-pill {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            margin-top: 8px;

            padding: 3px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 600;

        }



        .req-status-pill.pending {

            background: #fef9c3;

            color: #854d0e;

        }



        .req-status-pill.approved {

            background: #dcfce7;

            color: #14532d;

        }



        .req-status-pill.rejected {

            background: #fee2e2;

            color: #7f1d1d;

        }



        .file-download-btn {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 8px;

            padding: 7px 14px;

            background: var(--forest);

            color: #fff;

            border: none;

            border-radius: 8px;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;

            text-decoration: none;

            transition: all .18s;

        }



        .file-download-btn:hover {

            background: var(--forest-mid);

            transform: translateY(-1px);

            box-shadow: 0 4px 12px var(--forest-glow);

        }



        /* ════════════════════════════════════════════

           INPUT AREA

        ════════════════════════════════════════════ */

        .chat-input-wrap {

            padding: 14px 18px;

            border-top: 1px solid var(--border);

            background: var(--surface);

            position: relative;

        }



        .input-row {

            display: flex;

            align-items: center;

            gap: 10px;

        }



        .file-req-btn {

            width: 40px;

            height: 40px;

            border: 1.5px solid var(--border);

            border-radius: var(--radius-sm);

            background: var(--surface2);

            color: var(--muted);

            font-size: 15px;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            transition: all .18s;

            flex-shrink: 0;

            position: relative;

        }



        .file-req-btn:hover,

        .file-req-btn.active {

            border-color: var(--forest);

            color: var(--forest);

            background: var(--forest-pale);

        }



        .file-dropdown {

            position: absolute;

            bottom: calc(100% + 10px);

            left: 18px;

            width: 300px;

            background: var(--surface);

            border: 1px solid var(--border);

            border-radius: var(--radius);

            box-shadow: var(--shadow-lg);

            z-index: 200;

            opacity: 0;

            transform: translateY(8px);

            pointer-events: none;

            transition: all .2s cubic-bezier(.4, 0, .2, 1);

        }



        .file-dropdown.open {

            opacity: 1;

            transform: translateY(0);

            pointer-events: all;

        }



        .file-dropdown-header {

            padding: 11px 16px;

            border-bottom: 1px solid var(--border);

            font-weight: 700;

            font-size: 12px;

            color: var(--text);

            letter-spacing: .3px;

            display: flex;

            align-items: center;

            gap: 7px;

            background: var(--forest);

            border-radius: var(--radius) var(--radius) 0 0;

            color: #fff;

        }



        .file-dropdown-header i {

            color: var(--accent);

        }



        .file-dropdown-body {

            max-height: 240px;

            overflow-y: auto;

        }



        .file-dropdown-body::-webkit-scrollbar {

            width: 3px;

        }



        .file-dropdown-body::-webkit-scrollbar-thumb {

            background: var(--border2);

            border-radius: 3px;

        }



        .file-dropdown-item {

            display: flex;

            align-items: center;

            gap: 11px;

            padding: 11px 16px;

            cursor: pointer;

            transition: background .14s;

            border-bottom: 1px solid #f0f5f2;

        }



        .file-dropdown-item:last-child {

            border-bottom: none;

        }



        .file-dropdown-item:hover {

            background: var(--accent-soft);

        }



        .file-dropdown-item-icon {

            width: 34px;

            height: 34px;

            border-radius: 8px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 16px;

            flex-shrink: 0;

        }



        .file-dropdown-item-icon.pdf {

            background: #fee2e2;

            color: #ef4444;

        }



        .file-dropdown-item-icon.doc {

            background: #dbeafe;

            color: #3b82f6;

        }



        .file-dropdown-item-icon.xls {

            background: #dcfce7;

            color: #16a34a;

        }



        .file-dropdown-item-icon.img {

            background: #ede9fe;

            color: #7c3aed;

        }



        .file-dropdown-item-icon.other {

            background: #f3f4f6;

            color: #6b7280;

        }



        .file-dropdown-item-name {

            font-size: 13px;

            font-weight: 600;

            color: var(--text);

        }



        .file-dropdown-item-cat {

            font-size: 11px;

            color: var(--muted);

            margin-top: 1px;

            display: flex;

            align-items: center;

            gap: 4px;

        }



        .file-dropdown-empty {

            padding: 24px;

            text-align: center;

            color: var(--muted);

            font-size: 13px;

        }



        .file-dropdown-empty i {

            font-size: 24px;

            margin-bottom: 8px;

            display: block;

            opacity: .4;

        }



        .reason-bar {

            display: none;

            align-items: center;

            gap: 8px;

            padding: 8px 0 0;

            border-top: 1px dashed var(--border);

            margin-top: 8px;

        }



        .reason-bar.show {

            display: flex;

        }



        .reason-bar-label {

            font-size: 11px;

            font-weight: 700;

            color: var(--forest);

            white-space: nowrap;

        }



        .reason-bar input {

            flex: 1;

            padding: 7px 11px;

            border: 1.5px solid var(--border);

            border-radius: 8px;

            font-size: 13px;

            outline: none;

            font-family: var(--font);

            transition: border-color .15s;

        }



        .reason-bar input:focus {

            border-color: var(--forest);

        }



        .reason-cancel {

            width: 28px;

            height: 28px;

            border-radius: 7px;

            border: 1px solid var(--border);

            background: transparent;

            color: var(--muted);

            cursor: pointer;

            font-size: 13px;

            display: flex;

            align-items: center;

            justify-content: center;

            transition: all .15s;

        }



        .reason-cancel:hover {

            border-color: #ef4444;

            color: #ef4444;

            background: #fee2e2;

        }



        .msg-input {

            flex: 1;

            padding: 11px 16px;

            border: 1.5px solid var(--border);

            border-radius: 12px;

            font-size: 14px;

            font-family: var(--font);

            outline: none;

            transition: border-color .15s, box-shadow .15s;

            color: var(--text);

            background: var(--surface2);

        }



        .msg-input:focus {

            border-color: var(--forest);

            box-shadow: 0 0 0 3px rgba(16, 44, 38, .08);

            background: var(--surface);

        }



        .send-btn {

            height: 40px;

            padding: 0 18px;

            background: var(--forest);

            color: #fff;

            border: none;

            border-radius: var(--radius-sm);

            font-size: 13.5px;

            font-weight: 600;

            font-family: var(--font);

            cursor: pointer;

            display: flex;

            align-items: center;

            gap: 7px;

            transition: all .18s;

            box-shadow: 0 3px 12px rgba(16, 44, 38, .22);

        }



        .send-btn:hover {

            background: var(--forest-mid);

            transform: translateY(-2px);

            box-shadow: 0 6px 18px rgba(16, 44, 38, .3);

        }



        .send-btn:disabled {

            opacity: .6;

            cursor: not-allowed;

            transform: none;

            box-shadow: none;

        }



        /* ════════════════════════════════════════════

           TOAST

        ════════════════════════════════════════════ */

        .toast-zone {

            position: fixed;

            bottom: 24px;

            right: 24px;

            z-index: 9999;

        }



        .toast {

            background: var(--surface);

            border-radius: 11px;

            padding: 13px 18px;

            box-shadow: var(--shadow-lg);

            display: flex;

            align-items: center;

            gap: 10px;

            margin-top: 8px;

            transform: translateX(120%);

            transition: transform .3s ease;

            border-left: 4px solid transparent;

            font-size: 13.5px;

            min-width: 260px;

        }



        .toast.show {

            transform: translateX(0);

        }



        .toast.success {

            border-color: #22c55e;

        }



        .toast.error {

            border-color: #ef4444;

        }



        .toast i {

            font-size: 17px;

        }



        .toast.success i {

            color: #22c55e;

        }



        .toast.error i {

            color: #ef4444;

        }



        /* ════════════════════════════════════════════

           LOADING STATE

        ════════════════════════════════════════════ */

        .chat-loading {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 10px;

            padding: 48px;

            color: var(--muted);

            font-size: 13.5px;

        }



        @keyframes spin {

            to {

                transform: rotate(360deg);

            }

        }



        .spin {

            animation: spin .8s linear infinite;

        }



        /* ════════════════════════════════════════════

           RESPONSIVE

        ════════════════════════════════════════════ */

        @media (max-width: 1000px) {

            .main-content {

                margin-left: 0;

            }

        }



        @media (max-width: 820px) {

            .chat-wrap {

                grid-template-columns: 1fr;

            }



            .chat-panel {

                display: none;

            }

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



    <div id="nav-placeholder"></div>



    <main class="main-content">



        <!-- ══ HEADER — DEEP FOREST ═══════════════════════════════ -->

        <header class="page-header">

            <div>

                <h1><i class="fas fa-comments"></i> Messages</h1>

                <p>Chat with admin or your club members</p>

            </div>

            <div class="header-date">

                <i class="fas fa-calendar-alt"></i>

                <span id="currentDate"></span>

            </div>

        </header>



        <div class="chat-wrap">

            <!-- ── Left panel ── -->

            <div class="chat-panel">

                <div class="panel-header">

                    <h3>Conversations</h3>

                </div>

                <div class="panel-search">

                    <input type="text" id="convSearch" placeholder="Search chats…" oninput="filterConvs(this.value)">

                </div>

                <div class="contact-list" id="contactList">

                    <div class="conv-section-label">Direct</div>

                    <div class="conv-item active" id="adminConvItem" onclick="openAdminChat()">

                        <div class="conv-avatar admin-av">AD</div>

                        <div class="conv-info">

                            <div class="conv-name">Admin Department</div>

                            <div class="conv-preview" id="adminPreview">Loading…</div>

                        </div>

                    </div>

                    <div id="clubChatsSection"></div>

                </div>

            </div>



            <!-- ── Chat window ── -->

            <div class="chat-win">

                <div class="chat-header" id="chatHeader">

                    <div class="chat-header-avatar" id="chatAvatar">AD</div>

                    <div class="chat-header-info">

                        <h3 id="chatName">Admin Department</h3>

                        <div class="chat-header-status">

                            <span class="online-dot"></span>Online

                        </div>

                    </div>

                    <div id="clubHeaderBadge" style="display:none;" class="club-header-badge">

                        <i class="fas fa-users"></i>

                        <span id="clubMemberCount">0 members</span>

                    </div>

                </div>



                <div class="chat-messages" id="chatMessages">

                    <div class="chat-loading">

                        <i class="fas fa-spinner spin"></i> Loading messages…

                    </div>

                </div>



                <div class="chat-input-wrap">

                    <div class="file-dropdown" id="fileDropdown">

                        <div class="file-dropdown-header">

                            <i class="fas fa-lock"></i> Request a Restricted File

                        </div>

                        <div class="file-dropdown-body" id="fileDropdownBody">

                            <div class="file-dropdown-empty">

                                <i class="fas fa-spinner spin"></i> Loading files…

                            </div>

                        </div>

                    </div>



                    <div class="input-row">

                        <button class="file-req-btn" id="fileReqBtn" title="Request a restricted file"

                            onclick="toggleFileDropdown()" style="display:none;">

                            <i class="fas fa-paperclip"></i>

                        </button>

                        <input type="text" class="msg-input" id="msgInput"

                            placeholder="Type your message…"

                            onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMsg();}">

                        <button class="send-btn" id="sendBtn" onclick="sendMsg()">

                            <i class="fas fa-paper-plane"></i> Send

                        </button>

                    </div>



                    <div class="reason-bar" id="reasonBar">

                        <span class="reason-bar-label"><i class="fas fa-file-lock"></i> Reason:</span>

                        <input type="text" id="reasonInput"

                            placeholder="Why do you need this file?"

                            onkeydown="if(event.key==='Enter'){event.preventDefault();sendMsg();}">

                        <button class="reason-cancel" onclick="cancelFileRequest()" title="Cancel">

                            <i class="fas fa-times"></i>

                        </button>

                    </div>

                </div>

            </div>

        </div>

    </main>



    <div class="toast-zone" id="toastZone"></div>



    <script>

        /* ════════ CONFIG & STATE ════════ */

        const API = '<?= htmlspecialchars($chatApiPath,    ENT_QUOTES, "UTF-8") ?>';

        const FILE_REQ_API = '<?= htmlspecialchars($fileRequestApi, ENT_QUOTES, "UTF-8") ?>';

        const CLUB_API = '<?= htmlspecialchars($clubChatApi, ENT_QUOTES, "UTF-8") ?>';

        const STUDENT_INIT = '<?= $initials ?>';



        let convId = 0;

        let activeChatId = 0;

        let activeMode = 'admin';

        let pollTimer = null;

        let selectedFile = null;



        /* ════════ GLOBAL FUNCTIONS ════════ */

        function filterConvs(q) {

            q = q.toLowerCase();

            document.querySelectorAll('.conv-item').forEach(el => {

                const name = el.querySelector('.conv-name')?.textContent?.toLowerCase() || '';

                el.style.display = (!q || name.includes(q)) ? '' : 'none';

            });

        }



        function getCSRFToken() {

            // Get CSRF token from meta tag

            const metaTag = document.querySelector('meta[name="csrf-token"]');

            if (metaTag) {

                return metaTag.getAttribute('content');

            }

            

            console.warn('CSRF token not found in meta tag');

            return '';

        }



        function toast(msg, type = 'success') {

            const zone = document.getElementById('toastZone');

            const t = document.createElement('div');

            t.className = `toast ${type}`;

            t.innerHTML = `<i class="fas ${type==='success'?'fa-check-circle':'fa-exclamation-circle'}"></i><span>${msg}</span>`;

            zone.appendChild(t);

            requestAnimationFrame(() => t.classList.add('show'));

            setTimeout(() => {

                t.classList.remove('show');

                setTimeout(() => t.remove(), 320);

            }, 3500);

        }



        function openAdminChat() {

            activeMode = 'admin';

            activeChatId = convId;



            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));

            document.getElementById('adminConvItem').classList.add('active');

            document.getElementById('chatName').textContent = 'Admin Department';

            document.getElementById('chatAvatar').textContent = 'AD';

            document.getElementById('clubHeaderBadge').style.display = 'none';

            document.getElementById('fileReqBtn').style.display = '';



            clearInterval(pollTimer);

            loadAdminMessages();

            pollTimer = setInterval(loadAdminMessages, 5000);

        }



        async function sendMsg() {

            try {

                const msgInput = document.getElementById('msgInput');

                const reasonInput = document.getElementById('reasonInput');

                const sendBtn = document.getElementById('sendBtn');



                if (selectedFile) {

                    const reason = reasonInput.value.trim();

                    if (!reason) {

                        reasonInput.focus();

                        toast('Please enter a reason for the file request.', 'error');

                        return;

                    }

                    sendBtn.disabled = true;

                    await sendFileRequest(selectedFile, reason);

                    sendBtn.disabled = false;

                    return;

                }



                const text = msgInput.value.trim();

                if (!text) return;

                sendBtn.disabled = true;

                msgInput.value = '';



                if (activeMode === 'admin') {

                    const fd = new FormData();

                    fd.append('action', 'send_message');

                    fd.append('message', text);

                    if (convId) fd.append('conversation_id', convId);

                    fd.append('csrf_token', getCSRFToken());

                    

                    const res = await fetch(API, {

                        method: 'POST',

                        body: fd

                    });

                    

                    if (!res.ok) {

                        throw new Error(`HTTP error! status: ${res.status}`);

                    }

                    

                    const data = await res.json();

                    sendBtn.disabled = false;

                    if (data.success) {

                        if (data.conv_id && !convId) convId = data.conv_id;

                        await loadAdminMessages();

                    } else {

                        msgInput.value = text;

                        toast(data.message || 'Could not send message. Please try again.', 'error');

                    }

                } else {

                    const fd = new FormData();

                    fd.append('action', 'send_club_message');

                    fd.append('club_id', activeChatId);

                    fd.append('message', text);

                    fd.append('csrf_token', getCSRFToken());

                    

                    const res = await fetch(CLUB_API, {

                        method: 'POST',

                        body: fd

                    });

                    

                    if (!res.ok) {

                        throw new Error(`HTTP error! status: ${res.status}`);

                    }

                    

                    const data = await res.json();

                    sendBtn.disabled = false;

                    if (data.success) {

                        await loadClubMessages(activeChatId);

                    } else {

                        msgInput.value = text;

                        toast(data.message || 'Could not send message.', 'error');

                    }

                }

            } catch (error) {

                console.error('Send message error:', error);

                const sendBtn = document.getElementById('sendBtn');

                const msgInput = document.getElementById('msgInput');

                

                if (sendBtn) sendBtn.disabled = false;

                

                toast('Network error. Please check your connection and try again.', 'error');

            }

        }



        function cancelFileRequest() {

            selectedFile = null;

            document.getElementById('msgInput').value = '';

            document.getElementById('msgInput').disabled = false;

            document.getElementById('reasonInput').value = '';

            document.getElementById('reasonBar').classList.remove('show');

        }



        function toggleFileDropdown() {

            const dd = document.getElementById('fileDropdown');

            const btn = document.getElementById('fileReqBtn');

            dd.classList.toggle('open');

            btn.classList.toggle('active');

            if (dd.classList.contains('open')) {

                setTimeout(() => {

                    document.addEventListener('click', closeDropdownOutside, {

                        once: true

                    });

                }, 50);

            }

        }



        function closeDropdownOutside(e) {

            const dd = document.getElementById('fileDropdown');

            const btn = document.getElementById('fileReqBtn');

            if (!dd.contains(e.target) && !btn.contains(e.target)) {

                dd.classList.remove('open');

                btn.classList.remove('active');

            }

        }



        function selectFile(id, title, fileType) {

            selectedFile = {

                id,

                title,

                file_type: fileType

            };

            document.getElementById('fileDropdown').classList.remove('open');

            document.getElementById('fileReqBtn').classList.remove('active');

            const msgInput = document.getElementById('msgInput');

            msgInput.value = `📄 Requesting: ${title}`;

            msgInput.disabled = true;

            document.getElementById('reasonBar').classList.add('show');

            document.getElementById('reasonInput').focus();

            document.getElementById('fileReqBtn').style.color = 'var(--forest)';

        }



        function esc(s) {

            if (s == null) return '';

            return String(s)

                .replace(/&/g, '&amp;').replace(/</g, '&lt;')

                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        }



        function formatDateLabel(d) {

            const today = new Date().toISOString().substring(0, 10);

            if (d === today) return 'Today';

            const yest = new Date(Date.now() - 86400000).toISOString().substring(0, 10);

            if (d === yest) return 'Yesterday';

            return new Date(d).toLocaleDateString('en-US', {

                month: 'short',

                day: 'numeric',

                year: 'numeric'

            });

        }



        function getFileIcon(ft) {

            if (!ft) return 'fa-file';

            if (ft.includes('pdf')) return 'fa-file-pdf';

            if (ft.includes('image')) return 'fa-file-image';

            if (ft.includes('word') || ft.includes('document')) return 'fa-file-word';

            if (ft.includes('sheet') || ft.includes('excel')) return 'fa-file-excel';

            return 'fa-file-alt';

        }



        function getFileIconClass(ft) {

            if (!ft) return 'other';

            if (ft.includes('pdf')) return 'pdf';

            if (ft.includes('image')) return 'img';

            if (ft.includes('word') || ft.includes('document')) return 'doc';

            if (ft.includes('sheet') || ft.includes('excel')) return 'xls';

            return 'other';

        }



        /* ════════ INIT ════════ */

        document.addEventListener('DOMContentLoaded', () => {

            document.getElementById('currentDate').textContent =

                new Date().toLocaleDateString('en-US', {

                    weekday: 'short',

                    year: 'numeric',

                    month: 'long',

                    day: 'numeric'

                });



            ensureAdminConv().then(() => {

                openAdminChat();

                loadClubChats();

                loadRestrictedFiles();

            });

        });



        /* ════════ ADMIN CHAT ════════ */

        async function ensureAdminConv() {

            try {

                const fd = new FormData();

                fd.append('action', 'get_student_conv');

                fd.append('csrf_token', getCSRFToken());

                

                const res = await fetch(API, {

                    method: 'POST',

                    body: fd

                });

                

                if (!res.ok) {

                    throw new Error(`HTTP error! status: ${res.status}`);

                }

                

                fd.append('csrf_token', getCSRFToken());

                

                const res = await fetch(CLUB_API, {

                    method: 'POST',

                    body: fd

                });

                

                if (!res.ok) {

                    throw new Error(`HTTP error! status: ${res.status}`);

                }

                

                const data = await res.json();

                if (!data.success || !data.clubs.length) return;



                const section = document.getElementById('clubChatsSection');

                section.innerHTML = `<div class="conv-section-label">Club Chats</div>` +

                    data.clubs.map(club => `

                    <div class="conv-item" id="club_${club.id}"

                         onclick="openClubChat(${club.id}, '${esc(club.name)}', ${club.member_count})">

                        <div class="conv-avatar club-av">${esc(club.name.charAt(0).toUpperCase())}</div>

                        <div class="conv-info">

                            <div class="conv-name">${esc(club.name)} <span class="club-tag">Club</span></div>

                            <div class="conv-preview">${esc(club.last_message || 'No messages yet')}</div>

                        </div>

                        <span class="conv-time">${esc(club.last_time || '')}</span>

                        ${club.unread > 0 ? `<span class="conv-unread">${club.unread}</span>` : ''}

                    </div>`).join('');

            } catch (error) {

                console.error('Load club chats error:', error);

            }

        }



        function openClubChat(clubId, clubName, memberCount) {

            activeMode = 'club';

            activeChatId = clubId;

            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));

            const el = document.getElementById('club_' + clubId);

            if (el) el.classList.add('active');

            document.getElementById('chatName').textContent = clubName;

            document.getElementById('chatAvatar').textContent = clubName.charAt(0).toUpperCase();

            document.getElementById('clubHeaderBadge').style.display = '';

            document.getElementById('clubMemberCount').textContent = memberCount + ' members';

            document.getElementById('fileReqBtn').style.display = 'none';

            cancelFileRequest();

            clearInterval(pollTimer);

            loadClubMessages(clubId);

            pollTimer = setInterval(() => loadClubMessages(clubId), 5000);

        }



        async function loadClubMessages(clubId) {

            try {

                const fd = new FormData();

                fd.append('action', 'fetch_club_messages');

                fd.append('club_id', clubId);

                fd.append('csrf_token', getCSRFToken());

                

                const res = await fetch(CLUB_API, {

                    method: 'POST',

                    body: fd

                });

                

                if (!res.ok) {

                    throw new Error(`HTTP error! status: ${res.status}`);

                }

                

                const data = await res.json();

                if (!data.success) return;

                renderMessages(data.messages, 'club');

            } catch (error) {

                console.error('Load club messages error:', error);

            }

        }



        /* ════════ RENDER MESSAGES ════════ */

        function renderMessages(msgs, mode) {

            const box = document.getElementById('chatMessages');

            const atBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 60;



            if (!msgs || !msgs.length) {

                box.innerHTML = `<div style="text-align:center;padding:48px;color:var(--muted);">

                    <i class="fas fa-comments" style="font-size:36px;opacity:.2;display:block;margin-bottom:10px;color:var(--forest);"></i>

                    No messages yet. Say hello!

                </div>`;

                return;

            }



            let lastDate = '';

            box.innerHTML = msgs.map(m => {

                const isMine = (mode === 'admin') ?

                    m.sender_role === 'student' :

                    m.sender_id == <?= (int)$student_id ?>;



                const init = isMine ? STUDENT_INIT : (m.avatar_letter || (mode === 'club' && m.sender_name ? m.sender_name.charAt(0).toUpperCase() : 'AD'));

                const msgDate = (m.created_at || '').substring(0, 10);

                let divider = '';

                if (msgDate && msgDate !== lastDate) {

                    lastDate = msgDate;

                    divider = `<div class="date-sep"><span>${formatDateLabel(msgDate)}</span></div>`;

                }



                if (m.message_type === 'file_request') return divider + buildFileReqBubble(m, isMine, init);



                return `${divider}

<div class="msg-row ${isMine ? 'sent' : ''}">

    <div class="msg-av">${esc(init)}</div>

    <div>

        ${mode === 'club' && !isMine ? `<div style="font-size:11px;color:var(--muted);margin-bottom:3px;padding-left:2px;">${esc(m.sender_name)}</div>` : ''}

        <div class="msg-bubble">

            <div class="msg-text">${esc(m.message)}</div>

        </div>

        <div class="msg-time"><i class="far fa-clock"></i>${esc(m.time_label)}</div>

    </div>

</div>`;

            }).join('');



            if (atBottom) box.scrollTop = box.scrollHeight;



            if (mode === 'admin' && msgs.length) {

                const last = msgs[msgs.length - 1];

                document.getElementById('adminPreview').textContent = (last.message || '').substring(0, 45);

            }

        }



        function buildFileReqBubble(m, isMine, init) {

            const statusMap = {

                pending: {

                    cls: 'pending',

                    icon: 'fa-clock',

                    label: 'Pending Approval'

                },

                approved: {

                    cls: 'approved',

                    icon: 'fa-check-circle',

                    label: 'Approved'

                },

                rejected: {

                    cls: 'rejected',

                    icon: 'fa-times-circle',

                    label: 'Rejected'

                },

            };

            const s = statusMap[m.request_status] || statusMap.pending;

            const fileTypeIcon = getFileIcon(m.file_type || '');



            const downloadBtn = (m.request_status === 'approved' && m.download_url) ? `

<a href="${esc(m.download_url)}" class="file-download-btn" target="_blank">

    <i class="fas fa-download"></i> Download File

</a>` : '';



            return `

<div class="msg-row ${isMine ? 'sent' : ''}">

    <div class="msg-av">${esc(init)}</div>

    <div>

        <div class="msg-bubble file-req-bubble">

            <div class="file-req-header">

                <div class="file-req-icon"><i class="fas ${fileTypeIcon}"></i></div>

                <span class="file-req-label">FILE REQUEST</span>

            </div>

            <div class="file-req-name">${esc(m.file_name)}</div>

            <div class="file-req-reason">${esc(m.message)}</div>

            <div><span class="req-status-pill ${s.cls}"><i class="fas ${s.icon}"></i>${s.label}</span></div>

            ${downloadBtn}

        </div>

        <div class="msg-time"><i class="far fa-clock"></i>${esc(m.time_label)}</div>

    </div>

</div>`;

        }



        async function loadAdminMessages() {

            try {

                if (!convId) return;

                const fd = new FormData();

                fd.append('action', 'fetch_messages');

                fd.append('conversation_id', convId);

                fd.append('csrf_token', getCSRFToken());

                

                const res = await fetch(API, {

                    method: 'POST',

                    body: fd

                });

                

                if (!res.ok) {

                    throw new Error(`HTTP error! status: ${res.status}`);

                }

                

                const data = await res.json();

                if (!data.success) return;

                renderMessages(data.messages, 'admin');

                markAdminRead();

            } catch (error) {

                console.error('Load admin messages error:', error);

            }

        }



        function markAdminRead() {

            try {

                if (!convId) return;

                const fd = new FormData();

                fd.append('action', 'mark_read');

                fd.append('conversation_id', convId);

                fd.append('csrf_token', getCSRFToken());

                

                fetch(API, {

                    method: 'POST',

                    body: fd

                }).catch(error => {

                    console.error('Mark admin read error:', error);

                });

            } catch (error) {

                console.error('Mark admin read error:', error);

            }

        }



        /* ════════ FILE DROPDOWN ════════ */

        async function loadRestrictedFiles() {

            try {

                const fd = new FormData();

                fd.append('action', 'get_restricted_files');

                fd.append('csrf_token', getCSRFToken());

                

                const res = await fetch(FILE_REQ_API, {

                    method: 'POST',

                    body: fd

                });

                

                if (!res.ok) {

                    throw new Error(`HTTP error! status: ${res.status}`);

                }

                

                const data = await res.json();

                const body = document.getElementById('fileDropdownBody');

                if (!data.success || !data.files.length) {

                    body.innerHTML = `<div class="file-dropdown-empty"><i class="fas fa-folder-open"></i>No restricted files available.</div>`;

                    return;

                }

                body.innerHTML = data.files.map(f => `

                <div class="file-dropdown-item" onclick="selectFile(${f.id}, '${esc(f.title)}', '${esc(f.file_type)}')">

                    <div class="file-dropdown-item-icon ${getFileIconClass(f.file_type)}">

                        <i class="fas ${getFileIcon(f.file_type)}"></i>

                    </div>

                    <div>

                        <div class="file-dropdown-item-name">${esc(f.title)}</div>

                        <div class="file-dropdown-item-cat"><i class="fas fa-tag"></i>${esc(f.category)}</div>

                    </div>

                </div>`).join('');

            } catch (error) {

                console.error('Load restricted files error:', error);

            }

        }



        function toggleFileDropdown() {

            const dd = document.getElementById('fileDropdown');

            const btn = document.getElementById('fileReqBtn');

            dd.classList.toggle('open');

            btn.classList.toggle('active');

            if (dd.classList.contains('open')) {

                setTimeout(() => {

                    document.addEventListener('click', closeDropdownOutside, {

                        once: true

                    });

                }, 50);

            }

        }



        function closeDropdownOutside(e) {

            const dd = document.getElementById('fileDropdown');

            const btn = document.getElementById('fileReqBtn');

            if (!dd.contains(e.target) && !btn.contains(e.target)) {

                dd.classList.remove('open');

                btn.classList.remove('active');

            }

        }



        function selectFile(id, title, fileType) {

            selectedFile = {

                id,

                title,

                file_type: fileType

            };

            document.getElementById('fileDropdown').classList.remove('open');

            document.getElementById('fileReqBtn').classList.remove('active');

            const msgInput = document.getElementById('msgInput');

            msgInput.value = `📄 Requesting: ${title}`;

            msgInput.disabled = true;

            document.getElementById('reasonBar').classList.add('show');

            document.getElementById('reasonInput').focus();

            document.getElementById('fileReqBtn').style.color = 'var(--forest)';

        }



        function cancelFileRequest() {

            selectedFile = null;

            document.getElementById('msgInput').value = '';

            document.getElementById('msgInput').disabled = false;

            document.getElementById('reasonInput').value = '';

            document.getElementById('reasonBar').classList.remove('show');

        }



        /* ════════ SEARCH FILTER ════════ */

        function filterConvs(q) {

            q = q.toLowerCase();

            document.querySelectorAll('.conv-item').forEach(el => {

                const name = el.querySelector('.conv-name')?.textContent?.toLowerCase() || '';

                el.style.display = (!q || name.includes(q)) ? '' : 'none';

            });

        }



        /* ════════ HELPERS ════════ */

        function esc(s) {

            if (s == null) return '';

            return String(s)

                .replace(/&/g, '&amp;').replace(/</g, '&lt;')

                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        }



        function formatDateLabel(d) {

            const today = new Date().toISOString().substring(0, 10);

            if (d === today) return 'Today';

            const yest = new Date(Date.now() - 86400000).toISOString().substring(0, 10);

            if (d === yest) return 'Yesterday';

            return new Date(d).toLocaleDateString('en-US', {

                month: 'short',

                day: 'numeric',

                year: 'numeric'

            });

        }



        function getFileIcon(ft) {

            if (!ft) return 'fa-file';

            if (ft.includes('pdf')) return 'fa-file-pdf';

            if (ft.includes('image')) return 'fa-file-image';

            if (ft.includes('word') || ft.includes('document')) return 'fa-file-word';

            if (ft.includes('sheet') || ft.includes('excel')) return 'fa-file-excel';

            return 'fa-file-alt';

        }



        function getFileIconClass(ft) {

            if (!ft) return 'other';

            if (ft.includes('pdf')) return 'pdf';

            if (ft.includes('image')) return 'img';

            if (ft.includes('word') || ft.includes('document')) return 'doc';

            if (ft.includes('sheet') || ft.includes('excel')) return 'xls';

            return 'other';

        }



        function getCSRFToken() {

            // Get CSRF token from meta tag

            const metaTag = document.querySelector('meta[name="csrf-token"]');

            if (metaTag) {

                return metaTag.getAttribute('content');

            }

            

            console.warn('CSRF token not found in meta tag');

            return '';

        }



        function toast(msg, type = 'success') {

            const zone = document.getElementById('toastZone');

            const t = document.createElement('div');

            t.className = `toast ${type}`;

            t.innerHTML = `<i class="fas ${type==='success'?'fa-check-circle':'fa-exclamation-circle'}"></i><span>${msg}</span>`;

            zone.appendChild(t);

            requestAnimationFrame(() => t.classList.add('show'));

            setTimeout(() => {

                t.classList.remove('show');

                setTimeout(() => t.remove(), 320);

            }, 3500);

        }

    </script>



    <!-- ══ STUDENT NAV LOADER ══ -->

    <script>

        document.addEventListener('DOMContentLoaded', function() {

            var placeholder = document.getElementById('nav-placeholder');

            if (!placeholder) return;

            var pageDir = window.location.pathname.replace(/\/[^\/]*$/, '/');



            fetch('Student_nav.php')

                .then(function(res) {

                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    return res.text();

                })

                .then(function(html) {

                    var tmp = document.createElement('div');

                    tmp.innerHTML = html;

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

                    tmp.querySelectorAll('img[src]').forEach(function(img) {

                        var src = img.getAttribute('src');

                        if (src && !src.startsWith('/') && !src.startsWith('http')) img.setAttribute('src', pageDir + src);

                    });

                    tmp.querySelectorAll('style').forEach(function(styleEl) {

                        document.head.appendChild(styleEl.cloneNode(true));

                        styleEl.remove();

                    });

                    while (tmp.firstChild) placeholder.parentNode.insertBefore(tmp.firstChild, placeholder);

                    placeholder.remove();

                    tmp.querySelectorAll('script').forEach(function(oldScript) {

                        var newScript = document.createElement('script');

                        newScript.textContent = oldScript.textContent;

                        document.body.appendChild(newScript);

                    });

                    var nameEl = document.getElementById('navStudentName');

                    var gradeEl = document.getElementById('navGradeLevel');

                    if (nameEl && document.body.dataset.studentName) nameEl.textContent = document.body.dataset.studentName;

                    if (gradeEl && document.body.dataset.gradeLevel) gradeEl.textContent = document.body.dataset.gradeLevel;

                    (function waitForStudentNav(attempts) {

                        if (window.StudentNav && typeof window.StudentNav.bootProfileFromBody === 'function') {

                            window.StudentNav.bootProfileFromBody();

                        } else if (attempts > 0) {

                            setTimeout(function() {

                                waitForStudentNav(attempts - 1);

                            }, 60);

                        }

                    })(20);

                    var current = window.location.pathname.split('/').pop() || 'chatbox.php';

                    document.querySelectorAll('.sidebar .menu-item').forEach(function(item) {

                        var href = (item.getAttribute('href') || '').split('/').pop();

                        item.classList.toggle('active', href === current);

                    });

                })

                .catch(function(err) {

                    console.error('[NavLoader] Failed:', err);

                });

        });

    </script>



</body>



</html>