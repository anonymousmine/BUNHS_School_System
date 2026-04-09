<?php
session_start();
include '../db_connection.php';

/* ═══════════════════════════════════════════════════════
   SQL MIGRATION — run once to add new columns/tables
   ═══════════════════════════════════════════════════════
   ALTER TABLE `sub_admin`
     ADD COLUMN IF NOT EXISTS `approved_at`   DATETIME      NULL,
     ADD COLUMN IF NOT EXISTS `rejected_at`   DATETIME      NULL,
     ADD COLUMN IF NOT EXISTS `reject_reason` VARCHAR(500)  NULL,
     ADD COLUMN IF NOT EXISTS `created_at`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
     ADD COLUMN IF NOT EXISTS `last_login`    DATETIME      NULL,
     ADD COLUMN IF NOT EXISTS `last_active`   DATETIME      NULL,
     ADD COLUMN IF NOT EXISTS `role`          VARCHAR(255)  DEFAULT 'news_admin',
     ADD COLUMN IF NOT EXISTS `permissions`   TEXT          NULL;

   ALTER TABLE `sub_admin`
     MODIFY COLUMN `status` ENUM('pending','approved','rejected','suspended') DEFAULT 'pending';

   CREATE TABLE IF NOT EXISTS `admin_logs` (
     `id`          INT AUTO_INCREMENT PRIMARY KEY,
     `admin_id`    INT          NOT NULL,
     `action`      VARCHAR(50)  NOT NULL,
     `description` TEXT         NOT NULL,
     `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
     INDEX idx_admin_id (admin_id),
     INDEX idx_created_at (created_at)
   );
*/

/* ── Helpers ──────────────────────────────────────────── */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateAdminCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || hash_equals($_SESSION['csrf_token'], $token) === false) {
        return false;
    }
    return true;
}

/* ── Notification Functions ─────────────────────────────── */
function sendNotification($email, $phone, $subject, $message, $type = 'email') {
    $success = false;
    
    error_log("[NOTIFICATION] Sending notification - Email: $email, Phone: $phone, Type: $type");
    
    // Send email notification
    if (!empty($email) && ($type === 'email' || $type === 'both')) {
        $success = sendEmailNotification($email, $subject, $message);
        error_log("[NOTIFICATION] Email notification result: " . ($success ? 'SUCCESS' : 'FAILED'));
    }
    
    // Send SMS notification (if phone number provided and SMS service is available)
    if (!empty($phone) && ($type === 'sms' || $type === 'both')) {
        $smsSuccess = sendSMSNotification($phone, $message);
        if ($smsSuccess) $success = true;
    }
    
    error_log("[NOTIFICATION] Overall notification result: " . ($success ? 'SUCCESS' : 'FAILED'));
    return $success;
}

function sendEmailNotification($email, $subject, $message) {
    try {
        error_log("[EMAIL] Sending email to: $email");
        error_log("[EMAIL] Subject: $subject");
        
        // Use PHPMailer for email sending
        require_once '../vendor/autoload.php';
        
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
        $mail->Body = $message;
        
        if ($mail->send()) {
            error_log("[EMAIL] Email sent successfully to: $email");
            return true;
        } else {
            error_log("[EMAIL] Email send failed to: $email");
            return false;
        }
    } catch (Exception $e) {
        error_log("[EMAIL] Email sending failed: " . $e->getMessage());
        return false;
    }
}

function sendSMSNotification($phone, $message) {
    // Implement SMS sending using a service like Twilio, Semaphore, etc.
    // For now, this is a placeholder that logs the SMS
    error_log("SMS to $phone: $message");
    return true; // Placeholder
}

function getNotificationMessage($action, $firstName, $lastName, $reason = '') {
    $fullName = $firstName . ' ' . $lastName;
    
    switch ($action) {
        case 'pending':
            return [
                'subject' => 'Sub-Admin Account Pending Approval - BUNHS School System',
                'message' => "
                    <h2>Sub-Admin Account Registration</h2>
                    <p>Dear $fullName,</p>
                    <p>Thank you for your interest in becoming a Sub-Admin at BUNHS School System. Your account has been successfully created and is now pending approval from our system administrators.</p>
                    <p><strong>Account Details:</strong></p>
                    <ul>
                        <li>Name: $fullName</li>
                        <li>Status: Pending Approval</li>
                        <li>Registration Date: " . date('F d, Y') . "</li>
                    </ul>
                    <p>You will receive another notification once your account has been reviewed. This process typically takes 24-48 hours.</p>
                    <p>If you have any questions, please contact the system administrator.</p>
                    <p>Best regards,<br>BUNHS School System Administration</p>
                "
            ];
            
        case 'approved':
            return [
                'subject' => 'Sub-Admin Account Approved - BUNHS School System',
                'message' => "
                    <h2>Account Approved!</h2>
                    <p>Dear $fullName,</p>
                    <p>Congratulations! Your Sub-Admin account has been approved and is now active.</p>
                    <p><strong>Account Details:</strong></p>
                    <ul>
                        <li>Name: $fullName</li>
                        <li>Status: Approved</li>
                        <li>Approval Date: " . date('F d, Y') . "</li>
                    </ul>
                    <p>You can now log in to your account and access the Sub-Admin dashboard.</p>
                    <p><a href='" . $_SERVER['HTTP_HOST'] . "/BUNHS_School_System/login.php'>Click here to login</a></p>
                    <p>Best regards,<br>BUNHS School System Administration</p>
                "
            ];
            
        case 'rejected':
            return [
                'subject' => 'Sub-Admin Application Status - BUNHS School System',
                'message' => "
                    <h2>Application Status Update</h2>
                    <p>Dear $fullName,</p>
                    <p>After careful review, we regret to inform you that your Sub-Admin application has been rejected.</p>
                    <p><strong>Application Details:</strong></p>
                    <ul>
                        <li>Name: $fullName</li>
                        <li>Status: Rejected</li>
                        <li>Review Date: " . date('F d, Y') . "</li>
                    </ul>
                    <p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>
                    <p>If you believe this is an error or would like to appeal this decision, please contact the system administrator.</p>
                    <p>Best regards,<br>BUNHS School System Administration</p>
                "
            ];
            
        case 'suspended':
            return [
                'subject' => 'Sub-Admin Account Suspended - Buyoan National High School',
                'message' => "
                    <h2>Account Suspension Notice</h2>
                    <p>Dear $fullName,</p>
                    <p>Your Sub-Admin account has been temporarily suspended due to policy violations or security concerns.</p>
                    <p><strong>Suspension Details:</strong></p>
                    <ul>
                        <li>Name: $fullName</li>
                        <li>Status: Suspended</li>
                        <li>Suspension Date: " . date('F d, Y') . "</li>
                    </ul>
                    <p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>
                    <p>Your account access has been restricted. Please contact the system administrator for more information or to appeal this suspension.</p>
                    <p>Best regards,<br>Buyoan National High School Administration</p>
                "
            ];
            
        case 'unsuspend':
            return [
                'subject' => 'Account Suspension Cancelled - Buyoan National High School',
                'message' => "
                    <h2>Suspension Cancelled!</h2>
                    <p>Dear $fullName,</p>
                    <p>Good news! Your Sub-Admin account suspension has been cancelled and your access has been restored.</p>
                    <p><strong>Account Details:</strong></p>
                    <ul>
                        <li>Name: $fullName</li>
                        <li>Status: Approved</li>
                        <li>Suspension Cancelled Date: " . date('F d, Y') . "</li>
                    </ul>
                    <p>You can now log in to your account and access the Sub-Admin dashboard normally.</p>
                    <p><a href='" . $_SERVER['HTTP_HOST'] . "/BUNHS_School_System/login.php'>Click here to login</a></p>
                    <p>Best regards,<br>Buyoan National High School Administration</p>
                "
            ];
            
        case 'revoked':
            return [
                'subject' => 'Sub-Admin Access Revoked - BUNHS School System',
                'message' => "
                    <h2>Account Access Revoked</h2>
                    <p>Dear $fullName,</p>
                    <p>Your Sub-Admin account access has been permanently revoked.</p>
                    <p><strong>Revocation Details:</strong></p>
                    <ul>
                        <li>Name: $fullName</li>
                        <li>Status: Access Revoked</li>
                        <li>Revocation Date: " . date('F d, Y') . "</li>
                    </ul>
                    <p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>
                    <p>Your account access has been permanently terminated. If you believe this is an error, please contact the system administrator immediately.</p>
                    <p>Best regards,<br>BUNHS School System Administration</p>
                "
            ];
            
        case 'deleted':
            return [
                'subject' => 'Sub-Admin Account Deleted - BUNHS School System',
                'message' => "
                    <h2>Account Deletion Notice</h2>
                    <p>Dear $fullName,</p>
                    <p>Your Sub-Admin account has been permanently deleted from our system.</p>
                    <p><strong>Deletion Details:</strong></p>
                    <ul>
                        <li>Name: $fullName</li>
                        <li>Status: Account Deleted</li>
                        <li>Deletion Date: " . date('F d, Y') . "</li>
                    </ul>
                    <p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>
                    <p>All your account data has been permanently removed. If you believe this is an error, please contact the system administrator immediately.</p>
                    <p>Best regards,<br>BUNHS School System Administration</p>
                "
            ];
            
        default:
            return [
                'subject' => 'Account Status Update - BUNHS School System',
                'message' => "<p>Dear $fullName,</p><p>Your account status has been updated.</p>"
            ];
    }
}

function logAction($conn, $admin_id, $action, $description)
{
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('iss', $admin_id, $action, $description);
        $stmt->execute();
        $stmt->close();
    }
}

function getInitials($first, $last)
{
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}

function getAvatarColor($name)
{
    $colors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899', '#14b8a6'];
    return $colors[ord($name[0]) % count($colors)];
}

function roleLabel($role)
{
    $map = [
        'news_admin'         => 'News Admin',
        'announcement_admin' => 'Announcement Admin',
        'student_admin'      => 'Student Admin',
        'teacher_admin'      => 'Teacher Admin',
        'club_admin'         => 'Club Admin',
        'super_sub_admin'    => 'Super Sub-Admin',
        'forms_admin'        => 'Forms Admin',
    ];
    $roles = array_map('trim', explode(',', $role ?? ''));
    $labels = array_map(fn($r) => $map[$r] ?? ucfirst(str_replace('_', ' ', $r)), $roles);
    return implode(', ', $labels);
}

function roleBadgeColors($role)
{
    $map = [
        'super_sub_admin'    => ['#ede9fe', '#7c3aed'],
        'news_admin'         => ['#eff6ff', '#3b82f6'],
        'announcement_admin' => ['#fff7ed', '#ea580c'],
        'student_admin'      => ['#f0fdf4', '#16a34a'],
        'teacher_admin'      => ['#fef9c3', '#ca8a04'],
        'club_admin'         => ['#fdf4ff', '#a855f7'],
        'forms_admin'        => ['#f0fdfa', '#0d9488'],
    ];
    $roles = array_map('trim', explode(',', $role ?? ''));
    $primary = $roles[0] ?? 'news_admin';
    return $map[$primary] ?? ['#f3f4f6', '#6b7280'];
}

function timeAgo($datetime)
{
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '—';
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->days === 0) {
        if ($diff->h === 0) return $diff->i . 'm ago';
        return $diff->h . 'h ago';
    }
    if ($diff->days < 7)  return $diff->days . 'd ago';
    if ($diff->days < 30) return round($diff->days / 7) . 'w ago';
    return date('M d, Y', strtotime($datetime));
}

$actingAdminId = $_SESSION['admin_id'] ?? 0;

