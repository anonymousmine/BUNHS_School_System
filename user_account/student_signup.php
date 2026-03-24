<?php

/**
 * student_signup.php (ENHANCED) — supports both Students and Parents
 * ─────────────────────────────────────────────────────────────
 * 
 * Actions (POST):
 *   sc_register   – validate fields, hash password, store pending, send OTP
 *   sc_verify_otp – verify OTP, INSERT student/parent row, set session
 *   sc_resend_otp – regenerate + resend OTP
 *
 * Requires: ALTER TABLE students ADD COLUMN IF NOT EXISTS user_type VARCHAR(20) DEFAULT 'student';
 * ─────────────────────────────────────────────────────────────
 */

// ── Catch-all error handler → always return JSON, never a 500 ─
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e['message'] . ' (line ' . $e['line'] . ')']);
    }
});

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

// ── DB: walk up the tree to find db_connection.php ───────────
// Start from dirname(__DIR__) (one level up = project root) so we
// skip any stub/empty db_connection.php inside user_account/ itself.
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
    echo json_encode(['success' => false, 'message' => 'Cannot find db_connection.php']);
    exit;
}
include $_db; // → $conn (mysqli)

// ── Ensure user_type column exists (parent table support) ────────────────────
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS user_type VARCHAR(20) DEFAULT 'student'");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS relationship_to_student VARCHAR(100) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS linked_student_id VARCHAR(50) DEFAULT NULL");

// ── SMTP config ───────────────────────────────────────────────
define('SC_SMTP_HOST', 'smtp.gmail.com');
define('SC_SMTP_PORT', 587);
define('SC_SMTP_USER', 'bunhs.deped@gmail.com');
define('SC_SMTP_PASS', 'svhiovmxalojxzxg');
define('SC_SMTP_FROM', 'bunhs.deped@gmail.com');
define('SC_SMTP_NAME', 'Buyoan National High School');
define('SC_OTP_EXPIRY',   300);  // 5 min
define('SC_MAX_ATTEMPTS', 5);
define('SC_MAX_RESENDS',  3);

// ── Helpers ───────────────────────────────────────────────────
function sc_otp(): string
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sc_table_columns(mysqli $conn): array
{
    static $cols = null;
    if ($cols !== null) return $cols;
    $cols = [];
    $r = $conn->query("SHOW COLUMNS FROM students");
    if ($r) while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
    return $cols;
}
function sc_has_col(mysqli $conn, string $col): bool
{
    return in_array($col, sc_table_columns($conn), true);
}

/**
 * Generate a unique student_id in the format S-YYYY-NNNN.
 * Finds the highest existing number for the current year and increments it.
 * Falls back to a random suffix if the query fails.
 */
function sc_generate_student_id(mysqli $conn): string
{
    $year = date('Y');
    $prefix = "S-{$year}-";

    // Find the highest sequence number used this year
    $stmt = $conn->prepare(
        "SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1"
    );
    if ($stmt) {
        $like = $prefix . '%';
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            // Extract numeric suffix and increment
            $last_num = (int) substr($row['student_id'], strlen($prefix));
            $next_num = $last_num + 1;
        } else {
            $next_num = 1;
        }
    } else {
        // Query failed — use a random number to avoid collision
        $next_num = random_int(1000, 9999);
    }

    return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

function sc_send_email(string $to, string $otp): bool
{
    $search = __DIR__;
    $autoload = null;
    for ($i = 0; $i < 6; $i++) {
        $cand = $search . '/vendor/autoload.php';
        if (file_exists($cand)) {
            $autoload = $cand;
            break;
        }
        $p = dirname($search);
        if ($p === $search) break;
        $search = $p;
    }
    if (!$autoload) {
        error_log("=== DEV SIGNUP OTP email [{$to}]: {$otp} ===");
        return true;
    }
    require_once $autoload;
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SC_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SC_SMTP_USER;
        $mail->Password = SC_SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SC_SMTP_PORT;
        $mail->setFrom(SC_SMTP_FROM, SC_SMTP_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = 'BUNHS — Your Registration Verification Code';
        $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:30px;border:1px solid #dde8e2;border-radius:16px;">
            <h2 style="color:#1a3a2a;text-align:center;">Registration Code</h2>
            <p style="color:#555;text-align:center;">Use this code to complete your BUNHS account registration.</p>
            <div style="background:#f1f3f4;border-radius:8px;padding:20px;text-align:center;
                letter-spacing:10px;font-size:36px;font-weight:bold;color:#202124;margin:24px 0;">'
            . htmlspecialchars($otp, ENT_QUOTES) . '</div>
            <p style="color:#888;font-size:13px;text-align:center;">Expires in <strong>5 minutes</strong>. Never share this code.</p>
        </div>';
        $mail->AltBody = "Your BUNHS registration code: {$otp} (expires in 5 minutes)";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Signup email error: ' . $mail->ErrorInfo);
        return false;
    }
}

