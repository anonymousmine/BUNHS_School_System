<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  login.php — CACHED VERSION
//  Admin-only login page.  Replace the existing login.php with this file.
//  Only the PHP handler block changes; the HTML below it is untouched.
// ═══════════════════════════════════════════════════════════════════════════════

// Use shared session config so cookie settings match all admin pages
require_once __DIR__ . '/session_config.php';

// Load caching layer — must come BEFORE any credential lookup
require_once __DIR__ . '/cache_helper.php';

// Redirect already-authenticated admins (handles both session formats)
if (
    isset($_SESSION['admin_id'])
    || (isset($_SESSION['user_id']) && in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']))
) {
    header('Location: admin_account/admin_dashboard.php');
    exit;
}

$login_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $login_error = 'Security validation failed. Please try again.';
        sleep(1);
    } else {
        include __DIR__ . '/db_connection.php';   // provides $conn (mysqli)

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $login_error = 'Please enter both username and password.';
        } else {
            $admin     = null;
            $cache_key = "admin:{$username}";

            // ── 1. Try cache first ────────────────────────────────────────────────
            $cached = cache_get($cache_key);

            if ($cached !== false) {
                $admin = $cached;
            } else {
                // ── CACHE MISS — detect the real table & password column ──────────
                // Check 'admin' BEFORE 'admins' — real data is in `admin` table.
                $admin_table = null;
                foreach (['admin', 'admins'] as $_t) {
                    try {
                        $tc = $conn->query("SHOW TABLES LIKE '{$_t}'");
                        if ($tc && $tc->num_rows > 0) {
                            $admin_table = $_t;
                            break;
                        }
                    } catch (Throwable $e) { /* continue */
                    }
                }

                if ($admin_table) {
                    // Detect password column
                    $pw_col = 'password';
                    try {
                        $pw_check = $conn->query("SHOW COLUMNS FROM `{$admin_table}` LIKE 'password_hash'");
                        if ($pw_check && $pw_check->num_rows > 0) $pw_col = 'password_hash';
                    } catch (Throwable $e) { /* default to 'password' */
                    }

                    $stmt = $conn->prepare(
                        "SELECT id, {$pw_col} AS password FROM `{$admin_table}` WHERE username = ? LIMIT 1"
                    );
                    // Fetch id, password, and email for admin or sub_admin
                    if ($admin_table === 'admin') {
                        $stmt = $conn->prepare(
                            "SELECT id, {$pw_col} AS password, school_email AS email 
         FROM `admin` WHERE username = ? LIMIT 1"
                        );
                    } else {
                        $stmt = $conn->prepare(
                            "SELECT id, {$pw_col} AS password, email 
         FROM `sub_admin` WHERE username = ? LIMIT 1"
                        );
                    }

                    if ($stmt) {
                        $stmt->bind_param('s', $username);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($row && !empty($row['password'])) {
                            $admin = $row;
                            cache_set($cache_key, $admin, CACHE_TTL_CREDENTIALS);
                        }
                    }
                }
            }

            // ── 2. Verify password — ALWAYS runs, even on cache hit ──────────────
            if ($admin && password_verify($password, $admin['password'])) {
                // Generate OTP
                $otp = random_int(100000, 999999);

                // Store OTP and user info temporarily
                $_SESSION['pending_otp']   = $otp;
                $_SESSION['pending_user']  = [
                    'id'       => $admin['id'],
                    'username' => $username,
                    'email'    => $admin['email'] // comes from school_email (admin) or email (sub_admin)
                ];

                // Send OTP to email
                $to      = $admin['email'];
                $subject = "Your Admin Login Verification Code";
                $message = "Hello {$username},\n\nYour verification code is: {$otp}\n\n";
                $headers = "From: no-reply@bunhs.edu.ph";

                mail($to, $subject, $message, $headers);

                // Redirect to OTP verification page
                header('Location: veifrify_otp.php');
                exit;
            } else {
                $login_error = 'Invalid username or password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Login - Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="assets/img/logo.jpg" alt="School Logo">
                <h2>Admin Login</h2>
                <p>Please sign in to access the school management system</p>
            </div>

            <form id="loginForm" method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">


                <?php if ($login_error): ?>
                    <div class="alert alert-danger" style="
                        background:#fdf1f1;color:#b94040;border:1px solid #f0d5d5;
                        border-radius:8px;padding:11px 15px;margin-bottom:16px;
                        font-size:13.5px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                    <div class="error-message" id="usernameError">Please enter a valid username</div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="error-message" id="passwordError">Please enter your password</div>
                </div>

                <button type="submit" class="btn btn-login">Sign In</button>
            </form>

            <div class="forgot-password">
                <a href="admin_account/forgot_password.php">Forgot your password?</a>
            </div>

            <div class="signup-link">
                <p>Don't have an account? <a href="signup.html">Sign up here</a></p>
            </div>
        </div>
    </div>

    <!-- Vendor JS Files (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            let isValid = true;

            document.getElementById('usernameError').style.display = 'none';
            document.getElementById('passwordError').style.display = 'none';

            if (!username) {
                document.getElementById('usernameError').style.display = 'block';
                isValid = false;
            }
            if (!password) {
                document.getElementById('passwordError').style.display = 'block';
                isValid = false;
            }

            if (!isValid) e.preventDefault();
        });
    </script>
</body>

</html>