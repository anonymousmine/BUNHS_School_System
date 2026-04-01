<?php

/**
 * Emergency API - Handles emergency notifications and contacts
 * Uses Twilio for SMS and PHP mail() for emails
 */

session_start();
include '../db_connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Twilio Configuration - Replace with your actual Twilio credentials
// Get these from https://www.twilio.com/console
define('TWILIO_ACCOUNT_SID', 'YOUR_TWILIO_ACCOUNT_SID');
define('TWILIO_AUTH_TOKEN', 'YOUR_TWILIO_AUTH_TOKEN');
define('TWILIO_PHONE_NUMBER', '+1234567890'); // Your Twilio phone number

/**
 * Send SMS via Twilio
 */
function sendSMS($to, $message)
{
    // Remove any non-digit characters except +
    $cleanPhone = preg_replace('/[^\d+]/', '', $to);

    // If phone doesn't start with +, add +63 (Philippines)
    if (substr($cleanPhone, 0, 1) !== '+') {
        if (substr($cleanPhone, 0, 2) === '63') {
            $cleanPhone = '+' . $cleanPhone;
        } else {
            $cleanPhone = '+63' . ltrim($cleanPhone, '0');
        }
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';

    $data = [
        'To' => $cleanPhone,
        'From' => TWILIO_PHONE_NUMBER,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201 || $httpCode === 200) {
        return ['success' => true, 'message' => 'SMS sent successfully'];
    } else {
        $responseData = json_decode($response, true);
        return ['success' => false, 'message' => 'Failed to send SMS: ' . ($responseData['message'] ?? 'Unknown error')];
    }
}

/**
 * Send Email
 */
function sendEmail($to, $subject, $message)
{
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Buyoan National High School <no-reply@bunhs.edu>" . "\r\n";
    $headers .= "Reply-To: no-reply@bunhs.edu" . "\r\n";

    $htmlMessage = "
    <html>
    <head>
        <title>Emergency Alert</title>
    </head>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <div style='background-color: #dc2626; color: white; padding: 20px; text-align: center;'>
            <h1>⚠️ URGENT EMERGENCY ALERT ⚠️</h1>
        </div>
        <div style='padding: 20px; border: 1px solid #ddd;'>
            " . nl2br(htmlspecialchars($message)) . "
        </div>
        <div style='padding: 20px; font-size: 12px; color: #666;'>
            <p>This is an automated emergency notification from Buyoan National High School.</p>
            <p>Please do not reply to this message.</p>
        </div>
    </body>
    </html>
    ";

    if (mail($to, $subject, $htmlMessage, $headers)) {
        return ['success' => true, 'message' => 'Email sent successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to send email'];
    }
}

/**
 * Get all emergency contacts
 */
function getAllContacts($conn)
{
    $sql = "SELECT * FROM emergency_contacts WHERE status = 'active' ORDER BY contact_type, last_name";
    $result = $conn->query($sql);

    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
    return $contacts;
}

/**
 * Get contacts by preference
 */
function getContactsByPreference($conn, $preference)
{
    if ($preference === 'both') {
        $sql = "SELECT * FROM emergency_contacts WHERE status = 'active' AND (phone_number IS NOT NULL OR email IS NOT NULL) ORDER BY last_name";
    } else {
        $column = $preference === 'phone' ? 'phone_number' : 'email';
        $sql = "SELECT * FROM emergency_contacts WHERE status = 'active' AND {$column} IS NOT NULL ORDER BY last_name";
    }

    $result = $conn->query($sql);
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
    return $contacts;
}

/**
 * Add emergency contact
 */
function addContact($conn, $data)
{
    $stmt = $conn->prepare("INSERT INTO emergency_contacts (contact_type, first_name, last_name, phone_number, email, grade_level, notification_preference) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "sssssss",
        $data['contact_type'],
        $data['first_name'],
        $data['last_name'],
        $data['phone_number'],
        $data['email'],
        $data['grade_level'],
        $data['notification_preference']
    );

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Contact added successfully', 'id' => $stmt->insert_id];
    } else {
        return ['success' => false, 'message' => 'Failed to add contact: ' . $stmt->error];
    }
}

/**
 * Update emergency contact
 */
function updateContact($conn, $data)
{
    $stmt = $conn->prepare("UPDATE emergency_contacts SET first_name=?, last_name=?, phone_number=?, email=?, grade_level=?, notification_preference=? WHERE id=?");

    $stmt->bind_param(
        "ssssssi",
        $data['first_name'],
        $data['last_name'],
        $data['phone_number'],
        $data['email'],
        $data['grade_level'],
        $data['notification_preference'],
        $data['id']
    );

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Contact updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to update contact: ' . $stmt->error];
    }
}

/**
 * Delete emergency contact
 */
function deleteContact($conn, $id)
{
    $stmt = $conn->prepare("DELETE FROM emergency_contacts WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Contact deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete contact: ' . $stmt->error];
    }
}

/**
 * Log emergency alert
 */
function logAlert($conn, $data)
{
    $stmt = $conn->prepare("INSERT INTO emergency_alerts (disaster_type, message, recipient_count, sms_sent, email_sent, sent_by) VALUES (?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "ssiiis",
        $data['disaster_type'],
        $data['message'],
        $data['recipient_count'],
        $data['sms_sent'],
        $data['email_sent'],
        $data['sent_by']
    );

    if ($stmt->execute()) {
        return ['success' => true, 'id' => $stmt->insert_id];
    } else {
        return ['success' => false, 'message' => 'Failed to log alert: ' . $stmt->error];
    }
}

