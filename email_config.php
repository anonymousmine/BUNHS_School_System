<?php
// Email Configuration for BUNHS School System
// ================================================

// Use the same Gmail credentials as login_otp.php for consistency
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'bunhs.deped@gmail.com'); // Same as LOT_SMTP_USER from login_otp.php
define('SMTP_PASSWORD', 'svhiovmxalojxzxg'); // Same as LOT_SMTP_PASS from login_otp.php
define('SMTP_ENCRYPTION', 'tls');

// Email From Settings (same as login_otp.php)
define('FROM_EMAIL', 'bunhs.deped@gmail.com'); // Same as LOT_SMTP_FROM from login_otp.php
define('FROM_NAME', 'Buyoan National High School'); // Same as LOT_SMTP_FROM_NAME from login_otp.php

// ================================================
// SETUP INSTRUCTIONS:
// ================================================

/*
The email notification system now uses the same Gmail credentials as the OTP system.
This ensures consistency across all email communications from the school.

CURRENT CONFIGURATION:
- Email: bunhs.deped@gmail.com
- This is the official school email used for:
  - Login OTP verification
  - Event notifications
  - News announcements

SECURITY NOTES:
- The credentials are shared with login_otp.php
- If you need to update the email, update both files:
  1. login_otp.php (lines 16-19)
  2. email_config.php (lines 12-16)

TROUBLESHOOTING:
- If emails stop working, check if the Gmail app password is still valid
- Verify the Gmail account hasn't exceeded sending limits
- Check PHP error logs for SMTP connection issues
*/

// ================================================
// DO NOT MODIFY BELOW THIS LINE
// ================================================

// Email notification settings
define('EMAIL_NOTIFICATIONS_ENABLED', true);
define('EMAIL_BATCH_SIZE', 50); // Send emails in batches to avoid timeouts
define('EMAIL_RETRY_ATTEMPTS', 3); // Number of retry attempts for failed emails

?>
