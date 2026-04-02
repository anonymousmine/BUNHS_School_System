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
}
// Variables now available: $card_leadership, $card_cultural, $card_innovation,
//                          $cert_card1, $cert_card2, $cert_card3

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
        $user_type = null;
        $stmt = $conn->prepare("SELECT id, password_hash, school_email FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // If not found in admin, check sub_admin
        if (!$admin) {
            $stmt = $conn->prepare("SELECT id, password_hash, email FROM sub_admin WHERE username = ? AND status = 'approved' LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $user_type = 'sub-admin';
        } else {
            $user_type = 'admin';
        }
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Password correct - generate and send OTP
            $otp = sprintf("%06d", mt_rand(0, 999999));
            
            // Create session structure expected by login_otp.php
            $_SESSION['otp_pending'] = [
                'user_id' => $admin['id'],
                'username' => $username,
                'user_type' => $user_type,
                'email' => $admin['school_email'] ?? $admin['email'],
                'otp' => password_hash($otp, PASSWORD_DEFAULT),
                'expires' => time() + 300 // 5 minutes
            ];
            
            // Keep existing session variables for compatibility
            $_SESSION['login_otp'] = $otp;
            $_SESSION['otp_sent_to'] = $admin['school_email'] ?? $admin['email'];
            $_SESSION['login_admin_id'] = $admin['id'];
            $_SESSION['otp_generated_at'] = time();
            $_SESSION['show_otp_verification'] = true;
            
            // Send OTP via email
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'bunhs.deped@gmail.com';
                $mail->Password = 'svhiovmxalojxzxg';
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('bunhs.deped@gmail.com', 'Buyoan National High School');
                $mail->addAddress($_SESSION['otp_sent_to']);
                $mail->isHTML(true);
                $mail->Subject = 'Your Login Verification Code';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #1a73e8, #1557b0); color: white; padding: 30px; border-radius: 10px; text-align: center;'>
                            <h2 style='margin: 0 0 20px 0; font-size: 24px;'>🔐 Security Verification</h2>
                            <p style='margin: 0; font-size: 16px;'>Your login code for Buyoan National High School Admin Portal</p>
                        </div>
                        <div style='background: #f8f9fa; padding: 30px; border-radius: 10px; text-align: center; margin: 20px 0;'>
                            <div style='font-size: 36px; font-weight: bold; color: #1a73e8; letter-spacing: 8px; border: 2px dashed #1a73e8; padding: 15px 25px; border-radius: 8px; display: inline-block;'>
                                $otp
                            </div>
                            <p style='margin: 20px 0 0 0; color: #666; font-size: 14px;'>This code expires in 5 minutes</p>
                        </div>
                        <div style='text-align: center; color: #666; font-size: 12px; margin-top: 20px;'>
                            <p>If you didn't request this code, please ignore this email.</p>
                        </div>
                    </div>
                ";
                
                $mail->send();
                
            } catch (Exception $e) {
                $login_error = 'Failed to send verification email. Please try again.';
                error_log('OTP Email failed: ' . $e->getMessage());
            }
        } else {
            $login_error = 'Invalid username or password.';
        }
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

    <!-- OTP Verification Section (shown when login is successful) -->
    <?php if (isset($_SESSION['show_otp_verification']) && $_SESSION['show_otp_verification'] === true): ?>
        <div class="verification-page" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div class="verification-card" style="background: white; border-radius: 16px; box-shadow: 0 4px 12px rgba(0, 0, 0, .1); padding: 48px; width: 100%; max-width: 460px; text-align: center; position: relative;">
                <!-- Close Button -->
                <button class="bm-hero-close" id="closeOtpBtn" style="position: absolute; top: 16px; right: 16px; background: none; border: none; color: #666; font-size: 18px; cursor: pointer; padding: 8px; border-radius: 50%; transition: all 0.2s; z-index: 10;">
                    <i class="fas fa-times"></i>
                </button>
                
                <img src="assets/img/logo.jpg" alt="School Logo" class="verification-logo" style="width: 80px; height: auto; margin-bottom: 24px;">
                <h1 class="verification-title" style="font-size: 24px; font-weight: 500; color: #202124; margin-bottom: 16px;">Two-Step Verification</h1>
                <p class="verification-text" style="font-size: 14px; color: #5f6368; line-height: 1.5; margin-bottom: 32px;">Enter the code sent to <?php echo htmlspecialchars(maskEmail($_SESSION['otp_sent_to'] ?? 'your email')); ?></p>
                
                <form id="otpVerificationForm" method="POST" action="login_otp.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="login_verify_otp">
                    
                    <div class="bm-otp-row" style="display: flex; justify-content: center; gap: 7px; margin: 6px 0 20px;">
                        <input class="bm-otp-box" type="text" name="otp1" maxlength="1" inputmode="numeric" pattern="[0-9]" required style="width: 48px; height: 56px; font-family: var(--bunhs-display); font-size: 20px; text-align: center; border: 2px solid #e0e0e0; border-radius: var(--bunhs-radius-sm); transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;">
                        <input class="bm-otp-box" type="text" name="otp2" maxlength="1" inputmode="numeric" pattern="[0-9]" required style="width: 48px; height: 56px; font-family: var(--bunhs-display); font-size: 20px; text-align: center; border: 2px solid #e0e0e0; border-radius: var(--bunhs-radius-sm); transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;">
                        <input class="bm-otp-box" type="text" name="otp3" maxlength="1" inputmode="numeric" pattern="[0-9]" required style="width: 48px; height: 56px; font-family: var(--bunhs-display); font-size: 20px; text-align: center; border: 2px solid #e0e0e0; border-radius: var(--bunhs-radius-sm); transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;">
                        <input class="bm-otp-box" type="text" name="otp4" maxlength="1" inputmode="numeric" pattern="[0-9]" required style="width: 48px; height: 56px; font-family: var(--bunhs-display); font-size: 20px; text-align: center; border: 2px solid #e0e0e0; border-radius: var(--bunhs-radius-sm); transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;">
                        <input class="bm-otp-box" type="text" name="otp5" maxlength="1" inputmode="numeric" pattern="[0-9]" required style="width: 48px; height: 56px; font-family: var(--bunhs-display); font-size: 20px; text-align: center; border: 2px solid #e0e0e0; border-radius: var(--bunhs-radius-sm); transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;">
                        <input class="bm-otp-box" type="text" name="otp6" maxlength="1" inputmode="numeric" pattern="[0-9]" required style="width: 48px; height: 56px; font-family: var(--bunhs-display); font-size: 20px; text-align: center; border: 2px solid #e0e0e0; border-radius: var(--bunhs-radius-sm); transition: border-color .2s, background .2s, box-shadow .2s, transform .15s;">
                    </div>
                    
                    <div id="otpError" class="bm-err" style="display: none; margin: 6px 0;">
                        <i class="fas fa-exclamation-circle"></i> <span id="otpErrorText"></span>
                    </div>
                    
                    <button type="submit" class="bm-btn" id="verifyOtpBtn" style="width: 100%; padding: 13px 20px; margin-top: 4px;">
                        <span class="bm-btn-label"><i class="fas fa-check-circle"></i>&ensp;Verify & Sign In</span>
                        <div class="bm-spinner"></div>
                    </button>
                    
                    <div style="margin-top: 14px; display: flex; align-items: center; justify-content: center; gap: 12px;">
                        <button type="button" class="bm-ghost" id="resendOtpBtn" disabled>
                            Resend · <span id="resendTimer">30</span>s
                        </button>
                        <span style="color: var(--bunhs-border); font-size: 14px;">|</span>
                        <button type="button" class="bm-ghost" id="backToLoginBtn">
                            <i class="fas fa-arrow-left" style="font-size: 10px;"></i> Back
                        </button>
                    </div>
                    
                    <div style="margin-top: 16px; display: flex; align-items: center; justify-content: center; gap: 12px;">
                        <span class="bm-timer" id="otpTimer" style="color: var(--bunhs-muted); font-size: 12.5px;">
                            <i class="fas fa-clock"></i> <span id="loginTimerVal">05:00</span>
                        </span>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
            // OTP Input handling (matching modal behavior)
            const otpInputs = document.querySelectorAll('input[name^="otp"]');
            
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', (e) => {
                    if (e.target.value && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                    // Add filled class
                    if (e.target.value) {
                        e.target.classList.add('is-filled');
                    } else {
                        e.target.classList.remove('is-filled');
                    }
                });
                
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });
                
                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').slice(0, 6);
                    for (let i = 0; i < pastedData.length && i < otpInputs.length; i++) {
                        otpInputs[i].value = pastedData[i];
                        otpInputs[i].classList.add('is-filled');
                        if (i < otpInputs.length - 1) {
                            otpInputs[i + 1].focus();
                        }
                    }
                });
            });
            
            // Timer
            let timeLeft = 300; // 5 minutes in seconds
            const timerElement = document.getElementById('loginTimerVal');
            
            function updateTimer() {
                if (timeLeft <= 0) {
                    timerElement.textContent = 'Expired';
                    document.getElementById('verifyOtpBtn').disabled = true;
                    return;
                }
                
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                timeLeft--;
            }
            
            const timerInterval = setInterval(updateTimer, 1000);
            
            // Handle form submission
            document.getElementById('otpVerificationForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const otp = Array.from(otpInputs).map(input => input.value).join('');
                const formData = new FormData(e.target);
                formData.set('otp', otp);
                
                // Show loading state
                const submitBtn = document.getElementById('verifyOtpBtn');
                submitBtn.classList.add('loading');
                
                try {
                    const response = await fetch('login_otp.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Redirect to dashboard
                        window.location.href = 'admin_account/admin_dashboard.php';
                    } else {
                        // Show error
                        document.getElementById('otpError').style.display = 'block';
                        document.getElementById('otpErrorText').textContent = result.message || 'Invalid verification code';
                        
                        // Shake error boxes
                        otpInputs.forEach(input => {
                            input.classList.add('is-error');
                            setTimeout(() => input.classList.remove('is-error'), 380);
                        });
                    }
                } catch (error) {
                    document.getElementById('otpError').style.display = 'block';
                    document.getElementById('otpErrorText').textContent = 'Network error. Please try again.';
                } finally {
                    submitBtn.classList.remove('loading');
                }
            });
            
            // Resend OTP
            document.getElementById('resendOtpBtn').addEventListener('click', async () => {
                try {
                    const response = await fetch('login_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'resend_otp',
                            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Reset timer and inputs
                        timeLeft = 300;
                        otpInputs.forEach(input => {
                            input.value = '';
                            input.classList.remove('is-filled', 'is-error');
                        });
                        otpInputs[0].focus();
                        
                        document.getElementById('otpError').style.display = 'none';
                        
                        // Start resend countdown
                        let resendTimeLeft = 30;
                        const resendBtn = document.getElementById('resendOtpBtn');
                        const resendTimer = document.getElementById('resendTimer');
                        
                        const resendInterval = setInterval(() => {
                            if (resendTimeLeft <= 0) {
                                resendBtn.disabled = false;
                                resendTimer.textContent = 'Resend';
                                clearInterval(resendInterval);
                            } else {
                                resendTimer.textContent = resendTimeLeft;
                                resendTimeLeft--;
                            }
                        }, 1000);
                        
                    } else {
                        document.getElementById('otpError').style.display = 'block';
                        document.getElementById('otpErrorText').textContent = result.message || 'Failed to resend code';
                    }
                } catch (error) {
                    document.getElementById('otpError').style.display = 'block';
                    document.getElementById('otpErrorText').textContent = 'Network error. Please try again.';
                }
            });
            
            // Back to login
            document.getElementById('backToLoginBtn').addEventListener('click', () => {
                // Clear OTP session and reload
                fetch('login_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'cancel_otp',
                        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                    })
                }).then(() => {
                    window.location.reload();
                });
            });
            
            // Close button - hide form but keep session active
            document.getElementById('closeOtpBtn').addEventListener('click', () => {
                const verificationOverlay = document.querySelector('.verification-page');
                verificationOverlay.style.display = 'none';
            });
            
            // Check if OTP session is still valid when page loads
            window.addEventListener('load', () => {
                const otpGeneratedAt = <?php echo $_SESSION['otp_generated_at'] ?? 0; ?>;
                const currentTime = Math.floor(Date.now() / 1000);
                const maxAge = 300; // 5 minutes
                
                if (otpGeneratedAt && (currentTime - otpGeneratedAt) > maxAge) {
                    // OTP expired, clear session and reload
                    fetch('login_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'cancel_otp',
                            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                        })
                    }).then(() => {
                        window.location.reload();
                    });
                }
            });
            
            // Periodic check for OTP expiration
            setInterval(() => {
                const otpGeneratedAt = <?php echo $_SESSION['otp_generated_at'] ?? 0; ?>;
                const currentTime = Math.floor(Date.now() / 1000);
                const maxAge = 300; // 5 minutes
                
                if (otpGeneratedAt && (currentTime - otpGeneratedAt) > maxAge) {
                    // OTP expired, clear session and reload
                    fetch('login_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'cancel_otp',
                            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                        })
                    }).then(() => {
                        window.location.reload();
                    });
                }
            }, 5000); // Check every 5 seconds
            
            // Show OTP form again if session is still active and user navigates back
            const otpGeneratedAt = <?php echo $_SESSION['otp_generated_at'] ?? 0; ?>;
            const currentTime = Math.floor(Date.now() / 1000);
            const maxAge = 300; // 5 minutes
            
            if (otpGeneratedAt && (currentTime - otpGeneratedAt) <= maxAge) {
                const verificationOverlay = document.querySelector('.verification-page');
                if (verificationOverlay && verificationOverlay.style.display === 'none') {
                    verificationOverlay.style.display = 'flex';
                }
            }
        </script>
    <?php endif; ?>

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
            console.warn('Modal initialization failed:', error);
        }
    </script>

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

                    /* Password validation function for admin signup */
                    window.validateSignupPassword = function(password) {
                        const hasUppercase = /[A-Z]/.test(password);
                        const hasLowercase = /[a-z]/.test(password);
                        const hasNumber = /\d/.test(password);
                        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                        
                        // Update requirement indicators
                        updateSignupRequirement('signupReqUppercase', hasUppercase);
                        updateSignupRequirement('signupReqLowercase', hasLowercase);
                        updateSignupRequirement('signupReqNumber', hasNumber);
                        updateSignupRequirement('signupReqSpecial', hasSpecial);
                    };

                    function updateSignupRequirement(elementId, isValid) {
                        const element = document.getElementById(elementId);
                        const icon = element.querySelector('i');
                        if (isValid) {
                            icon.className = 'fas fa-check-circle';
                            icon.style.color = '#28a745';
                            element.style.color = '#28a745';
                        } else {
                            icon.className = 'fas fa-times-circle';
                            icon.style.color = '#dc3545';
                            element.style.color = '#6c757d';
                        }
                    }

                    /* Form validation for admin signup */
                    function validateSignupForm() {
                        let isValid = true;
                        
                        // First Name
                        const firstName = document.getElementById('firstName').value.trim();
                        if (!firstName) {
                            document.getElementById('firstNameError').style.display = 'block';
                            isValid = false;
                        } else {
                            document.getElementById('firstNameError').style.display = 'none';
                        }
                        
                        // Last Name
                        const lastName = document.getElementById('lastName').value.trim();
                        if (!lastName) {
                            document.getElementById('lastNameError').style.display = 'block';
                            isValid = false;
                        } else {
                            document.getElementById('lastNameError').style.display = 'none';
                        }
                        
                        // Email/Phone
                        const isEmail = document.getElementById('contactEmail').checked;
                        if (isEmail) {
                            const email = document.getElementById('email').value.trim();
                            if (!email) {
                                document.getElementById('emailError').style.display = 'block';
                                isValid = false;
                            } else {
                                document.getElementById('emailError').style.display = 'none';
                            }
                        } else {
                            const phone = document.getElementById('phone').value.trim();
                            if (!phone) {
                                document.getElementById('phoneError').style.display = 'block';
                                isValid = false;
                            } else {
                                document.getElementById('phoneError').style.display = 'none';
                            }
                        }
                        
                        // Password
                        const password = document.getElementById('signupPassword').value;
                        if (!password) {
                            document.getElementById('signupPasswordError').style.display = 'block';
                            isValid = false;
                        } else {
                            document.getElementById('signupPasswordError').style.display = 'none';
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
                            if (d.success) {
                                // Always show OTP step — no bypass
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

                    // Login credentials form submission
                    safeAddEventListener('loginCredentialsForm', 'submit', function(e) {
                        e.preventDefault();
                        hideErr('loginErrBox');
                        
                        const username = document.getElementById('loginUsername').value.trim();
                        const password = document.getElementById('loginPassword').value;
                        
                        if (!username || !password) {
                            showErr('loginErrBox', 'loginErrTxt', 'Please enter username and password.');
                            return;
                        }
                        
                        setLoad('loginSubmitBtn', true);
                        const formData = new FormData();
                        formData.append('username', username);
                        formData.append('password', password);
                        formData.append('csrf_token', document.querySelector('#loginCredentialsForm input[name="csrf_token"]').value);
                        
                        fetch('index.php', {
                            method: 'POST',
                            body: formData
                        }).then(response => response.json())
                        .then(data => {
                            setLoad('loginSubmitBtn', false);
                            if (data.success) {
                                // Always show OTP step — no bypass
                                document.getElementById('loginStep1').style.display = 'none';
                                document.getElementById('loginStep2').style.display = 'block';
                                if (data.masked_contact) document.getElementById('loginOtpSubtitle').textContent = 'Code sent to ' + data.masked_contact;
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
                                showErr('loginErrBox', 'loginErrTxt', data.message || 'Invalid credentials.');
                            }
                        }).catch(() => {
                            setLoad('loginSubmitBtn', false);
                            showErr('loginErrBox', 'loginErrTxt', 'Connection error. Try again.');
                        });
                    });

                    safeAddEventListener('loginOtpForm', 'submit', e => {
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

                    document.getElementById('signupForm').addEventListener('submit', e => {
                        e.preventDefault();
                        hideErr('signupErrBox');
                        document.getElementById('emailWarning').style.display = 'none';

                        // Run validation
                        if (!validateSignupForm()) {
                            return;
                        }

                        const fn = document.getElementById('firstName').value.trim();
                        const ln = document.getElementById('lastName').value.trim();
                        const mi = document.getElementById('middleInitial') ? document.getElementById('middleInitial').value.trim() : '';
                        const un = document.getElementById('signupUsername').value.trim();
                        const pw = document.getElementById('signupPassword').value;
                        const cp = document.getElementById('signupConfirmPassword').value;
                        const mth = document.querySelector('input[name="contact_method"]:checked').value;
                        const em = document.getElementById('email').value.trim();
                        const ph = document.getElementById('phone').value.trim();
                        const agr = document.getElementById('terms').checked;

                        if (!/^[A-Za-z\s\-]+$/.test(fn) || !/^[A-Za-z\s\-]+$/.test(ln)) {
                            showErr('signupErrBox', 'signupErrTxt', 'Names must contain letters only.');
                            return;
                        }
                        if (mi && !/^[A-Z]{1,2}\.?$/.test(mi)) {
                            showErr('signupErrBox', 'signupErrTxt', 'Middle initial must be 1–2 letters (e.g. A. or AB).');
                            return;
                        }
                        if (!un || un.length < 3) {
                            showErr('signupErrBox', 'signupErrTxt', 'Username must be at least 3 characters.');
                            return;
                        }
                        if (!/^[A-Za-z0-9_\-\.]+$/.test(un)) {
                            showErr('signupErrBox', 'signupErrTxt', 'Username may only contain letters, numbers, _ - .');
                            return;
                        }
                        if (!pw || pw.length < 8) {
                            showErr('signupErrBox', 'signupErrTxt', 'Password must be at least 8 characters.');
                            return;
                        }
                        if (!/[A-Z]/.test(pw) || !/[a-z]/.test(pw) || !/[0-9]/.test(pw) || !/[\W_]/.test(pw)) {
                            showErr('signupErrBox', 'signupErrTxt', 'Password must include uppercase, lowercase, a number and a special character.');
                            return;
                        }
                        if (pw !== cp) {
                            showErr('signupErrBox', 'signupErrTxt', 'Passwords do not match.');
                            return;
                        }
                        if (mth === 'email' && !em) {
                            showErr('signupErrBox', 'signupErrTxt', 'Please enter your email address.');
                            return;
                        }
                        if (mth === 'phone' && !/^09\d{9}$/.test(ph)) {
                            showErr('signupErrBox', 'signupErrTxt', 'Please enter a valid phone number (09XXXXXXXXX).');
                            return;
                        }
                        if (!agr) {
                            showErr('signupErrBox', 'signupErrTxt', 'You must agree to the Terms of Service.');
                            return;
                        }

                        setLoad('signupSubmitBtn', true);
                        const fd = new FormData(e.target);
                        // Ensure terms value is sent even if browser quirks drop unchecked
                        if (agr) fd.set('terms', '1');

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
                                const dst = mth === 'email' ? em : ph;
                                document.getElementById('otpSubtitle').textContent = 'Code sent to: ' + dst;
                                if (d.dev_otp) console.log('[DEV] Sub-admin signup OTP:', d.dev_otp);
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
                                if (d.message && d.message.toLowerCase().includes('email')) {
                                    const w = document.getElementById('emailWarning');
                                    w.textContent = d.message;
                                    w.style.display = 'block';
                                } else {
                                    showErr('signupErrBox', 'signupErrTxt', d.message || 'An error occurred.');
                                }
                            }
                        }).catch(() => {
                            setLoad('signupSubmitBtn', false);
                            showErr('signupErrBox', 'signupErrTxt', 'Connection error. Please try again.');
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

                })();
            })
            .catch(err => console.error('Error loading modals:', err));
    </script>

</body>

</html>