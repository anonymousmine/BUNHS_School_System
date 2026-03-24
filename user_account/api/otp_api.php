<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  otp_api.php — CACHED VERSION
//  PHP 5.4+ compatible — no ?? operator, no type hints, no const
//
//  CACHE INTEGRATION:
//  ┌─────────────────────────────────────────────────────────────────────────┐
//  │ OTP codes themselves live in $_SESSION — NOT in APCu.                   │
//  │ Sessions are per-user, per-browser, and expire independently per user.  │
//  │ Storing OTPs in a shared cache would be a security hole.                │
//  │                                                                         │
//  │ What APCu DOES here:                                                    │
//  │   After verify_phone_otp / verify_email_otp succeeds and the DB        │
//  │   UPDATE runs, we invalidate the student's notification preference      │
//  │   cache so get_preference returns fresh data on the next call.          │
//  └─────────────────────────────────────────────────────────────────────────┘
// ═══════════════════════════════════════════════════════════════════════════════

function _otp_shutdown()
{
    $e = error_get_last();
    if ($e && ($e['type'] === E_ERROR || $e['type'] === E_PARSE ||
        $e['type'] === E_CORE_ERROR || $e['type'] === E_COMPILE_ERROR)) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'Server error: ' . $e['message'] . ' (line ' . $e['line'] . ')',
        ));
    }
}
register_shutdown_function('_otp_shutdown');

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   0);
ini_set('session.use_only_cookies', 1);
session_start();

// ── DB: walk UP the directory tree until db_connection.php is found ───────────
$_db     = null;
$_search = __DIR__;
for ($_i = 0; $_i < 6; $_i++) {
    $_cand = $_search . '/db_connection.php';
    if (file_exists($_cand)) {
        $_db = $_cand;
        break;
    }
    $_parent = dirname($_search);
    if ($_parent === $_search) break;
    $_search = $_parent;
}
if (!$_db) {
    echo json_encode(array(
        'success' => false,
        'message' => 'Cannot find db_connection.php. Searched from: ' . __DIR__,
    ));
    exit;
}
include $_db;

// ── Load caching layer (walk up same way) ─────────────────────────────────────
$_ch     = null;
$_search = __DIR__;
for ($_i = 0; $_i < 6; $_i++) {
    $_cand = $_search . '/cache_helper.php';
    if (file_exists($_cand)) {
        $_ch = $_cand;
        break;
    }
    $_parent = dirname($_search);
    if ($_parent === $_search) break;
    $_search = $_parent;
}
if ($_ch) {
    include_once $_ch;
} else {
    // Graceful no-op stubs when cache_helper is not available
    if (!function_exists('cache_delete')) {
        function cache_delete($k) {}
    }
}

// ── Session guard ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['student_id'])) {
    echo json_encode(array('success' => false, 'message' => 'Session expired. Please log in again.'));
    exit;
}

// Settings
$OTP_EXPIRY   = 300;
$OTP_LENGTH   = 5;
$MAX_ATTEMPTS = 5;
$MAX_RESENDS  = 3;
$SMTP_HOST    = 'smtp.gmail.com';
$SMTP_PORT    = 587;
$SMTP_USER    = 'bunhs.deped@gmail.com';
$SMTP_PASS    = 'svhiovmxalojxzxg';
$SMTP_FROM    = 'bunhs.deped@gmail.com';
$SMTP_NAME    = 'Buyoan National High School';
$SEM_KEY      = 'YOUR_SEMAPHORE_API_KEY';
$SEM_SENDER   = 'BUNHS';

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// ── HELPERS — unchanged from original ─────────────────────────────────────────

