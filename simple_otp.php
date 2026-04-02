<?php
session_start();

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $entered_otp = trim($_POST['otp']);
    
    if (isset($_SESSION['pending_otp']) && $entered_otp == $_SESSION['pending_otp']) {
        // OTP correct - set session and redirect
        $pending_user = $_SESSION['pending_user'];
        
        $_SESSION['user_id'] = $pending_user['id'];
        $_SESSION['username'] = $pending_user['username'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_id'] = $pending_user['id'];
        $_SESSION['admin_username'] = $pending_user['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // Clear pending OTP
        unset($_SESSION['pending_otp']);
        unset($_SESSION['pending_user']);
        
        header('Location: admin_account/admin_dashboard.php');
        exit;
    } else {
        $error = 'Invalid OTP. Please try again.';
    }
}

// Check if OTP exists
if (!isset($_SESSION['pending_otp'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .otp-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .otp-title {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .otp-subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .otp-input {
            font-size: 24px;
            text-align: center;
            letter-spacing: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            transition: border-color 0.3s;
        }
        .otp-input:focus {
            border-color: #667eea;
            outline: none;
        }
        .verify-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .verify-btn:hover {
            transform: translateY(-2px);
        }
        .otp-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .otp-display {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <h2 class="otp-title">🔐 Verification Required</h2>
        <p class="otp-subtitle">Enter the 6-digit code sent to your email</p>
        
        <div class="otp-info">
            <strong>For Testing:</strong><br>
            Your OTP code is:<br>
            <div class="otp-display"><?php echo $_SESSION['pending_otp']; ?></div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" 
                   name="otp" 
                   class="otp-input" 
                   placeholder="000000" 
                   maxlength="6" 
                   pattern="[0-9]{6}" 
                   required 
                   autocomplete="off">
            <br>
            <button type="submit" class="verify-btn">Verify OTP</button>
        </form>
        
        <p style="margin-top: 20px; font-size: 14px;">
            <a href="index.php" style="color: #667eea;">← Back to Login</a>
        </p>
    </div>
    
    <script>
        // Auto-focus OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.querySelector('.otp-input');
            otpInput.focus();
            
            // Only allow numbers
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        });
    </script>
</body>
</html>
