<?php
// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

// Include database connection
include 'db_connection.php';

// Function to mask email
function maskEmail($email)
{
    $parts = explode('@', $email);
    $username = $parts[0];
    $domain = $parts[1];
    $maskedUsername = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
    return $maskedUsername . '@' . $domain;
}

// Constants for security
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('LOG_FILE', 'logs/login_attempts.log');



// Function to get client IP
function getClientIP()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to log login attempts
function logLoginAttempt($username, $ip, $success)
{
    $logEntry = sprintf(
        "[%s] IP: %s | Username: %s | Success: %s\n",
        date('Y-m-d H:i:s'),
        $ip,
        htmlspecialchars($username),
        $success ? 'Yes' : 'No'
    );
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

// Function to check rate limiting
function isRateLimited($ip)
{
    $attempts = $_SESSION['login_attempts'][$ip] ?? [];
    $recentAttempts = array_filter($attempts, function ($time) {
        return $time > time() - LOCKOUT_TIME;
    });
    return count($recentAttempts) >= MAX_LOGIN_ATTEMPTS;
}

// Function to record login attempt
function recordLoginAttempt($ip)
{
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    if (!isset($_SESSION['login_attempts'][$ip])) {
        $_SESSION['login_attempts'][$ip] = [];
    }
    $_SESSION['login_attempts'][$ip][] = time();
}

// Function to sanitize input
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}



$login_error = '';

// Handle login POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password']; // Do not trim password, as spaces might be part of it
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
        $user_data = null;
        $user_type = '';

        // First check admin table
        $stmt = $conn->prepare("SELECT id, password FROM admin WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $user_data = $result->fetch_assoc();
                    $user_type = 'admin';
                    $user_found = true;
                }
            }
            $stmt->close();
        }

        // If not found in admin table, check sub-admin table
        if (!$user_found) {
            $stmt = $conn->prepare("SELECT id, password FROM `sub_admin` WHERE username = ? AND status = 'approved'");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    if ($result->num_rows === 1) {
                        $user_data = $result->fetch_assoc();
                        $user_type = 'sub-admin';
                        $user_found = true;
                    }
                }
                $stmt->close();
            }
        }

        if ($user_found && password_verify($password, $user_data['password'])) {
            // Successful login
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['login_time'] = time();

            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            // Clear login attempts on success
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

    // Add delay on failed attempts to slow down brute force
    if (!empty($login_error) && $login_error !== 'Invalid request. Please try again.' && $login_error !== 'Too many failed attempts. Please try again later.') {
        sleep(1); // 1 second delay
    }
}

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="buyoan high school, buyoan national high school, BUNHS, buyoan school, buyoan, buyoan elementary, buyoan national high school website, buyoan elementary, buyoan highschool">

    <!-- Favicons -->
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

    <!-- Main CSS File -->
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            transition: background-color 0.2s;
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
            animation: fadeIn 0.5s ease-in;
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

        @media (max-width: 480px) {
            .verification-card {
                padding: 32px 24px;
                margin: 16px;
            }
        }
    </style>

</head>

<?php
$isVerificationPage = isset($_GET['verify']) && !empty($_SESSION['signup_data']);
?>