function _genOtp($len)
{
    $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $result = '';
    for ($i = 0; $i < $len; $i++) {
        $result .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $result;
}

function _maskPhone($p)
{
    $d = preg_replace('/\D/', '', $p);
    if (strlen($d) < 7) return $p;
    return substr($d, 0, 4) . '****' . substr($d, -3);
}

function _maskEmail($e)
{
    if (strpos($e, '@') === false) return $e;
    $parts = explode('@', $e, 2);
    return substr($parts[0], 0, 2) . '***@' . $parts[1];
}

function _sendEmail($to, $otp, $smtp)
{
    $autoload = null;
    $search   = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $cand = $search . '/vendor/autoload.php';
        if (file_exists($cand)) {
            $autoload = $cand;
            break;
        }
        $parent = dirname($search);
        if ($parent === $search) break;
        $search = $parent;
    }
    if (!$autoload) {
        error_log('DEV OTP email [' . $to . ']: ' . $otp);
        return true;
    }
    require_once $autoload;
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['user'];
        $mail->Password   = $smtp['pass'];
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtp['port'];
        $mail->setFrom($smtp['from'], $smtp['name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'BUNHS Notification Verification Code';
        $mail->Body    = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:30px;border:1px solid #dde8e2;border-radius:16px;">'
            . '<h2 style="color:#1a3a2a;text-align:center;">Notification Verification Code</h2>'
            . '<p style="color:#555;text-align:center;">Use this code to verify your contact for school notifications.</p>'
            . '<div style="background:#f8f5f0;border-radius:8px;padding:20px;text-align:center;letter-spacing:10px;font-size:36px;font-weight:bold;color:#1a3a2a;margin:24px 0;">'
            . htmlspecialchars($otp) . '</div>'
            . '<p style="color:#888;font-size:13px;text-align:center;">Expires in <strong>5 minutes</strong>. Never share this code.</p></div>';
        $mail->AltBody = 'Your BUNHS notification code: ' . $otp . ' (expires in 5 minutes)';
        $mail->send();
        return true;
    } catch (\Exception $ex) {
        error_log('OTP email error: ' . $mail->ErrorInfo);
        return false;
    }
}

function _sendSms($phone, $otp, $key, $sender)
{
    $clean = preg_replace('/\D/', '', $phone);
    if (substr($clean, 0, 1) === '0') $clean = '63' . substr($clean, 1);
    if ($key === 'YOUR_SEMAPHORE_API_KEY') {
        error_log('DEV OTP sms [' . $clean . ']: ' . $otp);
        return true;
    }
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => 'https://semaphore.co/api/v4/messages',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(array(
            'apikey'     => $key,
            'number'     => $clean,
            'message'    => 'Your BUNHS notification code: ' . $otp . ' (expires in 5 min)',
            'sendername' => $sender,
        )),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ));
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code === 200);
}

$smtpCfg = array(
    'host' => $SMTP_HOST,
    'port' => $SMTP_PORT,
    'user' => $SMTP_USER,
    'pass' => $SMTP_PASS,
    'from' => $SMTP_FROM,
    'name' => $SMTP_NAME,
);


// ── send_phone_otp — unchanged; OTPs stay in $_SESSION ────────────────────────
if ($action === 'send_phone_otp') {
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    if ($phone === '') {
        echo json_encode(array('success' => false, 'message' => 'Phone number is required.'));
        exit;
    }
    $otp = _genOtp($OTP_LENGTH);
    $_SESSION['notif_phone_otp'] = array(
        'otp' => $otp,
        'expires' => time() + $OTP_EXPIRY,
        'attempts' => 0,
        'resends' => 0,
        'phone' => $phone,
    );
    $sent = _sendSms($phone, $otp, $SEM_KEY, $SEM_SENDER);
    if ($sent) {
        echo json_encode(array('success' => true, 'masked' => _maskPhone($phone), 'dev_otp' => $otp));
    } else {
        unset($_SESSION['notif_phone_otp']);
        echo json_encode(array('success' => false, 'message' => 'Failed to send SMS. Please try again.'));
    }
    exit;
}


// ── send_email_otp — unchanged; OTPs stay in $_SESSION ────────────────────────
if ($action === 'send_email_otp') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array('success' => false, 'message' => 'A valid email address is required.'));
        exit;
    }
    $otp = _genOtp($OTP_LENGTH);
    $_SESSION['notif_email_otp'] = array(
        'otp' => $otp,
        'expires' => time() + $OTP_EXPIRY,
        'attempts' => 0,
        'resends' => 0,
        'email' => $email,
    );
    $sent = _sendEmail($email, $otp, $smtpCfg);
    if ($sent) {
        echo json_encode(array('success' => true, 'masked' => _maskEmail($email), 'dev_otp' => $otp));
    } else {
        unset($_SESSION['notif_email_otp']);
        echo json_encode(array('success' => false, 'message' => 'Failed to send email. Please try again.'));
    }
    exit;
}


// ═════════════════════════════════════════════════════════════════════════════
//  verify_phone_otp
//  ▼ CACHE PATCH — after successful DB UPDATE, invalidate notif preference cache
// ═════════════════════════════════════════════════════════════════════════════
if ($action === 'verify_phone_otp') {
    $input = strtoupper(trim(isset($_POST['otp']) ? $_POST['otp'] : ''));
    if (!isset($_SESSION['notif_phone_otp'])) {
        echo json_encode(array('success' => false, 'message' => 'Session expired. Please restart.'));
        exit;
    }
    $d = &$_SESSION['notif_phone_otp'];
    if ($d['attempts'] >= $MAX_ATTEMPTS) {
        unset($_SESSION['notif_phone_otp']);
        echo json_encode(array('success' => false, 'message' => 'Too many attempts. Please restart.'));
        exit;
    }
    if (time() > $d['expires']) {
        unset($_SESSION['notif_phone_otp']);
        echo json_encode(array('success' => false, 'message' => 'Code expired. Please request a new one.'));
        exit;
    }
    if ($input !== $d['otp']) {
        $d['attempts']++;
        $rem = $MAX_ATTEMPTS - $d['attempts'];
        echo json_encode(array('success' => false, 'message' => 'Invalid code. ' . $rem . ' attempt(s) remaining.'));
        exit;
    }

    // ── OTP correct — update DB ───────────────────────────────────────────────
    $sid  = $_SESSION['student_id'];
    $stmt = $conn->prepare("UPDATE students SET phone_verified = 1 WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param('s', $sid);
        $stmt->execute();
        $stmt->close();
    }
    unset($_SESSION['notif_phone_otp']);

    // ▼ CACHE PATCH — phone_verified changed in DB; stale notif cache is now wrong
    cache_delete("notif:{$sid}");

    echo json_encode(array('success' => true));
    exit;
}


