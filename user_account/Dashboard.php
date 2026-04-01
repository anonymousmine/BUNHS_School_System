<?php

/**
 * Student Dashboard - Redesigned
 * Buyoan National High School
 * Bento Box Layout with GPA, Events, Gamification, Schedule & more
 */

// ── SESSION: must start before session_config.php or any $_SESSION read ──────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../session_config.php';
include '../db_connection.php';

// ── Load APCu cache helper (walks up to project root automatically) ───────────
$_ch_path = null;
foreach ([__DIR__ . '/../cache_helper.php', __DIR__ . '/cache_helper.php'] as $_p) {
    if (file_exists($_p)) {
        $_ch_path = $_p;
        break;
    }
}
if ($_ch_path) {
    require_once $_ch_path;
} else {
    // Graceful no-op stubs so the rest of the file works without APCu
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

// Student functionality has been removed
header('Location: ../index.php');
exit;

$student_id              = $_SESSION['student_id'];
$student_name            = $_SESSION['student_name'] ?? 'Student';
$grade_level             = $_SESSION['grade_level']  ?? 'Grade 10';
$notification_preference = null;

// ── "Join us" from index.php passes ?show_notif=1 ────────────────────────────
if (isset($_GET['show_notif']) && $_GET['show_notif'] === '1') {
    unset($_SESSION['notif_dismissed']);
}

// ── FETCH FULL STUDENT PROFILE (with APCu caching) ───────────────────────────
// Cache key: student:profile:{id}   TTL: 120 seconds
// Invalidate anywhere the student updates their profile or verification status.
$db_student  = [];
$profile_key = "student:profile:{$student_id}";
$cached_profile = cache_get($profile_key);

if ($cached_profile !== false) {
    // CACHE HIT — restore profile from memory, no DB query needed
    $db_student              = $cached_profile;
    $student_name            = trim($cached_profile['first_name'] . ' ' . $cached_profile['last_name']);
    $grade_level             = $cached_profile['grade_level'];
    $notification_preference = $cached_profile['notification_preference'] ?? null;
} else {
    // CACHE MISS — query DB then store result
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
                $db_student              = $row;
                $student_name            = trim($row['first_name'] . ' ' . $row['last_name']);
                $grade_level             = $row['grade_level'];
                $notification_preference = $row['notification_preference'];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Fallback: minimal query for older schema
        try {
            $stmt = $conn->prepare("SELECT first_name, last_name, grade_level, notification_preference FROM students WHERE student_id = ?");
            if ($stmt) {
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $db_student              = $row;
                    $student_name            = trim($row['first_name'] . ' ' . $row['last_name']);
                    $grade_level             = $row['grade_level'];
                    $notification_preference = $row['notification_preference'];
                }
                $stmt->close();
            }
        } catch (Exception $e2) {
            error_log("DB error: " . $e2->getMessage());
        }
    }

    // Store in cache only when we got a valid row
    if (!empty($db_student)) {
        cache_set($profile_key, $db_student, CACHE_TTL_NOTIF_PREF);
    }
}

// ── DETERMINE PROFILE DISPLAY MODE ───────────────────────────────────────────
$login_method   = isset($db_student['login_method']) ? $db_student['login_method'] : null;
$phone_verified = !empty($db_student['phone_verified']);
$email_verified = !empty($db_student['email_verified']);
$profile_photo  = !empty($db_student['photo'])  ? $db_student['photo']  : null;
$student_email  = !empty($db_student['email'])  ? $db_student['email']  : null;
$student_phone  = !empty($db_student['phone'])  ? $db_student['phone']  : null;

if (!$login_method && !empty($_SESSION['login_method'])) {
    $login_method = $_SESSION['login_method'];
}
if (!$student_email && !empty($_SESSION['student_email'])) {
    $student_email = $_SESSION['student_email'];
}
if (!$student_phone && !empty($_SESSION['student_phone'])) {
    $student_phone = $_SESSION['student_phone'];
}

// Resolve display mode
if ($login_method === 'email' || ($email_verified && $student_email)) {
    $profile_display_mode = 'email';
} elseif ($login_method === 'phone' || ($phone_verified && $student_phone)) {
    $profile_display_mode = 'phone';
} elseif (!empty($_SESSION['notif_dismissed'])) {
    $profile_display_mode = 'skip';
} else {
    $profile_display_mode = 'none';
}

