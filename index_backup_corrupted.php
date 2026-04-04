<?php
// ─── Session + Cache + DB ─────────────────────────────────────────────────────
require_once 'session_config.php';
require_once 'cache_helper.php';

// ── Safe DB include with error recovery ──────────────────────────────────────
// SAFE MODE DISABLED - Full site live
// mysqli safe check active in db_connection.php

// Original DB include
try {
    include 'db_connection.php';
} catch (Exception $e) {
    error_log('DB Connection failed: ' . $e->getMessage());
    http_response_code(503);
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Service Unavailable</title>
    </head>

    <body>
        <h1>Service Temporarily Unavailable</h1>
        <p>Database connection failed. Please check back soon.</p>
        <p>Details: <?php echo htmlspecialchars($e->getMessage()); ?></p>
    </body>

    </html>
<?php
    exit;
}

require __DIR__ . '/vendor/autoload.php';

// ─── CSRF TOKEN — generate once per session, used by all login/signup forms ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ─── AUTO-CREATE SUPPORT TABLES ──────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS school_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_date DATE NOT NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    custom_message TEXT DEFAULT NULL,
    created_by VARCHAR(100) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS student_memories (
    id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL DEFAULT '',
    image       VARCHAR(500) NOT NULL DEFAULT '',
    category    ENUM('Student Activities','Academic Excellence','Sports') NOT NULL DEFAULT 'Student Activities',
    uploaded_by VARCHAR(100) DEFAULT 'admin',
    uploaded_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS school_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("INSERT IGNORE INTO school_settings (setting_key, setting_value) VALUES
    ('school_founding_year', '2018'),
    ('about_photo', 'assets/img/front pic/Buyoan School.jpg'),
    ('cta_photo', 'assets/img/education/Students learning.jpg')");

$conn->query("CREATE TABLE IF NOT EXISTS homepage_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_key VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) DEFAULT '',
    description TEXT DEFAULT '',
    icon VARCHAR(100) DEFAULT '',
    image VARCHAR(255) DEFAULT '',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("INSERT IGNORE INTO homepage_cards (card_key, title, description, icon, image) VALUES
    ('leadership', 'Leadership Development', 'Buyoan National High School shapes future leaders through dynamic SSG programs, hands-on leadership trainings, and engaging school and DepEd events helping students build confidence, teamwork, and communication skills that last a lifetime', 'fa-crown', 'assets/img/education/Leadership development.jpg'),
    ('cultural', 'Cultural Diversity', 'Buyoan National High School celebrates the rich blend of Bicolano and Filipino cultures that shape our campus community. We honor traditions, embrace diversity, and integrate local heritage into learning empowering students to grow with pride, inclusivity, and respect for all.', 'fa-globe', 'assets/img/education/Cultural Event.jpg'),
    ('innovation', 'Innovation Hub', 'Buyoan National High School\\'s Innovation Hub nurtures future-ready learners by inspiring creativity, critical thinking, and hands-on innovation to solve real-world challenges.', 'fa-lightbulb', 'assets/img/innovation.jpg'),
    ('cert_card1', 'Certified Excellence', 'Industry-recognized certificates', 'fa-trophy', ''),
    ('cert_card2', 'Learn at Your Pace', '24/7 access to all materials', 'fa-clock', ''),
    ('cert_card3', 'Global Community', 'Connect with learners worldwide', 'fa-users', '')");

// ─── HELPER FUNCTIONS ─────────────────────────────────────────────────────────
function maskEmail($email)
{
    $parts = explode('@', $email);
    $username = $parts[0];
    $domain   = $parts[1];
    $maskedUsername = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
    return $maskedUsername . '@' . $domain;
}

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);
define('LOG_FILE', 'logs/login_attempts.log');

function getClientIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    else return $_SERVER['REMOTE_ADDR'];
}

function logLoginAttempt($username, $ip, $success)
{
    $logEntry = sprintf("[%s] IP: %s | Username: %s | Success: %s\n", date('Y-m-d H:i:s'), $ip, htmlspecialchars($username), $success ? 'Yes' : 'No');
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

function isRateLimited($ip)
{
    $attempts = $_SESSION['login_attempts'][$ip] ?? [];
    $recentAttempts = array_filter($attempts, function ($time) {
        return $time > time() - LOCKOUT_TIME;
    });
    return count($recentAttempts) >= MAX_LOGIN_ATTEMPTS;
}

function recordLoginAttempt($ip)
{
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = [];
    if (!isset($_SESSION['login_attempts'][$ip])) $_SESSION['login_attempts'][$ip] = [];
    $_SESSION['login_attempts'][$ip][] = time();
}

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function star_html($rating)
{
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating)) $html .= '<i class="fas fa-star" style="color:#3b975e;font-size:18px;"></i>';
        elseif ($i - $rating < 1 && $i - $rating > 0) $html .= '<i class="fas fa-star-half-alt" style="color:#3b975e;font-size:18px;"></i>';
        else $html .= '<i class="far fa-star" style="color:#3b975e;font-size:18px;"></i>';
    }
    return $html;
}

function get_setting($conn, $key, $default = '')
{
    $k = $conn->real_escape_string($key);
    $res = $conn->query("SELECT setting_value FROM school_settings WHERE setting_key = '$k' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) return $row['setting_value'];
    return $default;
}

function get_card($conn, $key)
{
    $k = $conn->real_escape_string($key);
    $res = $conn->query("SELECT * FROM homepage_cards WHERE card_key = '$k' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) return $row;
    return ['title' => '', 'description' => '', 'icon' => '', 'image' => ''];
}

// ─── FETCH ALL DYNAMIC DATA (with APCu caching) ───────────────────────────────

// ── CACHE BLOCK 1: Homepage aggregate stats (all 14 queries bundled) ──────────
$stats = cache_get('stats:homepage');

if ($stats === false) {
    // CACHE MISS — run all queries and pack into one array

    $total_students  = 0;
    $active_students = 0;
    $res = $conn->query("SELECT COUNT(*) as total FROM students");
    if ($res && $row = $res->fetch_assoc()) $total_students = (int)$row['total'];

    $res = $conn->query("SELECT COUNT(*) as total FROM students WHERE LOWER(status)='active'");
    if ($res && $row = $res->fetch_assoc()) $active_students = (int)$row['total'];

    $total_teachers = 0;
    $res = $conn->query("SELECT COUNT(*) as total FROM teachers");
    if ($res && $row = $res->fetch_assoc()) $total_teachers = (int)$row['total'];

    $total_subjects = 0;
    $res = $conn->query("SELECT teacher_subjects FROM teachers WHERE teacher_subjects IS NOT NULL AND teacher_subjects != ''");
    $all_subjects = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $parts = array_map('trim', explode(',', $row['teacher_subjects']));
            foreach ($parts as $s) {
                if ($s !== '') $all_subjects[] = strtolower($s);
            }
        }
        $total_subjects = count(array_unique($all_subjects));
    }
    if ($total_subjects === 0) $total_subjects = 0;

    $ratio_display = '0:1';
    if ($total_teachers > 0) {
        $ratio_num = round($total_students / $total_teachers, 0);
        $ratio_display = $ratio_num . ':1';
    }

    $school_rating_val   = 0;
    $school_rating_count = 0;
    $res = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM school_ratings");
    if ($res && $row = $res->fetch_assoc()) {
        $school_rating_val   = round((float)$row['avg_rating'], 1);
        $school_rating_count = (int)$row['total_reviews'];
    }
    if ($school_rating_val <= 0) {
        $school_rating_val = 0;
        $school_rating_count = 0;
    }

    $graduation_rate = 0;
    $res = $conn->query("SELECT graduation_year, COUNT(*) as total,
        SUM(CASE WHEN LOWER(status) IN ('completers','graduate','graduated','completer') THEN 1 ELSE 0 END) as completers
        FROM students WHERE graduation_year IS NOT NULL AND graduation_year > 0
        GROUP BY graduation_year");
    if ($res && $res->num_rows > 0) {
        $batch_rates = [];
        while ($row = $res->fetch_assoc()) {
            if ($row['total'] > 0) $batch_rates[] = ($row['completers'] / $row['total']) * 100;
        }
        if (count($batch_rates) > 0) $graduation_rate = round(array_sum($batch_rates) / count($batch_rates), 0);
    }

    $batch_success_pct  = 0;
    $batch_success_year = null;
    $stmt_bg = $conn->prepare(
        "SELECT graduation_year, COUNT(*) AS total,
                SUM(CASE WHEN LOWER(status) IN ('completers','graduate','graduated','completer') THEN 1 ELSE 0 END) AS completers
         FROM students WHERE graduation_year IS NOT NULL AND graduation_year > 0
         GROUP BY graduation_year ORDER BY graduation_year DESC LIMIT 1"
    );
    if ($stmt_bg) {
        $stmt_bg->execute();
        $res_bg = $stmt_bg->get_result();
        if ($res_bg && $row_bg = $res_bg->fetch_assoc()) {
            $batch_success_year = (int)$row_bg['graduation_year'];
            if ((int)$row_bg['total'] > 0) {
                $batch_success_pct = round(((int)$row_bg['completers'] / (int)$row_bg['total']) * 100, 0);
            }
        }
        $stmt_bg->close();
    }

    $clubs_list       = [];
    $total_clubs      = 0;
    $clubs_has_logo   = false;
    $clubs_has_status = false;
    $clubs_col_check  = $conn->query("SHOW COLUMNS FROM clubs");
    if ($clubs_col_check) {
        while ($col = $clubs_col_check->fetch_assoc()) {
            if ($col['Field'] === 'logo')   $clubs_has_logo   = true;
            if ($col['Field'] === 'status') $clubs_has_status = true;
        }
    }
    $logo_select  = $clubs_has_logo   ? ', c.logo' : ', NULL AS logo';
    $status_where = $clubs_has_status ? "WHERE c.status = 'Active'" : '';
    $has_club_members = $conn->query("SHOW TABLES LIKE 'club_members'")->num_rows > 0;
    $member_select = $has_club_members
        ? '(SELECT COUNT(*) FROM club_members cm WHERE cm.club_id = c.id) AS member_count'
        : '0 AS member_count';
    $res = $conn->query("SELECT c.id, c.name, c.description $logo_select, $member_select FROM clubs c $status_where ORDER BY c.name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) $clubs_list[] = $row;
        $total_clubs = count($clubs_list);
    }
    if ($total_clubs === 0) {
        $res2 = $conn->query("SELECT COUNT(*) as total FROM clubs");
        if ($res2 && $row = $res2->fetch_assoc()) $total_clubs = (int)$row['total'];
    }

    $total_events = 0;
    $res = $conn->query("SELECT COUNT(*) as total FROM events");
    if ($res && $row = $res->fetch_assoc()) $total_events = (int)$row['total'];

    $today_date         = date('Y-m-d');
    $today_announcement = null;
    $res = $conn->query("SELECT * FROM school_announcements WHERE announcement_date = '$today_date' LIMIT 1");
    if ($res && $res->num_rows > 0) $today_announcement = $res->fetch_assoc();

    $upcoming_events = [];
    $res = $conn->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 4");
    if ($res) {
        while ($row = $res->fetch_assoc()) $upcoming_events[] = $row;
    }
    if (count($upcoming_events) < 4) {
        $need    = 4 - count($upcoming_events);
        $ids_in  = array_map(fn($e) => (int)$e['id'], $upcoming_events);
        $exclude = count($ids_in) ? 'AND id NOT IN (' . implode(',', $ids_in) . ')' : '';
        $res2 = $conn->query("SELECT * FROM events WHERE 1=1 $exclude ORDER BY event_date DESC LIMIT $need");
        if ($res2) while ($row = $res2->fetch_assoc()) $upcoming_events[] = $row;
    }

    $memories = ['Student Activities' => [], 'Academic Excellence' => [], 'Sports' => []];
    $mem_res  = $conn->query("SELECT title, image, category FROM student_memories ORDER BY uploaded_at DESC LIMIT 30");
    if ($mem_res) {
        while ($mrow = $mem_res->fetch_assoc()) {
            $cat = $mrow['category'];
            if (isset($memories[$cat])) $memories[$cat][] = $mrow;
        }
    }

    // Pack everything and cache
    $stats = compact(
        'total_students',
        'active_students',
        'total_teachers',
        'total_subjects',
        'ratio_display',
        'school_rating_val',
        'school_rating_count',
        'graduation_rate',
        'batch_success_pct',
        'batch_success_year',
        'clubs_list',
        'total_clubs',
        'total_events',
        'today_date',
        'today_announcement',
        'upcoming_events',
        'memories'
    );
    cache_set('stats:homepage', $stats, CACHE_TTL_STATS);
} else {
    // CACHE HIT — restore all variables into scope instantly
    extract($stats);
}

// Default fallback images when no memories uploaded yet
$default_memories = [
    'Student Activities' => 'assets/img/education/Student Activities.jpg',
    'Academic Excellence' => 'assets/img/education/Excellence.jpg',
    'Sports'              => 'assets/img/education/Campus Life.jpg',
];

// ── CACHE BLOCK 2: School settings ───────────────────────────────────────────
$cached_settings = cache_get('settings:homepage');
if ($cached_settings === false) {
    $founding_year       = (int)get_setting($conn, 'school_founding_year', date('Y') - 7);
    $years_of_excellence = date('Y') - $founding_year;
    $about_photo         = get_setting($conn, 'about_photo', 'assets/img/front pic/Buyoan School.jpg');
    $cta_photo           = get_setting($conn, 'cta_photo',   'assets/img/education/Students learning.jpg');
    $cached_settings     = compact('founding_year', 'years_of_excellence', 'about_photo', 'cta_photo');
    cache_set('settings:homepage', $cached_settings, CACHE_TTL_SETTINGS);
} else {
    extract($cached_settings);
}

// ── CACHE BLOCK 3: Homepage cards ─────────────────────────────────────────────
$_empty_card = ['title' => '', 'description' => '', 'icon' => '', 'image' => ''];
foreach (['leadership', 'cultural', 'innovation'] as $_ck) {
    $_var         = 'card_' . $_ck;
    $_cached_card = cache_get("card:{$_ck}");
    if ($_cached_card === false) {
        $_cached_card = get_card($conn, $_ck);
        cache_set("card:{$_ck}", $_cached_card, CACHE_TTL_CARD);
    }
    $$_var = $_cached_card ?: $_empty_card;
}
// cert_card1/2/3 use their own variable names (not card_ prefix) — fix naming to match HTML
foreach (['cert_card1', 'cert_card2', 'cert_card3'] as $_ck) {
    $_cached_card = cache_get("card:{$_ck}");
    if ($_cached_card === false) {
        $_cached_card = get_card($conn, $_ck);
        cache_set("card:{$_ck}", $_cached_card, CACHE_TTL_CARD);
    }
    $$_ck = $_cached_card ?: $_empty_card;   // sets $cert_card1, $cert_card2, $cert_card3
} // Added closing bracket here

// ── CACHE BLOCK 4: Login handler ──────────────────────────────────────────────
$login_error = '';

// Handle admin login POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    include __DIR__ . '/db_connection.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username === '' || $password === '') {
        $login_error = 'Please enter username and password.';
    } else {
        // Check admin table first
        $admin = null;
        $stmt = $conn->prepare("SELECT id, password, school_email FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // If not found in admin, check sub_admin
        if (!$admin) {
            $stmt = $conn->prepare("SELECT id, password, email FROM sub_admin WHERE username = ? AND status = 'approved' LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Password correct - Generate OTP and set up session like BUNHS_School_System(2)
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Get email field (admin uses school_email, sub_admin uses email)
            $email = $admin['school_email'] ?? $admin['email'] ?? 'admin@bunhs.edu.ph';
            
            // Store OTP in session exactly like BUNHS_School_System(2)
            $_SESSION['otp_pending'] = [
                'otp'       => password_hash($otp, PASSWORD_DEFAULT),
                'user_id'   => (int) $admin['id'],
                'username'  => $username,
                'user_type' => ($admin['school_email'] ?? false) ? 'admin' : 'sub-admin',
                'email'     => $email,
                'expires'   => time() + 300,
            ];
            
            // For development, store the plain OTP for easy access
            $_SESSION['dev_otp'] = $otp;
            
            // Store login success for page display
            $_SESSION['login_success'] = true;
            $_SESSION['otp_sent_to'] = $email;
            
            // Set flag to show OTP verification on this page
            $_SESSION['show_otp_verification'] = true;
            
            // If this is an AJAX request, return JSON success response
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => 'OTP sent successfully',
                    'email' => $email,
                    'dev_otp' => $otp // For development
                ]);
                exit;
            }
            
            // For non-AJAX requests, continue to show the page with OTP verification
            // Don't redirect - just continue rendering the page
        } else {
            $login_error = 'Invalid username or password.';
        }
    }
    $conn->close();
}

