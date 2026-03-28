<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  login_otp.php — OTP Login Handler
//  Sends a 6-digit verification code to the user's registered email before
//  granting access to admin_dashboard.php.
// ═══════════════════════════════════════════════════════════════════════════════

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

const REQUIRE_OTP = true;    // OTP verification required before dashboard access

// ── SMTP config (same credentials as signup.php) ─────────────────────────────
define('LOT_SMTP_HOST',      'smtp.gmail.com');
define('LOT_SMTP_PORT',      587);
define('LOT_SMTP_USER',      'bunhs.deped@gmail.com');
define('LOT_SMTP_PASS',      'svhiovmxalojxzxg');
define('LOT_SMTP_FROM',      'bunhs.deped@gmail.com');
define('LOT_SMTP_FROM_NAME', 'Buyoan National High School');

ob_start();  // capture ANY stray output (warnings, notices, BOM) before JSON

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/db_connection.php';

ob_clean();  // discard everything captured above

header('Content-Type: application/json');

// ── send() — always clears buffer, always outputs clean JSON ──────────────────
function send(array $d): void
{
    if (ob_get_level()) ob_clean();
    echo json_encode($d);
    exit;
}

// ── CSRF guard ────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
$csrf   = $_POST['csrf_token'] ?? '';
$sess_csrf = $_SESSION['csrf_token'] ?? '';

if ($action === 'login_verify_credentials') {
    if (empty($sess_csrf) || empty($csrf) || !hash_equals($sess_csrf, $csrf)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        send(['success' => false, 'message' => 'Security validation failed. Please hard-refresh (Ctrl+Shift+R) and try again.']);
    }
}

// ── Send OTP via Gmail (PHPMailer preferred, native mail() fallback) ──────────
function sendLoginOTP(string $to, string $otp, string $name, string $role): array
{
    $role_label = $role === 'admin' ? 'Admin' : 'Sub-Admin';
    $safe_name  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $html_body = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;'>
      <div style='background:#1a3a2a;padding:28px 32px 20px;border-radius:12px 12px 0 0;text-align:center;'>
        <h2 style='color:#fff;margin:0;font-size:20px;'>Buyoan National High School</h2>
        <p style='color:rgba(255,255,255,.6);margin:4px 0 0;font-size:13px;'>{$role_label} Login Verification</p>
      </div>
      <div style='background:#fff;padding:32px;border:1px solid #e0e0e0;border-top:none;'>
        <p style='color:#333;font-size:14px;margin:0 0 18px;'>Hello <strong>{$safe_name}</strong>,</p>
        <p style='color:#555;font-size:14px;margin:0 0 20px;'>Your 6-digit login verification code is:</p>
        <div style='background:#f4faf7;border:2px solid #2d6a4f;border-radius:10px;padding:20px;text-align:center;margin-bottom:20px;'>
          <span style='font-size:36px;font-weight:800;letter-spacing:10px;color:#1a3a2a;'>{$otp}</span>
        </div>
        <p style='color:#888;font-size:12px;margin:0;'>This code expires in 5 minutes. Do not share it with anyone.</p>
      </div>
      <div style='background:#f8f5f0;padding:16px 32px;border-radius:0 0 12px 12px;text-align:center;'>
        <p style='color:#aaa;font-size:11px;margin:0;'>Buyoan National High School &bull; Official System</p>
      </div>
    </div>";

    // ── PATH A: PHPMailer via Gmail SMTP (optimized) ────────────────────────
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new PHPMailer(true);
        try {
            // Optimize SMTP connection for faster sending
            $mail->isSMTP();
            $mail->Host       = LOT_SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = LOT_SMTP_USER;
            $mail->Password   = LOT_SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = LOT_SMTP_PORT;
            
            // Add timeout and connection optimizations
            $mail->Timeout    = 10; // 10 second timeout
            $mail->SMTPKeepAlive = true; // Keep connection alive
            $mail->SMTPAutoTLS = true; // Auto TLS detection
            
            // Set from and recipient
            $mail->setFrom(LOT_SMTP_FROM, LOT_SMTP_FROM_NAME);
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = 'Your BUNHS Login Verification Code';
            $mail->Body    = $html_body;
            
            // Send with error handling
            $start_time = microtime(true);
            $result = $mail->send();
            $send_time = round((microtime(true) - $start_time) * 1000, 2);
            
            error_log("[login_otp] PHPMailer sent OTP to {$to} in {$send_time}ms");
            return ['success' => true, 'send_time' => $send_time];
            
        } catch (Exception $e) {
            $error_msg = $mail->ErrorInfo ?? $e->getMessage();
            error_log('[login_otp] PHPMailer SMTP error: ' . $error_msg . ' — falling back to mail()');
            // Fall through to native mail() below
        }
    } else {
        error_log("[login_otp] PHPMailer not installed — using native mail() fallback");
    }

    // ── PATH B: Native mail() fallback (optimized) ─────────────────────────────────
    $headers  = "From: Buyoan National High School <" . LOT_SMTP_FROM . ">\r\n";
    $headers .= "Reply-To: " . LOT_SMTP_FROM . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1\r\n"; // High priority for faster delivery

    // Track send time for native mail
    $start_time = microtime(true);
    $sent = @mail($to, 'Your BUNHS Login Verification Code', $html_body, $headers);
    $send_time = round((microtime(true) - $start_time) * 1000, 2);

    // ── Always log OTP with timing for debugging ────────────────────────────────────────
    // Find log at: C:\xampp\php\logs\php_error_log or C:\xampp\apache\logs\error.log
    error_log("[login_otp] OTP for {$to} ({$role_label}): {$otp} | mail()=" . ($sent ? 'queued in ' . $send_time . 'ms' : 'FAILED'));

    if ($sent) {
        return ['success' => true, 'method' => 'native_mail', 'send_time' => $send_time];
    }

    // ── PATH C: Both failed — return dev_otp so caller can include it in response ──
    error_log("[login_otp] Both PHPMailer and native mail() failed for {$to}");
    return ['success' => false, 'dev_otp' => $otp, 'error' => 'All email methods failed'];
}

