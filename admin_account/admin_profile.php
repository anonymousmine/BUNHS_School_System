<?php
// ─────────────────────────────────────────────
//  Session & Database bootstrap
// ─────────────────────────────────────────────
require_once '../session_config.php';

// Redirect to login if not authenticated
$is_logged_in = (isset($_SESSION['user_id']) && in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']))
    || isset($_SESSION['admin_id']);
if (!$is_logged_in) {
    header('Location: ../index.php');
    exit;
}

include '../db_connection.php';   // Provides $conn (mysqli)
/** @var \mysqli $conn */ // $conn is set by db_connection.php

$admin_id = (int) $_SESSION['user_id'];

// ── Fetch user data (admin or sub-admin) ───────────────────────────
$user_type = $_SESSION['user_type'] ?? 'admin';
$user_data = null;

if ($user_type === 'admin') {
    // Fetch from admin table
    $stmt = $conn->prepare(
        "SELECT * FROM admin WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if (!$user_data) {
        // Admin record not found – log out for safety
        session_destroy();
        header('Location: ../index.php');
        exit;
    }

    // Auto-create new columns if not exist for admin table
    $conn->query("ALTER TABLE admin ADD COLUMN IF NOT EXISTS principal_title ENUM('Principal I','Principal II','Principal III','Principal IV') DEFAULT 'Principal I'");
    $conn->query("ALTER TABLE admin ADD COLUMN IF NOT EXISTS mission TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE admin ADD COLUMN IF NOT EXISTS vision TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE admin ADD COLUMN IF NOT EXISTS core_values TEXT DEFAULT NULL");

    // Re-fetch admin after potential schema change
    $stmt2 = $conn->prepare("SELECT * FROM admin WHERE id = ? LIMIT 1");
    $stmt2->bind_param('i', $admin_id);
    $stmt2->execute();
    $user_data = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
} elseif ($user_type === 'sub-admin') {
    // Fetch from sub_admin table
    $stmt = $conn->prepare(
        "SELECT * FROM sub_admin WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if (!$user_data) {
        // Sub-admin record not found – log out for safety
        session_destroy();
        header('Location: ../index.php');
        exit;
    }

    // Auto-create new columns if not exist for sub_admin table
    $conn->query("ALTER TABLE sub_admin ADD COLUMN IF NOT EXISTS mission TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE sub_admin ADD COLUMN IF NOT EXISTS vision TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE sub_admin ADD COLUMN IF NOT EXISTS core_values TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE sub_admin ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) DEFAULT NULL");
    $conn->query("ALTER TABLE sub_admin ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL");

    // Re-fetch sub_admin after potential schema change
    $stmt2 = $conn->prepare("SELECT * FROM sub_admin WHERE id = ? LIMIT 1");
    $stmt2->bind_param('i', $admin_id);
    $stmt2->execute();
    $user_data = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
}

// ── Helpers ───────────────────────────────────
function h(string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/** Split a newline-separated DB text field into a <li> list. */
function listItems(string $text): string
{
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $out   = '';
    foreach ($lines as $line) {
        $out .= '<li>' . h($line) . '</li>';
    }
    return $out;
}

/** Split a newline-separated DB text field into tag spans. */
function tagItems(string $text): string
{
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    $out   = '';
    foreach ($lines as $line) {
        $out .= '<span class="tag">' . h($line) . '</span>';
    }
    return $out;
}

// ── Derived display values ────────────────────
$full_name    = $user_data['full_name'] ?? $user_data['username'] ?? 'Administrator';
$title        = $user_data['title'] ?? 'School Administrator';
$principal_title = $user_data['principal_title'] ?? '';
$office       = $user_data['office_location'] ?? '';
$school_phone = $user_data['school_phone'] ?? '';
$school_email = $user_data['school_email'] ?? $user_data['email'] ?? '';
$biography    = $user_data['biography'] ?? '';
$education    = $user_data['education_history'] ?? '';
$certs        = $user_data['certifications'] ?? '';
$years_exp    = (int) ($user_data['years_experience'] ?? 0);
$twitter_url  = $user_data['twitter_url'] ?? '';
$linkedin_url = $user_data['linkedin_url'] ?? '';
$responsibilities = $user_data['responsibilities'] ?? '';
$goals        = $user_data['leadership_goals'] ?? '';
$profile_img  = $user_data['profile_image'] ?? '';
$profile_picture_url = $user_data['profile_picture_url'] ?? '';
$profile_picture_type = $user_data['profile_picture_type'] ?? 'icon';
$registration_method = $user_data['registration_method'] ?? 'email';
$mission      = $user_data['mission'] ?? '';
$vision       = $user_data['vision'] ?? '';
$core_values  = $user_data['core_values'] ?? '';

// Profile path for uploads - role-specific
// admin_profile.php lives in admin/, uploads/ is at root level, so we need ../
$profile_path = $user_type === 'admin' ? '../uploads/admin_profiles/' : '../uploads/sub_admin_profiles/';

// Role-specific data
$user_role = 'Administrator';
if ($user_type === 'admin' && !empty($principal_title)) {
    $user_role = $principal_title;
} elseif ($user_type === 'sub-admin') {
    // Use roleLabel function for sub-admin roles
    $role_map = [
        'news_admin'         => 'News Admin',
        'announcement_admin' => 'Announcement Admin',
        'student_admin'      => 'Student Admin',
        'teacher_admin'      => 'Teacher Admin',
        'club_admin'         => 'Club Admin',
        'super_sub_admin'    => 'Super Sub-Admin',
        'forms_admin'        => 'Forms Admin',
    ];
    $user_role = $role_map[$user_data['role'] ?? 'news_admin'] ?? 'Sub-Admin';
}

// Private fields
$personal_mobile = $user_data['personal_mobile'] ?? '';
$personal_email  = $user_data['personal_email']  ?? '';
$date_of_birth   = $user_data['date_of_birth']   ?? '';
$place_of_birth  = $user_data['place_of_birth']  ?? '';
$home_address    = $user_data['home_address']    ?? '';
$government_id   = $user_data['government_id']   ?? '';
$emerg_name      = $user_data['emergency_contact_name']  ?? '';
$emerg_phone     = $user_data['emergency_contact_phone'] ?? '';
$emerg_rel       = $user_data['emergency_relationship']  ?? '';
$bank_account    = $user_data['bank_account']    ?? '';

// Avatar: handle different profile picture types
if ($user_type === 'sub-admin') {
    // Sub-admin profile picture logic
    if ($profile_picture_type === 'gmail' && !empty($profile_picture_url)) {
        // Gmail profile picture
        $avatarStyle = 'background-image:url(' . h($profile_picture_url) . ');background-size:cover;background-position:center;';
        $avatarContent = '';
    } elseif ($profile_picture_type === 'upload' && !empty($profile_img)) {
        // Uploaded profile picture
        $avatarStyle = 'background-image:url(' . h($profile_path . $profile_img) . ');background-size:cover;background-position:center;';
        $avatarContent = '';
    } else {
        // Icon display for phone registration or fallback
        $avatarStyle = 'background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);display:flex;align-items:center;justify-content:center;';
        $avatarContent = '<i class="fa-solid fa-user" style="color:white;font-size:28px;"></i>';
    }
} else {
    // Admin profile picture logic (existing)
    $initials = strtoupper(
        implode('', array_map(
            fn($w) => $w[0],
            array_filter(
                explode(' ', trim($full_name)),
                fn($w) => !empty($w)
            )
        ))
    );

    $upload_subfolder = $user_type === 'admin' ? 'admin_profiles' : 'sub_admin_profiles';
    if ($profile_img && file_exists(__DIR__ . '/../uploads/' . $upload_subfolder . '/' . $profile_img)) {
        // Admin has uploaded profile picture and file exists
        $avatarStyle = 'background-image:url(' . h($profile_path . $profile_img) . ');background-size:cover;background-position:center;';
        $avatarContent = '';
    } else {
        // No profile picture or file doesn't exist, show initials
        $avatarStyle = '';
        $avatarContent = h($initials);
    }
}

// Certification count
$cert_count = count(array_filter(array_map('trim', explode("\n", $certs))));
// Responsibility count
$resp_count = count(array_filter(array_map('trim', explode("\n", $responsibilities))));

// ── Homepage Cards: fetch rows + handle save before ANY HTML output ───────────
$hpc_rows = [];
$hpc_res  = $conn->query("SELECT * FROM homepage_cards ORDER BY id ASC");
if ($hpc_res) {
    while ($hpc_row = $hpc_res->fetch_assoc()) $hpc_rows[] = $hpc_row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_hpc_action'] ?? '') === 'save_homepage_card') {
    $hpc_id    = (int)($_POST['hpc_id']    ?? 0);
    $hpc_title = trim($_POST['hpc_title']  ?? '');
    $hpc_desc  = trim($_POST['hpc_desc']   ?? '');
    $hpc_icon  = trim($_POST['hpc_icon']   ?? '');
    $hpc_image = trim($_POST['hpc_image']  ?? '');

    // Handle optional image upload
    if (!empty($_FILES['hpc_image_file']['name'])) {
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (
            in_array($_FILES['hpc_image_file']['type'], $allowed_mime)
            && $_FILES['hpc_image_file']['size'] <= 3145728
        ) {
            $ext   = pathinfo($_FILES['hpc_image_file']['name'], PATHINFO_EXTENSION);
            $fname = 'hpc_' . $hpc_id . '_' . time() . '.' . $ext;
            $dest  = '../assets/img/homepage_cards/' . $fname;
            @mkdir('../assets/img/homepage_cards/', 0775, true);
            if (move_uploaded_file($_FILES['hpc_image_file']['tmp_name'], $dest)) {
                $hpc_image = 'assets/img/homepage_cards/' . $fname;
            }
        }
    }

    if ($hpc_id > 0) {
        $stmt_hpc = $conn->prepare(
            "UPDATE homepage_cards SET title=?, description=?, icon=?, image=? WHERE id=?"
        );
        $stmt_hpc->bind_param('ssssi', $hpc_title, $hpc_desc, $hpc_icon, $hpc_image, $hpc_id);
        $stmt_hpc->execute();
        $stmt_hpc->close();
    }

    // AJAX response (JS fetch or XMLHttpRequest)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['_ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'image' => $hpc_image]);
        exit;
    }
    // Plain-POST fallback
    header('Location: admin_profile.php#homepage-cards-editor');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Profile — Buyoan National High School</title>

    <!-- Shared Admin Styles -->
    <link rel="stylesheet" href="/BUNHS_School_System/admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="/BUNHS_School_System/overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Fraunces:ital,wght@0,300;0,600;1,300&display=swap" rel="stylesheet" />

    <style>
        /* Base variables - kept for page-specific styling */
        :root {
            --green-dark: #3d5a2e;
            --green-mid: #5c7a42;
            --green-light: #8ca96b;
            --green-pale: #eef3e8;
            --green-ghost: #f6f8f3;
            --amber: #c07d38;
            --amber-pale: #fdf3e7;
            --red: #b94040;
            --red-pale: #fdf1f1;
            --text-primary: #1e2820;
            --text-secondary: #5a6558;
            --text-muted: #93a18e;
            --border: #dce5d5;
            --white: #ffffff;
            --shadow-sm: 0 1px 4px rgba(0, 0, 0, .06);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .08);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, .12);
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
        }

        /* Base reset */
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
            font-family:
                "Inter",
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            background: var(--light-color);
            color: var(--text-primary);
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        /* Main layout — .main is opened by admin_nav.php */
        .main {
            margin-left: 240px;
            min-height: 100vh;
            width: calc(100% - 240px);
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12.5px;
            color: var(--text-muted);
            margin-bottom: 22px;
            animation: cardReveal .4s ease both;
        }

        .breadcrumb a {
            color: var(--green-mid);
            text-decoration: none;
            font-weight: 500;
            transition: color .12s;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
            color: var(--green-dark);
        }

        .breadcrumb i {
            font-size: 8.5px;
            color: var(--text-muted);
        }

        .breadcrumb span {
            font-weight: 600;
            color: var(--text-secondary);
        }

        /* PAGE CONTENT — fills the remaining space after sidebar */
        .page-content {
            padding: 28px 32px 48px;
            width: 100%;
            min-height: calc(100vh - 72px);
            box-sizing: border-box;
            background: #f8fafc;
        }

        /* Profile page specific styles */
        .page-content.profile {
            background: #f8fafc;
        }

        /* Hero */
        .hero-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            margin-bottom: 22px;
            overflow: hidden;
            animation: cardReveal .5s ease both;
        }

        @keyframes cardReveal {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-banner {
            height: 130px;
            background: linear-gradient(125deg, #0f1c0b 0%, #2a4120 30%, var(--green-mid) 65%, #7dae56 100%);
            position: relative;
            overflow: hidden;
        }

        /* Layered geometric pattern overlay */
        .hero-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 120% at 80% 50%, rgba(140, 169, 107, .22) 0%, transparent 70%),
                radial-gradient(ellipse 30% 80% at 10% 80%, rgba(255, 255, 255, .07) 0%, transparent 60%);
        }

        .hero-banner::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                repeating-linear-gradient(-45deg,
                    transparent,
                    transparent 18px,
                    rgba(255, 255, 255, .028) 18px,
                    rgba(255, 255, 255, .028) 19px);
        }

        /* Decorative circle accent on hero */
        .hero-banner-accent {
            position: absolute;
            right: -30px;
            top: -30px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 28px solid rgba(255, 255, 255, .05);
            pointer-events: none;
        }

        .hero-banner-accent2 {
            position: absolute;
            right: 80px;
            bottom: -50px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 16px solid rgba(255, 255, 255, .04);
            pointer-events: none;
        }

        .hero-body {
            padding: 0 28px 24px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 14px;
        }

        .hero-left {
            display: flex;
            align-items: flex-end;
            gap: 18px;
        }

        .hero-avatar-wrap {
            margin-top: -50px;
            flex-shrink: 0;
            position: relative;
        }

        .hero-avatar {
            width: 92px;
            height: 92px;
            border-radius: var(--radius-md);
            border: 4px solid var(--white);
            box-shadow: 0 4px 20px rgba(0, 0, 0, .18), 0 0 0 1px rgba(0, 0, 0, .05);
            object-fit: cover;
            background: linear-gradient(145deg, var(--green-dark) 0%, var(--green-mid) 50%, var(--green-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            font-family: 'Fraunces', serif;
            letter-spacing: -1px;
            transition: transform .2s, box-shadow .2s;
        }

        .hero-avatar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0, 0, 0, .22), 0 0 0 1px rgba(0, 0, 0, .05);
        }

        /* Online status dot */
        .hero-avatar-wrap::after {
            content: '';
            position: absolute;
            bottom: 6px;
            right: 6px;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: #4ade80;
            border: 2.5px solid var(--white);
            box-shadow: 0 0 0 2px rgba(74, 222, 128, .3);
        }

        .hero-info {
            padding-bottom: 4px;
        }

        .hero-name {
            font-family: 'Fraunces', serif;
            font-size: 22px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.2;
            letter-spacing: -.3px;
        }

        .hero-role {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--green-mid);
            margin: 3px 0 10px;
            text-transform: uppercase;
            letter-spacing: .7px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .hero-role::before {
            content: '';
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--green-mid);
            flex-shrink: 0;
        }

        .hero-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 500;
            background: var(--green-pale);
            color: var(--green-dark);
            border: 1px solid var(--border);
            transition: background .14s, border-color .14s, transform .14s;
        }

        .chip:hover {
            background: #dcebd0;
            border-color: var(--green-light);
            transform: translateY(-1px);
        }

        .chip i {
            font-size: 10px;
            color: var(--green-mid);
        }

        .hero-actions {
            display: flex;
            gap: 8px;
            padding-bottom: 4px;
            flex-shrink: 0;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .16s cubic-bezier(.16, 1, .3, 1);
            border: none;
            font-family: inherit;
            white-space: nowrap;
            letter-spacing: .1px;
            position: relative;
            overflow: hidden;
        }

        .btn::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0);
            transition: background .14s;
            border-radius: inherit;
        }

        .btn:active::after {
            background: rgba(0, 0, 0, .06);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-dark) 0%, var(--green-mid) 100%);
            color: #fff;
            box-shadow: 0 2px 10px rgba(61, 90, 46, .28), inset 0 1px 0 rgba(255, 255, 255, .12);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--green-mid) 0%, #6a8f4e 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(61, 90, 46, .32), inset 0 1px 0 rgba(255, 255, 255, .12);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-ghost {
            background: var(--white);
            color: var(--text-secondary);
            border: 1.5px solid var(--border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
        }

        .btn-ghost:hover {
            border-color: var(--green-light);
            color: var(--green-dark);
            background: var(--green-ghost);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, .06);
        }

        .btn-danger-ghost {
            background: var(--white);
            color: var(--red);
            border: 1.5px solid #eecece;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
        }

        .btn-danger-ghost:hover {
            background: var(--red-pale);
            border-color: var(--red);
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(185, 64, 64, .1);
        }

        /* Stats */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            transition: box-shadow .22s, transform .22s;
            position: relative;
            overflow: hidden;
            animation: cardReveal .5s ease both;
        }

        .stat-card:nth-child(1) {
            animation-delay: .06s;
        }

        .stat-card:nth-child(2) {
            animation-delay: .12s;
        }

        .stat-card:nth-child(3) {
            animation-delay: .18s;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 3px 3px 0 0;
            opacity: 0;
            transition: opacity .22s;
        }

        .stat-card:nth-child(1)::before {
            background: linear-gradient(90deg, var(--green-dark), var(--green-light));
        }

        .stat-card:nth-child(2)::before {
            background: linear-gradient(90deg, #b86e28, var(--amber));
        }

        .stat-card:nth-child(3)::before {
            background: linear-gradient(90deg, #2755b5, #5b82e8);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
            transition: transform .2s;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.08) rotate(-3deg);
        }

        .stat-icon.green {
            background: var(--green-pale);
            color: var(--green-dark);
        }

        .stat-icon.amber {
            background: var(--amber-pale);
            color: var(--amber);
        }

        .stat-icon.blue {
            background: #eaf0fd;
            color: #3b66c4;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            font-family: 'Fraunces', serif;
            letter-spacing: -.5px;
        }

        .stat-label {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        /* Section cards */
        .section-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: box-shadow .22s, transform .22s;
            animation: cardReveal .5s ease both;
        }

        .section-card:nth-child(1) {
            animation-delay: .1s;
        }

        .section-card:nth-child(2) {
            animation-delay: .16s;
        }

        .section-card:nth-child(3) {
            animation-delay: .22s;
        }

        .section-card:nth-child(4) {
            animation-delay: .28s;
        }

        .section-card:nth-child(5) {
            animation-delay: .34s;
        }

        .section-card:nth-child(6) {
            animation-delay: .4s;
        }

        .section-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .section-card.full {
            grid-column: 1 / -1;
        }

        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(to bottom, var(--green-ghost), var(--white));
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .section-title i {
            width: 27px;
            height: 27px;
            border-radius: 7px;
            background: linear-gradient(135deg, var(--green-pale), #dcebd0);
            color: var(--green-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            box-shadow: 0 1px 3px rgba(61, 90, 46, .12);
        }

        .section-body {
            padding: 20px;
        }

        /* Info rows */
        .info-list {
            display: flex;
            flex-direction: column;
        }

        .info-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 11px 0;
            border-bottom: 1px solid var(--green-ghost);
            transition: background .12s;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row:first-child {
            padding-top: 0;
        }

        .info-label {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            white-space: nowrap;
            min-width: 110px;
            padding-top: 1px;
        }

        .info-value {
            font-size: 13.5px;
            color: var(--text-primary);
            font-weight: 500;
            text-align: right;
            line-height: 1.55;
        }

        .info-value a {
            color: var(--green-mid);
            text-decoration: none;
            transition: color .12s;
        }

        .info-value a:hover {
            text-decoration: underline;
            color: var(--green-dark);
        }

        .info-value.muted {
            color: var(--text-muted);
            font-style: italic;
            font-weight: 400;
        }

        /* Tags */
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .tag {
            padding: 4px 11px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background: var(--green-pale);
            color: var(--green-dark);
            border: 1px solid var(--border);
            transition: background .14s, border-color .14s, transform .14s;
        }

        .tag:hover {
            background: #d0e3c2;
            border-color: var(--green-light);
            transform: translateY(-1px);
        }

        /* Social links */
        .social-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all .16s;
        }

        .social-link.twitter {
            background: #e8f4fd;
            color: #1a8cd8;
            border: 1px solid #c8e6fb;
        }

        .social-link.twitter:hover {
            background: #1a8cd8;
            color: #fff;
        }

        .social-link.linkedin {
            background: #e8f0fc;
            color: #0a66c2;
            border: 1px solid #c8d8f6;
        }

        .social-link.linkedin:hover {
            background: #0a66c2;
            color: #fff;
        }

        /* Bio */
        .bio-text {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.85;
            padding: 16px 18px;
            background: var(--green-ghost);
            border-radius: var(--radius-md);
            border-left: 3px solid var(--green-mid);
            font-style: italic;
            position: relative;
        }

        .bio-text::before {
            content: '\201C';
            position: absolute;
            top: 8px;
            left: 18px;
            font-size: 48px;
            font-family: 'Fraunces', serif;
            color: var(--green-pale);
            line-height: 1;
            pointer-events: none;
        }

        /* Bullet list */
        .bullet-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .bullet-list li {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .bullet-list li::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green-mid);
            flex-shrink: 0;
            margin-top: 6px;
        }

        /* Sub-headings */
        .sub-heading {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-top: 16px;
            margin-bottom: 10px;
            padding-bottom: 7px;
            border-bottom: 1px solid var(--border);
        }

        .sub-heading:first-child {
            margin-top: 0;
        }

        .sub-heading i {
            color: var(--green-light);
        }

        /* Exp bar */
        .exp-bar-wrap {
            margin-top: 14px;
        }

        .exp-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .exp-bar {
            height: 5px;
            border-radius: 3px;
            background: var(--green-pale);
            overflow: hidden;
        }

        .exp-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--green-dark), var(--green-light));
            width: 0;
            animation: barGrow .9s ease forwards;
            animation-delay: .4s;
        }

        @keyframes barGrow {
            to {
                width: 75%;
            }
        }

        /* Private */
        .section-card.private {
            border-color: #f0d5d5;
        }

        .section-card.private .section-head {
            background: linear-gradient(to bottom, var(--red-pale), var(--white));
            border-bottom-color: #f0d5d5;
        }

        .section-card.private .section-title i {
            background: linear-gradient(135deg, #fde8e8, #f9d0d0);
            color: var(--red);
            box-shadow: 0 1px 3px rgba(185, 64, 64, .12);
        }

        .section-card.private .section-title {
            color: var(--red);
        }

        .private-notice {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 12px 14px;
            background: #fff8f8;
            border: 1px solid #f0d5d5;
            border-radius: var(--radius-sm);
            margin-bottom: 18px;
            font-size: 12.5px;
            color: #7a3535;
            line-height: 1.55;
        }

        .private-notice i {
            color: var(--red);
            margin-top: 1px;
            flex-shrink: 0;
        }

        .private-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #fde8e8;
            color: var(--red);
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            border-radius: 12px;
            border: 1px solid #f0d5d5;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10, 18, 8, .55);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px;
            overflow-y: auto;
        }

        .modal-overlay.active {
            display: flex;
            animation: fadein .22s ease;
        }

        @keyframes fadein {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: 0 24px 72px rgba(0, 0, 0, .28), 0 4px 16px rgba(0, 0, 0, .1);
            width: 100%;
            max-width: 700px;
            margin: auto;
            animation: slideup .32s cubic-bezier(.16, 1, .3, 1);
            overflow: hidden;
        }

        @keyframes slideup {
            from {
                opacity: 0;
                transform: translateY(32px) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 26px;
            background: linear-gradient(to bottom, var(--green-ghost), var(--white));
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 {
            font-family: 'Fraunces', serif;
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .modal-header h2 i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--green-dark), var(--green-mid));
            color: #fff;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(61, 90, 46, .25);
        }

        .modal-close {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: 1.5px solid var(--border);
            background: var(--white);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: all .16s;
        }

        .modal-close:hover {
            background: #fde8e8;
            color: var(--red);
            border-color: #f0d5d5;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 26px;
            max-height: calc(100vh - 240px);
            overflow-y: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 2px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            padding: 18px 26px;
            border-top: 1px solid var(--border);
            background: var(--green-ghost);
        }

        .form-section {
            margin-bottom: 22px;
        }

        .form-section-head {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .55px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--green-ghost);
        }

        .form-section-head i {
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, var(--green-pale), #dcebd0);
            color: var(--green-dark);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9.5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .45px;
        }

        .form-input,
        .form-textarea {
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-family: inherit;
            color: var(--text-primary);
            background: var(--white);
            transition: border-color .16s, box-shadow .16s, background .16s;
            outline: none;
        }

        .form-input:hover,
        .form-textarea:hover {
            border-color: var(--green-light);
            background: #fafcf8;
        }

        .form-input:focus,
        .form-textarea:focus {
            border-color: var(--green-mid);
            box-shadow: 0 0 0 3.5px rgba(92, 122, 66, .13);
            background: var(--white);
        }

        .form-textarea {
            resize: vertical;
            min-height: 84px;
            line-height: 1.65;
        }

        .avatar-upload-row {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 16px;
            background: linear-gradient(to right, var(--green-ghost), #f8fbf5);
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
            margin-bottom: 22px;
        }

        .avatar-preview {
            width: 68px;
            height: 68px;
            border-radius: var(--radius-md);
            border: 2px solid var(--border);
            flex-shrink: 0;
            background: linear-gradient(145deg, var(--green-dark), var(--green-mid), var(--green-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            font-family: 'Fraunces', serif;
            background-size: cover;
            background-position: center;
            box-shadow: 0 2px 10px rgba(61, 90, 46, .18);
        }

        .avatar-upload-info {
            flex: 1;
        }

        .avatar-upload-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .avatar-upload-hint {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 26px;
            right: 26px;
            background: #1a2716;
            color: #fff;
            padding: 13px 18px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 9999;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .22), 0 2px 8px rgba(0, 0, 0, .12);
            animation: slideInRight .3s cubic-bezier(.16, 1, .3, 1);
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, .08);
            min-width: 200px;
        }

        .toast.success {
            border-left: 3px solid #4ade80;
        }

        .toast.error {
            border-left: 3px solid #f87171;
        }

        .toast.success i {
            color: #4ade80;
        }

        .toast.error i {
            color: #f87171;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive */
        @media (max-width: 960px) {
            .sidebar {
                transform: translateX(-260px);
                transition: transform .25s;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
            }

            .page-content {
                padding: 22px 20px 40px;
                width: 100%;
            }
        }

        @media (max-width: 560px) {
            .page-content {
                padding: 14px 14px 36px;
            }

            .hero-body {
                flex-direction: column;
                align-items: flex-start;
                padding: 0 16px 20px;
            }

            .hero-left {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-actions {
                width: 100%;
            }

            .hero-actions .btn {
                flex: 1;
                justify-content: center;
            }

            .stats-strip {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .topbar .search {
                display: none;
            }
        }

        /* ── Loading state ───────────────────────────────────── */
        .dashboard-loading {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--green-ghost);
            z-index: 9000;
            gap: 14px;
        }

        .dashboard-loading p {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid var(--border);
            border-top-color: var(--green-mid);
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Nav error fallback ──────────────────────────────── */
        .nav-error {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: var(--green-ghost);
            gap: 12px;
            text-align: center;
            padding: 32px;
        }

        .nav-error i {
            font-size: 36px;
            color: var(--amber);
        }

        .nav-error h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .nav-error p {
            font-size: 14px;
            color: var(--text-muted);
        }

        .btn-retry {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            background: var(--green-dark);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: background .14s;
        }

        .btn-retry:hover {
            background: var(--green-mid);
        }
    </style>
</head>

<body>
    <?php include 'admin_nav.php'; ?>

    <section class="page-content profile" id="profile-content" style="display: block !important;">

        <nav class="breadcrumb">
            <a href="admin_dashboard.php">Home</a>
            <i class="fas fa-chevron-right"></i>
            <span>Admin Profile</span>
        </nav>

        <!-- Hero -->
        <div class="hero-card">
            <div class="hero-banner">
                <div class="hero-banner-accent"></div>
                <div class="hero-banner-accent2"></div>
            </div>
            <div class="hero-body">
                <div class="hero-left">
                    <div class="hero-avatar-wrap">
                        <div class="hero-avatar" id="heroAvatar" style="<?php echo $avatarStyle; ?>"><?php echo $avatarContent; ?></div>
                    </div>
                    <div class="hero-info">
                        <div class="hero-name" id="heroName"><?php echo h($full_name); ?></div>
                        <div class="hero-role" id="heroRole"><?php echo h($user_role); ?></div>
                        <div class="hero-chips">
                            <?php if ($office): ?><span class="chip"><i class="fas fa-map-marker-alt"></i> <?php echo h($office); ?></span><?php endif; ?>
                            <?php if ($school_phone): ?><span class="chip"><i class="fas fa-phone"></i> <?php echo h($school_phone); ?></span><?php endif; ?>
                            <?php if ($school_email): ?><span class="chip"><i class="fas fa-envelope"></i> <?php echo h($school_email); ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="hero-actions">
                    <button class="btn btn-ghost" onclick="openPrivateModal()"><i class="fas fa-lock"></i> Private Info</button>
                    <button class="btn btn-primary" onclick="openEditModal()"><i class="fas fa-edit"></i> Edit Profile</button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-strip">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-briefcase"></i></div>
                <div>
                    <div class="stat-value"><?php echo $years_exp > 0 ? h($years_exp) . '+' : '—'; ?></div>
                    <div class="stat-label">Years of Experience</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i class="fas fa-certificate"></i></div>
                <div>
                    <div class="stat-value"><?php echo $cert_count ?: '—'; ?></div>
                    <div class="stat-label">Certifications</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-tasks"></i></div>
                <div>
                    <div class="stat-value"><?php echo $resp_count ?: '—'; ?></div>
                    <div class="stat-label">Key Responsibilities</div>
                </div>
            </div>
        </div>

        <!-- Grid -->
        <div class="grid-2">

            <!-- Contact -->
            <div class="section-card">
                <div class="section-head">
                    <h3 class="section-title"><i class="fas fa-address-card"></i> Contact Information</h3>
                </div>
                <div class="section-body">
                    <div class="info-list">
                        <div class="info-row"><span class="info-label">Full Name</span><span class="info-value"><?php echo h($full_name); ?></span></div>
                        <div class="info-row"><span class="info-label">Title</span><span class="info-value"><?php echo h($title); ?></span></div>
                        <div class="info-row"><span class="info-label">Office</span><span class="info-value"><?php echo $office ? h($office) : '<span class="muted">Not provided</span>'; ?></span></div>
                        <div class="info-row"><span class="info-label">School Phone</span><span class="info-value"><?php echo $school_phone ? h($school_phone) : '<span class="muted">Not provided</span>'; ?></span></div>
                        <div class="info-row"><span class="info-label">School Email</span><span class="info-value"><?php echo $school_email ? '<a href="mailto:' . h($school_email) . '">' . h($school_email) . '</a>' : '<span class="muted">Not provided</span>'; ?></span></div>
                        <div class="info-row">
                            <span class="info-label">Social</span>
                            <span class="info-value">
                                <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap;">
                                    <?php if ($twitter_url): ?>
                                        <a class="social-link twitter" href="<?php echo h($twitter_url); ?>" target="_blank"><i class="fab fa-twitter"></i> Twitter</a>
                                    <?php endif; ?>
                                    <?php if ($linkedin_url): ?>
                                        <a class="social-link linkedin" href="<?php echo h($linkedin_url); ?>" target="_blank"><i class="fab fa-linkedin"></i> LinkedIn</a>
                                    <?php endif; ?>
                                    <?php if (!$twitter_url && !$linkedin_url): ?><span class="info-value muted">Not provided</span><?php endif; ?>
                                </div>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Education -->
            <div class="section-card">
                <div class="section-head">
                    <h3 class="section-title"><i class="fas fa-graduation-cap"></i> Education &amp; Credentials</h3>
                </div>
                <div class="section-body">
                    <div class="sub-heading"><i class="fas fa-university"></i> Educational History</div>
                    <ul class="bullet-list">
                        <?php echo $education ? listItems($education) : '<li class="info-value muted">Not provided</li>'; ?>
                    </ul>
                    <div class="sub-heading" style="margin-top:14px;"><i class="fas fa-certificate"></i> Certifications</div>
                    <div class="tag-cloud">
                        <?php echo $certs ? tagItems($certs) : '<span class="info-value muted">Not provided</span>'; ?>
                    </div>
                    <div class="exp-bar-wrap">
                        <div class="exp-bar-label"><span>Career Experience</span><span><?php echo $years_exp > 0 ? h($years_exp) . '+ years' : '—'; ?></span></div>
                        <div class="exp-bar">
                            <div class="exp-bar-fill" style="width:<?php echo min(100, $years_exp * 3); ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Biography -->
            <div class="section-card full">
                <div class="section-head">
                    <h3 class="section-title"><i class="fas fa-align-left"></i> Biography</h3>
                </div>
                <div class="section-body">
                    <p class="bio-text" id="biographyDisplay"><?php echo $biography ? h($biography) : '<span class="info-value muted">No biography provided.</span>'; ?></p>
                </div>
            </div>

            <!-- Responsibilities -->
            <div class="section-card">
                <div class="section-head">
                    <h3 class="section-title"><i class="fas fa-list-check"></i> Key Responsibilities</h3>
                </div>
                <div class="section-body">
                    <ul class="bullet-list" id="responsibilitiesDisplay">
                        <?php echo $responsibilities ? listItems($responsibilities) : '<li class="info-value muted">Not provided</li>'; ?>
                    </ul>
                </div>
            </div>

            <!-- Goals -->
            <div class="section-card">
                <div class="section-head">
                    <h3 class="section-title"><i class="fas fa-bullseye"></i> Leadership Goals</h3>
                </div>
                <div class="section-body">
                    <ul class="bullet-list" id="goalsDisplay">
                        <?php echo $goals ? listItems($goals) : '<li class="info-value muted">Not provided</li>'; ?>
                    </ul>
                </div>
            </div>

            <!-- Mission / Vision / Core Values -->
            <div class="section-card full">
                <div class="section-head">
                    <h3 class="section-title"><i class="fas fa-landmark"></i> Mission, Vision &amp; Core Values</h3>
                    <button class="btn btn-primary" onclick="openMVCModal()"><i class="fas fa-edit"></i> Edit</button>
                </div>
                <div class="section-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                        <div>
                            <div class="sub-heading"><i class="fas fa-bullseye"></i> Mission</div>
                            <p style="font-size:13.5px;color:var(--text-secondary);line-height:1.7;" id="missionDisplay">
                                <?php echo $mission ? nl2br(h($mission)) : '<span class="info-value muted">Not provided</span>'; ?>
                            </p>
                        </div>
                        <div>
                            <div class="sub-heading"><i class="fas fa-eye"></i> Vision</div>
                            <p style="font-size:13.5px;color:var(--text-secondary);line-height:1.7;" id="visionDisplay">
                                <?php echo $vision ? nl2br(h($vision)) : '<span class="info-value muted">Not provided</span>'; ?>
                            </p>
                        </div>
                        <div>
                            <div class="sub-heading"><i class="fas fa-heart"></i> Core Values</div>
                            <div class="tag-cloud" id="coreValuesDisplay">
                                <?php echo $core_values ? tagItems($core_values) : '<span class="info-value muted">Not provided</span>'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Principal Title display -->
            <div class="section-card">
                <div class="section-head">
                    <h3 class="section-title"><i class="fas fa-user-tie"></i> Principal Information</h3>
                </div>
                <div class="section-body">
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?php echo h($full_name); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Principal Title</span>
                            <span class="info-value" id="principalTitleDisplay">
                                <span class="chip"><i class="fas fa-medal"></i> <?php echo h($principal_title); ?></span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Responsibilities</span>
                            <span class="info-value" style="text-align:left;max-width:400px;">
                                <?php echo $responsibilities ? nl2br(h($responsibilities)) : '<span class="muted">Not provided</span>'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Private -->
            <div class="section-card private full">
                <div class="section-head">
                    <h3 class="section-title">
                        <i class="fas fa-shield-alt"></i>
                        Private Administrative Information
                        <span class="private-badge"><i class="fas fa-lock"></i> Restricted</span>
                    </h3>
                    <button class="btn btn-danger-ghost" onclick="openPrivateModal()"><i class="fas fa-edit"></i> Edit Private Info</button>
                </div>
                <div class="section-body">
                    <div class="private-notice">
                        <i class="fas fa-exclamation-triangle"></i>
                        This section contains sensitive personal information. Access is restricted to the administrator only. All data is encrypted and stored securely in compliance with data protection regulations.
                    </div>

                    <div class="sub-heading"><i class="fas fa-user"></i> Personal Details</div>
                    <div class="info-list">
                        <div class="info-row"><span class="info-label">Personal Mobile</span><span class="info-value <?php echo $personal_mobile ? '' : 'muted'; ?>"><?php echo $personal_mobile ? h($personal_mobile) : 'Not provided'; ?></span></div>
                        <div class="info-row"><span class="info-label">Personal Email</span><span class="info-value <?php echo $personal_email ? '' : 'muted'; ?>"><?php echo $personal_email ? h($personal_email) : 'Not provided'; ?></span></div>
                        <div class="info-row"><span class="info-label">Date of Birth</span><span class="info-value <?php echo $date_of_birth ? '' : 'muted'; ?>"><?php echo $date_of_birth ? h(date('F j, Y', strtotime($date_of_birth))) : 'Not provided'; ?></span></div>
                        <div class="info-row"><span class="info-label">Place of Birth</span><span class="info-value <?php echo $place_of_birth ? '' : 'muted'; ?>"><?php echo $place_of_birth ? h($place_of_birth) : 'Not provided'; ?></span></div>
                        <div class="info-row"><span class="info-label">Home Address</span><span class="info-value <?php echo $home_address ? '' : 'muted'; ?>"><?php echo $home_address ? h($home_address) : 'Not provided'; ?></span></div>
                        <div class="info-row"><span class="info-label">Gov. ID / SSN</span><span class="info-value <?php echo $government_id ? '' : 'muted'; ?>"><?php echo $government_id ? '••••••••' : 'Not provided'; ?></span></div>
                    </div>

                    <div class="sub-heading"><i class="fas fa-phone-alt"></i> Emergency Contact</div>
                    <div class="info-list">
                        <div class="info-row"><span class="info-label">Contact Name</span><span class="info-value <?php echo $emerg_name ? '' : 'muted'; ?>"><?php echo $emerg_name ? h($emerg_name) : 'Not provided'; ?></span></div>
                        <div class="info-row"><span class="info-label">Phone</span><span class="info-value <?php echo $emerg_phone ? '' : 'muted'; ?>"><?php echo $emerg_phone ? h($emerg_phone) : 'Not provided'; ?></span></div>
                        <div class="info-row"><span class="info-label">Relationship</span><span class="info-value <?php echo $emerg_rel ? '' : 'muted'; ?>"><?php echo $emerg_rel ? h($emerg_rel) : 'Not provided'; ?></span></div>
                    </div>

                    <div class="sub-heading"><i class="fas fa-university"></i> Financial</div>
                    <div class="info-list">
                        <div class="info-row"><span class="info-label">Bank Account</span><span class="info-value <?php echo $bank_account ? '' : 'muted'; ?>"><?php echo $bank_account ? '••••' . substr($bank_account, -4) : 'Not provided'; ?></span></div>
                    </div>
                </div>
            </div>

        </div><!-- /grid-2 -->

        <!-- ══════════════════════════════════════════════
                 HOMEPAGE CARDS EDITOR
            ══════════════════════════════════════════════ -->

        <section id="homepage-cards-editor" style="margin-top:32px;">
            <div class="section-header" style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                <div style="background:#eef4e8;border-radius:10px;width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-th-large" style="color:#3b975e;font-size:18px;"></i>
                </div>
                <div>
                    <h3 style="margin:0;font-size:18px;font-weight:700;">Homepage Cards Editor</h3>
                    <p style="margin:0;font-size:13px;color:#6c757d;">Edit the info cards and feature cards displayed on the public homepage.</p>
                </div>
            </div>

            <div id="hpc-save-msg" style="display:none;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:600;"></div>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;">
                <?php foreach ($hpc_rows as $hpc): ?>
                    <div class="card" style="border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.07);overflow:hidden;">
                        <!-- Card preview header -->
                        <div style="height:120px;background:#f3f6f0;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;">
                            <?php if (!empty($hpc['image'])): ?>
                                <img src="../<?php echo htmlspecialchars($hpc['image']); ?>" id="hpc-preview-<?php echo $hpc['id']; ?>"
                                    style="width:100%;height:120px;object-fit:cover;" alt="">
                            <?php else: ?>
                                <i class="fas <?php echo htmlspecialchars($hpc['icon'] ?: 'fa-image'); ?>"
                                    id="hpc-preview-<?php echo $hpc['id']; ?>-icon"
                                    style="font-size:40px;color:#b0c9a8;"></i>
                            <?php endif; ?>
                            <span style="position:absolute;top:8px;left:8px;background:rgba(0,0,0,.45);color:#fff;font-size:11px;font-weight:600;padding:3px 8px;border-radius:20px;">
                                <?php echo htmlspecialchars($hpc['card_key']); ?>
                            </span>
                        </div>

                        <!-- Edit form -->
                        <div style="padding:16px;">
                            <form class="hpc-form" data-id="<?php echo $hpc['id']; ?>" enctype="multipart/form-data">
                                <input type="hidden" name="_hpc_action" value="save_homepage_card">
                                <input type="hidden" name="_ajax" value="1">
                                <input type="hidden" name="hpc_id" value="<?php echo $hpc['id']; ?>">

                                <div style="margin-bottom:10px;">
                                    <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Title</label>
                                    <input type="text" name="hpc_title" value="<?php echo htmlspecialchars($hpc['title']); ?>"
                                        style="width:100%;padding:8px 10px;border:1px solid #dde3d5;border-radius:8px;font-size:13px;"
                                        placeholder="Card title">
                                </div>

                                <div style="margin-bottom:10px;">
                                    <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Description</label>
                                    <textarea name="hpc_desc" rows="3"
                                        style="width:100%;padding:8px 10px;border:1px solid #dde3d5;border-radius:8px;font-size:13px;resize:vertical;"
                                        placeholder="Card description"><?php echo htmlspecialchars($hpc['description']); ?></textarea>
                                </div>

                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
                                    <div>
                                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">
                                            <i class="fas fa-icons"></i> Icon <span style="color:#999;font-weight:400;">(FontAwesome)</span>
                                        </label>
                                        <input type="text" name="hpc_icon" value="<?php echo htmlspecialchars($hpc['icon']); ?>"
                                            style="width:100%;padding:8px 10px;border:1px solid #dde3d5;border-radius:8px;font-size:13px;"
                                            placeholder="fa-trophy">
                                    </div>
                                    <div>
                                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">
                                            <i class="fas fa-link"></i> Image Path
                                        </label>
                                        <input type="text" name="hpc_image" value="<?php echo htmlspecialchars($hpc['image']); ?>"
                                            style="width:100%;padding:8px 10px;border:1px solid #dde3d5;border-radius:8px;font-size:13px;"
                                            placeholder="assets/img/...">
                                    </div>
                                </div>

                                <div style="margin-bottom:12px;">
                                    <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">
                                        <i class="fas fa-upload"></i> Upload New Image
                                    </label>
                                    <input type="file" name="hpc_image_file" accept="image/*"
                                        style="font-size:12px;width:100%;"
                                        onchange="previewHpcImage(this, <?php echo $hpc['id']; ?>)">
                                </div>

                                <button type="submit"
                                    style="width:100%;padding:9px;background:#3b975e;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:.2s;"
                                    onmouseover="this.style.background='#2d7a4e'" onmouseout="this.style.background='#3b975e'">
                                    <i class="fas fa-save"></i> Save Card
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($hpc_rows)): ?>
                    <div style="grid-column:1/-1;text-align:center;padding:40px;color:#999;">
                        <i class="fas fa-th-large" style="font-size:40px;display:block;margin-bottom:12px;color:#ddd;"></i>
                        Homepage cards table is empty. Run the <code>index.php</code> page once to auto-create the defaults.
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </section><!-- end #profile-content -->


    <!-- ════════════════════════════════════════════════════
     EDIT PROFILE MODAL
════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="editProfileModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> Edit Profile</h2>
                <button class="modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">

                <div class="avatar-upload-row">
                    <div class="avatar-preview" id="avatarPreview" style="<?php echo $avatarStyle; ?>"><?php echo $avatarContent; ?></div>
                    <div class="avatar-upload-info">
                        <div class="avatar-upload-name">Profile Photo</div>
                        <div class="avatar-upload-hint">JPG or PNG, max 2MB. Square images work best.</div>
                        <label for="profileImageInput">
                            <span class="btn btn-ghost" style="font-size:12px;padding:7px 14px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                                <i class="fas fa-upload"></i> Upload Photo
                            </span>
                        </label>
                        <input type="file" id="profileImageInput" accept="image/*" style="display:none;" onchange="previewImage(this)">
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head"><i class="fas fa-user"></i> Basic Information</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input class="form-input" type="text" name="full_name" id="modal_full_name" value="<?php echo h($full_name); ?>" />
                        </div>
                        <?php if ($user_type === 'admin'): ?>
                            <div class="form-group">
                                <label class="form-label">Professional Title</label>
                                <input class="form-input" type="text" name="title" value="<?php echo h($title); ?>" />
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($user_type === 'admin'): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Principal's Title (Official Rank)</label>
                                <select class="form-input" name="principal_title">
                                    <?php foreach (['Principal I', 'Principal II', 'Principal III', 'Principal IV'] as $pt): ?>
                                        <option value="<?php echo $pt; ?>" <?php echo $principal_title === $pt ? 'selected' : ''; ?>><?php echo $pt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Photo</label>
                                <label for="profileImageInput" style="cursor:pointer;">
                                    <span class="btn btn-ghost" style="font-size:12px;padding:7px 14px;display:inline-flex;align-items:center;gap:6px;width:100%;">
                                        <i class="fas fa-upload"></i> Upload Photo
                                    </span>
                                </label>
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Biography</label>
                                <textarea class="form-textarea" name="biography" rows="4"><?php echo h($biography); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Principal's Responsibilities</label>
                                <textarea class="form-textarea" name="responsibilities" rows="4" placeholder="Describe the principal's responsibilities (one per line)..."><?php echo h($responsibilities); ?></textarea>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($user_type === 'admin'): ?>
                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-address-book"></i> Contact Details</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Office Location</label>
                                <input class="form-input" type="text" name="office_location" value="<?php echo h($office); ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">School Phone</label>
                                <input class="form-input" type="tel" name="school_phone" value="<?php echo h($school_phone); ?>" />
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">School Email</label>
                                <input class="form-input" type="email" name="school_email" value="<?php echo h($school_email); ?>" />
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-graduation-cap"></i> Education &amp; Credentials</div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Educational History (one per line)</label>
                                <textarea class="form-textarea" name="education_history" rows="3"><?php echo h($education); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Professional Certifications (one per line)</label>
                                <textarea class="form-textarea" name="certifications" rows="3"><?php echo h($certs); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Years of Experience</label>
                                <input class="form-input" type="number" name="years_experience" value="<?php echo h($years_exp); ?>" style="max-width:150px;" min="0" max="99" />
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-share-alt"></i> Social Media</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Twitter / X URL</label>
                                <input class="form-input" type="url" name="twitter_url" value="<?php echo h($twitter_url); ?>" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">LinkedIn URL</label>
                                <input class="form-input" type="url" name="linkedin_url" value="<?php echo h($linkedin_url); ?>" />
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-head"><i class="fas fa-tasks"></i> Goals</div>
                        <div class="form-row full">
                            <div class="form-group">
                                <label class="form-label">Leadership Goals (one per line)</label>
                                <textarea class="form-textarea" name="leadership_goals" rows="4"><?php echo h($goals); ?></textarea>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" id="saveProfileBtn" onclick="saveProfile()"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </div>
    </div>


    <!-- ════════════════════════════════════════════════════
     EDIT PRIVATE INFO MODAL
════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="editPrivateModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2><i class="fas fa-shield-alt"></i> Edit Private Information</h2>
                <button class="modal-close" onclick="closePrivateModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="private-notice">
                    <i class="fas fa-exclamation-triangle"></i>
                    Sensitive data. Changes are logged for security compliance. Ensure all information is accurate before saving.
                </div>

                <div class="form-section">
                    <div class="form-section-head"><i class="fas fa-user"></i> Personal Details</div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Personal Mobile</label><input class="form-input" type="tel" name="personal_mobile" placeholder="+63 912 345 6789" value="<?php echo h($personal_mobile); ?>" /></div>
                        <div class="form-group"><label class="form-label">Personal Email</label><input class="form-input" type="email" name="personal_email" placeholder="personal@email.com" value="<?php echo h($personal_email); ?>" /></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Date of Birth</label><input class="form-input" type="date" name="date_of_birth" value="<?php echo h($date_of_birth); ?>" /></div>
                        <div class="form-group"><label class="form-label">Place of Birth</label><input class="form-input" type="text" name="place_of_birth" placeholder="City, Province" value="<?php echo h($place_of_birth); ?>" /></div>
                    </div>
                    <div class="form-row full">
                        <div class="form-group"><label class="form-label">Home Address</label><textarea class="form-textarea" name="home_address" rows="2" placeholder="Street, Barangay, City, Province"><?php echo h($home_address); ?></textarea></div>
                    </div>
                    <div class="form-row full">
                        <div class="form-group"><label class="form-label">Government ID / SSN</label><input class="form-input" type="password" name="government_id" placeholder="••••••••" value="<?php echo h($government_id); ?>" /></div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head"><i class="fas fa-phone-alt"></i> Emergency Contact</div>
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Contact Name</label><input class="form-input" type="text" name="emergency_contact_name" placeholder="Full name" value="<?php echo h($emerg_name); ?>" /></div>
                        <div class="form-group"><label class="form-label">Contact Phone</label><input class="form-input" type="tel" name="emergency_contact_phone" placeholder="Phone number" value="<?php echo h($emerg_phone); ?>" /></div>
                    </div>
                    <div class="form-row full">
                        <div class="form-group"><label class="form-label">Relationship</label><input class="form-input" type="text" name="emergency_relationship" placeholder="e.g. Spouse, Parent, Sibling" value="<?php echo h($emerg_rel); ?>" /></div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head"><i class="fas fa-university"></i> Financial</div>
                    <div class="form-row full">
                        <div class="form-group"><label class="form-label">Bank Account Information</label><input class="form-input" type="password" name="bank_account" placeholder="Account number" value="<?php echo h($bank_account); ?>" /></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closePrivateModal()">Cancel</button>
                <button class="btn btn-primary" onclick="savePrivate()"><i class="fas fa-save"></i> Save Private Info</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════
     MISSION / VISION / CORE VALUES MODAL
════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="mvcModal">
        <div class="modal-card">
            <div class="modal-header">
                <h2><i class="fas fa-landmark"></i> Edit Mission, Vision &amp; Core Values</h2>
                <button class="modal-close" onclick="closeMVCModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-section">
                    <div class="form-section-head"><i class="fas fa-bullseye"></i> Mission</div>
                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label">Mission Statement</label>
                            <textarea class="form-textarea" name="mission" rows="5" id="mvc_mission"><?php echo h($mission); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-head"><i class="fas fa-eye"></i> Vision</div>
                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label">Vision Statement</label>
                            <textarea class="form-textarea" name="vision" rows="5" id="mvc_vision"><?php echo h($vision); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-head"><i class="fas fa-heart"></i> Core Values</div>
                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label">Core Values (one per line — shown as tags)</label>
                            <textarea class="form-textarea" name="core_values" rows="5" id="mvc_core_values" placeholder="Maka-Diyos&#10;Maka-tao&#10;Makakalikasan&#10;Makabansa"><?php echo h($core_values); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeMVCModal()">Cancel</button>
                <button class="btn btn-primary" id="saveMVCBtn" onclick="saveMVC()"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>


    <script>
        /* ── Load Navigation via fetch (same as original admin_profile.php) ── */
        function loadNavigation() {
            const container = document.getElementById('navigation-container');

            fetch('./admin_nav.php')
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                    initializeNavigation();
                    // Reveal page content after nav is injected
                    document.getElementById('profile-content').style.display = 'block';
                })
                .catch(error => {
                    console.error('Navigation error:', error);
                    container.innerHTML = `
          <div class="nav-error">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Unable to Load Navigation</h3>
            <p>There was a problem loading the navigation menu.</p>
            <button class="btn-retry" onclick="loadNavigation()">
              <i class="fas fa-redo"></i> Try Again
            </button>
          </div>
        `;
                });
        }

        /* ── Initialize navigation after injection ─────────────── */
        function initializeNavigation() {
            // Move .main's page content inside .main after nav injects its own .main wrapper
            const mainDiv = document.querySelector('.main');
            const pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent) {
                mainDiv.appendChild(pageContent);
            }

            // Fire any dropdown initializer the nav provides
            if (typeof window.initializeNavigationDropdowns === 'function') {
                window.initializeNavigationDropdowns();
            }

            // Mark profile item active in the injected nav
            document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
        }

        // Kick off on page load
        // loadNavigation(); // Disabled - navigation is included via PHP

        /* ── Modals ──────────────────────────────────────────────── */
        function openEditModal() {
            document.getElementById('editProfileModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editProfileModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function openPrivateModal() {
            document.getElementById('editPrivateModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePrivateModal() {
            document.getElementById('editPrivateModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function openMVCModal() {
            document.getElementById('mvcModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMVCModal() {
            document.getElementById('mvcModal').classList.remove('active');
            document.body.style.overflow = '';
        }
        async function saveMVC() {
            const btn = document.getElementById('saveMVCBtn');
            const fd = new FormData();
            fd.append('mission', document.getElementById('mvc_mission').value);
            fd.append('vision', document.getElementById('mvc_vision').value);
            fd.append('core_values', document.getElementById('mvc_core_values').value);
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
            btn.disabled = true;
            try {
                const res = await fetch('update_admin_profile.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    closeMVCModal();
                    showToast('Mission, Vision & Core Values updated!', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message || 'Save failed.', 'error');
                }
            } catch (e) {
                showToast('Network error.', 'error');
            } finally {
                btn.innerHTML = '<i class="fas fa-save"></i> Save';
                btn.disabled = false;
            }
        }

        document.querySelectorAll('.modal-overlay').forEach(o => {
            o.addEventListener('click', e => {
                if (e.target === o) {
                    closeEditModal();
                    closePrivateModal();
                }
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeEditModal();
                closePrivateModal();
            }
        });

        /* ── Image preview ───────────────────────────────────────── */
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    const el = document.getElementById('avatarPreview');
                    el.style.backgroundImage = `url(${e.target.result})`;
                    el.style.backgroundSize = 'cover';
                    el.style.backgroundPosition = 'center';
                    el.textContent = '';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        /* ── Save handlers ───────────────────────────────────────── */
        async function saveProfile() {
            const btn = document.getElementById('saveProfileBtn');
            const modal = document.getElementById('editProfileModal');

            // Gather form data from all named inputs/textareas inside the modal
            const formData = new FormData();
            modal.querySelectorAll('[name]').forEach(el => {
                formData.append(el.name, el.value);
            });

            // Attach image file if selected
            const imgFile = document.getElementById('profileImageInput').files[0];
            if (imgFile) formData.append('profile_image', imgFile);

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
            btn.disabled = true;

            try {
                const res = await fetch('update_admin_profile.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    closeEditModal();
                    showToast('Profile updated successfully!', 'success');
                    // Soft-update hero without full reload
                    const name = formData.get('full_name');
                    document.getElementById('heroName').textContent = name;
                    document.getElementById('heroRole').textContent = formData.get('title');
                    if (data.profile_image) {
                        const heroAv = document.getElementById('heroAvatar');
                        const profilePath = '<?php echo $profile_path; ?>' + data.profile_image;
                        // Use absolute path from root
                        heroAv.style.backgroundImage = `url(${profilePath})`;
                        heroAv.style.backgroundSize = 'cover';
                        heroAv.style.backgroundPosition = 'center';
                        heroAv.textContent = '';
                    }
                    // Reload after short delay so sections refresh from server
                    setTimeout(() => location.reload(), 900);
                } else {
                    showToast(data.message || 'Save failed. Please try again.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error. Please try again.', 'error');
            } finally {
                btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                btn.disabled = false;
            }
        }

        async function savePrivate() {
            const modal = document.getElementById('editPrivateModal');
            const btn = modal.querySelector('.btn-primary');

            const formData = new FormData();
            modal.querySelectorAll('[name]').forEach(el => {
                formData.append(el.name, el.value);
            });

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
            btn.disabled = true;

            try {
                const res = await fetch('update_admin_private.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    closePrivateModal();
                    showToast('Private information updated!', 'success');
                    setTimeout(() => location.reload(), 900);
                } else {
                    showToast(data.message || 'Save failed. Please try again.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error. Please try again.', 'error');
            } finally {
                btn.innerHTML = '<i class="fas fa-save"></i> Save Private Info';
                btn.disabled = false;
            }
        }

        /* ── Toast ───────────────────────────────────────────────── */
        function showToast(msg, type) {
            document.querySelectorAll('.toast').forEach(t => t.remove());
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle'
            };
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<i class="fas fa-${icons[type]}"></i> ${msg}`;
            t.onclick = () => t.remove();
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 4000);
        }
    </script>

    <!-- ── Homepage Cards Editor JS ── -->
    <script>
        // Preview selected image before upload
        function previewHpcImage(input, cardId) {
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                // Try to update the preview img if it exists
                const img = document.getElementById('hpc-preview-' + cardId);
                if (img && img.tagName === 'IMG') {
                    img.src = e.target.result;
                } else {
                    // Replace icon with img
                    const ico = document.getElementById('hpc-preview-' + cardId + '-icon');
                    if (ico) {
                        const newImg = document.createElement('img');
                        newImg.id = 'hpc-preview-' + cardId;
                        newImg.src = e.target.result;
                        newImg.style.cssText = 'width:100%;height:120px;object-fit:cover;';
                        ico.replaceWith(newImg);
                    }
                }
            };
            reader.readAsDataURL(input.files[0]);
        }

        // AJAX submit for each homepage card form
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.hpc-form').forEach(function(form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const btn = form.querySelector('button[type="submit"]');
                    const msgBox = document.getElementById('hpc-save-msg');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

                    try {
                        const fd = new FormData(form);
                        const res = await fetch('admin_profile.php', {
                            method: 'POST',
                            body: fd
                        });
                        const data = await res.json();

                        if (data.success) {
                            msgBox.style.display = 'block';
                            msgBox.style.background = '#d4edda';
                            msgBox.style.color = '#155724';
                            msgBox.innerHTML = '<i class="fas fa-check-circle"></i> Card saved successfully!';
                            // Update the image path input if a file was uploaded
                            if (data.image) {
                                const imgInput = form.querySelector('input[name="hpc_image"]');
                                if (imgInput) imgInput.value = data.image;
                            }
                        } else {
                            msgBox.style.display = 'block';
                            msgBox.style.background = '#f8d7da';
                            msgBox.style.color = '#721c24';
                            msgBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> Save failed. Please try again.';
                        }
                        setTimeout(() => {
                            msgBox.style.display = 'none';
                        }, 4000);
                    } catch (err) {
                        msgBox.style.display = 'block';
                        msgBox.style.background = '#f8d7da';
                        msgBox.style.color = '#721c24';
                        msgBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error. Please try again.';
                        setTimeout(() => {
                            msgBox.style.display = 'none';
                        }, 4000);
                    }

                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Save Card';
                });
            });
        });
    </script>

    <script>
        // Move page-content into .main (opened by admin_nav.php) — same pattern as admin_dashboard.php
        (function() {
            var mainDiv = document.querySelector('.main');
            var pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent && !mainDiv.contains(pageContent)) {
                mainDiv.appendChild(pageContent);
            }
        })();
    </script>
</body>

</html>