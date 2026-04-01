<?php
// ═══════════════════════════════════════════════════════════════════════════════════
//  admin_login.php — Admin/Sub-Admin Only Login Page
//  This replaces the mixed login.php with admin/sub-admin specific login
// ═══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/session_config.php';

// Redirect already-authenticated admins/sub-admins
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
            // Check admin table first
            $admin = null;
            $stmt = $conn->prepare("SELECT id, password_hash, school_email FROM admin WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // If not found in admin, check sub_admin
            if (!$admin) {
                $stmt = $conn->prepare("SELECT id, password_hash, email FROM sub_admin WHERE username = ? AND status = 'approved' LIMIT 1");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $admin = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if ($admin && password_verify($password, $admin['password_hash'])) {
                // Password correct - set session
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $username;
                $_SESSION['user_type'] = isset($admin['email']) ? 'sub-admin' : 'admin';
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $username;
                $_SESSION['login_time'] = time();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Redirect to admin dashboard
                header('Location: admin_account/admin_dashboard.php');
                exit;
            } else {
                $login_error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Admin Login - Buyoan National High School</title>
    <meta name="description" content="Admin login for Buyoan National High School">
    
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/img/logo.jpg" type="image/x-icon">

    <style>
        .admin-login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a3a2a 0%, #2d6a4f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Roboto', sans-serif;
        }
        .admin-login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 48px;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .admin-login-header {
            margin-bottom: 32px;
        }
        .admin-login-header img {
            width: 80px;
            height: auto;
            margin-bottom: 16px;
        }
        .admin-login-header h2 {
            color: #1a3a2a;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        .admin-login-header p {
            color: #6c757d;
            font-size: 14px;
            margin: 8px 0 0 0;
        }
        .admin-form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .admin-form-label {
            display: block;
            color: #1a3a2a;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .admin-form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        .admin-form-control:focus {
            border-color: #2d6a4f;
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.1);
        }
        .admin-btn-login {
            width: 100%;
            padding: 14px;
            background: #2d6a4f;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .admin-btn-login:hover {
            background: #1a3a2a;
        }
        .admin-error-message {
            display: none;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .admin-forgot-link {
            display: block;
            margin-top: 20px;
            color: #6c757d;
            text-decoration: none;
            font-size: 14px;
        }
        .admin-forgot-link:hover {
            color: #2d6a4f;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="admin-login-container">
        <div class="admin-login-card">
            <div class="admin-login-header">
                <img src="assets/img/logo.jpg" alt="School Logo">
                <h2>Admin Login</h2>
                <p>Administrator & Sub-Admin Access</p>
            </div>

            <form id="loginForm" method="POST" action="admin_login.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <?php if ($login_error): ?>
                    <div class="admin-error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="admin-form-group">
                    <label for="username" class="admin-form-label">Username</label>
                    <input type="text" class="admin-form-control" id="username" name="username" required>
                </div>

                <div class="admin-form-group">
                    <label for="password" class="admin-form-label">Password</label>
                    <input type="password" class="admin-form-control" id="password" name="password" required>
                </div>

                <button type="submit" class="admin-btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <a href="admin_account/forgot_password.php" class="admin-forgot-link">
                Forgot your password?
            </a>
        </div>
    </div>

    <script>
        // Clear any existing error messages on input
        document.getElementById('username').addEventListener('input', function() {
            document.querySelector('.admin-error-message').style.display = 'none';
        });
        document.getElementById('password').addEventListener('input', function() {
            document.querySelector('.admin-error-message').style.display = 'none';
        });
    </script>
</body>
</html>