// ── Column existence helper ───────────────────────────────────────────────────
function col_exists(mysqli $c, string $table, string $col): bool
{
    try {
        $r = $c->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
        return $r && $r->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

// ── Find admin table ─────────────────────────────────────────────────────────
// Check 'admin' BEFORE 'admins' — real data is in `admin` (has school_email).
// Both tables may exist; picking the wrong one causes "Unknown column" errors.
function find_admin_table(mysqli $c): ?string
{
    foreach (['admin', 'admins'] as $t) {
        try {
            $r = $c->query("SHOW TABLES LIKE '{$t}'");
            if ($r && $r->num_rows > 0) return $t;
        } catch (Throwable $e) { /* continue */
        }
    }
    return null;
}

// ── Find sub_admin table ─────────────────────────────────────────────────────
function find_subadmin_table(mysqli $c): ?string
{
    foreach (['sub_admin', 'sub-admin'] as $t) {
        try {
            $r = $c->query("SHOW TABLES LIKE '{$t}'");
            if ($r && $r->num_rows > 0) return $t;
        } catch (Throwable $e) { /* continue */
        }
    }
    return null;
}

// ── Fetch a user row from a table by username ─────────────────────────────────
function fetch_user(mysqli $c, string $table, string $username, string $extra_where = ''): ?array
{
    try {
        if (in_array($table, ['admin', 'admins', 'sub_admin', 'sub-admin'])) {
            // Admin tables always use standard username + password/password_hash
            $pw_col  = col_exists($c, $table, 'password_hash') ? 'password_hash' : 'password';
            // Detect whichever email column exists (admins uses school_email, sub_admin uses email)
            $em_col  = '';
            if (col_exists($c, $table, 'email'))        $em_col = ', email';
            elseif (col_exists($c, $table, 'school_email')) $em_col = ', school_email AS email';
            $ph_col  = col_exists($c, $table, 'phone')         ? ', phone'        : '';
            $where   = $extra_where ? "username = ? AND {$extra_where}" : 'username = ?';
            $stmt    = $c->prepare("SELECT id, `{$pw_col}` AS pw, username{$em_col}{$ph_col} FROM `{$table}` WHERE {$where} LIMIT 1");
        } else {
            // Student tables — detect actual column names dynamically
            $id_col   = null;
            $pw_col   = null;
            $user_col = null;
            foreach (['id', 'student_id', 'student_number', 'lrn'] as $col)
                if (!$id_col   && col_exists($c, $table, $col)) $id_col   = $col;
            foreach (['password_hash', 'password', 'pw'] as $col)
                if (!$pw_col   && col_exists($c, $table, $col)) $pw_col   = $col;
            foreach (['username', 'email', 'student_email'] as $col)
                if (!$user_col && col_exists($c, $table, $col)) $user_col = $col;

            if (!$id_col || !$pw_col || !$user_col) {
                error_log("[login_otp] Table `{$table}` missing required columns (id={$id_col}, pw={$pw_col}, user={$user_col})");
                return null;
            }
            $where = $extra_where ? "{$user_col} = ? AND {$extra_where}" : "{$user_col} = ?";
            $stmt  = $c->prepare("SELECT `{$id_col}` AS id, `{$pw_col}` AS pw, `{$user_col}` AS username FROM `{$table}` WHERE {$where} LIMIT 1");
        }

        if (!$stmt) return null;
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    } catch (Throwable $e) {
        error_log("[login_otp] fetch_user error on table `{$table}`: " . $e->getMessage());
        return null;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: login_verify_credentials
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'login_verify_credentials') {

    $start_time = microtime(true);
    
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        send(['success' => false, 'message' => 'Please enter both username and password.']);
    }

    $user      = null;
    $user_role = null;

    // 1. Admin table
    $admin_table = find_admin_table($conn);
    error_log("[login_otp] admin_table detected: " . ($admin_table ?? 'NONE'));
    if ($admin_table) {
        $row = fetch_user($conn, $admin_table, $username);
        error_log("[login_otp] admin row found: " . ($row ? 'YES (id=' . $row['id'] . ')' : 'NO'));
        if ($row && !empty($row['pw'])) {
            $pw_match = password_verify($password, $row['pw']);
            error_log("[login_otp] admin password_verify: " . ($pw_match ? 'MATCH' : 'FAIL') . " | hash_len=" . strlen($row['pw']));
            if ($pw_match) {
                $user      = $row;
                $user_role = 'admin';
            }
        }
    }

    // 2. Sub-admin table
    if (!$user) {
        $sa_table = find_subadmin_table($conn);
        error_log("[login_otp] subadmin_table detected: " . ($sa_table ?? 'NONE'));
        if ($sa_table) {
            $row = fetch_user($conn, $sa_table, $username, "status = 'approved'");
            error_log("[login_otp] subadmin row found: " . ($row ? 'YES' : 'NO'));
            if ($row) {
                error_log("[login_otp] subadmin password hash length: " . strlen($row['pw']));
                error_log("[login_otp] subadmin password empty check: " . (!empty($row['pw']) ? 'PASS' : 'FAIL'));
                $pw_verify = password_verify($password, $row['pw']);
                error_log("[login_otp] subadmin password_verify: " . ($pw_verify ? 'MATCH' : 'FAIL'));
                if ($row && !empty($row['pw']) && $pw_verify) {
                    $user      = $row;
                    $user_role = 'sub-admin';
                    error_log("[login_otp] sub-admin authentication SUCCESS");
                } else {
                    error_log("[login_otp] sub-admin authentication FAILED - conditions not met");
                }
            }
        }
    }

    // Student authentication has been removed

    error_log("[login_otp] final user_role: " . ($user_role ?? 'NULL — login failed'));

    if (!$user) {
        send(['success' => false, 'message' => 'Invalid username or password. Please try again.']);
    }

    // ── CREDENTIALS VALID ─────────────────────────────────────────────────────

    // ── OTP VERIFICATION REQUIRED ────────────────────────────────────────────
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    error_log("[login_otp] GENERATED OTP: {$otp} for username: {$username}, role: {$user_role}");

    $_SESSION['otp_pending'] = [
        'otp'       => password_hash($otp, PASSWORD_DEFAULT),
        'user_id'   => (int) $user['id'],
        'username'  => $user['username'],
        'user_type' => $user_role,
        'email'     => $user['email'] ?? '',
        'expires'   => time() + 300,
    ];

    // ── Get email — try all possible column names with direct queries ───────────
    $contact_email = $user['email'] ?? '';
    $display_name  = $user['username'];

    if (empty($contact_email)) {
        // Use a single UNION query to try all possible email column names at once.
        // This avoids SHOW COLUMNS (which can fail due to XAMPP permissions).
        $uid = (int) $user['id'];
        
        // For admin/sub-admin, check their respective tables
        $tbl = ($user_role === 'sub-admin') ? ($sa_table ?? $admin_table) : $admin_table;

        if ($tbl) {
            // Try each email column with try/catch — @ suppressor does NOT work
            // under XAMPP's strict mysqli error mode (MYSQLI_REPORT_STRICT).
            foreach (['school_email', 'email', 'admin_email', 'contact_email'] as $_c) {
                try {
                    $r = $conn->query("SELECT `{$_c}` AS em FROM `{$tbl}` WHERE id = {$uid} LIMIT 1");
                    if ($r && ($row_em = $r->fetch_assoc()) && !empty($row_em['em'])) {
                        $contact_email = $row_em['em'];
                        error_log("[login_otp] email found in `{$tbl}`.`{$_c}`: {$contact_email}");
                        break;
                    }
                } catch (Throwable $e) {
                    // Column doesn't exist — try the next one
                }
            }
        }
    }

    error_log("[login_otp] contact_email resolved: " . ($contact_email ?: 'NONE'));

    // Create masked email for response
    if (!empty($contact_email)) {
        [$u, $d] = explode('@', $contact_email, 2);
        $masked = substr($u, 0, 2) . str_repeat('*', max(1, strlen($u) - 2)) . '@' . $d;
    } else {
        $masked = 'your registered email';
    }

    // ── SEND OTP IMMEDIATELY (no delays) ───────────────────────────────────
    $mail_start = microtime(true);
    $mail_result = sendLoginOTP($contact_email, $otp, $display_name, $user_role);
    $mail_time = round((microtime(true) - $mail_start) * 1000, 2);
    
    error_log("[login_otp] OTP send completed for {$contact_email} in {$mail_time}ms");
    
    // ── RETURN RESPONSE ────────────────────────────────────────────────────────
    if ($mail_result['success']) {
        $response = [
            'success' => true,
            'message' => 'OTP sent to your registered email',
            'masked_contact' => $masked ?: 'your registered email',
            'send_time' => $mail_result['send_time'] ?? $mail_time,
            'method' => $mail_result['method'] ?? 'unknown'
        ];
    } else {
        // If email sending failed, return OTP for development
        $response = [
            'success' => false,
            'message' => 'Email service unavailable. Please use the code below.',
            'dev_otp' => $otp,
            'error' => $mail_result['error'] ?? 'Email sending failed',
            'send_time' => $mail_time
        ];
    }
    
    $total_time = round((microtime(true) - $start_time) * 1000, 2);
    error_log("[login_otp] Total OTP process time: {$total_time}ms for {$username}");
    
    send($response);
}

// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: login_verify_otp
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'login_verify_otp') {

    error_log("[login_otp] VERIFY ATTEMPT - Session exists: " . (isset($_SESSION['otp_pending']) ? 'YES' : 'NO'));
    
    if (!isset($_SESSION['otp_pending'])) {
        send(['success' => false, 'message' => 'Session expired. Please log in again.']);
    }

    $pending   = $_SESSION['otp_pending'];
    $otp_input = trim($_POST['otp'] ?? '');
    
    error_log("[login_otp] VERIFY - OTP input: '{$otp_input}', expires: " . date('Y-m-d H:i:s', $pending['expires']) . ", current: " . date('Y-m-d H:i:s'));

    if (time() > $pending['expires']) {
        error_log("[login_otp] VERIFY - OTP EXPIRED");
        unset($_SESSION['otp_pending']);
        send(['success' => false, 'message' => 'OTP expired. Please log in again.']);
    }

    if (!password_verify($otp_input, $pending['otp'])) {
        error_log("[login_otp] VERIFY - OTP MISMATCH - stored hash length: " . strlen($pending['otp']));
        send(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
    
    error_log("[login_otp] VERIFY - OTP SUCCESS for user: " . $pending['username']);

    session_regenerate_id(true);
    unset($_SESSION['otp_pending']);

    $_SESSION['user_id']    = $pending['user_id'];
    $_SESSION['username']   = $pending['username'];
    $_SESSION['user_type']  = $pending['user_type'];
    $_SESSION['login_time'] = time();

    if (in_array($pending['user_type'], ['admin', 'sub-admin'])) {
        $_SESSION['admin_id']       = $pending['user_id'];
        $_SESSION['admin_username'] = $pending['username'];
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    send(['success' => true, 'user_type' => $pending['user_type']]);
}

// ══════════════════════════════════════════════════════════════════════════════
//  ACTION: login_resend_otp
// ══════════════════════════════════════════════════════════════════════════════
if ($action === 'login_resend_otp') {
    if (!isset($_SESSION['otp_pending'])) {
        send(['success' => false, 'message' => 'Session expired. Please log in again.']);
    }
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp_pending']['otp']     = password_hash($otp, PASSWORD_DEFAULT);
    $_SESSION['otp_pending']['expires'] = time() + 300;
    $contact_email = $_SESSION['otp_pending']['email'] ?? '';
    $display_name  = $_SESSION['otp_pending']['username'] ?? 'User';
    $user_role_r   = $_SESSION['otp_pending']['user_type'] ?? 'admin';
    $uid_r         = $_SESSION['otp_pending']['user_id']  ?? 0;

    // If email not in session, look it up again from DB
    if (empty($contact_email) && $uid_r) {
        $r_table = in_array($user_role_r, ['admin']) ? find_admin_table($conn) : find_subadmin_table($conn);
        if ($r_table) {
            foreach (['school_email', 'email', 'admin_email'] as $_ecol) {
                try {
                    $eq = $conn->prepare("SELECT `{$_ecol}` AS em FROM `{$r_table}` WHERE id = ? LIMIT 1");
                    if ($eq) {
                        $eq->bind_param('i', $uid_r);
                        $eq->execute();
                        $er2 = $eq->get_result()->fetch_assoc();
                        $eq->close();
                        if (!empty($er2['em'])) {
                            $contact_email = $er2['em'];
                            break;
                        }
                    }
                } catch (Throwable $e) {
                    // Column doesn't exist — try next
                }
            }
        }
    }

    if (!empty($contact_email)) {
        sendLoginOTP($contact_email, $otp, $display_name, $user_role_r);
        [$u, $d] = explode('@', $contact_email, 2);
        $masked  = substr($u, 0, 2) . str_repeat('*', max(1, strlen($u) - 2)) . '@' . $d;
        // Update session with resolved email for future resends
        $_SESSION['otp_pending']['email'] = $contact_email;
    } else {
        error_log("[login_otp] Resend — no email found for '{$display_name}' — OTP: {$otp}");
        $masked = 'your registered email';
    }

    send(['success' => true, 'masked_contact' => $masked]);
}

send(['success' => false, 'message' => 'Unknown action.']);
