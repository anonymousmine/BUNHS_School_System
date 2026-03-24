<?php
session_start();
require_once '../db_connection.php';

$step = $_GET['step'] ?? 'email';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Security validation failed.';
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if email exists in admin table
            $stmt = $pdo->prepare("SELECT id FROM admin WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate 6-digit OTP
                $otp = random_int(100000, 999999);
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_user'] = $user['id'];
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_time'] = time();

                // Send email (using PHP mail - replace with SMTP in production)
                $subject = "BUNHS Password Reset OTP";
                $message = "Your password reset OTP is: " . str_pad($otp, 6, '0', STR_PAD_LEFT) . "\n\nThis code expires in 10 minutes.\nIf you didn't request this, ignore this email.";
                $headers = "From: noreply@bunhs-school.com\r\nReply-To: noreply@bunhs-school.com";

                if (mail($email, $subject, $message, $headers)) {
                    $success = 'OTP sent to your email. Check your inbox (and spam folder).';
                } else {
                    $error = 'Failed to send email. Please try again later.';
                }
            } else {
                $error = 'No account found with this email.';
                // Don't reveal if email exists (security)
                sleep(1);
            }
        }

        // Regenerate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Password Reset - BUNHS Admin</title>
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

        input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
        <p>Enter your admin email to receive a reset code</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <p style="text-align: center; font-size: 14px; color: #666;">
                Didn't receive it? <a href="?retry=1" style="color: #10b981;">Resend</a>
            </p>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label for="email">Admin Email</label>
                <input type="email" id="email" name="email" required
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    placeholder="admin@bunhs-school.com">
            </div>
            <button type="submit" class="btn">Send Reset Code</button>
        </form>

        <a href="../index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>
</body>

</html>