/* ═══════════════════════════════════════════════════════
   EXPORT HANDLER
═══════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export_format'])) {
    // CSRF Protection
    if (!isset($_GET['csrf_token']) || !validateAdminCSRFToken($_GET['csrf_token'])) {
        die('Security validation failed.');
    }
    
    $format = $_GET['export_format'];
    $tab = $_GET['export_tab'] ?? 'pending';
    
    // Get data based on tab
    $data = [];
    if ($tab === 'pending') {
        $data = $pending_subadmins;
    } elseif ($tab === 'approved') {
        $data = $approved_subadmins;
    } elseif ($tab === 'rejected') {
        $data = $rejected_subadmins;
    }
    
    if (empty($data)) {
        die('No data to export.');
    }
    
    // Prepare headers
    $filename = "sub_admins_{$tab}_" . date('Y-m-d_H-i-s');
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        if ($tab === 'pending' || $tab === 'rejected') {
            fputcsv($output, ['ID', 'Name', 'Email', 'Username', 'Date Applied', ($tab === 'rejected' ? 'Date Rejected' : 'Status')]);
        } else {
            fputcsv($output, ['ID', 'Name', 'Email', 'Username', 'Role', 'Status', 'Last Active', 'Created At']);
        }
        
        // CSV data
        foreach ($data as $row) {
            if ($tab === 'pending' || $tab === 'rejected') {
                $rowData = [
                    $row['id'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['email'],
                    $row['username'],
                    $row['created_at'] ?? '',
                    ($tab === 'rejected' ? ($row['rejected_at'] ?? '') : $row['status'])
                ];
            } else {
                $rowData = [
                    $row['id'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['email'],
                    $row['username'],
                    roleLabel($row['role']),
                    $row['status'],
                    $row['last_active'] ?? '',
                    $row['created_at'] ?? ''
                ];
            }
            fputcsv($output, $rowData);
        }
        
        fclose($output);
        exit;
    } elseif ($format === 'excel') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        
        // Simple HTML table for Excel (basic implementation)
        echo '<table>';
        
        // Headers
        if ($tab === 'pending' || $tab === 'rejected') {
            echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Date Applied</th><th>' . ($tab === 'rejected' ? 'Date Rejected' : 'Status') . '</th></tr>';
        } else {
            echo '<tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Last Active</th><th>Created At</th></tr>';
        }
        
        // Data
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['email']) . '</td>';
            echo '<td>' . htmlspecialchars($row['username']) . '</td>';
            
            if ($tab === 'pending' || $tab === 'rejected') {
                echo '<td>' . ($row['created_at'] ?? '') . '</td>';
                echo '<td>' . ($tab === 'rejected' ? ($row['rejected_at'] ?? '') : $row['status']) . '</td>';
            } else {
                echo '<td>' . htmlspecialchars(roleLabel($row['role'])) . '</td>';
                echo '<td>' . $row['status'] . '</td>';
                echo '<td>' . ($row['last_active'] ?? '') . '</td>';
                echo '<td>' . ($row['created_at'] ?? '') . '</td>';
            }
            
            echo '</tr>';
        }
        
        echo '</table>';
        exit;
    }
}

/* ═══════════════════════════════════════════════════════
   POST HANDLER
═══════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !validateAdminCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Security validation failed. Please try again.";
        header("Location: admins.php");
        exit();
    }
    
    $action = $_POST['action'];

    /* ── Single-record actions ── */
    if (in_array($action, ['approve', 'reject', 'delete', 'edit', 'revoke', 'suspend', 'unsuspend'])) {

        $stmt_id = $conn->prepare("SELECT id FROM sub_admin WHERE id = ?");
        $raw_id  = (int)($_POST['subadmin_id'] ?? 0);
        $stmt_id->bind_param('i', $raw_id);
        $stmt_id->execute();
        $stmt_id->store_result();
        $exists = $stmt_id->num_rows > 0;
        $stmt_id->close();

        if (!$exists) {
            $_SESSION['error'] = "Sub-admin not found.";
            header("Location: admins.php");
            exit();
        }

        if ($action === 'approve') {
            // Get user details for notification
            $user_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM sub_admin WHERE id = ?");
            $user_stmt->bind_param('i', $raw_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            
            error_log("[APPROVE] Approving sub-admin ID: $raw_id");
            error_log("[APPROVE] User data: " . ($user_data ? "Email: {$user_data['email']}, Name: {$user_data['first_name']} {$user_data['last_name']}" : "NOT FOUND"));
            
            $stmt = $conn->prepare("UPDATE sub_admin SET status='approved', approved_at=NOW() WHERE id=?");
            $stmt->bind_param('i', $raw_id);
            if ($stmt->execute()) {
                logAction($conn, $actingAdminId, 'approve', "Approved sub-admin ID #$raw_id");
                
                // Send approval notification
                if ($user_data) {
                    $notification = getNotificationMessage('approved', $user_data['first_name'], $user_data['last_name']);
                    error_log("[APPROVE] Sending approval notification...");
                    $notification_sent = sendNotification($user_data['email'], $user_data['phone'], $notification['subject'], $notification['message']);
                    error_log("[APPROVE] Notification sent: " . ($notification_sent ? 'SUCCESS' : 'FAILED'));
                } else {
                    error_log("[APPROVE] No user data found for notification");
                }
                
                $_SESSION['success'] = "Sub-admin approved successfully.";
            } else {
                $_SESSION['error'] = "Approval failed: " . $conn->error;
                error_log("[APPROVE] Database update failed: " . $conn->error);
            }
            $stmt->close();
        } elseif ($action === 'reject') {
            $reason = trim($_POST['reject_reason'] ?? '');
            
            error_log("[REJECT] Rejecting sub-admin ID: $raw_id");
            error_log("[REJECT] Reason: $reason");
            
            // Get user details for notification
            $user_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM sub_admin WHERE id = ?");
            $user_stmt->bind_param('i', $raw_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            
            error_log("[REJECT] User data: " . ($user_data ? "Email: {$user_data['email']}, Name: {$user_data['first_name']} {$user_data['last_name']}" : "NOT FOUND"));
            
            $stmt = $conn->prepare("UPDATE sub_admin SET status='rejected', reject_reason=?, rejected_at=NOW() WHERE id=?");
            $stmt->bind_param('si', $reason, $raw_id);
            if ($stmt->execute()) {
                logAction($conn, $actingAdminId, 'reject', "Rejected sub-admin ID #$raw_id. Reason: $reason");
                
                // Send rejection notification
                if ($user_data) {
                    $notification = getNotificationMessage('rejected', $user_data['first_name'], $user_data['last_name'], $reason);
                    error_log("[REJECT] Sending rejection notification...");
                    $notification_sent = sendNotification($user_data['email'], $user_data['phone'], $notification['subject'], $notification['message']);
                    error_log("[REJECT] Notification sent: " . ($notification_sent ? 'SUCCESS' : 'FAILED'));
                } else {
                    error_log("[REJECT] No user data found for notification");
                }
                
                $_SESSION['success'] = "Sub-admin has been rejected.";
            } else {
                $_SESSION['error'] = "Rejection failed: " . $conn->error;
                error_log("[REJECT] Database update failed: " . $conn->error);
            }
            $stmt->close();
        } elseif ($action === 'delete') {
            // Get user details for notification before deleting
            $user_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM sub_admin WHERE id = ?");
            $user_stmt->bind_param('i', $raw_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            
            // fetch name for log before deleting
            $n = $conn->query("SELECT CONCAT(first_name,' ',last_name) AS nm FROM sub_admin WHERE id=$raw_id")->fetch_assoc();
            $nm = $n['nm'] ?? "ID #$raw_id";
            $stmt = $conn->prepare("DELETE FROM sub_admin WHERE id=?");
            $stmt->bind_param('i', $raw_id);
            if ($stmt->execute()) {
                logAction($conn, $actingAdminId, 'delete', "Deleted sub-admin #$raw_id ($nm)");
                
                // Send deletion notification
                if ($user_data) {
                    $notification = getNotificationMessage('deleted', $user_data['first_name'], $user_data['last_name'], 'Account deleted by administrator');
                    sendNotification($user_data['email'], $user_data['phone'], $notification['subject'], $notification['message']);
                }
                
                $_SESSION['success'] = "Sub-admin deleted permanently.";
            } else {
                $_SESSION['error'] = "Delete failed: " . $conn->error;
            }
            $stmt->close();
        } elseif ($action === 'edit') {
            $first_name  = trim($_POST['first_name']  ?? '');
            $last_name   = trim($_POST['last_name']   ?? '');
            $email       = trim($_POST['email']       ?? '');
            $username    = trim($_POST['username']    ?? '');
            $roles_arr   = isset($_POST['roles']) && is_array($_POST['roles']) ? $_POST['roles'] : [];
            $roles_str   = implode(',', array_map('trim', $roles_arr));
            $perms_arr   = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
            $perms_str   = implode(',', array_map('trim', $perms_arr));

            $stmt = $conn->prepare("UPDATE sub_admin SET first_name=?, last_name=?, email=?, username=?, role=?, permissions=? WHERE id=?");
            $stmt->bind_param('ssssssi', $first_name, $last_name, $email, $username, $roles_str, $perms_str, $raw_id);
            if ($stmt->execute()) {
                logAction($conn, $actingAdminId, 'edit', "Edited sub-admin ID #$raw_id ($first_name $last_name)");
                $_SESSION['success'] = "Sub-admin updated successfully.";
            } else {
                $_SESSION['error'] = "Update failed: " . $conn->error;
            }
            $stmt->close();
        } elseif ($action === 'revoke') {
            // Get user details for notification
            $user_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM sub_admin WHERE id = ?");
            $user_stmt->bind_param('i', $raw_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            
            $stmt = $conn->prepare("UPDATE sub_admin SET status='pending' WHERE id=?");
            $stmt->bind_param('i', $raw_id);
            if ($stmt->execute()) {
                logAction($conn, $actingAdminId, 'revoke', "Revoked access for sub-admin ID #$raw_id");
                
                // Send revocation notification
                if ($user_data) {
                    $notification = getNotificationMessage('revoked', $user_data['first_name'], $user_data['last_name'], 'Access revoked by administrator');
                    sendNotification($user_data['email'], $user_data['phone'], $notification['subject'], $notification['message']);
                }
                
                $_SESSION['success'] = "Sub-admin access has been revoked.";
            } else {
                $_SESSION['error'] = "Revoke failed: " . $conn->error;
            }
            $stmt->close();
        } elseif ($action === 'suspend') {
            $reason = trim($_POST['suspend_reason'] ?? '');
            
            // Get user details for notification
            $user_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM sub_admin WHERE id = ?");
            $user_stmt->bind_param('i', $raw_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            
            $stmt = $conn->prepare("UPDATE sub_admin SET status='suspended' WHERE id=?");
            $stmt->bind_param('i', $raw_id);
            if ($stmt->execute()) {
                logAction($conn, $actingAdminId, 'suspend', "Suspended sub-admin ID #$raw_id. Reason: $reason");
                
                // Send suspension notification
                if ($user_data) {
                    $notification = getNotificationMessage('suspended', $user_data['first_name'], $user_data['last_name'], $reason);
                    sendNotification($user_data['email'], $user_data['phone'], $notification['subject'], $notification['message']);
                }
                
                $_SESSION['success'] = "Sub-admin has been suspended.";
            } else {
                $_SESSION['error'] = "Suspend failed: " . $conn->error;
            }
            $stmt->close();
        } elseif ($action === 'unsuspend') {
            // Get user details for notification
            $user_stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM sub_admin WHERE id = ?");
            $user_stmt->bind_param('i', $raw_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            $user_stmt->close();
            
            $stmt = $conn->prepare("UPDATE sub_admin SET status='approved' WHERE id=?");
            $stmt->bind_param('i', $raw_id);
            if ($stmt->execute()) {
                logAction($conn, $actingAdminId, 'unsuspend', "Unsuspended sub-admin ID #$raw_id");
                
                // Send suspension cancellation notification
                if ($user_data) {
                    $notification = getNotificationMessage('unsuspend', $user_data['first_name'], $user_data['last_name'], 'Account suspension has been cancelled');
                    sendNotification($user_data['email'], $user_data['phone'], $notification['subject'], $notification['message']);
                }
                
                $_SESSION['success'] = "Suspension has been cancelled.";
            } else {
                $_SESSION['error'] = "Failed to cancel suspension: " . $conn->error;
            }
            $stmt->close();
        }
    }

    /* ── Bulk actions ── */
    if ($action === 'bulk_action') {
        $bulk_type = $_POST['bulk_type'] ?? '';
        $ids = isset($_POST['bulk_ids']) ? array_filter(array_map('intval', explode(',', $_POST['bulk_ids']))) : [];

        if (!empty($ids) && in_array($bulk_type, ['approve', 'reject', 'delete'])) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types        = str_repeat('i', count($ids));

            if ($bulk_type === 'approve') {
                $stmt = $conn->prepare("UPDATE sub_admin SET status='approved', approved_at=NOW() WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $stmt->close();
                logAction($conn, $actingAdminId, 'bulk_approve', "Bulk approved IDs: " . implode(',', $ids));
                $_SESSION['success'] = count($ids) . " sub-admin(s) approved.";
            } elseif ($bulk_type === 'reject') {
                $stmt = $conn->prepare("UPDATE sub_admin SET status='rejected', rejected_at=NOW() WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $stmt->close();
                logAction($conn, $actingAdminId, 'bulk_reject', "Bulk rejected IDs: " . implode(',', $ids));
                $_SESSION['success'] = count($ids) . " sub-admin(s) rejected.";
            } elseif ($bulk_type === 'delete') {
                $stmt = $conn->prepare("DELETE FROM sub_admin WHERE id IN ($placeholders)");
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $stmt->close();
                logAction($conn, $actingAdminId, 'bulk_delete', "Bulk deleted IDs: " . implode(',', $ids));
                $_SESSION['success'] = count($ids) . " sub-admin(s) deleted.";
            }
        } else {
            $_SESSION['error'] = "No records selected or invalid action.";
        }
    }

    header("Location: admins.php");
    exit();
}

/* ═══════════════════════════════════════════════════════
   FILTERS
═══════════════════════════════════════════════════════ */
$filter_role        = trim($_GET['filter_role']   ?? '');
$filter_status      = trim($_GET['filter_status'] ?? '');
$filter_last_active = trim($_GET['filter_active'] ?? '');
$filter_date_from   = trim($_GET['date_from']     ?? '');
$filter_date_to     = trim($_GET['date_to']       ?? '');
$search_q           = trim($_GET['search']        ?? '');

/* ── Pagination Helper ───────────────────────────── */
function getPaginationData($current_page, $total_records, $per_page = 20) {
    $total_pages = ceil($total_records / $per_page);
    $offset = ($current_page - 1) * $per_page;
    
    return [
        'offset' => $offset,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'total_records' => $total_records
    ];
}

/* ── Pagination Parameters ── */
$current_page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

/* ── Stats ── */
$count_pending   = $conn->query("SELECT COUNT(*) c FROM sub_admin WHERE status='pending'")->fetch_assoc()['c']  ?? 0;
$count_approved  = $conn->query("SELECT COUNT(*) c FROM sub_admin WHERE status='approved'")->fetch_assoc()['c'] ?? 0;
$count_rejected  = $conn->query("SELECT COUNT(*) c FROM sub_admin WHERE status='rejected'")->fetch_assoc()['c'] ?? 0;
$count_suspended = $conn->query("SELECT COUNT(*) c FROM sub_admin WHERE status='suspended'")->fetch_assoc()['c'] ?? 0;
$count_active    = $conn->query("SELECT COUNT(*) c FROM sub_admin WHERE status='approved' AND last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'] ?? 0;

// Debug: Check all sub_admin records
$all_records = $conn->query("SELECT id, first_name, last_name, email, username, status, created_at FROM sub_admin ORDER BY id DESC LIMIT 10");
error_log("[ADMINS] All sub_admin records (last 10):");
while ($record = $all_records->fetch_assoc()) {
    error_log("[ADMINS] ID: {$record['id']}, Name: {$record['first_name']} {$record['last_name']}, Email: {$record['email']}, Status: {$record['status']}, Created: {$record['created_at']}");
}
error_log("[ADMINS] Stats - Pending: $count_pending, Approved: $count_approved, Rejected: $count_rejected, Suspended: $count_suspended");

/* ── Build dynamic WHERE for approved/suspended tabs ── */
function buildWhereClause($conn, $filter_role, $filter_status, $filter_last_active, $filter_date_from, $filter_date_to, $search_q, $base_statuses)
{
    $where  = "WHERE status IN ('" . implode("','", $base_statuses) . "')";
    $params = [];
    $types  = '';

    if ($filter_role !== '') {
        $where   .= " AND FIND_IN_SET(?, role)";
        $params[] = $filter_role;
        $types   .= 's';
    }
    if ($filter_status !== '' && in_array($filter_status, $base_statuses)) {
        $where   .= " AND status = ?";
        $params[] = $filter_status;
        $types   .= 's';
    }
    if ($filter_last_active === '24h') {
        $where .= " AND last_active >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    } elseif ($filter_last_active === '7d') {
        $where .= " AND last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } elseif ($filter_last_active === '30d') {
        $where .= " AND last_active >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($filter_last_active === 'never') {
        $where .= " AND (last_active IS NULL OR last_active = '0000-00-00 00:00:00')";
    }
    if ($filter_date_from !== '') {
        $where   .= " AND DATE(created_at) >= ?";
        $params[] = $filter_date_from;
        $types   .= 's';
    }
    if ($filter_date_to !== '') {
        $where   .= " AND DATE(created_at) <= ?";
        $params[] = $filter_date_to;
        $types   .= 's';
    }
    if ($search_q !== '') {
        $like     = "%$search_q%";
        $where   .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types   .= 'ssss';
    }
    return [$where, $types, $params];
}

/* ── Fetch pending (with pagination) ── */
$pending_subadmins = [];
[$w, $t, $p] = buildWhereClause($conn, $filter_role, '', $filter_last_active, $filter_date_from, $filter_date_to, $search_q, ['pending']);
$pagination_pending = getPaginationData($current_page, $count_pending, $per_page);
$sql = "SELECT * FROM sub_admin $w ORDER BY id DESC LIMIT {$pagination_pending['per_page']} OFFSET {$pagination_pending['offset']}";
error_log("[ADMINS] Pending query: $sql");
error_log("[ADMINS] Count pending: $count_pending");
$stmt = $conn->prepare($sql);
if (!empty($p)) {
    $stmt->bind_param($t, ...$p);
}
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $pending_subadmins[] = $row;
$stmt->close();
error_log("[ADMINS] Pending subadmins found: " . count($pending_subadmins));

/* ── Fetch approved + suspended (with pagination) ── */
$approved_subadmins = [];
[$w, $t, $p] = buildWhereClause($conn, $filter_role, $filter_status, $filter_last_active, $filter_date_from, $filter_date_to, $search_q, ['approved', 'suspended']);
$count_approved_filtered = $conn->query("SELECT COUNT(*) FROM sub_admin $w")->fetch_row()[0] ?? 0;
$pagination_approved = getPaginationData($current_page, $count_approved_filtered, $per_page);
$sql = "SELECT * FROM sub_admin $w ORDER BY id DESC LIMIT {$pagination_approved['per_page']} OFFSET {$pagination_approved['offset']}";
$stmt = $conn->prepare($sql);
if (!empty($p)) {
    $stmt->bind_param($t, ...$p);
}
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $approved_subadmins[] = $row;
$stmt->close();

/* ── Fetch rejected ── */
$rejected_subadmins = [];
$sql = "SELECT * FROM sub_admin WHERE status='rejected' ORDER BY id DESC LIMIT 20";
$r = $conn->query($sql);
while ($row = $r->fetch_assoc()) $rejected_subadmins[] = $row;

/* ── Fetch recent logs ── */
$admin_logs = [];
$r = $conn->query("SELECT al.*, CONCAT(sa.first_name,' ',sa.last_name) AS target_name FROM admin_logs al LEFT JOIN sub_admin sa ON al.admin_id = sa.id ORDER BY al.created_at DESC LIMIT 50");
if ($r) while ($row = $r->fetch_assoc()) $admin_logs[] = $row;

$all_roles = [
    'news_admin'         => 'News Admin',
    'announcement_admin' => 'Announcement Admin',
    'student_admin'      => 'Student Admin',
    'teacher_admin'      => 'Teacher Admin',
    'club_admin'         => 'Club Admin',
    'super_sub_admin'    => 'Super Sub-Admin',
    'forms_admin'        => 'Forms Admin',
];
$all_permissions = [
    'manage_students'  => 'Manage Students',
    'manage_teachers'  => 'Manage Teachers',
    'manage_finance'   => 'Manage Finance',
    'manage_clubs'     => 'Manage Clubs',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sub-Admins</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="../overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════════════
           DESIGN SYSTEM — Refined Dark-Accent Admin Theme
           Font: Sora (headings) + DM Sans (body)
           Palette: Deep Navy + Crisp White + Vivid Accents
        ══════════════════════════════════════════════════ */
        @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap');

        :root {
            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --border: #e2e5f0;
            --border-soft: #eceef6;
            --text-primary: #0f1523;
            --text-secondary: #4b5563;
            --text-muted: #6b7280;

            --blue: #3b62f5;
            --blue-light: #eef1fe;
            --blue-glow: rgba(59, 98, 245, .18);

            --green: #16c784;
            --green-light: #e9faf4;

            --amber: #f5a623;
            --amber-light: #fff7e6;

            --red: #f04040;
            --red-light: #fef0f0;

            --purple: #8b5cf6;
            --purple-light: #f3effe;

            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;

            --shadow-xs: 0 1px 3px rgba(15, 21, 35, .06);
            --shadow-sm: 0 2px 8px rgba(15, 21, 35, .08);
            --shadow-md: 0 6px 24px rgba(15, 21, 35, .10);
            --shadow-lg: 0 16px 48px rgba(15, 21, 35, .14);
            --shadow-blue: 0 8px 28px rgba(59, 98, 245, .28);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--light-color);
            margin: 0;
            padding: 0;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }

        /* Main layout — .main is opened by admin_nav.php */
        .main {
            margin-left: 240px;
            min-height: 100vh;
            width: calc(100% - 240px);
            box-sizing: border-box;
        }

        .page-content {
            padding: 28px 32px 48px;
            width: 100%;
            min-height: calc(100vh - 72px);
            box-sizing: border-box;
            background: #f8fafc;
        }

        /* ── Page Header ───────────────────────────── */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 32px 0 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            margin: 0 0 6px;
            letter-spacing: .02em;
        }

        .breadcrumb a {
            color: var(--text-muted);
            text-decoration: none;
            transition: color .2s;
        }

        .breadcrumb a:hover {
            color: var(--blue);
        }

        .breadcrumb i {
            font-size: 9px;
            opacity: .6;
        }

        .page-title-text {
            font-family: 'Sora', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--navy);
            margin: 0;
            letter-spacing: -.4px;
        }

        /* ── Toast ─────────────────────────────────── */
        .toast-stack {
            position: fixed;
            bottom: 28px;
            right: 28px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 9999;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            animation: slideInRight .32s cubic-bezier(.22, 1, .36, 1);
            min-width: 290px;
            backdrop-filter: blur(8px);
        }

        .toast-success {
            background: var(--green);
            color: #fff;
        }

        .toast-error {
            background: var(--red);
            color: #fff;
        }

        .toast-info {
            background: var(--blue);
            color: #fff;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(110%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ── Stats Row ─────────────────────────────── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 22px 20px 20px;
            display: flex;
            align-items: flex-start;
            flex-direction: column;
            gap: 14px;
            box-shadow: var(--shadow-xs);
            border: 1px solid var(--border);
            transition: transform .22s ease, box-shadow .22s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            opacity: 0;
            transition: opacity .22s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:nth-child(1)::before {
            background: var(--blue);
        }

        .stat-card:nth-child(2)::before {
            background: var(--amber);
        }

        .stat-card:nth-child(3)::before {
            background: var(--green);
        }

        .stat-card:nth-child(4)::before {
            background: var(--red);
        }

        .stat-card:nth-child(5)::before {
            background: var(--purple);
        }

        .stat-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stat-icon-wrap.blue {
            background: var(--blue-light);
            color: var(--blue);
        }

        .stat-icon-wrap.green {
            background: var(--green-light);
            color: var(--green);
        }

        .stat-icon-wrap.amber {
            background: var(--amber-light);
            color: var(--amber);
        }

        .stat-icon-wrap.red {
            background: var(--red-light);
            color: var(--red);
        }

        .stat-icon-wrap.purple {
            background: var(--purple-light);
            color: var(--purple);
        }

        .stat-info .stat-val {
            font-family: 'Sora', sans-serif;
            font-size: 30px;
            font-weight: 800;
            line-height: 1;
            color: var(--navy);
            letter-spacing: -.5px;
        }

        .stat-info .stat-lbl {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            margin-top: 5px;
            letter-spacing: .01em;
        }

        /* ── Tab Bar ───────────────────────────────── */
        .tab-bar {
            display: flex;
            gap: 2px;
            background: var(--surface);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            padding: 10px 14px 0;
            border: 1px solid var(--border);
            border-bottom: none;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 16px;
            border: none;
            background: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            white-space: nowrap;
            min-width: fit-content;
        }

        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            border-radius: 2px 2px 0 0;
            background: transparent;
            transition: background .2s;
        }

        .tab-btn:hover {
            color: var(--text-secondary);
            background: var(--surface-2);
        }

        .tab-btn .badge {
            background: var(--border);
            color: var(--text-secondary);
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            transition: all .2s;
            letter-spacing: .02em;
        }

        /* Active states per tab */
        .tab-btn.active {
            color: var(--blue);
            background: var(--blue-light);
        }

        .tab-btn.active::after {
            background: var(--blue);
        }

        .tab-btn.active .badge {
            background: var(--blue);
            color: #fff;
        }

        .tab-btn.pending-tab.active {
            color: var(--amber);
            background: var(--amber-light);
        }

        .tab-btn.pending-tab.active::after {
            background: var(--amber);
        }

        .tab-btn.pending-tab.active .badge {
            background: var(--amber);
            color: #fff;
        }

        .tab-btn.rejected-tab.active {
            color: var(--red);
            background: var(--red-light);
        }

        .tab-btn.rejected-tab.active::after {
            background: var(--red);
        }

        .tab-btn.rejected-tab.active .badge {
            background: var(--red);
            color: #fff;
        }

        .tab-btn.logs-tab.active {
            color: var(--purple);
            background: var(--purple-light);
        }

        .tab-btn.logs-tab.active::after {
            background: var(--purple);
        }

        .tab-btn.logs-tab.active .badge {
            background: var(--purple);
            color: #fff;
        }

        @media (max-width: 768px) {
            .tab-bar {
                gap: 4px;
                padding: 8px 10px 0;
            }
            
            .tab-btn {
                padding: 8px 12px;
                font-size: 12px;
                gap: 5px;
            }
            
            .tab-btn .badge {
                font-size: 9px;
                padding: 1px 5px;
            }
        }

        /* ── Main Card ─────────────────────────────── */
        .main-card {
            background: var(--surface);
            border-radius: 0 var(--radius-lg) var(--radius-lg) var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .card-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border-soft);
            flex-wrap: wrap;
            background: var(--surface);
            min-height: 60px;
        }

        /* Search */
        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 250px;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-wrap i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 13px;
            z-index: 1;
        }

        .search-wrap .btn-icon {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: all .2s;
        }

        .search-wrap .btn-icon:hover {
            background: var(--border-soft);
            color: var(--text-secondary);
        }

        .search-input {
            width: 100%;
            padding: 9px 45px 9px 38px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            background: var(--surface-2);
            color: var(--text-primary);
            flex: 1;
        }

        .search-input::placeholder {
            color: var(--text-muted);
        }

        .search-input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-glow);
            background: var(--surface);
        }

        .toolbar-right {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        /* Filter controls */
        .filter-select,
        .filter-date {
            padding: 8px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            outline: none;
            background: var(--surface-2);
            color: var(--text-secondary);
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }

        .filter-select:focus,
        .filter-date:focus {
            border-color: var(--blue);
            background: var(--surface);
            box-shadow: 0 0 0 3px var(--blue-glow);
        }

        .btn-filter-clear {
            padding: 8px 14px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
            background: var(--surface);
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-filter-clear:hover {
            background: var(--red-light);
            border-color: var(--red);
            color: var(--red);
        }

        /* ── Bulk Action Bar ───────────────────────── */
        .bulk-bar {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 22px;
            background: linear-gradient(90deg, #eef1fe 0%, #f5f7ff 100%);
            border-bottom: 1px solid #d4dcff;
            flex-wrap: wrap;
        }

        .bulk-bar.visible {
            display: flex;
        }

        .bulk-count {
            font-size: 13px;
            font-weight: 700;
            color: var(--blue);
        }

        .btn-bulk {
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            border: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .18s;
        }

        .btn-bulk-approve {
            background: var(--green);
            color: #fff;
        }

        .btn-bulk-approve:hover {
            background: #0fa96e;
            transform: translateY(-1px);
        }

        .btn-bulk-reject {
            background: var(--red-light);
            color: var(--red);
            border: 1px solid #fcd0d0;
        }

        .btn-bulk-reject:hover {
            background: var(--red);
            color: #fff;
        }

        .btn-bulk-delete {
            background: var(--red);
            color: #fff;
        }

        .btn-bulk-delete:hover {
            background: #c93232;
            transform: translateY(-1px);
        }

        .btn-bulk-cancel {
            background: rgba(255, 255, 255, .7);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-bulk-cancel:hover {
            background: var(--surface);
        }

        /* ── Data Table ────────────────────────────── */
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            min-width: 900px;
        }

        .data-table th {
            background: var(--surface-2);
            padding: 12px 16px;
            text-align: left;
            font-family: 'DM Sans', sans-serif;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .07em;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
            vertical-align: middle;
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-soft);
            font-size: 13px;
            vertical-align: middle;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .data-table tbody tr {
            transition: background .15s;
        }

        .data-table tbody tr:hover {
            background: #f7f9ff;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .col-check {
            width: 60px;
            min-width: 60px;
            max-width: 60px;
        }

        .row-cb {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--blue);
        }

        /* Specific column widths using percentages */
        .data-table th:nth-child(1), .data-table td:nth-child(1) { /* Checkbox */
            width: 60px;
            min-width: 60px;
            max-width: 60px;
        }

        .data-table th:nth-child(2), .data-table td:nth-child(2) { /* Applicant */
            width: 25%;
            min-width: 200px;
        }

        .data-table th:nth-child(3), .data-table td:nth-child(3) { /* Email */
            width: 25%;
            min-width: 200px;
        }

        .data-table th:nth-child(4), .data-table td:nth-child(4) { /* Username */
            width: 15%;
            min-width: 120px;
        }

        .data-table th:nth-child(5), .data-table td:nth-child(5) { /* Last Active */
            width: 18%;
            min-width: 140px;
        }

        .data-table th:nth-child(6), .data-table td:nth-child(6) { /* Status */
            width: 12%;
            min-width: 100px;
        }

        .data-table th:nth-child(7), .data-table td:nth-child(7) { /* Actions */
            width: 25%;
            min-width: 220px;
        }

        /* Avatar */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 13px;
            width: 100%;
            height: 40px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Sora', sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
            letter-spacing: .02em;
            box-shadow: var(--shadow-xs);
        }

        .user-info {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.2;
        }

        .user-username {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 1px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.2;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .status-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            display: inline-block;
        }

        .badge-pending {
            background: var(--amber-light);
            color: #9c6a0a;
        }

        .badge-pending::before {
            background: var(--amber);
        }

        .badge-approved {
            background: var(--green-light);
            color: #0c7a50;
        }

        .badge-approved::before {
            background: var(--green);
        }

        .badge-rejected {
            background: var(--red-light);
            color: #9b1c1c;
        }

        .badge-rejected::before {
            background: var(--red);
        }

        .badge-suspended {
            background: var(--purple-light);
            color: #5b21b6;
        }

        .badge-suspended::before {
            background: var(--purple);
        }

        /* Role chips */
        .role-chip {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .02em;
            white-space: nowrap;
        }

        .role-multi {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        /* Last active */
        .last-active {
            font-size: 12px;
        }

        .active-online {
            color: var(--green);
            font-weight: 700;
        }

        .active-recent {
            color: var(--amber);
            font-weight: 500;
        }

        .active-old {
            color: var(--text-muted);
        }

        .date-cell {
            color: var(--text-muted);
            font-size: 13px;
        }

        /* ── Action Buttons ────────────────────────── */
        .actions {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .btn-icon {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12.5px;
            color: var(--text-muted);
            cursor: pointer;
            transition: all .16s;
            text-decoration: none;
        }

        .btn-icon:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-icon.approve:hover {
            background: var(--green-light);
            color: var(--green);
            border-color: var(--green);
        }

        .btn-icon.reject:hover {
            background: var(--red-light);
            color: var(--red);
            border-color: var(--red);
        }

        .btn-icon.edit:hover {
            background: var(--blue-light);
            color: var(--blue);
            border-color: var(--blue);
        }

        .btn-icon.delete:hover {
            background: var(--red-light);
            color: var(--red);
            border-color: var(--red);
        }

        .btn-icon.revoke:hover {
            background: var(--amber-light);
            color: var(--amber);
            border-color: var(--amber);
        }

        .btn-icon.suspend:hover {
            background: var(--purple-light);
            color: var(--purple);
            border-color: var(--purple);
        }

        /* Pending approve/reject buttons */
        .action-group {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
            width: 100%;
            justify-content: flex-start;
        }

        .actions {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
            width: 100%;
            justify-content: flex-start;
        }

        .btn-approve,
        .btn-reject {
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            border: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .btn-approve {
            background: var(--green);
            color: #fff;
        }

        .btn-approve:hover {
            background: #0fa96e;
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(22, 199, 132, .35);
        }

        .btn-reject {
            background: var(--red-light);
            color: var(--red);
            border: 1.5px solid #fcd0d0;
        }

        .btn-reject:hover {
            background: var(--red);
            color: #fff;
            transform: translateY(-1px);
        }

        /* ── Empty State ───────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 72px 20px;
            color: var(--text-muted);
        }

        .empty-state .empty-icon {
            width: 68px;
            height: 68px;
            border-radius: var(--radius-lg);
            background: var(--surface-2);
            border: 1.5px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 18px;
            color: var(--text-muted);
        }

        .empty-state h4 {
            margin: 0 0 8px;
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 600;
            font-family: 'Sora', sans-serif;
        }

        .empty-state p {
            margin: 0;
            font-size: 13px;
        }

        /* ── Tab panels ────────────────────────────── */
        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        /* ── Modals ────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10, 16, 40, .5);
            backdrop-filter: blur(6px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: var(--surface);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg), 0 0 0 1px rgba(255, 255, 255, .05);
            width: 92%;
            max-width: 520px;
            animation: modalIn .26s cubic-bezier(.34, 1.56, .64, 1);
            overflow: hidden;
        }

        .modal-box.wide {
            max-width: 640px;
        }

        @keyframes modalIn {
            from {
                transform: translateY(24px) scale(.96);
                opacity: 0;
            }

            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .modal-head {
            padding: 22px 26px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-soft);
            background: var(--surface-2);
        }

        .modal-head h3 {
            margin: 0;
            font-family: 'Sora', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: var(--navy);
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            color: var(--text-muted);
            cursor: pointer;
            transition: all .2s;
        }

        .modal-close:hover {
            background: var(--red-light);
            border-color: var(--red);
            color: var(--red);
        }

        .modal-body {
            padding: 24px 26px;
            max-height: 72vh;
            overflow-y: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 5px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: var(--surface-2);
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        .modal-icon-banner {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 28px 26px 20px;
            text-align: center;
        }

        .modal-icon-banner .icon-circle {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 18px;
        }

        .icon-circle.green {
            background: var(--green-light);
            color: var(--green);
        }

        .icon-circle.red {
            background: var(--red-light);
            color: var(--red);
        }

        .icon-circle.purple {
            background: var(--purple-light);
            color: var(--purple);
        }

        .modal-icon-banner h4 {
            margin: 0 0 8px;
            font-family: 'Sora', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--navy);
        }

        .modal-icon-banner p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.65;
        }

        .modal-foot {
            padding: 20px 24px;
            border-top: 1px solid var(--border-soft);
            background: var(--surface);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .modal-foot-left {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .modal-foot-right {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* ── Form Styles ───────────────────────────── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 10px 13px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px;
            outline: none;
            transition: all .2s;
            background: var(--surface-2);
            color: var(--text-primary);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-glow);
            background: var(--surface);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 85px;
        }

        /* Permission toggles */
        .perm-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .perm-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 13px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all .18s;
            background: var(--surface-2);
        }

        .perm-item:hover,
        .perm-item.checked {
            border-color: var(--blue);
            background: var(--blue-light);
        }

        .perm-item input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--blue);
            cursor: pointer;
            flex-shrink: 0;
        }

        .perm-item span {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        /* Role grid */
        .role-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .role-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 13px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all .18s;
            background: var(--surface-2);
        }

        .role-item:hover,
        .role-item.checked {
            border-color: var(--purple);
            background: var(--purple-light);
        }

        .role-item input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: var(--purple);
            cursor: pointer;
            flex-shrink: 0;
        }

        .role-item span {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        /* ── Buttons ───────────────────────────────── */
        .btn-secondary {
            padding: 10px 20px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
            background: var(--surface);
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all .2s;
        }

        .btn-secondary:hover {
            background: var(--surface-2);
            border-color: var(--text-muted);
        }

        .btn-primary {
            padding: 10px 22px;
            border-radius: var(--radius-md);
            border: none;
            background: var(--blue);
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #2a52e0;
            transform: translateY(-1px);
            box-shadow: var(--shadow-blue);
        }

        .btn-danger {
            padding: 10px 22px;
            border-radius: var(--radius-md);
            border: none;
            background: var(--red);
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-danger:hover {
            background: #c93232;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(240, 64, 64, .35);
        }

        .btn-purple {
            padding: 10px 22px;
            border-radius: var(--radius-md);
            border: none;
            background: var(--purple);
            font-family: 'DM Sans', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all .2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-purple:hover {
            background: #7c3aed;
            transform: translateY(-1px);
        }

        /* ── Activity Logs ─────────────────────────── */
        .log-list {
            padding: 0 22px 22px;
        }

        .log-item {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-soft);
            align-items: flex-start;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }

        .log-icon.approve {
            background: var(--green-light);
            color: var(--green);
        }

        .log-icon.reject {
            background: var(--red-light);
            color: var(--red);
        }

        .log-icon.delete {
            background: var(--red-light);
            color: var(--red);
        }

        .log-icon.edit {
            background: var(--blue-light);
            color: var(--blue);
        }

        .log-icon.revoke {
            background: var(--amber-light);
            color: var(--amber);
        }

        .log-icon.suspend {
            background: var(--purple-light);
            color: var(--purple);
        }

        .log-icon.bulk_approve,
        .log-icon.bulk_reject,
        .log-icon.bulk_delete {
            background: var(--surface-2);
            color: var(--text-muted);
        }

        .log-body {
            flex: 1;
            min-width: 0;
        }

        .last-active {
            font-size: 12px;
            font-weight: 600;
        }

        .last-active-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .logout-time {
            font-size: 10px;
            color: var(--text-muted);
            line-height: 1.2;
        }

        .logout-time small {
            font-size: 10px;
            color: var(--text-muted);
        }

        .log-action {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .log-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .log-time {
            font-size: 11px;
            color: var(--text-muted);
            white-space: nowrap;
            margin-top: 3px;
        }

        /* ── Row States ────────────────────────────── */
        .row-pending {
            background: linear-gradient(90deg, rgba(245, 166, 35, .04), transparent);
        }

        .row-pending:hover {
            background: rgba(245, 166, 35, .06) !important;
        }

        .row-suspended {
            background: linear-gradient(90deg, rgba(139, 92, 246, .04), transparent);
        }

        .row-suspended:hover {
            background: rgba(139, 92, 246, .06) !important;
        }

        /* ── Responsive ────────────────────────────── */
        @media (max-width:1100px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width:900px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }

            /* Page content fills full width when sidebar slides off */
            .page-content {
                width: 100% !important;
                padding: 80px 16px 36px !important;
            }

            .dashboard-header {
                padding: 20px 0 16px !important;
            }

            /* Tab bar: allow horizontal scroll on small screens */
            .tab-bar {
                overflow-x: auto !important;
                flex-wrap: nowrap !important;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }

            .tab-bar::-webkit-scrollbar {
                display: none;
            }

            .tab-btn {
                white-space: nowrap !important;
                flex-shrink: 0 !important;
            }

            /* Card toolbar: stack search + filters */
            .card-toolbar {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 10px !important;
            }

            .search-wrap {
                width: 100% !important;
            }

            .search-input {
                width: 100% !important;
            }

            .toolbar-right {
                flex-wrap: wrap !important;
                gap: 8px !important;
            }

            /* Bulk bar: allow wrap */
            .bulk-bar {
                flex-wrap: wrap !important;
                gap: 8px !important;
                padding: 10px 16px !important;
            }

            /* Hide less-important table columns */
            .data-table .col-date,
            .data-table .col-active,
            .data-table th:nth-child(3),
            .data-table td:nth-child(3) {
                display: none !important;
            }

            /* Modal: near full-screen */
            .modal-box {
                width: 95vw !important;
                max-width: 95vw !important;
                margin: 10px !important;
            }
        }

        @media (max-width: 768px) {
            .main {
                margin-left: 0;
            }

            .page-content {
                padding: 22px 20px 40px;
                width: 100%;
            }
        }

        @media (max-width: 560px) {
            .page-content {
                padding: 14px 14px 36px;
            }

            .stats-row {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 16px 14px 14px !important;
            }

            .stat-info .stat-val {
                font-size: 24px !important;
            }

            .form-grid,
            .role-grid,
            .perm-grid {
                grid-template-columns: 1fr;
            }

            .action-group {
                flex-wrap: wrap;
            }

            /* Data table: make it horizontally scrollable */
            .table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            }

            /* Keep at least name + action columns */
            .data-table th:nth-child(4),
            .data-table td:nth-child(4),
            .data-table th:nth-child(5),
            .data-table td:nth-child(5) {
                display: none !important;
            }

            /* Modal footer: full-width buttons */
            .modal-foot {
                flex-direction: column !important;
                gap: 8px !important;
            }

            .modal-foot .btn-secondary,
            .modal-foot .btn-primary,
            .modal-foot .btn-danger,
            .modal-foot .btn-purple {
                width: 100% !important;
                justify-content: center !important;
            }

            /* Toast stack: wider on mobile */
            .toast-stack {
                right: 12px !important;
                left: 12px !important;
                bottom: 16px !important;
            }

            .toast {
                min-width: unset !important;
                width: 100% !important;
            }
        }

        @media (max-width: 768px) {
            .card-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            
            .search-wrap {
                min-width: 100%;
                max-width: 100%;
            }
            
            .toolbar-right {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .btn-secondary {
                font-size: 12px;
                padding: 8px 12px;
            }
            
            .bulk-bar {
                padding: 10px 15px;
                gap: 8px;
            }
            
            .btn-bulk {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            .tab-bar {
                gap: 1px;
                padding: 8px 8px 0;
            }
            
            .tab-btn {
                padding: 8px 10px;
                font-size: 11px;
                gap: 4px;
                min-width: 80px;
                flex: 1;
            }
            
            .tab-btn .badge {
                font-size: 9px;
                padding: 1px 4px;
            }
        }

        @media (max-width: 480px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }

            .page-title-text {
                font-size: 20px !important;
            }

            /* Shrink action icon buttons so they fit */
            .btn-icon {
                width: 30px !important;
                height: 30px !important;
                font-size: 11px !important;
            }

            /* Tighten table cells */
            .data-table td,
            .data-table th {
                padding: 10px 12px !important;
            }
        }

        /* Accessibility Enhancements */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Focus indicators */
        *:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 2px;
        }

        .btn-icon:focus-visible,
        .btn-approve:focus-visible,
        .btn-reject:focus-visible,
        .btn-primary:focus-visible,
        .btn-secondary:focus-visible,
        .btn-danger:focus-visible,
        .btn-purple:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(59, 98, 245, 0.2);
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            :root {
                --text-muted: #374151;
                --border: #9ca3af;
                --border-soft: #d1d5db;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Loading states */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.6;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--border);
            border-top: 2px solid var(--blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Skip to main content link */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--blue);
            color: white;
            padding: 8px;
            text-decoration: none;
            border-radius: 4px;
            z-index: 10000;
        }

        .skip-link:focus {
            top: 6px;
        }

        /* Advanced Search Panel */
        .advanced-search-panel {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-top: none;
            padding: 16px 22px;
            margin: 0;
        }

        .search-filters {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .date-range {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-range span {
            color: var(--text-muted);
            font-size: 13px;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: end;
        }

        @media (max-width: 900px) {
            .search-filters {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .filter-actions {
                flex-wrap: wrap;
            }
        }

        /* Pagination Styles */
        .pagination-wrapper {
            padding: 20px 22px;
            border-top: 1px solid var(--border-soft);
            background: var(--surface);
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .pagination-info {
            font-size: 13px;
            color: var(--text-muted);
        }

        .pagination-controls {
            display: flex;
            gap: 4px;
            align-items: center;
        }

        .pagination-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 13px;
            color: var(--text-secondary);
            transition: all .2s;
            background: var(--surface);
        }

        .pagination-link:hover {
            background: var(--surface-2);
            border-color: var(--blue);
            color: var(--blue);
        }

        .pagination-link.active {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue);
        }

        @media (max-width: 768px) {
            .pagination {
                flex-direction: column;
                gap: 12px;
            }
            
            .pagination-controls {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <?php include 'admin_nav.php'; ?>
    <script>
        // Initialize navigation functionality after include
        if (typeof initializeNavigation === 'function') {
            initializeNavigation();
        }
    </script>

    <section class="page-content" id="main-content" role="main" aria-label="Sub-Admin Management">
        <div class="toast-stack" id="toastStack" role="status" aria-live="polite" aria-label="Notifications"></div>

        <?php if (isset($_SESSION['success'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => showToast(<?php echo json_encode($_SESSION['success']); ?>, 'success'));
            </script>
        <?php unset($_SESSION['success']);
        endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', () => showToast(<?php echo json_encode($_SESSION['error']); ?>, 'error'));
            </script>
        <?php unset($_SESSION['error']);
        endif; ?>

        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <p class="breadcrumb">
                    <a href="admin_dashboard.php">Home</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Sub-Admins</span>
                </p>
                <h1 class="page-title-text">Sub-Admin Management</h1>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon-wrap blue"><i class="fas fa-users-gear"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $count_approved; ?></div>
                    <div class="stat-lbl">Active Sub-Admins</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap amber"><i class="fas fa-user-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $count_pending; ?></div>
                    <div class="stat-lbl">Pending Approval</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap green"><i class="fas fa-circle-check"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $count_active; ?></div>
                    <div class="stat-lbl">Active This Week</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap red"><i class="fas fa-user-xmark"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $count_rejected; ?></div>
                    <div class="stat-lbl">Rejected</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-wrap purple"><i class="fas fa-ban"></i></div>
                <div class="stat-info">
                    <div class="stat-val"><?php echo $count_suspended; ?></div>
                    <div class="stat-lbl">Suspended</div>
                </div>
            </div>
        </div>


        <!-- Tabs -->
        <div class="tab-bar" role="tablist" aria-label="Sub-Admin Sections">
            <button class="tab-btn pending-tab active" role="tab" aria-selected="true" aria-controls="panel-pending" onclick="switchTab('pending',this)">
                <i class="fas fa-user-clock"></i> Pending Requests
                <span class="badge"><?php echo count($pending_subadmins); ?></span>
            </button>
            <button class="tab-btn" role="tab" aria-selected="false" aria-controls="panel-approved" onclick="switchTab('approved',this)">
                <i class="fas fa-users-gear"></i> Approved Sub-Admins
                <span class="badge"><?php echo count($approved_subadmins); ?></span>
            </button>
            <button class="tab-btn rejected-tab" role="tab" aria-selected="false" aria-controls="panel-rejected" onclick="switchTab('rejected',this)">
                <i class="fas fa-ban"></i> Rejected
                <span class="badge"><?php echo $count_rejected; ?></span>
            </button>
            <button class="tab-btn logs-tab" role="tab" aria-selected="false" aria-controls="panel-logs" onclick="switchTab('logs',this)">
                <i class="fas fa-scroll"></i> Activity Logs
                <span class="badge"><?php echo count($admin_logs); ?></span>
            </button>
        </div>

        <!-- Main Card -->
        <div class="main-card">

            <!-- ══ PENDING TAB ══ -->
            <div class="tab-panel active" id="panel-pending" role="tabpanel" aria-labelledby="pending-tab" tabindex="0">
                <div class="card-toolbar">
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" id="advanced-search-pending" placeholder="Search pending…" aria-label="Search pending requests">
                        <button class="btn-icon" onclick="toggleAdvancedSearch('pending')" aria-label="Advanced search options" title="Advanced Search">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                    <div class="toolbar-right">
                        <span style="font-size:13px;color:#6b7280;"><?php echo count($pending_subadmins); ?> request<?php echo count($pending_subadmins) !== 1 ? 's' : ''; ?> awaiting review</span>
                    </div>
                </div>

                <!-- Advanced Search Panel -->
                <div class="advanced-search-panel" id="advanced-search-pending" style="display: none;">
                    <div class="search-filters">
                        <div class="filter-group">
                            <label>Date Range</label>
                            <div class="date-range">
                                <input type="date" id="pending-date-from" class="filter-date" placeholder="From">
                                <span>to</span>
                                <input type="date" id="pending-date-to" class="filter-date" placeholder="To">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>Application Method</label>
                            <select id="pending-method" class="filter-select">
                                <option value="">All Methods</option>
                                <option value="online">Online Application</option>
                                <option value="invitation">Invitation</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button class="btn-primary" onclick="applyAdvancedSearch('pending')">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <button class="btn-secondary" onclick="clearAdvancedSearch('pending')">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bulk bar -->
                <div class="bulk-bar" id="bulk-bar-pending">
                    <span class="bulk-count" id="bulk-count-pending">0 selected</span>
                    <button class="btn-bulk btn-bulk-approve" onclick="submitBulk('pending','approve')"><i class="fas fa-check"></i> Approve Selected</button>
                    <button class="btn-bulk btn-bulk-reject" onclick="submitBulk('pending','reject')"><i class="fas fa-times"></i> Reject Selected</button>
                    <button class="btn-bulk btn-bulk-delete" onclick="submitBulk('pending','delete')"><i class="fas fa-trash"></i> Delete Selected</button>
                    <button class="btn-bulk btn-bulk-cancel" onclick="clearSelection('pending')">Cancel</button>
                </div>

                <?php if (count($pending_subadmins) > 0): ?>
                    <div class="table-wrap">
                        <table class="data-table" id="pending-table" role="table" aria-label="Pending Sub-Admin Requests">
                            <thead>
                                <tr role="row">
                                    <th class="col-check" scope="col">
                                        <input type="checkbox" class="row-cb" id="cb-all-pending" onchange="toggleAll('pending',this)" aria-label="Select all pending requests">
                                    </th>
                                    <th scope="col">Applicant</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Username</th>
                                    <th scope="col">Date Applied</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_subadmins as $s): ?>
                                    <tr class="row-pending" data-id="<?php echo $s['id']; ?>" role="row">
                                        <td><input type="checkbox" class="row-cb pending-cb" value="<?php echo $s['id']; ?>" onchange="updateBulkBar('pending')" aria-label="Select <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>"></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="avatar" style="background:<?php echo getAvatarColor($s['first_name']); ?>">
                                                    <?php echo getInitials($s['first_name'], $s['last_name']); ?>
                                                </div>
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></div>
                                                    <div class="user-username">ID #<?php echo $s['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($s['email']); ?>"><?php echo htmlspecialchars($s['email']); ?></div></td>
                                        <td style="color:#6b7280;"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="@<?php echo htmlspecialchars($s['username']); ?>">@<?php echo htmlspecialchars($s['username']); ?></div></td>
                                        <td class="date-cell"><?php echo isset($s['created_at']) ? date('M d, Y', strtotime($s['created_at'])) : '—'; ?></td>
                                        <td>
                                            <div class="action-group">
                                                <button class="btn-approve" onclick="openApproveModal('<?php echo $s['id']; ?>','<?php echo htmlspecialchars(addslashes($s['first_name'] . ' ' . $s['last_name'])); ?>')" aria-label="Approve <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn-reject" onclick="openRejectModal('<?php echo $s['id']; ?>','<?php echo htmlspecialchars(addslashes($s['first_name'] . ' ' . $s['last_name'])); ?>')" aria-label="Reject <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                                <button class="btn-icon edit" title="Edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>)" aria-label="Edit <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><!-- /table-wrap -->
                    
                    <!-- Pagination for Pending -->
                    <?php if ($pagination_pending['total_pages'] > 1): ?>
                    <div class="pagination-wrapper">
                        <div class="pagination">
                            <span class="pagination-info">
                                Showing <?php echo ($pagination_pending['offset'] + 1); ?>-<?php echo min($pagination_pending['offset'] + $pagination_pending['per_page'], $pagination_pending['total_records']); ?> 
                                of <?php echo $pagination_pending['total_records']; ?> requests
                            </span>
                            <div class="pagination-controls">
                                <?php if ($pagination_pending['current_page'] > 1): ?>
                                    <a href="?tab=pending&page=<?php echo $pagination_pending['current_page'] - 1; ?>" class="pagination-link">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $pagination_pending['current_page'] - 2);
                                $end_page = min($pagination_pending['total_pages'], $pagination_pending['current_page'] + 2);
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?tab=pending&page=<?php echo $i; ?>" class="pagination-link <?php echo $i == $pagination_pending['current_page'] ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($pagination_pending['current_page'] < $pagination_pending['total_pages']): ?>
                                    <a href="?tab=pending&page=<?php echo $pagination_pending['current_page'] + 1; ?>" class="pagination-link">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                        <h4>No Pending Requests</h4>
                        <p>All sub-admin applications have been reviewed.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ APPROVED TAB ══ -->
            <div class="tab-panel" id="panel-approved">
                <!-- Filter Toolbar -->
                <form method="GET" action="admins.php" id="filter-form">
                    <input type="hidden" name="tab" value="approved">
                    <div class="card-toolbar">
                        <div class="search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" class="search-input" placeholder="Search sub-admins…" value="<?php echo htmlspecialchars($search_q); ?>">
                        </div>
                        <div class="toolbar-right">
                            <select name="filter_role" class="filter-select" onchange="this.form.submit()">
                                <option value="">All Roles</option>
                                <?php foreach ($all_roles as $rv => $rl): ?>
                                    <option value="<?php echo $rv; ?>" <?php echo $filter_role === $rv ? 'selected' : ''; ?>><?php echo $rl; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="filter_status" class="filter-select" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                            <select name="filter_active" class="filter-select" onchange="this.form.submit()">
                                <option value="">Any Activity</option>
                                <option value="24h" <?php echo $filter_last_active === '24h' ? 'selected' : ''; ?>>Active 24h</option>
                                <option value="7d" <?php echo $filter_last_active === '7d' ? 'selected' : ''; ?>>Active 7d</option>
                                <option value="30d" <?php echo $filter_last_active === '30d' ? 'selected' : ''; ?>>Active 30d</option>
                                <option value="never" <?php echo $filter_last_active === 'never' ? 'selected' : ''; ?>>Never Active</option>
                            </select>
                            <input type="date" name="date_from" class="filter-date" placeholder="From" value="<?php echo htmlspecialchars($filter_date_from); ?>" onchange="this.form.submit()">
                            <input type="date" name="date_to" class="filter-date" placeholder="To" value="<?php echo htmlspecialchars($filter_date_to);   ?>" onchange="this.form.submit()">
                            <?php if ($filter_role || $filter_status || $filter_last_active || $filter_date_from || $filter_date_to || $search_q): ?>
                                <a href="admins.php" class="btn-filter-clear"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <!-- Bulk bar -->
                <div class="bulk-bar" id="bulk-bar-approved">
                    <span class="bulk-count" id="bulk-count-approved">0 selected</span>
                    <button class="btn-bulk btn-bulk-approve" onclick="submitBulk('approved','approve')"><i class="fas fa-check"></i> Approve Selected</button>
                    <button class="btn-bulk btn-bulk-reject" onclick="submitBulk('approved','reject')"><i class="fas fa-times"></i> Reject Selected</button>
                    <button class="btn-bulk btn-bulk-delete" onclick="submitBulk('approved','delete')"><i class="fas fa-trash"></i> Delete Selected</button>
                    <button class="btn-bulk btn-bulk-cancel" onclick="clearSelection('approved')">Cancel</button>
                </div>

                <?php if (count($approved_subadmins) > 0): ?>
                    <div class="table-wrap">
                        <table class="data-table" id="approved-table">
                            <thead>
                                <tr>
                                    <th class="col-check"><input type="checkbox" class="row-cb" id="cb-all-approved" onchange="toggleAll('approved',this)"></th>
                                    <th>Sub-Admin</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Last Active</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_subadmins as $s):
                                    $roles = array_map('trim', explode(',', $s['role'] ?? 'news_admin'));
                                    $isSuspended = $s['status'] === 'suspended';
                                    $lastActive  = $s['last_active'] ?? null;
                                    $activeClass = 'active-old';
                                    if ($lastActive) {
                                        $mins = (time() - strtotime($lastActive)) / 60;
                                        if ($mins < 30)  $activeClass = 'active-online';
                                        elseif ($mins < 10080) $activeClass = 'active-recent';
                                    }
                                ?>
                                    <tr data-id="<?php echo $s['id']; ?>" class="<?php echo $isSuspended ? 'row-suspended' : ''; ?>">
                                        <td><input type="checkbox" class="row-cb approved-cb" value="<?php echo $s['id']; ?>" onchange="updateBulkBar('approved')"></td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="avatar" style="background:<?php echo $isSuspended ? '#9ca3af' : getAvatarColor($s['first_name']); ?>;<?php echo $isSuspended ? 'filter:grayscale(1)' : ''; ?>">
                                                    <?php echo getInitials($s['first_name'], $s['last_name']); ?>
                                                </div>
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></div>
                                                    <div class="user-username">@<?php echo htmlspecialchars($s['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($s['email']); ?>"><?php echo htmlspecialchars($s['email']); ?></div></td>
                                        <td>
                                            <div class="role-multi">
                                                <?php foreach ($roles as $r):
                                                    $rc = roleBadgeColors($r); ?>
                                                    <span class="role-chip" style="background:<?php echo $rc[0]; ?>;color:<?php echo $rc[1]; ?>">
                                                        <?php echo htmlspecialchars(roleLabel($r)); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="last-active-info">
                                                <div class="last-active <?php echo $activeClass; ?>">
                                                    <?php if ($activeClass === 'active-online'): ?><i class="fas fa-circle" style="font-size:8px;"></i> <?php endif; ?>
                                                    <?php echo timeAgo($lastActive); ?>
                                                </div>
                                                <?php if ($lastActive): ?>
                                                    <div class="logout-time">
                                                        <small>Logout: <?php echo date('M d, Y h:i A', strtotime($lastActive)); ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="logout-time">
                                                        <small>No login history</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge badge-<?php echo $isSuspended ? 'suspended' : 'approved'; ?>">
                                                <?php echo $isSuspended ? 'Suspended' : 'Approved'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-group">
                                                <?php if ($isSuspended): ?>
                                                    <button class="btn-approve" onclick="openUnsuspendModal('<?php echo $s['id']; ?>','<?php echo htmlspecialchars(addslashes($s['first_name'] . ' ' . $s['last_name'])); ?>')" aria-label="Cancel Suspension for <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>">
                                                        <i class="fas fa-rotate-left"></i> Cancel Suspension
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn-reject" onclick="openRejectModal('<?php echo $s['id']; ?>','<?php echo htmlspecialchars(addslashes($s['first_name'] . ' ' . $s['last_name'])); ?>')" aria-label="Reject <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                                <button class="btn-icon edit" title="Edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>)" aria-label="Edit <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><!-- /table-wrap -->
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-user-shield"></i></div>
                        <h4>No Sub-Admins Found</h4>
                        <p><?php echo ($filter_role || $filter_status || $filter_last_active || $search_q) ? 'Try adjusting your filters.' : 'Approve pending requests to grant access.'; ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ REJECTED TAB ══ -->
            <div class="tab-panel" id="panel-rejected">
                <div class="card-toolbar">
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" placeholder="Search rejected accounts…" oninput="filterTable('rejected-table',this.value)">
                    </div>
                    <div class="toolbar-right">
                        <span style="font-size:13px;color:#6b7280;">Last 20 records</span>
                    </div>
                </div>
                <?php if (count($rejected_subadmins) > 0): ?>
                    <div class="table-wrap">
                        <table class="data-table" id="rejected-table">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Rejected On</th>
                                    <th>Reason</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejected_subadmins as $s): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="avatar" style="background:#9ca3af;">
                                                    <?php echo getInitials($s['first_name'], $s['last_name']); ?>
                                                </div>
                                                <div>
                                                    <div class="user-name"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></div>
                                                    <div class="user-username">ID #<?php echo $s['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                                        <td style="color:#6b7280;">@<?php echo htmlspecialchars($s['username']); ?></td>
                                        <td class="date-cell"><?php echo isset($s['rejected_at']) ? date('M d, Y', strtotime($s['rejected_at'])) : '—'; ?></td>
                                        <td style="font-size:13px;color:#6b7280;max-width:200px;"><?php echo htmlspecialchars($s['reject_reason'] ?? '—'); ?></td>
                                        <td>
                                            <div class="actions">
                                                <button class="btn-icon approve" title="Re-approve" onclick="openApproveModal('<?php echo $s['id']; ?>','<?php echo htmlspecialchars(addslashes($s['first_name'] . ' ' . $s['last_name'])); ?>')">
                                                    <i class="fas fa-rotate-left"></i>
                                                </button>
                                                <button class="btn-icon delete" title="Delete" onclick="openDeleteModal('<?php echo $s['id']; ?>','<?php echo htmlspecialchars(addslashes($s['first_name'] . ' ' . $s['last_name'])); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div><!-- /table-wrap -->
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-check-double"></i></div>
                        <h4>No Rejected Accounts</h4>
                        <p>No sub-admin applications have been rejected.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ══ LOGS TAB ══ -->
            <div class="tab-panel" id="panel-logs">
                <div class="card-toolbar">
                    <div class="search-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" placeholder="Search logs…" oninput="filterLogs(this.value)">
                    </div>
                    <div class="toolbar-right">
                        <span style="font-size:13px;color:#6b7280;">Last 50 actions</span>
                    </div>
                </div>
                <?php if (count($admin_logs) > 0): ?>
                    <div class="log-list" id="log-list">
                        <?php
                        $logIcons = [
                            'approve'      => ['fas fa-user-check', 'approve'],
                            'reject'       => ['fas fa-user-xmark', 'reject'],
                            'delete'       => ['fas fa-trash', 'delete'],
                            'edit'         => ['fas fa-pen', 'edit'],
                            'revoke'       => ['fas fa-shield-halved', 'revoke'],
                            'suspend'      => ['fas fa-ban', 'suspend'],
                            'bulk_approve' => ['fas fa-check-double', 'bulk_approve'],
                            'bulk_reject'  => ['fas fa-times-circle', 'bulk_reject'],
                            'bulk_delete'  => ['fas fa-trash-can', 'bulk_delete'],
                        ];
                        $actionLabels = [
                            'approve' => 'Approved',
                            'reject' => 'Rejected',
                            'delete' => 'Deleted',
                            'edit' => 'Edited',
                            'revoke' => 'Revoked Access',
                            'suspend' => 'Suspended',
                            'bulk_approve' => 'Bulk Approved',
                            'bulk_reject' => 'Bulk Rejected',
                            'bulk_delete' => 'Bulk Deleted',
                        ];
                        foreach ($admin_logs as $log):
                            $ico = $logIcons[$log['action']] ?? ['fas fa-info-circle', 'edit'];
                        ?>
                            <div class="log-item" data-text="<?php echo htmlspecialchars(strtolower($log['action'] . ' ' . $log['description'])); ?>">
                                <div class="log-icon <?php echo $ico[1]; ?>">
                                    <i class="<?php echo $ico[0]; ?>"></i>
                                </div>
                                <div class="log-body">
                                    <div class="log-action"><?php echo htmlspecialchars($actionLabels[$log['action']] ?? ucfirst($log['action'])); ?></div>
                                    <div class="log-desc"><?php echo htmlspecialchars($log['description']); ?></div>
                                </div>
                                <div class="log-time"><?php echo timeAgo($log['created_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-scroll"></i></div>
                        <h4>No Activity Logs Yet</h4>
                        <p>Actions performed on sub-admins will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /.main-card -->

        <!-- Hidden bulk form -->
        <form method="POST" id="bulk-form" style="display:none;">
            <input type="hidden" name="action" value="bulk_action">
            <input type="hidden" name="bulk_type" id="bulk-form-type">
            <input type="hidden" name="bulk_ids" id="bulk-form-ids">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        </form>
    </section>

    <!-- ════════════════════════════════
     MODALS
════════════════════════════════ -->

    <!-- Approve Modal -->
    <div class="modal-overlay" id="modal-approve">
        <div class="modal-box">
            <div class="modal-icon-banner">
                <div class="icon-circle green"><i class="fas fa-user-check"></i></div>
                <h4>Approve Sub-Admin?</h4>
                <p>You are about to approve <strong id="approve-name"></strong>.<br>They will gain sub-admin access to the system.</p>
            </div>
            <div class="modal-foot" style="border-top:1px solid #f3f4f6;padding-top:20px;">
                <button class="btn-secondary" onclick="closeModal('modal-approve')">Cancel</button>
                <form method="post" style="display:contents;">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="subadmin_id" id="approve-id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Yes, Approve</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal-overlay" id="modal-reject">
        <div class="modal-box">
            <div class="modal-head">
                <h3><i class="fas fa-user-xmark" style="color:#ef4444;margin-right:8px;"></i>Reject Application</h3>
                <button class="modal-close" onclick="closeModal('modal-reject')"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <p style="margin:0 0 16px;color:#6b7280;font-size:14px;">Rejecting <strong id="reject-name"></strong> will mark their account as rejected. You may optionally provide a reason.</p>
                <form method="post" id="reject-form">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="subadmin_id" id="reject-id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="form-group">
                        <label>Reason for Rejection <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                        <textarea name="reject_reason" placeholder="e.g. Incomplete credentials, duplicate account…"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-foot">
                <button class="btn-secondary" onclick="closeModal('modal-reject')">Cancel</button>
                <button class="btn-danger" onclick="document.getElementById('reject-form').submit()"><i class="fas fa-times"></i> Reject</button>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="modal-edit">
        <div class="modal-box wide">
            <div class="modal-head">
                <h3><i class="fas fa-pen" style="color:#3b82f6;margin-right:8px;"></i>Edit Sub-Admin</h3>
                <button class="modal-close" onclick="closeModal('modal-edit')"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form method="post" id="edit-form">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="subadmin_id" id="edit-id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" id="edit-firstname" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" id="edit-lastname" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" id="edit-email" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" id="edit-username" required>
                        </div>

                        <!-- Roles -->
                        <div class="form-group full">
                            <label>Roles <span style="color:#9ca3af;font-weight:400;">(select one or more)</span></label>
                            <div class="role-grid" id="role-grid">
                                <?php foreach ($all_roles as $rv => $rl): ?>
                                    <label class="role-item" id="role-item-<?php echo $rv; ?>">
                                        <input type="checkbox" name="roles[]" value="<?php echo $rv; ?>" class="role-cb" onchange="toggleItemClass(this,'role-item-<?php echo $rv; ?>')">
                                        <span><?php echo $rl; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Permissions -->
                        <div class="form-group full">
                            <label>Permissions</label>
                            <div class="perm-grid" id="perm-grid">
                                <?php foreach ($all_permissions as $pv => $pl): ?>
                                    <label class="perm-item" id="perm-item-<?php echo $pv; ?>">
                                        <input type="checkbox" name="permissions[]" value="<?php echo $pv; ?>" class="perm-cb" onchange="toggleItemClass(this,'perm-item-<?php echo $pv; ?>')">
                                        <span><?php echo $pl; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-foot">
                <div class="modal-foot-left">
                    <button type="button" class="btn-icon suspend" title="Suspend" onclick="openSuspendModal(document.getElementById('edit-id').value, (document.getElementById('edit-firstname').value + ' ' + document.getElementById('edit-lastname').value))">
                        <i class="fas fa-ban"></i>
                    </button>
                    <button type="button" class="btn-icon revoke" title="Revoke Access" onclick="openRevokeModal(document.getElementById('edit-id').value, (document.getElementById('edit-firstname').value + ' ' + document.getElementById('edit-lastname').value))">
                        <i class="fas fa-shield-halved"></i>
                    </button>
                    <button type="button" class="btn-icon delete" title="Delete" onclick="openDeleteModal(document.getElementById('edit-id').value, (document.getElementById('edit-firstname').value + ' ' + document.getElementById('edit-lastname').value))">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="modal-foot-right">
                    <button class="btn-secondary" onclick="closeModal('modal-edit')">Cancel</button>
                    <button class="btn-primary" onclick="document.getElementById('edit-form').submit()"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Suspend Modal -->
    <div class="modal-overlay" id="modal-suspend">
        <div class="modal-box">
            <div class="modal-icon-banner">
                <div class="icon-circle purple"><i class="fas fa-ban"></i></div>
                <h4>Suspend Sub-Admin?</h4>
                <p>Suspending <strong id="suspend-name"></strong> will immediately prevent them from logging in.<br>
                    <span style="color:#8b5cf6;font-weight:600;">They can be re-activated later.</span>
                </p>
            </div>
            <div class="modal-body" style="padding:20px 24px;">
                <div class="form-group">
                    <label for="suspend-reason" style="display:block;margin-bottom:8px;font-weight:600;color:#374151;">Reason for Suspension <span style="color:#ef4444;">*</span></label>
                    <textarea id="suspend-reason" name="suspend_reason" rows="3" placeholder="Please provide a reason for suspending this sub-admin account..." required style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;resize:vertical;"></textarea>
                </div>
            </div>
            <div class="modal-foot" style="border-top:1px solid #f3f4f6;padding-top:20px;">
                <button class="btn-secondary" onclick="closeModal('modal-suspend')">Cancel</button>
                <form method="post" style="display:contents;">
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="subadmin_id" id="suspend-id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" class="btn-purple" onclick="return validateSuspendForm();"><i class="fas fa-ban"></i> Suspend Account</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Unsuspend Modal -->
    <div class="modal-overlay" id="modal-unsuspend">
        <div class="modal-box">
            <div class="modal-icon-banner">
                <div class="icon-circle green"><i class="fas fa-rotate-left"></i></div>
                <h4>Cancel Suspension?</h4>
                <p>This will restore <strong id="unsuspend-name"></strong>'s access to the Sub-Admin system.<br>
                    <span style="color:#10b981;font-weight:600;">They will be able to log in again.</span>
                </p>
            </div>
            <div class="modal-foot" style="border-top:1px solid #f3f4f6;padding-top:20px;">
                <button class="btn-secondary" onclick="closeModal('modal-unsuspend')">Cancel</button>
                <form method="post" style="display:contents;">
                    <input type="hidden" name="action" value="unsuspend">
                    <input type="hidden" name="subadmin_id" id="unsuspend-id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" class="btn-primary"><i class="fas fa-rotate-left"></i> Cancel Suspension</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Revoke Modal -->
    <div class="modal-overlay" id="modal-revoke">
        <div class="modal-box">
            <div class="modal-icon-banner">
                <div class="icon-circle" style="background:#fef3c7;color:#d97706;"><i class="fas fa-shield-halved"></i></div>
                <h4>Revoke Access?</h4>
                <p>Revoking <strong id="revoke-name"></strong>'s access will move them back to <em>Pending</em> status until re-approved.</p>
            </div>
            <div class="modal-foot" style="border-top:1px solid #f3f4f6;padding-top:20px;">
                <button class="btn-secondary" onclick="closeModal('modal-revoke')">Cancel</button>
                <form method="post" style="display:contents;">
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="subadmin_id" id="revoke-id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" style="padding:10px 22px;border-radius:8px;border:none;background:#f59e0b;color:#fff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;">
                        <i class="fas fa-shield-halved"></i> Revoke Access
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal-overlay" id="modal-delete">
        <div class="modal-box">
            <div class="modal-icon-banner">
                <div class="icon-circle red"><i class="fas fa-trash"></i></div>
                <h4>Delete Permanently?</h4>
                <p>This will permanently remove <strong id="delete-name"></strong> from the system.<br>
                    <span style="color:#ef4444;font-weight:600;">This action cannot be undone.</span>
                </p>
            </div>
            <div class="modal-foot" style="border-top:1px solid #f3f4f6;padding-top:20px;">
                <button class="btn-secondary" onclick="closeModal('modal-delete')">Cancel</button>
                <form method="post" style="display:contents;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="subadmin_id" id="delete-id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" class="btn-danger"><i class="fas fa-trash"></i> Delete Forever</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ════════════════ SCRIPTS ════════════════ -->
    <script>
        // ── Nav Loader ──────────────────────────────
        fetch('admin_nav.php')
            .then(r => r.text())
            .then(html => {
                document.getElementById('navigation-container').innerHTML = html;
                const main = document.querySelector('.main');
                const page = document.querySelector('.page-content');
                if (main && page) main.appendChild(page);
                initDropdowns();
                initMobileNav(); 
            })
            .catch(e => console.error('Nav error:', e));

        function initDropdowns() {
            // Initialize Navigation Functionality (already handled by admin_nav.php)
            // No need for dropdown path fixing since PHP handles it correctly
            if (typeof initializeNavigation === 'function') {
                initializeNavigation();
            }
        }

        // Mobile hamburger sidebar toggle
        // Defined here (not in admin_nav.php) because nav HTML is injected via
        // fetch() + innerHTML, and browsers do NOT execute scripts in innerHTML.
        function initMobileNav() {
            var hamburger = document.getElementById('navHamburgerBtn');
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (!hamburger || !sidebar || !overlay) return;

            // Clone to remove any stale listeners from a previous load
            var btn = hamburger.cloneNode(true);
            hamburger.parentNode.replaceChild(btn, hamburger);

            function openSidebar() {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('visible');
                btn.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }

            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
            });

            overlay.addEventListener('click', closeSidebar);

            sidebar.querySelectorAll('a.menu-item').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 900) closeSidebar();
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 900) closeSidebar();
            });
        }

        // ── Tab Switching ───────────────────────────
        function switchTab(name, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('panel-' + name).classList.add('active');
        }

        // Auto-switch tab if filter param present
        <?php if (!empty($_GET['tab'])): ?>
            document.addEventListener('DOMContentLoaded', () => {
                const tabBtn = document.querySelector('.tab-btn[onclick*="<?php echo htmlspecialchars($_GET['tab']); ?>"]');
                if (tabBtn) switchTab('<?php echo htmlspecialchars($_GET['tab']); ?>', tabBtn);
            });
        <?php endif; ?>

        // ── Search / Filter ─────────────────────────
        function filterTable(tableId, query) {
            const q = query.toLowerCase();
            document.querySelectorAll('#' + tableId + ' tbody tr').forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function filterLogs(query) {
            const q = query.toLowerCase();
            document.querySelectorAll('#log-list .log-item').forEach(item => {
                item.style.display = item.dataset.text.includes(q) ? '' : 'none';
            });
        }

        // ── Modal Helpers ───────────────────────────
        function openModal(id) {
            document.getElementById(id).classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }

        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => {
                if (e.target === m) closeModal(m.id);
            });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
        });

        // ── Open Modals ─────────────────────────────
        function openApproveModal(id, name) {
            document.getElementById('approve-id').value = id;
            document.getElementById('approve-name').textContent = name;
            openModal('modal-approve');
        }

        function openRejectModal(id, name) {
            document.getElementById('reject-id').value = id;
            document.getElementById('reject-name').textContent = name;
            openModal('modal-reject');
        }

        function openRevokeModal(id, name) {
            document.getElementById('revoke-id').value = id;
            document.getElementById('revoke-name').textContent = name;
            openModal('modal-revoke');
        }

        function openDeleteModal(id, name) {
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-name').textContent = name;
            openModal('modal-delete');
        }

        function openSuspendModal(id, name) {
            document.getElementById('suspend-id').value = id;
            document.getElementById('suspend-name').textContent = name;
            document.getElementById('suspend-reason').value = '';
            openModal('modal-suspend');
        }

        function openUnsuspendModal(id, name) {
            document.getElementById('unsuspend-id').value = id;
            document.getElementById('unsuspend-name').textContent = name;
            openModal('modal-unsuspend');
        }

        function validateSuspendForm() {
            const reason = document.getElementById('suspend-reason').value.trim();
            if (!reason) {
                alert('Please provide a reason for suspension.');
                document.getElementById('suspend-reason').focus();
                return false;
            }
            return true;
        }

        // Enhanced form submission with loading states
        function submitFormWithLoading(formId, buttonId) {
            const form = document.getElementById(formId);
            const button = document.getElementById(buttonId);
            
            if (!form || !button) return;
            
            // Add loading state
            button.classList.add('loading');
            button.disabled = true;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Submit form
            form.submit();
        }

        // ── Edit Modal with Roles + Permissions ─────
        function openEditModal(data) {
            if (typeof data === 'string') data = JSON.parse(data);

            document.getElementById('edit-id').value = data.id || '';
            document.getElementById('edit-firstname').value = data.first_name || '';
            document.getElementById('edit-lastname').value = data.last_name || '';
            document.getElementById('edit-email').value = data.email || '';
            document.getElementById('edit-username').value = data.username || '';

            // Roles
            const roleStr = data.role || '';
            const activeRoles = roleStr.split(',').map(r => r.trim()).filter(Boolean);
            document.querySelectorAll('.role-cb').forEach(cb => {
                const checked = activeRoles.includes(cb.value);
                cb.checked = checked;
                const item = document.getElementById('role-item-' + cb.value);
                if (item) item.classList.toggle('checked', checked);
            });

            // Permissions
            const permStr = data.permissions || '';
            const activePerms = permStr.split(',').map(p => p.trim()).filter(Boolean);
            document.querySelectorAll('.perm-cb').forEach(cb => {
                const checked = activePerms.includes(cb.value);
                cb.checked = checked;
                const item = document.getElementById('perm-item-' + cb.value);
                if (item) item.classList.toggle('checked', checked);
            });

            openModal('modal-edit');
        }

        function toggleItemClass(checkbox, itemId) {
            const item = document.getElementById(itemId);
            if (item) item.classList.toggle('checked', checkbox.checked);
        }

        // ── Bulk Actions ────────────────────────────
        function updateBulkBar(tab) {
            const checked = document.querySelectorAll('.' + tab + '-cb:checked');
            const bar = document.getElementById('bulk-bar-' + tab);
            const count = document.getElementById('bulk-count-' + tab);
            bar.classList.toggle('visible', checked.length > 0);
            count.textContent = checked.length + ' selected';
            // Update select-all state
            const all = document.querySelectorAll('.' + tab + '-cb');
            const cbAll = document.getElementById('cb-all-' + tab);
            if (cbAll) cbAll.indeterminate = checked.length > 0 && checked.length < all.length;
            if (cbAll) cbAll.checked = checked.length === all.length && all.length > 0;
        }

        function toggleAll(tab, master) {
            document.querySelectorAll('.' + tab + '-cb').forEach(cb => cb.checked = master.checked);
            updateBulkBar(tab);
        }

        function clearSelection(tab) {
            document.querySelectorAll('.' + tab + '-cb').forEach(cb => cb.checked = false);
            const cbAll = document.getElementById('cb-all-' + tab);
            if (cbAll) {
                cbAll.checked = false;
                cbAll.indeterminate = false;
            }
            updateBulkBar(tab);
        }

        function submitBulk(tab, type) {
            const checked = document.querySelectorAll('.' + tab + '-cb:checked');
            if (checked.length === 0) {
                showToast('No records selected.', 'error');
                return;
            }
            const ids = Array.from(checked).map(cb => cb.value).join(',');
            if (!confirm('Apply "' + type + '" to ' + checked.length + ' record(s)?')) return;
            
            // Add loading state to bulk bar
            const bulkBar = document.getElementById('bulk-bar-' + tab);
            bulkBar.classList.add('loading');
            
            document.getElementById('bulk-form-type').value = type;
            document.getElementById('bulk-form-ids').value = ids;
            document.getElementById('bulk-form').submit();
        }

        // ── Advanced Search ───────────────────────
        function toggleAdvancedSearch(tab) {
            const panel = document.getElementById('advanced-search-' + tab);
            const isVisible = panel.style.display !== 'none';
            panel.style.display = isVisible ? 'none' : 'block';
            
            // Update button icon
            const button = event.currentTarget;
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = isVisible ? 'fas fa-filter' : 'fas fa-times';
            }
        }

        function applyAdvancedSearch(tab) {
            const searchInput = document.getElementById('advanced-search-' + tab);
            const dateFrom = document.getElementById(tab + '-date-from');
            const dateTo = document.getElementById(tab + '-date-to');
            const method = document.getElementById(tab + '-method');
            
            // Build filter criteria
            const filters = {
                search: searchInput.value.toLowerCase(),
                dateFrom: dateFrom.value,
                dateTo: dateTo.value,
                method: method.value
            };
            
            // Filter table rows
            const table = document.getElementById(tab + '-table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                let show = true;
                
                // Text search
                if (filters.search) {
                    const text = row.textContent.toLowerCase();
                    show = text.includes(filters.search);
                }
                
                // Date range filter (simplified - would need actual date data)
                if (show && (filters.dateFrom || filters.dateTo)) {
                    // This would need actual date parsing from the row
                    // For now, just log the filter
                    console.log('Date filter applied:', filters.dateFrom, 'to', filters.dateTo);
                }
                
                row.style.display = show ? '' : 'none';
            });
            
            showToast('Filters applied', 'success');
        }

        function clearAdvancedSearch(tab) {
            document.getElementById('advanced-search-' + tab).value = '';
            document.getElementById(tab + '-date-from').value = '';
            document.getElementById(tab + '-date-to').value = '';
            document.getElementById(tab + '-method').value = '';
            
            // Reset table
            const table = document.getElementById(tab + '-table');
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => row.style.display = '');
            
            showToast('Filters cleared', 'info');
        }

        // Enhanced search with real-time filtering
        document.addEventListener('DOMContentLoaded', function() {
            const searchInputs = document.querySelectorAll('[id^="advanced-search-"]');
            searchInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const tab = this.id.replace('advanced-search-', '');
                    filterTable(tab + '-table', this.value);
                });
            });
        });

        // ── Export Functionality ─────────────────────
        function exportData(format, tab = 'pending') {
            // Show loading state
            showToast('Preparing export...', 'info');
            
            // Get current filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            const exportParams = new URLSearchParams();
            
            // Add current filters
            if (tab === 'approved') {
                urlParams.forEach((value, key) => {
                    if (key.startsWith('filter_') || key === 'search') {
                        exportParams.set(key, value);
                    }
                });
            }
            
            exportParams.set('export_format', format);
            exportParams.set('export_tab', tab);
            exportParams.set('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            
            // Create download URL
            const downloadUrl = 'admins.php?' + exportParams.toString();
            
            // Create temporary link and trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export started...', 'success');
        }

        // ── Toast ───────────────────────────────────
        function showToast(msg, type = 'info') {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };
            const t = document.createElement('div');
            t.className = 'toast toast-' + type;
            t.innerHTML = `<i class="fas ${icons[type]}"></i><span>${msg}</span>`;
            document.getElementById('toastStack').appendChild(t);
            setTimeout(() => {
                t.style.opacity = '0';
                t.style.transition = 'opacity .4s';
                setTimeout(() => t.remove(), 400);
            }, 3500);
        }
    </script>

    <script>
        // Move page-content into .main (opened by admin_nav.php) — same pattern as admin_profile.php
        (function() {
            var mainDiv = document.querySelector('.main');
            var pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent && !mainDiv.contains(pageContent)) {
                mainDiv.appendChild(pageContent);
            }
        })();
    </script>
    <script src="admin_assets/js/admin_script.js"></script>
</body>

</html>