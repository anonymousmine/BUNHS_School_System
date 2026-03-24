<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  signup.php  —  Sub-Admin Signup Handler (AJAX endpoint)
//
//  Actions handled:
//    send_otp      – validate form data, store in session, send OTP
//    verify_otp    – verify OTP, save sub-admin to DB, notify admin
//    resend_otp    – regenerate and resend OTP
//    check_email   – AJAX real-time email-availability check
// ═══════════════════════════════════════════════════════════════════════════════

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   0);   // set 1 on HTTPS/production
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

include 'db_connection.php';

// ── Try to load cache helper (optional – graceful fallback) ──────────────────
if (file_exists(__DIR__ . '/cache_helper.php')) {
    require_once __DIR__ . '/cache_helper.php';
}
if (!function_exists('cache_get')) {
    function cache_get($k)
    {
        return false;
    }
}
if (!function_exists('cache_set')) {
    function cache_set($k, $v, $t)
    {
        return false;
    }
}
if (!function_exists('cache_delete')) {
    function cache_delete($k)
    {
        return false;
    }
}

// ── PHPMailer autoload ───────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// ── SMTP / SMS configuration ─────────────────────────────────────────────────
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'bunhs.deped@gmail.com');       // ← your Gmail
define('SMTP_PASS',      'svhiovmxalojxzxg');            // ← app password
define('SMTP_FROM',      'bunhs.deped@gmail.com');
define('SMTP_FROM_NAME', 'Buyoan National High School');

define('SEMAPHORE_KEY',    'YOUR_SEMAPHORE_API_KEY');    // ← Semaphore key
define('SEMAPHORE_SENDER', 'BUNHS');

define('OTP_EXPIRY',      300);   // 5 minutes
define('MAX_OTP_ATTEMPTS', 5);
define('MAX_RESEND',       3);

// ── Helpers ──────────────────────────────────────────────────────────────────
function san($v)
{
    return htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function genOTP()
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function maskEmail($email)
{
    [$user, $domain] = explode('@', $email, 2);
    return substr($user, 0, 2) . str_repeat('*', max(1, strlen($user) - 2)) . '@' . $domain;
}

function maskPhone($phone)
{
    return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 7) . substr($phone, -3);
}

function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone($phone)
{
    return preg_match('/^(09|\+639)\d{9}$/', $phone);
}

function isStrongPassword($pw)
{
    // min 8 chars, at least 1 uppercase, 1 lowercase, 1 digit, 1 special char
    return strlen($pw) >= 8
        && preg_match('/[A-Z]/', $pw)
        && preg_match('/[a-z]/', $pw)
        && preg_match('/[0-9]/', $pw)
        && preg_match('/[\W_]/', $pw);
}

function sendEmailOTP($to, $otp, $name)
{
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Fallback: log OTP for dev (never use in production)
        error_log("[DEV] Email OTP for {$to}: {$otp}");
        return ['success' => true, 'dev_otp' => $otp];
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Your BUNHS Sub-Admin Verification Code';
        $mail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;'>
          <div style='background:#1a3a2a;padding:28px 32px 20px;border-radius:12px 12px 0 0;text-align:center;'>
            <h2 style='color:#fff;margin:0;font-size:20px;'>Buyoan National High School</h2>
            <p style='color:rgba(255,255,255,.6);margin:4px 0 0;font-size:13px;'>Sub-Admin Account Verification</p>
          </div>
          <div style='background:#fff;padding:32px;border:1px solid #e0e0e0;border-top:none;'>
            <p style='color:#333;font-size:14px;margin:0 0 18px;'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p style='color:#555;font-size:14px;margin:0 0 20px;'>Your 6-digit verification code is:</p>
            <div style='background:#f4faf7;border:2px solid #2d6a4f;border-radius:10px;padding:20px;text-align:center;margin-bottom:20px;'>
              <span style='font-size:36px;font-weight:800;letter-spacing:10px;color:#1a3a2a;'>{$otp}</span>
            </div>
            <p style='color:#888;font-size:12px;margin:0;'>This code expires in 5 minutes. Do not share it with anyone.</p>
          </div>
          <div style='background:#f8f5f0;padding:16px 32px;border-radius:0 0 12px 12px;text-align:center;'>
            <p style='color:#aaa;font-size:11px;margin:0;'>Buyoan National High School &bull; Official System</p>
          </div>
        </div>";
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        // Return dev OTP so we can test locally
        return ['success' => false, 'error' => $mail->ErrorInfo, 'dev_otp' => $otp];
    }
}

