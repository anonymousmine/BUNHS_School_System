<?php
require_once '../session_config.php';

$is_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['admin', 'sub-admin']))
    || (isset($_SESSION['admin_id']));

if (!$is_logged_in) {
    header('Location: ../login.php');
    exit();
}

include '../db_connection.php';
/** @var \mysqli $conn */

$admin_name = $_SESSION['username'] ?? 'Admin';
$user_type  = $_SESSION['user_type'] ?? 'admin';
$admin_id   = (int) ($_SESSION['user_id'] ?? 0);

// ── Fetch admin profile for avatar/name ───────────────────────────
$user_data = null;
if ($user_type === 'admin') {
    $stmt = $conn->prepare("SELECT full_name, profile_image, principal_title FROM admin WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($user_type === 'sub-admin') {
    $stmt = $conn->prepare("SELECT full_name, profile_image, role FROM sub_admin WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$display_name = $user_data['full_name'] ?? $admin_name;
$profile_img  = $user_data['profile_image'] ?? '';
$profile_path = $user_type === 'admin' ? '../uploads/admin_profiles/' : '../uploads/sub_admin_profiles/';

// Role label
$role_map = [
    'news_admin'         => 'News Admin',
    'announcement_admin' => 'Announcement Admin',
    'student_admin'      => 'Student Admin',
    'teacher_admin'      => 'Teacher Admin',
    'club_admin'         => 'Club Admin',
    'super_sub_admin'    => 'Super Sub-Admin',
    'forms_admin'        => 'Forms Admin',
];
if ($user_type === 'admin') {
    $user_role = $user_data['principal_title'] ?? 'Administrator';
} else {
    $user_role = $role_map[$user_data['role'] ?? ''] ?? 'Sub-Admin';
}

// Avatar logic
$initials = strtoupper(implode('', array_map(
    fn($w) => $w[0],
    array_filter(explode(' ', trim($display_name)), fn($w) => !empty($w))
)));
if ($profile_img && file_exists(__DIR__ . '/../uploads/' . ($user_type === 'admin' ? 'admin_profiles' : 'sub_admin_profiles') . '/' . $profile_img)) {
    $avatarStyle   = 'background-image:url(' . htmlspecialchars($profile_path . $profile_img, ENT_QUOTES) . ');background-size:cover;background-position:center;';
    $avatarContent = '';
} else {
    $avatarStyle   = '';
    $avatarContent = htmlspecialchars($initials, ENT_QUOTES);
}

// ── Fetch counts for info cards ───────────────────────────────────
$news_count         = (int) ($conn->query("SELECT COUNT(*) c FROM news")->fetch_assoc()['c'] ?? 0);
$announcement_count = 0; // No announcements table exists
$sub_admin_count    = (int) ($conn->query("SELECT COUNT(*) c FROM sub_admin")->fetch_assoc()['c'] ?? 0);
$pending_docs       = 0; // No documents table exists yet

// ── Fetch recent news (latest 8) ─────────────────────────
$recent_announcements = [];
$ra = $conn->query("SELECT title, created_at, short_description as content FROM news ORDER BY created_at DESC LIMIT 8");
if ($ra) {
    while ($row = $ra->fetch_assoc()) $recent_announcements[] = $row;
}

// ── Fetch recent news (latest 6) ─────────────────────────────────
$recent_news = [];
$rn = $conn->query("SELECT title, created_at, category FROM news ORDER BY created_at DESC LIMIT 6");
if ($rn) {
    while ($row = $rn->fetch_assoc()) $recent_news[] = $row;
}

// ── Fetch recent activities ────────────────────────────────────────
$recent_activities = [];
// Use news updates as activities for now
$act = $conn->query("SELECT CONCAT('News article published: ', title) as text, created_at FROM news ORDER BY created_at DESC LIMIT 10");
if ($act) {
    while ($row = $act->fetch_assoc()) $recent_activities[] = $row;
}

// ── Email subscribers (table removed during cleanup) ─────────────
$email_subscribers = [];
$active_subscribers_count = 0;

// ── System notifications ──────────────────────────────────────────
$notifications = [];
if ($pending_docs > 0) {
    $notifications[] = ['type' => 'warning', 'icon' => 'fa-file-alt', 'msg' => "{$pending_docs} pending document approval" . ($pending_docs > 1 ? 's' : '')];
}
// No messages table exists yet, so no unread messages notification
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Buyoan National High School</title>
    <base href="/BUNHS_School_System/">

    <link rel="stylesheet" href="/BUNHS_School_System/admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="/BUNHS_School_System/overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Fraunces:ital,wght@0,300;0,600;1,300&display=swap" rel="stylesheet">

    <style>
        /* ── CSS Variables — aligned with admin_profile.php ── */
        :root {
            --moss-900:   #1e2e12;
            --moss-800:   #2c4219;
            --moss-700:   #3d5a2e;   /* green-dark equiv */
            --moss-600:   #4f7040;
            --moss-500:   #5c7a42;   /* green-mid equiv  */
            --moss-400:   #7a9e5a;
            --moss-300:   #8ca96b;   /* green-light equiv*/
            --moss-200:   #c4d9ac;
            --moss-100:   #eef3e8;   /* green-pale equiv */
            --moss-50:    #f6f8f3;   /* green-ghost equiv*/
            --amber:      #c07d38;
            --amber-pale: #fdf3e7;
            --red:        #b94040;
            --red-pale:   #fdf1f1;
            --text-primary:   #1e2820;
            --text-secondary: #5a6558;
            --text-muted:     #93a18e;
            --border:     #dce5d5;
            --white:      #ffffff;
            --shadow-sm:  0 1px 4px rgba(0,0,0,.06);
            --shadow-md:  0 4px 16px rgba(0,0,0,.08);
            --shadow-lg:  0 8px 32px rgba(0,0,0,.12);
            --radius-sm:  8px;
            --radius-md:  14px;
            --radius-lg:  20px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--moss-50);
            color: var(--text-primary);
            line-height: 1.5;
        }

        /* Sidebar offset — same as admin_profile.php */
        .main {
            margin-left: 240px;
            min-height: 100vh;
            width: calc(100% - 240px);
            box-sizing: border-box;
        }

        /* ── Page content wrapper ── */
        .page-content {
            padding: 28px 32px 56px;
            width: 100%;
            min-height: calc(100vh - 72px);
            background: var(--moss-50);
        }

        /* ── Breadcrumb ── */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12.5px;
            color: var(--text-muted);
            margin-bottom: 22px;
            animation: fadeUp .4s ease both;
        }
        .breadcrumb a { color: var(--moss-500); text-decoration: none; font-weight: 500; }
        .breadcrumb a:hover { text-decoration: underline; color: var(--moss-700); }
        .breadcrumb i { font-size: 8.5px; color: var(--text-muted); }
        .breadcrumb span { font-weight: 600; color: var(--text-secondary); }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Hero welcome card ── */
        .hero-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            margin-bottom: 22px;
            overflow: hidden;
            animation: fadeUp .5s ease both;
        }

        .hero-banner {
            height: 110px;
            background: linear-gradient(125deg, #0f1c0b 0%, #2a4120 30%, var(--moss-500) 65%, #7dae56 100%);
            position: relative;
            overflow: hidden;
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 120% at 80% 50%, rgba(140,169,107,.22) 0%, transparent 70%),
                repeating-linear-gradient(60deg, transparent, transparent 30px, rgba(255,255,255,.018) 30px, rgba(255,255,255,.018) 31px);
        }
        .hero-banner-accent {
            position: absolute;
            left: -30px;
            top: -30px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            border: 24px solid rgba(255,255,255,.05);
            pointer-events: none;
        }
        .hero-banner-accent2 {
            position: absolute;
            right: 80px;
            bottom: -50px;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            border: 14px solid rgba(255,255,255,.04);
            pointer-events: none;
        }

        .hero-body {
            padding: 0 28px 20px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }
        .hero-left {
            display: flex;
            align-items: flex-end;
            gap: 18px;
        }
        .hero-avatar-wrap {
            margin-top: -44px;
            flex-shrink: 0;
            position: relative;
        }
        .hero-avatar {
            width: 82px;
            height: 82px;
            border-radius: var(--radius-md);
            border: 4px solid var(--white);
            box-shadow: 0 4px 20px rgba(0,0,0,.18), 0 0 0 1px rgba(0,0,0,.05);
            background: linear-gradient(145deg, var(--moss-700) 0%, var(--moss-500) 50%, var(--moss-300) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            font-family: 'Fraunces', serif;
            letter-spacing: -1px;
        }
        .hero-avatar-wrap::after {
            content: '';
            position: absolute;
            bottom: 6px;
            right: 6px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4ade80;
            border: 2.5px solid var(--white);
            box-shadow: 0 0 0 2px rgba(74,222,128,.3);
        }
        .hero-info { padding-bottom: 2px; }
        .hero-name {
            font-family: 'Fraunces', serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.2;
            letter-spacing: -.3px;
        }
        .hero-role {
            font-size: 11px;
            font-weight: 700;
            color: var(--moss-500);
            margin: 3px 0 8px;
            text-transform: uppercase;
            letter-spacing: .7px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .hero-role::before {
            content: '';
            display: inline-block;
            width: 5px; height: 5px;
            border-radius: 50%;
            background: var(--moss-500);
            flex-shrink: 0;
        }
        .hero-sub {
            font-size: 12.5px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .hero-sub i { color: var(--moss-400); font-size: 11px; }
        .hero-date-badge {
            background: var(--moss-100);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 500;
            color: var(--moss-700);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        .hero-date-badge i { color: var(--moss-500); }

        /* ── BENTO GRID ── */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            grid-auto-rows: minmax(0, 1fr);
            gap: 16px;
        }

        /* Shared bento cell */
        .bento-cell {
            background: var(--white);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: box-shadow .22s, transform .22s;
            animation: fadeUp .5s ease both;
        }
        .bento-cell:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* Grid spans */
        .col-3  { grid-column: span 3; }
        .col-4  { grid-column: span 4; }
        .col-5  { grid-column: span 5; }
        .col-6  { grid-column: span 6; }
        .col-7  { grid-column: span 7; }
        .col-8  { grid-column: span 8; }
        .col-12 { grid-column: span 12; }

        /* Stagger animation delays */
        .bento-cell:nth-child(1)  { animation-delay: .05s; }
        .bento-cell:nth-child(2)  { animation-delay: .10s; }
        .bento-cell:nth-child(3)  { animation-delay: .15s; }
        .bento-cell:nth-child(4)  { animation-delay: .20s; }
        .bento-cell:nth-child(5)  { animation-delay: .25s; }
        .bento-cell:nth-child(6)  { animation-delay: .30s; }
        .bento-cell:nth-child(7)  { animation-delay: .35s; }

        /* ── Cell: Info count ── */
        .info-cell {
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 130px;
        }
        .info-cell .cell-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }
        .info-cell .cell-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--moss-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--moss-700);
            font-size: 15px;
            flex-shrink: 0;
        }
        .info-cell .cell-tag {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: 3px 8px;
            border-radius: 20px;
            background: var(--moss-100);
            color: var(--moss-700);
            border: 1px solid var(--border);
        }
        .info-cell .cell-count {
            font-family: 'Fraunces', serif;
            font-size: 36px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1;
            letter-spacing: -1.5px;
        }
        .info-cell .cell-label {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 3px;
        }

        /* Accent versions */
        .info-cell.accent-moss {
            background: linear-gradient(135deg, var(--moss-800) 0%, var(--moss-600) 100%);
            border-color: var(--moss-700);
        }
        .info-cell.accent-moss .cell-icon { background: rgba(255,255,255,.15); color: #fff; }
        .info-cell.accent-moss .cell-tag  { background: rgba(255,255,255,.12); color: rgba(255,255,255,.85); border-color: rgba(255,255,255,.15); }
        .info-cell.accent-moss .cell-count { color: #fff; }
        .info-cell.accent-moss .cell-label { color: rgba(255,255,255,.65); }

        .info-cell.accent-light {
            background: var(--moss-50);
        }

        /* ── Cell: Announcements list ── */
        .list-cell { display: flex; flex-direction: column; }
        .cell-header {
            padding: 18px 22px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
        }
        .cell-header-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cell-header-title i { color: var(--moss-500); font-size: 13px; }
        .cell-header-link {
            font-size: 12px;
            font-weight: 600;
            color: var(--moss-500);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color .14s, gap .14s;
        }
        .cell-header-link:hover { color: var(--moss-700); gap: 7px; }

        .cell-body { padding: 10px 0; flex: 1; overflow: hidden; }
        .announcement-item {
            padding: 10px 22px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border-bottom: 1px solid var(--moss-50);
            transition: background .12s;
            cursor: default;
        }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-item:hover { background: var(--moss-50); }

        .ann-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--moss-400);
            flex-shrink: 0;
            margin-top: 6px;
        }
        .ann-content { flex: 1; min-width: 0; }
        .ann-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.35;
        }
        .ann-meta {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .ann-category {
            display: inline-flex;
            align-items: center;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: 2px 7px;
            border-radius: 10px;
            background: var(--moss-100);
            color: var(--moss-700);
            flex-shrink: 0;
            margin-top: 3px;
        }

        /* ── Cell: News feed ── */
        .news-item {
            padding: 11px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--moss-50);
            transition: background .12s;
        }
        .news-item:last-child { border-bottom: none; }
        .news-item:hover { background: var(--moss-50); }
        .news-icon-wrap {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--moss-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--moss-600);
            font-size: 13px;
            flex-shrink: 0;
        }
        .news-content { flex: 1; min-width: 0; }
        .news-title {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .news-date { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

        /* ── Cell: Activity feed ── */
        .activity-item-bento {
            padding: 10px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--moss-50);
            transition: background .12s;
        }
        .activity-item-bento:last-child { border-bottom: none; }
        .activity-item-bento:hover { background: var(--moss-50); }
        .activity-dot-wrap {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--moss-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--moss-600);
            font-size: 12px;
            flex-shrink: 0;
        }
        .activity-text { font-size: 12.5px; color: var(--text-primary); font-weight: 500; flex: 1; min-width: 0; }
        .activity-time { font-size: 10.5px; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }

        /* ── Cell: System notifications ── */
        .notif-list { padding: 12px 0; }
        .notif-item {
            padding: 12px 22px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border-bottom: 1px solid var(--moss-50);
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }
        .notif-icon.warning { background: #fff7e6; color: #c07d38; }
        .notif-icon.info    { background: var(--moss-100); color: var(--moss-600); }
        .notif-icon.success { background: #edfff4; color: #22863a; }
        .notif-msg { font-size: 12.5px; color: var(--text-primary); font-weight: 500; flex: 1; padding-top: 7px; }

        /* ── Cell: Sub-Admin management ── */
        .management-cell { padding: 22px; }
        .management-cell .mgmt-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 14px;
        }
        .mgmt-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .mgmt-stat:last-of-type { border-bottom: none; }
        .mgmt-stat-icon {
            width: 34px; height: 34px;
            border-radius: 9px;
            background: var(--moss-100);
            display: flex; align-items: center; justify-content: center;
            color: var(--moss-600);
            font-size: 14px;
            flex-shrink: 0;
        }
        .mgmt-stat-info { flex: 1; }
        .mgmt-stat-info .label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        .mgmt-stat-info .value { font-size: 17px; font-weight: 700; color: var(--text-primary); font-family: 'Fraunces', serif; }
        .mgmt-link {
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-size: 12.5px;
            font-weight: 600;
            background: linear-gradient(135deg, var(--moss-700) 0%, var(--moss-500) 100%);
            color: #fff;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(61,90,46,.25);
            transition: all .16s cubic-bezier(.16,1,.3,1);
            width: 100%;
            justify-content: center;
        }
        .mgmt-link:hover {
            background: linear-gradient(135deg, var(--moss-600) 0%, var(--moss-400) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(61,90,46,.3);
        }

        /* ── Cell: Accent banner (full-width info strip) ── */
        .banner-cell {
            background: linear-gradient(125deg, #0f1c0b 0%, var(--moss-800) 40%, var(--moss-600) 100%);
            border-color: var(--moss-700);
            padding: 22px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 90px;
        }
        .banner-cell-text h3 {
            font-family: 'Fraunces', serif;
            font-size: 17px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 4px;
        }
        .banner-cell-text p {
            font-size: 12.5px;
            color: rgba(255,255,255,.65);
        }
        .banner-cell-actions { display: flex; gap: 10px; flex-shrink: 0; }
        .banner-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all .16s;
            white-space: nowrap;
            cursor: pointer;
            border: none;
        }
        .banner-btn.primary {
            background: rgba(255,255,255,.95);
            color: var(--moss-800);
            box-shadow: 0 2px 10px rgba(0,0,0,.2);
        }
        .banner-btn.primary:hover { background: #fff; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.25); }
        .banner-btn.ghost {
            background: rgba(255,255,255,.1);
            color: rgba(255,255,255,.9);
            border: 1.5px solid rgba(255,255,255,.2);
        }
        .banner-btn.ghost:hover { background: rgba(255,255,255,.18); transform: translateY(-2px); }

        /* ── Empty state ── */
        .empty-state {
            padding: 28px;
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }
        .empty-state i { font-size: 28px; margin-bottom: 10px; display: block; color: var(--moss-200); }

        /* ── Loading spinner ── */
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid var(--border); border-top-color: var(--moss-500); border-radius: 50%; animation: spin .7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Responsive ── */
        @media (max-width: 1100px) {
            .col-3  { grid-column: span 6; }
            .col-4  { grid-column: span 6; }
            .col-5  { grid-column: span 12; }
            .col-7  { grid-column: span 12; }
            .col-8  { grid-column: span 12; }
        }
        @media (max-width: 768px) {
            .main { margin-left: 0; width: 100%; }
            .page-content { padding: 16px 14px 48px; }
            .col-3, .col-4, .col-5, .col-6, .col-7, .col-8, .col-12 { grid-column: span 12; }
            .banner-cell { flex-direction: column; align-items: flex-start; }
            .hero-body { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>

<body>
    <?php include 'admin_nav.php'; ?>

    <section class="page-content" id="dashboard-content" style="display:block !important;">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <i class="fas fa-home"></i>
            <a href="admin_dashboard.php">Home</a>
            <i class="fas fa-chevron-right"></i>
            <span>Dashboard</span>
        </nav>

        <!-- Hero Welcome Card -->
        <div class="hero-card">
            <!-- ═══════════════════════════════════════════
                 BENTO GRID
            ═══════════════════════════════════════════ -->
            <div class="bento-grid">

                <!-- ── Row 1: Info banner (full width) ── -->
                <div class="bento-cell col-12 banner-cell">
                    <div class="banner-cell-text">
                        <h3>Welcome back, <?= htmlspecialchars($display_name, ENT_QUOTES) ?>!</h3>
                        <p>Here's an overview of your school's information, news and announcements.</p>
                    </div>
                    <div class="banner-cell-actions">
                        <div class="hero-date-badge">
                            <i class="fas fa-calendar-alt"></i>
                            <span id="live-date">Loading…</span>
                        </div>
                        <a href="announcements/create_announcement.php" class="banner-btn primary">
                            <i class="fas fa-bullhorn"></i> New Announcement
                        </a>
                        <a href="announcements/create_new.php" class="banner-btn ghost">
                            <i class="fas fa-newspaper"></i> Post News
                        </a>
                    </div>
                </div>

                <!-- ── Row 2: Count cells ── -->
                <!-- News count (accent) -->
                <div class="bento-cell col-3 info-cell accent-moss">
                    <div class="cell-top">
                        <div class="cell-icon"><i class="fas fa-newspaper"></i></div>
                        <span class="cell-tag">Published</span>
                    </div>
                    <div>
                        <div class="cell-count"><?= $news_count ?></div>
                        <div class="cell-label">News Articles</div>
                    </div>
                </div>
            <div class="bento-cell col-3 info-cell">
                <div class="cell-top">
                    <div class="cell-icon"><i class="fas fa-bullhorn"></i></div>
                    <span class="cell-tag">Active</span>
                </div>
                <div>
                    <div class="cell-count"><?= $announcement_count ?></div>
                    <div class="cell-label">Announcements</div>
                </div>
            </div>

            <!-- Sub-admin count -->
            <div class="bento-cell col-3 info-cell accent-light">
                <div class="cell-top">
                    <div class="cell-icon"><i class="fas fa-user-shield"></i></div>
                    <span class="cell-tag">Team</span>
                </div>
                <div>
                    <div class="cell-count"><?= $sub_admin_count ?></div>
                    <div class="cell-label">Sub-Admins</div>
                </div>
            </div>

            <!-- Pending docs count -->
            <div class="bento-cell col-3 info-cell">
                <div class="cell-top">
                    <div class="cell-icon" style="background:<?= $pending_docs > 0 ? '#fff7e6' : 'var(--moss-100)' ?>;color:<?= $pending_docs > 0 ? '#c07d38' : 'var(--moss-600)' ?>;"><i class="fas fa-file-alt"></i></div>
                    <span class="cell-tag" style="<?= $pending_docs > 0 ? 'background:#fff7e6;color:#c07d38;border-color:#f5d9a8;' : '' ?>">Pending</span>
                </div>
                <div>
                    <div class="cell-count"><?= $pending_docs ?></div>
                    <div class="cell-label">Document Approvals</div>
                </div>
            </div>

            <!-- Email subscribers count -->
            <div class="bento-cell col-3 info-cell accent-moss" style="grid-column: span 3;">
                <div class="cell-top">
                    <div class="cell-icon"><i class="fas fa-envelope"></i></div>
                    <span class="cell-tag">Active</span>
                </div>
                <div>
                    <div class="cell-count"><?= $active_subscribers_count ?></div>
                    <div class="cell-label">Email Subscribers</div>
                </div>
            </div>

            <!-- ── Row 3: Announcements (large) + Sub-Admin panel ── -->

            <!-- Announcements list -->
            <div class="bento-cell col-8 list-cell" style="min-height:360px;">
                <div class="cell-header">
                    <div class="cell-header-title">
                        <i class="fas fa-bullhorn"></i> Recent Announcements
                    </div>
                    <a href="announcements/" class="cell-header-link">
                        View all <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="cell-body" id="announcements-body">
                    <?php if (count($recent_announcements) > 0): ?>
                        <?php foreach ($recent_announcements as $ann): ?>
                        <div class="announcement-item">
                            <div class="ann-dot"></div>
                            <div class="ann-content">
                                <div class="ann-title"><?= htmlspecialchars($ann['title'], ENT_QUOTES) ?></div>
                                <div class="ann-meta"><?= date('M j, Y · g:i A', strtotime($ann['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            No announcements yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sub-Admin management -->
            <div class="bento-cell col-4 management-cell">
                <div class="mgmt-label">User Management</div>
                <div class="mgmt-stat">
                    <div class="mgmt-stat-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="mgmt-stat-info">
                        <div class="label">Total Sub-Admins</div>
                        <div class="value"><?= $sub_admin_count ?></div>
                    </div>
                </div>
                <?php
                // Sub-admin role breakdown
                $role_counts = [];
                $rc = $conn->query("SELECT role, COUNT(*) c FROM sub_admin GROUP BY role");
                if ($rc) {
                    while ($r = $rc->fetch_assoc()) $role_counts[$r['role']] = $r['c'];
                }
                foreach ($role_map as $key => $label):
                    $c = $role_counts[$key] ?? 0;
                    if ($c === 0) continue;
                ?>
                <div class="mgmt-stat">
                    <div class="mgmt-stat-icon" style="font-size:12px;">
                        <i class="fas fa-circle" style="font-size:8px;color:var(--moss-400);"></i>
                    </div>
                    <div class="mgmt-stat-info">
                        <div class="label"><?= htmlspecialchars($label) ?></div>
                        <div class="value" style="font-size:14px;"><?= $c ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if ($user_type === 'admin'): ?>
                <a href="subadmin_signup.php" class="mgmt-link">
                    <i class="fas fa-user-plus"></i> Add Sub-Admin
                </a>
                <?php endif; ?>

                <a href="manage_subadmins.php" class="mgmt-link" style="margin-top:8px;background:linear-gradient(135deg,var(--moss-100) 0%,var(--moss-50) 100%);color:var(--moss-700);box-shadow:none;border:1px solid var(--border);">
                    <i class="fas fa-users-cog"></i> Manage Sub-Admins
                </a>
            </div>

            <!-- ── Row 4: News feed + Activity + Notifications ── -->

            <!-- News Feed -->
            <div class="bento-cell col-5 list-cell" style="min-height:300px;">
                <div class="cell-header">
                    <div class="cell-header-title">
                        <i class="fas fa-newspaper"></i> Latest News
                    </div>
                    <a href="announcements/" class="cell-header-link">
                        View all <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="cell-body">
                    <?php if (count($recent_news) > 0): ?>
                        <?php foreach ($recent_news as $n): ?>
                        <div class="news-item">
                            <div class="news-icon-wrap"><i class="fas fa-newspaper"></i></div>
                            <div class="news-content">
                                <div class="news-title"><?= htmlspecialchars($n['title'], ENT_QUOTES) ?></div>
                                <div class="news-date">
                                    <?php if (!empty($n['category'])): ?>
                                        <span style="color:var(--moss-600);font-weight:600;"><?= htmlspecialchars($n['category'], ENT_QUOTES) ?></span>
                                        &nbsp;·&nbsp;
                                    <?php endif; ?>
                                    <?= date('M j, Y', strtotime($n['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-newspaper"></i>
                            No news articles yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bento-cell col-4 list-cell" style="min-height:300px;">
                <div class="cell-header">
                    <div class="cell-header-title">
                        <i class="fas fa-history"></i> Recent Activity
                    </div>
                </div>
                <div class="cell-body" id="activity-body">
                    <?php if (count($recent_activities) > 0): ?>
                        <?php foreach ($recent_activities as $act): ?>
                        <div class="activity-item-bento">
                            <div class="activity-dot-wrap"><i class="fas fa-bolt"></i></div>
                            <div class="activity-text"><?= htmlspecialchars($act['text'], ENT_QUOTES) ?></div>
                            <div class="activity-time"><?= date('M j', strtotime($act['created_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            No recent activity.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Notifications -->
            <div class="bento-cell col-3 list-cell" style="min-height:300px;">
                <div class="cell-header">
                    <div class="cell-header-title">
                        <i class="fas fa-bell"></i> Notifications
                    </div>
                </div>
                <div class="notif-list">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notif): ?>
                        <div class="notif-item">
                            <div class="notif-icon <?= $notif['type'] ?>">
                                <i class="fas <?= $notif['icon'] ?>"></i>
                            </div>
                            <div class="notif-msg"><?= htmlspecialchars($notif['msg'], ENT_QUOTES) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color:var(--moss-300);"></i>
                            All systems clear.
                        </div>
                    <?php endif; ?>

                    <!-- Always-present links -->
                    <div class="notif-item">
                        <div class="notif-icon info"><i class="fas fa-link"></i></div>
                        <div class="notif-msg">
                            <a href="../news.php" style="color:var(--moss-600);text-decoration:none;font-weight:600;">
                                View Public Site <i class="fas fa-external-link-alt" style="font-size:10px;"></i>
                            </a>
                        </div>
                    </div>
                    <div class="notif-item">
                        <div class="notif-icon info"><i class="fas fa-user-circle"></i></div>
                        <div class="notif-msg">
                            <a href="admin_profile.php" style="color:var(--moss-600);text-decoration:none;font-weight:600;">
                                My Profile <i class="fas fa-arrow-right" style="font-size:10px;"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /bento-grid -->

        <!-- Email Subscribers Section (Removed During Cleanup) -->
        <div class="subscribers-table-container" style="margin-top: 24px;">
            <div class="bento-cell col-12" style="padding: 0;">
                <div class="cell-header" style="border-bottom: none; padding: 22px 22px 14px;">
                    <div class="cell-header-title">
                        <i class="fas fa-envelope"></i> Email Subscribers
                        <span style="margin-left: 8px; font-size: 11px; color: var(--text-muted); font-weight: 400;">
                            (0 total, 0 active)
                        </span>
                    </div>
                </div>
                
                <div style="padding: 0 22px 22px;">
                    <div class="empty-state" style="padding: 40px; text-align: center;">
                        <i class="fas fa-envelope" style="color: var(--moss-200); font-size: 32px; margin-bottom: 12px;"></i>
                        <p style="color: var(--text-muted); font-size: 14px;">Email subscribers table was removed during database cleanup.</p>
                        <p style="color: var(--text-muted); font-size: 12px; margin-top: 8px;">This feature can be re-enabled if needed.</p>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <script src="/BUNHS_School_System/admin_account/admin_assets/js/admin_script.js"></script>
    <script>
        /* ── Live date display ── */
        (function updateDate() {
            const el = document.getElementById('live-date');
            if (!el) return;
            const now = new Date();
            el.textContent = now.toLocaleDateString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
            });
        })();

        /* ── Move page-content into .main (same pattern as admin_profile.php) ── */
        (function() {
            var mainDiv = document.querySelector('.main');
            var pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent && !mainDiv.contains(pageContent)) {
                mainDiv.appendChild(pageContent);
            }
        })();

        /* ── Mobile nav ── */
        function initMobileNav() {
            var hamburger = document.getElementById('navHamburgerBtn');
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (!hamburger || !sidebar || !overlay) return;

            var fresh = hamburger.cloneNode(true);
            hamburger.parentNode.replaceChild(fresh, hamburger);
            hamburger = fresh;

            function openSidebar() {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('visible');
                hamburger.classList.add('open');
                hamburger.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }
            function closeSidebar() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                hamburger.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }

            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
            });
            overlay.addEventListener('click', closeSidebar);
            sidebar.querySelectorAll('a.menu-item').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 900) closeSidebar();
                });
            });
            window.addEventListener('resize', function() {
                if (window.innerWidth > 900) closeSidebar();
            });
        }

        document.addEventListener('DOMContentLoaded', initMobileNav);

        /* ── Refresh subscribers table ── */
        function refreshSubscribers() {
            const button = document.querySelector('[onclick="refreshSubscribers()"]');
            if (button) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
                button.disabled = true;
            }
            
            // Simple page refresh to reload subscriber data
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        /* ── Add row hover effect for subscribers table ── */
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.subscribers-table tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.background = 'var(--moss-50)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.background = '';
                });
            });
        });
    </script>
</body>
</html>