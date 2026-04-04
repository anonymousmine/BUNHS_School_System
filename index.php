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
    </body>
    </html>
<?php
    exit;
}

require __DIR__ . '/vendor/autoload.php';

// ─── CSRF TOKEN ───────────────────────────────────────────────────────────────
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

$conn->query("CREATE TABLE IF NOT EXISTS school_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── HELPER FUNCTIONS ─────────────────────────────────────────────────────────
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

// ─── FETCH DYNAMIC DATA ───────────────────────────────────────────────────────
$today_date = date('Y-m-d');
$today_announcement = null;
$res = $conn->query("SELECT * FROM school_announcements WHERE announcement_date = '$today_date' LIMIT 1");
if ($res && $res->num_rows > 0) $today_announcement = $res->fetch_assoc();

// Fetch data for hero section
$total_students = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM students");
if ($res && $row = $res->fetch_assoc()) $total_students = (int)$row['total'];

$active_students = 0;
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

$school_rating_val = 0;
$school_rating_count = 0;
$res = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM school_ratings");
if ($res && $row = $res->fetch_assoc()) {
    $school_rating_val = round((float)$row['avg_rating'], 1);
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

$batch_success_pct = 0;
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

$clubs_list = [];
$total_clubs = 0;
$clubs_has_logo = false;
$clubs_has_status = false;
$clubs_col_check = $conn->query("SHOW COLUMNS FROM clubs");
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

$total_events = 0;
$res = $conn->query("SELECT COUNT(*) as total FROM events");
if ($res && $row = $res->fetch_assoc()) $total_events = (int)$row['total'];

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

$recent_news = [];
$res = $conn->query("SELECT * FROM news ORDER BY news_date DESC LIMIT 4");
if ($res) {
    while ($row = $res->fetch_assoc()) $recent_news[] = $row;
}

// Get settings
$founding_year = (int)get_setting($conn, 'school_founding_year', date('Y') - 7);
$years_of_excellence = date('Y') - $founding_year;
$about_photo = get_setting($conn, 'about_photo', 'assets/img/front pic/Buyoan School.jpg');
$cta_photo = get_setting($conn, 'cta_photo', 'assets/img/education/Students learning.jpg');

// Get homepage cards
$card_leadership = get_card($conn, 'leadership');
$card_cultural = get_card($conn, 'cultural');
$card_innovation = get_card($conn, 'innovation');
$cert_card1 = get_card($conn, 'cert_card1');
$cert_card2 = get_card($conn, 'cert_card2');
$cert_card3 = get_card($conn, 'cert_card3');

// Dynamic News - Using sample data from news_posts_dynamic.php
$recent_news = [
    [
        'id' => 1,
        'title' => 'BUNHS Celebrates 25th Founding Anniversary',
        'content' => 'Buyoan National High School marks a quarter-century of educational excellence with a week-long celebration featuring various activities including cultural presentations, academic competitions, and community service projects.',
        'news_date' => '2024-06-01',
        'author' => 'Buyoan National High School',
        'image' => 'blog-post-1.jpg'
    ],
    [
        'id' => 2,
        'title' => 'Students Excel in Regional Science Fair',
        'content' => 'Three BUNHS students brought home medals from the Regional Science and Technology Fair, showcasing innovative projects that address real-world problems in agriculture and environmental sustainability.',
        'news_date' => '2024-05-28',
        'author' => 'Science Department',
        'image' => 'blog-post-2.jpg'
    ],
    [
        'id' => 3,
        'title' => 'New Computer Lab Inauguration',
        'content' => 'The school inaugurated a state-of-the-art computer laboratory to enhance digital literacy among students, featuring 50 modern workstations with high-speed internet access.',
        'news_date' => '2024-05-20',
        'author' => 'IT Department',
        'image' => 'congrats sir mark.jpg'
    ],
    [
        'id' => 4,
        'title' => 'Teachers Attend Professional Development Workshop',
        'content' => 'Faculty members participated in a comprehensive professional development program focused on modern teaching methodologies and digital learning tools.',
        'news_date' => '2024-05-15',
        'author' => 'Academic Affairs',
        'image' => '589797383_122231892338064980_8508821627356952197_n.jpg'
    ]
];

// Dynamic Events - Using functions from events.php
function get_upcoming_events_homepage($conn, $limit = 4)
{
    $today = date("Y-m-d");
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, location, image FROM events WHERE event_date >= ? ORDER BY event_date ASC LIMIT ?");
    $stmt->bind_param("si", $today, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

// If no events in database, use sample events
$upcoming_events = get_upcoming_events_homepage($conn, 4);
if (empty($upcoming_events)) {
    $upcoming_events = [
        [
            'id' => 1,
            'title' => 'Graduation Ceremony 2024',
            'description' => 'Annual commencement ceremony for graduating students celebrating their academic achievements.',
            'event_date' => date('Y-m-d', strtotime('+1 month')),
            'category' => 'Academic',
            'event_start_time' => '08:00 AM',
            'location' => 'School Auditorium',
            'image' => 'blog-post-1.jpg'
        ],
        [
            'id' => 2,
            'title' => 'Science Fair Exhibition',
            'description' => 'Students showcase their innovative science projects and research findings.',
            'event_date' => date('Y-m-d', strtotime('+2 weeks')),
            'category' => 'Academic',
            'event_start_time' => '09:00 AM',
            'location' => 'Science Building',
            'image' => 'blog-post-2.jpg'
        ],
        [
            'id' => 3,
            'title' => 'Sports Festival',
            'description' => 'Annual sports competition featuring various athletic events and team sports.',
            'event_date' => date('Y-m-d', strtotime('+3 weeks')),
            'category' => 'Sports',
            'event_start_time' => '07:00 AM',
            'location' => 'School Grounds',
            'image' => 'congrats sir mark.jpg'
        ],
        [
            'id' => 4,
            'title' => 'Cultural Arts Night',
            'description' => 'Evening celebration showcasing student talents in music, dance, and theater.',
            'event_date' => date('Y-m-d', strtotime('+1 month + 1 week')),
            'category' => 'Cultural',
            'event_start_time' => '06:00 PM',
            'location' => 'School Auditorium',
            'image' => '589797383_122231892338064980_8508821627356952197_n.jpg'
        ]
    ];
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

$banner_is_closed = $today_announcement && (int)$today_announcement['is_closed'] === 1;
$banner_message = '';
if ($banner_is_closed) {
    $banner_message = !empty($today_announcement['custom_message'])
        ? htmlspecialchars($today_announcement['custom_message'])
        : 'Announcement: "The school is closed today. All classes and school activities are suspended."';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Buyoan National High School</title>
    
    <!-- CSRF Token for AJAX requests -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)); ?>">
    
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/img/logo.jpg" type="image/x-icon">
    
    <style>
        /* ── Hero Section Styles ── */
        .hero-container {
            position: relative;
            background: linear-gradient(135deg, #1a3a2a 0%, #2d6a4f 100%);
            padding: 80px 0;
            color: white;
        }

        .hero-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero-content h2 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 30px 0;
        }

        .btn-apply, .btn-tour {
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-apply {
            background: #52b788;
            color: white;
        }

        .btn-apply:hover {
            background: #40916c;
            transform: translateY(-2px);
        }

        .btn-tour {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-tour:hover {
            background: white;
            color: #1a3a2a;
        }

        .announcement {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 25px;
            border-radius: 50px;
            display: inline-block;
            margin-top: 20px;
        }

        .announcement-badge {
            background: #e63946;
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            margin-right: 10px;
        }

        .highlights-container {
            margin-top: -50px;
            position: relative;
            z-index: 10;
        }

        .highlight-item {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            height: 100%;
            transition: transform 0.3s ease;
        }

        .highlight-item:hover {
            transform: translateY(-5px);
        }

        .highlight-item .icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }

        .highlight-item h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #1a3a2a;
        }

        .highlight-item p {
            color: #666;
            line-height: 1.6;
        }

        .event-banner {
            background: #f8f9fa;
            padding: 20px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .event-date {
            text-align: center;
        }

        .event-date .month {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
        }

        .event-date .day {
            display: block;
            font-size: 32px;
            font-weight: 700;
            color: #1a3a2a;
        }

        .event-banner h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1a3a2a;
        }

        .event-banner p {
            color: #666;
            margin-bottom: 0;
        }

        .btn-register {
            background: #52b788;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-register:hover {
            background: #40916c;
            color: white;
        }

        @media (max-width: 768px) {
            .hero-content h2 {
                font-size: 2rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-apply, .btn-tour {
                width: 200px;
                text-align: center;
            }
        }

        /* ── Events Section Styles ── */
        .event-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            height: 100%;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .event-img {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .event-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .event-card:hover .event-img img {
            transform: scale(1.05);
        }

        .event-date-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(26, 58, 42, 0.9);
            color: white;
            text-align: center;
            border-radius: 10px;
            padding: 8px 12px;
            min-width: 60px;
        }

        .event-date-badge .month {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1;
        }

        .event-date-badge .day {
            display: block;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            margin-top: 2px;
        }

        .event-content {
            padding: 25px;
        }

        .event-category {
            display: inline-block;
            background: #f0f7f4;
            color: #2d6a4f;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .event-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a3a2a;
            line-height: 1.3;
        }

        .event-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .event-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .event-time, .event-location {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            color: #666;
        }

        .event-time i, .event-location i {
            color: #52b788;
        }

        .event-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #52b788;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .event-link:hover {
            color: #40916c;
        }

        .btn-primary {
            background: #52b788;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            border: none;
        }

        .btn-primary:hover {
            background: #40916c;
            color: white;
            transform: translateY(-2px);
        }

        /* ── OTP Form Styles ── */
        #otpFormContainer {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 450px;
            margin: 0 auto;
        }

        #otpFormContainer .bm-shield-wrap {
            background: linear-gradient(135deg, rgba(201, 168, 76, 0.12), rgba(82, 183, 136, 0.08));
            border-color: rgba(201, 168, 76, 0.28);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        #otpFormContainer .bm-shield-wrap i {
            color: #f4a118;
            font-size: 40px;
        }

        #otpFormContainer h3 {
            color: #1a3a2a;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 25px;
        }

        #otpFormContainer p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 1.1rem;
        }

        #otpFormContainer .bm-otp-row {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 30px;
        }

        #otpFormContainer .bm-otp-box {
            width: 55px;
            height: 70px;
            border: 2px solid #ddd;
            border-radius: 12px;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            color: #1a3a2a;
            transition: border-color 0.3s ease;
        }

        #otpFormContainer .bm-otp-box:focus {
            border-color: #52b788;
            outline: none;
            box-shadow: 0 0 0 3px rgba(82, 183, 136, 0.1);
        }

        #otpFormContainer .bm-timer {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }

        #otpFormContainer .bm-timer i {
            color: #52b788;
            margin-right: 8px;
        }

        #otpFormContainer .btn-verify {
            background: linear-gradient(135deg, #52b788, #40916c);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
            width: 100%;
            max-width: 200px;
        }

        #otpFormContainer .btn-verify:hover {
            background: linear-gradient(135deg, #40916c, #2d6a4f);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* ── OTP Input Boxes - Override Modal Styles ── */
        #loginOtpBoxes .bm-otp-box {
            width: 55px !important;
            height: 70px !important;
            border: 2px solid #ddd !important;
            border-radius: 12px !important;
            text-align: center !important;
            font-size: 28px !important;
            font-weight: 700 !important;
            color: #1a3a2a !important;
            background: #f8f9fa !important;
            transition: border-color 0.3s ease !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 10 !important;
        }

        #loginOtpBoxes .bm-otp-box:focus {
            border-color: #52b788 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(82, 183, 136, 0.1) !important;
            background: #fff !important;
        }

        #loginOtpBoxes .bm-otp-box::placeholder {
            color: #999 !important;
            opacity: 0.5 !important;
        }

        /* ── Login Form Layout Improvements ── */
        #loginStep1 {
            padding: 10px 0;
        }

        #loginStep1 .bm-field {
            margin-bottom: 18px;
        }

        #loginStep1 .bm-input {
            padding: 14px 16px 14px 42px;
            font-size: 14px;
            border-radius: 12px;
        }

        #loginStep1 .bm-btn {
            padding: 14px 24px;
            font-size: 14px;
            border-radius: 12px;
            margin-top: 8px;
        }

        #loginStep1 .bm-err {
            margin-bottom: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
        }

        #loginStep1 .bm-ghost {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        #loginStep1 .bm-ghost:hover {
            background: rgba(82, 183, 136, 0.1);
            color: var(--bunhs-green);
        }
    </style>