$user_logged_in = true;
$user_verified  = ($email_verified || $phone_verified || !empty($_SESSION['dash_email_verified']));

// ── PROFILE PICTURE RESOLUTION ───────────────────────────────────────────────
$nav_profile_img  = 'assets/img/person/unknown.jpg';
$nav_profile_type = 'img';

if ($profile_display_mode === 'email') {
    if ($profile_photo) {
        $nav_profile_img  = htmlspecialchars($profile_photo, ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    } elseif (!empty($_SESSION['google_avatar'])) {
        $nav_profile_img  = htmlspecialchars($_SESSION['google_avatar'], ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    } else {
        $nav_profile_type = 'icon';
    }
} else {
    $nav_profile_type = 'icon';
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

// ── NOTIFICATION PREFERENCE ───────────────────────────────────────────────────
$has_notification_preference = isset($notification_preference)
    && $notification_preference !== 'none'
    && $notification_preference !== null;

$show_notif_modal = !$has_notification_preference
    && empty($_SESSION['notif_dismissed']);

if (isset($_GET['show_notif']) && $_GET['show_notif'] === '1') {
    $show_notif_modal = true;
}

// ── DYNAMIC DASHBOARD DATA ────────────────────────────────────────────────────
$events_joined   = 0;
$has_events      = false;
$upcoming_events = [];

if ($user_logged_in) {
    // Events joined count
    try {
        $res = $conn->query("SHOW TABLES LIKE 'event_participants'");
        if ($res && $res->num_rows > 0) {
            $es = $conn->prepare("SELECT COUNT(*) as cnt FROM event_participants WHERE student_id = ?");
            if ($es) {
                $es->bind_param("s", $student_id);
                $es->execute();
                $er = $es->get_result()->fetch_assoc();
                $events_joined = (int)($er['cnt'] ?? 0);
                $has_events    = ($events_joined > 0);
                $es->close();
            }
        }
    } catch (Exception $e) { /* table may not exist yet */
    }





    // Upcoming events (up to 3)
    try {
        $res = $conn->query("SHOW TABLES LIKE 'events'");
        if ($res && $res->num_rows > 0) {
            $ues = $conn->query(
                "SELECT event_name, event_date FROM events
                 WHERE event_date >= CURDATE()
                 ORDER BY event_date ASC LIMIT 3"
            );
            if ($ues) {
                $upcoming_events = $ues->fetch_all(MYSQLI_ASSOC);
            }
        }
    } catch (Exception $e) { /* table may not exist yet */
    }
}

$first_name = htmlspecialchars(explode(' ', $student_name)[0]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Dashboard — BUNHS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

    <style>
        /* ═══════════════════════════════════════════════════════
           BUNHS DESIGN SYSTEM — matches modals.php / index.php
           Forest-green academic portal  |  DM Sans + Playfair
           ═══════════════════════════════════════════════════════ */
        :root {
            --bunhs-forest: #1a3a2a;
            --bunhs-green: #2d6a4f;
            --bunhs-mint: #52b788;
            --bunhs-sage: #b7e4c7;
            --bunhs-lime: #a8d5a2;
            --bunhs-cream: #f8f5f0;
            --bunhs-warm: #fdf6ec;
            --bunhs-gold: #c9a84c;
            --bunhs-gold-lt: #f0d98a;
            --bunhs-ink: #1e2d24;
            --bunhs-muted: #6b7c72;
            --bunhs-border: #dde8e2;

            --primary-color: #2d6a4f;
            --primary-dark: #1a3a2a;
            --primary-light: #52b788;
            --secondary-color: #52b788;
            --danger-color: #e53935;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --text-primary: #1e2d24;
            --text-secondary: #6b7c72;
            --border-color: #dde8e2;
            --light-color: #f8f5f0;
            --bg-color: #f2f5f2;
            --white: #ffffff;
            --shadow: 0 2px 12px rgba(26, 58, 42, .08), 0 1px 4px rgba(26, 58, 42, .05);
            --shadow-hover: 0 8px 28px rgba(26, 58, 42, .14), 0 2px 8px rgba(26, 58, 42, .08);
            --radius: 16px;
            --radius-sm: 10px;
            --sidebar-w: 280px;
            --font-body: 'DM Sans', 'Segoe UI', sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            font-size: 14px;
        }

        /* sidebar loaded from Student_nav.php */

        /* ─── MAIN ─────────────────────────────────────────── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ─── TOPBAR ── BUNHS premium style ──────────────── */
        .topbar {
            background: var(--white);
            border-bottom: 2px solid var(--bunhs-border);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 8px rgba(26, 58, 42, .06);
        }

        .topbar-left .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: var(--bunhs-muted);
            margin-bottom: 3px;
            letter-spacing: .02em;
        }

        .topbar-left .breadcrumb i {
            font-size: 9px;
            color: var(--bunhs-mint);
        }

        .topbar-left h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--bunhs-forest);
            letter-spacing: -.01em;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1.5px solid var(--bunhs-border);
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 12.5px;
            color: var(--bunhs-muted);
            font-weight: 500;
            background: var(--bunhs-cream);
        }

        .date-chip i {
            color: var(--bunhs-mint);
            font-size: 12px;
        }

        .notif-btn {
            width: 38px;
            height: 38px;
            background: var(--white);
            border: 1.5px solid var(--bunhs-border);
            border-radius: 8px;
            cursor: pointer;
            display: grid;
            place-items: center;
            color: var(--bunhs-muted);
            position: relative;
            font-size: 14px;
            transition: all .2s;
        }

        .notif-btn:hover {
            border-color: var(--bunhs-mint);
            color: var(--bunhs-green);
            background: rgba(82, 183, 136, .06);
        }

        .notif-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 7px;
            height: 7px;
            background: var(--danger-color);
            border-radius: 50%;
            border: 2px solid var(--white);
        }

        /* ─── PAGE BODY ──────────────────────────────────── */
        .page-body {
            padding: 24px 28px 48px;
            flex: 1;
        }

        /* ─── BENTO GRID ─────────────────────────────────── */
        .bento {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            grid-auto-rows: minmax(80px, auto);
            gap: 20px;
        }

        /* ─── BASE CARD ── BUNHS premium style ───────────── */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--bunhs-border);
            overflow: hidden;
            transition: box-shadow .25s, transform .25s;
        }

        .card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
        }

        .card-inner {
            padding: 20px 22px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--bunhs-border);
        }

        .card-header h3 {
            font-family: 'DM Sans', sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            color: var(--bunhs-ink);
        }

        .card-header .view-all {
            font-size: 12px;
            color: var(--bunhs-mint);
            text-decoration: none;
            font-weight: 600;
        }

        .card-header .view-all:hover {
            color: var(--bunhs-green);
            text-decoration: underline;
        }

        .card-label {
            font-size: 10.5px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--bunhs-muted);
            margin-bottom: 4px;
        }

        .card-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--bunhs-ink);
            margin-bottom: 14px;
        }

        /* ─── STAT CARDS ── BUNHS style ──────────────────── */
        .stat-card {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--bunhs-border);
            cursor: pointer;
            transition: all .25s;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-3px);
            border-color: var(--bunhs-mint);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 20px;
            flex-shrink: 0;
            transition: transform .2s;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }

        .stat-icon.blue {
            background: #eff6ff;
            color: #3b82f6;
        }

        .stat-icon.green {
            background: #f0fdf4;
            color: #10b981;
        }

        .stat-icon.purple {
            background: #faf5ff;
            color: #8b5cf6;
        }

        .stat-icon.orange {
            background: #fffbeb;
            color: #f59e0b;
        }

        .stat-icon.teal {
            background: #f0fdfa;
            color: #14b8a6;
        }

        .stat-content .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .stat-content .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-content .stat-change {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 500;
            margin-top: 3px;
        }

        .stat-change.positive {
            color: #10b981;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        .stat-change.neutral {
            color: var(--text-secondary);
        }

        /* ─── HERO CARD ── BUNHS forest gradient ─────────── */
        .c-hero {
            grid-column: span 8;
            grid-row: span 2;
            background: linear-gradient(135deg, var(--bunhs-forest) 0%, #2d6a4f 50%, #1a4d38 100%);
            position: relative;
            overflow: hidden;
            border: none;
        }

        .c-hero .card-inner {
            justify-content: flex-end;
            padding: 28px 32px;
        }

        .hero-deco {
            position: absolute;
            top: -60px;
            right: -60px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, .1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-deco2 {
            position: absolute;
            top: 30px;
            right: 90px;
            width: 160px;
            height: 160px;
            background: radial-gradient(circle, rgba(255, 255, 255, .06) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-grid-bg {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(255, 255, 255, .03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, .03) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(201, 168, 76, .18);
            border: 1px solid rgba(201, 168, 76, .35);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 10.5px;
            color: var(--bunhs-gold-lt);
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 10px;
            width: fit-content;
        }

        .c-hero h2 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 30px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .c-hero p {
            font-size: 13px;
            color: rgba(255, 255, 255, .6);
            max-width: 420px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .hero-stats {
            display: flex;
            gap: 24px;
        }

        .hero-stat .val {
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }

        .hero-stat .lbl {
            font-size: 11px;
            color: rgba(255, 255, 255, .5);
            margin-top: 3px;
        }

        .hero-divider {
            width: 1px;
            background: rgba(255, 255, 255, .15);
            align-self: stretch;
        }

        /* ─── GPA CARD ─────────────────────────────────── */
        /* ─── EVENT COUNT CARD ───────────────────────────── */
        .c-event-count {
            grid-column: span 4;
            grid-row: span 2;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .c-event-count .card-inner {
            justify-content: space-between;
        }

        .ev-deco {
            position: absolute;
            bottom: -30px;
            right: -30px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
        }

        .ev-deco2 {
            position: absolute;
            top: -20px;
            right: 55px;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .05);
        }

        .ev-count-num {
            font-size: 56px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
            margin: 6px 0 3px;
        }

        .ev-label {
            font-size: 13px;
            color: rgba(255, 255, 255, .8);
            font-weight: 600;
        }

        .ev-sub {
            font-size: 11px;
            color: rgba(255, 255, 255, .5);
            margin-top: 2px;
        }

        .c-event-count .card-label {
            color: rgba(255, 255, 255, .55);
        }

        .ev-next {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, .12);
            border-radius: 8px;
            padding: 8px 12px;
        }

        .ev-next i {
            color: #fff;
            font-size: 12px;
        }

        .ev-next span {
            font-size: 12px;
            color: rgba(255, 255, 255, .8);
            font-weight: 500;
        }



        /* ─── GRADE TRENDS CHART ─────────────────────────── */
        /* ─── SCHEDULE ───────────────────────────────────── */
        .c-schedule {
            grid-column: span 4;
            grid-row: span 3;
        }

        .schedule-day-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
        }

        .day-tab {
            padding: 5px 11px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid var(--bunhs-border);
            background: var(--white);
            color: var(--bunhs-muted);
            transition: all .2s;
        }

        .day-tab.active {
            background: var(--bunhs-forest);
            color: #fff;
            border-color: var(--bunhs-forest);
            box-shadow: 0 2px 8px rgba(26, 58, 42, .2);
        }

        .day-tab:hover:not(.active) {
            border-color: var(--bunhs-mint);
            color: var(--bunhs-green);
            background: rgba(82, 183, 136, .05);
        }

        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            overflow-y: auto;
            padding-right: 2px;
        }

        .schedule-list::-webkit-scrollbar {
            width: 3px;
        }

        .schedule-list::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        .sch-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 9px 11px;
            border-radius: 8px;
            background: var(--light-color);
            border-left: 3px solid transparent;
            transition: all .2s;
        }

        .sch-item.now {
            background: #f0fdf4;
            border-left-color: #10b981;
        }

        .sch-item.done {
            opacity: .45;
        }

        .sch-time {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 48px;
            padding-top: 1px;
        }

        .sch-info strong {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            display: block;
        }

        .sch-info span {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .sch-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 5px;
        }



        /* ─── GOALS / TODO ───────────────────────────────── */
        .c-goals {
            grid-column: span 4;
            grid-row: span 3;
        }

        .goal-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid var(--fog);
        }

        .goal-item:last-child {
            border-bottom: none;
        }

        .goal-check {
            width: 18px;
            height: 18px;
            border-radius: 5px;
            border: 2px solid var(--fog);
            display: grid;
            place-items: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all .2s;
            margin-top: 1px;
            font-size: 10px;
            color: transparent;
        }

        .goal-check.done {
            background: var(--leaf);
            border-color: var(--leaf);
            color: #fff;
        }

        .goal-text {
            flex: 1;
        }

        .goal-text strong {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            display: block;
            line-height: 1.3;
        }

        .goal-text strong.striked {
            text-decoration: line-through;
            opacity: .45;
        }

        .goal-text span {
            font-size: 11px;
            color: #a0ad96;
        }

        .goal-tag {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
            background: var(--fog);
            color: #7a8a72;
        }

        .add-goal-row {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .add-goal-row input {
            flex: 1;
            padding: 9px 12px;
            border-radius: 9px;
            border: 1.5px solid var(--fog);
            font-family: var(--font-body);
            font-size: 12.5px;
            color: var(--ink);
            outline: none;
            transition: border .2s;
            background: var(--mist);
        }

        .add-goal-row input:focus {
            border-color: var(--leaf);
        }

        .add-goal-row button {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            border: none;
            background: var(--forest);
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            transition: background .2s;
            flex-shrink: 0;
        }

        .add-goal-row button:hover {
            background: var(--leaf);
        }

        /* ─── EVENTS JOINED COUNTER ──────────────────────── */
        .c-event-count {
            grid-column: span 4;
            grid-row: span 2;
            background: linear-gradient(135deg, var(--bunhs-forest) 0%, var(--bunhs-green) 100%);
            position: relative;
            overflow: hidden;
        }

        .c-event-count .card-inner {
            justify-content: space-between;
        }

        .ev-deco {
            position: absolute;
            bottom: -30px;
            right: -30px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07);
        }

        .ev-deco2 {
            position: absolute;
            top: -20px;
            right: 60px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .05);
        }

        .ev-count-num {
            font-family: var(--font-head);
            font-size: 64px;
            font-weight: 800;
            color: var(--lime);
            line-height: 1;
            margin: 8px 0 4px;
        }

        .ev-label {
            font-size: 13px;
            color: rgba(255, 255, 255, .65);
        }

        .ev-sub {
            font-size: 11px;
            color: rgba(255, 255, 255, .4);
            margin-top: 2px;
        }

        .c-event-count .card-label {
            color: rgba(255, 255, 255, .45);
        }

        .ev-next {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, .1);
            border-radius: 10px;
            padding: 9px 12px;
        }

        .ev-next i {
            color: var(--lime);
            font-size: 13px;
        }

        .ev-next span {
            font-size: 12px;
            color: rgba(255, 255, 255, .75);
            font-weight: 500;
        }

        .feedback-chip {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .fb-good {
            background: #d1fae5;
            color: #065f46;
        }

        .fb-avg {
            background: #fef3c7;
            color: #92400e;
        }

        /* ─── GOALS / TODO ───────────────────────────────── */
        .c-goals {
            grid-column: span 4;
            grid-row: span 3;
        }

        .goal-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .goal-item:last-child {
            border-bottom: none;
        }

        .goal-check {
            width: 17px;
            height: 17px;
            border-radius: 4px;
            border: 2px solid var(--bunhs-border);
            display: grid;
            place-items: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all .2s;
            margin-top: 1px;
            font-size: 9px;
            color: transparent;
        }

        .goal-check.done {
            background: var(--bunhs-green);
            border-color: var(--bunhs-green);
            color: #fff;
        }

        .goal-text {
            flex: 1;
        }

        .goal-text strong {
            font-size: 13px;
            font-weight: 500;
            color: var(--bunhs-ink);
            display: block;
            line-height: 1.3;
        }

        .goal-text strong.striked {
            text-decoration: line-through;
            opacity: .45;
        }

        .goal-text span {
            font-size: 11px;
            color: var(--bunhs-muted);
        }

        .goal-tag {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            background: rgba(82, 183, 136, .1);
            color: var(--bunhs-green);
            border: 1px solid rgba(82, 183, 136, .2);
        }

        .add-goal-row {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .add-goal-row input {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1.5px solid var(--bunhs-border);
            font-family: 'DM Sans', sans-serif;
            font-size: 12.5px;
            color: var(--bunhs-ink);
            outline: none;
            transition: border .2s, box-shadow .2s;
            background: var(--bunhs-cream);
        }

        .add-goal-row input:focus {
            border-color: var(--bunhs-mint);
            box-shadow: 0 0 0 3px rgba(82, 183, 136, .12);
            background: #fff;
        }

        .add-goal-row button {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: none;
            background: var(--bunhs-forest);
            color: #fff;
            cursor: pointer;
            font-size: 13px;
            transition: background .2s, transform .15s;
            flex-shrink: 0;
        }

        .add-goal-row button:hover {
            background: var(--bunhs-green);
            transform: scale(1.05);
        }

        /* ─── SCROLLBAR ──────────────────────────────────── */
        ::-webkit-scrollbar {
            width: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        /* ─── TOAST ── BUNHS style ───────────────────────── */
        #toastContainer {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 9999;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bunhs-forest);
            color: #fff;
            padding: 12px 18px;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(26, 58, 42, .25);
            animation: slideInRight .3s ease;
            border-left: 3px solid var(--bunhs-mint);
        }

        .toast.success {
            background: var(--bunhs-green);
            border-left-color: var(--bunhs-gold-lt);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: none;
                opacity: 1;
            }
        }

        /* ─── RESPONSIVE ─────────────────────────────────── */
        @media (max-width: 1200px) {
            .c-hero {
                grid-column: span 12;
            }

            .c-events {
                grid-column: span 6;
            }

            .c-schedule {
                grid-column: span 6;
            }

            .c-grades {
                grid-column: span 6;
            }

            .c-goals {
                grid-column: span 6;
            }

            .c-event-count {
                grid-column: span 6;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .bento>.card {
                grid-column: span 12 !important;
            }
        }

        /* ── Dashboard empty-state: new / unverified account ── */
        .dash-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 320px;
            text-align: center;
            color: var(--bunhs-muted);
            gap: 12px;
            padding: 48px 24px;
        }

        .dash-empty-state i {
            font-size: 48px;
            opacity: .22;
            color: var(--bunhs-green);
        }

        .dash-empty-state h3 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--bunhs-ink);
            margin: 0;
        }

        .dash-empty-state p {
            font-size: 13px;
            max-width: 340px;
            line-height: 1.6;
            color: var(--bunhs-muted);
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

    <!-- ── MAIN ── -->
    <main class="main">

        <!-- Topbar — matches admin page header -->
        <div class="topbar">
            <div class="topbar-left">
                <p class="breadcrumb">
                    <span>Home</span>
                    <i class="fas fa-chevron-right"></i>
                    <span>Dashboard</span>
                </p>
                <h1>Dashboard</h1>
            </div>
            <div class="topbar-right">
                <div class="date-chip">
                    <i class="fas fa-calendar-day"></i>
                    <span id="currentDate"></span>
                </div>
                <!-- Bell triggers the custom notification modal (NOT Bootstrap) -->
                <button class="notif-btn" id="notifBellBtn"
                    onclick="if(typeof openNotifModal==='function') openNotifModal();"
                    title="Notification Preferences"
                    aria-label="Open notification preferences">
                    <i class="fas fa-bell"></i>
                    <!-- Red dot shown while no verified preference is saved -->
                    <div class="notif-dot" id="notifDot"
                        style="<?php echo $has_notification_preference ? 'display:none' : ''; ?>"></div>
                </button>
            </div>
        </div>

        <!-- ── PAGE BODY ── -->
        <div class="page-body">

            <!-- ── BENTO GRID ── -->
            <?php if ($user_logged_in): ?>
                <div class="bento">

                    <!-- 1. HERO WELCOME (8 cols × 2 rows) -->
                    <div class="card c-hero">
                        <div class="hero-grid-bg"></div>
                        <div class="hero-deco"></div>
                        <div class="hero-deco2"></div>
                        <div class="card-inner">
                            <div class="hero-tag"><i class="fas fa-circle" style="color:var(--bunhs-mint);font-size:7px;"></i> Active Semester</div>
                            <h2>Hello, <?php echo $first_name; ?>! 👋</h2>
                            <p>Welcome back to your student portal. Keep track of your grades, events, and daily schedule — all in one place.</p>
                            <?php if ($user_logged_in): ?>
                                <div class="hero-stats">
                                    <div class="hero-stat">
                                        <div class="val" id="heroLoginDays">—</div>
                                        <div class="lbl">Days Active</div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="color:rgba(255,255,255,.45);font-size:13px;margin-top:8px;">
                                    <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                                    Log in to see your activity stats.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- DAILY SCHEDULE (8 cols × 3 rows) -->
                    <div class="card c-schedule" style="grid-column: span 8;">
                        <div class="card-inner">
                            <div class="card-header">
                                <h3>Daily Schedule</h3>
                                <span style="font-size:11px;color:var(--text-secondary);font-weight:500;" id="todayLabel"></span>
                            </div>
                            <div class="schedule-day-tabs">
                                <button class="day-tab active">Mon</button>
                                <button class="day-tab">Tue</button>
                                <button class="day-tab">Wed</button>
                                <button class="day-tab">Thu</button>
                                <button class="day-tab">Fri</button>
                            </div>
                            <div class="schedule-list" id="scheduleList">
                                <div class="sch-item done">
                                    <span class="sch-time">7:30</span>
                                    <div class="sch-dot" style="background:#a0ad96"></div>
                                    <div class="sch-info"><strong>Flag Ceremony</strong><span>Quadrangle</span></div>
                                </div>
                                <div class="sch-item done">
                                    <span class="sch-time">8:00</span>
                                    <div class="sch-dot" style="background:#a0ad96"></div>
                                    <div class="sch-info"><strong>Filipino</strong><span>Room 104 · Mrs. Santos</span></div>
                                </div>
                                <div class="sch-item now">
                                    <span class="sch-time">9:00</span>
                                    <div class="sch-dot" style="background:var(--leaf)"></div>
                                    <div class="sch-info"><strong>Mathematics</strong><span>Room 201 · Mr. Cruz</span></div>
                                </div>
                                <div class="sch-item">
                                    <span class="sch-time">10:00</span>
                                    <div class="sch-dot" style="background:var(--sky)"></div>
                                    <div class="sch-info"><strong>Science</strong><span>Lab 3 · Ms. Reyes</span></div>
                                </div>
                                <div class="sch-item">
                                    <span class="sch-time">11:00</span>
                                    <div class="sch-dot" style="background:var(--amber)"></div>
                                    <div class="sch-info"><strong>English</strong><span>Room 104 · Mrs. Lim</span></div>
                                </div>
                                <div class="sch-item">
                                    <span class="sch-time">12:00</span>
                                    <div class="sch-dot" style="background:#d4ddc8"></div>
                                    <div class="sch-info"><strong>Lunch Break</strong><span>Canteen</span></div>
                                </div>
                                <div class="sch-item">
                                    <span class="sch-time">1:00</span>
                                    <div class="sch-dot" style="background:var(--rose)"></div>
                                    <div class="sch-info"><strong>Araling Panlipunan</strong><span>Room 303 · Mr. Dela Cruz</span></div>
                                </div>
                                <div class="sch-item">
                                    <span class="sch-time">2:00</span>
                                    <div class="sch-dot" style="background:#a0ad96"></div>
                                    <div class="sch-info"><strong>MAPEH</strong><span>Gym · Coach Bautista</span></div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- 8. STUDY GOALS / TODO (4 cols × 3 rows) -->
                    <div class="card c-goals">
                        <div class="card-inner">
                            <div class="card-header">
                                <h3>Study Goals</h3>
                                <a href="#" class="view-all">+ Add</a>
                            </div>
                            <div style="flex:1;overflow-y:auto;" id="goalsList">
                                <div class="goal-item">
                                    <div class="goal-check done" onclick="toggleGoal(this)"><i class="fas fa-check"></i></div>
                                    <div class="goal-text">
                                        <strong class="striked">Review Chapter 7 — Algebra</strong>
                                        <span>Math · Due today</span>
                                    </div>
                                    <span class="goal-tag">Math</span>
                                </div>
                                <div class="goal-item">
                                    <div class="goal-check" onclick="toggleGoal(this)"><i class="fas fa-check"></i></div>
                                    <div class="goal-text">
                                        <strong>Finish Science Lab Report</strong>
                                        <span>Science · Due Dec 12</span>
                                    </div>
                                    <span class="goal-tag">Science</span>
                                </div>
                                <div class="goal-item">
                                    <div class="goal-check done" onclick="toggleGoal(this)"><i class="fas fa-check"></i></div>
                                    <div class="goal-text">
                                        <strong class="striked">Submit English Essay Draft</strong>
                                        <span>English · Submitted</span>
                                    </div>
                                    <span class="goal-tag">English</span>
                                </div>
                                <div class="goal-item">
                                    <div class="goal-check" onclick="toggleGoal(this)"><i class="fas fa-check"></i></div>
                                    <div class="goal-text">
                                        <strong>Study for AP Long Test</strong>
                                        <span>AP · Due Dec 15</span>
                                    </div>
                                    <span class="goal-tag">AP</span>
                                </div>
                                <div class="goal-item">
                                    <div class="goal-check" onclick="toggleGoal(this)"><i class="fas fa-check"></i></div>
                                    <div class="goal-text">
                                        <strong>Practice oral recitation (Filipino)</strong>
                                        <span>Filipino · Ongoing</span>
                                    </div>
                                    <span class="goal-tag">Filipino</span>
                                </div>
                            </div>
                            <div class="add-goal-row">
                                <input type="text" id="newGoalInput" placeholder="Add a new goal…" />
                                <button onclick="addGoal()"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                    </div>

                </div><!-- /bento -->
            <?php else: ?>
                <!-- Empty state for new / unauthenticated accounts -->
                <div class="dash-empty-state">
                    <i class="fas fa-user-graduate"></i>
                    <h3>Welcome to BUNHS Student Portal</h3>
                    <p>Your dashboard is ready, but there's no activity to show yet. Once you start joining events, receiving grades, and logging in regularly — everything will appear here.</p>
                </div>
            <?php endif; ?>
        </div><!-- /page-body -->
    </main>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <?php include 'notification_modal.php'; ?>


    <script>
        /* ── DATE ── */
        const now = new Date();
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const todayEl = document.getElementById('todayLabel');
        if (todayEl) todayEl.textContent = days[now.getDay()];

        /* ── COUNTER ANIMATION ── */
        function animateCount(el, target, duration = 1400) {
            let start = 0,
                step = target / (duration / 16);
            const tick = () => {
                start = Math.min(start + step, target);
                el.textContent = Math.floor(start);
                if (start < target) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }
        setTimeout(() => animateCount(document.getElementById('evCountNum'), 12), 400);



        /* ── GOALS TOGGLE ── */
        function toggleGoal(checkEl) {
            checkEl.classList.toggle('done');
            const strong = checkEl.closest('.goal-item').querySelector('strong');
            strong.classList.toggle('striked');
        }

        function addGoal() {
            const input = document.getElementById('newGoalInput');
            if (!input.value.trim()) return;
            const list = document.getElementById('goalsList');
            const item = document.createElement('div');
            item.className = 'goal-item';
            item.innerHTML = `
    <div class="goal-check" onclick="toggleGoal(this)"><i class="fas fa-check"></i></div>
    <div class="goal-text">
      <strong>${input.value.trim()}</strong>
      <span>Personal · No due date</span>
    </div>
    <span class="goal-tag">New</span>
  `;
            list.appendChild(item);
            input.value = '';
            showToast('Goal added!', 'success');
        }
        document.getElementById('newGoalInput').addEventListener('keydown', e => {
            if (e.key === 'Enter') addGoal();
        });

        /* ── SCHEDULE DAY TABS ── */
        document.querySelectorAll('.day-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.day-tab').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });

        /* ── TOAST ── */
        function showToast(message, type = 'success') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = 'toast ' + type;
            t.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${message}</span>`;
            c.appendChild(t);
            setTimeout(() => {
                t.style.opacity = '0';
                t.style.transition = 'opacity .3s';
                setTimeout(() => t.remove(), 300);
            }, 2800);
        }


        /* ══════════════════════════════════════════════════════════
           NOTIFICATION DOT  — hide the red dot once the student
           saves their notification preference via the nm-modal.
           Hooks into the "Done" button of notification_modal.php.
        ══════════════════════════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', function() {
            var doneBtn = document.getElementById('nmBtnDone');
            if (doneBtn) {
                doneBtn.addEventListener('click', function() {
                    var dot = document.getElementById('notifDot');
                    if (dot) dot.style.display = 'none';
                });
            }
        });

        /* ── NAV ── */
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', e => {
                e.preventDefault();
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });
        });
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
                    // StudentNav.init() runs from inside Student_nav.php's own <script>.
                    // We call bootProfileFromBody() here after the DOM is ready so the
                    // avatar, label, and name are all wired from <body data-*> attributes.
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
                    var current = window.location.pathname.split('/').pop() || 'Dashboard.php';
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