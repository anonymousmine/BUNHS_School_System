<?php
// ─── Session + Cache + DB ─────────────────────────────────────────────────────
require_once 'session_config.php';
require_once 'cache_helper.php';

// ── Safe DB include with error recovery ──────────────────────────────────────
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

    $recent_news = [];
    $res = $conn->query("SELECT * FROM news WHERE news_date <= CURDATE() ORDER BY news_date DESC, created_at DESC LIMIT 4");
    if ($res) {
        while ($row = $res->fetch_assoc()) $recent_news[] = $row;
    }
    if (count($recent_news) < 4) {
        $need    = 4 - count($recent_news);
        $ids_in  = array_map(fn($n) => (int)$n['id'], $recent_news);
        $exclude = count($ids_in) ? 'AND id NOT IN (' . implode(',', $ids_in) . ')' : '';
        $res2 = $conn->query("SELECT * FROM news WHERE news_date > CURDATE() $exclude ORDER BY news_date ASC LIMIT $need");
        if ($res2) while ($row = $res2->fetch_assoc()) $recent_news[] = $row;
    }

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
        'recent_news',
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
foreach (['leadership', 'cultural', 'innovation', 'cert_card1', 'cert_card2', 'cert_card3'] as $_ck) {
    $_var        = 'card_' . $_ck;
    $_cached_card = cache_get("card:{$_ck}");
    if ($_cached_card === false) {
        $_cached_card = get_card($conn, $_ck);
        cache_set("card:{$_ck}", $_cached_card, CACHE_TTL_CARD);
    }
    $$_var = $_cached_card;
}
// Variables now available: $card_leadership, $card_cultural, $card_innovation,
//                          $cert_card1, $cert_card2, $cert_card3

// ── CACHE BLOCK 4: Login handler ──────────────────────────────────────────────
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username  = sanitizeInput($_POST['username']);
    $password  = $_POST['password'];
    $client_ip = getClientIP();

    if (isRateLimited($client_ip)) {
        $login_error = 'Too many failed attempts. Please try again later.';
        logLoginAttempt($username, $client_ip, false);
    } elseif (empty($username) || empty($password)) {
        $login_error = 'Please fill in all fields.';
        recordLoginAttempt($client_ip);
        logLoginAttempt($username, $client_ip, false);
    } elseif (strlen($username) > 50 || strlen($password) > 255) {
        $login_error = 'Invalid input length.';
        recordLoginAttempt($client_ip);
        logLoginAttempt($username, $client_ip, false);
    } else {
        $user_found = false;
        $user_data  = null;
        $user_type  = '';

        // Try admin cache first
        $cached_admin = cache_get("admin:{$username}");
        if ($cached_admin !== false) {
            $user_data  = $cached_admin;
            $user_type  = 'admin';
            $user_found = true;
        } else {
            $stmt = $conn->prepare("SELECT id, password FROM admin WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result->num_rows === 1) {
                        $user_data  = $result->fetch_assoc();
                        $user_type  = 'admin';
                        $user_found = true;
                        cache_set("admin:{$username}", $user_data, CACHE_TTL_CREDENTIALS);
                    }
                }
                $stmt->close();
            }
        }

        // Try sub-admin cache if admin not found
        if (!$user_found) {
            $cached_sub = cache_get("subadmin:{$username}");
            if ($cached_sub !== false && ($cached_sub['status'] ?? '') === 'approved') {
                $user_data  = $cached_sub;
                $user_type  = 'sub-admin';
                $user_found = true;
            } else {
                $stmt = $conn->prepare("SELECT id, password, status FROM `sub_admin` WHERE username = ? AND status = 'approved'");
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        if ($result->num_rows === 1) {
                            $user_data  = $result->fetch_assoc();
                            $user_type  = 'sub-admin';
                            $user_found = true;
                            cache_set("subadmin:{$username}", $user_data, CACHE_TTL_CREDENTIALS);
                        }
                    }
                    $stmt->close();
                }
            }
        }

        // password_verify() always runs — cache never bypasses this
        if ($user_found && password_verify($password, $user_data['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user_data['id'];
            $_SESSION['username']   = $username;
            $_SESSION['user_type']  = $user_type;
            $_SESSION['login_time'] = time();
            unset($_SESSION['login_attempts'][$client_ip]);
            logLoginAttempt($username, $client_ip, true);
            header('Location: admin_account/admin_dashboard.php');
            exit();
        } else {
            $login_error = 'Invalid username or password.';
            recordLoginAttempt($client_ip);
            logLoginAttempt($username, $client_ip, false);
        }
    }

    if (
        !empty($login_error)
        && $login_error !== 'Invalid request. Please try again.'
        && $login_error !== 'Too many failed attempts. Please try again later.'
    ) {
        sleep(1);
    }
}

