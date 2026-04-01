<?php

/**
 * Student Club Page
 * Buyoan National High School
 */

// ── SESSION ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../session_config.php';
include '../db_connection.php';

// ── AUTHENTICATION GUARD ──────────────────────────────────────────────────────
// Student functionality has been removed
header('Location: ../index.php');
exit;

$student_id   = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$grade_level  = $_SESSION['grade_level'] ?? 'Grade 10';
$notification_preference = null;

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
            }
            $stmt->close();
        }
    } catch (Exception $e2) {
        error_log("DB error: " . $e2->getMessage());
    }
}

// ── PROFILE DISPLAY MODE ──────────────────────────────────────────────────────
$login_method   = isset($db_student['login_method']) ? $db_student['login_method'] : null;
$phone_verified = !empty($db_student['phone_verified']);
$email_verified = !empty($db_student['email_verified']);
$profile_photo  = !empty($db_student['photo']) ? $db_student['photo'] : null;
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

// ── PROFILE PICTURE ───────────────────────────────────────────────────────────
$nav_profile_img  = 'assets/img/person/unknown.jpg';
$nav_profile_type = 'icon';

if ($profile_display_mode === 'email') {
    if ($profile_photo) {
        $nav_profile_img  = htmlspecialchars($profile_photo, ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    } elseif (!empty($_SESSION['google_avatar'])) {
        $nav_profile_img  = htmlspecialchars($_SESSION['google_avatar'], ENT_QUOTES, 'UTF-8');
        $nav_profile_type = 'img';
    }
}

switch ($profile_display_mode) {
    case 'email':
        $nav_display_label = $student_email
            ? htmlspecialchars($student_email, ENT_QUOTES, 'UTF-8')
            : htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
        break;
    case 'phone':
        $nav_display_label = $student_phone
            ? htmlspecialchars($student_phone, ENT_QUOTES, 'UTF-8')
            : htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8');
        break;
    default:
        $nav_display_label = '';
        break;
}

$first_name = htmlspecialchars(explode(' ', $student_name)[0]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Clubs — BUNHS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        /* ═══════════════════════════════════════════════════════
           BUNHS DESIGN SYSTEM — matches Dashboard.php
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
            font-family: var(--font-body);
            background: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            font-size: 14px;
        }

        /* sidebar loaded from Student_nav.php */

        /* ─── MAIN ──────────────────────────────────────────── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ─── TOPBAR ─────────────────────────────────────────── */
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
            font-family: var(--font-display);
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

        /* ─── PAGE BODY ──────────────────────────────────────── */
        .page-body {
            padding: 28px;
            flex: 1;
        }

        /* ─── CLUB GRID ──────────────────────────────────────── */
        .club-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .club-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--bunhs-border);
            padding: 24px;
            box-shadow: var(--shadow);
            transition: box-shadow .2s, transform .2s;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .club-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .club-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: var(--bunhs-sage);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--bunhs-forest);
        }

        .club-card h3 {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 700;
            color: var(--bunhs-forest);
        }

        .club-card p {
            font-size: 13px;
            color: var(--bunhs-muted);
            line-height: 1.55;
        }

        .club-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 12px;
            color: var(--bunhs-muted);
        }

        .club-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .club-meta i {
            color: var(--bunhs-mint);
            font-size: 11px;
        }

        .btn-join {
            margin-top: auto;
            padding: 10px 18px;
            background: var(--bunhs-green);
            color: var(--white);
            border: none;
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-join:hover {
            background: var(--bunhs-forest);
        }

        .btn-join.joined {
            background: var(--bunhs-sage);
            color: var(--bunhs-forest);
            cursor: default;
        }

        /* ─── SECTION HEADER ─────────────────────────────────── */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .section-header h2 {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
            color: var(--bunhs-forest);
        }

        .section-header p {
            font-size: 13px;
            color: var(--bunhs-muted);
            margin-top: 3px;
        }

        /* ─── RESPONSIVE ─────────────────────────────────────── */
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
            }

            .page-body {
                padding: 16px;
            }

            .club-grid {
                grid-template-columns: 1fr;
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

    <!-- ── STUDENT NAV (loaded via JS fetch at bottom) ── -->
    <div id="nav-placeholder"></div>

    <!-- ── MAIN ── -->
    <main class="main">

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-left">
                <p class="breadcrumb">
                    <span>Home</span>
                    <i class="fas fa-chevron-right"></i>
                    <span>Student Clubs</span>
                </p>
                <h1>Student Clubs</h1>
            </div>
            <div class="topbar-right">
                <div class="date-chip">
                    <i class="fas fa-calendar-alt"></i>
                    <span id="currentDate"></span>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-body">

            <div class="section-header">
                <div>
                    <h2>Explore Clubs</h2>
                    <p>Join a club and connect with fellow students who share your interests.</p>
                </div>
            </div>

            <div class="club-grid">

                <!-- Club Card 1 -->
                <div class="club-card">
                    <div class="club-icon"><i class="fas fa-flask"></i></div>
                    <h3>Science Club</h3>
                    <p>Explore the wonders of science through experiments, competitions, and field visits.</p>
                    <div class="club-meta">
                        <span><i class="fas fa-users"></i> 24 members</span>
                        <span><i class="fas fa-clock"></i> Every Friday</span>
                    </div>
                    <a href="#" class="btn-join">Join Club</a>
                </div>

                <!-- Club Card 2 -->
                <div class="club-card">
                    <div class="club-icon"><i class="fas fa-palette"></i></div>
                    <h3>Arts & Crafts Club</h3>
                    <p>Express your creativity through painting, sculpture, and mixed-media art projects.</p>
                    <div class="club-meta">
                        <span><i class="fas fa-users"></i> 18 members</span>
                        <span><i class="fas fa-clock"></i> Every Wednesday</span>
                    </div>
                    <a href="#" class="btn-join">Join Club</a>
                </div>

                <!-- Club Card 3 -->
                <div class="club-card">
                    <div class="club-icon"><i class="fas fa-book-open"></i></div>
                    <h3>Literary Club</h3>
                    <p>Dive into the world of books, poetry, and storytelling. Write, read, and share.</p>
                    <div class="club-meta">
                        <span><i class="fas fa-users"></i> 15 members</span>
                        <span><i class="fas fa-clock"></i> Every Tuesday</span>
                    </div>
                    <a href="#" class="btn-join">Join Club</a>
                </div>

                <!-- Club Card 4 -->
                <div class="club-card">
                    <div class="club-icon"><i class="fas fa-laptop-code"></i></div>
                    <h3>Computer Club</h3>
                    <p>Learn programming, web development, and explore technology through hands-on projects.</p>
                    <div class="club-meta">
                        <span><i class="fas fa-users"></i> 30 members</span>
                        <span><i class="fas fa-clock"></i> Every Thursday</span>
                    </div>
                    <a href="#" class="btn-join">Join Club</a>
                </div>

                <!-- Club Card 5 -->
                <div class="club-card">
                    <div class="club-icon"><i class="fas fa-music"></i></div>
                    <h3>Music Club</h3>
                    <p>Sing, play instruments, and collaborate on musical performances for school events.</p>
                    <div class="club-meta">
                        <span><i class="fas fa-users"></i> 20 members</span>
                        <span><i class="fas fa-clock"></i> Every Monday</span>
                    </div>
                    <a href="#" class="btn-join">Join Club</a>
                </div>

                <!-- Club Card 6 -->
                <div class="club-card">
                    <div class="club-icon"><i class="fas fa-futbol"></i></div>
                    <h3>Sports Club</h3>
                    <p>Stay active and compete in basketball, volleyball, track & field, and more.</p>
                    <div class="club-meta">
                        <span><i class="fas fa-users"></i> 40 members</span>
                        <span><i class="fas fa-clock"></i> Daily after class</span>
                    </div>
                    <a href="#" class="btn-join">Join Club</a>
                </div>

            </div>
        </div><!-- /page-body -->

    </main>

    <script>
        // ── Date chip ──
        (function() {
            var d = new Date();
            var opts = {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            };
            var el = document.getElementById('currentDate');
            if (el) el.textContent = d.toLocaleDateString('en-US', opts);
        })();
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
                    var current = window.location.pathname.split('/').pop() || 'student_club.php';
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