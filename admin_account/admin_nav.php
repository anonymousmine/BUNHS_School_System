<?php

/**
 * admin_nav.php  —  Topbar + Sidebar
 * ─────────────────────────────────────────────────────────────
 * Works correctly from ANY directory depth inside admin_account/
 * Includes:
 *   • Functional bell / envelope / user dropdowns
 *   • Dynamic sidebar counts (forms, admins)
 *   • "NEW" badges driven by admin_notifications table
 *   • Chatbox unread message count
 *   • Auto-clears "NEW" badge when admin visits that module
 * ─────────────────────────────────────────────────────────────
 */

// ── 0. Embed mode ─────────────────────────────────────────────
// ?embed=html  → full HTML (default, same as direct include)
// ?embed=json  → JSON-only: counts, badge states, API URLs
//   Call from JS: fetch('/admin_account/admin_nav.php?embed=json')
$_embed = $_GET['embed'] ?? 'html';
if ($_embed === 'json') {
    // We still need the DB counts, so we continue to section 4.
    // Output will happen at the very end of this file when embed=json.
}

// ── 1. Resolve base paths (directory-depth-safe) ──────────────
// Uses __FILE__ + realpath so this works from ANY subdirectory.

$_nav_admin_root = str_replace('\\', '/', realpath(__DIR__));

// Walk up the tree until we land on the admin_account folder
$_walk  = $_nav_admin_root;
$_guard = 6;
while ($_guard-- > 0 && basename($_walk) !== 'admin_account' && dirname($_walk) !== $_walk) {
    $_walk = dirname($_walk);
}
$_admin_fs_root = (basename($_walk) === 'admin_account') ? $_walk : $_nav_admin_root;

// Build web-accessible $adminBase / $assetsBase from REQUEST_URI (for <a href>)
$_uri_parts  = explode('/', $_SERVER['REQUEST_URI']);
$_ai         = array_search('admin_account', $_uri_parts);

if ($_ai !== false) {
    $adminBase  = '/BUNHS_School_System/admin_account/';
    $assetsBase = '/BUNHS_School_System/';
} else {
    $adminBase  = '/BUNHS_School_System/admin_account/';
    $assetsBase = '/BUNHS_School_System/';
}

// AJAX API URLs (always inside admin_account/)
$notifApiPath = $adminBase . 'notification_api.php';
$chatApiPath  = $adminBase . 'chat_api.php';
$chatboxPath  = $adminBase . 'admin_chatbox.php';

// ── 2. DB connection guard ────────────────────────────────────
if (!isset($conn) || !($conn instanceof mysqli)) {
    $dbPath = dirname($_admin_fs_root) . '/db_connection.php';
    if (file_exists($dbPath)) {
        include_once $dbPath;
    }
}

// ── 3. Detect current module for auto-clear of "NEW" badge ───
$_current_page = basename($_SERVER['PHP_SELF'], '.php');
$_module_map   = [
    'forms'               => 'forms',
    'create_announcement' => 'announcements',
    'create_new'          => 'news',
    'admins'              => 'admins',
];
$_active_module = $_module_map[$_current_page] ?? null;

// ── 4. All sidebar counts in one pass ────────────────────────
$_counts = [
    'admins'   => 0,
    'forms'    => 0,
    'chat'     => 0,
];
$_new_modules = [];

if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {

    // Helper: run a COUNT query safely — returns 0 if table doesn't exist
    $__safe_count = function (string $sql) use ($conn): int {
        $prev = $conn->errno;
        $r    = @$conn->query($sql);          // suppress PHP warning
        if (!$r || $conn->errno) {
            $conn->errno && $conn->query('SELECT 1'); // reset conn error state
            return 0;
        }
        $v = $r->fetch_assoc();
        return (int)($v['c'] ?? $v['total'] ?? 0);
    };

    // Sub-admin count
    $_counts['admins'] = $__safe_count(
        "SELECT COUNT(*) AS c FROM sub_admin WHERE status = 'approved'"
    );

    // Forms — try common table names, fall back to 0 gracefully
    foreach (['document_requests', 'form_requests', 'clearance_forms', 'forms'] as $_ft) {
        $n = $__safe_count("SELECT COUNT(*) AS c FROM `{$_ft}`");
        if ($n > 0 || $conn->query("SHOW TABLES LIKE '{$_ft}'")->num_rows > 0) {
            $_counts['forms'] = $n;
            break;
        }
    }

    // Unread student chat messages
    $_counts['chat'] = $__safe_count(
        "SELECT COUNT(*) AS c FROM chat_messages
         WHERE sender_role = 'student' AND is_read = 0"
    );

    // Auto-clear "NEW" badge for current module
    if ($_active_module && $conn->ping()) {
        $stmt = $conn->prepare(
            "UPDATE admin_notifications SET is_read = 1
             WHERE edited_module = ? AND is_read = 0"
        );
        if ($stmt) {
            $stmt->bind_param('s', $_active_module);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Which modules still have unread notifications?
    if ($conn->ping()) {
        $r = $conn->query(
            "SELECT DISTINCT edited_module FROM admin_notifications WHERE is_read = 0"
        );
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $_new_modules[] = strtolower($row['edited_module']);
            }
        }
    }
}

// ── 5. Display helpers ────────────────────────────────────────
function _nav_fmt_count(int $n): string
{
    if ($n >= 1000) return round($n / 1000, 1) . 'k';
    return (string)$n;
}
function _nav_has_new(array $mods, string $key): bool
{
    return in_array(strtolower($key), $mods, true);
}

// ── 6. User data helper ────────────────────────────────────────
if (!function_exists('roleLabel')) {
    function roleLabel($role)
    {
        $map = [
            'news_admin'         => 'News Admin',
            'announcement_admin' => 'Announcement Admin',
            'super_sub_admin'    => 'Super Sub-Admin',
            'forms_admin'        => 'Forms Admin',
        ];
        $roles = array_map('trim', explode(',', $role ?? ''));
        $labels = array_map(fn($r) => $map[$r] ?? ucfirst(str_replace('_', ' ', $r)), $roles);
        return implode(', ', $labels);
    }
}