function sendSmsOTP($phone, $otp)
{
    $url  = 'https://api.semaphore.co/api/v4/messages';
    $data = [
        'apikey'      => SEMAPHORE_KEY,
        'number'      => $phone,
        'message'     => "Your BUNHS verification code is: {$otp}. Valid for 5 minutes.",
        'sendername'  => SEMAPHORE_SENDER,
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        error_log("SMS error: {$err}");
        return ['success' => false, 'error' => $err, 'dev_otp' => $otp];
    }
    return ['success' => true];
}

// ─────────────────────────────────────────────────────────────────────────────
//  ACTION ROUTER
// ─────────────────────────────────────────────────────────────────────────────
$action = trim($_POST['action'] ?? '');


// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: check_email  — real-time AJAX email check
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'check_email') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!isValidEmail($email)) {
        echo json_encode(['status' => 'invalid']);
        exit;
    }
    $cache_key = "email_exists:{$email}";
    $cached    = cache_get($cache_key);
    if ($cached !== false) {
        echo json_encode(['status' => $cached]);
        exit;
    }
    $stmt = $conn->prepare("SELECT id FROM sub_admin WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    $status = $stmt->num_rows > 0 ? 'exists' : 'available';
    $stmt->close();
    cache_set($cache_key, $status, 300);
    echo json_encode(['status' => $status]);
    exit;
}


// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: send_otp  — Step 1 form submit; validate, store in session, send OTP
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'send_otp') {

    // ── 1. Collect & sanitize inputs ─────────────────────────────────────────
    $first_name     = san($_POST['first_name']     ?? '');
    $middle_initial = strtoupper(san($_POST['middle_initial'] ?? ''));
    $last_name      = san($_POST['last_name']      ?? '');
    $suffix         = san($_POST['suffix']         ?? '');
    $username       = san($_POST['username']       ?? '');
    $password       = $_POST['password']           ?? '';
    $confirm_pw     = $_POST['confirm_password']   ?? '';
    $contact_method = $_POST['contact_method']     ?? 'email';
    $email          = strtolower(trim($_POST['email']  ?? ''));
    $phone          = trim($_POST['phone']         ?? '');
    $terms          = $_POST['terms']              ?? '';

    // ── 2. Validation ────────────────────────────────────────────────────────
    if (!$first_name || !$last_name) {
        echo json_encode(['success' => false, 'message' => 'First and last name are required.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z\s\-]+$/', $first_name) || !preg_match('/^[A-Za-z\s\-]+$/', $last_name)) {
        echo json_encode(['success' => false, 'message' => 'Name must contain letters only.']);
        exit;
    }
    if ($middle_initial && !preg_match('/^[A-Z]{1,2}\.?$/', $middle_initial)) {
        echo json_encode(['success' => false, 'message' => 'Middle initial must be 1–2 letters (e.g. A. or AB).']);
        exit;
    }
    if (strlen($username) < 3 || strlen($username) > 50) {
        echo json_encode(['success' => false, 'message' => 'Username must be 3–50 characters.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $username)) {
        echo json_encode(['success' => false, 'message' => 'Username may only contain letters, numbers, underscores, hyphens and dots.']);
        exit;
    }
    if (!$password) {
        echo json_encode(['success' => false, 'message' => 'Password is required.']);
        exit;
    }
    if (!isStrongPassword($password)) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters and include uppercase, lowercase, a number, and a special character.']);
        exit;
    }
    if ($password !== $confirm_pw) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    if (!$terms) {
        echo json_encode(['success' => false, 'message' => 'You must agree to the Terms of Service.']);
        exit;
    }

    // Contact validation
    if ($contact_method === 'email') {
        if (!isValidEmail($email)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }
    } else {
        if (!isValidPhone($phone)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid Philippine phone number (09XXXXXXXXX).']);
            exit;
        }
    }

    // ── 3. Check username uniqueness ─────────────────────────────────────────
    $stmt = $conn->prepare("SELECT id FROM sub_admin WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'That username is already taken. Please choose another.']);
        exit;
    }
    $stmt->close();

    // ── 4. Check email uniqueness (if email method) ──────────────────────────
    if ($contact_method === 'email') {
        $stmt = $conn->prepare("SELECT id FROM sub_admin WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'That email address is already registered.']);
            exit;
        }
        $stmt->close();
    }

    // ── 5. Generate OTP ──────────────────────────────────────────────────────
    $otp = genOTP();

    // ── 6. Store everything in session (not yet written to DB) ───────────────
    $_SESSION['sa_signup'] = [
        'first_name'     => $first_name,
        'middle_initial' => $middle_initial,
        'last_name'      => $last_name,
        'suffix'         => $suffix,
        'username'       => $username,
        'password_hash'  => password_hash($password, PASSWORD_BCRYPT),
        'contact_method' => $contact_method,
        'email'          => $email,
        'phone'          => $phone,
        'otp'            => $otp,
        'otp_expires'    => time() + OTP_EXPIRY,
        'otp_attempts'   => 0,
        'resend_count'   => 0,
    ];

    // ── 7. Send OTP ──────────────────────────────────────────────────────────
    $display_name = $first_name . ' ' . $last_name;
    if ($contact_method === 'email') {
        $result = sendEmailOTP($email, $otp, $display_name);
        $masked = maskEmail($email);
    } else {
        $result = sendSmsOTP($phone, $otp);
        $masked = maskPhone($phone);
    }

    $response = ['success' => true, 'masked_contact' => $masked];
    // Expose OTP in dev mode (localhost) to aid testing
    if (!empty($result['dev_otp']) || (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']))) {
        $response['dev_otp'] = $otp;
    }
    echo json_encode($response);
    exit;
}


// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: verify_otp  — Step 2: check OTP and save sub-admin to DB
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'verify_otp') {

    if (empty($_SESSION['sa_signup'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }

    $sd  = &$_SESSION['sa_signup'];
    $otp = trim($_POST['otp'] ?? '');

    // Rate-limit OTP attempts
    if (($sd['otp_attempts'] ?? 0) >= MAX_OTP_ATTEMPTS) {
        unset($_SESSION['sa_signup']);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please start registration again.']);
        exit;
    }

    // Check expiry
    if (time() > ($sd['otp_expires'] ?? 0)) {
        unset($_SESSION['sa_signup']);
        echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please start over.']);
        exit;
    }

    // Validate OTP format
    if (!preg_match('/^\d{6}$/', $otp)) {
        $sd['otp_attempts']++;
        echo json_encode(['success' => false, 'message' => 'Invalid code format.']);
        exit;
    }

    // Compare (constant-time)
    if (!hash_equals((string)($sd['otp'] ?? ''), $otp)) {
        $sd['otp_attempts']++;
        $left = MAX_OTP_ATTEMPTS - $sd['otp_attempts'];
        echo json_encode(['success' => false, 'message' => "Incorrect code. {$left} attempt(s) remaining."]);
        exit;
    }

    // ── OTP correct — write sub-admin to DB ──────────────────────────────────
    $first_name     = $sd['first_name'];
    $middle_initial = $sd['middle_initial'];
    $last_name      = $sd['last_name'];
    $suffix         = $sd['suffix'];
    $username       = $sd['username'];
    $password_hash  = $sd['password_hash'];
    $email          = $sd['email'];
    $phone          = $sd['phone'];

    // Build full name for display
    $full_name = trim($first_name
        . ($middle_initial ? ' ' . rtrim($middle_initial, '.') . '.' : '')
        . ' ' . $last_name
        . ($suffix ? ' ' . $suffix : ''));

    // ── Ensure sub_admin table has the columns we need (safe ALTER) ──────────
    $conn->query("ALTER TABLE `sub_admin`
        ADD COLUMN IF NOT EXISTS `first_name`     VARCHAR(100) NULL AFTER `id`,
        ADD COLUMN IF NOT EXISTS `middle_initial` VARCHAR(5)   NULL AFTER `first_name`,
        ADD COLUMN IF NOT EXISTS `last_name`      VARCHAR(100) NULL AFTER `middle_initial`,
        ADD COLUMN IF NOT EXISTS `suffix`         VARCHAR(20)  NULL AFTER `last_name`,
        ADD COLUMN IF NOT EXISTS `full_name`      VARCHAR(255) NULL,
        ADD COLUMN IF NOT EXISTS `phone`          VARCHAR(20)  NULL,
        ADD COLUMN IF NOT EXISTS `status`         ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
        ADD COLUMN IF NOT EXISTS `created_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN IF NOT EXISTS `role`           VARCHAR(255) DEFAULT 'news_admin'
    ");

    $stmt = $conn->prepare(
        "INSERT INTO `sub_admin`
         (first_name, middle_initial, last_name, suffix, full_name, username, email, phone, password, status, role, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'news_admin', NOW())"
    );

    if (!$stmt) {
        error_log('signup.php INSERT prepare error: ' . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        exit;
    }

    $stmt->bind_param(
        'sssssssss',
        $first_name,
        $middle_initial,
        $last_name,
        $suffix,
        $full_name,
        $username,
        $email,
        $phone,
        $password_hash
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        error_log('signup.php INSERT execute error: ' . $err);
        // Duplicate entry check
        if (strpos($err, '1062') !== false || strpos($err, 'Duplicate') !== false) {
            echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not save account. Please try again.']);
        }
        exit;
    }

    $new_id = $conn->insert_id;
    $stmt->close();

    // ── Invalidate email cache so check_email reflects reality ───────────────
    if ($email) cache_delete("email_exists:{$email}");

    // ── Notify admin via email (best-effort, non-blocking) ───────────────────
    // Fetch admin email from admins table
    $admin_email = '';
    $ar = $conn->query("SELECT school_email FROM admins LIMIT 1");
    if ($ar && $row = $ar->fetch_assoc()) $admin_email = trim($row['school_email'] ?? '');

    // Only attempt delivery to real, externally-routable email addresses.
    // Skip fake/placeholder domains (e.g. buyoan.edu, example.com, localhost).
    $skip_domains = ['buyoan.edu', 'example.com', 'test.com', 'localhost', 'school.local'];
    $admin_domain = strtolower(substr(strrchr($admin_email, '@'), 1));
    $can_email_admin = $admin_email
        && isValidEmail($admin_email)
        && !in_array($admin_domain, $skip_domains)
        && class_exists('PHPMailer\PHPMailer\PHPMailer');

    if ($can_email_admin) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($admin_email, 'Admin');
            $mail->isHTML(true);
            $mail->Subject = 'New Sub-Admin Registration Pending Approval';
            $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:480px;'>
              <h3 style='color:#1a3a2a;'>New Sub-Admin Registration</h3>
              <p>A new sub-admin account is awaiting your approval:</p>
              <ul>
                <li><strong>Name:</strong> " . htmlspecialchars($full_name) . "</li>
                <li><strong>Username:</strong> " . htmlspecialchars($username) . "</li>
                <li><strong>Email:</strong> " . htmlspecialchars($email) . "</li>
              </ul>
              <p>Please log in to the admin panel to approve or reject this request.</p>
            </div>";
            $mail->send();
        } catch (\Exception $e) {
            error_log('Admin notification email failed: ' . $e->getMessage());
            // Non-fatal — registration still succeeds
        }
    } else {
        // Log the pending registration for the admin to see in the panel
        error_log("New sub-admin pending approval — ID:{$new_id} username:{$username} email:{$email}");
    }

    // ── Clear signup session ──────────────────────────────────────────────────
    unset($_SESSION['sa_signup']);

    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully! Your account is pending admin approval. You will be notified once approved.',
    ]);
    exit;
}


// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: resend_otp
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'resend_otp') {

    if (empty($_SESSION['sa_signup'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }

    $sd = &$_SESSION['sa_signup'];

    if (($sd['resend_count'] ?? 0) >= MAX_RESEND) {
        echo json_encode(['success' => false, 'message' => 'Maximum resend limit reached. Please start registration again.']);
        exit;
    }

    $otp = genOTP();
    $sd['otp']          = $otp;
    $sd['otp_expires']  = time() + OTP_EXPIRY;
    $sd['otp_attempts'] = 0;
    $sd['resend_count'] = ($sd['resend_count'] ?? 0) + 1;

    $name = $sd['first_name'] . ' ' . $sd['last_name'];

    if ($sd['contact_method'] === 'email') {
        $result = sendEmailOTP($sd['email'], $otp, $name);
        $masked = maskEmail($sd['email']);
    } else {
        $result = sendSmsOTP($sd['phone'], $otp);
        $masked = maskPhone($sd['phone']);
    }

    $response = ['success' => true, 'message' => 'A new code has been sent to ' . $masked];
    if (!empty($result['dev_otp'])) $response['dev_otp'] = $otp;
    echo json_encode($response);
    exit;
}


// ══════════════════════════════════════════════════════════════════════════════
//  Fallback — unknown action
// ══════════════════════════════════════════════════════════════════════════════
echo json_encode(['success' => false, 'message' => 'Invalid action.']);
