<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Development OTP Helper</title>
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
            max-width: 500px;
            width: 100%;
        }
        .otp-display {
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
            margin: 20px 0;
            letter-spacing: 10px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px dashed #667eea;
        }
        .info-box {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .warning-box {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <h2>🔐 Development OTP Helper</h2>
        
        <?php if (isset($_SESSION['otp_pending'])): ?>
            <div class="info-box">
                <h5>✅ OTP Session Active</h5>
                <p><strong>User:</strong> <?php echo htmlspecialchars($_SESSION['otp_pending']['username']); ?></p>
                <p><strong>Type:</strong> <?php echo ucfirst($_SESSION['otp_pending']['user_type']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['otp_pending']['email'] ?: 'Not set'); ?></p>
                <p><strong>Expires:</strong> <?php echo date('Y-m-d H:i:s', $_SESSION['otp_pending']['expires']); ?></p>
            </div>
            
            <div class="otp-display">
                <?php 
                // For development, show the OTP if we have it stored
                if (isset($_SESSION['dev_otp'])) {
                    echo $_SESSION['dev_otp'];
                } else {
                    echo "XXXXXX";
                    echo '<br><small>OTP not available in session</small>';
                }
                ?>
            </div>
            
            <div class="warning-box">
                <h6>⚠️ Development Mode Only</h6>
                <p>In production, the OTP would be sent to the user's email address. This helper is for development testing only.</p>
            </div>
            
            <div class="d-grid gap-2">
                <a href="verify_otp_new.php" class="btn btn-primary btn-lg">
                    🚀 Go to OTP Verification Page
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    ← Back to Login
                </a>
            </div>
            
        <?php elseif (isset($_SESSION['dev_otp'])): ?>
            <div class="info-box">
                <h5>📧 OTP Available (from login)</h5>
                <p>OTP was generated during login but session may have expired.</p>
            </div>
            
            <div class="otp-display">
                <?php echo $_SESSION['dev_otp']; ?>
            </div>
            
            <a href="index.php" class="btn btn-primary">
                🔄 Login Again to Generate New OTP
            </a>
            
        <?php else: ?>
            <div class="warning-box">
                <h5>❌ No OTP Session Found</h5>
                <p>Please login first to generate an OTP.</p>
            </div>
            
            <a href="index.php" class="btn btn-primary btn-lg">
                🔑 Go to Login Page
            </a>
        <?php endif; ?>
        
        <hr>
        <p class="text-muted small">
            <strong>Note:</strong> This page is for development purposes only. 
            In production, OTP codes are sent via email and not displayed here.
        </p>
    </div>
</body>
</html>
