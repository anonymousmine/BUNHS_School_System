<?php

/**
 * dashboard_verify_otp.php
 * ─────────────────────────────────────────────────────────────
 * AJAX endpoint — handles OTP send/verify/resend for the
 * Student Dashboard verification form.
 *
 * Mirrors the logic in login_otp.php but is specifically for
 * email-based verification in Dashboard.php.
 *
 * Supported actions (POST):
 *   dash_send_otp    – validate email, generate + email OTP
 *   dash_verify_otp  – verify submitted OTP against session
 *   dash_resend_otp  – regenerate + re-send OTP
 * ─────────────────────────────────────────────────────────────
 */

// ── Suppress notices so they never corrupt JSON output ───────
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// ── Secure session (must match Dashboard.php) ─────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);   // set to 1 on HTTPS/production
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
session_start();

// ── Only authenticated students may use this endpoint ────────
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

// ── SMTP / OTP constants ──────────────────────────────────────
define('DV_SMTP_HOST',     'smtp.gmail.com');
define('DV_SMTP_PORT',     587);
define('DV_SMTP_USER',     'bunhs.deped@gmail.com');
define('DV_SMTP_PASS',     'svhiovmxalojxzxg');          // Gmail App Password
define('DV_SMTP_FROM',     'bunhs.deped@gmail.com');
define('DV_SMTP_FROM_NAME', 'Buyoan National High School');

define('DV_OTP_EXPIRY',       300);   // 5 minutes
define('DV_MAX_OTP_ATTEMPTS', 5);
define('DV_MAX_RESEND',       3);

// ── Helpers ──────────────────────────────────────────────────

/**
 * Sanitize user input.
 */