function _get_user_data(mysqli $conn): array
{
    $user_data = [
        'name' => $_SESSION['username'] ?? 'Admin',
        'photo' => 'assets/img/person/school head.jpg',
        'photo_type' => 'default',
        'role' => 'Administrator',
        'user_type' => $_SESSION['user_type'] ?? 'admin',
        'principal_title' => ''
    ];

    $user_id = (int)($_SESSION['user_id'] ?? 0);
    $user_type = $_SESSION['user_type'] ?? 'admin';

    if ($user_id > 0 && $conn->ping()) {
        if ($user_type === 'admin') {
            // Fetch from admin table
            $stmt = $conn->prepare(
                "SELECT full_name, profile_image, principal_title FROM admin WHERE id = ? LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $admin = $result->fetch_assoc();
                $stmt->close();

                if ($admin) {
                    $user_data['name'] = $admin['full_name'] ?? $user_data['name'];
                    if (!empty($admin['profile_image'])) {
                        $photo_path = 'uploads/admin_profiles/' . $admin['profile_image'];
                        // Check if file exists before using it
                        if (file_exists(__DIR__ . '/../' . $photo_path)) {
                            $user_data['photo'] = $photo_path;
                        }
                        // Also set profile_image for use in admin_profile.php
                        $user_data['profile_image'] = $admin['profile_image'];
                    }
                    $user_data['principal_title'] = $admin['principal_title'] ?? '';
                    $user_data['role'] = !empty($user_data['principal_title'])
                        ? $user_data['principal_title']
                        : 'Administrator';
                    // Admins use 'upload' type for profile photos
                    $user_data['photo_type'] = !empty($admin['profile_image']) ? 'upload' : 'default';
                }
            }
        } elseif ($user_type === 'sub-admin') {
            // Fetch from sub_admin table
            $stmt = $conn->prepare(
                "SELECT full_name, role, registration_method, profile_picture_url, profile_picture_type, profile_image FROM sub_admin WHERE id = ? LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $sub_admin = $result->fetch_assoc();
                $stmt->close();

                if ($sub_admin) {
                    $user_data['name'] = $sub_admin['full_name'] ?? $user_data['name'];

                    // Handle profile picture based on registration method and type
                    $profile_type = $sub_admin['profile_picture_type'] ?? 'icon';
                    $profile_url = $sub_admin['profile_picture_url'] ?? '';
                    $profile_img = $sub_admin['profile_image'] ?? '';

                    if ($profile_type === 'gmail' && !empty($profile_url)) {
                        // User registered with Gmail and has a profile URL
                        $user_data['photo'] = $profile_url;
                        $user_data['photo_type'] = 'external';
                    } elseif ($profile_type === 'upload' && !empty($profile_img)) {
                        // User uploaded custom profile picture
                        $photo_path = 'uploads/sub_admin_profiles/' . $profile_img;
                        // Check if file exists before using it
                        if (file_exists(__DIR__ . '/../' . $photo_path)) {
                            $user_data['photo'] = $photo_path;
                            $user_data['photo_type'] = 'upload';
                        } else {
                            // File doesn't exist, fallback to icon
                            $user_data['photo'] = 'icon';
                            $user_data['photo_type'] = 'icon';
                        }
                        // Also set profile_image for use in admin_profile.php
                        $user_data['profile_image'] = $profile_img;
                    } else {
                        // Default to icon for phone registration or no profile picture
                        $user_data['photo'] = 'icon';
                        $user_data['photo_type'] = 'icon';
                    }

                    // Use roleLabel function to format role
                    $user_data['role'] = roleLabel($sub_admin['role'] ?? 'news_admin');
                }
            }
        }
    }

    return $user_data;
}

// Get user data for use in navigation
$current_user = _get_user_data($conn);

// ── CSRF Token Generation ───────────────────────────────────────────
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_expires'] = time() + 1800; // 30 minutes
        }

        return $_SESSION['csrf_token'] ?? '';
    }
}
?>

<?php
// ── JSON embed mode: output config + counts and exit early ────
if ($_embed === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success'      => true,
        'counts'       => $_counts,
        'new_modules'  => $_new_modules,
        'api'          => [
            'notif'   => $notifApiPath,
            'chat'    => $chatApiPath,
            'chatUrl' => $chatboxPath,
        ],
        'adminBase'    => $adminBase,
        'assetsBase'   => $assetsBase,
    ]);
    exit;
}
?>

<!-- CSRF Token for JavaScript -->
<meta name="csrf-token" content="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">

<!-- ════════════════════════════════════════════════════════════
     STYLES
