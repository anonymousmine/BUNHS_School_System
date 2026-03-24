<?php

/**
 * subadmin_signup.php — Admin-only endpoint to create pending sub-admin accounts
 * Mirrors user_account/student_signup.php logic but targets `sub_admin` table
 * Creates with status='pending' → main admin approves via admins.php
 */

// JSON + error handler
header('Content-Type: application/json');
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
    }
});
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

// DB connection (walk up tree)
$_db = null;
$_s = dirname(__DIR__);
for ($_i = 0; $_i < 6; $_i++) {
    if (file_exists($_s . '/db_connection.php')) {
        $_db = $_s . '/db_connection.php';
        break;
    }
    $_p = dirname($_s);
    if ($_p === $_s) break;
    $_s = $_p;
}
if (!$_db) {
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}
include $_db;

// Admin-only check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin'])) {
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

// Ensure sub_admin columns
$conn->query("ALTER TABLE `sub_admin` ADD COLUMN IF NOT EXISTS `status` ENUM('pending','approved','rejected') DEFAULT 'pending'");
$conn->query("ALTER TABLE `sub_admin` ADD COLUMN IF NOT EXISTS `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP");

// Config
define('SA_OTP_EXPIRY', 300);
define('SA_MAX_ATTEMPTS', 5);
define('SA_MAX_RESENDS', 3);

function sa_gen_otp()
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sa_columns($conn)
{
    static $cols;
    if ($cols) return $cols;
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM `sub_admin`");
    while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    return $cols;
}

function sa_has_col($conn, $col)
{
    return in_array($col, sa_columns($conn));
}

// SMTP (reuse student_signup config)
define('SA_SMTP_HOST', 'smtp.gmail.com');
define('SA_SMTP_PORT', 587);
define('SA_SMTP_USER', 'bunhs.deped@gmail.com');
define('SA_SMTP_PASS', 'msqncrybbxlxhmbn');
define('SA_SMTP_FROM', 'bunhs.deped@gmail.com');
define('SA_SMTP_NAME', 'BUNHS Admin');

function sa_send_email($to, $otp)
{
    $autoload = null;
    $search = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $cand = $search . '/vendor/autoload.php';
        if (file_exists($cand)) {
            $autoload = $cand;
            break;
        }
        $search = dirname($search);
    }
    if (!$autoload) {
        error_log("=== DEV SUBADMIN OTP: {$otp} to {$to}");
        return true;
    }
    require_once $autoload;
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SA_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SA_SMTP_USER;
        $mail->Password = SA_SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SA_SMTP_PORT;
        $mail->setFrom(SA_SMTP_FROM, SA_SMTP_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'BUNHS — Sub-Admin Registration Code';
        $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:30px;border:1px solid #dde8e2;border-radius:16px;">
            <h2 style="color:#1a3a2a;text-align:center;">Sub-Admin Verification Code</h2>
            <div style="background:#f1f3f4;border-radius:8px;padding:20px;text-align:center;letter-spacing:10px;font-size:36px;font-weight:bold;color:#202124;margin:24px 0;">'
            . htmlspecialchars($otp) . '</div>
            <p style="color:#888;font-size:13px;text-align:center;">Expires in 5 minutes. For admin use only.</p>
        </div>';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Subadmin email error: ' . $mail->ErrorInfo);
        return false;
    }
}

$action = trim($_POST['action'] ?? '');

if ($action === 'sa_register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';
    $method = trim($_POST['contact_method'] ?? 'email');

    // Validation
    if (strlen($password) < 8 || $password !== $confirm_pw) {
        echo json_encode(['success' => false, 'message' => 'Password must be 8+ chars and match confirmation.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Valid email required.']);
        exit;
    }
    if ($method === 'phone' && empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone required for SMS.']);
        exit;
    }

    // Check username/email uniqueness
    $chk = $conn->prepare("SELECT id FROM `sub_admin` WHERE username=? OR email=?");
    $chk->bind_param('ss', $username, $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }
    $chk->close();

    $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $otp = sa_gen_otp();

    $_SESSION['sa_pending'] = [
        'username' => $username,
        'email' => $email,
        'phone' => $phone,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'password' => $hashed,
        'method' => $method,
        'otp' => $otp,
        'otp_expires' => time() + SA_OTP_EXPIRY,
        'otp_attempts' => 0,
        'resend_count' => 0
    ];

    $sent = ($method === 'email') ? sa_send_email($email, $otp) : true; // SMS placeholder
    echo json_encode(['success' => true, 'dev_otp' => $otp]); // Remove dev_otp in prod
    exit;
}

if ($action === 'sa_verify_otp') {
    $otp_input = trim($_POST['otp'] ?? '');
    if (empty($_SESSION['sa_pending']) || time() > $_SESSION['sa_pending']['otp_expires'] || $_SESSION['sa_pending']['otp_attempts'] >= SA_MAX_ATTEMPTS) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please try again.']);
        exit;
    }
    $p = &$_SESSION['sa_pending'];
    if ($otp_input !== $p['otp']) {
        $p['otp_attempts']++;
        echo json_encode(['success' => false, 'message' => 'Invalid code.']);
        exit;
    }

    // Insert pending sub-admin
    $cols = ['username', 'password', 'email', 'first_name', 'last_name', 'status'];
    $vals = [$p['username'], $p['password'], $p['email'], $p['first_name'], $p['last_name'], 'pending'];
    $types = 'ssssss';

    if (sa_has_col($conn, 'phone')) {
        $cols[] = 'phone';
        $vals[] = $p['phone'];
        $types .= 's';
    }

    $sql = 'INSERT INTO `sub_admin` (' . implode(',', $cols) . ') VALUES (' . str_repeat('?,', count($cols) - 1) . '?)';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $success = $stmt->execute();
    $stmt->close();

    unset($_SESSION['sa_pending']);
    echo json_encode(['success' => $success]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