// If this is an AJAX request and there's a login error, return it as JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && !empty($login_error)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $login_error]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="buyoan high school, buyoan national high school, BUNHS, buyoan school, buyoan, buyoan elementary, buyoan national high school website">

    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/img/logo.jpg" type="image/x-icon">

    <style>
        .verification-page {
            background-color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Roboto', sans-serif;
        }

        .verification-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
            padding: 48px;
            width: 100%;
            max-width: 460px;
            text-align: center;
        }

        .verification-logo {
            width: 80px;
            height: auto;
            margin-bottom: 24px;
        }

        .verification-title {
            font-size: 24px;
            font-weight: 500;
            color: #202124;
            margin-bottom: 16px;
        }

        .verification-text {
            font-size: 14px;
            color: #5f6368;
            line-height: 1.5;
            margin-bottom: 32px;
        }

        .verification-btn {
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color .2s;
            width: 100%;
            max-width: 120px;
        }

        .verification-btn:hover {
            background-color: #1557b0;
        }

        .verification-btn:disabled {
            background-color: #dadce0;
            cursor: not-allowed;
        }

        .verification-footer {
            margin-top: 32px;
            font-size: 12px;
            color: #5f6368;
            line-height: 1.4;
        }

        .verification-footer a {
            color: #1a73e8;
            text-decoration: none;
        }

        .verification-footer a:hover {
            text-decoration: underline;
        }

        .fade-in {
            animation: fadeIn .5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media(max-width:480px) {
            .verification-card {
                padding: 32px 24px;
                margin: 16px;
            }
        }

        /* ── Event Banner Closed State ── */
        .event-banner.school-closed {
            background: #c0392b !important;
            border-color: #922b21 !important;
        }

        .event-banner.school-closed h3,
        .event-banner.school-closed p,
        .event-banner.school-closed .month,
        .event-banner.school-closed .day {
            color: #fff !important;
        }

        .event-banner.school-closed .btn-register {
            background: #fff;
            color: #c0392b;
            border-color: #fff;
        }

        .event-banner.school-closed .btn-register:hover {
            background: #f8d7d7;
        }

        /* ── Clubs grid card ── */
        .club-showcase-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .07);
            padding: 20px;
            text-align: center;
            transition: .2s;
        }

        .club-showcase-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        }

        .club-showcase-card .club-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #eef4e8;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 26px;
            color: #4e6b32;
        }

        .club-showcase-card h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .club-showcase-card .member-count {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 12px;
        }

        .club-showcase-card .btn-learn {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            background: #3b975e;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .club-showcase-card .btn-learn:hover {
            background: #2d7a4e;
            color: #fff;
        }

        .no-clubs-msg {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 15px;
        }

        /* ── Join Us / btn-apply mobile responsiveness ── */
        #join-us-btn,
        .btn-apply {
            display: inline-block;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            -webkit-user-select: none;
            user-select: none;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .cta-buttons {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                width: 100%;
            }

            #join-us-btn,
            .btn-apply,
            .btn-tour {
                width: 100%;
                max-width: 280px;
                text-align: center;
                padding: 14px 20px;
                font-size: 16px;
                min-height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                box-sizing: border-box;
            }
        }

        @media (max-width: 480px) {

            #join-us-btn,
            .btn-apply,
            .btn-tour {
                max-width: 100%;
                font-size: 15px;
            }
        }
    </style>