════════════════════════════════════════════════════════════ -->
<style>
    /* ── Topbar dropdown shared ── */
    .topbar-right {
        position: relative;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .dropdown-wrapper {
        position: relative;
    }

    .dropdown-panel {
        position: absolute;
        top: calc(100% + 12px);
        right: 0;
        width: 360px;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .15);
        border: 1px solid #e8ecf0;
        z-index: 9999;
        opacity: 0;
        transform: translateY(-8px) scale(.98);
        pointer-events: none;
        transition: opacity .22s ease, transform .22s ease;
        overflow: hidden;
    }

    .dropdown-panel.open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: all;
    }

    /* Panel header */
    .dp-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px 12px;
        border-bottom: 1px solid #f0f4f8;
    }

    .dp-header h4 {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .dp-header .mark-all {
        font-size: 12px;
        color: #8a9a5b;
        background: none;
        border: none;
        cursor: pointer;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
        transition: background .15s;
    }

    .dp-header .mark-all:hover {
        background: #f0f4f8;
    }

    /* Scrollable panel body */
    .dp-body {
        max-height: 360px;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .dp-body::-webkit-scrollbar {
        width: 4px;
    }

    .dp-body::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    /* Notification rows */
    .notif-item {
        display: flex;
        gap: 12px;
        padding: 14px 20px;
        cursor: pointer;
        border-bottom: 1px solid #f8fafc;
        transition: background .15s;
        position: relative;
    }

    .notif-item:hover {
        background: #f8fafc;
    }

    .notif-item.unread {
        background: #f0f7ff;
    }

    .notif-item.unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: #3b82f6;
        border-radius: 0 2px 2px 0;
    }

    .notif-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 16px;
    }

    .notif-icon.blue {
        background: #eff6ff;
        color: #3b82f6;
    }

    .notif-icon.green {
        background: #f0fdf4;
        color: #22c55e;
    }

    .notif-icon.orange {
        background: #fff7ed;
        color: #f97316;
    }

    .notif-icon.purple {
        background: #faf5ff;
        color: #a855f7;
    }

    .notif-icon.red {
        background: #fef2f2;
        color: #ef4444;
    }

    .notif-content {
        flex: 1;
        min-width: 0;
    }

    .notif-name {
        font-weight: 600;
        font-size: 13px;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .notif-desc {
        font-size: 12px;
        color: #64748b;
        line-height: 1.4;
        margin-bottom: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notif-meta {
        font-size: 11px;
        color: #94a3b8;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .notif-role {
        background: #f1f5f9;
        padding: 1px 6px;
        border-radius: 6px;
    }

    .notif-time {
        margin-left: auto;
    }

    /* Message preview rows */
    .msg-item {
        display: flex;
        gap: 12px;
        padding: 14px 20px;
        cursor: pointer;
        border-bottom: 1px solid #f8fafc;
        transition: background .15s;
        text-decoration: none;
    }

    .msg-item:hover {
        background: #f8fafc;
    }

    .msg-item.unread {
        background: #f0fff4;
    }

    .msg-avatar {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: linear-gradient(135deg, #8a9a5b, #6d7a48);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }

    .msg-info {
        flex: 1;
        min-width: 0;
    }

    .msg-name {
        font-weight: 600;
        font-size: 13px;
        color: #1e293b;
        margin-bottom: 2px;
        display: flex;
        justify-content: space-between;
    }

    .msg-preview {
        font-size: 12px;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .msg-time {
        font-size: 11px;
        color: #94a3b8;
    }

    .msg-badge {
        background: #8a9a5b;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 10px;
    }

    /* Panel footer link */
    .dp-footer {
        display: block;
        text-align: center;
        padding: 12px;
        font-size: 13px;
        color: #8a9a5b;
        font-weight: 600;
        border-top: 1px solid #f0f4f8;
        text-decoration: none;
        transition: background .15s;
    }

    .dp-footer:hover {
        background: #f8fafc;
    }

    /* Empty / loading states */
    .dp-empty {
        padding: 40px 20px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }

    .dp-empty i {
        font-size: 32px;
        margin-bottom: 10px;
        display: block;
        opacity: .4;
    }

    .dp-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 24px;
        color: #94a3b8;
        font-size: 13px;
    }

    .dp-loading i {
        animation: _navSpin .8s linear infinite;
    }

    @keyframes _navSpin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Topbar icon badge (red, for envelope & bell) */
    .icon-btn {
        position: relative;
    }

    .icon-btn .badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        background: #ef4444;
        color: #fff;
        border-radius: 9px;
        font-size: 10px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        line-height: 1;
    }

    .icon-btn .badge[data-count="0"],
    .icon-btn .badge:empty {
        display: none;
    }

    /* User dropdown panel */
    #userDropdownPanel {
        width: 220px;
    }

    .user-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        color: #374151;
        text-decoration: none;
        font-size: 14px;
        transition: background .15s;
    }

    .user-menu-item:hover {
        background: #f8fafc;
    }

    .user-menu-item.logout {
        color: #ef4444;
    }

    .user-menu-divider {
        height: 1px;
        background: #f0f4f8;
        margin: 4px 0;
    }

    /* ═══════════════════════════════════════════════
   SIDEBAR BADGE SYSTEM  —  improved UI
   ═══════════════════════════════════════════════ */

    /* ── Shared pill base ── */
    .sb-count,
    .sb-new,
    .sb-finance,
    .sb-chat {
        margin-left: auto;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        white-space: nowrap;
        font-family: 'Segoe UI', system-ui, sans-serif;
        line-height: 1;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    /* Subtle lift when the parent menu-item is hovered */
    .menu-item:hover .sb-count,
    .menu-item:hover .sb-finance,
    .menu-item:hover .sb-chat {
        transform: scale(1.08);
    }

    /* ── Blue pill — numeric record count ── */
    .sb-count {
        min-width: 22px;
        height: 20px;
        padding: 0 7px;
        font-size: 11px;
        font-weight: 700;
        /* frosted-glass blue */
        background: rgba(59, 130, 246, 0.15);
        color: #2563eb;
        border: 1px solid rgba(59, 130, 246, 0.25);
        box-shadow: 0 1px 3px rgba(59, 130, 246, 0.12);
        letter-spacing: .2px;
    }

    /* ── RED "NEW" alert badge ── */
    .sb-new {
        height: 20px;
        padding: 0 8px;
        font-size: 9.5px;
        font-weight: 900;
        letter-spacing: 1px;
        text-transform: uppercase;
        /* solid red with glow */
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #ffffff;
        border: none;
        box-shadow:
            0 2px 8px rgba(239, 68, 68, 0.45),
            inset 0 1px 0 rgba(255, 255, 255, .2);
        /* attention-grabbing pulse */
        animation: _sbNewPulse 2.4s ease-in-out infinite;
        position: relative;
        overflow: hidden;
    }

    /* shimmer sweep across the "NEW" badge */
    .sb-new::before {
        content: '';
        position: absolute;
        top: 0;
        left: -60%;
        width: 40%;
        height: 100%;
        background: linear-gradient(90deg,
                transparent 0%,
                rgba(255, 255, 255, .35) 50%,
                transparent 100%);
        animation: _sbShimmer 2.4s ease-in-out infinite;
    }

    @keyframes _sbNewPulse {

        0%,
        100% {
            box-shadow: 0 2px 8px rgba(239, 68, 68, .45),
                inset 0 1px 0 rgba(255, 255, 255, .2);
            transform: scale(1);
        }

        50% {
            box-shadow: 0 3px 14px rgba(239, 68, 68, .7),
                inset 0 1px 0 rgba(255, 255, 255, .2);
            transform: scale(1.06);
        }
    }

    @keyframes _sbShimmer {
        0% {
            left: -60%;
        }

        60% {
            left: 120%;
        }

        100% {
            left: 120%;
        }
    }

    /* ── Green pill — finance total ── */
    .sb-finance {
        height: 20px;
        padding: 0 8px;
        font-size: 10.5px;
        font-weight: 700;
        background: rgba(34, 197, 94, 0.13);
        color: #15803d;
        border: 1px solid rgba(34, 197, 94, 0.28);
        box-shadow: 0 1px 3px rgba(34, 197, 94, 0.12);
        letter-spacing: .1px;
    }

    /* ── Orange pill — unread chat messages ── */
    .sb-chat {
        min-width: 22px;
        height: 20px;
        padding: 0 7px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(249, 115, 22, 0.14);
        color: #c2410c;
        border: 1px solid rgba(249, 115, 22, 0.28);
        box-shadow: 0 1px 3px rgba(249, 115, 22, 0.12);
        letter-spacing: .2px;
    }

    /* ── Active menu-item: invert badge colours for contrast ── */
    .menu-item.active .sb-count {
        background: rgba(255, 255, 255, .18);
        color: #fff;
        border-color: rgba(255, 255, 255, .3);
        box-shadow: none;
    }

    .menu-item.active .sb-finance {
        background: rgba(255, 255, 255, .18);
        color: #d1fae5;
        border-color: rgba(255, 255, 255, .3);
        box-shadow: none;
    }

    .menu-item.active .sb-chat {
        background: rgba(255, 255, 255, .18);
        color: #fed7aa;
        border-color: rgba(255, 255, 255, .3);
        box-shadow: none;
    }

    /* "NEW" stays vivid even when active */
    .menu-item.active .sb-new {
        background: linear-gradient(135deg, #fca5a5 0%, #f87171 100%);
        color: #7f1d1d;
        box-shadow: 0 2px 8px rgba(239, 68, 68, .3);
    }

    /* ═══════════════════════════════════════════════
       MOBILE HAMBURGER BUTTON
    ═══════════════════════════════════════════════ */
    .hamburger-btn {
        display: none;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 10px;
        background: transparent;
        cursor: pointer;
        flex-direction: column;
        gap: 5px;
        padding: 8px;
        flex-shrink: 0;
        transition: background .2s;
        -webkit-tap-highlight-color: transparent;
    }

    .hamburger-btn:hover {
        background: rgba(0, 0, 0, .06);
    }

    .hamburger-btn span {
        display: block;
        width: 20px;
        height: 2px;
        background: #374151;
        border-radius: 2px;
        transition: transform .25s ease, opacity .25s ease, width .25s ease;
        transform-origin: center;
    }

    .hamburger-btn.open span:nth-child(1) {
        transform: translateY(7px) rotate(45deg);
    }

    .hamburger-btn.open span:nth-child(2) {
        opacity: 0;
        width: 0;
    }

    .hamburger-btn.open span:nth-child(3) {
        transform: translateY(-7px) rotate(-45deg);
    }

    /* ═══════════════════════════════════════════════
       SIDEBAR OVERLAY (mobile dim backdrop)
    ═══════════════════════════════════════════════ */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .45);
        z-index: 999;
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        opacity: 0;
        transition: opacity .28s ease;
    }

    .sidebar-overlay.visible {
        opacity: 1;
    }

    /* ═══════════════════════════════════════════════
       RESPONSIVE BREAKPOINTS
    ═══════════════════════════════════════════════ */

    /* ── Tablet / small desktop (900px – 1100px) ── */
    @media (max-width: 1100px) {
        .user-info {
            display: none !important;
        }
    }

    /* ── Tablet (max 900px) ── */
    @media (max-width: 900px) {
        .hamburger-btn {
            display: flex !important;
        }

        .sidebar-overlay {
            display: block;
        }

        /* Sidebar slides off-screen by default */
        .sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important;
            z-index: 1000 !important;
            transform: translateX(-100%) !important;
            transition: transform .28s cubic-bezier(.4, 0, .2, 1) !important;
            box-shadow: none !important;
        }

        .sidebar.mobile-open {
            transform: translateX(0) !important;
            box-shadow: 4px 0 30px rgba(0, 0, 0, .18) !important;
        }

        /* Main content fills full width */
        .main {
            margin-left: 0 !important;
            width: 100% !important;
        }

        /* Topbar fills full width */
        .topbar {
            left: 0 !important;
            width: 100% !important;
        }

        /* Search bar shrinks */
        .search {
            flex: 1 !important;
            min-width: 0 !important;
        }

        .search input {
            width: 100% !important;
        }

        .search-shortcut {
            display: none !important;
        }

        /* Dropdown panels become full-width on very small screens */
        .dropdown-panel {
            width: min(360px, calc(100vw - 24px)) !important;
        }
    }

    /* ── Mobile (max 600px) ── */
    @media (max-width: 600px) {
        .topbar {
            padding: 0 12px !important;
            gap: 8px !important;
        }

        .search {
            max-width: none !important;
        }

        /* Hide search text on tiny screens, show icon only via placeholder */
        .search input {
            font-size: 13px !important;
        }

        .user {
            padding: 4px !important;
        }

        .user img {
            width: 32px !important;
            height: 32px !important;
        }

        .topbar-right {
            gap: 4px !important;
        }

        /* Notification dropdown: right-align on mobile to avoid overflow */
        #bellPanel,
        #envelopePanel {
            right: -60px !important;
        }

        #userDropdownPanel {
            right: 0 !important;
        }
    }

    /* ── Very small (max 400px): hide search entirely ── */
    @media (max-width: 400px) {
        .search {
            display: none !important;
        }
    }

    /* Ensure page content has safe padding on mobile */
    @media (max-width: 900px) {
        .page-content {
            padding-top: 80px !important;
        }
    }