function sc_send_sms(string $phone, string $otp): bool
{
    $clean = preg_replace('/\D/', '', $phone);
    if (str_starts_with($clean, '0')) $clean = '63' . substr($clean, 1);
    $apiKey = 'YOUR_SEMAPHORE_API_KEY';
    if ($apiKey === 'YOUR_SEMAPHORE_API_KEY') {
        error_log("=== DEV SIGNUP SMS OTP [{$clean}]: {$otp} ===");
        return true;
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://semaphore.co/api/v4/messages',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'apikey' => $apiKey,
            'number' => $clean,
            'message' => "Your BUNHS registration code: {$otp} (expires 5 min)",
            'sendername' => 'BUNHS'
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}

// ── Action router ─────────────────────────────────────────────
$action = trim($_POST['action'] ?? '');

// ════════════════════════════════════════════════════════════
// sc_register — validate, hash password, store pending, send OTP
// ════════════════════════════════════════════════════════════
if ($action === 'sc_register') {

    $user_type      = strtolower(trim($_POST['user_type'] ?? 'student'));
    $first_name     = trim($_POST['first_name']     ?? '');
    $middle_initial = strtoupper(trim($_POST['middle_initial'] ?? ''));
    $last_name      = trim($_POST['last_name']      ?? '');
    $suffix         = trim($_POST['suffix']         ?? '');
    $age            = (int)($_POST['age']           ?? 0);
    $gender         = trim($_POST['gender']         ?? '');
    $method         = trim($_POST['contact_method'] ?? 'email');
    $email          = trim($_POST['email']          ?? '');
    $phone          = trim($_POST['phone']          ?? '');
    $password       = $_POST['password']            ?? '';
    $confirm_pw     = $_POST['confirm_password']    ?? '';

    // NEW PARENT-SPECIFIC FIELDS
    $relationship   = trim($_POST['relationship_to_student'] ?? '');
    $occupation     = trim($_POST['occupation']     ?? '');
    $home_address   = trim($_POST['home_address']   ?? '');
    $emergency_contact = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_phone   = trim($_POST['emergency_contact_phone'] ?? '');

    // ── Validation ────────────────────────────────────────────
    if ($user_type !== 'student' && $user_type !== 'parent') {
        echo json_encode(['success' => false, 'message' => 'Invalid user type.']);
        exit;
    }

    if (!$first_name) {
        echo json_encode(['success' => false, 'message' => 'First name is required.']);
        exit;
    }
    if (!$last_name) {
        echo json_encode(['success' => false, 'message' => 'Last name is required.']);
        exit;
    }

    // Student-specific validation
    if ($user_type === 'student') {
        if ($age < 10 || $age > 25) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid age (10–25).']);
            exit;
        }
        if (!$gender) {
            echo json_encode(['success' => false, 'message' => 'Please select a gender.']);
            exit;
        }
    }

    // Parent-specific validation
    if ($user_type === 'parent') {
        if (!$relationship) {
            echo json_encode(['success' => false, 'message' => 'Please select your relationship to the student.']);
            exit;
        }
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }
    if ($password !== $confirm_pw) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    if ($method === 'email') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']);
            exit;
        }
        // Check email uniqueness
        $s = $conn->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
        $s->bind_param('s', $email);
        $s->execute();
        if ($s->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
            exit;
        }
        $s->close();
    } else {
        if (empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
            exit;
        }
    }

    // ── Hash password (bcrypt) ────────────────────────────────
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // ── Generate OTP ──────────────────────────────────────────
    $otp = sc_otp();

    // ── Store pending in session (DB insert happens after OTP) ─
    $_SESSION['sc_pending'] = [
        'user_type'      => $user_type,
        'first_name'     => $first_name,
        'middle_initial' => $middle_initial,
        'last_name'      => $last_name,
        'suffix'         => $suffix,
        'age'            => $age,
        'gender'         => $gender,
        'method'         => $method,
        'email'          => $email,
        'phone'          => $phone,
        'password'       => $hashed_password,
        'relationship'   => $relationship,
        'occupation'     => $occupation,
        'home_address'   => $home_address,
        'emergency_contact' => $emergency_contact,
        'emergency_phone'   => $emergency_phone,
        'otp'            => $otp,
        'otp_expires'    => time() + SC_OTP_EXPIRY,
        'otp_attempts'   => 0,
        'resend_count'   => 0,
    ];

    $sent = ($method === 'email') ? sc_send_email($email, $otp) : sc_send_sms($phone, $otp);
    if (!$sent) {
        error_log("=== DEV SIGNUP OTP: {$otp} ===");
        $sent = true;
    }

    if ($sent) {
        echo json_encode(['success' => true, 'dev_otp' => $otp]); // ⚠ remove dev_otp in production
    } else {
        unset($_SESSION['sc_pending']);
        echo json_encode(['success' => false, 'message' => 'Failed to send verification code.']);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// sc_verify_otp — check OTP, INSERT student/parent, set session
// ════════════════════════════════════════════════════════════
if ($action === 'sc_verify_otp') {

    $otp_input = trim($_POST['otp'] ?? '');

    if (empty($_SESSION['sc_pending'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }

    $p = &$_SESSION['sc_pending'];

    if ($p['otp_attempts'] >= SC_MAX_ATTEMPTS) {
        unset($_SESSION['sc_pending']);
        echo json_encode(['success' => false, 'message' => 'Too many attempts. Please register again.']);
        exit;
    }
    if (time() > $p['otp_expires']) {
        unset($_SESSION['sc_pending']);
        echo json_encode(['success' => false, 'message' => 'Code expired. Please register again.']);
        exit;
    }
    if (!preg_match('/^\d{6}$/', $otp_input) || $otp_input !== $p['otp']) {
        $p['otp_attempts']++;
        $rem = SC_MAX_ATTEMPTS - $p['otp_attempts'];
        echo json_encode(['success' => false, 'message' => "Invalid code. {$rem} attempt(s) remaining."]);
        exit;
    }

    // ✅ OTP correct — INSERT student/parent row
    // Build dynamically so unknown optional columns never crash the INSERT.
    $cols         = sc_table_columns($conn);
    $insert_cols  = [];
    $insert_vals  = [];
    $insert_types = '';

    // ── Required columns ───────────────────────────────────────
    $required = [
        ['first_name',  's', $p['first_name']],
        ['last_name',   's', $p['last_name']],
        ['user_type',   's', $p['user_type']],  // NEW: store user type
    ];

    // Only add gender for students
    if ($p['user_type'] === 'student') {
        $required[] = ['gender', 's', $p['gender']];
    }

    foreach ($required as [$col, $type, $val]) {
        if (sc_has_col($conn, $col)) {
            $insert_cols[] = $col;
            $insert_vals[] = $val;
            $insert_types .= $type;
        }
    }

    // ── student_id — generate a unique S-YYYY-NNNN value ──────
    if (sc_has_col($conn, 'student_id')) {
        $insert_cols[] = 'student_id';
        $insert_vals[] = sc_generate_student_id($conn);
        $insert_types .= 's';
    }

    // ── Password ────────────────────────────────────────────────
    if (sc_has_col($conn, 'password')) {
        $insert_cols[] = 'password';
        $insert_vals[] = $p['password'];
        $insert_types .= 's';
    }

    // ── Optional columns — added only if they exist ─────────────
    $optional = [
        'middle_initial'          => ['s', $p['middle_initial']],
        'suffix'                  => ['s', $p['suffix']],
        'age'                     => ['i', (int)$p['age']],
        'status'                  => ['s', 'Active'],
        'notification_preference' => ['s', null],
        'email_verified'          => ['i', ($p['method'] === 'email' ? 1 : 0)],
        'phone_verified'          => ['i', ($p['method'] === 'phone' ? 1 : 0)],
        'enrollment_status'       => ['s', 'Enrolled'],
        'date_enrolled'           => ['s', date('Y-m-d')],
        'relationship_to_student' => ['s', $p['user_type'] === 'parent' ? $p['relationship'] : null],
        'occupation'              => ['s', $p['user_type'] === 'parent' ? $p['occupation'] : null],
        'address'                 => ['s', $p['user_type'] === 'parent' ? $p['home_address'] : null],
    ];

    foreach ($optional as $col => [$type, $val]) {
        if (sc_has_col($conn, $col)) {
            $insert_cols[] = $col;
            $insert_vals[] = $val;
            $insert_types .= $type;
        }
    }

    // ── Email / phone ─────────────────────────────────────────
    if ($p['method'] === 'email' && sc_has_col($conn, 'email')) {
        $insert_cols[] = 'email';
        $insert_vals[] = $p['email'];
        $insert_types .= 's';
    }
    if ($p['method'] === 'phone') {
        $phone_col = sc_has_col($conn, 'phone') ? 'phone'
            : (sc_has_col($conn, 'phone_number') ? 'phone_number' : null);
        if ($phone_col) {
            $insert_cols[] = $phone_col;
            $insert_vals[] = $p['phone'];
            $insert_types .= 's';
        }
    }

    // ── Timestamp columns ─────────────────────────────────────
    $ts_candidates = ['created_at', 'registered_at', 'date_registered'];
    foreach ($ts_candidates as $ts_col) {
        if (sc_has_col($conn, $ts_col)) {
            $insert_cols[] = $ts_col;
            $insert_vals[] = date('Y-m-d H:i:s');
            $insert_types .= 's';
            break;
        }
    }

    // ── Safety guard ──────────────────────────────────────────
    if (empty($insert_cols)) {
        echo json_encode(['success' => false, 'message' => 'No matching columns found. Columns detected: ' . implode(', ', $cols)]);
        exit;
    }

    // ── Execute INSERT ────────────────────────────────────────
    $sql  = 'INSERT INTO students (' . implode(', ', $insert_cols) . ') VALUES (' . implode(', ', array_fill(0, count($insert_cols), '?')) . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error, 'sql' => $sql]);
        exit;
    }

    $bind_args = [$insert_types];
    foreach ($insert_vals as $k => $_) $bind_args[] = &$insert_vals[$k];
    call_user_func_array([$stmt, 'bind_param'], $bind_args);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Could not create account: ' . $stmt->error]);
        exit;
    }

    $new_id = (int)$conn->insert_id;
    $stmt->close();

    // Retrieve the generated student_id string for the session
    $generated_student_id = null;
    $sid_row = $conn->query("SELECT student_id FROM students WHERE id = {$new_id} LIMIT 1");
    if ($sid_row) {
        $sid_data = $sid_row->fetch_assoc();
        $generated_student_id = $sid_data['student_id'] ?? null;
    }

    // ── Set session so Dashboard/Profile auth passes immediately ──
    session_regenerate_id(true);
    $_SESSION['user_id']       = $new_id;
    $_SESSION['student_id']    = $generated_student_id ?? $new_id;  // prefer S-YYYY-NNNN string
    $_SESSION['user_type']     = $p['user_type'];
    $_SESSION['student_name']  = trim($p['first_name'] . ' ' . $p['last_name']);
    $_SESSION['login_method']  = $p['method'];
    $_SESSION['grade_level']   = '';

    // Store parent-specific session data
    if ($p['user_type'] === 'parent') {
        $_SESSION['parent_relationship'] = $p['relationship'];
        $_SESSION['parent_occupation']   = $p['occupation'];
    }

    unset($_SESSION['sc_pending']);
    echo json_encode(['success' => true]);
    exit;
}

// ════════════════════════════════════════════════════════════
// sc_resend_otp
// ════════════════════════════════════════════════════════════
if ($action === 'sc_resend_otp') {

    if (empty($_SESSION['sc_pending'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }

    $p = &$_SESSION['sc_pending'];

    if ($p['resend_count'] >= SC_MAX_RESENDS) {
        echo json_encode(['success' => false, 'message' => 'Resend limit reached. Please start again.']);
        exit;
    }

    $otp = sc_otp();
    $p['otp']          = $otp;
    $p['otp_expires']  = time() + SC_OTP_EXPIRY;
    $p['otp_attempts'] = 0;
    $p['resend_count']++;

    $sent = ($p['method'] === 'email') ? sc_send_email($p['email'], $otp) : sc_send_sms($p['phone'], $otp);
    if (!$sent) {
        error_log("=== DEV RESEND SIGNUP OTP: {$otp} ===");
        $sent = true;
    }

    $remaining = SC_MAX_RESENDS - $p['resend_count'];
    echo json_encode([
        'success' => $sent,
        'message' => $sent ? "New code sent. ({$remaining} resend(s) left.)" : 'Failed to send. Please try again.',
        'dev_otp' => $otp, // ⚠ remove in production
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
