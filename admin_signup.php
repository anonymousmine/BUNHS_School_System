<?php
// ═══════════════════════════════════════════════════════════════════════════════════
//  admin_signup.php — Sub-Admin Registration Page
//  This page allows admins to create new sub-admin accounts
// ═════════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/session_config.php';

// Only admins can access this page
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
}

include __DIR__ . '/db_connection.php';

$signup_error = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $signup_error = 'Security validation failed. Please try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        // Validation
        if (empty($full_name) || empty($username) || empty($email) || empty($role) || empty($password)) {
            $signup_error = 'All fields are required.';
        } elseif (strlen($username) < 3) {
            $signup_error = 'Username must be at least 3 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signup_error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $signup_error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm_password) {
            $signup_error = 'Passwords do not match.';
        } elseif (!in_array($role, ['news_admin', 'announcement_admin', 'student_admin', 'teacher_admin', 'club_admin', 'forms_admin'])) {
            $signup_error = 'Invalid role selected.';
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM sub_admin WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $signup_error = 'Username already exists.';
            } else {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM sub_admin WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $signup_error = 'Email already exists.';
                } else {
                    // Create sub-admin account
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO sub_admin (username, password_hash, email, role, status, created_at) VALUES (?, ?, ?, ?, 'approved', NOW())");
                    $stmt->bind_param('ssss', $username, $password_hash, $email, $role);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Sub-admin account created successfully! Username: ' . htmlspecialchars($username);
                        // Clear form
                        $_POST = [];
                    } else {
                        $signup_error = 'Failed to create account. Please try again.';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Get role options
$role_options = [
    'news_admin' => 'News Admin',
    'announcement_admin' => 'Announcement Admin',
    'student_admin' => 'Student Admin',
    'teacher_admin' => 'Teacher Admin',
    'club_admin' => 'Club Admin',
    'forms_admin' => 'Forms Admin',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Create Sub-Admin - Buyoan National High School</title>
    <meta name="description" content="Create sub-admin account for Buyoan National High School">
    
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/img/logo.jpg" type="image/x-icon">

    <style>
        .admin-signup-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a3a2a 0%, #2d6a4f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Roboto', sans-serif;
        }
        .admin-signup-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 48px;
            width: 100%;
            max-width: 520px;
            text-align: center;
        }
        .admin-signup-header {
            margin-bottom: 32px;
        }
        .admin-signup-header img {
            width: 80px;
            height: auto;
            margin-bottom: 16px;
        }
        .admin-signup-header h2 {
            color: #1a3a2a;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }
        .admin-signup-header p {
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
        .admin-btn-signup {
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
        .admin-btn-signup:hover {
            background: #1a3a2a;
        }
        .admin-success-message {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .admin-error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .admin-back-link {
            display: inline-block;
            margin-top: 20px;
            color: #6c757d;
            text-decoration: none;
            font-size: 14px;
        }
        .admin-back-link:hover {
            color: #2d6a4f;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="admin-signup-container">
        <div class="admin-signup-card">
            <div class="admin-signup-header">
                <img src="assets/img/logo.jpg" alt="School Logo">
                <h2>Create Sub-Admin Account</h2>
                <p>Add a new administrator to the system</p>
            </div>

            <?php if ($success_message): ?>
                <div class="admin-success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($signup_error): ?>
                <div class="admin-error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($signup_error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form id="signupForm" method="POST" action="admin_signup.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="admin-form-group">
                    <label for="full_name" class="admin-form-label">Full Name</label>
                    <input type="text" class="admin-form-control" id="full_name" name="full_name" required>
                </div>

                <div class="admin-form-group">
                    <label for="username" class="admin-form-label">Username</label>
                    <input type="text" class="admin-form-control" id="username" name="username" required>
                </div>

                <div class="admin-form-group">
                    <label for="email" class="admin-form-label">Email</label>
                    <input type="email" class="admin-form-control" id="email" name="email" required>
                </div>

                <div class="admin-form-group">
                    <label for="role" class="admin-form-label">Role</label>
                    <select class="admin-form-control" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <?php foreach ($role_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="admin-form-group">
                    <label for="password" class="admin-form-label">Password</label>
                    <input type="password" class="admin-form-control" id="password" name="password" required>
                </div>

                <div class="admin-form-group">
                    <label for="confirm_password" class="admin-form-label">Confirm Password</label>
                    <input type="password" class="admin-form-control" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="admin-btn-signup">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <a href="admin_dashboard.php" class="admin-back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        // Clear error messages on input
        document.querySelectorAll('.admin-form-control').forEach(input => {
            input.addEventListener('input', function() {
                document.querySelector('.admin-error-message').style.display = 'none';
                document.querySelector('.admin-success-message').style.display = 'none';
            });
        });
    </script>
</body>
</html>