<body class="<?php echo $isVerificationPage ? 'verification-page' : 'index-page'; ?>">

    <?php if ($isVerificationPage): ?>
        <div class="verification-card fade-in">
            <img src="assets/img/logo.jpg" alt="School Logo" class="verification-logo">
            <h1 class="verification-title">Verify your email</h1>
            <p class="verification-text">
                To continue, first verify it's you. We will send a verification code to <?php echo maskEmail($_SESSION['signup_data']['email']); ?>.
            </p>
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

                <a href="index.html" class="logo d-flex align-items-center">
                    <!-- School Logo -->
                    <img src="assets/img/Bagong_Pilipinas_logo.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 20px;">
                    <img src="assets/img/DepED logo circle.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 0px;">
                    <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 50px;">

                    <!-- School Name -->
                    <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
                </a>

                <div id="nav-placeholder"></div>

            </div>
        </header>

        <main class="main">

            <!-- Hero Section -->
            <section id="hero" class="hero section">

                <div class="hero-container">
                    <div class="hero-content">
                        <h2 style="color: white;">Web-Based Information Sytem for Buyoan National High School</h2>
                        <p></p>
                        <div class="cta-buttons">
                            <a href="#" class="btn-apply">Join us</a>
                            <a href="user_account/Dashboard.php" class="btn-tour">Join as Guest</a>
                        </div>
                        <div class="announcement">
                            <div class="announcement-badge">New</div>
                            <p>Fall 2025 Enrollment Open - Early Decision Deadline December 15</p>
                        </div>
                    </div>
                </div>

                <div class="highlights-container container">
                    <div class="row gy-4">
                        <div class="col-md-4">
                            <div class="highlight-item">
                                <div class="icon">
                                    <i class="fas fa-graduation-cap" style="color: #22775e;"></i>
                                </div>
                                <h3>1 Batch Graduate Success</h3>
                                <p>One batch united by growth, learning, and shared success along the journey.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="highlight-item">
                                <div class="icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3>18:1 Student-Faculty Ratio</h3>
                                <p>Average number of students per faculty member, reflecting class size and learning support.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="highlight-item">
                                <div class="icon">
                                    <i class="fa-solid fa-star" style="color: #FFD43B;"></i>
                                </div>
                                <h3>School Rating</h3>
                                <div style="text-align: center; padding: 5px 0;">
                                    <div style="font-size: 36px; font-weight: bold; color: #312f2f; margin-bottom: 5px;">4.0</div>
                                    <div style="margin-bottom: 5px;">
                                        <i class="fas fa-star" style="color: #FFD43B; font-size: 18px;"></i>
                                        <i class="fas fa-star" style="color: #FFD43B; font-size: 18px;"></i>
                                        <i class="fas fa-star" style="color: #FFD43B; font-size: 18px;"></i>
                                        <i class="fas fa-star" style="color: #FFD43B; font-size: 18px;"></i>
                                        <i class="far fa-star" style="color: #FFD43B; font-size: 18px;"></i>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">9,689 reviews</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="event-banner">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <div class="event-date">
                                    <span class="month">OCT</span>
                                    <span class="day">28</span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h3>Open Campus Day</h3>
                                <p>Experience our vibrant campus life, meet faculty members, and learn about our academic programs.</p>
                            </div>
                            <div class="col-md-2">
                                <a href="#" class="btn-register">Register</a>
                            </div>
                        </div>
                    </div>
                </div>

            </section><!-- /Hero Section -->



            <!-- About Section -->
            <section id="about" class="about section">

                <div class="container">

                    <div class="row gy-5">

                        <div class="col-lg-6">
                            <div class="content">
                                <h3>Nurturing Learners, Building the Nation</h3>
                                <p>For seven years, Buyoan National High School has been shaping young minds through quality, culture-based education in a
                                    safe and caring community empowering every learner to grow, achieve their dreams, and contribute to a brighter nation.</p>

                                <div class="stats-row">
                                    <div class="stat-item">
                                        <div class="number">263</div>
                                        <div class="label">Students Enrolled</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number">98%</div>
                                        <div class="label">Graduation Rate</div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="number">25+</div>
                                        <div class="label">Expert Faculty</div>
                                    </div>
                                </div>

                                <div class="mission-statement">
                                    <p><em>"Young Man Think Big! Aspire, Suceed... "</em></p>
                                </div>

                                <a href="about.html" class="btn-learn-more">
                                    Learn More About Us
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="image-wrapper">
                                <img src="assets/img/front pic/Buyoan School.jpg" alt="Campus Overview" class="img-fluid">
                                <div class="experience-badge">
                                    <div class="years">7+</div>
                                    <div class="text">Years of Excellence</div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </section><!-- /About Section -->

            <!-- Featured Programs Section -->
            <section id="featured-programs" class="featured-programs section">

                <!-- Section Title -->
                <div class="container section-title">
                    <h2>Featured Programs</h2>
                    <p>We offer programs that inspire and equip aspiring students to reach their full potential.</p>
                </div><!-- End Section Title -->

                <div class="container">

                    <div class="featured-programs-wrapper">

                        <div class="programs-overview">
                            <div class="overview-content">
                                <h2>Discover Excellence in Education</h2>
                                <p>Buyoan National High School exemplifies excellence in education through its unwavering commitment to academic
                                    achievement, values formation, and community partnership empowering both teachers and students to grow, innovate, and
                                    lead in an ever-changing world.</p>
                                <div class="overview-stats">
                                    <div class="stat-item">
                                        <span class="stat-number">263</span>
                                        <span class="stat-label">Active Students</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number">98%</span>
                                        <span class="stat-label">Graduate Rate</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number">50+</span>
                                        <span class="stat-label">Programs Offered</span>
                                    </div>
                                </div>
                            </div>
                            <div class="overview-image">
                                <img src="assets/img/education/Education.jpg" alt="Education" class="img-fluid">
                            </div>
                        </div>

                        <div class="programs-showcase">

                            <div class="program-card featured-program">
                                <div class="card-image">
                                    <img src="assets/img/einstein.png" alt="Program" class="img-fluid">
                                    <div class="program-badge">
                                        <i class="fas fa-star"></i>
                                        <span>Top Rated</span>
                                    </div>
                                </div>
                                <div class="card-content">
                                    <div class="program-category">Grade & Section</div>
                                    <h3>G7-EINSTEIN</h3>
                                    <p>Think Like Einstein, Dream Like a Scientist.</p>
                                    <div class="program-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <span>1 Year</span>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="#" class="learn-more">Learn More</a>
                                        <div class="enrollment">
                                            <i class="fas fa-users"></i>
                                            <span>36 enrolled</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="programs-list">

                                <div class="program-item">
                                    <div class="item-visual">
                                        <img src="assets/img/office-toy.png" alt="Program" class="img-fluid">
                                    </div>
                                    <div class="item-details">
                                        <div class="item-category">Grade & Section</div>
                                        <h4>G7-NEWTON</h4>
                                        <p>Inspired by Isaac Newton’s curiosity and groundbreaking ideas.</p>
                                        <div class="item-info">
                                            <span>28 enrolled</span>
                                        </div>
                                    </div>
                                    <div class="item-action">
                                        <i class="fas fa-arrow-circle-right"></i>
                                    </div>
                                </div>

                                <div class="program-item">
                                    <div class="item-visual">
                                        <img src="assets/img/genetics.png" alt="Program" class="img-fluid">
                                    </div>
                                    <div class="item-details">
                                        <div class="item-category">Grade & Section</div>
                                        <h4>G8-MENDEL</h4>
                                        <p>Innovate. Discover. Lead the Future.</p>
                                        <div class="item-info">
                                            <span>35 enrolled</span>
                                        </div>
                                    </div>
                                    <div class="item-action">
                                        <i class="fas fa-arrow-circle-right"></i>
                                    </div>
                                </div>

                                <div class="program-item">
                                    <div class="item-visual">
                                        <img src="assets/img/pasteur.jpg" alt="Program" class="img-fluid">
                                    </div>
                                    <div class="item-details">
                                        <div class="item-category">Grade & Section</div>
                                        <h4>G8-PASTEUR</h4>
                                        <p>Learning Through Imagination and Discovery.</p>
                                        <div class="item-info">
                                            <span>33 enrolled</span>
                                        </div>
                                    </div>
                                    <div class="item-action">
                                        <i class="fas fa-arrow-circle-right"></i>
                                    </div>
                                </div>

                                <div class="program-item">
                                    <div class="item-visual">
                                        <img src="assets/img/curie.png" alt="Program" class="img-fluid">
                                    </div>
                                    <div class="item-details">
                                        <div class="item-category">Grade & Section</div>
                                        <h4>G9-CURIE</h4>
                                        <p>Driven by Discovery, Guided by Science.</p>
                                        <div class="item-info">
                                            <span>49 enrolled</span>
                                        </div>
                                    </div>
                                    <div class="item-action">
                                        <i class="fas fa-arrow-circle-right"></i>
                                    </div>
                                </div>

                                <div class="program-item">
                                    <div class="item-visual">
                                        <img src="assets/img/archimedes.png" alt="Program" class="img-fluid">
                                    </div>
                                    <div class="item-details">
                                        <div class="item-category">Grade & Section</div>
                                        <h4>G9-ARCHIMEES</h4>
                                        <p>Logic, creativity, and innovation.</p>
                                        <div class="item-info">
                                            <span>42 enrolled</span>
                                        </div>
                                    </div>
                                    <div class="item-action">
                                        <i class="fas fa-arrow-circle-right"></i>
                                    </div>
                                </div>

                                <div class="program-item">
                                    <div class="item-visual">
                                        <img src="assets/img/pythagoras.png" alt="Program" class="img-fluid">
                                    </div>
                                    <div class="item-details">
                                        <div class="item-category">Grade & Section</div>
                                        <h4>G10-PYTHAGORAS</h4>
                                        <p>Shaping minds through precision.</p>
                                        <div class="item-info">
                                            <span>40 enrolled</span>
                                        </div>
                                    </div>
                                    <div class="item-action">
                                        <i class="fas fa-arrow-circle-right"></i>
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </section><!-- /Featured Programs Section -->

            <!-- Students Life Block Section -->
            <section id="students-life-block" class="students-life-block section">

                <!-- Section Title -->
                <div class="container section-title">
                    <h2>Students Life</h2>
                    <p>Where learning meets fun, and every day inspires growth and friendship.</p>
                </div><!-- End Section Title -->

                <div class="container">

                    <div class="row align-items-center g-5">
                        <div class="col-lg-6">
                            <div class="content-wrapper">
                                <div class="section-tag">
                                    Student Life
                                </div>
                                <h2>Experience Student Life at Buyoan National High School</h2>
                                <p class="description">Step into a world where learning goes beyond the classroom—where every day is filled with discovery, teamwork, and
                                    opportunities to grow. At Buyoan National High School, students build lasting friendships, explore their passions, and
                                    prepare to become future leaders in a supportive and inspiring environment.</p>

                                <div class="stats-row">
                                    <div class="stat-item">
                                        <span class="stat-number">85+</span>
                                        <span class="stat-label">Student Organizations</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number">150+</span>
                                        <span class="stat-label">Annual Events</span>
                                    </div>
                                </div>

                                <div class="action-links">
                                    <a href="student-life.html" class="primary-link">Explore Student Life</a>
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

                    <div class="highlights-section">
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="highlight-card">
                                    <div class="highlight-image">
                                        <img src="assets/img/education/Leadership development.jpg" alt="Leadership Programs" class="img-fluid">
                                    </div>
                                    <div class="highlight-content">
                                        <h5>Leadership Development</h5>
                                        <p>Buyoan National High School shapes future leaders through dynamic SSG programs, hands-on leadership trainings, and
                                            engaging school and DepEd events helping students build confidence, teamwork, and communication skills that last a
                                            lifetime</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="highlight-card">
                                    <div class="highlight-image">
                                        <img src="assets/img/education/Cultural Event.jpg" alt="Cultural Events" class="img-fluid">
                                    </div>
                                    <div class="highlight-content">
                                        <h5>Cultural Diversity</h5>
                                        <p>Buyoan National High School celebrates the rich blend of Bicolano and Filipino cultures that shape our campus community.
                                            We honor traditions, embrace diversity, and integrate local heritage into learning empowering students to grow with
                                            pride, inclusivity, and respect for all.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="highlight-card">
                                    <div class="highlight-image">
                                        <img src="assets/img/innovation.jpg" alt="Innovation Hub" class="img-fluid">
                                    </div>
                                    <div class="highlight-content">
                                        <h5>Innovation Hub</h5>
                                        <p>Buyoan National High School’s Innovation Hub nurtures future-ready learners by inspiring creativity, critical thinking,
                                            and hands-on innovation to solve real-world challenges.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </section><!-- /Students Life Block Section -->

            <!-- Testimonials Section -->
            <!-- <section id="testimonials" class="testimonials section light-background">

    
      <div class="container section-title">
        <h2>Testimonials</h2>
        <p>Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit</p>
      </div>

      <div class="container">

        <div class="testimonials-slider swiper init-swiper">
          <script type="application/json" class="swiper-config">
            {
              "slidesPerView": 1,
              "loop": true,
              "speed": 600,
              "autoplay": {
                "delay": 5000
              },
              "navigation": {
                "nextEl": ".swiper-button-next",
                "prevEl": ".swiper-button-prev"
              }
            }
          </script>

          <div class="swiper-wrapper">

            <div class="swiper-slide">
              <div class="testimonial-item">
                <div class="row">
                  <div class="col-lg-8">
                    <h2>Sed ut perspiciatis unde omnis</h2>
                    <p>
                      Proin iaculis purus consequat sem cure digni ssim donec porttitora entum suscipit rhoncus. Accusantium quam, ultricies eget id, aliquam eget nibh et. Maecen aliquam, risus at semper.
                    </p>
                    <p>
                      Beatae magnam dolore quia ipsum. Voluptatem totam et qui dolore dignissimos. Amet quia sapiente laudantium nihil illo et assumenda sit cupiditate. Nam perspiciatis perferendis minus consequatur. Enim ut eos quo.
                    </p>
                    <div class="profile d-flex align-items-center">
                      <img src="assets/img/person/person-m-7.webp" class="profile-img" alt="">
                      <div class="profile-info">
                        <h3>Saul Goodman</h3>
                        <span>Client</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-4 d-none d-lg-block">
                    <div class="featured-img-wrapper">
                      <img src="assets/img/person/person-m-7.webp" class="featured-img" alt="">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="swiper-slide">
              <div class="testimonial-item">
                <div class="row">
                  <div class="col-lg-8">
                    <h2>Nemo enim ipsam voluptatem</h2>
                    <p>
                      Export tempor illum tamen malis malis eram quae irure esse labore quem cillum quid cillum eram malis quorum velit fore eram velit sunt aliqua noster fugiat irure amet legam anim culpa.
                    </p>
                    <p>
                      Dolorem excepturi esse qui amet maxime quibusdam aut repellendus voluptatum. Corrupti enim a repellat cumque est laborum fuga consequuntur. Dolorem nostrum deleniti quas voluptatem iure dolorum rerum. Repudiandae doloribus ut repellat harum vero aut. Modi aut velit aperiam aspernatur odit ut vitae.
                    </p>
                    <div class="profile d-flex align-items-center">
                      <img src="assets/img/person/person-f-8.webp" class="profile-img" alt="">
                      <div class="profile-info">
                        <h3>Sara Wilsson</h3>
                        <span>Designer</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-4 d-none d-lg-block">
                    <div class="featured-img-wrapper">
                      <img src="assets/img/person/person-f-8.webp" class="featured-img" alt="">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="swiper-slide">
              <div class="testimonial-item">
                <div class="row">
                  <div class="col-lg-8">
                    <h2>
                      Labore nostrum eos impedit
                    </h2>
                    <p>
                      Fugiat enim eram quae cillum dolore dolor amet nulla culpa multos export minim fugiat minim velit minim dolor enim duis veniam ipsum anim magna sunt elit fore quem dolore labore illum veniam.
                    </p>
                    <p>
                      Itaque ut explicabo vero occaecati est quam rerum sed. Numquam tempora aut aut quaerat quia illum. Nobis quia autem odit ipsam numquam. Doloribus sit sint corporis eius totam fuga. Hic nostrum suscipit corrupti nam expedita adipisci aut optio.
                    </p>
                    <div class="profile d-flex align-items-center">
                      <img src="assets/img/person/person-m-9.webp" class="profile-img" alt="">
                      <div class="profile-info">
                        <h3>Matt Brandon</h3>
                        <span>Freelancer</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-4 d-none d-lg-block">
                    <div class="featured-img-wrapper">
                      <img src="assets/img/person/person-m-9.webp" class="featured-img" alt="">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="swiper-slide">
              <div class="testimonial-item">
                <div class="row">
                  <div class="col-lg-8">
                    <h2>Impedit dolor facilis nulla</h2>
                    <p>
                      Enim nisi quem export duis labore cillum quae magna enim sint quorum nulla quem veniam duis minim tempor labore quem eram duis noster aute amet eram fore quis sint minim.
                    </p>
                    <p>
                      Omnis aspernatur accusantium qui delectus praesentium repellendus. Facilis sint odio aspernatur voluptas commodi qui qui qui pariatur. Corrupti deleniti itaque quaerat ipsum deleniti culpa tempora tempore. Et consequatur exercitationem hic aspernatur nobis est voluptatibus architecto laborum.
                    </p>
                    <div class="profile d-flex align-items-center">
                      <img src="assets/img/person/person-f-10.webp" class="profile-img" alt="">
                      <div class="profile-info">
                        <h3>Jena Karlis</h3>
                        <span>Store Owner</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-4 d-none d-lg-block">
                    <div class="featured-img-wrapper">
                      <img src="assets/img/person/person-f-10.webp" class="featured-img" alt="">
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <div class="swiper-navigation w-100 d-flex align-items-center justify-content-center">
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
          </div>

        </div>

      </div>-->

            </section><!-- /Testimonials Section -->

            <!-- Call To Action Section -->
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
                                            <span class="number purecounter" data-purecounter-start="0" data-purecounter-end="250" data-purecounter-duration="2">263</span>
                                            <span class="label">Active Learners</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="number purecounter" data-purecounter-start="0" data-purecounter-end="7" data-purecounter-duration="2">0</span>
                                            <span class="label">Expert Strands</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="action-buttons">
                                    <a href="programs.html" class="btn-primary">Explore Programs</a>
                                    <a href="trial.html" class="btn-secondary">
                                        <span>Enroll Now</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="visual-section">
                                <div class="main-image-container">
                                    <img src="assets/img/education/Students learning.jpg" alt="Students Learning" class="main-image">
                                    <div class="overlay-gradient"></div>
                                </div>

                                <div class="feature-cards">
                                    <div class="feature-card achievement">
                                        <div class="icon">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <div class="content">
                                            <h4>Certified Excellence</h4>
                                            <p>Industry-recognized certificates</p>
                                        </div>
                                    </div>

                                    <div class="feature-card flexibility">
                                        <div class="icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="content">
                                            <h4>Learn at Your Pace</h4>
                                            <p>24/7 access to all materials</p>
                                        </div>
                                    </div>

                                    <div class="feature-card community">
                                        <div class="icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="content">
                                            <h4>Global Community</h4>
                                            <p>Connect with learners worldwide</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

            </section><!-- /Call To Action Section -->

            <!-- Recent News Section -->
            <section id="recent-news" class="recent-news section">

                <!-- Section Title -->
                <div class="container section-title">
                    <h2>Recent News</h2>
                    <p>Your Gateway to the Latest Campus Updates.</p>
                </div><!-- End Section Title -->

                <div class="container">

                    <div class="row gy-5">

                        <div class="col-xl-3 col-md-6">
                            <div class="post-box">
                                <div class="post-img"><img src="assets/img/blog/blog-post-2.jpg" class="img-fluid" alt=""></div>
                                <div class="meta">
                                    <span class="post-date">Fri, October 24, 2025</span>
                                    <span class="post-author"> / Buyoan National High School</span>
                                </div>
                                <h3 class="post-title">Mga paalala para sa Ligtas Undas 2025</h3>
                                <p>Illum voluptas ab enim placeat. Adipisci enim velit nulla. Vel omnis laudantium. Asperiores eum ipsa est officiis. Modi qui magni est...</p>
                                <a href="blog-details.html" class="readmore stretched-link"><span>Read More</span><i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="post-box">
                                <div class="post-img"><img src="assets/img/blog/blog-post-1.jpg" class="img-fluid" alt=""></div>
                                <div class="meta">
                                    <span class="post-date">Thu, October 23, 2025</span>
                                    <span class="post-author"> / Buyoan National High School</span>
                                </div>
                                <h3 class="post-title">School Advisory 2025-10-007</h3>
                                <p>Narito ang mga mahahalagang petsa na dapat pakatatandaan:
                                    Oktubre 24: Walang pasok dahil sa Legazpi Fiesta
                                    Oktubre 27-31: Walang pasok dahil sa implementasyon ng Midyear Break ng mga pampublikong paaralan sa buong bansa
                                    Nobyembre 3: May pasok at ikalawang araw ng pamanahunang pagsusulit. Para sa iskedyul ng inyong klase, marapat na
                                    makipagugnayan sa inyong tagapayo.
                                    Nawa’y maging gabay ito sa lahat</p>
                                <a href="blog-details.html" class="readmore stretched-link"><span>Read More</span><i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="post-box">
                                <div class="post-img"><img src="assets/img/blog/blog-post-3.webp" class="img-fluid" alt=""></div>
                                <div class="meta">
                                    <span class="post-date">Wed, October 22, 2025</span>
                                    <span class="post-author"> / Buyoan National High School</span>
                                </div>
                                <h3 class="post-title">#WALANGPASOK | PAGDIRIWANG NG KAPISTAHAN NI SEÑOR SAN RAPHAEL DE ARKANGHEL</h3>
                                <p>Ipinapaabot ng Pamahalaang Lungsod ng Legazpi ang pansamantalang suspensyon ng klase sa lahat ng antas sa mga
                                    pampublikong paaralan at suspensyon ng trabaho sa lahat ng tanggapan ng lokal na pamahalaan sa darating na Biyernes,
                                    Oktubre 24, 2025, bilang pakikiisa sa taunang Kapistahan ni Señor San Raphael de Arkanghel — ang patron ng Lungsod ng
                                    Legazpi.
                                    Ang ikalawang araw ng Ikalawang Pamanahunang Pagsusulit ay isasagawa sa ika-3 araw ng Nobyembre 2025.
                                    Naway maging gabay ito sa lahat.</p>
                                <a href="blog-details.html" class="readmore stretched-link"><span>Read More</span><i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6">
                            <div class="post-box">
                                <div class="post-img"><img src="assets/img/blog/blog-post-4.webp" class="img-fluid" alt=""></div>
                                <div class="meta">
                                    <span class="post-date">Tue, Sep 16</span>
                                    <span class="post-author"> / Mario Douglas</span>
                                </div>
                                <h3 class="post-title">Pariatur quia facilis similique deleniti</h3>
                                <p>Et consequatur eveniet nam voluptas commodi cumque ea est ex. Aut quis omnis sint ipsum earum quia eligendi...</p>
                                <a href="blog-details.html" class="readmore stretched-link"><span>Read More</span><i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>

                    </div>

                </div>

            </section><!-- /Recent News Section -->

            <!-- Events Section -->
            <section id="events" class="events section">

                <!-- Section Title -->
                <div class="container section-title">
                    <h2>Events</h2>
                    <p>Buyoan National High School hosts a variety of events throughout the school year, including academic competitions, sports meets, cultural celebrations, and community outreach programs. These events aim to promote student engagement, teamwork, and holistic development, while showcasing the talents and creativity of our students.</p>
                </div><!-- End Section Title -->

                <div class="container">

                    <div class="event-filters mb-4">
                        <div class="row justify-content-center g-3">
                            <div class="col-md-4">
                                <select class="form-select">
                                    <option selected="">All Months</option>
                                    <option>January</option>
                                    <option>February</option>
                                    <option>March</option>
                                    <option>April</option>
                                    <option>May</option>
                                    <option>June</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select">
                                    <option selected="">All Categories</option>
                                    <option>Academic</option>
                                    <option>Arts</option>
                                    <option>Sports</option>
                                    <option>Community</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">

                        <div class="col-lg-6">
                            <div class="event-card">
                                <div class="event-date">
                                    <span class="month">DEC</span>
                                    <span class="day">3-5</span>
                                    <span class="year">2025</span>
                                </div>
                                <div class="event-content">
                                    <div class="event-tag academic">Academic</div>
                                    <h3>Teacher INSET</h3>
                                    <p>The whole school staff extends its warmest appreciation to Sir Jonel T. Ascutia, a highly-esteemed faculty of Gogon HS, for sharing his expertise and deep views on using cooperative and integrative approaches to enhance active learning and to deepen understanding of learners by connecting knowledge across different areas.</p>
                                    <div class="event-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <span>08:00 AM - 05:00 PM</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>Gogon High School</span>
                                        </div>
                                    </div>
                                    <div class="event-actions">
                                        <a href="#" class="btn-learn-more">Learn More</a>
                                        <a href="#" class="btn-calendar"><i class="fas fa-calendar-plus"></i> Add to Calendar</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="event-card">
                                <div class="event-date">
                                    <span class="month">OCT</span>
                                    <span class="day">21</span>
                                    <span class="year">2025</span>
                                </div>
                                <div class="event-content">
                                    <div class="event-tag sports">Sports</div>
                                    <h3>Sports Tournament</h3>
                                    <p>The month-long tournament, held in various venues in Legazpi, brought together student athletes from across the city, encouraging friendly competition and camaraderie among students.
                                        With their achievements, Buyoan National High School’s athletes have set a high bar for future sports meets and inspired fellow Buyoanons to aim for excellence in both sports and academics.</p>
                                    <div class="event-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <span>08:30 AM - 05:00 PM</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>Legazpi City</span>
                                        </div>
                                    </div>
                                    <div class="event-actions">
                                        <a href="#" class="btn-learn-more">Learn More</a>
                                        <a href="#" class="btn-calendar"><i class="fas fa-calendar-plus"></i> Add to Calendar</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="event-card">
                                <div class="event-date">
                                    <span class="month">NOV</span>
                                    <span class="day">19</span>
                                    <span class="year">2025</span>
                                </div>
                                <div class="event-content">
                                    <div class="event-tag arts">Environmental</div>
                                    <h3>YES-O Officers</h3>
                                    <p>The activity featured engaging lectures on weather and climate, global warming, and sustainability, along with fun and interactive learning sessions that inspired eco-leaders to take action for a greener future.</p>
                                    <div class="event-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <span>08:00 PM - 05:00 PM</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>Legazpi Expo Center</span>
                                        </div>
                                    </div>
                                    <div class="event-actions">
                                        <a href="#" class="btn-learn-more">Learn More</a>
                                        <a href="#" class="btn-calendar"><i class="fas fa-calendar-plus"></i> Add to Calendar</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="event-card">
                                <div class="event-date">
                                    <span class="month">SEP</span>
                                    <span class="day">5</span>
                                    <span class="year">2025</span>
                                </div>
                                <div class="event-content">
                                    <div class="event-tag community">Community</div>
                                    <h3>Parent-Teacher Conference</h3>
                                    <p>The School Principal Jojo D. Apuli presented the School Report Card to narrate the current status of the school.
                                        The elected SPTA Officers also took their Oath of Office during the said event:</p>
                                    <div class="event-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <span>01:00 PM - 03:00 PM</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span>BuNHS School</span>
                                        </div>
                                    </div>
                                    <div class="event-actions">
                                        <a href="#" class="btn-learn-more">Learn More</a>
                                        <a href="#" class="btn-calendar"><i class="fas fa-calendar-plus"></i> Add to Calendar</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="text-center mt-5">
                        <a href="#" class="btn-view-all">View All Events</a>
                    </div>

                </div>

            </section><!-- /Events Section -->

        </main>

        <footer id="footer" class="footer-16 footer position-relative dark-background">

            <div class="container">

                <div class="footer-main">
                    <div class="row align-items-start">

                        <div class="col-lg-5">
                            <div class="brand-section">
                                <a href="index.html" class="logo d-flex align-items-center mb-4">
                                    <span class="sitename">Buyoan National High School</span>
                                </a>
                                <p class="brand-description">Crafting exceptional digital experiences through thoughtful design and innovative solutions that elevate your brand presence.</p>

                                <div class="contact-info mt-5">
                                    <div class="contact-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Legaspi, Albay, Bicol Region,Philippines</span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-phone"></i>
                                        <span>0985-072-2808</span>
                                    </div>
                                    <div class="contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span>hello@designstudio.com</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="footer-nav-wrapper">
                                <div class="row">

                                    <div class="col-6 col-lg-3">
                                        <div class="nav-column">
                                            <h6>Studio</h6>
                                            <nav class="footer-nav">
                                                <a href="#">Our Story</a>
                                                <a href="#">Design Process</a>
                                                <a href="#">Portfolio</a>
                                                <a href="#">Case Studies</a>
                                                <a href="#">Awards</a>
                                            </nav>
                                        </div>
                                    </div>

                                    <div class="col-6 col-lg-3">
                                        <div class="nav-column">
                                            <h6>Services</h6>
                                            <nav class="footer-nav">
                                                <a href="#">Brand Identity</a>
                                                <a href="#">Web Design</a>
                                                <a href="#">Mobile Apps</a>
                                                <a href="#">Digital Strategy</a>
                                                <a href="#">Consultation</a>
                                            </nav>
                                        </div>
                                    </div>

                                    <div class="col-6 col-lg-3">
                                        <div class="nav-column">
                                            <h6>Resources</h6>
                                            <nav class="footer-nav">
                                                <a href="#">Design Blog</a>
                                                <a href="#">Style Guide</a>
                                                <a href="#">Free Assets</a>
                                                <a href="#">Tutorials</a>
                                                <a href="#">Inspiration</a>
                                            </nav>
                                        </div>
                                    </div>

                                    <div class="col-6 col-lg-3">
                                        <div class="nav-column">
                                            <h6>Connect</h6>
                                            <nav class="footer-nav">
                                                <a href="#">Start Project</a>
                                                <a href="#">Schedule Call</a>
                                                <a href="#">Join Newsletter</a>
                                                <a href="#">Follow Updates</a>
                                                <a href="#">Partnership</a>
                                            </nav>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="footer-social">
                    <div class="row align-items-center">

                        <div class="col-lg-6">
                            <div class="newsletter-section">
                                <h5>Stay Inspired</h5>
                                <p>Subscribe to receive design insights and creative inspiration delivered monthly.</p>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="social-section">
                                <div class="social-links">
                                    <p><a href="#" aria-label="Dribbble" class="social-link">
                                            <i class="fab fa-dribbble"></i>
                                            <span>Twitter</span>
                                        </a></p>
                                    <p><a href="https://web.facebook.com/BuyoanCampus/?_rdc=1&_rdr&checkpoint_src=any#" aria-label="Behance" class="social-link">
                                            <i class="fab fa-behance"></i>
                                            <span>FaceBook</span>
                                        </a></p>
                                    <p><a href="#" aria-label="Instagram" class="social-link">
                                            <i class="fab fa-instagram"></i>
                                            <span>Instagram</span>
                                        </a></p>
                                    <p><a href="https://www.linkedin.com/in/jojo-apuli-3b8b07147/?originalSubdomain=ph" aria-label="LinkedIn" class="social-link">
                                            <i class="fab fa-linkedin"></i>
                                            <span>LinkedIn</span>
                                        </a></p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <div class="footer-bottom">
                <div class="container">
                    <div class="bottom-content">
                        <div class="row align-items-center">

                            <div class="col-lg-6">
                                <div class="copyright">
                                    <p>© <span class="sitename">Buyoan National High School</span>. All rights reserved.</p>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="legal-links">
                                    <a href="#">Privacy Policy</a>
                                    <a href="#">Terms of Service</a>
                                    <a href="#">Cookie Policy</a>
                                    <div class="credits">
                                        <!-- All the links in the footer should remain intact. -->
                                        <!-- You can delete the links only if you've purchased the pro version. -->
                                        <!-- Licensing information: https://bootstrapmade.com/license/ -->
                                        <!-- Purchase the pro version with working PHP/AJAX contact form: [buy-url] -->
                                        Designed by <a href="">Bibert Ribano, Georgie-Anne Cabarubia, and Wendellyn A. Gaviola </a>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </footer>
    <?php endif; ?>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="fas fa-arrow-up"></i></a>

    <!-- Preloader -->
    <div id="preloader"></div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

    <!-- Include Navigation -->
    <script>
        fetch('nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('nav-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading navigation:', error));
    </script>



    <!-- Include Modals -->
    <script>
        fetch('modals.php')
            .then(response => response.text())
            .then(data => {
                document.body.insertAdjacentHTML('beforeend', data);
                // Add event listeners for login and signup buttons
                document.addEventListener('DOMContentLoaded', function() {
                    const loginBtn = document.querySelector('.btn-login');
                    const signupBtn = document.querySelector('.btn-signup');

                    if (loginBtn) {
                        loginBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                            loginModal.show();
                        });
                    }
                });

                // Add event listeners for signup modal after modals are loaded
                const signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
                const contactEmail = document.getElementById('contactEmail');
                const contactPhone = document.getElementById('contactPhone');
                const emailField = document.getElementById('emailField');
                const phoneField = document.getElementById('phoneField');
                const emailInput = document.getElementById('email');
                const phoneInput = document.getElementById('phone');
                const emailWarning = document.getElementById('emailWarning');
                const signupFormContainer = document.getElementById('signupFormContainer');
                const otpFormContainer = document.getElementById('otpFormContainer');

                function toggleContactFields() {
                    if (contactEmail.checked) {
                        emailField.style.display = 'block';
                        phoneField.style.display = 'none';
                        emailInput.required = true;
                        phoneInput.required = false;
                    } else {
                        emailField.style.display = 'none';
                        phoneField.style.display = 'block';
                        emailInput.required = false;
                        phoneInput.required = true;
                    }
                }

                contactEmail.addEventListener('change', toggleContactFields);
                contactPhone.addEventListener('change', toggleContactFields);

                // Handle signup form submission
                document.getElementById('signupForm').addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    fetch('signup.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show OTP verification form
                                signupFormContainer.style.display = 'none';
                                otpFormContainer.style.display = 'block';
                                document.getElementById('otp').focus();
                            } else {
                                if (data.message === 'this email is already used') {
                                    emailWarning.textContent = data.message;
                                    emailWarning.style.display = 'block';
                                    emailWarning.style.fontSize = 'small';
                                    emailWarning.style.color = 'red';
                                } else {
                                    alert(data.message);
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                });

                // Handle OTP form submission
                document.getElementById('otpForm').addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);

                    fetch('signup.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                signupModal.hide();
                                // Reset forms
                                document.getElementById('signupForm').reset();
                                document.getElementById('otpForm').reset();
                                signupFormContainer.style.display = 'block';
                                otpFormContainer.style.display = 'none';
                            } else {
                                alert(data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                });

                // Handle resend OTP
                document.getElementById('resendOtpBtn').addEventListener('click', function() {
                    const formData = new FormData();
                    formData.append('action', 'resend_otp');

                    fetch('signup.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            alert(data.message);
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        });
                });

                // Reset modal when closed
                document.getElementById('signupModal').addEventListener('hidden.bs.modal', function() {
                    document.getElementById('signupForm').reset();
                    document.getElementById('otpForm').reset();
                    signupFormContainer.style.display = 'block';
                    otpFormContainer.style.display = 'none';
                    emailWarning.style.display = 'none';
                });
            })
            .catch(error => console.error('Error loading modals:', error));
    </script>

    <!-- Verification Page Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            if (sendOtpBtn) {
                sendOtpBtn.addEventListener('click', function() {
                    sendOtpBtn.disabled = true;
                    sendOtpBtn.textContent = 'Sending...';

                    const formData = new FormData();
                    formData.append('action', 'send_otp');

                    fetch('signup.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success toast
                                showToast('Verification code sent');
                                // Redirect to OTP input page
                                setTimeout(() => {
                                    window.location.href = 'verify_otp.php';
                                }, 1500);
                            } else {
                                alert(data.message);
                                sendOtpBtn.disabled = false;
                                sendOtpBtn.textContent = 'Send';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                            sendOtpBtn.disabled = false;
                            sendOtpBtn.textContent = 'Send';
                        });
                });
            }
        });

        function showToast(message) {
            // Simple toast implementation
            const toast = document.createElement('div');
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.backgroundColor = '#4CAF50';
            toast.style.color = 'white';
            toast.style.padding = '12px 24px';
            toast.style.borderRadius = '4px';
            toast.style.zIndex = '9999';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 3000);
        }
    </script>

</body>

</html>