</head>

<?php $isVerificationPage = isset($_GET['verify']) && !empty($_SESSION['signup_data']); ?>
<body class="<?php echo $isVerificationPage ? 'verification-page' : 'index-page'; ?>">

    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
            <a href="index.php" class="logo d-flex align-items-center">
                <img src="assets/img/Bagong_Pilipinas_logo.png" alt="School Logo" class="me-2" style="height:85px;width:auto;border-radius:20px;">
                <img src="assets/img/DepED logo circle.png" alt="School Logo" class="me-2" style="height:85px;width:auto;border-radius:0px;">
                <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height:85px;width:auto;border-radius:50px;">
                <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
            </a>
            <div id="nav-placeholder"></div>
        </div>
    </header>

    <!-- OTP Verification Section (shown when login is successful) -->
    <?php if (isset($_SESSION['show_otp_verification']) && $_SESSION['show_otp_verification'] === true): ?>
        <div class="verification-page" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div class="modal fade show" id="otpVerificationModal" style="display: block; position: relative;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="bm-hero">
                            <div class="bm-hero-grid"></div>
                            <button class="bm-hero-close" onclick="window.location.reload()"><i class="fas fa-times"></i></button>
                            <img src="assets/img/logo.jpg" alt="BUNHS" class="bm-hero-logo">
                            <div class="bm-hero-badge"><i class="fas fa-shield-halved"></i> Admin Portal</div>
                            <h2>Two-Step Verification</h2>
                            <p>Enter the code sent to <?php echo htmlspecialchars(maskEmail($_SESSION['otp_sent_to'] ?? 'your email')); ?></p>

                            <div class="bm-steps">
                                <div class="bm-step" id="lstep1">
                                    <div class="bm-step-dot">1</div><span>Login</span>
                                </div>
                                <div class="bm-step-line"></div>
                                <div class="bm-step active" id="lstep2">
                                    <div class="bm-step-dot">2</div><span>Verify</span>
                                </div>
                            </div>
                        </div>

                        <div class="bm-body">
                            <form id="pageOtpForm">
                                <div class="bm-otp-inputs" id="pageOtpBoxes" style="display: flex; justify-content: center; gap: 10px; margin: 20px 0;">
                                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                    <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                </div>
                                <input type="hidden" id="pageOtpHidden" name="otp">

                                <div class="bm-err" id="pageOtpErrBox">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span id="pageOtpErrTxt"></span>
                                </div>

                                <div class="bm-success" id="pageOtpSuccessBox">
                                    <i class="fas fa-check-circle"></i>
                                    <span id="pageOtpSuccessTxt"></span>
                                </div>

                                <button type="submit" class="bm-btn" id="pageVerifyBtn">
                                    <span class="bm-btn-label"><i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign In</span>
                                    <div class="bm-spinner"></div>
                                </button>

                                <div style="margin-top:14px; display:flex; align-items:center; justify-content:center; gap:12px;">
                                    <button type="button" class="bm-ghost" id="pageResendBtn" disabled>
                                        Resend · <span id="pageResendTimer">30</span>s
                                    </button>
                                    <span style="color:var(--bunhs-border); font-size:14px;">|</span>
                                    <button type="button" class="bm-ghost" onclick="window.location.reload()">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </button>
                                </div>

    <?php $isVerificationPage = isset($_GET['verify']) && !empty($_SESSION['signup_data']); ?>
    <body class="<?php echo $isVerificationPage ? 'verification-page' : 'index-page'; ?>">

        <header id="header" class="header d-flex align-items-center sticky-top">
            <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
                <a href="index.php" class="logo d-flex align-items-center">
                    <img src="assets/img/Bagong_Pilipinas_logo.png" alt="School Logo" class="me-2" style="height:85px;width:auto;border-radius:20px;">
                    <img src="assets/img/DepED logo circle.png" alt="School Logo" class="me-2" style="height:85px;width:auto;border-radius:0px;">
                    <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height:85px;width:auto;border-radius:50px;">
                    <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
                </a>
                <div id="nav-placeholder"></div>
            </div>
        </header>

        <!-- OTP Verification Section (shown when login is successful) -->
        <?php if (isset($_SESSION['show_otp_verification']) && $_SESSION['show_otp_verification'] === true): ?>
            <div class="verification-page" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div class="modal fade show" id="otpVerificationModal" style="display: block; position: relative;">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="bm-hero">
                                <div class="bm-hero-grid"></div>
                                <button class="bm-hero-close" onclick="window.location.reload()"><i class="fas fa-times"></i></button>
                                <img src="assets/img/logo.jpg" alt="BUNHS" class="bm-hero-logo">
                                <div class="bm-hero-badge"><i class="fas fa-shield-halved"></i> Admin Portal</div>
                                <h2>Two-Step Verification</h2>
                                <p>Enter the code sent to <?php echo htmlspecialchars(maskEmail($_SESSION['otp_sent_to'] ?? 'your email')); ?></p>

                                <div class="bm-steps">
                                    <div class="bm-step" id="lstep1">
                                        <div class="bm-step-dot">1</div><span>Login</span>
                                    </div>
                                    <div class="bm-step-line"></div>
                                    <div class="bm-step active" id="lstep2">
                                        <div class="bm-step-dot">2</div><span>Verify</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bm-body">
                                <form id="pageOtpForm">
                                    <div class="bm-otp-inputs" id="pageOtpBoxes" style="display: flex; justify-content: center; gap: 10px; margin: 20px 0;">
                                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                        <input class="bm-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                                    </div>
                                    <input type="hidden" id="pageOtpHidden" name="otp">

                                    <div class="bm-err" id="pageOtpErrBox">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <span id="pageOtpErrTxt"></span>
                                    </div>

                                    <div class="bm-success" id="pageOtpSuccessBox">
                                        <i class="fas fa-check-circle"></i>
                                        <span id="pageOtpSuccessTxt"></span>
                                    </div>

                                    <button type="submit" class="bm-btn" id="pageVerifyBtn">
                                        <span class="bm-btn-label"><i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign In</span>
                                        <div class="bm-spinner"></div>
                                    </button>

                                    <div style="margin-top:14px; display:flex; align-items:center; justify-content:center; gap:12px;">
                                        <button type="button" class="bm-ghost" id="pageResendBtn" disabled>
                                            Resend · <span id="pageResendTimer">30</span>s
                                        </button>
                                        <span style="color:var(--bunhs-border); font-size:14px;">|</span>
                                        <button type="button" class="bm-ghost" onclick="window.location.reload()">
                                            <i class="fas fa-arrow-left"></i> Back
                                        </button>
                                    </div>

                                    <div style="margin-top: 16px; text-align: center;">
                                        <small style="color: #666;">
                                            <i class="fas fa-clock"></i> Code expires in: <span id="pageTimer">05:00</span>
                                        </small>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <main class="main">

            <!-- ═══════════════════════════════════════════════
             HERO SECTION
        ═══════════════════════════════════════════════ -->
            <section id="hero" class="hero section">

                <div class="hero-container">
                    <div class="hero-content">
                        <h2 style="color:white;">Web-Based Information System for Buyoan National High School</h2>
                        <p></p>
                        <div class="cta-buttons">
                        </div>
                        <div class="announcement">
                            <div class="announcement-badge">New</div>
                            <p>2026 Enrollment Open - Early Decision Deadline December 15</p>
                        </div>
                    </div>
                </div>

                <!-- ── 3 Highlight Cards ── -->
                <div class="highlights-container container">
                    <div class="row gy-4">
                        <!-- Card 1: Batch Graduate Success -->
                        <div class="col-md-4">
                            <div class="highlight-item">
                                <div class="icon">
                                    <i class="fas fa-graduation-cap" style="color:#22775e;"></i>
                                </div>
                                <h3>
                                    <?php if ($batch_success_pct > 0): ?>
                                        <?php echo $batch_success_pct; ?>% Batch Graduate Success
                                    <?php else: ?>
                                        Batch Graduate Success
                                    <?php endif; ?>
                                </h3>
                                <p>
                                    <?php if ($batch_success_year): ?>
                                        <?php echo $batch_success_year; ?> batch completers graduation rate.
                                    <?php else: ?>
                                        Batch graduate completers tracked per enrollment year.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Card 2: Student-Faculty Ratio -->
                        <div class="col-md-4">
                            <div class="highlight-item">
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3><?php echo htmlspecialchars($ratio_display); ?> Student-Faculty Ratio</h3>
                                <p>Average number of students per faculty member, reflecting class size and learning support.</p>
                            </div>
                        </div>

                        <!-- Card 3: School Rating -->
                        <div class="col-md-4">
                            <div class="highlight-item">
                                <div class="icon">
                                    <i class="fa-solid fa-star" style="color:#3b975e;"></i>
                                </div>
                                <h3>School Rating</h3>
                                <div style="text-align:center;padding:5px 0;">
                                    <div style="font-size:36px;font-weight:bold;color:#312f2f;margin-bottom:5px;">
                                        <?php echo $school_rating_val > 0 ? number_format($school_rating_val, 1) : 'N/A'; ?>
                                    </div>
                                    <div style="margin-bottom:5px;">
                                        <?php echo $school_rating_val > 0 ? star_html($school_rating_val) : '<span style="color:#999;font-size:13px;">No ratings yet</span>'; ?>
                                    </div>
                                    <?php if ($school_rating_count > 0): ?>
                                        <div style="font-size:12px;color:#666;"><?php echo number_format($school_rating_count); ?> review<?php echo $school_rating_count !== 1 ? 's' : ''; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Event Banner ── -->
                <?php
                $banner_is_closed = $today_announcement && (int)$today_announcement['is_closed'] === 1;
                $banner_message   = '';
                $banner_date_obj  = null;

                if ($banner_is_closed) {
                    $banner_message = !empty($today_announcement['custom_message'])
                        ? htmlspecialchars($today_announcement['custom_message'])
                        : 'Announcement: "The school is closed today. All classes and school activities are suspended."';
                }

                // Use pre-fetched upcoming events for banner display
                $banner_event = count($upcoming_events) > 0 ? $upcoming_events[0] : null;
                ?>

                <div class="event-banner<?php echo $banner_is_closed ? ' school-closed' : ''; ?>">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <div class="event-date">
                                    <?php if ($banner_is_closed): ?>
                                        <span class="month" style="font-size:13px;font-weight:700;">CLOSED</span>
                                        <span class="day"><?php echo date('d'); ?></span>
                                    <?php elseif ($banner_event): ?>
                                        <span class="month"><?php echo strtoupper(date('M', strtotime($banner_event['event_date']))); ?></span>
                                        <span class="day"><?php echo date('j', strtotime($banner_event['event_date'])); ?></span>
                                    <?php else: ?>
                                        <span class="month">—</span>
                                        <span class="day">—</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <?php if ($banner_is_closed): ?>
                                    <h3>School Closure Notice</h3>
                                    <p><?php echo $banner_message; ?></p>
                                <?php elseif ($banner_event): ?>
                                    <h3><?php echo htmlspecialchars($banner_event['title']); ?></h3>
                                    <p><?php echo htmlspecialchars(mb_substr(strip_tags($banner_event['description']), 0, 160)) . (strlen(strip_tags($banner_event['description'])) > 160 ? '…' : ''); ?></p>
                                <?php else: ?>
                                    <h3>Open Campus Day</h3>
                                    <p>Experience our vibrant campus life, meet faculty members, and learn about our academic programs.</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <a href="events.php" class="btn-register">View</a>
                            </div>
                        </div>
                    </div>
                </div>

            </section><!-- /Hero Section -->


            <!-- ═══════════════════════════════════════════════
             CALL TO ACTION SECTION
        ═══════════════════════════════════════════════ -->
            <section id="call-to-action" class="call-to-action section light-background">
                <div class="container">
                    <div class="row align-items-center">

                        <div class="col-lg-5">
                            <div class="content-wrapper">
                                <div class="badge">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span>Premium Education</span>
                                </div>
                                <h2>Elevate Your Learning Journey with Buyoan National High School</h2>
                                <p>Discover unlimited potential through our carefully curated learning experiences designed by industry leaders and educational experts.</p>

                                <div class="highlight-stats">
                                    <div class="stat-group">
                                        <div class="stat-item">
                                            <span class="number purecounter" data-purecounter-start="0" data-purecounter-end="<?php echo $active_students; ?>" data-purecounter-duration="2"><?php echo $active_students; ?></span>
                                            <span class="label">Active Learners</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="number purecounter" data-purecounter-start="0" data-purecounter-end="<?php echo $total_subjects; ?>" data-purecounter-duration="2"><?php echo $total_subjects; ?></span>
                                            <span class="label">Subjects</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="action-buttons">
                                    <!-- Same destination as "Join Us" button at top -->
                                    <a href="#" class="btn-primary btn-signup">Explore Programs and Enroll Now</a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="visual-section">
                                <div class="main-image-container">
                                    <img src="<?php echo htmlspecialchars($cta_photo); ?>" alt="Students Learning" class="main-image">
                                    <div class="overlay-gradient"></div>
                                </div>

                                <!-- Feature cards (editable from admin_profile) -->
                                <div class="feature-cards">
                                    <div class="feature-card achievement">
                                        <div class="icon"><i class="fas <?php echo htmlspecialchars($cert_card1['icon'] ?: 'fa-trophy'); ?>"></i></div>
                                        <div class="content">
                                            <h4><?php echo htmlspecialchars($cert_card1['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($cert_card1['description']); ?></p>
                                        </div>
                                    </div>
                                    <div class="feature-card flexibility">
                                        <div class="icon"><i class="fas <?php echo htmlspecialchars($cert_card2['icon'] ?: 'fa-clock'); ?>"></i></div>
                                        <div class="content">
                                            <h4><?php echo htmlspecialchars($cert_card2['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($cert_card2['description']); ?></p>
                                        </div>
                                    </div>
                                    <div class="feature-card community">
                                        <div class="icon"><i class="fas <?php echo htmlspecialchars($cert_card3['icon'] ?: 'fa-users'); ?>"></i></div>
                                        <div class="content">
                                            <h4><?php echo htmlspecialchars($cert_card3['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($cert_card3['description']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section><!-- /Call To Action Section -->


            <!-- ═══════════════════════════════════════════════
             EVENTS — Rolling 4
        ═══════════════════════════════════════════════ -->
            <section id="events" class="events section">
                <div class="container section-title">
                    <h2>Events</h2>
                    <p>Buyoan National High School hosts a variety of events throughout the school year, including academic competitions, sports meets, cultural celebrations, and community outreach programs. These events aim to promote student engagement, teamwork, and holistic development, while showcasing the talents and creativity of our students.</p>
                </div>

                <div class="container">

                    <div class="event-filters mb-4">
                        <div class="row justify-content-center g-3">
                            <div class="col-md-4">
                                <select class="form-select" id="eventMonthFilter">
                                    <option value="">All Months</option>
                                    <?php
                                    $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                    foreach ($months as $i => $m) {
                                        echo '<option value="' . ($i + 1) . '">' . $m . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="eventCatFilter">
                                    <option value="">All Categories</option>
                                    <option>Academic</option>
                                    <option>Arts</option>
                                    <option>Sports</option>
                                    <option>Community</option>
                                    <option>Environmental</option>
                                    <option>Cultural</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4" id="events-grid">
                        <?php if (count($upcoming_events) > 0): ?>
                            <?php foreach ($upcoming_events as $event): ?>
                                <?php
                                $ev_month = strtoupper(date('M', strtotime($event['event_date'])));
                                $ev_day   = date('j', strtotime($event['event_date']));
                                $ev_year  = date('Y', strtotime($event['event_date']));
                                $ev_cat   = htmlspecialchars($event['category'] ?? 'General');
                                $ev_cat_lower = strtolower($event['category'] ?? 'general');
                                $ev_start = !empty($event['event_start_time']) ? date('h:i A', strtotime($event['event_start_time'])) : '';
                                $ev_end   = !empty($event['event_end_time'])   ? date('h:i A', strtotime($event['event_end_time']))   : '';
                                $ev_time  = $ev_start ? ($ev_end ? "$ev_start - $ev_end" : $ev_start) : 'TBA';
                                ?>
                                <div class="col-lg-6 event-item"
                                    data-month="<?php echo date('n', strtotime($event['event_date'])); ?>"
                                    data-category="<?php echo htmlspecialchars($event['category'] ?? ''); ?>">
                                    <div class="event-card" style="cursor:pointer;" onclick="window.location='event-details.php?id=<?php echo (int)$event['id']; ?>'">
                                        <div class="event-date">
                                            <span class="month"><?php echo $ev_month; ?></span>
                                            <span class="day"><?php echo $ev_day; ?></span>
                                            <span class="year"><?php echo $ev_year; ?></span>
                                        </div>
                                        <div class="event-content">
                                            <div class="event-tag <?php echo $ev_cat_lower; ?>"><?php echo $ev_cat; ?></div>
                                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                            <p><?php echo htmlspecialchars(mb_substr(strip_tags($event['description']), 0, 200)) . (strlen(strip_tags($event['description'])) > 200 ? '...' : ''); ?></p>
                                            <div class="event-meta">
                                                <div class="meta-item">
                                                    <i class="fas fa-clock"></i>
                                                    <span><?php echo $ev_time; ?></span>
                                                </div>
                                            </div>
                                            <div class="event-actions">
                                                <a href="event-details.php?id=<?php echo (int)$event['id']; ?>" class="btn-learn-more">Learn More</a>
                                                <a href="#" class="btn-calendar" onclick="event.stopPropagation()"><i class="fas fa-calendar-plus"></i> Add to Calendar</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-5 text-muted">
                                <i class="fas fa-calendar-times" style="font-size:48px;margin-bottom:16px;display:block;color:#ccc;"></i>
                                No upcoming events at the moment. Check back soon!
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-center mt-5">
                        <a href="event-details.php" class="btn-view-all">View All Events</a>
                    </div>

                </div>
            </section><!-- /Events Section -->

        </main>

        <!-- Footer Placeholder -->
        <div id="footer-placeholder"></div>

    <?php endif; ?>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="fas fa-arrow-up"></i></a>
    <!-- Preloader -->
    <div id="preloader"></div>

    <!-- Vendor JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- validate.js removed — not needed for this page -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@srexi/purecounterjs/dist/purecounter_vanilla.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <script src="assets/js/safe-event-listeners.js"></script>

    <!-- Main JavaScript -->
    <script src="assets/js/main.js"></script>

    <!-- Navigation -->
    <script>
        fetch('nav.php').then(r => r.text()).then(d => {
            const navElement = document.getElementById('nav-placeholder');
            if (navElement) {
                navElement.innerHTML = d;
            } else {
                console.warn('Navigation placeholder element not found');
            }
        }).catch(e => console.error('Error loading navigation:', e));
    </script>
    <!-- Events filter script -->
    <script>
        function applyEventFilters() {
            const monthFilter = document.getElementById('eventMonthFilter');
            const catFilter = document.getElementById('eventCatFilter');
            
            if (!monthFilter || !catFilter) {
                console.warn('Event filter elements not found');
                return;
            }
            
            const month = monthFilter.value;
            const cat = catFilter.value;
            document.querySelectorAll('#events-grid .event-item').forEach(function(item) {
                const mMatch = !month || item.dataset.month === month;
                const cMatch = !cat || item.dataset.category.toLowerCase() === cat.toLowerCase();
                item.style.display = (mMatch && cMatch) ? '' : 'none';
            });
        }
        
        const monthFilter = document.getElementById('eventMonthFilter');
        const catFilter = document.getElementById('eventCatFilter');
        if (monthFilter) monthFilter.addEventListener('change', applyEventFilters);
        if (catFilter) catFilter.addEventListener('change', applyEventFilters);
    </script>

    <!-- Modals + auth logic -->
    <script>
        try {
            fetch('modals.php')
                .then(r => r.text())
                .then(html => {
                    document.body.insertAdjacentHTML('beforeend', html);
                    console.log('Modals loaded successfully');
                })
                .catch(error => {
                    console.warn('Error loading modals:', error);
                });
        } catch (error) {
        }
    }).catch(function(e) { console.error('Error loading navigation:', e); });

    fetch('footer.php').then(function(r) { return r.text(); }).then(function(d) {
        const footerElement = document.getElementById('footer-placeholder');
        if (footerElement) {
            footerElement.innerHTML = d;
        } else {
            console.warn('Footer placeholder element not found');
        }
    }).catch(function(e) { console.error('Error loading footer:', e); });
        
    console.log('Script completed successfully!');
});
    </script>

</body>

</html>
                            document.getElementById('termsError').style.display = 'none';
                        }
                        
                        return isValid;
                    }
                    safeAddEventListener('toggleLoginPwd', 'click', function() {
                        var inp = document.getElementById('loginPassword');
                        var ico = document.getElementById('loginEyeIcon');
                        if (inp && ico) {
                            if (inp.type === 'password') {
                                inp.type = 'text';
                                ico.className = 'fas fa-eye-slash';
                            } else {
                                inp.type = 'password';
                                ico.className = 'fas fa-eye';
                            }
                        const otp = document.getElementById('loginOtpHidden').value;
                        if (otp.length !== 6) {
                            shakeOtp('loginOtpBoxes');
                            showErr('loginOtpErrBox', 'loginOtpErrTxt', 'Please enter all 6 digits.');
                            return;
                        }
                        setLoad('loginVerifyBtn', true);
                        const fd = new FormData();
                        fd.append('action', 'login_verify_otp');
                        fd.append('otp', otp);
                        fetch('login_otp.php', {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json()).then(d => {
                            setLoad('loginVerifyBtn', false);
                            if (d.success) {
                                // Route by role: students → Dashboard, admins/sub-admins → admin panel
                                if (d.user_type === 'student') {
                                    window.location.href = 'user_account/Dashboard.php';
                                } else {
                                    window.location.href = 'admin_account/admin_dashboard.php';
                                }
                            } else {
                                shakeOtp('loginOtpBoxes');
                                showErr('loginOtpErrBox', 'loginOtpErrTxt', d.message || 'Invalid code.');
                                clearOtp('loginOtpBoxes');
                                document.getElementById('loginOtpHidden').value = '';
                                document.querySelector('#loginOtpBoxes .bm-otp-box').focus();
                            }
                        }).catch(() => {
                            setLoad('loginVerifyBtn', false);
                            showErr('loginOtpErrBox', 'loginOtpErrTxt', 'Connection error.');
                        });
                    });

                    document.getElementById('loginResendBtn').addEventListener('click', () => {
                        const rb = document.getElementById('loginResendBtn');
                        rb.disabled = true;
                        rb.classList.remove('on');
                        const fd = new FormData();
                        fd.append('action', 'login_resend_otp');
                        fetch('login_otp.php', {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json()).then(d => {
                            if (d.success) {
                                rb.innerHTML = 'Resend · <span id="loginResendTimer">30</span>s';
                                mmss('loginTimerVal', 'loginTimer', 300);
                                cdwn('loginResendTimer', 30, () => {
                                    rb.disabled = false;
                                    rb.innerHTML = 'Resend code';
                                    rb.classList.add('on');
                                });
                                clearOtp('loginOtpBoxes');
                                document.getElementById('loginOtpHidden').value = '';
                            } else {
                                showErr('loginOtpErrBox', 'loginOtpErrTxt', d.message);
                            }
                        });
                    });

                    document.getElementById('loginBackBtn').addEventListener('click', () => {
                        document.getElementById('loginStep2').style.display = 'none';
                        document.getElementById('loginStep1').style.display = 'block';
                        clearOtp('loginOtpBoxes');
                        document.getElementById('loginOtpHidden').value = '';
                        hideErr('loginOtpErrBox');
                        hideErr('loginErrBox');
                    });

                    document.getElementById('loginModal').addEventListener('hidden.bs.modal', () => {
                        document.getElementById('loginStep1').style.display = 'block';
                        document.getElementById('loginStep2').style.display = 'none';
                        document.getElementById('loginCredentialsForm').reset();
                        clearOtp('loginOtpBoxes');
                        document.getElementById('loginOtpHidden').value = '';
                        hideErr('loginErrBox');
                        hideErr('loginOtpErrBox');
                    });

                    document.getElementById('toggleSignupPwd').addEventListener('click', function() {
                        var inp = document.getElementById('signupPassword');
                        var ico = document.getElementById('signupEyeIcon');
                        if (inp.type === 'password') {
                            inp.type = 'text';
                            ico.className = 'fas fa-eye-slash';
                        } else {
                            inp.type = 'password';
                            ico.className = 'fas fa-eye';
                        }
                    });

                    document.getElementById('toggleSignupConfirmPwd').addEventListener('click', function() {
                        var inp = document.getElementById('signupConfirmPassword');
                        var ico = document.getElementById('signupConfirmEyeIcon');
                        if (inp.type === 'password') {
                            inp.type = 'text';
                            ico.className = 'fas fa-eye-slash';
                        } else {
                            inp.type = 'password';
                            ico.className = 'fas fa-eye';
                        }
                    });

                    function toggleContact() {
                        const em = document.getElementById('contactEmail').checked;
                        document.getElementById('emailField').style.display = em ? '' : 'none';
                        document.getElementById('phoneField').style.display = em ? 'none' : '';
                        document.getElementById('email').required = em;
                        document.getElementById('phone').required = !em;
                        if (!em) document.getElementById('email').value = '';
                        else document.getElementById('phone').value = '';
                    }
                    document.getElementById('contactEmail').addEventListener('change', toggleContact);
                    document.getElementById('contactPhone').addEventListener('change', toggleContact);

                    document.getElementById('otpForm').addEventListener('submit', e => {
                        e.preventDefault();
                        hideErr('otpErrBox');
                        const otp = document.getElementById('signupOtpHidden').value;
                        if (otp.length !== 6) {
                            shakeOtp('signupOtpBoxes');
                            showErr('otpErrBox', 'otpErrTxt', 'Please enter all 6 digits.');
                            return;
                        }
                        setLoad('otpVerifyBtn', true);
                        const fd = new FormData();
                        fd.append('action', 'verify_otp');
                        fd.append('otp', otp);
                        fd.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>');
                        fetch('signup.php', {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json()).then(d => {
                            setLoad('otpVerifyBtn', false);
                            if (d.success) {
                                // Sub-admin accounts are PENDING — do NOT redirect to Dashboard.
                                // Close the modal and show a friendly approval-pending notice.
                                bootstrap.Modal.getInstance(document.getElementById('signupModal')).hide();
                                // Show a toast / inline banner so the user knows what happened
                                const msg = d.message || 'Account created! Awaiting admin approval.';
                                // Re-use the page-level toast helper if available, otherwise alert
                                if (typeof toast === 'function') {
                                    toast(msg, 'success');
                                } else {
                                    // Inject a non-blocking green banner at the top of the page
                                    const banner = document.createElement('div');
                                    banner.style.cssText = 'position:fixed;top:70px;left:50%;transform:translateX(-50%);z-index:99999;background:#1a3a2a;color:#fff;padding:14px 28px;border-radius:12px;font-family:"DM Sans",sans-serif;font-size:14px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,.25);display:flex;align-items:center;gap:10px;max-width:520px;text-align:center;';
                                    banner.innerHTML = '<i class="fas fa-check-circle" style="color:#52b788;font-size:18px;flex-shrink:0;"></i><span>' + msg + '</span>';
                                    document.body.appendChild(banner);
                                    setTimeout(() => banner.remove(), 7000);
                                }
                            } else {
                                shakeOtp('signupOtpBoxes');
                                showErr('otpErrBox', 'otpErrTxt', d.message || 'Invalid code.');
                                clearOtp('signupOtpBoxes');
                                document.getElementById('signupOtpHidden').value = '';
                                document.querySelector('#signupOtpBoxes .bm-otp-box').focus();
                            }
                        }).catch(() => {
                            setLoad('otpVerifyBtn', false);
                            showErr('otpErrBox', 'otpErrTxt', 'Connection error.');
                        });
                    });

                    document.getElementById('resendOtpBtn').addEventListener('click', () => {
                        const rb = document.getElementById('resendOtpBtn');
                        rb.disabled = true;
                        rb.classList.remove('on');
                        const fd = new FormData();
                        fd.append('action', 'resend_otp');
                        fd.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>');
                        fetch('signup.php', {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json()).then(d => {
                            if (d.success) {
                                toast(d.message, 'success');
                                rb.innerHTML = "Didn't receive it? Resend · <span id='resendTimer'>30</span>s";
                                mmss('otpCountdown', 'signupTimer', 300);
                                cdwn('resendTimer', 30, () => {
                                    rb.disabled = false;
                                    rb.innerHTML = "Didn't receive it? Resend";
                                    rb.classList.add('on');
                                });
                                clearOtp('signupOtpBoxes');
                                document.getElementById('signupOtpHidden').value = '';
                                document.querySelector('#signupOtpBoxes .bm-otp-box').focus();
                            } else {
                                showErr('otpErrBox', 'otpErrTxt', d.message);
                                if (!d.message.includes('limit')) rb.disabled = false;
                            }
                        });
                    });

                    document.getElementById('signupModal').addEventListener('hidden.bs.modal', () => {
                        document.getElementById('signupForm').reset();
                        document.getElementById('signupFormContainer').style.display = 'block';
                        document.getElementById('otpFormContainer').style.display = 'none';
                        document.getElementById('spill1').classList.add('active');
                        document.getElementById('spill2').classList.remove('active');
                        clearOtp('signupOtpBoxes');
                        document.getElementById('signupOtpHidden').value = '';
                        const w = document.getElementById('emailWarning');
                        if (w) w.style.display = 'none';
                        hideErr('signupErrBox');
                        hideErr('otpErrBox');
                        document.getElementById('contactEmail').checked = true;
                        toggleContact();
                    });

// Initialize OTP boxes for admin forms
                    initOtp('loginOtpBoxes', 'loginOtpHidden');
                    initOtp('signupOtpBoxes', 'signupOtpHidden');

                    // Login credentials form handler
                    const loginCredentialsForm = document.getElementById('loginCredentialsForm');
                    if (loginCredentialsForm) {
                        loginCredentialsForm.addEventListener('submit', async (e) => {
                            e.preventDefault();
                            hideErr('loginErrBox');
                            setLoad('loginSubmitBtn', true);

                            const formData = new FormData(loginCredentialsForm);
                            
                            try {
                                const response = await fetch('index.php', {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                });

                                const result = await response.json();

                                if (result.success) {
                                    // OTP was sent successfully, transition to step 2
                                    transitionToOtpStep();
                                    
                                    // Log development OTP if available
                                    if (result.dev_otp) {
                                        console.log('[DEV] Login OTP:', result.dev_otp);
                                    }
                                } else {
                                    showErr('loginErrBox', 'loginErrTxt', result.message || 'Login failed. Please try again.');
                                }
                            } catch (error) {
                                showErr('loginErrBox', 'loginErrTxt', 'Connection error. Please try again.');
                            } finally {
                                setLoad('loginSubmitBtn', false);
                            }
                        });
                    }

                    // Function to transition to OTP step with animation
                    function transitionToOtpStep() {
                        const step1 = document.getElementById('loginStep1');
                        const step2 = document.getElementById('loginStep2');
                        const step1Dot = document.getElementById('lstep1');
                        const step2Dot = document.getElementById('lstep2');

                        if (!step1 || !step2) return;

                        // Fade out step 1
                        step1.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        step1.style.opacity = '0';
                        step1.style.transform = 'translateX(-20px)';

                        setTimeout(() => {
                            // Hide step 1 and show step 2
                            step1.style.display = 'none';
                            step2.style.display = 'block';
                            step2.style.opacity = '0';
                            step2.style.transform = 'translateX(20px)';

                            // Update step indicators
                            if (step1Dot) step1Dot.classList.remove('active');
                            if (step2Dot) step2Dot.classList.add('active');

                            // Update hero text
                            const heroTitle = document.querySelector('#loginModal .bm-hero h2');
                            const heroSubtitle = document.querySelector('#loginModal .bm-hero p');
                            if (heroTitle) heroTitle.textContent = 'Two-Step Verification';
                            if (heroSubtitle) heroSubtitle.textContent = 'Enter the code sent to your email';

                            setTimeout(() => {
                                // Fade in step 2
                                step2.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                                step2.style.opacity = '1';
                                step2.style.transform = 'translateX(0)';

                                // Focus first OTP box
                                const firstOtpBox = document.querySelector('#loginOtpBoxes .bm-otp-box');
                                if (firstOtpBox) firstOtpBox.focus();

                                // Start timer
                                mmss('loginTimerVal', 'loginTimer', 300);

                                // Start resend countdown
                                const resendBtn = document.getElementById('loginResendBtn');
                                if (resendBtn) {
                                    resendBtn.disabled = true;
                                    resendBtn.classList.remove('on');
                                    cdwn('loginResendTimer', 30, () => {
                                        resendBtn.disabled = false;
                                        resendBtn.innerHTML = 'Resend code';
                                        resendBtn.classList.add('on');
                                    });
                                }
                            }, 50);
                        }, 300);
                    }

                    // Function to transition back to login step
                    function transitionToLoginStep() {
                        const step1 = document.getElementById('loginStep1');
                        const step2 = document.getElementById('loginStep2');
                        const step1Dot = document.getElementById('lstep1');
                        const step2Dot = document.getElementById('lstep2');

                        if (!step1 || !step2) return;

                        // Fade out step 2
                        step2.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        step2.style.opacity = '0';
                        step2.style.transform = 'translateX(20px)';

                        setTimeout(() => {
                            // Hide step 2 and show step 1
                            step2.style.display = 'none';
                            step1.style.display = 'block';
                            step1.style.opacity = '0';
                            step1.style.transform = 'translateX(-20px)';

                            // Update step indicators
                            if (step2Dot) step2Dot.classList.remove('active');
                            if (step1Dot) step1Dot.classList.add('active');

                            // Update hero text
                            const heroTitle = document.querySelector('#loginModal .bm-hero h2');
                            const heroSubtitle = document.querySelector('#loginModal .bm-hero p');
                            if (heroTitle) heroTitle.textContent = 'Welcome Back';
                            if (heroSubtitle) heroSubtitle.textContent = 'Sign in to access the school management system';

                            setTimeout(() => {
                                // Fade in step 1
                                step1.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                                step1.style.opacity = '1';
                                step1.style.transform = 'translateX(0)';

                                // Focus username field
                                const usernameField = document.getElementById('loginUsername');
                                if (usernameField) usernameField.focus();
                            }, 50);
                        }, 300);
                    }

                    // OTP verification form handler
                    const loginOtpForm = document.getElementById('loginOtpForm');
                    if (loginOtpForm) {
                        loginOtpForm.addEventListener('submit', async (e) => {
                            e.preventDefault();

                            const otp = document.getElementById('loginOtpHidden').value;
                            if (otp.length !== 6) {
                                shakeOtp('loginOtpBoxes');
                                showErr('loginOtpErrBox', 'loginOtpErrTxt', 'Please enter all 6 digits.');
                                return;
                            }

                            setLoad('loginVerifyBtn', true);

                            try {
                                const formData = new FormData();
                                formData.append('action', 'login_verify_otp');
                                formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
                                formData.append('otp', otp);

                                const response = await fetch('login_otp_fixed.php', {
                                    method: 'POST',
                                    body: formData
                                });

                                const result = await response.json();

                                if (result.success) {
                                    showErr('loginOtpErrBox', 'loginOtpErrTxt', '');
                                    showSuccess('loginOtpSuccessBox', 'loginOtpSuccessTxt', 'Verification successful! Redirecting...');

                                    setTimeout(() => {
                                        const userType = result.user_type || 'admin';
                                        if (userType === 'admin' || userType === 'sub-admin') {
                                            window.location.href = 'admin_account/admin_dashboard.php';
                                        } else {
                                            window.location.href = 'index.php';
                                        }
                                    }, 1000);
                                } else {
                                    shakeOtp('loginOtpBoxes');
                                    showErr('loginOtpErrBox', 'loginOtpErrTxt', result.message || 'Invalid code. Please try again.');
                                    clearOtp('loginOtpBoxes');
                                    document.getElementById('loginOtpHidden').value = '';
                                    document.querySelector('#loginOtpBoxes .bm-otp-box').focus();
                                }
                            } catch (error) {
                                showErr('loginOtpErrBox', 'loginOtpErrTxt', 'Connection error. Please try again.');
                            } finally {
                                setLoad('loginVerifyBtn', false);
                            }
                        });
                    }

                    // Resend OTP button handler
                    const loginResendBtn = document.getElementById('loginResendBtn');
                    if (loginResendBtn) {
                        loginResendBtn.addEventListener('click', async () => {
                            loginResendBtn.disabled = true;
                            loginResendBtn.classList.remove('on');
                            const originalHtml = loginResendBtn.innerHTML;
                            loginResendBtn.innerHTML = 'Sending...';

                            try {
                                const formData = new FormData();
                                formData.append('action', 'login_resend_otp');

                                const response = await fetch('login_otp_fixed.php', {
                                    method: 'POST',
                                    body: formData
                                });

                                const result = await response.json();

                                if (result.success) {
                                    // Reset timer
                                    mmss('loginTimerVal', 'loginTimer', 300);

                                    // Clear OTP boxes
                                    clearOtp('loginOtpBoxes');
                                    document.getElementById('loginOtpHidden').value = '';
                                    document.querySelector('#loginOtpBoxes .bm-otp-box').focus();

                                    // Show success message
                                    showSuccess('loginOtpSuccessBox', 'loginOtpSuccessTxt', 'New code sent!');
                                    setTimeout(() => {
                                        const successBox = document.getElementById('loginOtpSuccessBox');
                                        if (successBox) successBox.classList.remove('show');
                                    }, 3000);

                                    // Restart resend countdown
                                    loginResendBtn.innerHTML = 'Resend · <span id="loginResendTimer">30</span>s';
                                    cdwn('loginResendTimer', 30, () => {
                                        loginResendBtn.disabled = false;
                                        loginResendBtn.innerHTML = 'Resend code';
                                        loginResendBtn.classList.add('on');
                                    });
                                } else {
                                    showErr('loginOtpErrBox', 'loginOtpErrTxt', result.message || 'Could not resend code.');
                                    loginResendBtn.innerHTML = originalHtml;
                                    loginResendBtn.disabled = false;
                                }
                            } catch (error) {
                                showErr('loginOtpErrBox', 'loginOtpErrTxt', 'Connection error. Please try again.');
                                loginResendBtn.innerHTML = originalHtml;
                                loginResendBtn.disabled = false;
                            }
                        });
                    }

                    // Back button handler
                    const loginBackBtn = document.getElementById('loginBackBtn');
                    if (loginBackBtn) {
                        loginBackBtn.addEventListener('click', () => {
                            transitionToLoginStep();
                            clearOtp('loginOtpBoxes');
                            document.getElementById('loginOtpHidden').value = '';
                            hideErr('loginOtpErrBox');
                            hideErr('loginErrBox');
                        });
                    }

                    // Add success message function
                    function showSuccess(boxId, txtId, msg) {
                        const b = document.getElementById(boxId);
                        const t = document.getElementById(txtId);
                        if (b) {
                            b.classList.add('show');
                            b.classList.remove('show-err');
                            b.classList.add('show-success');
                        }
                        if (t) t.textContent = msg;
                    }
                })();
            })
            .catch(err => console.error('Error loading modals:', err));

        // Page OTP Verification Form Handler
        const pageOtpForm = document.getElementById('pageOtpForm');
        if (pageOtpForm) {
            // Initialize OTP boxes with modal styling
            const otpBoxes = pageOtpForm.querySelectorAll('.bm-otp-box');
            const otpHidden = document.getElementById('pageOtpHidden');
            
            // OTP input handling
            otpBoxes.forEach((box, index) => {
                box.addEventListener('input', (e) => {
                    const value = e.target.value.replace(/\D/g, '').slice(-1);
                    e.target.value = value;
                    
                    // Update hidden field
                    otpHidden.value = Array.from(otpBoxes).map(b => b.value).join('');
                    
                    // Auto-focus next box
                    if (value && index < otpBoxes.length - 1) {
                        otpBoxes[index + 1].focus();
                    }
                });
                
                box.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        otpBoxes[index - 1].value = '';
                        otpBoxes[index - 1].focus();
                        otpHidden.value = Array.from(otpBoxes).map(b => b.value).join('');
                    }
                });
                
                box.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                    pastedData.split('').forEach((char, i) => {
                        if (otpBoxes[i]) {
                            otpBoxes[i].value = char;
                        }
                    });
                    otpHidden.value = pastedData;
                    otpBoxes[Math.min(pastedData.length, otpBoxes.length - 1)].focus();
                });
            });
            
            // Form submission
            pageOtpForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const otp = otpHidden.value;
                if (otp.length !== 6) {
                    document.getElementById('pageOtpErrBox').classList.add('show');
                    document.getElementById('pageOtpErrTxt').textContent = 'Please enter all 6 digits.';
                    document.getElementById('pageOtpSuccessBox').classList.remove('show');
                    return;
                }
                
                const verifyBtn = document.getElementById('pageVerifyBtn');
                setLoad('pageVerifyBtn', true);
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'login_verify_otp');
                    formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>');
                    formData.append('otp', otp);
                    
                    const response = await fetch('login_otp_fixed.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById('pageOtpErrBox').classList.remove('show');
                        document.getElementById('pageOtpSuccessBox').classList.add('show');
                        document.getElementById('pageOtpSuccessTxt').textContent = 'Verification successful! Redirecting...';
                        
                        setTimeout(() => {
                            window.location.href = 'admin_account/admin_dashboard.php';
                        }, 1500);
                    } else {
                        document.getElementById('pageOtpErrBox').classList.add('show');
                        document.getElementById('pageOtpErrTxt').textContent = result.message || 'Invalid code. Please try again.';
                        document.getElementById('pageOtpSuccessBox').classList.remove('show');
                        
                        // Clear OTP boxes
                        otpBoxes.forEach(box => box.value = '');
                        otpHidden.value = '';
                        otpBoxes[0].focus();
                    }
                } catch (error) {
                    document.getElementById('pageOtpErrBox').classList.add('show');
                    document.getElementById('pageOtpErrTxt').textContent = 'Connection error. Please try again.';
                    document.getElementById('pageOtpSuccessBox').classList.remove('show');
                } finally {
                    setLoad('pageVerifyBtn', false);
                }
            });
            
            // Resend button
            const pageResendBtn = document.getElementById('pageResendBtn');
            if (pageResendBtn) {
                pageResendBtn.addEventListener('click', async () => {
                    pageResendBtn.disabled = true;
                    pageResendBtn.classList.remove('on');
                    const originalHtml = pageResendBtn.innerHTML;
                    pageResendBtn.innerHTML = 'Sending...';
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'login_resend_otp');
                        
                        const response = await fetch('login_otp_fixed.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            document.getElementById('pageOtpSuccessBox').classList.add('show');
                            document.getElementById('pageOtpSuccessTxt').textContent = 'New code sent!';
                            document.getElementById('pageOtpErrBox').classList.remove('show');
                            
                            // Clear OTP boxes
                            otpBoxes.forEach(box => box.value = '');
                            otpHidden.value = '';
                            otpBoxes[0].focus();
                            
                            // Reset timer
                            startPageTimer();
                            
                            setTimeout(() => {
                                document.getElementById('pageOtpSuccessBox').classList.remove('show');
                            }, 3000);
                            
                            // Restart resend countdown
                            pageResendBtn.innerHTML = 'Resend · <span id="pageResendTimer">30</span>s';
                            cdwn('pageResendTimer', 30, () => {
                                pageResendBtn.disabled = false;
                                pageResendBtn.innerHTML = 'Resend';
                                pageResendBtn.classList.add('on');
                            });
                        } else {
                            document.getElementById('pageOtpErrBox').classList.add('show');
                            document.getElementById('pageOtpErrTxt').textContent = result.message || 'Could not resend code.';
                            pageResendBtn.innerHTML = originalHtml;
                            pageResendBtn.disabled = false;
                        }
                    } catch (error) {
                        document.getElementById('pageOtpErrBox').classList.add('show');
                        document.getElementById('pageOtpErrTxt').textContent = 'Connection error. Please try again.';
                        pageResendBtn.innerHTML = originalHtml;
                        pageResendBtn.disabled = false;
                    }
                });
            }
            
            // Timer functionality
            let pageTimerInterval;
            function startPageTimer() {
                clearInterval(pageTimerInterval);
                let timeLeft = 300; // 5 minutes
                
                pageTimerInterval = setInterval(() => {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    document.getElementById('pageTimer').textContent = 
                        `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                    
                    if (timeLeft <= 0) {
                        clearInterval(pageTimerInterval);
                        document.getElementById('pageTimer').textContent = 'Expired';
                        document.getElementById('pageVerifyBtn').disabled = true;
                    }
                    
                    timeLeft--;
                }, 1000);
            }
            
            // Start timer when page loads
            if (document.getElementById('pageTimer')) {
                startPageTimer();
            }

            // Load navigation and footer after DOM is ready
            fetch('nav.php').then(function(r) { return r.text(); }).then(function(d) {
                const navElement = document.getElementById('nav-placeholder');
                if (navElement) {
                    navElement.innerHTML = d;
                } else {
                    console.warn('Navigation placeholder element not found');
                }
            }).catch(function(e) { console.error('Error loading navigation:', e); });

            fetch('footer.php').then(function(r) { return r.text(); }).then(function(d) {
                const footerElement = document.getElementById('footer-placeholder');
                if (footerElement) {
                    footerElement.innerHTML = d;
                } else {
                    console.warn('Footer placeholder element not found');
                }
            }).catch(function(e) { console.error('Error loading footer:', e); });
        }); // Close DOMContentLoaded
        console.log('Script completed successfully!');
    })();
    </script>

</body>

</html>