function dv_sanitize(string $v): string
{
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a 6-digit zero-padded OTP.
 */
function dv_gen_otp(): string
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Mask email for display — e.g. jo***@gmail.com
 */
function dv_mask_email(string $email): string
{
    [$user, $domain] = explode('@', $email, 2);
    $masked = substr($user, 0, 2) . str_repeat('*', max(1, strlen($user) - 2));
    return $masked . '@' . $domain;
}

/**
 * Send OTP via Gmail SMTP using PHPMailer.
 * Falls back gracefully when PHPMailer is not installed (dev mode).
 *
 * @param string $to    Recipient email address
 * @param string $otp   6-digit OTP code
 * @return bool         true on success, false on failure
 */
function dv_send_email(string $to, string $otp): bool
{
    // Locate PHPMailer autoload — two common locations
    $candidates = [
        __DIR__ . '/vendor/autoload.php',           // same dir as this file
        __DIR__ . '/../vendor/autoload.php',         // one level up (common XAMPP layout)
    ];

    $autoload = null;
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            $autoload = $path;
            break;
        }
    }

    if (!$autoload) {
        // PHPMailer not installed — log OTP for local dev and return false
        error_log("=== DEV DASHBOARD OTP for [{$to}]: {$otp} ===");
        return false;
    }

    require_once $autoload;

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = DV_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = DV_SMTP_USER;
        $mail->Password   = DV_SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = DV_SMTP_PORT;
        $mail->setFrom(DV_SMTP_FROM, DV_SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'Buyoan National High School — Verification Code';
        $mail->Body    = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:30px;
                        border:1px solid #e0e0e0;border-radius:12px;">
                <h2 style="color:#1a3a2a;text-align:center;">Dashboard Verification Code</h2>
                <p style="color:#555;text-align:center;">
                    Use this code to verify your identity on the student dashboard.
                </p>
                <div style="background:#f1f3f4;border-radius:8px;padding:20px;text-align:center;
                            letter-spacing:10px;font-size:36px;font-weight:bold;color:#202124;
                            margin:24px 0;">' . htmlspecialchars($otp) . '</div>
                <p style="color:#888;font-size:13px;text-align:center;">
                    Expires in <strong>5 minutes</strong>. Never share this code.
                </p>
                <p style="color:#aaa;font-size:11px;text-align:center;margin-top:16px;">
                    Buyoan National High School &mdash; Student Portal
                </p>
            </div>';
        $mail->AltBody = "Your BUNHS dashboard verification code: {$otp} (expires in 5 minutes)";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('DashboardOTP mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ── Action router ─────────────────────────────────────────────
$action = $_POST['action'] ?? '';

// ────────────────────────────────────────────────────────────────
// ACTION: dash_send_otp
// Validates the email, generates an OTP, stores it in session,
// and sends it to the user's Gmail address.
// ────────────────────────────────────────────────────────────────
if ($action === 'dash_send_otp') {

    $email = trim($_POST['email'] ?? '');

    // Basic validation
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your email address.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    // Only accept Gmail addresses (as per project scope)
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    if ($domain !== 'gmail.com') {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid Gmail address (e.g. yourname@gmail.com).']);
        exit;
    }

    // Generate OTP and store in session
    $otp = dv_gen_otp();
    $_SESSION['dash_otp_data'] = [
        'otp'          => $otp,
        'otp_expires'  => time() + DV_OTP_EXPIRY,
        'otp_attempts' => 0,
        'resend_count' => 0,
        'email'        => $email,
    ];

    // Attempt to send
    $sent = dv_send_email($email, $otp);

    if (!$sent) {
        // DEV MODE: PHPMailer not configured — log OTP and still succeed
        // so the UI flow can be tested locally.
        // ⚠️ In production: replace the two lines below with an error response.
        error_log("=== DEV DASHBOARD OTP for [{$email}]: {$otp} ===");
        $sent = true; // comment this line out in production to block undelivered OTPs
    }

    if ($sent) {
        echo json_encode([
            'success'        => true,
            'masked_email'   => dv_mask_email($email),
            // ⚠️ DEV ONLY — remove dev_otp before going to production!
            'dev_otp'        => $otp,
        ]);
    } else {
        unset($_SESSION['dash_otp_data']);
        echo json_encode(['success' => false, 'message' => 'Failed to send verification code. Please try again.']);
    }
    exit;
}

// ────────────────────────────────────────────────────────────────
// ACTION: dash_verify_otp
// Checks the submitted 6-digit code against the session value.
// ────────────────────────────────────────────────────────────────
if ($action === 'dash_verify_otp') {

    $otp_input = trim($_POST['otp'] ?? '');

    if (empty($_SESSION['dash_otp_data'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please request a new code.']);
        exit;
    }

    $d = &$_SESSION['dash_otp_data'];

    // Brute-force guard
    if ($d['otp_attempts'] >= DV_MAX_OTP_ATTEMPTS) {
        unset($_SESSION['dash_otp_data']);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please request a new code.']);
        exit;
    }

    // Expiry check
    if (time() > $d['otp_expires']) {
        unset($_SESSION['dash_otp_data']);
        echo json_encode(['success' => false, 'message' => 'Code expired. Please request a new code.']);
        exit;
    }

    // Format + value check
    if (!preg_match('/^\d{6}$/', $otp_input) || $otp_input !== $d['otp']) {
        $d['otp_attempts']++;
        $remaining = DV_MAX_OTP_ATTEMPTS - $d['otp_attempts'];
        echo json_encode([
            'success' => false,
            'message' => "Invalid code. {$remaining} attempt(s) remaining.",
        ]);
        exit;
    }

    // ✅ Correct — mark as verified in session and clean up OTP data
    $_SESSION['dash_email_verified'] = true;
    $_SESSION['dash_verified_email'] = $d['email'];
    unset($_SESSION['dash_otp_data']);

    echo json_encode(['success' => true, 'message' => 'Email verified successfully.']);
    exit;
}

// ────────────────────────────────────────────────────────────────
// ACTION: dash_resend_otp
// Regenerates the OTP and re-sends it without requiring the
// email address again (reuses value stored in session).
// ────────────────────────────────────────────────────────────────
if ($action === 'dash_resend_otp') {

    if (empty($_SESSION['dash_otp_data'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }

    $d = &$_SESSION['dash_otp_data'];

    if ($d['resend_count'] >= DV_MAX_RESEND) {
        echo json_encode(['success' => false, 'message' => 'Maximum resend limit reached. Please start again.']);
        exit;
    }

    $otp = dv_gen_otp();
    $d['otp']          = $otp;
    $d['otp_expires']  = time() + DV_OTP_EXPIRY;
    $d['otp_attempts'] = 0;
    $d['resend_count']++;

    $remaining = DV_MAX_RESEND - $d['resend_count'];
    $sent      = dv_send_email($d['email'], $otp);

    if (!$sent) {
        // DEV fallback
        error_log("=== DEV DASHBOARD RESEND OTP for [{$d['email']}]: {$otp} ===");
        $sent = true;
    }

    if ($sent) {
        echo json_encode([
            'success' => true,
            'message' => "New code sent to " . dv_mask_email($d['email']) . ". ({$remaining} resend(s) remaining.)",
            // ⚠️ DEV ONLY — remove dev_otp before going to production!
            'dev_otp' => $otp,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to resend code. Please try again.']);
    }
    exit;
}

// ── Unknown action ────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