</style>

<!-- ════════════════════════════════════════════════════════════
     TOPBAR
════════════════════════════════════════════════════════════ -->
<div class="main">
    <header class="topbar">
        <!-- ── Hamburger (mobile only) ── -->
        <button class="hamburger-btn" id="navHamburgerBtn" aria-label="Toggle navigation menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

        <div class="search" role="search">
            <i class="fas fa-search"></i>
            <input type="text" id="globalSearch"
                placeholder="Search students, teachers, courses…"
                aria-label="Search">
            <kbd class="search-shortcut" title="Ctrl+K">Ctrl+K</kbd>
        </div>

        <div class="topbar-right">

            <!-- ── Envelope / Messages ── -->
            <div class="dropdown-wrapper" id="envelopeWrapper">
                <button class="icon-btn" id="envelopeBtn"
                    data-dropdown="envelopePanel"
                    aria-label="Messages" title="Messages">
                    <i class="fas fa-envelope"></i>
                    <span class="badge" id="envelopeBadge"
                        data-count="<?= $_counts['chat'] ?>"
                        <?= $_counts['chat'] === 0 ? 'style="display:none"' : '' ?>>
                        <?= $_counts['chat'] > 99 ? '99+' : $_counts['chat'] ?>
                    </span>
                </button>
                <div class="dropdown-panel" id="envelopePanel">
                    <div class="dp-header">
                        <h4>Messages</h4>
                        <a href="<?= $chatboxPath ?>"
                            style="font-size:12px;color:#8a9a5b;font-weight:600;">Open chat</a>
                    </div>
                    <div class="dp-body" id="envelopeBody">
                        <div class="dp-loading"><i class="fas fa-spinner"></i> Loading…</div>
                    </div>
                    <a href="<?= $chatboxPath ?>" class="dp-footer">View all conversations</a>
                </div>
            </div>

            <!-- ── Bell / Notifications ── -->
            <div class="dropdown-wrapper" id="bellWrapper">
                <button class="icon-btn" id="bellBtn"
                    data-dropdown="bellPanel"
                    aria-label="Notifications" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="badge" id="bellBadge" data-count="0"
                        style="display:none;"></span>
                </button>
                <div class="dropdown-panel" id="bellPanel">
                    <div class="dp-header">
                        <h4>Notifications</h4>
                        <button class="mark-all" id="markAllReadBtn">Mark all read</button>
                    </div>
                    <div class="dp-body" id="bellBody">
                        <div class="dp-loading"><i class="fas fa-spinner"></i> Loading…</div>
                    </div>
                    <a href="<?= $adminBase ?>reports.php?tab=activity" class="dp-footer">
                        View all activity
                    </a>
                </div>
            </div>

            <!-- ── Upcoming Events ── -->
            <div class="dropdown-wrapper" id="announcementWrapper">
                <button class="icon-btn" id="announcementBtn"
                    data-dropdown="announcementPanel"
                    aria-label="Upcoming Events" title="Upcoming Events">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="badge" id="announcementBadge" data-count="0"
                        style="display:none;"></span>
                </button>
                <div class="dropdown-panel" id="announcementPanel">
                    <div class="dp-header">
                        <h4>Upcoming Events</h4>
                        <a href="<?= $adminBase ?>announcements/create_announcement.php"
                            style="font-size:12px;color:#8a9a5b;font-weight:600;">Manage events</a>
                    </div>
                    <div class="dp-body" id="announcementBody">
                        <div class="dp-loading"><i class="fas fa-spinner"></i> Loading…</div>
                    </div>
                    <a href="<?= $adminBase ?>announcements/" class="dp-footer">View all events</a>
                </div>
            </div>

            <!-- ── User profile dropdown ── -->
            <div class="dropdown-wrapper" id="userWrapper">
                <button class="user" id="userBtn"
                    data-dropdown="userDropdownPanel"
                    aria-haspopup="true" aria-expanded="false" title="User menu"
                    style="display: flex; align-items: center; gap: 10px; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
                    <?php if (($current_user['photo_type'] ?? 'default') === 'icon'): ?>
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <i class="fa-solid fa-user" style="color: white; font-size: 16px;"></i>
                        </div>
                    <?php else: ?>
                        <img src="<?= ($current_user['photo_type'] ?? 'default') === 'external' ? $current_user['photo'] : $assetsBase . $current_user['photo'] ?>"
                            alt="Profile picture"
                            style="width: 36px; height: 36px; border-radius: 8px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <?php endif; ?>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($current_user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="user-role"><?= htmlspecialchars($current_user['role'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-panel" id="userDropdownPanel">
                    <div style="padding:16px 16px 8px;">
                        <p style="font-size:13px;color:#64748b;">Signed in as</p>
                        <p style="font-weight:700;font-size:14px;color:#1e293b;">
                            <?= htmlspecialchars($current_user['name'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p style="font-size:12px;color:#8a9a5b;margin-top:2px;">
                            <?= htmlspecialchars($current_user['role'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <div class="user-menu-divider"></div>
                    <a href="<?= $adminBase ?>admin_profile.php" class="user-menu-item">
                        <i class="fas fa-user"></i><span>My Profile</span>
                    </a>
                    <a href="<?= $adminBase ?>settings.php" class="user-menu-item">
                        <i class="fas fa-cog"></i><span>Settings</span>
                    </a>
                    <div class="user-menu-divider"></div>
                    <a href="<?= $adminBase ?>logout.php" class="user-menu-item logout"
                        onclick="return confirm('Are you sure you want to logout?');">
                        <i class="fas fa-power-off"></i><span>Logout</span>
                    </a>
                </div>
            </div>

        </div><!-- /topbar-right -->
    </header>
</div>

<!-- ════════════════════════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════════════════════════ -->
<aside class="sidebar" role="navigation" aria-label="Main navigation">

    <div class="logo">
        <img src="<?= $assetsBase ?>assets/img/logo.jpg" alt="School Logo">
        <h2>Buyoan National High School</h2>
    </div>

    <a href="<?= $adminBase ?>admin_profile.php" class="profile" tabindex="0"
        style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
        <?php if (($current_user['photo_type'] ?? 'default') === 'icon'): ?>
            <div style="width: 44px; height: 44px; border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; border: 2px solid #fff; box-shadow: 0 3px 12px rgba(0,0,0,0.15);">
                <i class="fa-solid fa-user" style="color: white; font-size: 20px;"></i>
            </div>
        <?php else: ?>
            <img src="<?= ($current_user['photo_type'] ?? 'default') === 'external' ? $current_user['photo'] : $assetsBase . $current_user['photo'] ?>"
                alt="Profile picture"
                style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 3px 12px rgba(0,0,0,0.15);">
        <?php endif; ?>
        <div class="info">
            <h4 style="margin:0; font-size:14px; font-weight:600; color:#1e293b;"><?= htmlspecialchars($current_user['name'], ENT_QUOTES, 'UTF-8') ?></h4>
            <p style="margin:2px 0 0; font-size:12px; color:#64748b; font-weight:500;"><?= htmlspecialchars($current_user['role'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <i class="fas fa-chevron-right profile-arrow"></i>
    </a>

    <div class="menu-divider"></div>

    <nav class="menu" role="menu" aria-label="Main menu">

        <!-- ── MAIN MENU ── -->
        <div class="menu-section">
            <span class="menu-label">MAIN MENU</span>

            <a href="<?= $adminBase ?>admin_dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <!-- Admins -->
            <a href="<?= $adminBase ?>admins.php" class="menu-item">
                <i class="fas fa-user-tie"></i>
                <span>Admins</span>
                <?php if (_nav_has_new($_new_modules, 'admins')): ?>
                    <span class="sb-new">NEW</span>
                <?php elseif ($_counts['admins'] > 0): ?>
                    <span class="sb-count"><?= _nav_fmt_count($_counts['admins']) ?></span>
                <?php endif; ?>
            </a>

            <!-- Forms -->
            <a href="<?= $adminBase ?>forms.php" class="menu-item">
                <i class="fas fa-file-circle-check"></i>
                <span>Forms</span>
                <?php if (_nav_has_new($_new_modules, 'forms')): ?>
                    <span class="sb-new">NEW</span>
                <?php elseif ($_counts['forms'] > 0): ?>
                    <span class="sb-count"><?= _nav_fmt_count($_counts['forms']) ?></span>
                <?php endif; ?>
            </a>

            <!-- ── MANAGEMENT ── -->
            <div class="menu-section">
                <span class="menu-label">MANAGEMENT</span>

                <!-- Announcements (collapsible sub-menu) -->
                <div class="sidebar-dropdown" id="announcementsDropdown">
                    <button type="button" class="menu-item sidebar-dropdown-toggle"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="announcementsMenu">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Announcements</span>
                        <?php if (
                            _nav_has_new($_new_modules, 'announcements') ||
                            _nav_has_new($_new_modules, 'news')
                        ): ?>
                            <span class="sb-new" style="margin-right:4px;">NEW</span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down sidebar-dropdown-arrow"
                            style="font-size:11px; margin-left:auto; flex-shrink:0;
                              transition:transform .2s ease;"></i>
                    </button>
                    <div class="sidebar-dropdown-menu" id="announcementsMenu"
                        style="display:none; padding-left:14px;">
                        <a href="<?= $adminBase ?>announcements/create_announcement.php"
                            class="menu-item" style="font-size:13px;">
                            <i class="fas fa-calendar-check"></i>
                            <span>Post Announcement</span>
                        </a>
                        <a href="<?= $adminBase ?>announcements/create_new.php"
                            class="menu-item" style="font-size:13px;">
                            <i class="fas fa-plus-circle"></i>
                            <span>Post News</span>
                        </a>
                    </div>
                </div>

                <!--
            <a href="<?= $adminBase ?>reports.php" class="menu-item">
                <i class="fa-solid fa-person-harassing"></i>
                <span>Reports</span>
            </a>
            -->

                <!-- Chatbox — orange unread message count -->
                <a href="<?= $adminBase ?>admin_chatbox.php" class="menu-item">
                    <i class="fa-solid fa-comments"></i>
                    <span>Chatbox</span>
                    <?php if ($_counts['chat'] > 0): ?>
                        <span class="sb-chat" id="sidebarChatBadge">
                            <?= _nav_fmt_count($_counts['chat']) ?>
                        </span>
                    <?php else: ?>
                        <span class="sb-chat" id="sidebarChatBadge" style="display:none;"></span>
                    <?php endif; ?>
                </a>

                <!-- ── SYSTEM ── -->
                <div class="menu-section">
                    <span class="menu-label">SYSTEM</span>
                    <a href="<?= $adminBase ?>settings.php" class="menu-item">
                        <i class="fas fa-cog"></i><span>Settings</span>
                    </a>
                    <a href="<?= $adminBase ?>help&support.php" class="menu-item">
                        <i class="fas fa-question-circle"></i><span>Help & Support</span>
                    </a>
                </div>

    </nav>

    <div class="sidebar-footer">
        <div class="storage-info">
            <div class="storage-header">
                <span>Storage Used</span>
                <span class="storage-percentage">68%</span>
            </div>
            <div class="storage-bar" role="progressbar"
                aria-valuenow="68" aria-valuemin="0" aria-valuemax="100">
                <div class="storage-progress" style="width:68%"></div>
            </div>
            <p class="storage-text">6.8 GB of 10 GB</p>
        </div>
        <a href="<?= $adminBase ?>logout.php" class="logout"
            onclick="return confirm('Are you sure you want to logout?');">
            <i class="fas fa-power-off"></i><span>Logout</span>
        </a>
    </div>

</aside>

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<!-- ════════════════════════════════════════════════════════════
     JAVASCRIPT  —  same pattern as admin_chatbox.php
     • DOMContentLoaded — identical to how chatbox initialises
     • Direct getElementById binding — clean and simple
     • e.target.closest() — handles clicks on child icons
     • API URLs from PHP — always absolute, never relative
     • One-time guard — safe if nav is included more than once
════════════════════════════════════════════════════════════ -->
<script>
    /* ── API URLs from PHP (absolute paths, work from any directory) ── */
    const _NAV_API = {
        notif: <?= json_encode($notifApiPath) ?>,
        chat: <?= json_encode($chatApiPath)  ?>,
        chatUrl: <?= json_encode($chatboxPath)  ?>,
    };

    /* ── CSRF Token Helper ── */
    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /* ── initAdminNav()
         Called automatically on DOMContentLoaded when nav is PHP-included.
         Call it manually after a fetch()+innerHTML insertion, e.g.:
           fetch('/admin_account/admin_nav.php?embed=1')
             .then(r => r.text())
             .then(html => {
               document.getElementById('navContainer').innerHTML = html;
               // inline <script> tags won't run via innerHTML — call init manually:
               if (typeof initAdminNav === 'function') initAdminNav();
             });
    ── */
    function initAdminNav(options) {
        /* One-time guard — safe when called multiple times */
        if (window.__adminNavReady) return;
        window.__adminNavReady = true;

        /* Support an optional rootSelector so the nav can mount into
           a custom container: initAdminNav({ root: '#myNavContainer' }) */
        var _root = (options && options.root) ?
            document.querySelector(options.root) :
            document;

        /* ════════════════════════════════════════════════════════
           DROPDOWN CONTROL — matches admin_chatbox.php pattern
           ─────────────────────────────────────────────────────
           KEY RULES (identical to chatbox):
           • NO stopPropagation anywhere — it breaks outside-click
           • ONE document click listener handles everything
           • e.target.closest() detects wrapper vs outside click
           • Buttons are identified by closest('[data-dropdown]')
           • Lazy-load flags live here, not inside togglePanel
        ════════════════════════════════════════════════════════ */

        /* Lazy-load flags — content fetched only on first open */
        var bellLoaded = false;
        var envLoaded = false;
        var announcementLoaded = false;

        /* ── Mark-all-read (bound directly — element is always present) ── */
        var markAllBtn = _root.getElementById ?
            _root.getElementById('markAllReadBtn') :
            _root.querySelector('#markAllReadBtn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function() {
                fetch(_NAV_API.notif + '?action=mark_all_read', {
                        method: 'POST'
                    })
                    .then(function() {
                        _root.querySelectorAll('#bellBody .notif-item')
                            .forEach(function(el) {
                                el.classList.remove('unread');
                            });
                        setBellBadge(0);
                    })
                    .catch(function() {});
            });
        }

        /* ── Close every .dropdown-panel except the one supplied ── */
        function closeAll(keepPanel) {
            _root.querySelectorAll('.dropdown-panel').forEach(function(panel) {
                if (panel !== keepPanel) {
                    panel.classList.remove('open');
                }
            });
        }

        /* ── Single document-level listener (same as chatbox pattern) ──
           Logic flow — mirrors admin_chatbox.php exactly:
           1. Did the click land inside a .dropdown-wrapper?
              NO  → close everything and stop.
              YES → which button triggered it (if any)?
           2. Find the nearest [data-dropdown] button ancestor.
              Found  → toggle that panel, lazy-load if needed.
              Not found (clicked inside an already-open panel) → do nothing.
        ─────────────────────────────────────────────────────────────── */
        document.addEventListener('click', function(e) {

            /* Step 1 — is the click inside any dropdown wrapper at all? */
            var wrapper = e.target.closest('.dropdown-wrapper');
            if (!wrapper) {
                /* Clicked outside every wrapper → close all */
                closeAll(null);
                return;
            }

            /* Step 2 — did the click land on (or inside) a trigger button?
               Using closest('[data-dropdown]') so clicking a child <i> icon
               still resolves to the correct button element.                */
            var btn = e.target.closest('[data-dropdown]');
            if (!btn) {
                /* Clicked inside the panel area, not on the button → keep open */
                return;
            }

            /* Step 3 — toggle the target panel */
            var targetId = btn.getAttribute('data-dropdown');
            var panel = document.getElementById(targetId);
            if (!panel) return;

            var isOpen = panel.classList.contains('open');

            /* Close all siblings first */
            closeAll(panel);

            if (isOpen) {
                /* Already open → close it */
                panel.classList.remove('open');
            } else {
                /* Closed → open it and lazy-load content if first time */
                panel.classList.add('open');

                if (targetId === 'bellPanel' && !bellLoaded) {
                    bellLoaded = true;
                    fetchNotifications();
                }
                if (targetId === 'envelopePanel' && !envLoaded) {
                    envLoaded = true;
                    fetchEnvelope();
                }
                if (targetId === 'announcementPanel' && !announcementLoaded) {
                    announcementLoaded = true;
                    fetchAnnouncements();
                }
            }
        });

        /* ════════════════════════════════════════════════════════
           BELL — NOTIFICATIONS
        ════════════════════════════════════════════════════════ */
        const ICONS = {
            students: {
                i: 'fa-user-graduate',
                c: 'blue'
            },
            teachers: {
                i: 'fa-chalkboard-teacher',
                c: 'green'
            },
            clubs: {
                i: 'fa-users-line',
                c: 'purple'
            },
            forms: {
                i: 'fa-file-circle-check',
                c: 'orange'
            },
            announcements: {
                i: 'fa-calendar-alt',
                c: 'blue'
            },
            create_announcement: {
                i: 'fa-calendar-check',
                c: 'blue'
            },
            news: {
                i: 'fa-newspaper',
                c: 'green'
            },
            create_new: {
                i: 'fa-newspaper',
                c: 'green'
            },
        };

        function getIcon(mod) {
            return ICONS[(mod || '').toLowerCase().replace(/\s+/g, '_')] || {
                i: 'fa-bell',
                c: 'blue'
            };
        }

        function fetchNotifications() {
            var body = document.getElementById('bellBody');
            if (!body) return;
            body.innerHTML = '<div class="dp-loading"><i class="fas fa-spinner"></i> Loading…</div>';

            fetch(_NAV_API.notif + '?action=fetch')
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    if (!d.success) throw new Error();
                    setBellBadge(d.unread_count);
                    renderNotifs(d.notifications);
                })
                .catch(function() {
                    var b = document.getElementById('bellBody');
                    if (b) b.innerHTML = '<div class="dp-empty"><i class="fas fa-wifi-slash"></i>Could not load.</div>';
                });
        }

        function renderNotifs(list) {
            var body = document.getElementById('bellBody');
            if (!body) return;

            if (!list.length) {
                body.innerHTML = '<div class="dp-empty"><i class="fas fa-bell-slash"></i>No notifications yet.</div>';
                return;
            }

            body.innerHTML = list.map(function(n) {
                var m = getIcon(n.edited_module);
                return '<div class="notif-item ' + (n.is_read == 0 ? 'unread' : '') + '" data-id="' + n.id + '">' +
                    '<div class="notif-icon ' + m.c + '"><i class="fas ' + m.i + '"></i></div>' +
                    '<div class="notif-content">' +
                    '<div class="notif-name">' + esc(n.sub_admin_name) + '</div>' +
                    '<div class="notif-desc">' + esc(n.edit_description) + '</div>' +
                    '<div class="notif-meta">' +
                    '<span class="notif-role">' + esc(n.role) + '</span>' +
                    '<span>' + esc(n.sub_admin_email) + '</span>' +
                    '<span class="notif-time">' + esc(n.time_ago) + '</span>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            }).join('');

            /* Attach click handlers to each row */
            body.querySelectorAll('.notif-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    if (!this.classList.contains('unread')) return;
                    var id = this.dataset.id;
                    var fd = new FormData();
                    fd.append('action', 'mark_read');
                    fd.append('id', id);
                    var row = this;
                    fetch(_NAV_API.notif, {
                            method: 'POST',
                            body: fd
                        })
                        .then(function() {
                            row.classList.remove('unread');
                            var b = document.getElementById('bellBadge');
                            var cur = parseInt(b ? b.textContent : '0') || 0;
                            setBellBadge(Math.max(0, cur - 1));
                        })
                        .catch(function() {});
                });
            });
        }

        function setBellBadge(n) {
            var b = document.getElementById('bellBadge');
            if (!b) return;
            b.textContent = n > 99 ? '99+' : n;
            b.dataset.count = n;
            b.style.display = n > 0 ? '' : 'none';
        }

        /* ════════════════════════════════════════════════════════
           ENVELOPE — CHAT PREVIEW
        ════════════════════════════════════════════════════════ */
        function fetchEnvelope() {
            var body = document.getElementById('envelopeBody');
            if (!body) return;
            body.innerHTML = '<div class="dp-loading"><i class="fas fa-spinner"></i> Loading…</div>';

            var fd = new FormData();
            fd.append('action', 'envelope_preview');
            fd.append('csrf_token', getCSRFToken());
            fetch(_NAV_API.chat, {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    if (!d.success) throw new Error();
                    setEnvBadge(d.total_unread);
                    renderEnvelope(d.previews);
                })
                .catch(function() {
                    var b = document.getElementById('envelopeBody');
                    if (b) b.innerHTML = '<div class="dp-empty"><i class="fas fa-wifi-slash"></i>Could not load.</div>';
                });
        }

        function renderEnvelope(list) {
            var body = document.getElementById('envelopeBody');
            if (!body) return;

            if (!list.length) {
                body.innerHTML = '<div class="dp-empty"><i class="fas fa-envelope-open"></i>No messages yet.</div>';
                return;
            }

            body.innerHTML = list.map(function(m) {
                var unreadClass = m.unread > 0 ? 'unread' : '';
                var unreadBadge = m.unread > 0 ? '<span class="msg-badge">' + m.unread + '</span>' : '';

                // Enhanced message preview with sender info and better formatting
                var messagePreview = m.last_message || '—';
                if (messagePreview.length > 50) {
                    messagePreview = messagePreview.substring(0, 50) + '...';
                }

                // Determine sender type and format accordingly
                var senderInfo = '';
                if (m.sender_role === 'student') {
                    senderInfo = '<span style="color: #8a9a5b; font-size: 11px;">Student • </span>';
                } else if (m.sender_role === 'admin') {
                    senderInfo = '<span style="color: #3b82f6; font-size: 11px;">Admin • </span>';
                }

                return '<a class="msg-item ' + unreadClass + '" href="' + esc(_NAV_API.chatUrl) + '?conv=' + parseInt(m.conv_id) + '">' +
                    '<div class="msg-avatar" style="background: linear-gradient(135deg, #8a9a5b, #6d7a48);">' + esc(m.avatar_letter) + '</div>' +
                    '<div class="msg-info">' +
                    '<div class="msg-name">' +
                    '<div style="display: flex; align-items: center; gap: 6px;">' +
                    esc(m.student_name) +
                    unreadBadge +
                    '</div>' +
                    '<div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">' + senderInfo + esc(m.time_ago) + '</div>' +
                    '</div>' +
                    '<div class="msg-preview" style="margin-top: 4px; font-weight: 400;">' + esc(messagePreview) + '</div>' +
                    '</div>' +
                    '</a>';
            }).join('');
        }

        function setEnvBadge(n) {
            var b = document.getElementById('envelopeBadge');
            if (b) {
                b.textContent = n > 99 ? '99+' : n;
                b.dataset.count = n;
                b.style.display = n > 0 ? '' : 'none';
            }
            var sb = document.getElementById('sidebarChatBadge');
            if (sb) {
                sb.textContent = n > 999 ? Math.round(n / 1000) + 'k' : n;
                sb.style.display = n > 0 ? '' : 'none';
            }
        }

        /* ════════════════════════════════════════════════════════
           UPCOMING EVENTS DROPDOWN
        ════════════════════════════════════════════════════════ */
        function fetchAnnouncements() {
            var body = document.getElementById('announcementBody');
            if (!body) return;
            body.innerHTML = '<div class="dp-loading"><i class="fas fa-spinner"></i> Loading…</div>';

            // Fetch upcoming events from create_announcement.php API
            fetch('<?= $adminBase ?>announcements/create_announcement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_upcoming_events&limit=5&csrf_token=' + encodeURIComponent(getCSRFToken())
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.events && data.events.length > 0) {
                        renderAnnouncements(data.events);
                    } else {
                        renderAnnouncements([]);
                    }
                })
                .catch(error => {
                    console.error('Error fetching announcements:', error);
                    renderAnnouncements([]);
                });
        }

        function renderAnnouncements(list) {
            var body = document.getElementById('announcementBody');
            if (!body) return;

            if (!list.length) {
                body.innerHTML = '<div class="dp-empty"><i class="fas fa-calendar-alt"></i>No upcoming events.</div>';
                return;
            }

            body.innerHTML = list.map(function(event) {
                // Format event date
                var eventDate = new Date(event.event_date);
                var today = new Date();
                var tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);

                var dateLabel = '';
                if (eventDate.toDateString() === today.toDateString()) {
                    dateLabel = 'Today';
                } else if (eventDate.toDateString() === tomorrow.toDateString()) {
                    dateLabel = 'Tomorrow';
                } else {
                    dateLabel = eventDate.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric'
                    });
                }

                // Format time
                var timeLabel = event.event_start_time || 'All day';

                // Category colors
                var categoryColors = {
                    'academic': 'blue',
                    'cultural': 'purple',
                    'sports': 'green',
                    'meeting': 'orange',
                    'holiday': 'red',
                    'other': 'gray'
                };
                var categoryClass = categoryColors[event.category] || 'blue';

                return '<div class="notif-item" style="cursor:pointer;" onclick="window.location.href=\'<?= $adminBase ?>announcements/create_announcement.php\'">' +
                    '<div class="notif-icon ' + categoryClass + '"><i class="fas fa-calendar-alt"></i></div>' +
                    '<div class="notif-content">' +
                    '<div class="notif-name">' + esc(event.title) + '</div>' +
                    '<div class="notif-desc">' + dateLabel + ' at ' + esc(timeLabel) + '</div>' +
                    '<div class="notif-meta">' +
                    '<span class="notif-role">' + esc(event.category || 'event') + '</span>' +
                    '<span class="notif-time">' + dateLabel + '</span>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            }).join('');
        }

        /* ════════════════════════════════════════════════════════
           MOBILE SIDEBAR TOGGLE
        ════════════════════════════════════════════════════════ */
        (function() {
            var hamburger = document.getElementById('navHamburgerBtn');
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (!hamburger || !sidebar || !overlay) return;

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
                if (sidebar.classList.contains('mobile-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            overlay.addEventListener('click', closeSidebar);

            /* Close sidebar when a nav link is clicked on mobile */
            sidebar.querySelectorAll('a.menu-item').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 900) closeSidebar();
                });
            });

            /* Close on resize back to desktop */
            window.addEventListener('resize', function() {
                if (window.innerWidth > 900) closeSidebar();
            });
        })();

        /* ════════════════════════════════════════════════════════
           SIDEBAR ANNOUNCEMENTS SUB-MENU
        ════════════════════════════════════════════════════════ */
        var announceToggle = document.querySelector('[aria-controls="announcementsMenu"]');
        if (announceToggle) {
            announceToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var menuId = this.getAttribute('aria-controls');
                var menu = document.getElementById(menuId);
                var arrow = this.querySelector('.sidebar-dropdown-arrow');
                if (!menu) return;
                var nowOpen = menu.style.display !== 'none';
                menu.style.display = nowOpen ? 'none' : 'block';
                this.setAttribute('aria-expanded', String(!nowOpen));
                if (arrow) arrow.style.transform = nowOpen ? '' : 'rotate(180deg)';
            });
        }

        /* Auto-expand announcements menu if currently on a sub-page */
        (function() {
            var page = location.pathname.split('/').pop().replace('.php', '');
            var subPages = ['create_announcement', 'create_new'];
            if (subPages.indexOf(page) === -1) return;
            var menu = document.getElementById('announcementsMenu');
            var btn = document.querySelector('[aria-controls="announcementsMenu"]');
            var arrow = btn ? btn.querySelector('.sidebar-dropdown-arrow') : null;
            if (menu) menu.style.display = 'block';
            if (btn) btn.setAttribute('aria-expanded', 'true');
            if (arrow) arrow.style.transform = 'rotate(180deg)';
        })();

        /* ════════════════════════════════════════════════════════
           INITIAL BADGE COUNTS on page load
        ════════════════════════════════════════════════════════ */
        fetch(_NAV_API.notif + '?action=fetch')
            .then(function(r) {
                return r.json();
            })
            .then(function(d) {
                if (d.success) setBellBadge(d.unread_count);
            })
            .catch(function() {});

        (function() {
            var fd = new FormData();
            fd.append('action', 'envelope_preview');
            fetch(_NAV_API.chat, {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    if (d.success) setEnvBadge(d.total_unread);
                })
                .catch(function() {});
        })();

        /* ════════════════════════════════════════════════════════
           BACKGROUND REFRESH every 60 s
        ════════════════════════════════════════════════════════ */
        setInterval(function() {
            fetch(_NAV_API.notif + '?action=fetch')
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    if (d.success) setBellBadge(d.unread_count);
                })
                .catch(function() {});

            var fd = new FormData();
            fd.append('action', 'envelope_preview');
            fetch(_NAV_API.chat, {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
                    if (d.success) setEnvBadge(d.total_unread);
                })
                .catch(function() {});
        }, 60000);

        /* ════════════════════════════════════════════════════════
           XSS-SAFE ESCAPE
        ════════════════════════════════════════════════════════ */
        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

    } /* end initAdminNav() */

    // Global function for announcement badge - accessible from any page
    function setAnnouncementBadge(n) {
        var b = document.getElementById('announcementBadge');
        if (b) {
            b.textContent = n > 99 ? '99+' : n;
            b.dataset.count = n;
            b.style.display = n > 0 ? '' : 'none';
        }
    }

    /* ── Auto-run on DOMContentLoaded when PHP-included directly ── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initAdminNav();
            initializeNavigation();
        });

        // Initialize Navigation Functionality (fix dropdown paths)
        function initializeNavigation() {
            // The dropdown items are already correctly set by PHP using $adminBase
            // No JavaScript path fixing needed for the main navigation
            // This function is kept for compatibility but doesn't need to modify hrefs
            console.log('Navigation initialized - PHP paths are already correct');

            // Initialize announcement badge with sample count
            setAnnouncementBadge(3);
        }

    } else {
        /* DOM already ready (e.g. script injected after load) */
        initAdminNav();
        initializeNavigation();
    }
</script>