$conn->close();
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

    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
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

    <?php if ($isVerificationPage): ?>
        <div class="verification-card fade-in">
            <img src="assets/img/logo.jpg" alt="School Logo" class="verification-logo">
            <h1 class="verification-title">Verify your email</h1>
            <p class="verification-text">To continue, first verify it's you. We will send a verification code to <?php echo maskEmail($_SESSION['signup_data']['email']); ?>.</p>
            <button id="sendOtpBtn" class="verification-btn">Send</button>
            <div class="verification-footer">
                <p>Not your computer? Use Guest mode to start your session privately.</p>
                <p><a href="privacy.php">Learn more</a> about using Guest mode</p>
                <p>Use is subject to the <a href="privacy.php">Privacy Policy</a></p>
            </div>
        </div>
    <?php else: ?>

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
                            <a href="#" class="btn-apply" id="joinUsBtn">Join us</a>
                            <a href="#" class="btn-tour" id="createAccountBtn">Create Account</a>
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
             ABOUT SECTION — Nurturing Learners
        ═══════════════════════════════════════════════ -->
            <section id="about" class="about section">
                <div class="container">
                    <div class="row gy-5">
                        <div class="col-lg-6">
                            <div class="content">
                                <h3>Nurturing Learners, Building the Nation</h3>
                                <p>For <?php echo $years_of_excellence; ?> years, Buyoan National High School has been shaping young minds through quality, culture-based education in a safe and caring community empowering every learner to grow, achieve their dreams, and contribute to a brighter nation.</p>

                                <div class="stats-row">
                                    <div class="stat-item">
                                        <div class="number"><?php echo number_format($total_students); ?></div>
                                        <div class="label">Students Enrolled</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number"><?php echo $graduation_rate > 0 ? $graduation_rate . '%' : 'N/A'; ?></div>
                                        <div class="label">Graduation Rate</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number"><?php echo $total_teachers; ?></div>
                                        <div class="label">Expert Faculty</div>
                                    </div>
                                </div>

                                <div class="mission-statement">
                                    <p><em>"Young Man Think Big! Aspire, Succeed..."</em></p>
                                </div>

                                <a href="about.php" class="btn-learn-more">
                                    Learn More About Us
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="image-wrapper">
                                <img src="<?php echo htmlspecialchars($about_photo); ?>" alt="Campus Overview" class="img-fluid">
                                <div class="experience-badge">
                                    <div class="years"><?php echo $years_of_excellence; ?>+</div>
                                    <div class="text">Years of Excellence</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section><!-- /About Section -->


            <!-- ═══════════════════════════════════════════════
             FEATURED PROGRAMS — now shows Clubs dynamically
        ═══════════════════════════════════════════════ -->
            <section id="featured-programs" class="featured-programs section">
                <div class="container section-title">
                    <h2>Featured Programs</h2>
                    <p>We offer programs that inspire and equip aspiring students to reach their full potential.</p>
                </div>

                <div class="container">
                    <div class="featured-programs-wrapper">

                        <div class="programs-overview">
                            <div class="overview-content">
                                <h2>Discover Excellence in Education</h2>
                                <p>Buyoan National High School exemplifies excellence in education through its unwavering commitment to academic achievement, values formation, and community partnership empowering both teachers and students to grow, innovate, and lead in an ever-changing world.</p>
                                <div class="overview-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $active_students > 0 ? $active_students : $total_students; ?></span>
                                        <span class="stat-label">Active Students</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $total_subjects > 0 ? $total_subjects . '+' : '0'; ?></span>
                                        <span class="stat-label">Subjects Taught</span>
                                    </div>
                                </div>
                            </div>
                            <div class="overview-image">
                                <img src="assets/img/education/Education.jpg" alt="Education" class="img-fluid">
                            </div>
                        </div>

                        <!-- ── Clubs Showcase Grid ── -->
                        <div class="programs-showcase" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:20px;margin-top:30px;">
                            <?php if (count($clubs_list) > 0): ?>
                                <?php foreach ($clubs_list as $club): ?>
                                    <div class="club-showcase-card">
                                        <div class="club-icon">
                                            <?php if (!empty($club['logo'])): ?>
                                                <img src="<?php echo htmlspecialchars($club['logo']); ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;" alt="<?php echo htmlspecialchars($club['name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-users"></i>
                                            <?php endif; ?>
                                        </div>
                                        <h4><?php echo htmlspecialchars($club['name']); ?></h4>
                                        <div class="member-count">
                                            <i class="fas fa-user-friends"></i>
                                            <?php echo (int)$club['member_count']; ?> member<?php echo (int)$club['member_count'] !== 1 ? 's' : ''; ?>
                                        </div>
                                        <a href="student_club.php" class="btn-learn">Learn More</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-clubs-msg" style="grid-column:1/-1;">
                                    <i class="fas fa-users" style="font-size:40px;margin-bottom:10px;display:block;color:#ccc;"></i>
                                    No clubs available at the moment.
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </section><!-- /Featured Programs Section -->


            <!-- ═══════════════════════════════════════════════
             STUDENTS LIFE SECTION
        ═══════════════════════════════════════════════ -->
            <section id="students-life-block" class="students-life-block section">
                <div class="container section-title">
                    <h2>Students Life</h2>
                    <p>Where learning meets fun, and every day inspires growth and friendship.</p>
                </div>

                <div class="container">
                    <div class="row align-items-center g-5">
                        <div class="col-lg-6">
                            <div class="content-wrapper">
                                <div class="section-tag">Student Life</div>
                                <h2>Experience Student Life at Buyoan National High School</h2>
                                <p class="description">Step into a world where learning goes beyond the classroom—where every day is filled with discovery, teamwork, and opportunities to grow. At Buyoan National High School, students build lasting friendships, explore their passions, and prepare to become future leaders in a supportive and inspiring environment.</p>

                                <div class="stats-row">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $total_clubs > 0 ? $total_clubs . '+' : '0'; ?></span>
                                        <span class="stat-label">Student Clubs</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $total_events > 0 ? $total_events . '+' : '0'; ?></span>
                                        <span class="stat-label">Annual Events</span>
                                    </div>
                                </div>

                                <div class="action-links">
                                    <a href="student-life.php" class="primary-link">Explore Student Life</a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="visual-grid">
                                <div class="main-visual">
                                    <img src="assets/img/education/Campus Life.jpg" alt="Campus Life" class="img-fluid">
                                    <div class="overlay-badge">
                                        <i class="fas fa-heart"></i>
                                        <span>Campus Community</span>
                                    </div>
                                </div>

                                <div class="secondary-visuals">
                                    <div class="small-visual">
                                        <img src="assets/img/education/Student Activities.jpg" alt="Student Activities" class="img-fluid">
                                        <div class="visual-caption">
                                            <span>Student Activities</span>
                                        </div>
                                    </div>

                                    <div class="small-visual">
                                        <img src="assets/img/education/Excellence.jpg" alt="Academic Excellence" class="img-fluid">
                                        <div class="visual-caption">
                                            <span>Academic Excellence</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Highlight Cards (editable from admin_profile) -->
                    <div class="highlights-section">
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="highlight-card">
                                    <div class="highlight-image">
                                        <img src="<?php echo htmlspecialchars($card_leadership['image'] ?: 'assets/img/education/Leadership development.jpg'); ?>" alt="Leadership Programs" class="img-fluid">
                                    </div>
                                    <div class="highlight-content">
                                        <h5><?php echo htmlspecialchars($card_leadership['title']); ?></h5>
                                        <p><?php echo htmlspecialchars($card_leadership['description']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="highlight-card">
                                    <div class="highlight-image">
                                        <img src="<?php echo htmlspecialchars($card_cultural['image'] ?: 'assets/img/education/Cultural Event.jpg'); ?>" alt="Cultural Events" class="img-fluid">
                                    </div>
                                    <div class="highlight-content">
                                        <h5><?php echo htmlspecialchars($card_cultural['title']); ?></h5>
                                        <p><?php echo htmlspecialchars($card_cultural['description']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="highlight-card">
                                    <div class="highlight-image">
                                        <img src="<?php echo htmlspecialchars($card_innovation['image'] ?: 'assets/img/innovation.jpg'); ?>" alt="Innovation Hub" class="img-fluid">
                                    </div>
                                    <div class="highlight-content">
                                        <h5><?php echo htmlspecialchars($card_innovation['title']); ?></h5>
                                        <p><?php echo htmlspecialchars($card_innovation['description']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section><!-- /Students Life Block Section -->


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
             RECENT NEWS — Rolling 4
        ═══════════════════════════════════════════════ -->
            <section id="recent-news" class="recent-news section">
                <div class="container section-title">
                    <h2>Recent News</h2>
                    <p>Your Gateway to the Latest Campus Updates.</p>
                </div>

                <div class="container">
                    <div class="row gy-5">
                        <?php if (count($recent_news) > 0): ?>
                            <?php foreach ($recent_news as $news_item): ?>
                                <?php
                                $news_img = !empty($news_item['image'])
                                    ? 'assets/img/blog/' . $news_item['image']
                                    : 'assets/img/blog/blog-post-2.jpg';
                                $news_date_fmt = !empty($news_item['news_date'])
                                    ? date('D, F j, Y', strtotime($news_item['news_date']))
                                    : 'N/A';
                                $news_author = !empty($news_item['author'])
                                    ? $news_item['author']
                                    : 'Buyoan National High School';
                                $news_excerpt = htmlspecialchars(mb_substr(strip_tags($news_item['content'] ?? $news_item['description'] ?? ''), 0, 200));
                                ?>
                                <div class="col-xl-3 col-md-6">
                                    <div class="post-box" style="cursor:pointer;" onclick="window.location='news.php?id=<?php echo (int)$news_item['id']; ?>'">
                                        <div class="post-img">
                                            <img src="<?php echo htmlspecialchars($news_img); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($news_item['title']); ?>">
                                        </div>
                                        <div class="meta">
                                            <span class="post-date"><?php echo $news_date_fmt; ?></span>
                                            <span class="post-author"> / <?php echo htmlspecialchars($news_author); ?></span>
                                        </div>
                                        <h3 class="post-title"><?php echo htmlspecialchars($news_item['title']); ?></h3>
                                        <p><?php echo $news_excerpt . (strlen(strip_tags($news_item['content'] ?? $news_item['description'] ?? '')) > 200 ? '...' : ''); ?></p>
                                        <a href="news.php?id=<?php echo (int)$news_item['id']; ?>" class="readmore stretched-link"><span>Read More</span><i class="fas fa-arrow-right"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-5 text-muted">
                                <i class="fas fa-newspaper" style="font-size:48px;margin-bottom:16px;display:block;color:#ccc;"></i>
                                No news available at the moment. Check back soon!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section><!-- /Recent News Section -->


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
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/js/main.js"></script>

    <!-- Navigation -->
    <script>
        fetch('nav.php').then(r => r.text()).then(d => {
            document.getElementById('nav-placeholder').innerHTML = d;
        }).catch(e => console.error('Error loading navigation:', e));
    </script>
    <!-- Footer -->
    <script>
        fetch('footer.php').then(r => r.text()).then(d => {
            document.getElementById('footer-placeholder').innerHTML = d;
        }).catch(e => console.error('Error loading footer:', e));
    </script>

    <!-- Events filter script -->
    <script>
        function applyEventFilters() {
            const month = document.getElementById('eventMonthFilter').value;
            const cat = document.getElementById('eventCatFilter').value;
            document.querySelectorAll('#events-grid .event-item').forEach(function(item) {
                const mMatch = !month || item.dataset.month === month;
                const cMatch = !cat || item.dataset.category.toLowerCase() === cat.toLowerCase();
                item.style.display = (mMatch && cMatch) ? '' : 'none';
            });
        }
        document.getElementById('eventMonthFilter').addEventListener('change', applyEventFilters);
        document.getElementById('eventCatFilter').addEventListener('change', applyEventFilters);
    </script>

    <!-- Modals + auth logic -->
    <script>
        fetch('modals.php')
            .then(r => r.text())
            .then(html => {
                document.body.insertAdjacentHTML('beforeend', html);

                document.querySelectorAll('.btn-login, [data-open-login]').forEach(btn => {
                    btn.addEventListener('click', e => {
                        e.preventDefault();
                        new bootstrap.Modal(document.getElementById('loginModal')).show();
                    });
                });
                document.querySelectorAll('.btn-signup, [data-open-signup]').forEach(btn => {
                    btn.addEventListener('click', e => {
                        e.preventDefault();
                        new bootstrap.Modal(document.getElementById('signupModal')).show();
                    });
                });

                (function() {
                    'use strict';

                    function initOtp(rowId, hiddenId) {
                        const row = document.getElementById(rowId);
                        if (!row) return;
                        const boxes = row.querySelectorAll('.bm-otp-box');
                        const hid = document.getElementById(hiddenId);

                        function sync() {
                            let v = '';
                            boxes.forEach(b => {
                                v += b.value;
                                b.classList.toggle('is-filled', b.value !== '');
                            });
                            if (hid) hid.value = v;
                        }
                        boxes.forEach((b, i) => {
                            b.addEventListener('input', () => {
                                b.value = b.value.replace(/\D/g, '').slice(-1);
                                sync();
                                if (b.value && i < boxes.length - 1) boxes[i + 1].focus();
                            });
                            b.addEventListener('keydown', e => {
                                if (e.key === 'Backspace' && !b.value && i > 0) {
                                    boxes[i - 1].value = '';
                                    boxes[i - 1].focus();
                                    sync();
                                }
                                if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                                if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
                            });
                            b.addEventListener('paste', e => {
                                e.preventDefault();
                                const d = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                                d.split('').forEach((c, j) => {
                                    if (boxes[j]) boxes[j].value = c;
                                });
                                sync();
                                boxes[Math.min(d.length, boxes.length - 1)].focus();
                            });
                            b.addEventListener('keypress', e => {
                                if (!/\d/.test(e.key)) e.preventDefault();
                            });
                        });
                    }

                    function clearOtp(rowId) {
                        document.querySelectorAll('#' + rowId + ' .bm-otp-box').forEach(b => {
                            b.value = '';
                            b.classList.remove('is-filled', 'is-error');
                        });
                    }

                    function shakeOtp(rowId) {
                        document.querySelectorAll('#' + rowId + ' .bm-otp-box').forEach(b => {
                            b.classList.add('is-error');
                            setTimeout(() => b.classList.remove('is-error'), 420);
                        });
                    }

                    function mmss(spanId, timerId, secs) {
                        const span = document.getElementById(spanId);
                        const timer = document.getElementById(timerId);
                        if (!span) return;
                        let rem = secs;

                        function tick() {
                            const m = String(Math.floor(rem / 60)).padStart(2, '0');
                            const s = String(rem % 60).padStart(2, '0');
                            span.textContent = m + ':' + s;
                            if (timer) timer.classList.toggle('urgent', rem <= 60);
                            if (rem-- > 0) setTimeout(tick, 1000);
                        }
                        tick();
                    }

                    function cdwn(spanId, secs, done) {
                        const span = document.getElementById(spanId);
                        if (!span) return;
                        let rem = secs;
                        span.textContent = rem;
                        const t = setInterval(() => {
                            rem--;
                            span.textContent = rem;
                            if (rem <= 0) {
                                clearInterval(t);
                                if (done) done();
                            }
                        }, 1000);
                        return t;
                    }

                    function showErr(boxId, txtId, msg) {
                        const b = document.getElementById(boxId),
                            t = document.getElementById(txtId);
                        if (b) b.classList.add('show');
                        if (t) t.textContent = msg;
                    }

                    function hideErr(boxId) {
                        const b = document.getElementById(boxId);
                        if (b) b.classList.remove('show');
                    }

                    function setLoad(id, on) {
                        const b = document.getElementById(id);
                        if (!b) return;
                        b.disabled = on;
                        b.classList.toggle('loading', on);
                    }

                    function toast(msg, type) {
                        const c = {
                            success: '#2d6a4f',
                            error: '#c62828',
                            info: '#1a3a2a'
                        };
                        const el = document.createElement('div');
                        el.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;padding:13px 18px;border-radius:12px;color:#fff;font-family:DM Sans,sans-serif;font-size:13px;font-weight:600;box-shadow:0 8px 28px rgba(0,0,0,.22);background:' + (c[type] || c.info) + ';max-width:280px;';
                        el.textContent = msg;
                        document.body.appendChild(el);
                        setTimeout(() => {
                            el.style.opacity = '0';
                            el.style.transition = 'opacity .3s';
                            setTimeout(() => el.remove(), 300);
                        }, 4000);
                    }

                    initOtp('loginOtpBoxes', 'loginOtpHidden');
                    initOtp('signupOtpBoxes', 'signupOtpHidden');

                    document.getElementById('toggleLoginPwd').addEventListener('click', function() {
                        var inp = document.getElementById('loginPassword');
                        var ico = document.getElementById('loginEyeIcon');
                        if (inp.type === 'password') {
                            inp.type = 'text';
                            ico.className = 'fas fa-eye-slash';
                        } else {
                            inp.type = 'password';
                            ico.className = 'fas fa-eye';
                        }
                    });

                    document.getElementById('loginCredentialsForm').addEventListener('submit', e => {
                        e.preventDefault();
                        hideErr('loginErrBox');
                        setLoad('loginSubmitBtn', true);
                        const fd = new FormData(e.target);
                        fd.append('action', 'login_verify_credentials');
                        fetch('login_otp.php', {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json()).then(d => {
                            setLoad('loginSubmitBtn', false);
                            if (d.success) {
                                document.getElementById('loginStep1').style.display = 'none';
                                document.getElementById('loginStep2').style.display = 'block';
                                if (d.masked_contact) document.getElementById('loginOtpSubtitle').textContent = 'Code sent to ' + d.masked_contact;
                                mmss('loginTimerVal', 'loginTimer', 300);
                                const rb = document.getElementById('loginResendBtn');
                                rb.disabled = true;
                                rb.innerHTML = 'Resend · <span id="loginResendTimer">30</span>s';
                                cdwn('loginResendTimer', 30, () => {
                                    rb.disabled = false;
                                    rb.innerHTML = 'Resend code';
                                    rb.classList.add('on');
                                });
                                document.querySelector('#loginOtpBoxes .bm-otp-box').focus();
                            } else {
                                showErr('loginErrBox', 'loginErrTxt', d.message || 'Invalid credentials.');
                            }
                        }).catch(() => {
                            setLoad('loginSubmitBtn', false);
                            showErr('loginErrBox', 'loginErrTxt', 'Connection error. Try again.');
                        });
                    });

                    document.getElementById('loginOtpForm').addEventListener('submit', e => {
                        e.preventDefault();
                        hideErr('loginOtpErrBox');
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

                    document.getElementById('signupForm').addEventListener('submit', e => {
                        e.preventDefault();
                        hideErr('signupErrBox');
                        const pw = document.getElementById('signupPassword').value;
                        const cp = document.getElementById('signupConfirmPassword').value;
                        if (pw !== cp) {
                            showErr('signupErrBox', 'signupErrTxt', 'Passwords do not match.');
                            return;
                        }
                        setLoad('signupSubmitBtn', true);
                        const fd = new FormData(e.target);
                        fetch('signup.php', {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json()).then(d => {
                            setLoad('signupSubmitBtn', false);
                            if (d.success) {
                                document.getElementById('signupFormContainer').style.display = 'none';
                                document.getElementById('otpFormContainer').style.display = 'block';
                                document.getElementById('spill1').classList.remove('active');
                                document.getElementById('spill2').classList.add('active');
                                const mth = document.querySelector('input[name="contact_method"]:checked').value;
                                const dst = mth === 'email' ? document.getElementById('email').value : document.getElementById('phone').value;
                                document.getElementById('otpSubtitle').textContent = 'Code sent to: ' + dst;
                                mmss('otpCountdown', 'signupTimer', 300);
                                const rb = document.getElementById('resendOtpBtn');
                                rb.disabled = true;
                                rb.innerHTML = "Didn't receive it? Resend · <span id='resendTimer'>30</span>s";
                                cdwn('resendTimer', 30, () => {
                                    rb.disabled = false;
                                    rb.innerHTML = "Didn't receive it? Resend";
                                    rb.classList.add('on');
                                });
                                document.querySelector('#signupOtpBoxes .bm-otp-box').focus();
                            } else {
                                if (d.message === 'this email is already used') {
                                    const w = document.getElementById('emailWarning');
                                    w.textContent = d.message;
                                    w.style.display = 'block';
                                } else {
                                    showErr('signupErrBox', 'signupErrTxt', d.message || 'An error occurred.');
                                }
                            }
                        }).catch(() => {
                            setLoad('signupSubmitBtn', false);
                            showErr('signupErrBox', 'signupErrTxt', 'Connection error.');
                        });
                    });

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
                        fetch('signup.php', {
                            method: 'POST',
                            body: fd
                        }).then(r => r.json()).then(d => {
                            setLoad('otpVerifyBtn', false);
                            if (d.success) {
                                bootstrap.Modal.getInstance(document.getElementById('signupModal')).hide();
                                window.location.href = 'user_account/Dashboard.php';
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

                })();
            })
            .catch(err => console.error('Error loading modals:', err));
    </script>


    <!-- ═══════════════════════════════════════════════════════════════════
     STUDENT LOGIN POPUP
     Same visual design as the Dashboard.php Two-Step Verification modal.
     Triggered by #joinUsBtn. Self-contained — no Bootstrap required.
     Uses login_otp.php: login_verify_credentials → login_verify_otp.
     On success redirects to user_account/Dashboard.php.
═══════════════════════════════════════════════════════════════════ -->
    <div id="slOverlay"
        style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;
            justify-content:center;padding:16px;background:rgba(8,20,14,.65);
            backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px);
            opacity:0;visibility:hidden;transition:opacity .3s ease,visibility .3s ease;">

        <div id="slCard"
            style="position:relative;background:#fff;border-radius:20px;
                box-shadow:0 28px 72px rgba(26,58,42,.24),0 6px 20px rgba(26,58,42,.14);
                width:100%;max-width:440px;font-family:'DM Sans','Segoe UI',sans-serif;
                transform:translateY(28px) scale(.95);
                transition:transform .38s cubic-bezier(.34,1.28,.64,1);overflow:hidden;">

            <!-- Banner -->
            <div style="position:relative;background:#1a3a2a;padding:30px 30px 24px;overflow:hidden;">
                <div style="position:absolute;inset:0;opacity:.04;
                        background-image:linear-gradient(rgba(255,255,255,1) 1px,transparent 1px),
                                         linear-gradient(90deg,rgba(255,255,255,1) 1px,transparent 1px);
                        background-size:28px 28px;"></div>
                <!-- Close button -->
                <button id="slCloseBtn"
                    style="position:absolute;top:14px;right:16px;z-index:3;
                       width:30px;height:30px;border-radius:50%;
                       background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);
                       color:rgba(255,255,255,.65);font-size:12px;cursor:pointer;
                       display:flex;align-items:center;justify-content:center;"
                    aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
                <div style="position:relative;z-index:1;display:inline-flex;align-items:center;gap:5px;
                        background:rgba(201,168,76,.18);border:1px solid rgba(201,168,76,.38);
                        color:#f0d98a;font-size:10.5px;font-weight:700;letter-spacing:.09em;
                        text-transform:uppercase;padding:3px 11px;border-radius:99px;margin-bottom:8px;">
                    <i class="fas fa-shield-alt"></i> Student Sign In
                </div>
                <h2 style="position:relative;z-index:1;font-family:'Playfair Display',Georgia,serif;
                       font-size:22px;font-weight:700;color:#fff;margin:0 0 4px;">
                    Two-Step Verification
                </h2>
                <p style="position:relative;z-index:1;font-size:13px;color:rgba(255,255,255,.55);margin:0;">
                    Sign in to your BUNHS student account.
                </p>
            </div>

            <!-- Body -->
            <div style="padding:26px 30px 30px;">

                <!-- Step 1: Credentials -->
                <div id="slStep1" style="text-align:center;">
                    <div style="width:62px;height:62px;border-radius:50%;
                            background:linear-gradient(135deg,rgba(82,183,136,.14),rgba(45,106,79,.08));
                            border:2px solid rgba(82,183,136,.28);
                            display:flex;align-items:center;justify-content:center;
                            font-size:23px;color:#2d6a4f;margin:0 auto 10px;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <p style="font-family:'Playfair Display',Georgia,serif;font-size:17px;
                          font-weight:700;color:#1a3a2a;margin:0 0 5px;">Sign in to your account</p>
                    <p style="font-size:12.5px;color:#6b7c72;margin:0 0 18px;">
                        Enter your email and password to receive a verification code.
                    </p>

                    <!-- Error strip step 1 -->
                    <div id="slS1Err"
                        style="display:none;align-items:center;gap:8px;
                            background:#fdf1f1;border:1px solid #f0d5d5;border-left:3px solid #e53935;
                            border-radius:8px;padding:10px 14px;margin-bottom:14px;
                            font-size:13px;color:#b94040;text-align:left;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span id="slS1ErrTxt"></span>
                    </div>

                    <!-- Email -->
                    <div style="margin-bottom:14px;text-align:left;">
                        <label for="slUsername"
                            style="display:block;font-size:12px;font-weight:600;
                                  color:#1a3a2a;margin-bottom:6px;letter-spacing:.02em;">
                            Email Address
                        </label>
                        <input type="email" id="slUsername" name="username"
                            placeholder="yourname@gmail.com"
                            autocomplete="username"
                            style="width:100%;padding:11px 14px;border:2px solid #dde8e2;
                                  border-radius:10px;font-family:'DM Sans',sans-serif;
                                  font-size:14px;color:#1e2d24;background:#f8f5f0;
                                  outline:none;transition:border-color .2s,box-shadow .2s;"
                            onfocus="this.style.borderColor='#52b788';this.style.boxShadow='0 0 0 3.5px rgba(82,183,136,.18)';this.style.background='#fff';"
                            onblur="this.style.borderColor='#dde8e2';this.style.boxShadow='';this.style.background='#f8f5f0';">
                    </div>

                    <!-- Password -->
                    <div style="margin-bottom:20px;text-align:left;">
                        <label for="slPassword"
                            style="display:block;font-size:12px;font-weight:600;
                                  color:#1a3a2a;margin-bottom:6px;letter-spacing:.02em;">
                            Password
                        </label>
                        <div style="position:relative;">
                            <input type="password" id="slPassword" name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                style="width:100%;padding:11px 42px 11px 14px;border:2px solid #dde8e2;
                                      border-radius:10px;font-family:'DM Sans',sans-serif;
                                      font-size:14px;color:#1e2d24;background:#f8f5f0;
                                      outline:none;transition:border-color .2s,box-shadow .2s;"
                                onfocus="this.style.borderColor='#52b788';this.style.boxShadow='0 0 0 3.5px rgba(82,183,136,.18)';this.style.background='#fff';"
                                onblur="this.style.borderColor='#dde8e2';this.style.boxShadow='';this.style.background='#f8f5f0';">
                            <button type="button" id="slTogglePwd" tabindex="-1"
                                style="position:absolute;right:13px;top:50%;transform:translateY(-50%);
                                       background:none;border:none;cursor:pointer;color:#6b7c72;font-size:14px;">
                                <i class="fas fa-eye" id="slEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button id="slSendCodeBtn"
                        style="width:100%;padding:13px 20px;font-family:'DM Sans',sans-serif;
                               font-size:13.5px;font-weight:700;color:#fff;cursor:pointer;
                               background:linear-gradient(135deg,#3a8c6a,#1a3a2a);border:none;
                               border-radius:12px;display:flex;align-items:center;justify-content:center;
                               gap:8px;box-shadow:0 4px 16px rgba(26,58,42,.3);
                               transition:transform .15s,box-shadow .15s,opacity .15s;">
                        <i class="fas fa-paper-plane"></i>&ensp;Send Verification Code
                    </button>
                </div>

                <!-- Step 2: OTP -->
                <div id="slStep2" style="text-align:center;display:none;">
                    <div style="width:62px;height:62px;border-radius:50%;
                            background:linear-gradient(135deg,rgba(82,183,136,.14),rgba(45,106,79,.08));
                            border:2px solid rgba(82,183,136,.28);
                            display:flex;align-items:center;justify-content:center;
                            font-size:23px;color:#2d6a4f;margin:0 auto 10px;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <p style="font-family:'Playfair Display',Georgia,serif;font-size:17px;
                          font-weight:700;color:#1a3a2a;margin:0 0 5px;">Enter Verification Code</p>
                    <p style="font-size:12.5px;color:#6b7c72;margin:0 0 12px;" id="slOtpSubtitle">
                        Enter the 6-digit code sent to your registered contact.
                    </p>

                    <!-- Timer pill -->
                    <div style="margin-bottom:16px;">
                        <span id="slTimerPill"
                            style="display:inline-flex;align-items:center;gap:6px;padding:5px 13px;
                                 border-radius:99px;font-size:12px;font-weight:600;
                                 background:#fdf6ec;border:1px solid #f0e4cc;color:#8b5e1a;
                                 transition:background .3s,border-color .3s,color .3s;">
                            <i class="fas fa-clock"></i> <span id="slTimerVal">05:00</span>
                        </span>
                    </div>

                    <!-- OTP boxes -->
                    <div id="slOtpBoxes" style="display:flex;gap:8px;justify-content:center;margin-bottom:16px;">
                        <input class="sl-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                            style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;
                                  background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;
                                  color:#1e2d24;outline:none;transition:border-color .2s,box-shadow .2s;">
                        <input class="sl-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                            style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;
                                  background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;
                                  color:#1e2d24;outline:none;transition:border-color .2s,box-shadow .2s;">
                        <input class="sl-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                            style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;
                                  background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;
                                  color:#1e2d24;outline:none;transition:border-color .2s,box-shadow .2s;">
                        <input class="sl-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                            style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;
                                  background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;
                                  color:#1e2d24;outline:none;transition:border-color .2s,box-shadow .2s;">
                        <input class="sl-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                            style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;
                                  background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;
                                  color:#1e2d24;outline:none;transition:border-color .2s,box-shadow .2s;">
                        <input class="sl-otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                            style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;
                                  background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;
                                  color:#1e2d24;outline:none;transition:border-color .2s,box-shadow .2s;">
                    </div>
                    <input type="hidden" id="slOtpHidden">

                    <!-- Error strip step 2 -->
                    <div id="slS2Err"
                        style="display:none;align-items:center;gap:8px;
                            background:#fdf1f1;border:1px solid #f0d5d5;border-left:3px solid #e53935;
                            border-radius:8px;padding:10px 14px;margin-bottom:14px;
                            font-size:13px;color:#b94040;text-align:left;">
                        <i class="fas fa-exclamation-circle"></i>
                        <span id="slS2ErrTxt"></span>
                    </div>

                    <!-- Verify button -->
                    <button id="slVerifyBtn"
                        style="width:100%;padding:13px 20px;font-family:'DM Sans',sans-serif;
                               font-size:13.5px;font-weight:700;color:#fff;cursor:pointer;
                               background:linear-gradient(135deg,#3a8c6a,#1a3a2a);border:none;
                               border-radius:12px;display:flex;align-items:center;justify-content:center;
                               gap:8px;box-shadow:0 4px 16px rgba(26,58,42,.3);
                               transition:transform .15s,box-shadow .15s,opacity .15s;">
                        <i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign in
                    </button>

                    <!-- Resend + Back -->
                    <div style="margin-top:14px;display:flex;align-items:center;justify-content:center;gap:12px;">
                        <button id="slResendBtn" disabled
                            style="background:none;border:none;padding:0;cursor:pointer;
                                   font-family:'DM Sans',sans-serif;font-size:12.5px;color:#6b7c72;
                                   font-weight:600;transition:color .2s;opacity:.5;">
                            Resend · <span id="slResendTimer">30</span>s
                        </button>
                        <span style="color:#dde8e2;font-size:14px;">|</span>
                        <button id="slBackBtn"
                            style="background:none;border:none;padding:0;cursor:pointer;
                                   font-family:'DM Sans',sans-serif;font-size:12.5px;color:#6b7c72;
                                   font-weight:600;transition:color .2s;">
                            <i class="fas fa-arrow-left" style="font-size:10px;"></i> Back
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div><!-- /#slOverlay -->

    <script>
        (function() {
            'use strict';

            const ENDPOINT = 'login_otp.php';

            const overlay = document.getElementById('slOverlay');
            const card = document.getElementById('slCard');
            const step1 = document.getElementById('slStep1');
            const step2 = document.getElementById('slStep2');
            const s1Err = document.getElementById('slS1Err');
            const s1ErrTxt = document.getElementById('slS1ErrTxt');
            const s2Err = document.getElementById('slS2Err');
            const s2ErrTxt = document.getElementById('slS2ErrTxt');
            const sendBtn = document.getElementById('slSendCodeBtn');
            const verifyBtn = document.getElementById('slVerifyBtn');
            const resendBtn = document.getElementById('slResendBtn');
            const backBtn = document.getElementById('slBackBtn');
            const closeBtn = document.getElementById('slCloseBtn');
            const timerPill = document.getElementById('slTimerPill');
            const timerVal = document.getElementById('slTimerVal');
            const subtitle = document.getElementById('slOtpSubtitle');
            const joinUsBtn = document.getElementById('joinUsBtn');

            let _timerID = null;
            let _resendID = null;
            let _busy = false;

            // ── Open / Close ──────────────────────────────────────────
            function openModal() {
                showStep(1);
                overlay.style.display = 'flex';
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    overlay.style.opacity = '1';
                    overlay.style.visibility = 'visible';
                    card.style.transform = 'translateY(0) scale(1)';
                }));
                document.body.style.overflow = 'hidden';
                hideS1Err();
                setTimeout(() => document.getElementById('slUsername').focus(), 380);
            }

            function closeModal() {
                overlay.style.opacity = '0';
                overlay.style.visibility = 'hidden';
                card.style.transform = 'translateY(28px) scale(.95)';
                document.body.style.overflow = '';
                clearInterval(_timerID);
                clearInterval(_resendID);
                _busy = false;
                showStep(1);
                document.getElementById('slUsername').value = '';
                document.getElementById('slPassword').value = '';
                hideS1Err();
                hideS2Err();
                clearOtpBoxes();
            }

            // ── Step switcher ─────────────────────────────────────────
            function showStep(n) {
                step1.style.display = n === 1 ? 'block' : 'none';
                step2.style.display = n === 2 ? 'block' : 'none';
            }

            // ── Error helpers ─────────────────────────────────────────
            function showS1Err(msg) {
                s1ErrTxt.textContent = msg;
                s1Err.style.display = 'flex';
            }

            function hideS1Err() {
                s1Err.style.display = 'none';
            }

            function showS2Err(msg) {
                s2ErrTxt.textContent = msg;
                s2Err.style.display = 'flex';
            }

            function hideS2Err() {
                s2Err.style.display = 'none';
            }

            function setLoad(btn, on, loadHtml, idleHtml) {
                btn.disabled = on;
                btn.style.opacity = on ? '.7' : '1';
                btn.innerHTML = on ? loadHtml : idleHtml;
            }

            // ── OTP boxes ─────────────────────────────────────────────
            function wireOtpBoxes() {
                const boxes = Array.from(document.querySelectorAll('#slOtpBoxes .sl-otp-box'));
                const hid = document.getElementById('slOtpHidden');

                function sync() {
                    hid.value = boxes.map(b => b.value).join('');
                }

                boxes.forEach((box, i) => {
                    box.addEventListener('input', () => {
                        box.value = box.value.replace(/\D/g, '').slice(-1);
                        sync();
                        if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
                    });
                    box.addEventListener('keydown', e => {
                        if (e.key === 'Backspace' && !box.value && i > 0) {
                            boxes[i - 1].value = '';
                            boxes[i - 1].focus();
                            sync();
                        }
                        if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                        if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
                    });
                    box.addEventListener('paste', e => {
                        e.preventDefault();
                        const text = (e.clipboardData || window.clipboardData)
                            .getData('text').replace(/\D/g, '').slice(0, 6);
                        text.split('').forEach((ch, j) => {
                            if (boxes[j]) boxes[j].value = ch;
                        });
                        sync();
                        boxes[Math.min(text.length, boxes.length - 1)].focus();
                    });
                    box.addEventListener('keypress', e => {
                        if (!/\d/.test(e.key)) e.preventDefault();
                    });
                    box.addEventListener('focus', () => {
                        box.style.borderColor = '#52b788';
                        box.style.boxShadow = '0 0 0 3.5px rgba(82,183,136,.18)';
                        box.style.background = '#fff';
                    });
                    box.addEventListener('blur', () => {
                        box.style.borderColor = box.value ? '#2d6a4f' : '#dde8e2';
                        box.style.boxShadow = '';
                        box.style.background = box.value ? 'rgba(45,106,79,.06)' : '#f8f5f0';
                    });
                });
            }

            function clearOtpBoxes() {
                document.querySelectorAll('#slOtpBoxes .sl-otp-box').forEach(b => {
                    b.value = '';
                    b.style.borderColor = '#dde8e2';
                    b.style.boxShadow = '';
                    b.style.background = '#f8f5f0';
                });
                document.getElementById('slOtpHidden').value = '';
            }

            function shakeOtpBoxes() {
                document.querySelectorAll('#slOtpBoxes .sl-otp-box').forEach(b => {
                    b.style.borderColor = '#e53935';
                    setTimeout(() => {
                        b.style.borderColor = '#dde8e2';
                    }, 420);
                });
            }

            // ── Timer — identical to Dashboard.php ────────────────────
            function startTimer() {
                clearInterval(_timerID);
                let rem = 300;

                function tick() {
                    const m = String(Math.floor(rem / 60)).padStart(2, '0');
                    const s = String(rem % 60).padStart(2, '0');
                    timerVal.textContent = m + ':' + s;
                    const urgent = rem <= 60;
                    timerPill.style.background = urgent ? '#fff1f0' : '#fdf6ec';
                    timerPill.style.borderColor = urgent ? '#ffd0cc' : '#f0e4cc';
                    timerPill.style.color = urgent ? '#c62828' : '#8b5e1a';
                    if (rem-- > 0) _timerID = setTimeout(tick, 1000);
                }
                tick();
            }

            // ── Resend countdown ──────────────────────────────────────
            function startResendCountdown() {
                resendBtn.disabled = true;
                resendBtn.style.opacity = '.5';
                resendBtn.innerHTML = 'Resend · <span id="slResendTimer">30</span>s';
                let rem = 30;
                _resendID = setInterval(() => {
                    rem--;
                    const el = document.getElementById('slResendTimer');
                    if (el) el.textContent = rem;
                    if (rem <= 0) {
                        clearInterval(_resendID);
                        resendBtn.disabled = false;
                        resendBtn.style.opacity = '1';
                        resendBtn.innerHTML = 'Resend code';
                        resendBtn.classList.add('on');
                    }
                }, 1000);
            }

            // ══ Step 1 — credentials → send OTP ══════════════════════
            sendBtn.addEventListener('click', () => {
                hideS1Err();
                const email = (document.getElementById('slUsername').value || '').trim();
                const password = (document.getElementById('slPassword').value || '');
                if (!email || !password) {
                    showS1Err('Please enter your email and password.');
                    return;
                }
                setLoad(sendBtn, true,
                    '<i class="fas fa-spinner fa-spin"></i>&ensp;Sending…',
                    '<i class="fas fa-paper-plane"></i>&ensp;Send Verification Code');

                const fd = new FormData();
                fd.append('action', 'login_verify_credentials');
                fd.append('username', email);
                fd.append('email', email);
                fd.append('password', password);

                fetch(ENDPOINT, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        setLoad(sendBtn, false, '',
                            '<i class="fas fa-paper-plane"></i>&ensp;Send Verification Code');
                        if (d.success) {
                            if (d.masked_contact) subtitle.textContent = 'Code sent to: ' + d.masked_contact;
                            if (d.dev_otp) console.log('[DEV] Login OTP:', d.dev_otp);
                            showStep(2);
                            clearOtpBoxes();
                            startTimer();
                            startResendCountdown();
                            hideS2Err();
                            setTimeout(() => {
                                const first = document.querySelector('#slOtpBoxes .sl-otp-box');
                                if (first) first.focus();
                            }, 200);
                        } else {
                            showS1Err(d.message || 'Invalid username or password.');
                        }
                    })
                    .catch(() => {
                        setLoad(sendBtn, false, '',
                            '<i class="fas fa-paper-plane"></i>&ensp;Send Verification Code');
                        showS1Err('Connection error. Please try again.');
                    });
            });

            document.getElementById('slPassword').addEventListener('keydown', e => {
                if (e.key === 'Enter') sendBtn.click();
            });
            document.getElementById('slTogglePwd').addEventListener('click', function() {
                const inp = document.getElementById('slPassword'),
                    icon = document.getElementById('slEyeIcon');
                const show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });



            // ══ Step 2 — verify OTP ═══════════════════════════════════
            verifyBtn.addEventListener('click', () => {
                if (_busy) return;
                hideS2Err();
                const otp = document.getElementById('slOtpHidden').value;
                if (otp.length !== 6) {
                    shakeOtpBoxes();
                    showS2Err('Please enter all 6 digits.');
                    return;
                }

                _busy = true;
                setLoad(verifyBtn, true,
                    '<i class="fas fa-spinner fa-spin"></i>&ensp;Verifying…',
                    '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign in');

                const fd = new FormData();
                fd.append('action', 'login_verify_otp');
                fd.append('otp', otp);

                fetch(ENDPOINT, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        setLoad(verifyBtn, false, '',
                            '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign in');
                        _busy = false;
                        if (d.success) {
                            clearInterval(_timerID);
                            // Route by role — same logic as the existing loginModal
                            if (d.user_type === 'student') {
                                window.location.href = 'user_account/Dashboard.php';
                            } else {
                                window.location.href = 'admin_account/admin_dashboard.php';
                            }
                        } else {
                            shakeOtpBoxes();
                            showS2Err(d.message || 'Invalid code. Please try again.');
                            clearOtpBoxes();
                            const first = document.querySelector('#slOtpBoxes .sl-otp-box');
                            if (first) first.focus();
                        }
                    })
                    .catch(() => {
                        setLoad(verifyBtn, false, '',
                            '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Sign in');
                        _busy = false;
                        showS2Err('Connection error. Please try again.');
                    });
            });

            // ── Resend ────────────────────────────────────────────────
            resendBtn.addEventListener('click', () => {
                resendBtn.disabled = true;
                resendBtn.classList.remove('on');
                const fd = new FormData();
                fd.append('action', 'login_resend_otp');
                fetch(ENDPOINT, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            clearOtpBoxes();
                            startTimer();
                            startResendCountdown();
                            hideS2Err();
                            if (d.masked_contact) subtitle.textContent = 'New code sent to ' + d.masked_contact;
                            // Green flash — identical to Dashboard.php
                            s2ErrTxt.textContent = '✓ ' + (d.message || 'New code sent.');
                            s2Err.style.background = 'rgba(82,183,136,.08)';
                            s2Err.style.borderColor = 'rgba(82,183,136,.3)';
                            s2Err.style.borderLeft = '3px solid #2d6a4f';
                            s2Err.style.color = '#2d6a4f';
                            s2Err.style.display = 'flex';
                            if (d.dev_otp) console.log('[DEV] Resend OTP:', d.dev_otp);
                            setTimeout(() => {
                                hideS2Err();
                                s2Err.style.cssText = '';
                            }, 3500);
                            setTimeout(() => {
                                const first = document.querySelector('#slOtpBoxes .sl-otp-box');
                                if (first) first.focus();
                            }, 150);
                        } else {
                            resendBtn.disabled = false;
                            resendBtn.style.opacity = '1';
                            showS2Err(d.message || 'Failed to resend. Please try again.');
                        }
                    })
                    .catch(() => {
                        resendBtn.disabled = false;
                        resendBtn.style.opacity = '1';
                        showS2Err('Connection error. Could not resend.');
                    });
            });

            // ── Back ──────────────────────────────────────────────────
            backBtn.addEventListener('click', () => {
                clearInterval(_timerID);
                clearInterval(_resendID);
                clearOtpBoxes();
                hideS2Err();
                showStep(1);
                setTimeout(() => document.getElementById('slUsername').focus(), 150);
            });

            // ── Close button + backdrop click + Escape ─────────────────
            closeBtn.addEventListener('click', closeModal);
            overlay.addEventListener('click', e => {
                if (e.target === overlay) closeModal();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && overlay.style.visibility === 'visible') closeModal();
            });

            // ── Open trigger ──────────────────────────────────────────
            if (joinUsBtn) {
                joinUsBtn.addEventListener('click', e => {
                    e.preventDefault();
                    openModal();
                });
            }

            // ── Init ──────────────────────────────────────────────────
            wireOtpBoxes();

        })();
    </script>

    <!-- ═══════════════════════════════════════════════════════════════════
     STUDENT SIGNUP POPUP  (sc- prefix)
     Triggered by #createAccountBtn. Backend: user_account/student_signup.php
═══════════════════════════════════════════════════════════════════ -->
    <div id="scOverlay"
        style="position:fixed;inset:0;z-index:99999;display:flex;align-items:center;
            justify-content:center;padding:16px;background:rgba(8,20,14,.65);
            backdrop-filter:blur(7px);-webkit-backdrop-filter:blur(7px);
            opacity:0;visibility:hidden;transition:opacity .3s ease,visibility .3s ease;
            overflow-y:auto;">
        <div id="scCard"
            style="position:relative;background:#fff;border-radius:20px;
                box-shadow:0 28px 72px rgba(26,58,42,.24),0 6px 20px rgba(26,58,42,.14);
                width:100%;max-width:480px;font-family:'DM Sans','Segoe UI',sans-serif;
                transform:translateY(28px) scale(.95);margin:auto;
                transition:transform .38s cubic-bezier(.34,1.28,.64,1);overflow:hidden;">

            <!-- Banner -->
            <div style="position:relative;background:#1a3a2a;padding:30px 30px 24px;overflow:hidden;">
                <div style="position:absolute;inset:0;opacity:.04;
                        background-image:linear-gradient(rgba(255,255,255,1) 1px,transparent 1px),
                                         linear-gradient(90deg,rgba(255,255,255,1) 1px,transparent 1px);
                        background-size:28px 28px;"></div>
                <button id="scCloseBtn"
                    style="position:absolute;top:14px;right:16px;z-index:3;width:30px;height:30px;
                           border-radius:50%;background:rgba(255,255,255,.1);
                           border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.65);
                           font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-times"></i>
                </button>
                <div style="position:relative;z-index:1;display:inline-flex;align-items:center;gap:5px;
                        background:rgba(201,168,76,.18);border:1px solid rgba(201,168,76,.38);
                        color:#f0d98a;font-size:10.5px;font-weight:700;letter-spacing:.09em;
                        text-transform:uppercase;padding:3px 11px;border-radius:99px;margin-bottom:8px;">
                    <i class="fas fa-user-plus"></i> Create Account
                </div>
                <h2 style="position:relative;z-index:1;font-family:'Playfair Display',Georgia,serif;
                       font-size:22px;font-weight:700;color:#fff;margin:0 0 4px;">
                    Student Registration
                </h2>
                <p style="position:relative;z-index:1;font-size:13px;color:rgba(255,255,255,.55);margin:0;">
                    Create your BUNHS student account.
                </p>
                <!-- Step pills -->
                <div style="position:relative;z-index:1;display:flex;align-items:center;gap:6px;margin-top:16px;">
                    <div id="scPill1" style="display:flex;align-items:center;gap:5px;padding:4px 12px;
                     border-radius:99px;background:rgba(255,255,255,.15);font-size:11px;font-weight:600;color:#fff;">
                        <span style="width:18px;height:18px;border-radius:50%;background:#52b788;
                                 display:flex;align-items:center;justify-content:center;font-size:10px;">1</span>
                        Details
                    </div>
                    <div style="flex:1;height:1px;background:rgba(255,255,255,.15);"></div>
                    <div id="scPill2" style="display:flex;align-items:center;gap:5px;padding:4px 12px;
                     border-radius:99px;background:rgba(255,255,255,.06);font-size:11px;font-weight:600;color:rgba(255,255,255,.4);">
                        <span style="width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,.15);
                                 display:flex;align-items:center;justify-content:center;font-size:10px;">2</span>
                        Verify
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div style="padding:24px 30px 30px;">

                <!-- Step 1: Registration form -->
                <div id="scStep1">
                    <div id="scFormErr"
                        style="display:none;align-items:center;gap:8px;background:#fdf1f1;
                            border:1px solid #f0d5d5;border-left:3px solid #e53935;
                            border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#b94040;">
                        <i class="fas fa-exclamation-circle"></i><span id="scFormErrTxt"></span>
                    </div>

                    <!-- Name row -->
                    <div style="display:grid;grid-template-columns:1fr 80px 1fr;gap:10px;margin-bottom:12px;">
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:5px;">First Name <span style="color:#e53935;">*</span></label>
                            <input id="scFirstName" type="text" placeholder="Juan"
                                style="width:100%;padding:10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;transition:border-color .2s,box-shadow .2s;">
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:5px;">M.I.</label>
                            <input id="scMiddleInitial" type="text" placeholder="A." maxlength="3"
                                style="width:100%;padding:10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;transition:border-color .2s,box-shadow .2s;text-transform:uppercase;">
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:5px;">Last Name <span style="color:#e53935;">*</span></label>
                            <input id="scLastName" type="text" placeholder="Dela Cruz"
                                style="width:100%;padding:10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;transition:border-color .2s,box-shadow .2s;">
                        </div>
                    </div>

                    <!-- Suffix + Age + Gender -->
                    <div style="display:grid;grid-template-columns:90px 80px 1fr;gap:10px;margin-bottom:12px;">
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:5px;">Suffix</label>
                            <select id="scSuffix" style="width:100%;padding:10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;">
                                <option value="">None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:5px;">Age <span style="color:#e53935;">*</span></label>
                            <input id="scAge" type="number" placeholder="16" min="10" max="25"
                                style="width:100%;padding:10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;">
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:5px;">Gender <span style="color:#e53935;">*</span></label>
                            <select id="scGender" style="width:100%;padding:10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;">
                                <option value="">Select…</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div style="margin-bottom:20px;">

                        <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:7px;">Contact for OTP <span style="color:#e53935;">*</span></label>
                        <div style="display:flex;gap:16px;margin-bottom:8px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#1a3a2a;font-weight:500;">
                                <input type="radio" name="scContact" id="scContactEmail" value="email" checked style="accent-color:#2d6a4f;width:15px;height:15px;">
                                <i class="fas fa-envelope" style="color:#2d6a4f;"></i> Email
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#1a3a2a;font-weight:500;">
                                <input type="radio" name="scContact" id="scContactPhone" value="phone" style="accent-color:#2d6a4f;width:15px;height:15px;">
                                <i class="fas fa-mobile-alt" style="color:#2d6a4f;"></i> Phone
                            </label>
                        </div>
                        <div id="scEmailWrap">
                            <input id="scEmail" type="email" placeholder="yourname@gmail.com"
                                style="width:100%;padding:10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;">
                        </div>
                        <div id="scPhoneWrap" style="display:none;">
                            <input id="scPhone" type="tel" placeholder="09XXXXXXXXX"
                                style="width:100%;padding:10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;">
                        </div>
                    </div>



                    <!-- Password -->
                    <div style="margin-bottom:12px;">
                        <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:5px;">Password <span style="color:#e53935;">*</span></label>
                        <div style="position:relative;">
                            <input id="scPassword" type="password" placeholder="At least 8 characters"
                                style="width:100%;padding:10px 42px 10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;">
                            <button type="button" id="scTogglePwd" tabindex="-1"
                                style="position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7c72;font-size:13px;">
                                <i class="fas fa-eye" id="scEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div style="margin-bottom:20px;">
                        <label style="display:block;font-size:11.5px;font-weight:600;color:#1a3a2a;margin-bottom:5px;">Confirm Password <span style="color:#e53935;">*</span></label>
                        <div style="position:relative;">
                            <input id="scConfirmPassword" type="password" placeholder="Re-enter password"
                                style="width:100%;padding:10px 42px 10px 13px;border:2px solid #dde8e2;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13.5px;color:#1e2d24;background:#f8f5f0;outline:none;">
                            <button type="button" id="scToggleConfirm" tabindex="-1"
                                style="position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7c72;font-size:13px;">
                                <i class="fas fa-eye" id="scConfirmEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button id="scSubmitBtn"
                        style="width:100%;padding:13px 20px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:700;color:#fff;cursor:pointer;background:linear-gradient(135deg,#3a8c6a,#1a3a2a);border:none;border-radius:12px;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 16px rgba(26,58,42,.3);">
                        <i class="fas fa-paper-plane"></i>&ensp;Create Account &amp; Send Code
                    </button>
                    <p style="text-align:center;margin-top:14px;font-size:12.5px;color:#6b7c72;">
                        Already have an account?
                        <a href="#" id="scSwitchToLogin" style="color:#2d6a4f;font-weight:600;text-decoration:none;">Sign in</a>
                    </p>
                </div>

                <!-- Step 2: OTP -->
                <div id="scStep2" style="display:none;text-align:center;">
                    <div style="width:62px;height:62px;border-radius:50%;background:linear-gradient(135deg,rgba(82,183,136,.14),rgba(45,106,79,.08));border:2px solid rgba(82,183,136,.28);display:flex;align-items:center;justify-content:center;font-size:23px;color:#2d6a4f;margin:0 auto 10px;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <p style="font-family:'Playfair Display',Georgia,serif;font-size:17px;font-weight:700;color:#1a3a2a;margin:0 0 5px;">Verify your contact</p>
                    <p style="font-size:12.5px;color:#6b7c72;margin:0 0 12px;" id="scOtpSubtitle">Enter the 6-digit code we sent.</p>
                    <div style="margin-bottom:16px;">
                        <span id="scTimerPill" style="display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:99px;font-size:12px;font-weight:600;background:#fdf6ec;border:1px solid #f0e4cc;color:#8b5e1a;">
                            <i class="fas fa-clock"></i> <span id="scTimerVal">05:00</span>
                        </span>
                    </div>
                    <div id="scOtpBoxes" style="display:flex;gap:8px;justify-content:center;margin-bottom:16px;">
                        <input class="sc-otp-box" type="text" maxlength="1" inputmode="numeric" style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;color:#1e2d24;outline:none;">
                        <input class="sc-otp-box" type="text" maxlength="1" inputmode="numeric" style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;color:#1e2d24;outline:none;">
                        <input class="sc-otp-box" type="text" maxlength="1" inputmode="numeric" style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;color:#1e2d24;outline:none;">
                        <input class="sc-otp-box" type="text" maxlength="1" inputmode="numeric" style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;color:#1e2d24;outline:none;">
                        <input class="sc-otp-box" type="text" maxlength="1" inputmode="numeric" style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;color:#1e2d24;outline:none;">
                        <input class="sc-otp-box" type="text" maxlength="1" inputmode="numeric" style="width:46px;height:54px;border-radius:10px;border:2px solid #dde8e2;background:#f8f5f0;font-size:22px;font-weight:700;text-align:center;color:#1e2d24;outline:none;">
                    </div>
                    <input type="hidden" id="scOtpHidden">
                    <div id="scOtpErr" style="display:none;align-items:center;gap:8px;background:#fdf1f1;border:1px solid #f0d5d5;border-left:3px solid #e53935;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#b94040;text-align:left;">
                        <i class="fas fa-exclamation-circle"></i><span id="scOtpErrTxt"></span>
                    </div>
                    <button id="scVerifyBtn" style="width:100%;padding:13px 20px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:700;color:#fff;cursor:pointer;background:linear-gradient(135deg,#3a8c6a,#1a3a2a);border:none;border-radius:12px;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 16px rgba(26,58,42,.3);">
                        <i class="fas fa-check-circle"></i>&ensp;Verify &amp; Create Account
                    </button>
                    <div style="margin-top:14px;display:flex;align-items:center;justify-content:center;gap:12px;">
                        <button id="scResendBtn" disabled style="background:none;border:none;padding:0;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:12.5px;color:#6b7c72;font-weight:600;opacity:.5;">
                            Resend · <span id="scResendTimer">30</span>s
                        </button>
                        <span style="color:#dde8e2;">|</span>
                        <button id="scBackBtn" style="background:none;border:none;padding:0;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:12.5px;color:#6b7c72;font-weight:600;">
                            <i class="fas fa-arrow-left" style="font-size:10px;"></i> Back
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            'use strict';
            const SIGNUP_URL = 'user_account/student_signup.php';
            const overlay = document.getElementById('scOverlay');
            const card = document.getElementById('scCard');
            const step1 = document.getElementById('scStep1');
            const step2 = document.getElementById('scStep2');
            const formErr = document.getElementById('scFormErr');
            const formErrTxt = document.getElementById('scFormErrTxt');
            const otpErr = document.getElementById('scOtpErr');
            const otpErrTxt = document.getElementById('scOtpErrTxt');
            const submitBtn = document.getElementById('scSubmitBtn');
            const verifyBtn = document.getElementById('scVerifyBtn');
            const resendBtn = document.getElementById('scResendBtn');
            const backBtn = document.getElementById('scBackBtn');
            const closeBtn = document.getElementById('scCloseBtn');
            const timerPill = document.getElementById('scTimerPill');
            const timerVal = document.getElementById('scTimerVal');
            const subtitle = document.getElementById('scOtpSubtitle');
            const triggerBtn = document.getElementById('createAccountBtn');
            let _timerID = null,
                _resendID = null,
                _busy = false;

            /* focus style */
            document.querySelectorAll('#scStep1 input, #scStep1 select').forEach(el => {
                el.addEventListener('focus', () => {
                    el.style.borderColor = '#52b788';
                    el.style.boxShadow = '0 0 0 3.5px rgba(82,183,136,.18)';
                    el.style.background = '#fff';
                });
                el.addEventListener('blur', () => {
                    el.style.borderColor = '#dde8e2';
                    el.style.boxShadow = '';
                    el.style.background = '#f8f5f0';
                });
            });

            /* password toggles */
            document.getElementById('scTogglePwd').addEventListener('click', function() {
                const i = document.getElementById('scPassword'),
                    ic = document.getElementById('scEyeIcon');
                const s = i.type === 'password';
                i.type = s ? 'text' : 'password';
                ic.className = s ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
            document.getElementById('scToggleConfirm').addEventListener('click', function() {
                const i = document.getElementById('scConfirmPassword'),
                    ic = document.getElementById('scConfirmEyeIcon');
                const s = i.type === 'password';
                i.type = s ? 'text' : 'password';
                ic.className = s ? 'fas fa-eye-slash' : 'fas fa-eye';
            });

            /* contact toggle */
            document.querySelectorAll('input[name="scContact"]').forEach(r => {
                r.addEventListener('change', () => {
                    const em = document.getElementById('scContactEmail').checked;
                    document.getElementById('scEmailWrap').style.display = em ? '' : 'none';
                    document.getElementById('scPhoneWrap').style.display = em ? 'none' : '';
                });
            });



            /* open/close */
            function openModal() {
                overlay.style.display = 'flex';
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    overlay.style.opacity = '1';
                    overlay.style.visibility = 'visible';
                    card.style.transform = 'translateY(0) scale(1)';
                }));
                document.body.style.overflow = 'hidden';
                setTimeout(() => document.getElementById('scFirstName').focus(), 380);
            }

            function closeModal() {
                overlay.style.opacity = '0';
                overlay.style.visibility = 'hidden';
                card.style.transform = 'translateY(28px) scale(.95)';
                document.body.style.overflow = '';
                clearInterval(_timerID);
                clearInterval(_resendID);
                _busy = false;
                resetForm();
            }

            function resetForm() {
                ['scFirstName', 'scMiddleInitial', 'scLastName', 'scAge', 'scEmail', 'scPhone', 'scPassword', 'scConfirmPassword']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('scSuffix').value = '';
                document.getElementById('scGender').value = '';
                document.getElementById('scContactEmail').checked = true;
                document.getElementById('scEmailWrap').style.display = '';
                document.getElementById('scPhoneWrap').style.display = 'none';
                hideFormErr();
                hideOtpErr();
                clearOtpBoxes();
                showStep(1);
            }

            /* step */
            function showStep(n) {
                step1.style.display = n === 1 ? 'block' : 'none';
                step2.style.display = n === 2 ? 'block' : 'none';
            }

            /* errors */
            function showFormErr(m) {
                formErrTxt.textContent = m;
                formErr.style.display = 'flex';
            }

            function hideFormErr() {
                formErr.style.display = 'none';
            }

            function showOtpErr(m) {
                otpErrTxt.textContent = m;
                otpErr.style.display = 'flex';
            }

            function hideOtpErr() {
                otpErr.style.display = 'none';
            }

            function setLoad(btn, on, lh, ih) {
                btn.disabled = on;
                btn.style.opacity = on ? '.7' : '1';
                btn.innerHTML = on ? lh : ih;
            }

            /* OTP boxes */
            function wireOtpBoxes() {
                const boxes = Array.from(document.querySelectorAll('#scOtpBoxes .sc-otp-box'));
                const hid = document.getElementById('scOtpHidden');

                function sync() {
                    hid.value = boxes.map(b => b.value).join('');
                }
                boxes.forEach((box, i) => {
                    box.addEventListener('input', () => {
                        box.value = box.value.replace(/\D/g, '').slice(-1);
                        sync();
                        if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
                    });
                    box.addEventListener('keydown', e => {
                        if (e.key === 'Backspace' && !box.value && i > 0) {
                            boxes[i - 1].value = '';
                            boxes[i - 1].focus();
                            sync();
                        }
                        if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                        if (e.key === 'ArrowRight' && i < boxes.length - 1) boxes[i + 1].focus();
                    });
                    box.addEventListener('paste', e => {
                        e.preventDefault();
                        const t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                        t.split('').forEach((ch, j) => {
                            if (boxes[j]) boxes[j].value = ch;
                        });
                        sync();
                        boxes[Math.min(t.length, boxes.length - 1)].focus();
                    });
                    box.addEventListener('keypress', e => {
                        if (!/\d/.test(e.key)) e.preventDefault();
                    });
                    box.addEventListener('focus', () => {
                        box.style.borderColor = '#52b788';
                        box.style.boxShadow = '0 0 0 3.5px rgba(82,183,136,.18)';
                        box.style.background = '#fff';
                    });
                    box.addEventListener('blur', () => {
                        box.style.borderColor = box.value ? '#2d6a4f' : '#dde8e2';
                        box.style.boxShadow = '';
                        box.style.background = box.value ? 'rgba(45,106,79,.06)' : '#f8f5f0';
                    });
                });
            }

            function clearOtpBoxes() {
                document.querySelectorAll('#scOtpBoxes .sc-otp-box').forEach(b => {
                    b.value = '';
                    b.style.borderColor = '#dde8e2';
                    b.style.boxShadow = '';
                    b.style.background = '#f8f5f0';
                });
                const h = document.getElementById('scOtpHidden');
                if (h) h.value = '';
            }

            function shakeOtpBoxes() {
                document.querySelectorAll('#scOtpBoxes .sc-otp-box').forEach(b => {
                    b.style.borderColor = '#e53935';
                    setTimeout(() => {
                        b.style.borderColor = '#dde8e2';
                    }, 420);
                });
            }

            /* timer */
            function startTimer() {
                clearInterval(_timerID);
                let rem = 300;

                function tick() {
                    const m = String(Math.floor(rem / 60)).padStart(2, '0'),
                        s = String(rem % 60).padStart(2, '0');
                    timerVal.textContent = m + ':' + s;
                    const u = rem <= 60;
                    timerPill.style.background = u ? '#fff1f0' : '#fdf6ec';
                    timerPill.style.borderColor = u ? '#ffd0cc' : '#f0e4cc';
                    timerPill.style.color = u ? '#c62828' : '#8b5e1a';
                    if (rem-- > 0) _timerID = setTimeout(tick, 1000);
                }
                tick();
            }

            function startResendCountdown() {
                resendBtn.disabled = true;
                resendBtn.style.opacity = '.5';
                resendBtn.innerHTML = 'Resend · <span id="scResendTimer">30</span>s';
                let rem = 30;
                _resendID = setInterval(() => {
                    rem--;
                    const el = document.getElementById('scResendTimer');
                    if (el) el.textContent = rem;
                    if (rem <= 0) {
                        clearInterval(_resendID);
                        resendBtn.disabled = false;
                        resendBtn.style.opacity = '1';
                        resendBtn.innerHTML = 'Resend code';
                        resendBtn.classList.add('on');
                    }
                }, 1000);
            }

            /* Step 1 submit */
            submitBtn.addEventListener('click', () => {
                hideFormErr();
                const fn = (document.getElementById('scFirstName').value || '').trim();
                const ln = (document.getElementById('scLastName').value || '').trim();
                const age = (document.getElementById('scAge').value || '').trim();
                const gender = document.getElementById('scGender').value;

                const isEmail = document.getElementById('scContactEmail').checked;
                const email = (document.getElementById('scEmail').value || '').trim();
                const phone = (document.getElementById('scPhone').value || '').trim();
                const pw = document.getElementById('scPassword').value;
                const cpw = document.getElementById('scConfirmPassword').value;
                if (!fn) {
                    showFormErr('First name is required.');
                    return;
                }
                if (!ln) {
                    showFormErr('Last name is required.');
                    return;
                }
                if (!age || isNaN(age) || +age < 10 || +age > 25) {
                    showFormErr('Enter a valid age (10–25).');
                    return;
                }
                if (!gender) {
                    showFormErr('Please select a gender.');
                    return;
                }
                if (isEmail && !email) {
                    showFormErr('Please enter your email address.');
                    return;
                }
                if (isEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showFormErr('Enter a valid email address.');
                    return;
                }
                if (!isEmail && !phone) {
                    showFormErr('Please enter your phone number.');
                    return;
                }
                if (!pw) {
                    showFormErr('Please enter a password.');
                    return;
                }
                if (pw.length < 8) {
                    showFormErr('Password must be at least 8 characters.');
                    return;
                }
                if (pw !== cpw) {
                    showFormErr('Passwords do not match.');
                    return;
                }

                setLoad(submitBtn, true, '<i class="fas fa-spinner fa-spin"></i>&ensp;Creating account…', '<i class="fas fa-paper-plane"></i>&ensp;Create Account &amp; Send Code');
                const fd = new FormData();
                fd.append('action', 'sc_register');
                fd.append('first_name', fn);
                fd.append('middle_initial', (document.getElementById('scMiddleInitial').value || '').trim());
                fd.append('last_name', ln);
                fd.append('suffix', document.getElementById('scSuffix').value);
                fd.append('age', age);
                fd.append('gender', gender);
                fd.append('contact_method', isEmail ? 'email' : 'phone');
                fd.append('password', pw);
                fd.append('account_type', document.getElementById('scAccountType').value);
                fd.append('confirm_password', cpw);
                fd.append('email', isEmail ? email : '');
                fd.append('phone', isEmail ? '' : phone);

                fetch(SIGNUP_URL, {

                    method: 'POST',
                    body: fd
                }).then(r => r.json()).then(d => {
                    setLoad(submitBtn, false, '', '<i class="fas fa-paper-plane"></i>&ensp;Create Account &amp; Send Code');
                    if (d.success) {
                        subtitle.textContent = 'Code sent to: ' + (isEmail ? email : phone);
                        if (d.dev_otp) console.log('[DEV] Signup OTP:', d.dev_otp);
                        showStep(2);
                        clearOtpBoxes();
                        startTimer();
                        startResendCountdown();
                        hideOtpErr();
                        setTimeout(() => {
                            const f = document.querySelector('#scOtpBoxes .sc-otp-box');
                            if (f) f.focus();
                        }, 200);
                    } else {
                        showFormErr(d.message || 'Registration failed. Please try again.');
                    }
                }).catch(() => {
                    setLoad(submitBtn, false, '', '<i class="fas fa-paper-plane"></i>&ensp;Create Account &amp; Send Code');
                    showFormErr('Connection error. Please try again.');
                });
            });

            /* Step 2 verify */
            verifyBtn.addEventListener('click', () => {
                if (_busy) return;
                hideOtpErr();
                const otp = document.getElementById('scOtpHidden').value;
                if (otp.length !== 6) {
                    shakeOtpBoxes();
                    showOtpErr('Please enter all 6 digits.');
                    return;
                }
                _busy = true;
                setLoad(verifyBtn, true, '<i class="fas fa-spinner fa-spin"></i>&ensp;Verifying…', '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Create Account');
                const fd = new FormData();
                fd.append('action', 'sc_verify_otp');
                fd.append('otp', otp);
                fetch(SIGNUP_URL, {
                    method: 'POST',
                    body: fd
                }).then(r => r.json()).then(d => {
                    setLoad(verifyBtn, false, '', '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Create Account');
                    _busy = false;
                    if (d.success) {
                        clearInterval(_timerID);
                        window.location.href = 'user_account/Dashboard.php';
                    } else {
                        shakeOtpBoxes();
                        showOtpErr(d.message || 'Invalid code. Please try again.');
                        clearOtpBoxes();
                        const f = document.querySelector('#scOtpBoxes .sc-otp-box');
                        if (f) f.focus();
                    }
                }).catch(() => {
                    setLoad(verifyBtn, false, '', '<i class="fas fa-check-circle"></i>&ensp;Verify &amp; Create Account');
                    _busy = false;
                    showOtpErr('Connection error. Please try again.');
                });
            });

            /* Resend */
            resendBtn.addEventListener('click', () => {
                resendBtn.disabled = true;
                resendBtn.classList.remove('on');
                const fd = new FormData();
                fd.append('action', 'sc_resend_otp');
                fetch(SIGNUP_URL, {
                    method: 'POST',
                    body: fd
                }).then(r => r.json()).then(d => {
                    if (d.success) {
                        clearOtpBoxes();
                        startTimer();
                        startResendCountdown();
                        hideOtpErr();
                        otpErrTxt.textContent = '✓ ' + (d.message || 'New code sent.');
                        otpErr.style.background = 'rgba(82,183,136,.08)';
                        otpErr.style.borderColor = 'rgba(82,183,136,.3)';
                        otpErr.style.borderLeft = '3px solid #2d6a4f';
                        otpErr.style.color = '#2d6a4f';
                        otpErr.style.display = 'flex';
                        if (d.dev_otp) console.log('[DEV] Resend OTP:', d.dev_otp);
                        setTimeout(() => {
                            hideOtpErr();
                            otpErr.style.cssText = '';
                        }, 3500);
                        setTimeout(() => {
                            const f = document.querySelector('#scOtpBoxes .sc-otp-box');
                            if (f) f.focus();
                        }, 150);
                    } else {
                        resendBtn.disabled = false;
                        resendBtn.style.opacity = '1';
                        showOtpErr(d.message || 'Failed to resend.');
                    }
                }).catch(() => {
                    resendBtn.disabled = false;
                    resendBtn.style.opacity = '1';
                    showOtpErr('Connection error.');
                });
            });

            /* Back */
            backBtn.addEventListener('click', () => {
                clearInterval(_timerID);
                clearInterval(_resendID);
                clearOtpBoxes();
                hideOtpErr();
                showStep(1);
                setTimeout(() => document.getElementById('scFirstName').focus(), 150);
            });

            /* Close / backdrop / Escape */
            closeBtn.addEventListener('click', closeModal);
            overlay.addEventListener('click', e => {
                if (e.target === overlay) closeModal();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && overlay.style.visibility === 'visible') closeModal();
            });

            /* Switch to login */
            document.getElementById('scSwitchToLogin').addEventListener('click', e => {
                e.preventDefault();
                closeModal();
                const b = document.getElementById('joinUsBtn');
                if (b) b.click();
            });

            /* Open trigger */
            if (triggerBtn) triggerBtn.addEventListener('click', e => {
                e.preventDefault();
                openModal();
            });

            wireOtpBoxes();
            showStep(1);
        })();
    </script>

</body>

</html>