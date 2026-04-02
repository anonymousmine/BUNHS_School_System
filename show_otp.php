<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>OTP for Testing</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .otp-display { 
            background: #f0f8ff; 
            padding: 20px; 
            border-radius: 8px; 
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #0066cc;
            margin: 20px 0;
        }
        .info { background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔐 OTP Verification - Testing Helper</h1>
    
    <div class="info">
        <strong>For Development Only:</strong> This page shows the current OTP for testing since email sending is disabled.
    </div>
    
    <?php if (isset($_SESSION['pending_otp'])): ?>
        <div class="otp-display">
            Your OTP is: <?php echo $_SESSION['pending_otp']; ?>
        </div>
        
        <div class="info">
            <strong>User Info:</strong><br>
            Username: <?php echo htmlspecialchars($_SESSION['pending_user']['username']); ?><br>
            Email: <?php echo htmlspecialchars($_SESSION['pending_user']['email']); ?><br>
            User ID: <?php echo $_SESSION['pending_user']['id']; ?>
        </div>
        
        <p>
            <a href="login_otp.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                Go to OTP Verification Page
            </a>
        </p>
    <?php else: ?>
        <div class="info" style="background: #fff3cd; color: #856404;">
            No OTP is currently set. Please login first to generate an OTP.
        </div>
        
        <p>
            <a href="index.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                Go to Login Page
            </a>
        </p>
    <?php endif; ?>
    
    <hr>
    <p><small><strong>Note:</strong> In production, the OTP would be sent to the user's email address. This is only for development testing.</small></p>
</body>
</html>
