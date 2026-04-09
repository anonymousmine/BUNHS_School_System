<?php
include 'db_connection.php';

// Create email subscribers and notification logs tables
function setup_email_notification_tables($conn) {
    // Create email_subscribers table
    $conn->query("CREATE TABLE IF NOT EXISTS email_subscribers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        is_active TINYINT(1) DEFAULT 1,
        subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Create email_notification_logs table
    $conn->query("CREATE TABLE IF NOT EXISTS email_notification_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subscriber_id INT NOT NULL,
        notification_type ENUM('event', 'news') NOT NULL,
        item_id INT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('sent', 'failed', 'pending') DEFAULT 'sent',
        error_message TEXT DEFAULT NULL,
        FOREIGN KEY (subscriber_id) REFERENCES email_subscribers(id) ON DELETE CASCADE,
        INDEX idx_subscriber (subscriber_id),
        INDEX idx_notification_type (notification_type),
        INDEX idx_item (notification_type, item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Save email subscriber
function save_email_subscriber($conn, $email) {
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => 'error', 'message' => 'Invalid email address'];
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id, is_active FROM email_subscribers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['is_active']) {
            $stmt->close();
            return ['status' => 'info', 'message' => 'Email already subscribed'];
        } else {
            // Reactivate subscription
            $stmt = $conn->prepare("UPDATE email_subscribers SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
            return ['status' => 'success', 'message' => 'Subscription reactivated'];
        }
    }

    // Insert new subscriber
    $stmt = $conn->prepare("INSERT INTO email_subscribers (email) VALUES (?)");
    $stmt->bind_param("s", $email);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        return ['status' => 'success', 'message' => 'Email subscribed successfully'];
    } else {
        return ['status' => 'error', 'message' => 'Failed to subscribe email'];
    }
}

// Get all active subscribers
function get_active_subscribers($conn) {
    $stmt = $conn->prepare("SELECT id, email FROM email_subscribers WHERE is_active = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $subscribers = [];
    while ($row = $result->fetch_assoc()) {
        $subscribers[] = $row;
    }
    $stmt->close();
    return $subscribers;
}

// Send email notification using PHPMailer
function send_email_notification($conn, $subscriber_email, $subject, $body, $notification_type, $item_id) {
    // Include configuration
    include 'email_config.php';
    
    // Check if email notifications are enabled
    if (!defined('EMAIL_NOTIFICATIONS_ENABLED') || !EMAIL_NOTIFICATIONS_ENABLED) {
        return false;
    }
    
    // Include PHPMailer
    require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once 'vendor/phpmailer/phpmailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings (same as login_otp.php for consistency)
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Add timeout and connection optimizations (same as login_otp.php)
        $mail->Timeout = 10; // 10 second timeout
        $mail->SMTPKeepAlive = true; // Keep connection alive
        $mail->SMTPAutoTLS = true; // Auto TLS detection

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($subscriber_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        // Send with timing and error handling (same as login_otp.php)
        $start_time = microtime(true);
        $result = $mail->send();
        $send_time = round((microtime(true) - $start_time) * 1000, 2);
        
        error_log("[email_notifications] Sent {$notification_type} notification to {$subscriber_email} in {$send_time}ms");
        
        // Log successful notification
        log_notification($conn, $subscriber_email, $notification_type, $item_id, 'sent');
        
        return true;
    } catch (Exception $e) {
        $error_msg = $mail->ErrorInfo ?? $e->getMessage();
        error_log("[email_notifications] SMTP error: " . $error_msg . " for {$subscriber_email}");
        
        // Log failed notification
        log_notification($conn, $subscriber_email, $notification_type, $item_id, 'failed', $error_msg);
        return false;
    }
}

// Log notification
function log_notification($conn, $email, $notification_type, $item_id, $status, $error_message = null) {
    // Get subscriber ID
    $stmt = $conn->prepare("SELECT id FROM email_subscribers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriber = $result->fetch_assoc();
    $stmt->close();

    if ($subscriber) {
        $stmt = $conn->prepare("INSERT INTO email_notification_logs (subscriber_id, notification_type, item_id, status, error_message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isisi", $subscriber['id'], $notification_type, $item_id, $status, $error_message);
        $stmt->execute();
        $stmt->close();
    }
}

// Send email using PHPMailer for event notifications
function sendEventEmail($email, $subject, $body) {
    try {
        require_once 'vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'bunhs.deped@gmail.com';
        $mail->Password = 'svhiovmxalojxzxg';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        $mail->setFrom('bunhs.deped@gmail.com', 'Buyoan National High School');
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("[EMAIL] PHPMailer Error: " . $e->getMessage());
        return false;
    }
}

// Send notifications to all subscribers for new event
function notify_subscribers_new_event($conn, $event_id) {
    error_log("[EMAIL] Starting notification for event ID: $event_id");
    
    // Get event details with more comprehensive query
    $stmt = $conn->prepare("SELECT title, description, event_date, category, location, event_start_time, event_end_time, organizer_name, organizer_position FROM events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    $stmt->close();

    if (!$event) {
        error_log("[EMAIL] Event not found for ID: $event_id");
        return false;
    }

    error_log("[EMAIL] Event data retrieved: " . json_encode($event));

    $subscribers = get_active_subscribers($conn);
    $success_count = 0;

    foreach ($subscribers as $subscriber) {
        $subject = "New Event: " . $event['title'];
        
        // Enhanced HTML template with all event details
        $time_info = '';
        if ($event['event_start_time']) {
            $time_info = '<p style="color:#555;margin:5px 0;font-size:13px;"><strong>Time:</strong> ' . $event['event_start_time'];
            if ($event['event_end_time']) {
                $time_info .= ' - ' . $event['event_end_time'];
            }
            $time_info .= '</p>';
        }

        $organizer_info = '';
        if ($event['organizer_name']) {
            $organizer_info = '<p style="color:#555;margin:5px 0;font-size:13px;"><strong>Organizer:</strong> ' . htmlspecialchars($event['organizer_name'], ENT_QUOTES);
            if ($event['organizer_position']) {
                $organizer_info .= ' (' . htmlspecialchars($event['organizer_position'], ENT_QUOTES) . ')';
            }
            $organizer_info .= '</p>';
        }
        
        // Professional HTML template matching OTP style
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;'>
          <div style='background:#1a3a2a;padding:28px 32px 20px;border-radius:12px 12px 0 0;text-align:center;'>
            <h2 style='color:#fff;margin:0;font-size:20px;'>Buyoan National High School</h2>
            <p style='color:rgba(255,255,255,.6);margin:4px 0 0;font-size:13px;'>Event Announcement</p>
          </div>
          <div style='background:#fff;padding:32px;border:1px solid #e0e0e0;border-top:none;'>
            <p style='color:#333;font-size:14px;margin:0 0 18px;'>Hello,</p>
            <p style='color:#555;font-size:14px;margin:0 0 20px;'>We're excited to announce a new upcoming event:</p>
            
            <div style='background:#f4faf7;border:2px solid #2d6a4f;border-radius:10px;padding:20px;margin-bottom:20px;'>
              <h3 style='color:#1a3a2a;margin:0 0 10px;font-size:18px;'>" . htmlspecialchars($event['title'], ENT_QUOTES) . "</h3>
              <p style='color:#555;margin:5px 0;font-size:13px;'><strong>Date:</strong> " . date('F j, Y', strtotime($event['event_date'])) . "</p>
              <p style='color:#555;margin:5px 0;font-size:13px;'><strong>Category:</strong> " . htmlspecialchars($event['category'], ENT_QUOTES) . "</p>
              $time_info
              $organizer_info";
        
        if (!empty($event['location'])) {
            $body .= "<p style='color:#555;margin:5px 0;font-size:13px;'><strong>Location:</strong> " . htmlspecialchars($event['location'], ENT_QUOTES) . "</p>";
        }
        
        $body .= "
            </div>
            
            <div style='background:#f8f5f0;padding:16px;border-radius:8px;margin:20px 0;'>
              <p style='color:#666;font-size:13px;margin:0;'><strong>Event Details:</strong></p>
              <p style='color:#555;font-size:13px;margin:8px 0 0;line-height:1.5;'>" . nl2br(htmlspecialchars($event['description'] ?? 'No description provided', ENT_QUOTES)) . "</p>
            </div>
            
            <div style='text-align:center;margin:30px 0 20px;'>
              <a href='http://localhost/BUNHS_School_System/events.php' style='display:inline-block;background:#1a3a2a;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:500;font-size:14px;'>View Event Calendar</a>
            </div>
            
            <p style='color:#888;font-size:12px;margin:20px 0 0;'>This event is open to all students, parents, and staff members.</p>
            <p style='color:#888;font-size:12px;margin:5px 0 0;'>If you no longer wish to receive these notifications, you can unsubscribe at any time.</p>
          </div>
          
          <div style='background:#1a3a2a;padding:20px;text-align:center;border-radius:0 0 12px 12px;'>
            <p style='color:rgba(255,255,255,.6);margin:0;font-size:11px;'>Buyoan National High School &copy; 2026</p>
            <p style='color:rgba(255,255,255,.4);margin:5px 0 0;font-size:10px;'>DepEd Region IV-A CALABARZON</p>
          </div>
        </div>";
        
        // Send email using PHPMailer
        $email_sent = sendEventEmail($subscriber['email'], $subject, $body);
        
        if ($email_sent) {
            $success_count++;
            error_log("[EMAIL] Email sent successfully to: " . $subscriber['email']);
        } else {
            error_log("[EMAIL] Failed to send email to: " . $subscriber['email']);
        }
    }
    
    error_log("[EMAIL] Notification completed. Sent to $success_count out of " . count($subscribers) . " subscribers");
    return $success_count;
}

// Send notifications to all subscribers for new news
function notify_subscribers_new_news($conn, $news_id) {
    // Get news details
    $stmt = $conn->prepare("SELECT title, short_description, category, author, news_date FROM news WHERE id = ?");
    $stmt->bind_param("i", $news_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $news = $result->fetch_assoc();
    $stmt->close();

    if (!$news) return false;

    $subscribers = get_active_subscribers($conn);
    $success_count = 0;

    foreach ($subscribers as $subscriber) {
        $subject = "News: " . $news['title'];
        
        // Professional HTML template matching OTP style
        $body = "
        <div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;'>
          <div style='background:#1a3a2a;padding:28px 32px 20px;border-radius:12px 12px 0 0;text-align:center;'>
            <h2 style='color:#fff;margin:0;font-size:20px;'>Buyoan National High School</h2>
            <p style='color:rgba(255,255,255,.6);margin:4px 0 0;font-size:13px;'>News Announcement</p>
          </div>
          <div style='background:#fff;padding:32px;border:1px solid #e0e0e0;border-top:none;'>
            <p style='color:#333;font-size:14px;margin:0 0 18px;'>Hello,</p>
            <p style='color:#555;font-size:14px;margin:0 0 20px;'>We're pleased to share the latest news from our school:</p>
            
            <div style='background:#f4faf7;border:2px solid #2d6a4f;border-radius:10px;padding:20px;margin-bottom:20px;'>
              <h3 style='color:#1a3a2a;margin:0 0 10px;font-size:18px;'>" . htmlspecialchars($news['title'], ENT_QUOTES) . "</h3>
              <p style='color:#555;margin:5px 0;font-size:13px;'><strong>Author:</strong> " . htmlspecialchars($news['author'], ENT_QUOTES) . "</p>
              <p style='color:#555;margin:5px 0;font-size:13px;'><strong>Date:</strong> " . date('F j, Y', strtotime($news['news_date'])) . "</p>
              <p style='color:#555;margin:5px 0;font-size:13px;'><strong>Category:</strong> " . htmlspecialchars($news['category'], ENT_QUOTES) . "</p>
            </div>
            
            <div style='background:#f8f5f0;padding:16px;border-radius:8px;margin:20px 0;'>
              <p style='color:#666;font-size:13px;margin:0;'><strong>News Summary:</strong></p>
              <p style='color:#555;font-size:13px;margin:8px 0 0;line-height:1.5;'>" . nl2br(htmlspecialchars($news['short_description'], ENT_QUOTES)) . "</p>
            </div>
            
            <p style='color:#888;font-size:12px;margin:20px 0 0;'>Stay tuned for more updates from Buyoan National High School.</p>
          </div>
          <div style='background:#f8f5f0;padding:16px 32px;border-radius:0 0 12px 12px;text-align:center;'>
            <p style='color:#aaa;font-size:11px;margin:0;'>Buyoan National High School &bull; Official News Notification</p>
            <p style='color:#aaa;font-size:10px;margin:4px 0 0;'>You received this because you subscribed to news notifications.</p>
          </div>
        </div>";

        if (send_email_notification($conn, $subscriber['email'], $subject, $body, 'news', $news_id)) {
            $success_count++;
        }
    }

    return $success_count;
}

// Setup tables when this file is included
setup_email_notification_tables($conn);
?>