// ═════════════════════════════════════════════════════════════════════════════
//  verify_email_otp
//  ▼ CACHE PATCH — same as verify_phone_otp: invalidate notif preference cache
// ═════════════════════════════════════════════════════════════════════════════
if ($action === 'verify_email_otp') {
    $input = strtoupper(trim(isset($_POST['otp']) ? $_POST['otp'] : ''));
    if (!isset($_SESSION['notif_email_otp'])) {
        echo json_encode(array('success' => false, 'message' => 'Session expired. Please restart.'));
        exit;
    }
    $d = &$_SESSION['notif_email_otp'];
    if ($d['attempts'] >= $MAX_ATTEMPTS) {
        unset($_SESSION['notif_email_otp']);
        echo json_encode(array('success' => false, 'message' => 'Too many attempts. Please restart.'));
        exit;
    }
    if (time() > $d['expires']) {
        unset($_SESSION['notif_email_otp']);
        echo json_encode(array('success' => false, 'message' => 'Code expired. Please request a new one.'));
        exit;
    }
    if ($input !== $d['otp']) {
        $d['attempts']++;
        $rem = $MAX_ATTEMPTS - $d['attempts'];
        echo json_encode(array('success' => false, 'message' => 'Invalid code. ' . $rem . ' attempt(s) remaining.'));
        exit;
    }

    // ── OTP correct — update DB ───────────────────────────────────────────────
    $sid  = $_SESSION['student_id'];
    $stmt = $conn->prepare("UPDATE students SET email_verified = 1 WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param('s', $sid);
        $stmt->execute();
        $stmt->close();
    }
    unset($_SESSION['notif_email_otp']);

    // ▼ CACHE PATCH — email_verified changed in DB; invalidate stale notif cache
    cache_delete("notif:{$sid}");

    echo json_encode(array('success' => true));
    exit;
}


// ── resend_otp — unchanged; OTPs stay in $_SESSION ────────────────────────────
if ($action === 'resend_otp') {
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';

    if ($type === 'phone') {
        if (!isset($_SESSION['notif_phone_otp'])) {
            echo json_encode(array('success' => false, 'message' => 'Session expired.'));
            exit;
        }
        $d = &$_SESSION['notif_phone_otp'];
        if ($d['resends'] >= $MAX_RESENDS) {
            echo json_encode(array('success' => false, 'message' => 'Resend limit reached.'));
            exit;
        }
        $otp           = _genOtp($OTP_LENGTH);
        $d['otp']      = $otp;
        $d['expires']  = time() + $OTP_EXPIRY;
        $d['attempts'] = 0;
        $d['resends']++;
        $sent = _sendSms($d['phone'], $otp, $SEM_KEY, $SEM_SENDER);
        $rem  = $MAX_RESENDS - $d['resends'];
        echo json_encode(array(
            'success' => $sent,
            'message' => $sent ? 'New code sent. (' . $rem . ' resend(s) left)' : 'Failed to send SMS.',
            'dev_otp' => $otp,
        ));
        exit;
    }

    if ($type === 'email') {
        if (!isset($_SESSION['notif_email_otp'])) {
            echo json_encode(array('success' => false, 'message' => 'Session expired.'));
            exit;
        }
        $d = &$_SESSION['notif_email_otp'];
        if ($d['resends'] >= $MAX_RESENDS) {
            echo json_encode(array('success' => false, 'message' => 'Resend limit reached.'));
            exit;
        }
        $otp           = _genOtp($OTP_LENGTH);
        $d['otp']      = $otp;
        $d['expires']  = time() + $OTP_EXPIRY;
        $d['attempts'] = 0;
        $d['resends']++;
        $sent = _sendEmail($d['email'], $otp, $smtpCfg);
        $rem  = $MAX_RESENDS - $d['resends'];
        echo json_encode(array(
            'success' => $sent,
            'message' => $sent ? 'New code sent. (' . $rem . ' resend(s) left)' : 'Failed to send email.',
            'dev_otp' => $otp,
        ));
        exit;
    }

    echo json_encode(array('success' => false, 'message' => 'Invalid resend type.'));
    exit;
}

echo json_encode(array('success' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)));