</head>

<body>
    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
            <a href="index.php" class="logo d-flex align-items-center">
                <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height:85px;width:auto;border-radius:50px;">
                <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
            </a>
            <div id="nav-placeholder"></div>
        </div>
    </header>

    <main class="main">
        <!-- Event Banner -->
        <?php if ($banner_is_closed): ?>
        <div class="event-banner school-closed">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <div class="event-date">
                            <span class="month">—</span>
                            <span class="day">—</span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h3>School Closure Notice</h3>
                        <p><?php echo $banner_message; ?></p>
                    </div>
                    <div class="col-md-2">
                        <button class="btn-register" disabled>Registration Closed</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hero Section -->
        <section id="hero" class="hero section">

            <div class="hero-container">
                <div class="hero-content">
                    <h2 style="color:white;">Web-Based Information System for Buyoan National High School</h2>
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
        </section>

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
        </section>

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
        </section>

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
        </section>

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
        </section>

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
                            // Check if image already has full path
                            if (strpos($news_item['image'], 'assets/') === 0) {
                                $news_img = $news_item['image'];
                            }
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
        </section>

        <!-- ═══════════════════════════════════════════════
             EVENTS — Dynamic from events.php
        ═══════════════════════════════════════════════ -->
        <section id="events" class="events section">
            <div class="container section-title">
                <h2>Upcoming Events</h2>
                <p>Buyoan National High School hosts a variety of events throughout the school year, including academic competitions, sports meets, cultural celebrations, and community outreach programs.</p>
            </div>

            <div class="container">
                <div class="row gy-4">
                    <?php if (count($upcoming_events) > 0): ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <?php
                            // Get event image with proper path
                            $event_img = !empty($event['image'])
                                ? 'assets/img/' . $event['image']
                                : 'assets/img/education/default-event.jpg';
                            // Check if image already has full path
                            if (strpos($event['image'], 'assets/') === 0) {
                                $event_img = $event['image'];
                            }
                            $event_date_fmt = !empty($event['event_date'])
                                ? date('F j, Y', strtotime($event['event_date']))
                                : 'TBD';
                            $event_time = !empty($event['event_start_time'])
                                ? $event['event_start_time']
                                : 'Time TBD';
                            ?>
                            <div class="col-lg-3 col-md-6">
                                <div class="event-card" style="cursor:pointer;" onclick="window.location='events.php?id=<?php echo (int)$event['id']; ?>'">
                                    <div class="event-img">
                                        <img src="<?php echo htmlspecialchars($event_img); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                        <div class="event-date-badge">
                                            <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                            <span class="day"><?php echo date('j', strtotime($event['event_date'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="event-content">
                                        <div class="event-category"><?php echo htmlspecialchars($event['category']); ?></div>
                                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                        <p class="event-description"><?php echo htmlspecialchars(mb_substr(strip_tags($event['description']), 0, 100)) . (strlen(strip_tags($event['description'])) > 100 ? '...' : ''); ?></p>
                                        <div class="event-meta">
                                            <div class="event-time">
                                                <i class="fas fa-clock"></i>
                                                <?php echo htmlspecialchars($event_time); ?>
                                            </div>
                                            <div class="event-location">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($event['location'] ?? 'Main Campus'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="events.php?id=<?php echo (int)$event['id']; ?>" class="event-link stretched-link">
                                        <span>View Details</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5 text-muted">
                            <i class="fas fa-calendar-alt" style="font-size:48px;margin-bottom:16px;display:block;color:#ccc;"></i>
                            No upcoming events scheduled. Check back soon!
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-5">
                    <a href="events.php" class="btn-primary">View All Events</a>
                </div>
            </div>
        </section>
    </main>

    <footer id="footer-placeholder"></footer>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Load modals dynamically -->
    <script>
    // Load modals after page loads
    fetch('modals.php')
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.body.insertAdjacentHTML('beforeend', html);
            console.log('Modals loaded successfully');
            
            // Initialize modal event listeners after modals are loaded
            setTimeout(function() {
                // Handle signup button clicks
                const signupBtns = document.querySelectorAll('[data-bs-target="#signupModal"]');
                signupBtns.forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Signup button clicked');
                        const signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
                        signupModal.show();
                    });
                });
                
                // Handle login button clicks
                const loginBtns = document.querySelectorAll('[data-bs-target="#loginModal"]');
                loginBtns.forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Login button clicked');
                        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                        loginModal.show();
                    });
                });
                
                // Add login form validation
                setTimeout(function() {
                    const loginForm = document.getElementById('loginForm');
                    const loginSubmitBtn = document.getElementById('loginSubmitBtn');
                    
                    if (loginSubmitBtn) {
                        loginSubmitBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('Login submit clicked');
                            
                            let isValid = true;
                            
                            // Validate Username/Email
                            const loginUsername = document.getElementById('loginUsername');
                            const loginUsernameError = document.getElementById('loginUsernameError');
                            if (loginUsername && loginUsername.value.trim() === '') {
                                if (loginUsernameError) loginUsernameError.style.display = 'block';
                                isValid = false;
                            } else if (loginUsernameError) {
                                loginUsernameError.style.display = 'none';
                            }
                            
                            // Validate Password
                            const loginPassword = document.getElementById('loginPassword');
                            const loginPasswordError = document.getElementById('loginPasswordError');
                            if (loginPassword && loginPassword.value.trim() === '') {
                                if (loginPasswordError) loginPasswordError.style.display = 'block';
                                isValid = false;
                            } else if (loginPasswordError) {
                                loginPasswordError.style.display = 'none';
                            }
                            
                            if (isValid) {
                                console.log('Login form is valid, proceeding...');
                                
                                // Show loading state
                                const originalText = loginSubmitBtn.innerHTML;
                                loginSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                                loginSubmitBtn.disabled = true;
                                
                                // Prepare form data
                                const formData = new FormData();
                                formData.append('action', 'login_verify_credentials');
                                formData.append('username', loginUsername.value.trim());
                                formData.append('password', loginPassword.value.trim());
                                formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
                                
                                // Submit to login_otp.php
                                fetch('login_otp.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    console.log('Login response:', data);
                                    
                                    if (data.success) {
                                        // Hide login form and show OTP form
                                        setTimeout(() => {
                                            const loginFormContainer = document.getElementById('loginStep1');
                                            const loginOtpContainer = document.getElementById('loginStep2');
                                            
                                            console.log('Login form container:', loginFormContainer);
                                            console.log('Login OTP container:', loginOtpContainer);
                                            
                                            if (loginFormContainer && loginOtpContainer) {
                                                loginFormContainer.style.display = 'none';
                                                loginOtpContainer.style.display = 'block';
                                                
                                                // Update OTP form info
                                                const loginOtpSubtitle = document.getElementById('loginOtpSubtitle');
                                                if (loginOtpSubtitle) {
                                                    loginOtpSubtitle.innerHTML = `Enter the 6-digit code sent to ${data.masked_contact || 'your registered email'}.`;
                                                }
                                                
                                                // Start countdown timer
                                                if (typeof startOTPTimer === 'function') {
                                                    startOTPTimer();
                                                }
                                                
                                                // Initialize OTP input boxes
                                                const loginOtpBoxes = document.querySelectorAll('#loginOtpBoxes .bm-otp-box');
                                                loginOtpBoxes.forEach((box, index) => {
                                                    // Force visibility
                                                    box.style.display = 'block';
                                                    box.style.visibility = 'visible';
                                                    box.style.opacity = '1';
                                                    box.style.position = 'relative';
                                                    box.style.zIndex = '10';
                                                    
                                                    // Add input event listeners
                                                    box.addEventListener('input', function(e) {
                                                        if (e.target.value.length === 1) {
                                                            // Move to next box
                                                            if (index < loginOtpBoxes.length - 1) {
                                                                loginOtpBoxes[index + 1].focus();
                                                            }
                                                        }
                                                    });
                                                    
                                                    box.addEventListener('keydown', function(e) {
                                                        if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                                                            // Move to previous box
                                                            loginOtpBoxes[index - 1].focus();
                                                        }
                                                    });
                                                    
                                                    box.addEventListener('paste', function(e) {
                                                        e.preventDefault();
                                                        const pastedData = e.clipboardData.getData('text').slice(0, 6);
                                                        const digits = pastedData.split('');
                                                        digits.forEach((digit, i) => {
                                                            if (index + i < loginOtpBoxes.length && /[0-9]/.test(digit)) {
                                                                loginOtpBoxes[index + i].value = digit;
                                                            }
                                                        });
                                                    });
                                                });
                                                
                                                console.log('Login OTP boxes initialized:', loginOtpBoxes.length);
                                                
                                                // Attach OTP form submit event listener here
                                                const loginOtpForm = document.getElementById('loginOtpForm');
                                                console.log('OTP form found after initialization:', loginOtpForm);
                                                if (loginOtpForm) {
                                                    // Remove any existing event listeners to prevent duplicates
                                                    const newForm = loginOtpForm.cloneNode(true);
                                                    loginOtpForm.parentNode.replaceChild(newForm, loginOtpForm);
                                                    
                                                    // Add fresh event listener
                                                    newForm.addEventListener('submit', function(e) {
                                                        console.log('OTP form submitted, preventing default');
                                                        e.preventDefault();
                                                        
                                                        // Collect OTP from all input boxes
                                                        const otpBoxes = document.querySelectorAll('#loginOtpBoxes .bm-otp-box');
                                                        let otpCode = '';
                                                        otpBoxes.forEach(box => {
                                                            otpCode += box.value;
                                                        });
                                                        
                                                        console.log('OTP code collected:', otpCode);
                                                        
                                                        // Set hidden OTP field
                                                        const hiddenOtp = document.getElementById('loginOtpHidden');
                                                        if (hiddenOtp) {
                                                            hiddenOtp.value = otpCode;
                                                        }
                                                        
                                                        // Validate OTP
                                                        if (otpCode.length !== 6) {
                                                            const errBox = document.getElementById('loginOtpErrBox');
                                                            const errTxt = document.getElementById('loginOtpErrTxt');
                                                            if (errBox && errTxt) {
                                                                errTxt.textContent = 'Please enter all 6 digits';
                                                                errBox.style.display = 'block';
                                                            }
                                                            return;
                                                        }
                                                        
                                                        // Show loading state
                                                        const submitBtn = document.getElementById('loginVerifyBtn');
                                                        if (submitBtn) {
                                                            submitBtn.disabled = true;
                                                            submitBtn.classList.add('loading');
                                                        }
                                                        
                                                        // Submit OTP verification
                                                        const formData = new FormData(newForm);
                                                        formData.append('action', 'login_verify_otp');
                                                        
                                                        console.log('Submitting OTP verification...');
                                                        
                                                        fetch('login_otp.php', {
                                                            method: 'POST',
                                                            body: formData
                                                        })
                                                        .then(response => response.json())
                                                        .then(data => {
                                                            console.log('OTP verification response:', data);
                                                            if (data.success) {
                                                                // Redirect to admin dashboard
                                                                window.location.href = 'admin_account/admin_dashboard.php';
                                                            } else {
                                                                // Show error
                                                                const errBox = document.getElementById('loginOtpErrBox');
                                                                const errTxt = document.getElementById('loginOtpErrTxt');
                                                                if (errBox && errTxt) {
                                                                    errTxt.textContent = data.message || 'Invalid OTP. Please try again.';
                                                                    errBox.style.display = 'block';
                                                                }
                                                                
                                                                // Clear OTP boxes
                                                                otpBoxes.forEach(box => {
                                                                    box.value = '';
                                                                });
                                                                
                                                                // Focus first box
                                                                if (otpBoxes.length > 0) {
                                                                    otpBoxes[0].focus();
                                                                }
                                                            }
                                                        })
                                                        .catch(error => {
                                                            console.error('OTP verification error:', error);
                                                            const errBox = document.getElementById('loginOtpErrBox');
                                                            const errTxt = document.getElementById('loginOtpErrTxt');
                                                            if (errBox && errTxt) {
                                                                errTxt.textContent = 'Connection error. Please try again.';
                                                                errBox.style.display = 'block';
                                                            }
                                                        })
                                                        .finally(() => {
                                                            // Hide loading state
                                                            if (submitBtn) {
                                                                submitBtn.disabled = false;
                                                                submitBtn.classList.remove('loading');
                                                            }
                                                        });
                                                    });
                                                }
                                            } else {
                                                console.error('Login OTP containers not found');
                                            }
                                        }, 100); // Small delay to ensure DOM is ready
                                    } else {
                                        // Show error message
                                        const loginErrBox = document.getElementById('loginErrBox');
                                        const loginErrTxt = document.getElementById('loginErrTxt');
                                        if (loginErrBox && loginErrTxt) {
                                            loginErrTxt.textContent = data.message || 'Login failed. Please try again.';
                                            loginErrBox.style.display = 'block';
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Login error:', error);
                                    const loginErrBox = document.getElementById('loginErrBox');
                                    const loginErrTxt = document.getElementById('loginErrTxt');
                                    if (loginErrBox && loginErrTxt) {
                                        loginErrTxt.textContent = 'Connection error. Please try again.';
                                        loginErrBox.style.display = 'block';
                                    }
                                })
                                .finally(() => {
                                    // Restore button state
                                    loginSubmitBtn.innerHTML = originalText;
                                    loginSubmitBtn.disabled = false;
                                });
                            } else {
                                console.log('Login form validation failed');
                            }
                        });
                    }
                    
                    // Add real-time validation for login form
                    if (loginForm) {
                        const loginUsername = document.getElementById('loginUsername');
                        const loginPassword = document.getElementById('loginPassword');
                        const loginUsernameError = document.getElementById('loginUsernameError');
                        const loginPasswordError = document.getElementById('loginPasswordError');
                        
                        if (loginUsername) {
                            loginUsername.addEventListener('input', function() {
                                if (this.value.trim() !== '' && loginUsernameError) {
                                    loginUsernameError.style.display = 'none';
                                }
                            });
                        }
                        
                        if (loginPassword) {
                            loginPassword.addEventListener('input', function() {
                                if (this.value.trim() !== '' && loginPasswordError) {
                                    loginPasswordError.style.display = 'none';
                                }
                            });
                        }
                    }
                }, 300);
                
                console.log('Modal event listeners attached');
                
                // Add form validation for signup form
                setTimeout(function() {
                    const signupForm = document.getElementById('signupForm');
                    const sendCodeBtn = document.getElementById('signupSubmitBtn');
                    
                    // Position validation indicators properly below each input field
                    const positionValidationBelowInput = function(inputId, errorId) {
                        const input = document.getElementById(inputId);
                        const error = document.getElementById(errorId);
                        if (input && error) {
                            // Move error element right after the input's parent container
                            const inputContainer = input.closest('.bm-field');
                            if (inputContainer) {
                                inputContainer.appendChild(error);
                            }
                        }
                    };
                    
                    // Position all validation errors below their respective input fields
                    positionValidationBelowInput('firstName', 'firstNameError');
                    positionValidationBelowInput('lastName', 'lastNameError');
                    positionValidationBelowInput('signupUsername', 'usernameError');
                    positionValidationBelowInput('email', 'emailError');
                    positionValidationBelowInput('phone', 'phoneError');
                    
                    // Special handling for password fields to maintain proper layout
                    const passwordField = document.getElementById('signupPassword');
                    const passwordError = document.getElementById('signupPasswordError');
                    const passwordRequirements = document.getElementById('signupPasswordRequirements');
                    
                    if (passwordField && passwordError && passwordRequirements) {
                        const passwordContainer = passwordField.closest('.bm-field');
                        if (passwordContainer) {
                            // Insert password error after the input container
                            const inputWrap = passwordField.closest('.bm-input-wrap');
                            if (inputWrap) {
                                // Insert error right after input wrap, before requirements
                                inputWrap.insertAdjacentElement('afterend', passwordError);
                                // Keep requirements in their original position
                            }
                        }
                    }
                    
                    positionValidationBelowInput('signupConfirmPassword', 'signupConfirmPasswordError');
                    positionValidationBelowInput('terms', 'termsError');
                    
                    if (sendCodeBtn) {
                        sendCodeBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            console.log('Send Verification Code clicked');
                            
                            let isValid = true;
                            
                            // Validate First Name
                            const firstName = document.getElementById('firstName');
                            const firstNameError = document.getElementById('firstNameError');
                            if (firstName && firstName.value.trim() === '') {
                                if (firstNameError) {
                                    firstNameError.style.display = 'block';
                                    firstNameError.style.marginTop = '4px';
                                    firstNameError.style.marginBottom = '8px';
                                }
                                isValid = false;
                            } else if (firstNameError) {
                                firstNameError.style.display = 'none';
                            }
                            
                            // Validate Last Name
                            const lastName = document.getElementById('lastName');
                            const lastNameError = document.getElementById('lastNameError');
                            if (lastName && lastName.value.trim() === '') {
                                if (lastNameError) {
                                    lastNameError.style.display = 'block';
                                    lastNameError.style.marginTop = '4px';
                                    lastNameError.style.marginBottom = '8px';
                                }
                                isValid = false;
                            } else if (lastNameError) {
                                lastNameError.style.display = 'none';
                            }
                            
                            // Validate Username
                            const username = document.getElementById('signupUsername');
                            const usernameError = document.getElementById('usernameError');
                            if (username && username.value.trim() === '') {
                                if (usernameError) {
                                    usernameError.style.display = 'block';
                                    usernameError.style.marginTop = '4px';
                                    usernameError.style.marginBottom = '8px';
                                }
                                isValid = false;
                            } else if (usernameError) {
                                usernameError.style.display = 'none';
                            }
                            
                            // Validate Email (Gmail only)
                            const email = document.getElementById('email');
                            const emailError = document.getElementById('emailError');
                            const emailFormatError = document.getElementById('emailFormatError');
                            
                            if (!email || email.value.trim() === '') {
                                if (emailError) {
                                    emailError.style.display = 'block';
                                    emailError.style.marginTop = '4px';
                                    emailError.style.marginBottom = '8px';
                                }
                                isValid = false;
                            } else {
                                // Use Gmail validation function
                                const isGmailValid = window.validateGmailEmail(email.value);
                                if (!isGmailValid) {
                                    if (emailError) {
                                        emailError.style.display = 'block';
                                        emailError.style.marginTop = '4px';
                                        emailError.style.marginBottom = '8px';
                                    }
                                    isValid = false;
                                } else if (emailError) {
                                    emailError.style.display = 'none';
                                }
                            }
                            
                            // Validate Password
                            const password = document.getElementById('signupPassword');
                            const passwordError = document.getElementById('signupPasswordError');
                            if (password && password.value.trim() === '') {
                                if (passwordError) {
                                    passwordError.style.display = 'block';
                                    passwordError.style.marginTop = '4px';
                                    passwordError.style.marginBottom = '4px';
                                    passwordError.style.fontSize = '10.5px';
                                    passwordError.style.color = '#e53935';
                                }
                                isValid = false;
                            } else if (passwordError) {
                                passwordError.style.display = 'none';
                            }
                            
                            // Validate Confirm Password
                            const confirmPassword = document.getElementById('signupConfirmPassword');
                            const confirmPasswordError = document.getElementById('signupConfirmPasswordError');
                            const passwordMismatchError = document.getElementById('passwordMismatchError');
                            if (confirmPassword && confirmPassword.value.trim() === '') {
                                if (confirmPasswordError) {
                                    confirmPasswordError.style.display = 'block';
                                    confirmPasswordError.style.marginTop = '4px';
                                    confirmPasswordError.style.marginBottom = '8px';
                                }
                                isValid = false;
                            } else if (confirmPasswordError) {
                                confirmPasswordError.style.display = 'none';
                            }
                            
                            // Check password match
                            if (password && confirmPassword && password.value !== confirmPassword.value) {
                                if (passwordMismatchError) {
                                    passwordMismatchError.style.display = 'block';
                                    passwordMismatchError.style.marginTop = '4px';
                                    passwordMismatchError.style.marginBottom = '8px';
                                }
                                isValid = false;
                            } else if (passwordMismatchError) {
                                passwordMismatchError.style.display = 'none';
                            }
                            
                            // Validate Terms
                            const terms = document.getElementById('terms');
                            const termsError = document.getElementById('termsError');
                            if (terms && !terms.checked) {
                                if (termsError) {
                                    termsError.style.display = 'block';
                                    termsError.style.marginTop = '4px';
                                    termsError.style.marginBottom = '8px';
                                }
                                isValid = false;
                            } else if (termsError) {
                                termsError.style.display = 'none';
                            }
                            
                            if (isValid) {
                                console.log('Form is valid, proceeding with OTP generation...');
                                
                                // Show loading state
                                const submitBtn = document.getElementById('signupSubmitBtn');
                                if (submitBtn) {
                                    submitBtn.disabled = true;
                                    submitBtn.classList.add('loading');
                                }
                                
                                // Collect form data
                                const formData = new FormData(signupForm);
                                formData.append('action', 'send_otp');
                                formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
                                
                                // Submit to backend for OTP generation
                                fetch('login_otp.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    console.log('OTP response:', data);
                                    if (data.success) {
                                        // Hide signup form and show OTP form
                                        const signupFormContainer = document.getElementById('signupFormContainer');
                                        const otpFormContainer = document.getElementById('otpFormContainer');
                                        
                                        if (signupFormContainer && otpFormContainer) {
                                            signupFormContainer.style.display = 'none';
                                            otpFormContainer.style.display = 'block';
                                            
                                            // Update OTP subtitle
                                            const otpSubtitle = document.getElementById('otpSubtitle');
                                            if (otpSubtitle) {
                                                otpSubtitle.innerHTML = `Enter the 6-digit code sent to ${data.masked_email || 'your Gmail address'}.`;
                                            }
                                            
                                            // Initialize OTP input boxes
                                            const otpBoxes = document.querySelectorAll('#signupOtpBoxes .bm-otp-box');
                                            otpBoxes.forEach((box, index) => {
                                                box.value = '';
                                                box.addEventListener('input', function(e) {
                                                    if (e.target.value.length === 1) {
                                                        if (index < otpBoxes.length - 1) {
                                                            otpBoxes[index + 1].focus();
                                                        }
                                                    }
                                                });
                                                
                                                box.addEventListener('keydown', function(e) {
                                                    if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                                                        otpBoxes[index - 1].focus();
                                                    }
                                                });
                                                
                                                box.addEventListener('paste', function(e) {
                                                    e.preventDefault();
                                                    const pastedData = e.clipboardData.getData('text').slice(0, 6);
                                                    const digits = pastedData.split('');
                                                    digits.forEach((digit, i) => {
                                                        if (index + i < otpBoxes.length && /[0-9]/.test(digit)) {
                                                            otpBoxes[index + i].value = digit;
                                                        }
                                                    });
                                                });
                                            });
                                            
                                            // Focus first OTP box
                                            if (otpBoxes.length > 0) {
                                                otpBoxes[0].focus();
                                            }
                                            
                                            // Start OTP timer
                                            startSignupOTPTimer();
                                        }
                                    } else {
                                        // Show error message
                                        const errBox = document.getElementById('signupErrBox');
                                        const errTxt = document.getElementById('signupErrTxt');
                                        if (errBox && errTxt) {
                                            errTxt.textContent = data.message || 'Failed to send verification code';
                                            errBox.style.display = 'block';
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('OTP request error:', error);
                                    const errBox = document.getElementById('signupErrBox');
                                    const errTxt = document.getElementById('signupErrTxt');
                                    if (errBox && errTxt) {
                                        errTxt.textContent = 'Connection error. Please try again.';
                                        errBox.style.display = 'block';
                                    }
                                })
                                .finally(() => {
                                    // Hide loading state
                                    if (submitBtn) {
                                        submitBtn.disabled = false;
                                        submitBtn.classList.remove('loading');
                                    }
                                });
                            } else {
                                console.log('Form validation failed');
                            }
                        });
                    }
                    
                    // Add real-time validation to hide errors as users type
                    const firstName = document.getElementById('firstName');
                    const firstNameError = document.getElementById('firstNameError');
                    if (firstName && firstNameError) {
                        firstName.addEventListener('input', function() {
                            if (this.value.trim() !== '') {
                                firstNameError.style.display = 'none';
                            }
                        });
                    }
                    
                    const lastName = document.getElementById('lastName');
                    const lastNameError = document.getElementById('lastNameError');
                    if (lastName && lastNameError) {
                        lastName.addEventListener('input', function() {
                            if (this.value.trim() !== '') {
                                lastNameError.style.display = 'none';
                            }
                        });
                    }
                    
                    const username = document.getElementById('signupUsername');
                    const usernameError = document.getElementById('usernameError');
                    if (username && usernameError) {
                        username.addEventListener('input', function() {
                            if (this.value.trim() !== '') {
                                usernameError.style.display = 'none';
                            }
                        });
                    }
                    
                    const email = document.getElementById('email');
                    const emailError = document.getElementById('emailError');
                    if (email && emailError) {
                        email.addEventListener('input', function() {
                            if (this.value.trim() !== '') {
                                emailError.style.display = 'none';
                            }
                        });
                    }
                    
                    const phone = document.getElementById('phone');
                    const phoneError = document.getElementById('phoneError');
                    if (phone && phoneError) {
                        phone.addEventListener('input', function() {
                            if (this.value.trim() !== '') {
                                phoneError.style.display = 'none';
                            }
                        });
                    }
                    
                    const password = document.getElementById('signupPassword');
                    const passwordErrorRealtime = document.getElementById('signupPasswordError');
                    if (password && passwordErrorRealtime) {
                        password.addEventListener('input', function() {
                            if (this.value.trim() !== '') {
                                passwordErrorRealtime.style.display = 'none';
                            }
                        });
                    }
                    
                    const confirmPassword = document.getElementById('signupConfirmPassword');
                    const confirmPasswordError = document.getElementById('signupConfirmPasswordError');
                    const passwordMismatchError = document.getElementById('passwordMismatchError');
                    if (confirmPassword) {
                        confirmPassword.addEventListener('input', function() {
                            if (this.value.trim() !== '') {
                                if (confirmPasswordError) confirmPasswordError.style.display = 'none';
                            }
                            // Check password match in real-time
                            if (password && this.value === password.value) {
                                if (passwordMismatchError) passwordMismatchError.style.display = 'none';
                            }
                        });
                    }
                    
                    const terms = document.getElementById('terms');
                    const termsError = document.getElementById('termsError');
                    if (terms && termsError) {
                        terms.addEventListener('change', function() {
                            if (this.checked) {
                                termsError.style.display = 'none';
                            }
                        });
                    }
                    
                    // Fix modal overlay issue and improve performance
                    const modals = document.querySelectorAll('.modal');
                    modals.forEach(function(modal) {
                        modal.addEventListener('hidden.bs.modal', function() {
                            // Use requestAnimationFrame for better performance
                            requestAnimationFrame(function() {
                                // Remove modal-open class from body
                                document.body.classList.remove('modal-open');
                                // Remove all modal backdrops
                                const backdrops = document.querySelectorAll('.modal-backdrop');
                                backdrops.forEach(function(backdrop) {
                                    backdrop.remove();
                                });
                                // Restore body overflow
                                document.body.style.overflow = '';
                                document.body.style.paddingRight = '';
                                // Fix aria-hidden issue
                                modal.setAttribute('aria-hidden', 'false');
                                console.log('Modal overlay cleared');
                            });
                        });
                    });
                }, 100); // Reduced timeout for better performance
            }, 100);
        })
        .catch(function(error) {
            console.warn('Error loading modals:', error);
        });
    </script>

    <script>
    console.log('JavaScript is executing!');
    
    // Enhanced password validation function
    window.validateSignupPassword = function(password) {
        console.log('validateSignupPassword called with:', password);
        
        // Real validation checks
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[!@#$%^&*]/.test(password);
        const minLength = password.length >= 8;
        
        // Update requirement indicators if they exist
        const elements = {
            'signupReqUppercase': hasUppercase,
            'signupReqLowercase': hasLowercase,
            'signupReqNumber': hasNumber,
            'signupReqSpecial': hasSpecial
        };
        
        for (const [elementId, isValid] of Object.entries(elements)) {
            const element = document.getElementById(elementId);
            if (element) {
                const icon = element.querySelector('i');
                if (icon) {
                    if (isValid) {
                        icon.className = 'fas fa-check-circle';
                        icon.style.color = '#28a745';
                    } else {
                        icon.className = 'fas fa-times-circle';
                        icon.style.color = '#dc3545';
                    }
                }
            }
        }
        
        // Show/hide password strength bar
        const strengthWrap = document.getElementById('pwStrengthWrap');
        if (strengthWrap) {
            strengthWrap.style.display = password.length > 0 ? 'block' : 'none';
        }
        
        // Update password strength bar
        let strength = 0;
        if (hasUppercase) strength++;
        if (hasLowercase) strength++;
        if (hasNumber) strength++;
        if (hasSpecial) strength++;
        if (password.length >= 12) strength++;
        
        const strengthColors = ['#dc3545', '#ffc107', '#fd7e14', '#20c997', '#28a745'];
        const strengthLabels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        
        for (let i = 1; i <= 4; i++) {
            const bar = document.getElementById(`pws${i}`);
            if (bar) {
                bar.style.background = i <= strength ? strengthColors[strength] : '#dde8e2';
            }
        }
        
        const strengthLabel = document.getElementById('pwStrengthLabel');
        if (strengthLabel) {
            strengthLabel.textContent = password.length > 0 ? strengthLabels[Math.min(strength, 4)] : '';
        }
        
        return minLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
    };
    
    console.log('validateSignupPassword function defined:', typeof window.validateSignupPassword);
    
    // Gmail validation function
    window.validateGmailEmail = function(email) {
        console.log('validateGmailEmail called with:', email);
        
        const emailError = document.getElementById('emailError');
        const emailFormatError = document.getElementById('emailFormatError');
        const emailWarning = document.getElementById('emailWarning');
        
        // Hide all errors initially
        if (emailError) emailError.style.display = 'none';
        if (emailFormatError) emailFormatError.style.display = 'none';
        if (emailWarning) emailWarning.style.display = 'none';
        
        if (!email) {
            if (emailError) emailError.style.display = 'block';
            return false;
        }
        
        // Check if it's a Gmail address
        const gmailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
        const isValidFormat = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        
        if (!isValidFormat) {
            if (emailFormatError) {
                emailFormatError.textContent = 'Please enter a valid Gmail address';
                emailFormatError.style.display = 'block';
            }
            return false;
        }
        
        if (!gmailRegex.test(email.toLowerCase())) {
            if (emailWarning) {
                emailWarning.textContent = 'Only Gmail addresses are allowed for registration';
                emailWarning.style.display = 'block';
            }
            return false;
        }
        
        return true;
    };
    
    console.log('validateGmailEmail function defined:', typeof window.validateGmailEmail);
    
    // OTP Timer function
    window.startOTPTimer = function() {
        let timeLeft = 300; // 5 minutes in seconds
        const timerElement = document.getElementById('loginTimerVal');
        
        if (!timerElement) return;
        
        const timer = setInterval(function() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                timerElement.textContent = 'Code expired. Please try again.';
                timerElement.style.color = '#dc3545';
            }
            
            timeLeft--;
        }, 1000);
    };
    
    // Signup OTP Timer function
    window.startSignupOTPTimer = function() {
        let timeLeft = 300; // 5 minutes in seconds
        const timerElement = document.getElementById('otpCountdown');
        const resendBtn = document.getElementById('resendOtpBtn');
        const resendTimer = document.getElementById('resendTimer');
        
        if (!timerElement) return;
        
        const timer = setInterval(function() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (resendTimer) {
                resendTimer.textContent = timeLeft;
            }
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                timerElement.textContent = '00:00';
                if (resendBtn) {
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = 'Didn\'t receive it? Resend';
                }
            } else {
                if (resendBtn) {
                    resendBtn.disabled = true;
                    resendBtn.innerHTML = `Didn\'t receive it? Resend · <span id="resendTimer">${timeLeft}</span>s`;
                }
            }
            
            timeLeft--;
        }, 1000);
    };

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM is ready, attaching event listeners...');
        
        // Password toggle functionality
        function setupPasswordToggle(toggleId, inputId, iconId) {
            const toggleBtn = document.getElementById(toggleId);
            const inputField = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            console.log(`Setting up password toggle: ${toggleId}, ${inputId}, ${iconId}`);
            console.log('Toggle button:', toggleBtn);
            console.log('Input field:', inputField);
            console.log('Icon:', icon);
            
            if (toggleBtn && inputField && icon) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Password toggle clicked, current type:', inputField.type);
                    
                    if (inputField.type === 'password') {
                        inputField.type = 'text';
                        icon.className = 'fas fa-eye-slash';
                        console.log('Changed to text, icon:', icon.className);
                    } else {
                        inputField.type = 'password';
                        icon.className = 'fas fa-eye';
                        console.log('Changed to password, icon:', icon.className);
                    }
                });
                console.log('Password toggle event listener attached successfully');
            } else {
                console.warn('Password toggle setup failed - missing elements');
            }
        }
        
        // Setup password toggles after modals are loaded
        setTimeout(function() {
            console.log('Setting up password toggles after modal load...');
            
            // Setup password toggles for signup form
            setupPasswordToggle('toggleSignupPwd', 'signupPassword', 'signupEyeIcon');
            setupPasswordToggle('toggleSignupConfirmPwd', 'signupConfirmPassword', 'signupConfirmEyeIcon');
            
            // Setup password toggle for login form
            setupPasswordToggle('toggleLoginPwd', 'loginPassword', 'loginEyeIcon');
        }, 500); // Wait for modals to be fully loaded
        
        // Setup signup OTP verification handler
        setTimeout(function() {
            console.log('Setting up signup OTP verification handler...');
            
            const otpForm = document.getElementById('otpForm');
            if (otpForm) {
                otpForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('Signup OTP form submitted');
                    
                    // Collect OTP from all input boxes
                    const otpBoxes = document.querySelectorAll('#signupOtpBoxes .bm-otp-box');
                    let otpCode = '';
                    otpBoxes.forEach(box => {
                        otpCode += box.value;
                    });
                    
                    // Set hidden OTP field
                    const hiddenOtp = document.getElementById('signupOtpHidden');
                    if (hiddenOtp) {
                        hiddenOtp.value = otpCode;
                    }
                    
                    // Validate OTP
                    if (otpCode.length !== 6) {
                        const errBox = document.getElementById('otpErrBox');
                        const errTxt = document.getElementById('otpErrTxt');
                        if (errBox && errTxt) {
                            errTxt.textContent = 'Please enter all 6 digits';
                            errBox.style.display = 'block';
                        }
                        return;
                    }
                    
                    // Show loading state
                    const verifyBtn = document.getElementById('otpVerifyBtn');
                    if (verifyBtn) {
                        verifyBtn.disabled = true;
                        verifyBtn.classList.add('loading');
                    }
                    
                    // Submit OTP verification
                    const formData = new FormData(otpForm);
                    formData.append('action', 'verify_signup_otp');
                    
                    fetch('login_otp.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Signup OTP verification response:', data);
                        if (data.success) {
                            // Show success message and redirect
                            alert('Account created successfully! Your account is pending approval from the admin.');
                            window.location.href = 'index.php';
                        } else {
                            // Show error
                            const errBox = document.getElementById('otpErrBox');
                            const errTxt = document.getElementById('otpErrTxt');
                            if (errBox && errTxt) {
                                errTxt.textContent = data.message || 'Invalid OTP. Please try again.';
                                errBox.style.display = 'block';
                            }
                            
                            // Clear OTP boxes
                            otpBoxes.forEach(box => {
                                box.value = '';
                            });
                            
                            // Focus first box
                            if (otpBoxes.length > 0) {
                                otpBoxes[0].focus();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('OTP verification error:', error);
                        const errBox = document.getElementById('otpErrBox');
                        const errTxt = document.getElementById('otpErrTxt');
                        if (errBox && errTxt) {
                            errTxt.textContent = 'Connection error. Please try again.';
                            errBox.style.display = 'block';
                        }
                    })
                    .finally(() => {
                        // Hide loading state
                        if (verifyBtn) {
                            verifyBtn.disabled = false;
                            verifyBtn.classList.remove('loading');
                        }
                    });
                });
            }
            
            // Setup resend OTP button
            const resendBtn = document.getElementById('resendOtpBtn');
            if (resendBtn) {
                resendBtn.addEventListener('click', function() {
                    console.log('Resend OTP clicked');
                    
                    // Show loading state
                    resendBtn.disabled = true;
                    resendBtn.innerHTML = 'Sending...';
                    
                    // Get stored email from session or form
                    const email = document.getElementById('email')?.value;
                    if (!email) {
                        alert('Email address not found. Please start over.');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'resend_signup_otp');
                    formData.append('email', email);
                    
                    fetch('login_otp.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Resend OTP response:', data);
                        if (data.success) {
                            alert('New verification code sent to your email.');
                            // Restart timer
                            startSignupOTPTimer();
                        } else {
                            alert(data.message || 'Failed to resend verification code.');
                        }
                    })
                    .catch(error => {
                        console.error('Resend OTP error:', error);
                        alert('Connection error. Please try again.');
                    })
                    .finally(() => {
                        // Reset button state
                        resendBtn.disabled = false;
                        resendBtn.innerHTML = 'Didn\'t receive it? Resend';
                    });
                });
            }
        }, 600); // Wait for OTP form to be loaded
        
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
        
        console.log('Script completed successfully!');
    });
    </script>

</body>
</html>
