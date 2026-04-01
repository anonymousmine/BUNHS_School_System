<?php
require_once '../session_config.php';
require_once '../db_connection.php';

$error = '';
$success = '';
$show_form = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Security validation failed.';
    } else {
        $otp = trim($_POST['otp'] ?? '');
        $new_password = $_POST['new_password'] ?? '';

        if (
            !isset($_SESSION['reset_otp'], $_SESSION['reset_user'], $_SESSION['reset_time']) ||
            time() - $_SESSION['reset_time'] > 600
        ) { // 10 minutes
            $error = 'Session expired. Please request a new code.';
            unset($_SESSION['reset_otp'], $_SESSION['reset_user'], $_SESSION['reset_email'], $_SESSION['reset_time']);
        } elseif ($otp !== (string)$_SESSION['reset_otp']) {
            $error = 'Invalid verification code.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update admin password
            $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashed_password, $_SESSION['reset_user']);
            if ($stmt->execute()) {
                $success = 'Password reset successfully! You can now login with your new password.';
                unset($_SESSION['reset_otp'], $_SESSION['reset_user'], $_SESSION['reset_email'], $_SESSION['reset_time']);
                $show_form = false;

                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }

        // Regenerate CSRF token anyway
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Reset Password - BUNHS Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 420px;
            margin: 40px auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 32px;
        }

        .logo {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            margin: 0 auto 20px;
            display: block;
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            text-align: center;
            margin-bottom: 8px;
        }

        p {
            color: #666;
            text-align: center;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .otp-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin: 20px 0;
        }

        .otp-input {
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #059669;
        }

        .btn:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .resend-link {
            display: block;
            text-align: center;
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
            margin-top: 16px;
        }

        .back-link {
            display: inline-block;
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="card">
        <img src="../assets/img/logo.jpg" alt="BUNHS" class="logo">
        <h1>Reset Password</h1>
        <p>Enter the 6-digit code sent to your email and your new password</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$show_form): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Password updated successfully!
            </div>
            <p style="text-align: center;">
                <a href="../index.php" class="btn">Go to Login</a>
            </p>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-group">
                    <label>Verification Code</label>
                    <div class="otp-grid">
                        <input type="text" name="otp" class="otp-input" maxlength="6" inputmode="numeric" pattern="[0-9]*" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="8">
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="8">
                </div>

                <button type="submit" class="btn">Reset Password</button>
            </form>

            <a href="forgot_password.php" class="resend-link">
                <i class="fas fa-redo"></i> Didn't receive code? Resend
            </a>
            <a href="../index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        <?php endif; ?>
    </div>
</body>

</html>