/**
 * Get alert history
 */
function getAlertHistory($conn, $limit = 50)
{
    $sql = "SELECT * FROM emergency_alerts ORDER BY sent_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $alerts = [];
    while ($row = $result->fetch_assoc()) {
        $alerts[] = $row;
    }
    return $alerts;
}

/**
 * Get contacts from students table for notification
 */
function getStudentContactsForNotification($conn)
{
    $sql = "SELECT 
                s.id,
                s.first_name,
                s.last_name,
                s.grade_level,
                s.parent_phone,
                s.parent_email,
                s.notification_preference
            FROM students s
            WHERE s.notification_preference != 'none'
            AND (s.parent_phone IS NOT NULL OR s.parent_email IS NOT NULL)";

    $result = $conn->query($sql);
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = $row;
    }
    return $contacts;
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'list_contacts') {
            echo json_encode(getAllContacts($conn));
        } elseif ($action === 'student_contacts') {
            echo json_encode(getStudentContactsForNotification($conn));
        } elseif ($action === 'alert_history') {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            echo json_encode(getAlertHistory($conn, $limit));
        } elseif ($action === 'send_test_sms') {
            // Test endpoint - send SMS to admin
            $result = sendSMS('+639123456789', 'Test emergency SMS from BUNHS Emergency System');
            echo json_encode($result);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            $input = $_POST;
        }

        if ($action === 'send_alert') {
            $disasterType = $input['disaster_type'] ?? '';
            $message = $input['message'] ?? '';
            $sentBy = $input['sent_by'] ?? 'Admin';

            if (empty($disasterType) || empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }

            // Get all contacts
            $emergencyContacts = getAllContacts($conn);
            $studentContacts = getStudentContactsForNotification($conn);

            // Merge contacts and remove duplicates
            $allContacts = [];
            foreach (array_merge($emergencyContacts, $studentContacts) as $contact) {
                $key = ($contact['phone_number'] ?? '') . '|' . ($contact['email'] ?? '');
                if (!isset($allContacts[$key])) {
                    $allContacts[$key] = $contact;
                }
            }

            $smsSent = 0;
            $emailSent = 0;
            $smsErrors = [];
            $emailErrors = [];

            // Send notifications
            foreach ($allContacts as $contact) {
                $pref = $contact['notification_preference'] ?? 'none';
                $phone = $contact['phone_number'] ?? $contact['parent_phone'] ?? '';
                $email = $contact['email'] ?? $contact['parent_email'] ?? '';

                // Send SMS
                if (($pref === 'phone' || $pref === 'both') && !empty($phone)) {
                    $result = sendSMS($phone, $message);
                    if ($result['success']) {
                        $smsSent++;
                    } else {
                        $smsErrors[] = $phone . ': ' . $result['message'];
                    }
                }

                // Send Email
                if (($pref === 'email' || $pref === 'both') && !empty($email)) {
                    $result = sendEmail($email, '⚠️ URGENT: ' . ucwords(str_replace('_', ' ', $disasterType)) . ' Alert', $message);
                    if ($result['success']) {
                        $emailSent++;
                    } else {
                        $emailErrors[] = $email . ': ' . $result['message'];
                    }
                }
            }

            // Log the alert
            $totalRecipients = $smsSent + $emailSent;
            logAlert($conn, [
                'disaster_type' => $disasterType,
                'message' => $message,
                'recipient_count' => $totalRecipients,
                'sms_sent' => $smsSent,
                'email_sent' => $emailSent,
                'sent_by' => $sentBy
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Emergency alert sent',
                'sms_sent' => $smsSent,
                'email_sent' => $emailSent,
                'total_recipients' => $totalRecipients,
                'sms_errors' => $smsErrors,
                'email_errors' => $emailErrors
            ]);
        } elseif ($action === 'add_contact') {
            echo json_encode(addContact($conn, $input));
        } elseif ($action === 'update_contact') {
            echo json_encode(updateContact($conn, $input));
        } elseif ($action === 'delete_contact') {
            $id = $input['id'] ?? 0;
            echo json_encode(deleteContact($conn, $id));
        } elseif ($action === 'sync_students') {
            // Sync students to emergency contacts
            $result = $conn->query("SELECT * FROM students WHERE notification_preference != 'none' AND (parent_phone IS NOT NULL OR parent_email IS NOT NULL)");

            $synced = 0;
            while ($student = $result->fetch_assoc()) {
                // Check if contact already exists
                $checkStmt = $conn->prepare("SELECT id FROM emergency_contacts WHERE phone_number = ? OR email = ?");
                $phone = $student['parent_phone'] ?? '';
                $email = $student['parent_email'] ?? '';
                $checkStmt->bind_param("ss", $phone, $email);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows === 0) {
                    // Insert new contact
                    $insertStmt = $conn->prepare("INSERT INTO emergency_contacts (contact_type, first_name, last_name, phone_number, email, grade_level, notification_preference) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $contactType = 'student_parent';
                    $insertStmt->bind_param(
                        "sssssss",
                        $contactType,
                        $student['first_name'],
                        $student['last_name'],
                        $student['parent_phone'],
                        $student['parent_email'],
                        $student['grade_level'],
                        $student['notification_preference']
                    );
                    $insertStmt->execute();
                    $synced++;
                }
            }

            echo json_encode(['success' => true, 'synced' => $synced, 'message' => "Synced $synced student contacts"]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
