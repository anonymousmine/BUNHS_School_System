<?php
// Session management and role-based access control
include '../../session_config.php';
include '../../db_connection.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: ../../login.php');
    exit();
}

$allowed_roles = ['admin', 'sub-admin'];
if (!in_array($_SESSION['user_type'], $allowed_roles)) {
    header('Location: ../../index.php?error=unauthorized');
    exit();
}

// ============================================================
//  SECURITY FUNCTIONS
// ============================================================

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateEventCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    // Token expires after 1 hour
    if (time() - $_SESSION['csrf_token_time'] > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Input Sanitization
function sanitizeInput($input, $type = 'string') {
    switch ($type) {
        case 'string':
            return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        case 'float':
            return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
        default:
            return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
}

// Output Escaping
function esc($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Comprehensive Input Validation
function validateEventInput($data) {
    $errors = [];
    
    // Required fields validation
    if (empty($data['event_title'])) {
        $errors[] = "Event title is required";
    } elseif (strlen($data['event_title']) > 100) {
        $errors[] = "Event title too long (max 100 characters)";
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-_,.()&\/]+$/', $data['event_title'])) {
        $errors[] = "Event title contains invalid characters";
    }
    
    if (empty($data['event_category'])) {
        $errors[] = "Event category is required";
    }
    
    if (empty($data['event_date'])) {
        $errors[] = "Event date is required";
    } elseif (strtotime($data['event_date']) < strtotime('today')) {
        $errors[] = "Event date cannot be in the past";
    }
    
    // Description validation
    if (!empty($data['event_description']) && strlen($data['event_description']) > 500) {
        $errors[] = "Description too long (max 500 characters)";
    }
    
    // Email validation for organizer contact
    if (!empty($data['organizer_contact'])) {
        if (filter_var($data['organizer_contact'], FILTER_VALIDATE_EMAIL)) {
            // Valid email
        } elseif (preg_match('/^[\d\s\-\+\(\)]+$/', $data['organizer_contact'])) {
            // Valid phone number format
        } else {
            $errors[] = "Organizer contact must be a valid email or phone number";
        }
    }
    
    // Time validation
    if (!empty($data['event_start_time']) && !empty($data['event_end_time'])) {
        if ($data['event_start_time'] >= $data['event_end_time']) {
            $errors[] = "End time must be after start time";
        }
    }
    
    return $errors;
}

// Secure File Upload Validation
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'], $maxSize = 5242880) {
    $errors = [];
    
    // Check if file was uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = "File too large (max 5MB)";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = "File upload was incomplete";
                break;
            case UPLOAD_ERR_NO_FILE:
                // No file uploaded - not an error for optional fields
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = "Server configuration error - no temporary directory";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errors[] = "Server error - cannot write file";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errors[] = "File upload stopped by extension";
                break;
            default:
                $errors[] = "Unknown upload error";
                break;
        }
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = "File too large (max 5MB)";
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        $errors[] = "Invalid file type. Allowed: " . implode(', ', $allowedTypes);
    }
    
    // Validate actual file content (MIME type)
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg',
            'image/png', 
            'image/webp',
            'image/gif'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = "File content does not match extension";
        }
    }
    
    // Check if file is actually an image
    if (!@getimagesize($file['tmp_name'])) {
        $errors[] = "Uploaded file is not a valid image";
    }
    
    return $errors;
}

// Generate CSRF token for this session
$csrf_token = generateCSRFToken();

// ============================================================
//  HELPER: ensure extended columns exist (idempotent)
// ============================================================
function ensure_event_columns($conn)
{
    $checks = [
        "location"             => "VARCHAR(255) DEFAULT NULL",
        "image"                => "VARCHAR(255) DEFAULT NULL",
        "organizer_name"       => "VARCHAR(255) DEFAULT NULL",
        "organizer_position"   => "VARCHAR(255) DEFAULT NULL",
        "organizer_contact"    => "VARCHAR(255) DEFAULT NULL",
        "team_based"           => "TINYINT(1) DEFAULT 0",
        "source"               => "VARCHAR(255) DEFAULT NULL",
        "is_official"          => "TINYINT(1) DEFAULT 0",
    ];
    foreach ($checks as $col => $def) {
        $r = $conn->query("SHOW COLUMNS FROM events LIKE '$col'");
        if ($r->num_rows == 0) {
            $conn->query("ALTER TABLE events ADD COLUMN $col $def");
        }
    }
}

function ensure_feature_tables($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS event_highlights (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        highlight VARCHAR(500) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        time_slot VARCHAR(100) NOT NULL,
        activity VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        registrant_type VARCHAR(50) DEFAULT 'student',
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        UNIQUE KEY uq_application (event_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS event_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        group_name VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        FOREIGN KEY (group_id) REFERENCES event_groups(id) ON DELETE CASCADE,
        UNIQUE KEY uq_group_student (group_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS group_teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        teacher_name VARCHAR(255) NOT NULL,
        FOREIGN KEY (group_id) REFERENCES event_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_event_columns($conn);
ensure_feature_tables($conn);

// Check if DepEd calendar has been seeded
$already_seeded = $conn->query(
    "SELECT COUNT(*) as cnt FROM events WHERE source = 'DepEd Order No. 012, s. 2025'"
)->fetch_assoc()['cnt'];
if ($already_seeded == 0) {
    insert_deped_calendar_events($conn);
}

// ============================================================
//  EXISTING FUNCTIONS (unchanged)
// ============================================================
function insert_news($conn)
{
    $title             = $_POST['title'];
    $short_description = $_POST['short_description'];
    $content           = $_POST['content'];
    $category          = $_POST['category'];
    $news_date         = $_POST['news_date'];
    $author            = $_POST['author'];
    if (empty($news_date)) $news_date = date("Y-m-d");
    if (empty($author))    $author    = "Unknown";
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir  = "../../assets/img/blog/";
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = basename($_FILES["image"]["name"]);
        }
    }
    $stmt = $conn->prepare("INSERT INTO news (title, short_description, content, image, category, news_date, author, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssss", $title, $short_description, $content, $image, $category, $news_date, $author);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function delete_news($conn, $id)
{
    $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
}

function insert_event($conn)
{
    // Server-side validation
    $validation_errors = validateEventInput($_POST);
    if (!empty($validation_errors)) {
        echo json_encode(['status' => 'error', 'message' => implode(', ', $validation_errors)]);
        return false;
    }

    $title            = sanitizeInput($_POST['event_title'], 'string');
    $description      = sanitizeInput($_POST['event_description'], 'string');
    $event_date       = sanitizeInput($_POST['event_date'], 'string');
    $category         = sanitizeInput($_POST['event_category'], 'string');
    $event_start_time = !empty($_POST['event_start_time']) ? sanitizeInput($_POST['event_start_time'], 'string') : null;
    $event_end_time   = !empty($_POST['event_end_time']) ? sanitizeInput($_POST['event_end_time'], 'string') : null;
    $event_days       = isset($_POST['event_days']) ? intval($_POST['event_days']) : 1;
    $team_based       = isset($_POST['team_based']) ? 1 : 0;
    $location         = !empty($_POST['event_location']) ? sanitizeInput($_POST['event_location'], 'string') : null;
    $organizer_name   = !empty($_POST['organizer_name']) ? sanitizeInput($_POST['organizer_name'], 'string') : null;
    $organizer_pos    = !empty($_POST['organizer_position']) ? sanitizeInput($_POST['organizer_position'], 'string') : null;
    $organizer_contact = !empty($_POST['organizer_contact']) ? sanitizeInput($_POST['organizer_contact'], 'string') : null;
    $source           = !empty($_POST['event_source']) ? sanitizeInput($_POST['event_source'], 'string') : null;
    $is_official      = isset($_POST['is_official']) ? 1 : 0;

    if ($event_days < 1) $event_days = 1;

    // Validate time logic
    if ($event_start_time && $event_end_time && $event_start_time >= $event_end_time) {
        echo json_encode(['status' => 'error', 'message' => 'End time must be after start time.']);
        return false;
    }

    // Handle image upload with security validation
    $image = '';
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $file_errors = validateFileUpload($_FILES['event_image']);
        if (!empty($file_errors)) {
            echo json_encode(['status' => 'error', 'message' => implode(', ', $file_errors)]);
            return false;
        }
        
        $target_dir  = "../../assets/img/events/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $ext         = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $filename    = 'event_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
            $image = $filename;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded file.']);
            return false;
        }
    }

    $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, category, event_start_time, event_end_time, event_days, team_based, location, image, organizer_name, organizer_position, organizer_contact, source, is_official, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssssiisssssii", $title, $description, $event_date, $category, $event_start_time, $event_end_time, $event_days, $team_based, $location, $image, $organizer_name, $organizer_pos, $organizer_contact, $source, $is_official);
    $success = $stmt->execute();
    $new_id  = $conn->insert_id;
    $stmt->close();

    if ($success && $new_id) {
        // Save highlights
        if (!empty($_POST['highlights'])) {
            $conn->query("DELETE FROM event_highlights WHERE event_id = $new_id");
            foreach ($_POST['highlights'] as $i => $hl) {
                $hl = trim($hl);
                if ($hl === '') continue;
                $s = $conn->prepare("INSERT INTO event_highlights (event_id, highlight, sort_order) VALUES (?,?,?)");
                $s->bind_param("isi", $new_id, $hl, $i);
                $s->execute();
                $s->close();
            }
        }
        // Save schedule rows
        if (!empty($_POST['schedule_time']) && !empty($_POST['schedule_activity'])) {
            $conn->query("DELETE FROM event_schedule WHERE event_id = $new_id");
            foreach ($_POST['schedule_time'] as $i => $time) {
                $time = trim($time);
                $act  = isset($_POST['schedule_activity'][$i]) ? trim($_POST['schedule_activity'][$i]) : '';
                $desc = isset($_POST['schedule_desc'][$i])     ? trim($_POST['schedule_desc'][$i])     : '';
                if ($time === '' && $act === '') continue;
                $s = $conn->prepare("INSERT INTO event_schedule (event_id, time_slot, activity, description, sort_order) VALUES (?,?,?,?,?)");
                $s->bind_param("isssi", $new_id, $time, $act, $desc, $i);
                $s->execute();
                $s->close();
            }
        }
    }
    return $success;
}

function update_event_details($conn, $event_id)
{
    $event_id         = intval($event_id);
    $title            = $_POST['event_title'];
    $description      = $_POST['event_description'];
    $event_date       = $_POST['event_date'];
    $category         = $_POST['event_category'];
    $event_start_time = isset($_POST['event_start_time']) ? $_POST['event_start_time'] : null;
    $event_end_time   = isset($_POST['event_end_time'])   ? $_POST['event_end_time']   : null;
    $event_days       = isset($_POST['event_days'])       ? intval($_POST['event_days']) : 1;
    $team_based       = isset($_POST['team_based'])       ? 1 : 0;
    $location         = isset($_POST['event_location'])   ? $_POST['event_location']   : null;
    $organizer_name   = isset($_POST['organizer_name'])   ? $_POST['organizer_name']   : null;
    $organizer_pos    = isset($_POST['organizer_position']) ? $_POST['organizer_position'] : null;
    $organizer_contact = isset($_POST['organizer_contact']) ? $_POST['organizer_contact'] : null;

    $image_set = '';
    $image_val = '';
    if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
        $target_dir  = "../../assets/img/events/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $ext         = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $filename    = 'event_' . time() . '.' . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
            $image_val = $filename;
            $image_set = ', image=?';
        }
    }

    if ($image_set) {
        $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, category=?, event_start_time=?, event_end_time=?, event_days=?, team_based=?, location=?, image=?, organizer_name=?, organizer_position=?, organizer_contact=? WHERE id=?");
        $stmt->bind_param("ssssssiissssi", $title, $description, $event_date, $category, $event_start_time, $event_end_time, $event_days, $team_based, $location, $image_val, $organizer_name, $organizer_pos, $organizer_contact, $event_id);
    } else {
        $stmt = $conn->prepare("UPDATE events SET title=?, description=?, event_date=?, category=?, event_start_time=?, event_end_time=?, event_days=?, team_based=?, location=?, organizer_name=?, organizer_position=?, organizer_contact=? WHERE id=?");
        $stmt->bind_param("ssssssiissssi", $title, $description, $event_date, $category, $event_start_time, $event_end_time, $event_days, $team_based, $location, $organizer_name, $organizer_pos, $organizer_contact, $event_id);
    }
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // Re-save highlights
        $conn->query("DELETE FROM event_highlights WHERE event_id = $event_id");
        if (!empty($_POST['highlights'])) {
            foreach ($_POST['highlights'] as $i => $hl) {
                $hl = trim($hl);
                if ($hl === '') continue;
                $s = $conn->prepare("INSERT INTO event_highlights (event_id, highlight, sort_order) VALUES (?,?,?)");
                $s->bind_param("isi", $event_id, $hl, $i);
                $s->execute();
                $s->close();
            }
        }
        // Re-save schedule
        $conn->query("DELETE FROM event_schedule WHERE event_id = $event_id");
        if (!empty($_POST['schedule_time'])) {
            foreach ($_POST['schedule_time'] as $i => $time) {
                $time = trim($time);
                $act  = isset($_POST['schedule_activity'][$i]) ? trim($_POST['schedule_activity'][$i]) : '';
                $desc = isset($_POST['schedule_desc'][$i])     ? trim($_POST['schedule_desc'][$i])     : '';
                if ($time === '' && $act === '') continue;
                $s = $conn->prepare("INSERT INTO event_schedule (event_id, time_slot, activity, description, sort_order) VALUES (?,?,?,?,?)");
                $s->bind_param("isssi", $event_id, $time, $act, $desc, $i);
                $s->execute();
                $s->close();
            }
        }
    }
    return $success;
}

function get_events_by_month($conn, $year, $month)
{
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events WHERE YEAR(event_date)=? AND MONTH(event_date)=? ORDER BY event_date ASC");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    $stmt->close();
    return $events;
}

function get_all_events($conn)
{
    $result = $conn->query("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events ORDER BY event_date ASC");
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    return $events;
}

function get_category_counts($conn)
{
    $categories = ['Academic', 'Sports', 'Cultural', 'Workshops', 'Conferences', 'Academic Calendar', 'Holidays', 'Health & Nutrition', 'Governance & Elections', 'Assessments', 'Professional Development', 'Remedial & Intervention'];
    
    // Get counts with single query
    $stmt = $conn->prepare("SELECT category, COUNT(*) as count FROM events GROUP BY category");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $db_counts = [];
    while ($row = $result->fetch_assoc()) {
        $db_counts[$row['category']] = $row['count'];
    }
    $stmt->close();
    
    // Build final counts array with 0 for missing categories
    $counts = [];
    foreach ($categories as $cat) {
        $counts[$cat] = $db_counts[$cat] ?? 0;
    }
    
    return $counts;
}

function get_upcoming_events($conn, $limit = 10)
{
    $today = date("Y-m-d");
    $limit = intval($limit);
    $sql   = "SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events WHERE event_date >= '$today' OR DATE_ADD(event_date, INTERVAL (event_days-1) DAY) >= '$today' ORDER BY event_date ASC LIMIT $limit";
    $result = $conn->query($sql);
    $events = [];
    if ($result) while ($row = $result->fetch_assoc()) $events[] = $row;
    return $events;
}

function get_events_happening_today($conn)
{
    $today = date("Y-m-d");
    $stmt  = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events WHERE event_date=? ORDER BY event_start_time ASC");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    $stmt->close();
    return $events;
}

function get_featured_events($conn)
{
    $today        = date("Y-m-d");
    $today_events = get_events_happening_today($conn);
    if (!empty($today_events)) return $today_events;
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events WHERE event_date > ? ORDER BY event_date ASC LIMIT 5");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    $stmt->close();
    if (!empty($events)) return $events;
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days FROM events ORDER BY event_date DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) $events[] = $row;
    $stmt->close();
    return $events;
}

function get_featured_event($conn)
{
    $events = get_featured_events($conn);
    return !empty($events) ? $events[0] : null;
}

function delete_event($conn, $id)
{
    $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// ============================================================
//  NEW: Applications / Groups / Teachers helpers
// ============================================================
function get_all_applications($conn, $event_id = null)
{
    if ($event_id) {
        $stmt = $conn->prepare("SELECT ea.*, e.title AS event_title FROM event_applications ea JOIN events e ON ea.event_id=e.id WHERE ea.event_id=? ORDER BY ea.applied_at DESC");
        $stmt->bind_param("i", $event_id);
    } else {
        $stmt = $conn->prepare("SELECT ea.*, e.title AS event_title FROM event_applications ea JOIN events e ON ea.event_id=e.id ORDER BY ea.applied_at DESC");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function update_application_status($conn, $app_id, $status)
{
    $allowed = ['Approved', 'Rejected', 'Pending'];
    if (!in_array($status, $allowed)) return false;
    $stmt = $conn->prepare("UPDATE event_applications SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $app_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_groups_for_event($conn, $event_id)
{
    $stmt = $conn->prepare("SELECT * FROM event_groups WHERE event_id=? ORDER BY id ASC");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        // Load members
        $ms = $conn->prepare("SELECT * FROM group_members WHERE group_id=?");
        $ms->bind_param("i", $row['id']);
        $ms->execute();
        $row['members'] = $ms->get_result()->fetch_all(MYSQLI_ASSOC);
        $ms->close();
        // Load teachers
        $ts = $conn->prepare("SELECT * FROM group_teachers WHERE group_id=?");
        $ts->bind_param("i", $row['id']);
        $ts->execute();
        $row['teachers'] = $ts->get_result()->fetch_all(MYSQLI_ASSOC);
        $ts->close();
        $groups[] = $row;
    }
    $stmt->close();
    return $groups;
}

function get_approved_applicants($conn, $event_id)
{
    $stmt = $conn->prepare("SELECT student_id, student_name FROM event_applications WHERE event_id=? AND status='Approved' ORDER BY student_name ASC");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function get_event_by_id($conn, $id)
{
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return null;
    // highlights
    $s = $conn->prepare("SELECT highlight FROM event_highlights WHERE event_id=? ORDER BY sort_order ASC");
    $s->bind_param("i", $id);
    $s->execute();
    $row['highlights'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    // schedule
    $s = $conn->prepare("SELECT * FROM event_schedule WHERE event_id=? ORDER BY sort_order ASC");
    $s->bind_param("i", $id);
    $s->execute();
    $row['schedule'] = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $row;
}

// ============================================================
//  NEW: DepEd Calendar Functions
// ============================================================
function insert_deped_calendar_events($conn)
{
    // Clear existing official events to avoid duplicates
    $conn->query("DELETE FROM events WHERE is_official = 1");
    
    $events = [
        // Academic Calendar Events
        ["title"=>"Start of School Year 2025-2026", "description"=>"Formal opening of School Year 2025-2026", "event_date"=>"2025-06-16", "category"=>"Academic Calendar", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        ["title"=>"End of School Year 2025-2026", "description"=>"EOSY rites and deliberations", "event_date"=>"2026-03-31", "category"=>"Academic Calendar", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        ["title"=>"Brigada Eskwela 2025", "description"=>"National Schools Maintenance Week", "event_date"=>"2025-06-09", "category"=>"Academic Calendar", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        
        // Early Registration Periods
        ["title"=>"Early Registration for SY 2025-2026", "description"=>"Early registration for incoming students", "event_date"=>"2025-05-10", "category"=>"Academic Calendar", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        ["title"=>"Balik Eskwela Program", "description"=>"Orientations and enrollment for returning students", "event_date"=>"2025-06-02", "category"=>"Academic Calendar", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        
        // National Holidays
        ["title"=>"Day of Valor (Araw ng Kagitingan)", "description"=>"Regular Holiday", "event_date"=>"2025-04-09", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"Maundy Thursday", "description"=>"Regular Holiday", "event_date"=>"2025-04-17", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"Good Friday", "description"=>"Regular Holiday", "event_date"=>"2025-04-18", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"Labor Day", "description"=>"Regular Holiday", "event_date"=>"2025-05-01", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"Independence Day", "description"=>"Regular Holiday", "event_date"=>"2025-06-12", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"National Heroes Day", "description"=>"Regular Holiday", "event_date"=>"2025-08-25", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"Bonifacio Day", "description"=>"Regular Holiday", "event_date"=>"2025-11-30", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"Christmas Day", "description"=>"Regular Holiday", "event_date"=>"2025-12-25", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"Rizal Day", "description"=>"Regular Holiday", "event_date"=>"2025-12-30", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        
        // Special Non-Working Holidays
        ["title"=>"Ninoy Aquino Day", "description"=>"Special Non-Working Holiday", "event_date"=>"2025-08-21", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"All Saints' Day", "description"=>"Special Non-Working Holiday", "event_date"=>"2025-11-01", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"All Souls' Day", "description"=>"Special Non-Working Holiday", "event_date"=>"2025-11-02", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        ["title"=>"Last Day of the Year", "description"=>"Special Non-Working Holiday", "event_date"=>"2025-12-31", "category"=>"Holidays", "source"=>"Proclamation No. 368", "is_official"=>1],
        
        // Break Periods
        ["title"=>"Mid-year Break", "description"=>"Last week of October break", "event_date"=>"2025-10-27", "category"=>"Holidays", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1, "event_days"=>5],
        ["title"=>"Christmas Break Start", "description"=>"Start of Christmas break", "event_date"=>"2025-12-20", "category"=>"Holidays", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        ["title"=>"Christmas Break End", "description"=>"Resumption of classes", "event_date"=>"2026-01-06", "category"=>"Holidays", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        
        // Parent-Teacher Conferences
        ["title"=>"First Quarter Parent-Teacher Conference", "description"=>"PTC after first quarter exams", "event_date"=>"2025-08-15", "category"=>"Academic Calendar", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        ["title"=>"Second Quarter Parent-Teacher Conference", "description"=>"PTC after second quarter exams", "event_date"=>"2025-10-24", "category"=>"Academic Calendar", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        ["title"=>"Third Quarter Parent-Teacher Conference", "description"=>"PTC after third quarter exams", "event_date"=>"2026-01-23", "category"=>"Academic Calendar", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        
        // Health & Nutrition
        ["title"=>"OK sa DepEd Program Launch", "description"=>"Health and Nutrition Program", "event_date"=>"2025-07-15", "category"=>"Health & Nutrition", "source"=>"DepEd Memorandum", "is_official"=>1],
        ["title"=>"National School Deworming Month", "description"=>"Mass deworming activities", "event_date"=>"2025-07-01", "category"=>"Health & Nutrition", "source"=>"DepEd Memorandum", "is_official"=>1],
        ["title"=>"School Dental Health Program", "description"=>"Dental check-up and treatment", "event_date"=>"2025-09-20", "category"=>"Health & Nutrition", "source"=>"DepEd Memorandum", "is_official"=>1],
        
        // Governance & Elections
        ["title"=>"Learner Government Elections", "description"=>"Student government elections after 3rd Quarter exams", "event_date"=>"2026-01-30", "category"=>"Governance & Elections", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        ["title"=>"School In-service Training (INSET)", "description"=>"Teacher professional development", "event_date"=>"2025-10-20", "category"=>"Governance & Elections", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1, "event_days"=>3],
        
        // Assessments
        ["title"=>"First Quarterly Assessment", "description"=>"First quarter examinations", "event_date"=>"2025-08-08", "category"=>"Assessments", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1, "event_days"=>3],
        ["title"=>"Second Quarterly Assessment", "description"=>"Second quarter examinations", "event_date"=>"2025-10-17", "category"=>"Assessments", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1, "event_days"=>3],
        ["title"=>"Third Quarterly Assessment", "description"=>"Third quarter examinations", "event_date"=>"2026-01-16", "category"=>"Assessments", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1, "event_days"=>3],
        ["title"=>"Fourth Quarterly Assessment", "description"=>"Fourth quarter examinations", "event_date"=>"2026-03-20", "category"=>"Assessments", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1, "event_days"=>3],
        ["title"=>"National Achievement Test (NAT)", "description"=>"System assessment for Grade 6 and 10", "event_date"=>"2026-02-15", "category"=>"Assessments", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
        ["title"=>"Early Language, Literacy, and Numeracy Assessment", "description"=>"ELLN for Kindergarten to Grade 3", "event_date"=>"2025-10-10", "category"=>"Assessments", "source"=>"DepEd Order No. 012, s. 2025", "is_official"=>1],
    ];
    
    $success_count = 0;
    foreach ($events as $ev) {
        $event_days = isset($ev['event_days']) ? $ev['event_days'] : 1;
        $is_official = isset($ev['is_official']) ? $ev['is_official'] : 1;
        
        $stmt = $conn->prepare("INSERT INTO events (title, description, event_date, category, event_days, source, is_official, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssisi", $ev['title'], $ev['description'], $ev['event_date'], $ev['category'], $event_days, $ev['source'], $is_official);
        if ($stmt->execute()) {
            $success_count++;
        }
        $stmt->close();
    }
    
    return $success_count;
}

// ============================================================
//  AJAX REQUEST HANDLER
// ============================================================
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        // Validate CSRF token for all POST requests
        if (!validateEventCSRFToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['status' => 'error', 'message' => 'Security token expired. Please refresh the page.']);
            exit;
        }

        $action = sanitizeInput($_POST['action'], 'string');

        // ---------- existing actions ----------
        if ($action == 'add_event') {
            $success = insert_event($conn);
            echo json_encode($success ? ['status' => 'success', 'message' => 'Event created!'] : ['status' => 'error', 'message' => 'Error creating event.']);
            exit;
        }
        if ($action == 'update_event' && isset($_POST['event_id'])) {
            $event_id = intval($_POST['event_id']);
            $success = update_event_details($conn, $event_id);
            echo json_encode($success ? ['status' => 'success', 'message' => 'Event updated successfully!'] : ['status' => 'error', 'message' => 'Error updating event.']);
            exit;
        }
        if ($action == 'delete_event' && isset($_POST['id'])) {
            $success = delete_event($conn, $_POST['id']);
            echo json_encode($success ? ['status' => 'success', 'message' => 'Deleted.'] : ['status' => 'error', 'message' => 'Error.']);
            exit;
        }
        if ($action == 'get_events' && isset($_POST['year'], $_POST['month'])) {
            $events = get_events_by_month($conn, $_POST['year'], $_POST['month']);
            echo json_encode(['status' => 'success', 'events' => $events]);
            exit;
        }
        if ($action == 'get_all_events') {
            echo json_encode(['status' => 'success', 'events' => get_all_events($conn)]);
            exit;
        }
        if ($action == 'get_upcoming_events') {
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
            echo json_encode(['status' => 'success', 'events' => get_upcoming_events($conn, $limit)]);
            exit;
        }
        if ($action == 'get_featured_events') {
            echo json_encode(['status' => 'success', 'events' => get_featured_events($conn)]);
            exit;
        }
        if ($action == 'delete' && isset($_POST['id'])) {
            $success = delete_news($conn, $_POST['id']);
            echo json_encode($success ? ['status' => 'success', 'message' => 'Deleted.'] : ['status' => 'error', 'message' => 'Error.']);
            exit;
        }
        if ($action == 'get_event' && isset($_POST['event_id'])) {
            $ev = get_event_by_id($conn, intval($_POST['event_id']));
            echo json_encode($ev ? ['status' => 'success', 'event' => $ev] : ['status' => 'error', 'message' => 'Not found.']);
            exit;
        }

        // ---------- Application actions ----------
        if ($action == 'get_applications') {
            $eid = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
            echo json_encode(['status' => 'success', 'applications' => get_all_applications($conn, $eid)]);
            exit;
        }
        if ($action == 'update_application_status' && isset($_POST['app_id'], $_POST['status'])) {
            $success = update_application_status($conn, intval($_POST['app_id']), $_POST['status']);
            echo json_encode($success ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Update failed.']);
            exit;
        }

        // ---------- Group actions ----------
        if ($action == 'create_group' && isset($_POST['event_id'], $_POST['group_name'])) {
            $eid  = intval($_POST['event_id']);
            $name = trim($_POST['group_name']);
            $stmt = $conn->prepare("INSERT INTO event_groups (event_id, group_name) VALUES (?,?)");
            $stmt->bind_param("is", $eid, $name);
            $ok   = $stmt->execute();
            $gid  = $conn->insert_id;
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success', 'group_id' => $gid, 'group_name' => $name] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'delete_group' && isset($_POST['group_id'])) {
            $gid  = intval($_POST['group_id']);
            $stmt = $conn->prepare("DELETE FROM event_groups WHERE id=?");
            $stmt->bind_param("i", $gid);
            $ok   = $stmt->execute();
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'add_member' && isset($_POST['group_id'], $_POST['student_id'], $_POST['student_name'])) {
            $gid   = intval($_POST['group_id']);
            $sid   = trim($_POST['student_id']);
            $sname = trim($_POST['student_name']);
            $stmt  = $conn->prepare("INSERT IGNORE INTO group_members (group_id, student_id, student_name) VALUES (?,?,?)");
            $stmt->bind_param("iss", $gid, $sid, $sname);
            $ok    = $stmt->execute();
            $mid   = $conn->insert_id;
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success', 'member_id' => $mid] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'remove_member' && isset($_POST['member_id'])) {
            $mid  = intval($_POST['member_id']);
            $stmt = $conn->prepare("DELETE FROM group_members WHERE id=?");
            $stmt->bind_param("i", $mid);
            $ok   = $stmt->execute();
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'add_teacher' && isset($_POST['group_id'], $_POST['teacher_name'])) {
            $gid  = intval($_POST['group_id']);
            $name = trim($_POST['teacher_name']);
            $stmt = $conn->prepare("INSERT INTO group_teachers (group_id, teacher_name) VALUES (?,?)");
            $stmt->bind_param("is", $gid, $name);
            $ok   = $stmt->execute();
            $tid  = $conn->insert_id;
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success', 'teacher_id' => $tid] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'remove_teacher' && isset($_POST['teacher_id'])) {
            $tid  = intval($_POST['teacher_id']);
            $stmt = $conn->prepare("DELETE FROM group_teachers WHERE id=?");
            $stmt->bind_param("i", $tid);
            $ok   = $stmt->execute();
            $stmt->close();
            echo json_encode($ok ? ['status' => 'success'] : ['status' => 'error', 'message' => 'Failed.']);
            exit;
        }
        if ($action == 'get_groups' && isset($_POST['event_id'])) {
            $groups = get_groups_for_event($conn, intval($_POST['event_id']));
            echo json_encode(['status' => 'success', 'groups' => $groups]);
            exit;
        }
        if ($action == 'get_approved_applicants' && isset($_POST['event_id'])) {
            $applicants = get_approved_applicants($conn, intval($_POST['event_id']));
            echo json_encode(['status' => 'success', 'applicants' => $applicants]);
            exit;
        }
        // ---------- DepEd Calendar Action ----------
        if ($action == 'import_deped_calendar') {
            $count = insert_deped_calendar_events($conn);
            echo json_encode(['status' => 'success', 'message' => "Successfully imported {$count} DepEd calendar events."]);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action'])) {
        $success = insert_news($conn);
        echo json_encode($success ? ['status' => 'success', 'message' => 'News post created!'] : ['status' => 'error', 'message' => 'Error.']);
        exit;
    }
}

// Data for the page - refreshed 2026-03-26
$featured_events  = get_featured_events($conn);
$today            = date("Y-m-d");
$featured_event   = !empty($featured_events) ? $featured_events[0] : null;
$is_current_event = !empty($featured_events) && $featured_events[0]['event_date'] == $today;
$category_counts  = get_category_counts($conn);
$all_events_list  = get_all_events($conn);
$all_applications = get_all_applications($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - School Admin Dashboard</title>
    <link rel="stylesheet" href="/BUNHS_School_System/admin_account/admin_assets/cs/admin_style.css?v=20260326">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="/BUNHS_School_System/assets/vendor/bootstrap/css/bootstrap.min.css?v=20260326" rel="stylesheet">
    <link href="/BUNHS_School_System/assets/vendor/bootstrap-icons/bootstrap-icons.css?v=20260326" rel="stylesheet">
    <link href="/BUNHS_School_System/assets/css/main.css?v=20260326" rel="stylesheet">
    <style>
        /* ============================================================
           BUNHS ANNOUNCEMENTS & EVENTS — REDESIGNED UI
           Deep Forest theme · Outfit + Plus Jakarta Sans
        ============================================================ */
        :root {
            --forest-deep:    #102C26;
            --forest-mid:     #1B4D44;
            --forest-bright:  #2A7A68;
            --forest-mist:    #3D9E8A;
            --forest-pale:    #E6F4F1;
            --forest-softer:  #F0F9F7;
            --gold:           #D4A843;
            --gold-light:     #F0C96A;
            --white:          #FFFFFF;
            --ink:            #0E1F1C;
            --ink-mid:        #2C4A44;
            --ink-soft:       #5A7A74;
            --surface:        #F7FAFA;
            --border:         #D4E8E4;
            --shadow-sm:      0 2px 8px rgba(16,44,38,.08);
            --shadow-md:      0 8px 28px rgba(16,44,38,.13);
            --shadow-lg:      0 20px 50px rgba(16,44,38,.18);
            --radius-sm:      8px;
            --radius-md:      14px;
            --radius-lg:      22px;
            --transition:     all .25s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--surface);
            color: var(--ink);
            font-size: 15px;
            line-height: 1.6;
        }

        h1,h2,h3,h4,h5,h6,
        .heading-title,
        .admin-section-title,
        .modal-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }

        /* ============================================================
           TOAST
        ============================================================ */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast-notification {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            color: #fff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 500;
            font-size: 14px;
            min-width: 280px;
            max-width: 380px;
            animation: slideInRight .35s ease-out, fadeOut .4s ease-in 3.6s forwards;
            backdrop-filter: blur(12px);
        }

        .toast-notification.success  { background: linear-gradient(135deg, var(--forest-mid), var(--forest-bright)); }
        .toast-notification.error    { background: linear-gradient(135deg, #c0392b, #e74c3c); }
        .toast-notification.warning  { background: linear-gradient(135deg, #d4a017, var(--gold)); color: var(--ink); }
        .toast-notification.info     { background: linear-gradient(135deg, var(--forest-bright), var(--forest-mist)); }
        .toast-notification i        { font-size: 18px; flex-shrink: 0; }
        .toast-notification .toast-message { flex: 1; }
        .toast-notification .toast-close   { background: none; border: none; color: inherit; cursor: pointer; padding: 0; font-size: 16px; opacity: .7; transition: opacity .2s; }
        .toast-notification .toast-close:hover { opacity: 1; }

        @keyframes slideInRight { from { transform: translateX(110%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut      { from { opacity: 1; } to { opacity: 0; transform: translateX(110%); } }
        @keyframes spin         { to { transform: rotate(360deg); } }
        @keyframes fadeInUp     { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

        /* ============================================================
           LOADING / NAV
        ============================================================ */
        .dashboard-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            flex-direction: column;
            gap: 16px;
        }

        .dashboard-loading .spinner,
        .spinner-enhanced {
            width: 44px;
            height: 44px;
            border: 3px solid var(--border);
            border-top-color: var(--forest-bright);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .nav-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: var(--radius-md);
            padding: 24px;
            text-align: center;
            margin: 16px;
        }

        /* ============================================================
           PAGE TITLE / HERO BANNER
        ============================================================ */
        .page-title {
            background: linear-gradient(135deg, var(--forest-deep) 0%, var(--forest-mid) 60%, var(--forest-bright) 100%);
            color: #fff;
            padding: 3.5rem 0 2.5rem;
            margin-bottom: 0;
            position: relative;
            overflow: hidden;
        }

        .page-title::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 80% at 80% 50%, rgba(212,168,67,.12) 0%, transparent 70%),
                radial-gradient(ellipse 40% 60% at 10% 80%, rgba(61,158,138,.15) 0%, transparent 60%);
            pointer-events: none;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--forest-bright), var(--gold), var(--forest-bright));
        }

        .heading-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            letter-spacing: -.5px;
            margin-bottom: .5rem;
            position: relative;
            z-index: 2;
        }

        .page-title p {
            font-size: 1rem;
            opacity: .82;
            position: relative;
            z-index: 2;
        }

        /* breadcrumbs */
        .breadcrumbs { position: relative; z-index: 2; }
        .breadcrumbs ol { display: flex; gap: 6px; list-style: none; padding: 0; margin: 1rem 0 0; flex-wrap: wrap; }
        .breadcrumbs ol li { font-size: .85rem; opacity: .75; }
        .breadcrumbs ol li a { color: #fff; text-decoration: none; opacity: .85; }
        .breadcrumbs ol li a:hover { opacity: 1; text-decoration: underline; }
        .breadcrumbs ol li + li::before { content: '/'; margin-right: 6px; opacity: .5; }
        .breadcrumbs ol li.current { opacity: 1; font-weight: 600; }

        /* ============================================================
           FEATURED EVENT BANNER
        ============================================================ */
        .featured-event-section { margin: 0; padding: 0; }

        .featured-event-banner {
            padding: 56px 24px 52px !important;
            position: relative;
            overflow: hidden;
        }

        .featured-event-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(0,0,0,.55), rgba(0,0,0,.3));
            pointer-events: none;
        }

        .featured-event-banner .row { position: relative; z-index: 2; }

        .featured-event-banner h2 {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(1.8rem, 3vw, 2.8rem) !important;
            font-weight: 800 !important;
            letter-spacing: -.3px;
            text-shadow: 0 2px 12px rgba(0,0,0,.4);
        }

        .featured-event-banner .badge {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            letter-spacing: .5px;
            border-radius: 30px;
        }

        /* ============================================================
           MAIN CONTENT WRAPPER
        ============================================================ */
        .main.page-content { background: var(--surface); }

        /* ============================================================
           ADMIN SECTION CARDS
        ============================================================ */
        .admin-section-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem 2.25rem;
            margin-bottom: 2.25rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: box-shadow .3s ease;
            position: relative;
            overflow: hidden;
            animation: fadeInUp .45s ease both;
        }

        .admin-section-card:hover {
            box-shadow: var(--shadow-md);
        }

        .admin-section-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--forest-deep), var(--forest-bright), var(--gold));
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        /* Section headings inside cards */
        .admin-section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--forest-deep);
            border-bottom: 2px solid var(--forest-pale);
            padding-bottom: 1rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .admin-section-title i {
            color: var(--forest-bright);
            font-size: 1.1rem;
        }

        .admin-section-title .ms-auto { margin-left: auto; }

        /* ============================================================
           FORM CONTROLS
        ============================================================ */
        .form-label {
            font-family: 'Outfit', sans-serif;
            font-size: .82rem;
            font-weight: 600;
            color: var(--ink-mid);
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: .35rem;
        }

        .form-control, .form-select {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .9rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: .65rem .9rem;
            background: var(--white);
            color: var(--ink);
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--forest-bright);
            box-shadow: 0 0 0 3px rgba(42,122,104,.15);
            outline: none;
        }

        .form-control::placeholder { color: #aebfbb; }

        .form-field-wrapper {
            position: relative;
            margin-bottom: .25rem;
        }

        .form-field-wrapper .field-icon {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ink-soft);
            font-size: 14px;
            z-index: 2;
            pointer-events: none;
            transition: color .2s;
        }

        .form-field-wrapper:focus-within .field-icon { color: var(--forest-bright); }

        .form-field-wrapper .form-control,
        .form-field-wrapper .form-select { padding-right: 38px; }

        .form-field-wrapper textarea + .field-icon { top: 16px; transform: none; }

        .required-star { color: #e74c3c; font-size: 11px; }

        .character-counter {
            position: absolute;
            bottom: 10px;
            right: 12px;
            font-size: 11px;
            color: var(--ink-soft);
            background: var(--white);
            padding: 0 4px;
            border-radius: 4px;
            pointer-events: none;
        }

        .field-validation-wrapper.has-counter .form-control { padding-bottom: 28px; }

        .validation-feedback { font-size: .78rem; color: #e74c3c; min-height: 18px; }

        /* Enhanced validation styles */
        .form-control.is-valid {
            border-color: #28a745;
            background-color: #f8fff9;
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            background-color: #fff8f8;
        }

        .form-control.is-valid:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .form-control.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .validation-feedback.show {
            display: block !important;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Highlight / schedule dynamic rows */
        .highlight-row,
        .schedule-row-input {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .highlight-row input,
        .schedule-row-input input { flex: 1; }

        .btn-remove-row {
            background: none;
            border: none;
            color: #e74c3c;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            padding: 0 4px;
            transition: transform .2s;
        }
        .btn-remove-row:hover { transform: scale(1.2); }

        .btn-add-row {
            font-size: .83rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            color: var(--forest-bright);
            background: none;
            border: 1.5px dashed var(--forest-mist);
            border-radius: var(--radius-sm);
            padding: 6px 14px;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-add-row:hover { background: var(--forest-pale); border-color: var(--forest-bright); }

        /* Checkbox */
        .form-check-input {
            border-color: var(--border);
            transition: var(--transition);
        }
        .form-check-input:checked {
            background-color: var(--forest-bright);
            border-color: var(--forest-bright);
        }
        .form-check-label { font-size: .9rem; color: var(--ink-mid); }

        /* Form section groupings inside modals */
        .form-section-card {
            background: var(--forest-softer);
            border-radius: var(--radius-md);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--border);
        }

        .form-section-card .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: .9rem;
            font-weight: 700;
            color: var(--forest-deep);
            margin-bottom: .9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: .6rem;
            border-bottom: 1.5px solid var(--border);
        }

        /* ============================================================
           BUTTONS
        ============================================================ */
        .btn-primary,
        .btn-enhanced {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: .88rem;
            letter-spacing: .3px;
            padding: .65rem 1.6rem;
            border-radius: var(--radius-sm);
            border: none;
            background: linear-gradient(135deg, var(--forest-deep), var(--forest-mid));
            color: #fff;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::after,
        .btn-enhanced::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.08), transparent);
            pointer-events: none;
        }

        .btn-primary:hover,
        .btn-enhanced:hover {
            background: linear-gradient(135deg, var(--forest-mid), var(--forest-bright));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16,44,38,.3);
            color: #fff;
        }

        .btn-primary:active,
        .btn-enhanced:active {
            transform: translateY(0);
        }

        .btn-secondary {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: .88rem;
            padding: .65rem 1.4rem;
            border-radius: var(--radius-sm);
            background: var(--surface);
            border: 1.5px solid var(--border);
            color: var(--ink-mid);
            transition: var(--transition);
        }

        .btn-secondary:hover {
            background: var(--forest-pale);
            border-color: var(--forest-mist);
            color: var(--forest-deep);
        }

        .btn-outline-primary {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: .82rem;
            padding: .4rem .85rem;
            border-radius: 7px;
            border: 1.5px solid var(--forest-mid);
            color: var(--forest-mid);
            background: transparent;
            transition: var(--transition);
        }
        .btn-outline-primary:hover { background: var(--forest-pale); color: var(--forest-deep); }

        .btn-outline-info {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: .82rem;
            padding: .4rem .85rem;
            border-radius: 7px;
            border: 1.5px solid #17a2b8;
            color: #17a2b8;
            background: transparent;
            transition: var(--transition);
        }
        .btn-outline-info:hover { background: #e9f8fb; color: #117a8b; }

        .btn-outline-danger {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: .82rem;
            padding: .4rem .85rem;
            border-radius: 7px;
            border: 1.5px solid #dc3545;
            color: #dc3545;
            background: transparent;
            transition: var(--transition);
        }
        .btn-outline-danger:hover { background: #fdf2f3; color: #a71d2a; }

        .btn-group-enhanced { display: flex; gap: 5px; flex-wrap: wrap; }

        .btn-approve {
            font-family: 'Outfit', sans-serif;
            font-size: .8rem;
            font-weight: 600;
            padding: 5px 12px;
            border: none;
            border-radius: 7px;
            background: linear-gradient(135deg, #1e7e34, #28a745);
            color: #fff;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-approve:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(40,167,69,.3); }

        .btn-reject {
            font-family: 'Outfit', sans-serif;
            font-size: .8rem;
            font-weight: 600;
            padding: 5px 12px;
            border: none;
            border-radius: 7px;
            background: linear-gradient(135deg, #a71d2a, #dc3545);
            color: #fff;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-reject:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(220,53,69,.3); }

        .btn-retry {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            padding: 8px 20px;
            border: 2px solid #e74c3c;
            border-radius: var(--radius-sm);
            background: transparent;
            color: #e74c3c;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-retry:hover { background: #e74c3c; color: #fff; }

        .manage-event-btn, .btn-manage-event {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            font-family: 'Outfit', sans-serif;
            font-size: .83rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            background: linear-gradient(135deg, var(--forest-deep), var(--forest-mid));
            color: #fff;
            border: none;
        }
        .btn-manage-event:hover { opacity: .9; transform: translateY(-1px); }

        /* ============================================================
           TABLES
        ============================================================ */
        .table-enhanced,
        .table-hover {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .87rem;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .table-enhanced thead tr,
        .applications-table thead tr,
        .table-hover thead tr {
            background: linear-gradient(135deg, var(--forest-deep), var(--forest-mid));
        }

        .table-enhanced th,
        .applications-table th,
        .table-hover th {
            padding: 1rem 1rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: rgba(255,255,255,.9);
            border: none;
            white-space: nowrap;
        }

        .table-enhanced th:first-child { border-radius: var(--radius-sm) 0 0 0; }
        .table-enhanced th:last-child  { border-radius: 0 var(--radius-sm) 0 0; }

        .table-enhanced td,
        .applications-table td,
        .table-hover td {
            padding: .9rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--forest-pale);
            color: var(--ink);
            transition: background .15s ease;
        }

        .table-enhanced tbody tr:hover td,
        .applications-table tbody tr:hover td,
        .table-hover tbody tr:hover td {
            background: var(--forest-softer);
        }

        .table-enhanced tbody tr:last-child td { border-bottom: none; }

        .table-responsive {
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        /* ============================================================
           BADGES & STATUS
        ============================================================ */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-family: 'Outfit', sans-serif;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .3px;
            text-transform: uppercase;
        }

        .status-badge.Pending, .status-badge.primary   { background: #fff3cd; color: #856404; }
        .status-badge.Approved, .status-badge.success  { background: #d1e7dd; color: #0a5132; }
        .status-badge.Rejected, .status-badge.danger   { background: #f8d7da; color: #842029; }
        .status-badge.secondary                         { background: #e9ecef; color: #5a6268; }

        .event-date-badge {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 7px 10px;
            background: linear-gradient(135deg, var(--forest-mid), var(--forest-bright));
            color: #fff;
            border-radius: 10px;
            font-size: .82rem;
            min-width: 58px;
            text-align: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            box-shadow: 0 3px 8px rgba(27,77,68,.25);
        }

        .event-date-badge small { font-size: .7rem; opacity: .85; font-weight: 400; }

        .event-title-cell strong { color: var(--ink); font-weight: 600; }
        .event-title-cell .text-muted { font-size: .8rem; color: var(--ink-soft); }

        /* Category pills */
        .event-item-category {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-family: 'Outfit', sans-serif;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .event-item-category.academic          { background: #dbeafe; color: #1e40af; }
        .event-item-category.sports            { background: #fee2e2; color: #991b1b; }
        .event-item-category.cultural          { background: #ede9fe; color: #5b21b6; }
        .event-item-category.workshops         { background: #fef3c7; color: #92400e; }
        .event-item-category.conferences       { background: #d1fae5; color: #065f46; }
        .event-item-category.holidays          { background: #fce7f3; color: #9d174d; }
        .event-item-category.assessments       { background: #e0f2fe; color: #075985; }
        .event-item-category                   { background: var(--forest-pale); color: var(--forest-deep); }

        /* ============================================================
           SEARCH / FILTER BAR
        ============================================================ */
        .search-filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            align-items: flex-end;
        }

        .search-filter-row .form-field-wrapper { flex: 1; min-width: 180px; }

        /* ============================================================
           CALENDAR
        ============================================================ */
        .calendar-wrapper {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .month {
            padding: 20px;
            width: 100%;
            background: linear-gradient(135deg, var(--forest-deep), var(--forest-mid));
            text-align: center;
        }

        .month ul { margin: 0; padding: 0; list-style: none; display: flex; align-items: center; justify-content: space-between; }
        .month ul li { color: #fff; }
        .month ul li:nth-child(2) { font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }

        .month .prev, .month .next {
            cursor: pointer;
            color: rgba(255,255,255,.8);
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
            font-size: 18px;
        }
        .month .prev:hover, .month .next:hover { background: rgba(255,255,255,.2); color: #fff; }

        .weekdays {
            margin: 0; padding: 10px 0;
            background: var(--forest-softer);
            display: flex; justify-content: space-around;
            border-bottom: 1px solid var(--border);
            list-style: none;
        }
        .weekdays li { display: inline-block; width: 13.6%; color: var(--ink-soft); text-align: center; font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 12px; text-transform: uppercase; }

        .days { padding: 8px; background: var(--white); margin: 0; display: flex; flex-wrap: wrap; justify-content: flex-start; list-style: none; }
        .days li {
            list-style: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14.28%;
            text-align: center;
            margin-bottom: 4px;
            font-size: 13px;
            color: var(--ink);
            padding: 10px 0;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
            border-radius: 10px;
            min-height: 44px;
            font-weight: 500;
        }
        .days li:hover { background: var(--forest-pale); color: var(--forest-deep); transform: scale(1.06); }
        .days li.other-month { color: var(--border); }
        .days li.today { font-weight: 800; color: var(--forest-bright); }
        .days li.today::after { content: ''; position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%); width: 5px; height: 5px; background: var(--forest-bright); border-radius: 50%; }
        .days li .event-dot { position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%); width: 5px; height: 5px; border-radius: 50%; background: var(--forest-bright); }
        .days li .event-dot.academic   { background: #3b82f6; }
        .days li .event-dot.sports     { background: #ef4444; }
        .days li .event-dot.cultural   { background: #8b5cf6; }
        .days li .event-dot.workshops  { background: #f59e0b; }

        /* ============================================================
           SIDEBAR (events list & categories)
        ============================================================ */
        .sidebar-item {
            background: var(--white);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 1.25rem 1.5rem;
            margin-top: 1.5rem;
        }

        .sidebar-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--forest-deep);
            margin-bottom: 1rem;
            padding-bottom: .6rem;
            border-bottom: 2px solid var(--forest-pale);
        }

        .categories ul { list-style: none; padding: 0; margin: 0; }
        .categories ul li { margin-bottom: 8px; }
        .categories ul li a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--ink-mid);
            text-decoration: none;
            font-size: .88rem;
            padding: 7px 12px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }
        .categories ul li a:hover { background: var(--forest-pale); color: var(--forest-deep); }
        .categories ul li a span {
            background: var(--forest-pale);
            color: var(--forest-mid);
            border-radius: 20px;
            padding: 2px 10px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: .78rem;
        }

        /* ============================================================
           UPCOMING EVENTS LIST
        ============================================================ */
        .event-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: var(--white);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .event-item:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--forest-mist); }

        .event-item .event-date {
            flex-shrink: 0;
            width: 66px;
            text-align: center;
            padding: 10px 8px;
            background: linear-gradient(135deg, var(--forest-deep), var(--forest-mid));
            border-radius: var(--radius-sm);
            color: #fff;
            font-family: 'Outfit', sans-serif;
        }
        .event-item .event-date .day   { display: block; font-size: 26px; font-weight: 800; line-height: 1; }
        .event-item .event-date .month { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 0; background: transparent; opacity: .8; }

        .event-item .event-content { flex: 1; }
        .event-item .event-content h3 { font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 700; margin-bottom: 6px; color: var(--ink); }
        .event-item .event-meta { display: flex; gap: 14px; margin-bottom: 8px; flex-wrap: wrap; }
        .event-item .event-meta p { display: flex; align-items: center; gap: 5px; font-size: .82rem; color: var(--ink-soft); margin: 0; }
        .event-item .event-meta i { color: var(--forest-bright); }

        .event-item .btn-event {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--forest-bright);
            font-family: 'Outfit', sans-serif;
            font-size: .82rem;
            font-weight: 700;
            text-decoration: none;
            padding: 4px 0;
            border-bottom: 1.5px solid transparent;
            transition: var(--transition);
        }
        .event-item .btn-event:hover { color: var(--forest-deep); border-bottom-color: var(--forest-deep); }

        /* ============================================================
           MODAL
        ============================================================ */
        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .modal-header, .modal-header-enhanced {
            background: linear-gradient(135deg, var(--forest-deep), var(--forest-mid));
            color: #fff;
            padding: 1.4rem 2rem;
            border: none;
        }

        .modal-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .modal-header .btn-close { filter: invert(1); opacity: .75; }
        .modal-header .btn-close:hover { opacity: 1; }

        .modal-body { padding: 1.75rem 2rem; }

        .modal-footer {
            padding: 1.2rem 2rem;
            border-top: 1px solid var(--border);
            background: var(--forest-softer);
        }

        /* Event date header inside modal */
        .event-date-header {
            background: linear-gradient(135deg, var(--forest-deep), var(--forest-mid));
            color: #fff;
            padding: 1.4rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }

        .event-date-header h5 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0;
        }

        .event-date-display {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .85rem;
            opacity: .85;
            background: rgba(255,255,255,.15);
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* Live preview inside modal */
        .event-preview-container {
            background: var(--forest-softer);
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border);
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .event-preview-header {
            background: var(--forest-pale);
            padding: .8rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
        }

        .event-preview-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: .85rem;
            color: var(--forest-deep);
        }

        .event-preview-card { padding: 1.25rem; }

        .events-list-modal {
            max-height: 180px;
            overflow-y: auto;
            margin-top: 8px;
        }

        /* ============================================================
           GROUPS
        ============================================================ */
        .group-card {
            background: var(--forest-softer);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            margin-bottom: 14px;
        }

        .group-card .group-name {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--forest-deep);
            margin-bottom: 8px;
        }

        .group-card .member-tag,
        .group-card .teacher-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: .82rem;
            margin: 3px;
            color: var(--ink-mid);
        }

        .group-card .teacher-tag { background: var(--forest-pale); border-color: var(--forest-mist); color: var(--forest-deep); }

        .group-card .remove-btn {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            font-size: .72rem;
            padding: 0 0 0 3px;
            transition: transform .2s;
        }
        .group-card .remove-btn:hover { transform: scale(1.3); }

        /* ============================================================
           EVENT SELECT FILTER (Applications section)
        ============================================================ */
        .event-select-filter {
            max-width: 340px;
            margin-bottom: 1.5rem;
        }

        /* ============================================================
           FEATURED EVENT CAROUSEL
        ============================================================ */
        .featured-event-carousel { position: relative; overflow: hidden; }
        .featured-event-slide { display: none; animation: fadeIn .5s ease-in-out; }
        .featured-event-slide.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: translateX(0); } }

        /* ============================================================
           MISC UTILITIES
        ============================================================ */
        .text-muted { color: var(--ink-soft) !important; }

        .loading-enhanced {
            display: flex; align-items: center; justify-content: center;
            padding: 2.5rem;
            background: var(--forest-softer);
            border-radius: var(--radius-md);
            margin: 1.5rem 0;
        }

        /* Focus ring for accessibility */
        .focus-ring:focus {
            box-shadow: 0 0 0 3px rgba(42,122,104,.2), 0 0 0 6px rgba(42,122,104,.08);
        }

        /* Interactive micro-interaction */
        .interactive-element { transition: var(--transition); }
        .interactive-element:active { transform: scale(.97); }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 768px) {
            .admin-section-card { padding: 1.25rem 1.25rem; }
            .heading-title { font-size: 1.85rem; }

            .event-item { flex-direction: column; gap: 10px; }
            .event-item .event-date { width: 100%; display: flex; align-items: center; gap: 12px; }
            .event-item .event-date .day { font-size: 22px; }

            .search-filter-row { flex-direction: column; }
            .search-filter-row > * { width: 100%; }

            .modal-body { padding: 1.25rem 1.25rem; }
        }
    </style>
</head>

<body>
    <div class="toast-container" id="toastContainer"></div>
    <div id="navigation-container">
        <div class="dashboard-loading">
            <div class="spinner"></div>
            <p>Loading navigation...</p>
        </div>
    </div>

    <main class="main page-content" id="main-content" style="display: block; margin-left: 240px; width: calc(100vw - 240px); max-width: calc(100vw - 240px); padding: 0 20px; overflow-x: hidden; box-sizing: border-box;">
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">
                                <i class="fas fa-bullhorn me-3" style="color:var(--gold-light,#F0C96A);font-size:2rem;vertical-align:middle;"></i>Announcements &amp; Events
                            </h1>
                            <p class="mb-0" style="font-size:1.05rem;opacity:.85;max-width:520px;margin:0 auto;">Manage school events, student applications, and group assignments for Buyoan National High School.</p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="../admin_dashboard.php"><i class="fas fa-home me-1"></i>Home</a></li>
                        <li class="current">Announcements</li>
                    </ol>
                </div>
            </nav>
        </div>

        <?php
        $bannerImage = '';
        if ($featured_event) {
            switch (strtolower($featured_event['category'])) {
                case 'academic':
                    $bannerImage = '../../admin_account/admin_assets/pics/academics.jpg';
                    break;
                case 'sports':
                    $bannerImage = '../../admin_account/admin_assets/pics/sports.jpg';
                    break;
                case 'cultural':
                    $bannerImage = '../../admin_account/admin_assets/pics/culture.jpg';
                    break;
                case 'workshops':
                    $bannerImage = '../../admin_account/admin_assets/pics/workshop.jpg';
                    break;
                case 'conferences':
                    $bannerImage = '../../admin_account/admin_assets/pics/conference.jpg';
                    break;
            }
        }
        $bannerStyle = $bannerImage
            ? "background:linear-gradient(135deg,rgba(0,0,0,.6),rgba(0,0,0,.4)),url('$bannerImage');background-size:cover;background-position:center;"
            : "background:linear-gradient(135deg,#1abc9c,#16a085);";
        ?>

        <section class="featured-event-section section" style="margin:0;padding:0;">
            <div class="container-fluid" style="margin:0;padding:0;max-width:100%;">
                <div class="featured-event-banner" style="<?php echo $bannerStyle; ?> padding:40px 20px;margin:20px 0;border-radius:0;color:#fff;text-align:center;">
                    <?php if ($featured_event): ?>
                        <div class="row justify-content-center">
                            <div class="col-lg-10">
                                <?php if ($is_current_event): ?>
                                    <span class="badge bg-warning text-dark mb-3" style="font-size:14px;padding:8px 16px;"><i class="fas fa-calendar-check me-2"></i>HAPPENING NOW</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark mb-3" style="font-size:14px;padding:8px 16px;"><i class="fas fa-star me-2"></i>UPCOMING EVENT</span>
                                <?php endif; ?>
                                <h2 style="font-size:2.5rem;font-weight:700;margin:15px 0;"><?php echo htmlspecialchars($featured_event['title']); ?></h2>
                                <p style="font-size:1.2rem;margin-bottom:15px;">
                                    <i class="bi bi-calendar-event me-2"></i>
                                    <?php echo (new DateTime($featured_event['event_date']))->format('F j, Y'); ?>
                                </p>
                                <?php if ($featured_event['description']): ?>
                                    <p style="font-size:1rem;opacity:.9;max-width:600px;margin:0 auto 20px;"><?php echo htmlspecialchars($featured_event['description']); ?></p>
                                <?php endif; ?>
                                <span class="event-item-category <?php echo strtolower($featured_event['category']); ?>" style="display:inline-flex;align-items:center;gap:4px;padding:8px 20px;border-radius:25px;font-size:14px;font-weight:600;text-transform:uppercase;background:#fff;color:#1abc9c;">
                                    <?php echo htmlspecialchars($featured_event['category']); ?>
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row justify-content-center">
                            <div class="col-lg-10">
                                <h2 style="font-size:2rem;font-weight:700;margin:15px 0;"><i class="fa-regular fa-calendar-check"></i> No Upcoming Events</h2>
                                <p style="font-size:1rem;opacity:.9;">Check back later for upcoming events at Buyoan National High School.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ======================================================
         EVENT MODAL (add event on calendar click)
    ====================================================== -->
        <div class="modal fade event-modal" id="eventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="event-date-header">
                        <div>
                            <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Add New Event</h5>
                            <small style="opacity:.75;font-size:.8rem;">DepEd Order No. 012, s. 2025 compliant</small>
                        </div>
                        <div class="event-date-display" id="eventDateDisplay"></div>
                    </div>
                    <div class="modal-body">
                        <form action="" method="POST" id="eventForm" enctype="multipart/form-data">
                            <input type="hidden" id="eventDate" name="event_date">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <!-- ── SECTION 1: Basic Information ── -->
                            <div class="form-section-card">
                                <div class="section-title"><i class="fas fa-info-circle"></i>Basic Information</div>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="field-validation-wrapper has-counter">
                                            <label class="form-label">Event Title <span class="required-star">*</span></label>
                                            <div class="form-field-wrapper">
                                                <input type="text" class="form-control" id="eventTitle" name="event_title" placeholder="e.g. Foundation Day Celebration" maxlength="100" required>
                                                <i class="fas fa-calendar-check field-icon"></i>
                                                <span class="character-counter" id="titleCounter">0/100</span>
                                            </div>
                                            <div class="validation-feedback" id="titleFeedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Category <span class="required-star">*</span></label>
                                        <div class="form-field-wrapper">
                                            <select class="form-select" id="eventCategory" name="event_category" required>
                                                <option value="">Select category…</option>
                                                <optgroup label="── Academic ──">
                                                    <option value="Academic">Academic</option>
                                                    <option value="Academic Calendar">Academic Calendar</option>
                                                    <option value="Assessments">Assessments</option>
                                                    <option value="Remedial &amp; Intervention">Remedial &amp; Intervention</option>
                                                    <option value="Professional Development">Professional Development</option>
                                                </optgroup>
                                                <optgroup label="── Events ──">
                                                    <option value="Sports">Sports</option>
                                                    <option value="Cultural">Cultural</option>
                                                    <option value="Workshops">Workshops</option>
                                                    <option value="Conferences">Conferences</option>
                                                </optgroup>
                                                <optgroup label="── DepEd Programs ──">
                                                    <option value="Health &amp; Nutrition">Health &amp; Nutrition</option>
                                                    <option value="Governance &amp; Elections">Governance &amp; Elections</option>
                                                    <option value="National Celebrations">National Celebrations</option>
                                                    <option value="Activities &amp; Observances">Activities &amp; Observances</option>
                                                </optgroup>
                                                <optgroup label="── Calendar ──">
                                                    <option value="Holidays">Holidays</option>
                                                    <option value="Class Suspension">Class Suspension</option>
                                                    <option value="Break Period">Break Period</option>
                                                </optgroup>
                                            </select>
                                            <i class="fas fa-tag field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Location / Venue</label>
                                        <div class="form-field-wrapper">
                                            <input type="text" class="form-control" name="event_location" placeholder="e.g. Main Auditorium, Covered Court, Online">
                                            <i class="fas fa-map-marker-alt field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Participation Level</label>
                                        <div class="form-field-wrapper">
                                            <select class="form-select" name="event_level">
                                                <option value="">— select —</option>
                                                <option value="School">School-Level</option>
                                                <option value="District">District-Level</option>
                                                <option value="Division">Division-Level</option>
                                                <option value="Regional">Regional-Level</option>
                                                <option value="National">National</option>
                                                <option value="International">International</option>
                                            </select>
                                            <i class="fas fa-sitemap field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="field-validation-wrapper has-counter">
                                            <label class="form-label">Description / Purpose</label>
                                            <div class="form-field-wrapper">
                                                <textarea class="form-control" id="eventDescription" name="event_description" placeholder="Brief description of the event, its objectives, and expected participants…" rows="3" maxlength="500"></textarea>
                                                <i class="fas fa-align-left field-icon"></i>
                                                <span class="character-counter" id="descCounter">0/500</span>
                                            </div>
                                            <div class="validation-feedback" id="descFeedback"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── SECTION 2: Schedule & Duration ── -->
                            <div class="form-section-card">
                                <div class="section-title"><i class="fas fa-clock"></i>Schedule &amp; Duration</div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Start Date <span class="required-star">*</span></label>
                                        <div class="form-field-wrapper">
                                            <input type="date" class="form-control" name="event_date_display" id="eventStartDateDisplay" readonly style="background:var(--forest-softer);">
                                            <i class="fas fa-calendar field-icon"></i>
                                        </div>
                                        <small class="text-muted">Set by selecting a date on the calendar.</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date <small class="text-muted">(if multi-day)</small></label>
                                        <div class="form-field-wrapper">
                                            <input type="date" class="form-control" name="event_end_date">
                                            <i class="fas fa-calendar-check field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Start Time</label>
                                        <div class="form-field-wrapper">
                                            <input type="time" class="form-control" name="event_start_time">
                                            <i class="fas fa-clock field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">End Time</label>
                                        <div class="form-field-wrapper">
                                            <input type="time" class="form-control" name="event_end_time">
                                            <i class="fas fa-clock field-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── SECTION 3: Organizer & Responsible Unit ── -->
                            <div class="form-section-card">
                                <div class="section-title"><i class="fas fa-user-tie"></i>Organizer &amp; Responsible Unit</div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Responsible Office / Unit</label>
                                        <div class="form-field-wrapper">
                                            <select class="form-select" name="responsible_office">
                                                <option value="">— select —</option>
                                                <option value="School Administration">School Administration</option>
                                                <option value="Guidance Office">Guidance Office</option>
                                                <option value="English Department">English Department</option>
                                                <option value="Mathematics Department">Mathematics Department</option>
                                                <option value="Science Department">Science Department</option>
                                                <option value="Filipino Department">Filipino Department</option>
                                                <option value="AP Department">Araling Panlipunan Dept.</option>
                                                <option value="TLE / TVL Department">TLE / TVL Department</option>
                                                <option value="MAPEH Department">MAPEH Department</option>
                                                <option value="Values Education">Values Education Dept.</option>
                                                <option value="SSLG / SELG">SSLG / SELG</option>
                                                <option value="External Partners">External Partners (EPS)</option>
                                                <option value="SDO / Division Office">SDO / Division Office</option>
                                                <option value="Regional Office">Regional Office</option>
                                                <option value="DepEd Central">DepEd Central Office</option>
                                            </select>
                                            <i class="fas fa-building field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Organizer Name</label>
                                        <div class="form-field-wrapper">
                                            <input type="text" class="form-control" name="organizer_name" placeholder="Full name of organizer / coordinator">
                                            <i class="fas fa-user field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Position / Designation</label>
                                        <div class="form-field-wrapper">
                                            <input type="text" class="form-control" name="organizer_position" placeholder="e.g. School Principal, Department Head">
                                            <i class="fas fa-id-badge field-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── SECTION 6: Event Cover Image ── -->
                            <div class="form-section-card">
                                <div class="section-title"><i class="fas fa-image"></i>Event Cover Image</div>
                                <input type="file" class="form-control" name="event_image" accept="image/*">
                                <small class="text-muted mt-1 d-block">Accepted: JPG, PNG, WEBP. Recommended size: 1200×600px.</small>
                            </div>

                        </form>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                        <button type="submit" form="eventForm" class="btn btn-primary" id="eventSubmitBtn"><i class="fas fa-save me-2"></i>Save Event</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================
         EDIT EVENT DETAILS MODAL
    ====================================================== -->
        <div class="modal fade" id="editEventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Event Details</h5>
                            <small style="opacity:.75;font-size:.8rem;">DepEd Order No. 012, s. 2025 compliant</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editEventForm" enctype="multipart/form-data">
                            <input type="hidden" id="editEventId" name="event_id">
                            <input type="hidden" name="event_date" id="editEventDate">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <!-- ── SECTION 1: Basic Information ── -->
                            <div class="form-section-card">
                                <div class="section-title"><i class="fas fa-info-circle"></i>Basic Information</div>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Event Title <span class="required-star">*</span></label>
                                        <input type="text" class="form-control" id="editEventTitle" name="event_title" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Category <span class="required-star">*</span></label>
                                        <div class="form-field-wrapper">
                                            <select class="form-select" id="editEventCategory" name="event_category" required>
                                                <option value="">Select category…</option>
                                                <optgroup label="── Academic ──">
                                                    <option value="Academic">Academic</option>
                                                    <option value="Academic Calendar">Academic Calendar</option>
                                                    <option value="Assessments">Assessments</option>
                                                    <option value="Remedial &amp; Intervention">Remedial &amp; Intervention</option>
                                                    <option value="Professional Development">Professional Development</option>
                                                </optgroup>
                                                <optgroup label="── Events ──">
                                                    <option value="Sports">Sports</option>
                                                    <option value="Cultural">Cultural</option>
                                                    <option value="Workshops">Workshops</option>
                                                    <option value="Conferences">Conferences</option>
                                                </optgroup>
                                                <optgroup label="── DepEd Programs ──">
                                                    <option value="Health &amp; Nutrition">Health &amp; Nutrition</option>
                                                    <option value="Governance &amp; Elections">Governance &amp; Elections</option>
                                                    <option value="National Celebrations">National Celebrations</option>
                                                    <option value="Activities &amp; Observances">Activities &amp; Observances</option>
                                                </optgroup>
                                                <optgroup label="── Calendar ──">
                                                    <option value="Holidays">Holidays</option>
                                                    <option value="Class Suspension">Class Suspension</option>
                                                    <option value="Break Period">Break Period</option>
                                                </optgroup>
                                            </select>
                                            <i class="fas fa-tag field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Location / Venue</label>
                                        <div class="form-field-wrapper">
                                            <input type="text" class="form-control" id="editEventLocation" name="event_location" placeholder="e.g. Main Auditorium, Online">
                                            <i class="fas fa-map-marker-alt field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Participation Level</label>
                                        <select class="form-select" id="editEventLevel" name="event_level">
                                            <option value="">— select —</option>
                                            <option value="School">School-Level</option>
                                            <option value="District">District-Level</option>
                                            <option value="Division">Division-Level</option>
                                            <option value="Regional">Regional-Level</option>
                                            <option value="National">National</option>
                                            <option value="International">International</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Description / Purpose</label>
                                        <textarea class="form-control" id="editEventDescription" name="event_description" rows="3" placeholder="Event description and objectives…"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- ── SECTION 2: Schedule & Duration ── -->
                            <div class="form-section-card">
                                <div class="section-title"><i class="fas fa-clock"></i>Schedule &amp; Duration</div>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Event Date</label>
                                        <input type="date" class="form-control" id="editEventDateDisplay" name="event_date_edit" readonly style="background:var(--forest-softer);">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">End Date <small class="text-muted">(multi-day)</small></label>
                                        <input type="date" class="form-control" id="editEventEndDate" name="event_end_date">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Start Time</label>
                                        <input type="time" class="form-control" id="editEventStartTime" name="event_start_time">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">End Time</label>
                                        <input type="time" class="form-control" id="editEventEndTime" name="event_end_time">
                                    </div>
                                </div>
                            </div>

                            <!-- ── SECTION 5: Organizer & Responsible Unit ── -->
                            <div class="form-section-card">
                                <div class="section-title"><i class="fas fa-user-tie"></i>Organizer &amp; Responsible Unit</div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Responsible Office / Unit</label>
                                        <select class="form-select" id="editResponsibleOffice" name="responsible_office">
                                            <option value="">— select —</option>
                                            <option value="School Administration">School Administration</option>
                                            <option value="Guidance Office">Guidance Office</option>
                                            <option value="English Department">English Department</option>
                                            <option value="Mathematics Department">Mathematics Department</option>
                                            <option value="Science Department">Science Department</option>
                                            <option value="Filipino Department">Filipino Department</option>
                                            <option value="AP Department">Araling Panlipunan Dept.</option>
                                            <option value="TLE / TVL Department">TLE / TVL Department</option>
                                            <option value="MAPEH Department">MAPEH Department</option>
                                            <option value="Values Education">Values Education Dept.</option>
                                            <option value="SSLG / SELG">SSLG / SELG</option>
                                            <option value="External Partners">External Partners (EPS)</option>
                                            <option value="SDO / Division Office">SDO / Division Office</option>
                                            <option value="Regional Office">Regional Office</option>
                                            <option value="DepEd Central">DepEd Central Office</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Organizer Name</label>
                                        <div class="form-field-wrapper">
                                            <input type="text" class="form-control" id="editOrgName" name="organizer_name">
                                            <i class="fas fa-user field-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Position / Designation</label>
                                        <div class="form-field-wrapper">
                                            <input type="text" class="form-control" id="editOrgPosition" name="organizer_position">
                                            <i class="fas fa-id-badge field-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── SECTION 6: Event Image ── -->
                            <div class="form-section-card">
                                <div class="section-title"><i class="fas fa-image"></i>Event Cover Image</div>
                                <div id="currentImagePreview" class="mb-2"></div>
                                <input type="file" class="form-control" name="event_image" accept="image/*">
                                <small class="text-muted mt-1 d-block">Leave blank to keep the existing image.</small>
                            </div>

                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveEditEventBtn"><i class="fas fa-save me-2"></i>Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======================================================
         MAIN EVENTS LIST + CALENDAR
    ====================================================== -->
        <section id="events-2" class="events-2 section">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="events-list" id="eventsListContainer">
                            <div class="loading-enhanced">
                                <div class="spinner-enhanced"></div>
                                <p class="mt-3 text-muted">Loading events...</p>
                            </div>
                        </div>
                        <div class="pagination-wrapper">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1"><i class="bi bi-chevron-left"></i></a></li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="events-page-sidebar">
                            <div class="sidebar-item">
                                <h3 class="sidebar-title">Upcoming Events</h3>
                                <div class="calendar-container">
                                    <div class="calendar-wrapper">
                                        <div class="month" id="calendarMonth">
                                            <ul>
                                                <li class="prev" onclick="changeMonth(-1)">&#10094;</li>
                                                <li class="next" onclick="changeMonth(1)">&#10095;</li>
                                                <li id="monthYearDisplay"></li>
                                            </ul>
                                        </div>
                                        <ul class="weekdays">
                                            <li>Su</li>
                                            <li>Mo</li>
                                            <li>Tu</li>
                                            <li>We</li>
                                            <li>Th</li>
                                            <li>Fr</li>
                                            <li>Sa</li>
                                        </ul>
                                        <ul class="days" id="calendarDays"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="sidebar-item">
                                <h3 class="sidebar-title">Event Categories</h3>
                                <div class="categories">
                                    <ul>
                                        <li><a href="#">Academic <span>(<?php echo $category_counts['Academic']; ?>)</span></a></li>
                                        <li><a href="#">Sports <span>(<?php echo $category_counts['Sports']; ?>)</span></a></li>
                                        <li><a href="#">Cultural <span>(<?php echo $category_counts['Cultural']; ?>)</span></a></li>
                                        <li><a href="#">Workshops <span>(<?php echo $category_counts['Workshops']; ?>)</span></a></li>
                                        <li><a href="#">Conferences <span>(<?php echo $category_counts['Conferences']; ?>)</span></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================
         SECTION: EVENT MANAGEMENT (CRUD)
    ====================================================== -->
        <section class="container mb-5">
            <div class="admin-section-card">
                <div class="admin-section-title">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Event Management</span>
                    <div class="ms-auto">
                        <button class="btn btn-enhanced interactive-element" onclick="openCreateModal()" style="background:linear-gradient(135deg,#102C26,#1B4D44);font-size:.85rem;padding:.55rem 1.25rem;">
                            <i class="fas fa-plus me-2"></i>Create New Event
                        </button>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="row g-3 mb-4 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label"><i class="fas fa-search me-1"></i>Search Events</label>
                        <div class="form-field-wrapper">
                            <input type="text" class="form-control focus-ring" id="eventSearchInput" placeholder="Search by title…">
                            <i class="fas fa-search field-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="eventCategoryFilter" class="form-label"><i class="fas fa-tag me-1"></i>Category</label>
                        <select class="form-select focus-ring" id="eventCategoryFilter">
                            <option value="">All Categories</option>
                            <option value="Academic">Academic</option>
                            <option value="Sports">Sports</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Workshops">Workshops</option>
                            <option value="Conferences">Conferences</option>
                            <option value="Academic Calendar">Academic Calendar</option>
                            <option value="Holidays">Holidays</option>
                            <option value="Health &amp; Nutrition">Health &amp; Nutrition</option>
                            <option value="Governance &amp; Elections">Governance &amp; Elections</option>
                            <option value="Assessments">Assessments</option>
                            <option value="Professional Development">Professional Development</option>
                            <option value="Remedial &amp; Intervention">Remedial &amp; Intervention</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="eventStatusFilter" class="form-label"><i class="fas fa-filter me-1"></i>Status</label>
                        <select class="form-select focus-ring" id="eventStatusFilter">
                            <option value="">All Events</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="past">Past</option>
                            <option value="official">Official Only</option>
                        </select>
                    </div>
                </div>

                <!-- Events Table -->
                <div class="table-responsive">
                    <table class="table table-hover table-enhanced" id="eventsManagementTable">
                        <thead>
                            <tr>
                                <th><i class="fas fa-calendar me-2"></i>Date</th>
                                <th><i class="fas fa-heading me-2"></i>Title</th>
                                <th><i class="fas fa-tag me-2"></i>Category</th>
                                <th><i class="fas fa-map-marker-alt me-2"></i>Location</th>
                                <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                <th><i class="fas fa-cogs me-2"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="eventsManagementBody">
                            <?php 
                            $events_query = "SELECT * FROM events ORDER BY event_date DESC, created_at DESC";
                            $events_result = $conn->query($events_query);
                            if ($events_result && $events_result->num_rows > 0):
                                while ($event = $events_result->fetch_assoc()): 
                                    $event_date = new DateTime($event['event_date']);
                                    $is_past = $event_date < new DateTime();
                                    $status_class = $is_past ? 'secondary' : ($event['is_official'] ? 'success' : 'primary');
                                    $status_text = $is_past ? 'Past' : ($event['is_official'] ? 'Official' : 'Regular');
                            ?>
                                <tr id="event-row-<?php echo $event['id']; ?>">
                                    <td>
                                        <span class="event-date-badge">
                                            <strong><?php echo $event_date->format('M d'); ?></strong><br>
                                            <small><?php echo $event_date->format('Y'); ?></small>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="event-title-cell">
                                            <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                            <?php if ($event['event_start_time']): ?>
                                                <br><small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo $event['event_start_time']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="event-item-category <?php echo strtolower($event['category']); ?>">
                                            <?php echo htmlspecialchars($event['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($event['location']): ?>
                                            <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($event['location']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group-enhanced">
                                            <button class="btn btn-outline-primary btn-enhanced interactive-element" onclick="editEvent(<?php echo $event['id']; ?>)" title="Edit Event">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-info btn-enhanced interactive-element" onclick="viewEventDetails(<?php echo $event['id']; ?>)" title="View Event Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-enhanced interactive-element" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo addslashes(htmlspecialchars($event['title'])); ?>')" title="Delete Event">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x mb-3" style="color:var(--border);display:block;"></i>
                                        <p class="mb-2" style="font-family:'Outfit',sans-serif;font-weight:700;color:var(--ink-soft);">No events found</p>
                                        <p class="text-muted mb-3" style="font-size:.85rem;">Click "Create New Event" or select a date on the calendar to add your first event.</p>
                                        <button class="btn btn-enhanced" onclick="openCreateModal()" style="font-size:.83rem;padding:.5rem 1.25rem;"><i class="fas fa-plus me-2"></i>Create First Event</button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ======================================================
         SECTION: EVENT JOIN APPLICATIONS
    ====================================================== -->
        <section class="container mb-5">
            <div class="admin-section-card">
                <div class="admin-section-title">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Event Join Applications</span>
                </div>

                <!-- Filter by event -->
                <div class="event-select-filter">
                    <label for="appEventFilter" class="form-label"><i class="fas fa-filter me-1"></i>Filter by Event</label>
                    <select class="form-select" id="appEventFilter" onchange="loadApplications()">
                        <option value="">All Events</option>
                        <?php foreach ($all_events_list as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>"><?php echo htmlspecialchars($ev['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover applications-table" id="applicationsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Event</th>
                                <th>Applied</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="applicationsBody">
                            <?php if (empty($all_applications)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <i class="fas fa-clipboard fa-3x mb-3" style="color:var(--border);display:block;"></i>
                                        <p style="font-family:'Outfit',sans-serif;font-weight:700;color:var(--ink-soft);margin-bottom:4px;">No applications yet</p>
                                        <p class="text-muted" style="font-size:.85rem;">Student applications to join events will appear here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_applications as $i => $app): ?>
                                    <tr id="app-row-<?php echo $app['id']; ?>">
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo htmlspecialchars($app['student_name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($app['student_id']); ?></code></td>
                                        <td><?php echo htmlspecialchars($app['email'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($app['phone'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($app['event_title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                        <td><span class="status-badge <?php echo $app['status']; ?>"><?php echo $app['status']; ?></span></td>
                                        <td>
                                            <?php if ($app['status'] !== 'Approved'): ?>
                                                <button class="btn-approve me-1" onclick="updateAppStatus(<?php echo $app['id']; ?>,'Approved')"><i class="fas fa-check me-1"></i>Approve</button>
                                            <?php endif; ?>
                                            <?php if ($app['status'] !== 'Rejected'): ?>
                                                <button class="btn-reject" onclick="updateAppStatus(<?php echo $app['id']; ?>,'Rejected')"><i class="fas fa-times me-1"></i>Reject</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ======================================================
         SECTION: GROUP MANAGEMENT
    ====================================================== -->
        <section class="container mb-5">
            <div class="admin-section-card">
                <div class="admin-section-title">
                    <i class="fas fa-users"></i>
                    <span>Group Management</span>
                </div>

                <div class="row align-items-end mb-4">
                    <div class="col-md-5">
                        <label for="groupEventSelect" class="form-label"><i class="fas fa-calendar-alt me-1"></i>Select Event</label>
                        <select class="form-select" id="groupEventSelect" onchange="loadGroupsForEvent()">
                            <option value="">— Choose event —</option>
                            <?php foreach ($all_events_list as $ev): ?>
                                <option value="<?php echo $ev['id']; ?>"><?php echo htmlspecialchars($ev['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="newGroupName" class="form-label"><i class="fas fa-users me-1"></i>New Group Name</label>
                        <input type="text" class="form-control" id="newGroupName" placeholder="e.g. Group A">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" onclick="createGroup()"><i class="fas fa-plus me-1"></i>Create Group</button>
                    </div>
                </div>

                <div id="groupsContainer">
                    <p class="text-muted text-center py-3">Select an event to manage its groups.</p>
                </div>
            </div>
        </section>

    </main><!-- end #main-content -->

    <script src="/BUNHS_School_System/assets/vendor/bootstrap/js/bootstrap.bundle.min.js?v=20260326"></script>
    <script src="/BUNHS_School_System/assets/js/main.js?v=20260326"></script>
    <script src="/BUNHS_School_System/admin_account/admin_assets/js/admin_script.js?v=20260326"></script>

    <script>
        // ============================================================
        //  PREVENT MAIN.JS ERRORS - Defensive checks for missing elements
        // ============================================================
        // Override main.js functions that might cause errors
        const preventMainJSErrors = function() {
            // Prevent toggleScrolled errors
            const originalToggleScrolled = window.toggleScrolled;
            if (typeof originalToggleScrolled === 'function') {
                window.toggleScrolled = function() {
                    try {
                        const element = document.querySelector('header, .topbar, .navbar');
                        if (element && element.classList) {
                            return originalToggleScrolled.apply(this, arguments);
                        }
                    } catch (e) {
                        console.warn('toggleScrolled error prevented:', e);
                    }
                };
            }
            
            // Prevent addEventListener errors
            const elementsThatMightNotExist = [
                '.hamburger-btn',
                '.search input',
                '.user-dropdown',
                '.notification-btn'
            ];
            
            elementsThatMightNotExist.forEach(selector => {
                const element = document.querySelector(selector);
                if (element && element.addEventListener) {
                    // Element exists and has addEventListener - safe to use
                }
            });
        };

        // ============================================================
        //  INIT
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            // First prevent main.js errors
            preventMainJSErrors();
            
            // Then load navigation
            loadNavigation();
            
            // Finally initialize calendar
            initCalendar();
        });

        // ============================================================
        //  TOAST
        // ============================================================
        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast-notification ' + type;
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-times-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            toast.innerHTML = `<i class="${icons[type]}"></i><span class="toast-message">${message}</span><button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
            container.appendChild(toast);
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
            }, duration);
        }

        // ============================================================
        //  NAV LOADING 
        // ============================================================
        function loadNavigation() {
            const container = document.getElementById('navigation-container');
            if (!container) {
                console.error('Navigation container not found');
                return;
            }
            
            console.log('Loading navigation...');
            fetch('/BUNHS_School_System/admin_account/admin_nav.php?embed=html&current_page=announcements')
                .then(r => {
                    console.log('Navigation response status:', r.status);
                    if (!r.ok) {
                        throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                    }
                    return r.text();
                })
                .then(data => {
                    console.log('Navigation loaded successfully, length:', data.length);
                    container.innerHTML = data;
                    initializeNavigation();
                    const mainContent = document.getElementById('main-content');
                    if (mainContent) {
                        mainContent.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Navigation loading failed:', error);
                    container.innerHTML = `<div class="nav-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Unable to Load Navigation</h3>
                        <p>Error: ${error.message}</p>
                        <button class="btn-retry" onclick="loadNavigation()">Try Again</button>
                    </div>`;
                });
        }

        function initializeNavigation() {
            const navigationContainer = document.querySelector('#navigation-container');
            if (navigationContainer) {
                const mainDiv = navigationContainer.querySelector('.main');
                if (mainDiv) {
                    while (mainDiv.firstChild) {
                        navigationContainer.appendChild(mainDiv.firstChild);
                    }
                    mainDiv.remove();
                }
            }
            fixAllNavLinks();
            initDropdowns();
        }

        function getAdminBase() {
            const parts = window.location.pathname.split('/');
            const idx = parts.indexOf('admin_account');
            if (idx !== -1) return parts.slice(0, idx + 1).join('/') + '/';
            return window.location.pathname.split('/').slice(0, -1).join('/') + '/';
        }

        function fixAllNavLinks() {
            const adminBase = getAdminBase();
            document.querySelectorAll('.sidebar a[href], .topbar a[href], .user-menu a[href]').forEach(link => {
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('http') || href.startsWith('/')) return;
                if (href.startsWith('admin_account/')) link.setAttribute('href', adminBase + href.replace('admin_account/', ''));
                else if (!href.startsWith('../') && !href.startsWith('./')) link.setAttribute('href', adminBase + href);
            });
            document.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
                item.setAttribute('href', getAdminBase() + 'announcements/' + item.getAttribute('data-page'));
            });
        }

        function initDropdowns() {
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                if (!toggle) return;
                const fresh = toggle.cloneNode(true);
                toggle.parentNode.replaceChild(fresh, toggle);
                fresh.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = this.closest('.dropdown');
                    if (!dropdown) return;
                    const isActive = dropdown.classList.contains('active');
                    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
                    if (!isActive) dropdown.classList.add('active');
                });
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('.dropdown')) document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
            });
        }

        // ============================================================
        //  DYNAMIC ROW HELPERS (highlights & schedule)
        // ============================================================
        function addHighlightRow() {
            const c = document.getElementById('highlightsContainer');
            const div = document.createElement('div');
            div.className = 'highlight-row';
            div.innerHTML = `<input type="text" class="form-control form-control-sm" name="highlights[]" placeholder="Highlight item"><button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>`;
            c.appendChild(div);
        }

        function addScheduleRow() {
            const c = document.getElementById('scheduleContainer');
            const div = document.createElement('div');
            div.className = 'schedule-row-input';
            div.innerHTML = `<input type="text" class="form-control form-control-sm" name="schedule_time[]" placeholder="Time" style="width:200px;flex:none;"><input type="text" class="form-control form-control-sm" name="schedule_activity[]" placeholder="Activity"><input type="text" class="form-control form-control-sm" name="schedule_desc[]" placeholder="Description (optional)"><button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>`;
            c.appendChild(div);
        }

        function addEditHighlightRow(val = '') {
            const c = document.getElementById('editHighlightsContainer');
            const div = document.createElement('div');
            div.className = 'highlight-row';
            div.innerHTML = `<input type="text" class="form-control form-control-sm" name="highlights[]" value="${escHtml(val)}" placeholder="Highlight item"><button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>`;
            c.appendChild(div);
        }

        function addEditScheduleRow(time = '', act = '', desc = '') {
            const c = document.getElementById('editScheduleContainer');
            const div = document.createElement('div');
            div.className = 'schedule-row-input';
            div.innerHTML = `<input type="text" class="form-control form-control-sm" name="schedule_time[]" value="${escHtml(time)}" placeholder="Time" style="width:200px;flex:none;"><input type="text" class="form-control form-control-sm" name="schedule_activity[]" value="${escHtml(act)}" placeholder="Activity"><input type="text" class="form-control form-control-sm" name="schedule_desc[]" value="${escHtml(desc)}" placeholder="Description (optional)"><button type="button" class="btn-remove-row" onclick="removeRow(this)">×</button>`;
            c.appendChild(div);
        }

        function removeRow(btn) {
            btn.parentElement.remove();
        }

        function escHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // ============================================================
        //  AJAX HELPER
        // ============================================================
        function ajaxPost(params) {
            return fetch('', {
                method: 'POST',
                body: new URLSearchParams(params),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            }).then(r => r.json());
        }

        function ajaxFormData(formData) {
            formData.append('_method', 'post');
            return fetch('', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(r => r.json());
        }

        // ============================================================
        //  CALENDAR + EVENTS (from original, intact)
        // ============================================================
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();
        let eventsData = {};
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        function initCalendar() {
            loadEventsForMonth(currentYear, currentMonth + 1);
            loadUpcomingEvents();
            renderCalendar(currentYear, currentMonth);
        }

        function loadEventsForMonth(year, month) {
            ajaxPost({
                action: 'get_events',
                year,
                month
            }).then(data => {
                if (data.status === 'success') {
                    eventsData = {};
                    data.events.forEach(ev => {
                        const k = ev.event_date;
                        if (!eventsData[k]) eventsData[k] = [];
                        eventsData[k].push(ev);
                    });
                    renderCalendar(currentYear, currentMonth);
                }
            }).catch(e => console.error(e));
        }

        function formatEventDateRange(dateStr, days) {
            const start = new Date(dateStr);
            const d = parseInt(days) || 1;
            if (d === 1) return '1day';
            const end = new Date(start);
            end.setDate(start.getDate() + d - 1);
            const sm = start.getMonth(),
                em = end.getMonth(),
                sy = start.getFullYear(),
                ey = end.getFullYear();
            if (sm === em && sy === ey) return monthNames[sm] + ' ' + start.getDate() + '-' + end.getDate() + ', ' + sy;
            if (sy === ey) return monthNames[sm] + ' ' + start.getDate() + ' - ' + monthNames[em] + ' ' + end.getDate() + ', ' + sy;
            return monthNames[sm] + ' ' + start.getDate() + ', ' + sy + ' - ' + monthNames[em] + ' ' + end.getDate() + ', ' + ey;
        }

        function formatEventTime(s, e) {
            const fmt = t => {
                if (!t) return '';
                const [h, m] = t.split(':');
                const hr = parseInt(h);
                return (hr % 12 || 12) + ':' + m + (hr >= 12 ? ' PM' : ' AM');
            };
            if (s && e) return fmt(s) + ' - ' + fmt(e);
            return fmt(s || e);
        }

        function createEventHTML(event) {
            const d = new Date(event.event_date);
            const cat = event.category.toLowerCase();
            const dateRange = formatEventDateRange(event.event_date, event.event_days);
            const time = formatEventTime(event.event_start_time, event.event_end_time);
            const btnText = (event.team_based == 1 || event.team_based === true) ? 'Join Now' : 'Learn More';
            return `<div class="event-item">
        <div class="event-date"><span class="day">${d.getDate()}</span><span class="month">${monthNames[d.getMonth()].toUpperCase().slice(0,3)}</span></div>
        <div class="event-content">
            <h3>${escHtml(event.title)}</h3>
            <div class="event-meta">
                ${time ? `<p><i class="bi bi-clock"></i> ${time}</p>` : ''}
                <p><i class="bi bi-calendar-event"></i> ${dateRange}</p>
            </div>
            ${event.description ? `<p>${escHtml(event.description)}</p>` : ''}
            <div class="d-flex gap-2 flex-wrap mt-2">
                <a href="event-details.php?id=${event.id}" class="btn-event">${btnText} <i class="bi bi-arrow-right"></i></a>
                <button class="manage-event-btn btn-manage-event" onclick="openEditModal(${event.id})"><i class="fas fa-edit me-1"></i>Edit Details</button>
            </div>
        </div>
    </div>`;
        }

        function loadUpcomingEvents() {
            ajaxPost({
                action: 'get_upcoming_events',
                limit: 10
            }).then(data => {
                const c = document.getElementById('eventsListContainer');
                if (data.status === 'success' && data.events.length > 0) {
                    c.innerHTML = data.events.map(createEventHTML).join('');
                } else {
                    c.innerHTML = '<div class="text-center py-5"><p class="text-muted">No upcoming events. Click on a date in the calendar to add one!</p></div>';
                }
            }).catch(() => {
                document.getElementById('eventsListContainer').innerHTML = '<div class="text-center py-5"><p class="text-muted">Error loading events.</p></div>';
            });
        }

        function renderCalendar(year, month) {
            const disp = document.getElementById('monthYearDisplay');
            const days = document.getElementById('calendarDays');
            disp.innerHTML = monthNames[month] + '<br><span style="font-size:18px">' + year + '</span>';
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const daysInPrev = new Date(year, month, 0).getDate();
            const today = new Date();
            let html = '';
            for (let i = firstDay - 1; i >= 0; i--) html += `<li class="other-month">${daysInPrev-i}</li>`;
            for (let i = 1; i <= daysInMonth; i++) {
                const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(i).padStart(2, '0');
                const isToday = (year === today.getFullYear() && month === today.getMonth() && i === today.getDate());
                const hasEvents = eventsData[dateStr] && eventsData[dateStr].length > 0;
                let cls = isToday ? ' today' : '';
                let dots = '';
                if (hasEvents) eventsData[dateStr].forEach(ev => {
                    dots += `<span class="event-dot ${ev.category.toLowerCase()}"></span>`;
                });
                html += `<li${cls} onclick="openEventModal('${dateStr}')">${i}${dots}</li>`;
            }
            const total = Math.ceil((firstDay + daysInMonth) / 7) * 7;
            for (let i = 1; i <= (total - firstDay - daysInMonth); i++) html += `<li class="other-month">${i}</li>`;
            days.innerHTML = html;
        }

        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            } else if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            loadEventsForMonth(currentYear, currentMonth + 1);
        }

        function openEventModal(dateStr) {
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            document.getElementById('eventForm').reset();
            document.getElementById('eventDate').value = dateStr;
            if (document.getElementById('eventStartDateDisplay'))
                document.getElementById('eventStartDateDisplay').value = dateStr;
            
            // Set date display if element exists
            const dateDisplay = document.getElementById('eventDateDisplay');
            if (dateDisplay) {
                dateDisplay.textContent = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
            
            loadEventsForDate(dateStr);
            modal.show();
        }

        function loadEventsForDate(dateStr) {
            const c = document.getElementById('eventsListForDate');
            if (!c) {
                // Element doesn't exist in the current modal structure, so just skip
                return;
            }
            if (eventsData[dateStr] && eventsData[dateStr].length > 0) {
                c.innerHTML = eventsData[dateStr].map(ev =>
                    `<div class="event-list-item category-${ev.category.toLowerCase()} position-relative">
                <button class="delete-event-btn" onclick="deleteEvent(${ev.id},'${dateStr}')"><i class="fas fa-trash"></i></button>
                <h6>${escHtml(ev.title)}</h6>
                <span class="event-item-category ${ev.category.toLowerCase()}">${ev.category}</span>
                ${ev.description?`<p class="mt-2">${escHtml(ev.description)}</p>`:''}
            </div>`
                ).join('');
            } else {
                c.innerHTML = '<p class="text-muted">No events on this date.</p>';
            }
        }

        function deleteEvent(eventId, dateStr) {
            if (!confirm('Delete this event?')) return;
            ajaxPost({
                action: 'delete_event',
                id: eventId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Event deleted.', 'success');
                    loadEventsForMonth(currentYear, currentMonth + 1);
                    loadUpcomingEvents();
                    loadEventsForDate(dateStr);
                } else showToast('Error: ' + data.message, 'error');
            });
        }

        function insertEventDynamically(event) {
            const c = document.getElementById('eventsListContainer');
            const spinner = c.querySelector('.spinner-border');
            if (spinner) c.innerHTML = '';
            const noMsg = c.querySelector('.text-muted');
            if (noMsg && noMsg.textContent.includes('No upcoming')) c.innerHTML = '';
            c.insertAdjacentHTML('afterbegin', createEventHTML(event));
        }

        // Enhanced Form Validation System
        class FormValidator {
            constructor(formId) {
                this.form = document.getElementById(formId);
                this.rules = {};
                this.setupValidation();
            }

            addRule(fieldName, rules) {
                this.rules[fieldName] = rules;
            }

            validateField(fieldName, value) {
                const rules = this.rules[fieldName];
                if (!rules) return { valid: true };

                for (const rule of rules) {
                    const result = rule.test(value);
                    if (!result.valid) {
                        this.showFieldError(fieldName, result.message);
                        return result;
                    }
                }

                this.clearFieldError(fieldName);
                return { valid: true };
            }

            validateAll() {
                let isValid = true;
                const firstInvalidField = null;

                for (const fieldName in this.rules) {
                    const field = this.form.querySelector(`[name="${fieldName}"]`);
                    if (field) {
                        const result = this.validateField(fieldName, field.value);
                        if (!result.valid) {
                            isValid = false;
                            if (!firstInvalidField) firstInvalidField = field;
                        }
                    }
                }

                if (!isValid && firstInvalidField) {
                    firstInvalidField.focus();
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                return isValid;
            }

            showFieldError(fieldName, message) {
                const field = this.form.querySelector(`[name="${fieldName}"]`);
                const feedback = document.getElementById(`${fieldName.replace(/[^a-zA-Z0-9]/g, '')}Feedback`);
                
                if (field) {
                    field.classList.add('is-invalid');
                    field.classList.remove('is-valid');
                }
                
                if (feedback) {
                    feedback.textContent = message;
                    feedback.style.display = 'block';
                }
            }

            clearFieldError(fieldName) {
                const field = this.form.querySelector(`[name="${fieldName}"]`);
                const feedback = document.getElementById(`${fieldName.replace(/[^a-zA-Z0-9]/g, '')}Feedback`);
                
                if (field) {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
                
                if (feedback) {
                    feedback.textContent = '';
                    feedback.style.display = 'none';
                }
            }

            setupValidation() {
                // Real-time validation
                this.form.addEventListener('input', (e) => {
                    if (e.target.name && this.rules[e.target.name]) {
                        this.validateField(e.target.name, e.target.value);
                    }
                });

                // Form submission validation
                this.form.addEventListener('submit', (e) => {
                    if (!this.validateAll()) {
                        e.preventDefault();
                        showToast('Please correct the errors below', 'error');
                    }
                });
            }
        }

        // Initialize enhanced validator for event form
        const eventValidator = new FormValidator('eventForm');
        
        eventValidator.addRule('event_title', [
            {
                test: (value) => {
                    if (!value.trim()) return { valid: false, message: 'Event title is required' };
                    if (value.length > 100) return { valid: false, message: 'Title too long (max 100 characters)' };
                    if (!/^[a-zA-Z0-9\s\-_,.()&\/]+$/.test(value)) return { valid: false, message: 'Title contains invalid characters' };
                    return { valid: true };
                }
            }
        ]);

        eventValidator.addRule('event_category', [
            {
                test: (value) => {
                    if (!value) return { valid: false, message: 'Category is required' };
                    return { valid: true };
                }
            }
        ]);

        eventValidator.addRule('event_date', [
            {
                test: (value) => {
                    if (!value) return { valid: false, message: 'Event date is required' };
                    if (new Date(value) < new Date().setHours(0,0,0,0)) return { valid: false, message: 'Event date cannot be in the past' };
                    return { valid: true };
                }
            }
        ]);

        eventValidator.addRule('event_description', [
            {
                test: (value) => {
                    if (value && value.length > 500) return { valid: false, message: 'Description too long (max 500 characters)' };
                    return { valid: true };
                }
            }
        ]);

        // Character counters
        function updateCharacterCounter(fieldId, counterId, maxLength) {
            const field = document.getElementById(fieldId);
            const counter = document.getElementById(counterId);
            
            if (field && counter) {
                field.addEventListener('input', () => {
                    const length = field.value.length;
                    counter.textContent = `${length}/${maxLength}`;
                    counter.style.color = length > maxLength * 0.9 ? '#e74c3c' : '#6c757d';
                });
            }
        }

        updateCharacterCounter('eventTitle', 'titleCounter', 100);
        updateCharacterCounter('eventDescription', 'descCounter', 500);

        // Auto-save functionality
        class AutoSave {
            constructor(formId, storageKey = 'eventDraft', interval = 30000) {
                this.form = document.getElementById(formId);
                this.storageKey = storageKey;
                this.interval = interval;
                this.timer = null;
                this.lastSave = null;
                this.setupAutoSave();
            }

            getFormData() {
                const formData = new FormData(this.form);
                const data = {};
                for (let [key, value] of formData.entries()) {
                    if (key !== 'csrf_token') { // Don't save CSRF token
                        data[key] = value;
                    }
                }
                return data;
            }

            saveToStorage() {
                const data = this.getFormData();
                
                // Only save if data has changed
                const currentData = JSON.stringify(data);
                if (this.lastSave !== currentData) {
                    localStorage.setItem(this.storageKey, currentData);
                    localStorage.setItem(`${this.storageKey}_timestamp`, Date.now());
                    this.lastSave = currentData;
                    this.showSaveIndicator();
                }
            }

            loadFromStorage() {
                const savedData = localStorage.getItem(this.storageKey);
                const timestamp = localStorage.getItem(`${this.storageKey}_timestamp`);
                
                if (savedData && timestamp) {
                    const age = Date.now() - parseInt(timestamp);
                    const maxAge = 24 * 60 * 60 * 1000; // 24 hours
                    
                    if (age < maxAge) {
                        try {
                            const data = JSON.parse(savedData);
                            this.populateForm(data);
                            this.showRestoreIndicator();
                            return true;
                        } catch (e) {
                            console.error('Error loading draft:', e);
                        }
                    }
                }
                return false;
            }

            populateForm(data) {
                // Populate form fields
                for (const [key, value] of Object.entries(data)) {
                    const field = this.form.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'checkbox') {
                            field.checked = value === '1' || value === true;
                        } else if (field.type === 'radio') {
                            const radio = this.form.querySelector(`[name="${key}"][value="${value}"]`);
                            if (radio) radio.checked = true;
                        } else if (field.type === 'file') {
                            // Skip file inputs - they cannot be programmatically set for security reasons
                            continue;
                        } else {
                            field.value = value;
                        }
                        
                        // Trigger change events for dynamic fields
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
            }

            clearStorage() {
                localStorage.removeItem(this.storageKey);
                localStorage.removeItem(`${this.storageKey}_timestamp`);
                this.lastSave = null;
                this.hideSaveIndicator();
            }

            setupAutoSave() {
                // Load draft on page load
                if (this.loadFromStorage()) {
                    // Show restore notification
                    setTimeout(() => {
                        showToast('Draft restored from auto-save', 'info');
                    }, 1000);
                }

                // Start auto-save timer
                this.startAutoSave();

                // Save on form changes
                this.form.addEventListener('input', () => {
                    this.debounceSave();
                });

                this.form.addEventListener('change', () => {
                    this.debounceSave();
                });

                // Clear on successful submission
                this.form.addEventListener('submit', () => {
                    this.clearStorage();
                });
            }

            startAutoSave() {
                this.stopAutoSave();
                this.timer = setInterval(() => {
                    this.saveToStorage();
                }, this.interval);
            }

            stopAutoSave() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            }

            debounceSave() {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.saveToStorage();
                }, 2000); // Wait 2 seconds after user stops typing
            }

            showSaveIndicator() {
                let indicator = document.getElementById('autoSaveIndicator');
                if (!indicator) {
                    indicator = document.createElement('div');
                    indicator.id = 'autoSaveIndicator';
                    indicator.innerHTML = '<i class="fas fa-save me-1"></i>Draft saved';
                    indicator.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: var(--forest-deep);
                        color: white;
                        padding: 8px 16px;
                        border-radius: 8px;
                        font-size: 14px;
                        z-index: 9999;
                        box-shadow: var(--shadow-md);
                        opacity: 0;
                        transition: opacity 0.3s ease;
                    `;
                    document.body.appendChild(indicator);
                }
                
                setTimeout(() => {
                    indicator.style.opacity = '1';
                }, 100);

                setTimeout(() => {
                    indicator.style.opacity = '0';
                }, 2000);
            }

            showRestoreIndicator() {
                const indicator = document.getElementById('autoSaveIndicator');
                if (indicator) {
                    indicator.innerHTML = '<i class="fas fa-history me-1"></i>Draft restored';
                    indicator.style.background = 'var(--gold)';
                    
                    setTimeout(() => {
                        indicator.style.opacity = '0';
                    }, 3000);
                }
            }

            hideSaveIndicator() {
                const indicator = document.getElementById('autoSaveIndicator');
                if (indicator) {
                    indicator.remove();
                }
            }
        }

        // Initialize auto-save for event form
        const autoSave = new AutoSave('eventForm', 'eventDraft');

        // Submit add-event form with enhanced validation
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Use validator instead of basic checks
            if (!eventValidator.validateAll()) {
                return;
            }
            const btn = document.getElementById('eventSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            const fd = new FormData(this);
            fd.append('action', 'add_event');
            ajaxFormData(fd).then(data => {
                    if (data.status === 'success') {
                        showToast(`Event "${title}" added!`, 'success');
                        this.reset();
                        document.getElementById('eventDays').value = '1';
                        loadEventsForMonth(currentYear, currentMonth + 1);
                        loadEventsForDate(date);
                        loadUpcomingEvents();
                        bootstrap.Modal.getInstance(document.getElementById('eventModal'))?.hide();
                    } else showToast('Error: ' + data.message, 'error');
                }).catch(() => showToast('An error occurred.', 'error'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-2"></i>Add Event';
                });
        });

        // ============================================================
        //  EDIT EVENT MODAL
        // ============================================================
        function openEditModal(eventId) {
            ajaxPost({
                action: 'get_event',
                event_id: eventId
            }).then(data => {
                if (data.status !== 'success') {
                    showToast('Could not load event.', 'error');
                    return;
                }
                const ev = data.event;
                document.getElementById('editEventId').value = ev.id;
                document.getElementById('editEventDate').value = ev.event_date;
                document.getElementById('editEventTitle').value = ev.title || '';
                document.getElementById('editEventCategory').value = ev.category || 'Academic';
                document.getElementById('editEventLocation').value = ev.location || '';
                document.getElementById('editEventStartTime').value = ev.event_start_time || '';
                document.getElementById('editEventEndTime').value = ev.event_end_time || '';
                document.getElementById('editEventDescription').value = ev.description || '';
                document.getElementById('editOrgName').value = ev.organizer_name || '';
                document.getElementById('editOrgPosition').value = ev.organizer_position || '';

                // Image preview
                const prev = document.getElementById('currentImagePreview');
                if (prev) {
                    prev.innerHTML = ev.image ? `<img src="../../assets/img/events/${escHtml(ev.image)}" style="max-height:100px;border-radius:8px;" alt="Current image"> <small class="text-muted ms-2">${escHtml(ev.image)}</small>` : '<small class="text-muted">No image uploaded.</small>';
                }

                new bootstrap.Modal(document.getElementById('editEventModal')).show();
            });
        }

        document.getElementById('saveEditEventBtn').addEventListener('click', function() {
            const form = document.getElementById('editEventForm');
            const fd = new FormData(form);
            fd.append('action', 'update_event');
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            ajaxFormData(fd).then(data => {
                    if (data.status === 'success') {
                        showToast('Event updated successfully!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('editEventModal'))?.hide();
                        loadUpcomingEvents();
                        loadEventsForMonth(currentYear, currentMonth + 1);
                    } else showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                }).catch(() => showToast('An error occurred.', 'error'))
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-save me-2"></i>Save Changes';
                });
        });

        // ============================================================
        //  APPLICATIONS
        // ============================================================
        function loadApplications() {
            const eid = document.getElementById('appEventFilter').value;
            const params = {
                action: 'get_applications'
            };
            if (eid) params.event_id = eid;
            ajaxPost(params).then(data => {
                if (data.status !== 'success') return;
                const tbody = document.getElementById('applicationsBody');
                if (!data.applications.length) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No applications found.</td></tr>';
                    return;
                }
                tbody.innerHTML = data.applications.map((a, i) => `
            <tr id="app-row-${a.id}">
                <td>${i+1}</td>
                <td>${escHtml(a.student_name)}</td>
                <td><code>${escHtml(a.student_id)}</code></td>
                <td>${escHtml(a.email||'—')}</td>
                <td>${escHtml(a.phone||'—')}</td>
                <td>${escHtml(a.event_title)}</td>
                <td>${new Date(a.applied_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</td>
                <td><span class="status-badge ${a.status}">${a.status}</span></td>
                <td>
                    ${a.status!=='Approved'?`<button class="btn-approve me-1" onclick="updateAppStatus(${a.id},'Approved')"><i class="fas fa-check me-1"></i>Approve</button>`:''}
                    ${a.status!=='Rejected'?`<button class="btn-reject" onclick="updateAppStatus(${a.id},'Rejected')"><i class="fas fa-times me-1"></i>Reject</button>`:''}
                </td>
            </tr>`).join('');
            });
        }

        function updateAppStatus(appId, status) {
            ajaxPost({
                action: 'update_application_status',
                app_id: appId,
                status
            }).then(data => {
                if (data.status === 'success') {
                    showToast(`Application ${status}.`, status === 'Approved' ? 'success' : 'warning');
                    loadApplications();
                } else showToast('Error updating status.', 'error');
            });
        }

        // ============================================================
        //  GROUP MANAGEMENT
        // ============================================================
        function loadGroupsForEvent() {
            const eid = document.getElementById('groupEventSelect').value;
            if (!eid) {
                document.getElementById('groupsContainer').innerHTML = '<p class="text-muted text-center py-3">Select an event to manage its groups.</p>';
                return;
            }
            ajaxPost({
                action: 'get_groups',
                event_id: eid
            }).then(data => {
                if (data.status !== 'success') return;
                renderGroups(data.groups, eid);
            });
        }

        function renderGroups(groups, eventId) {
            const c = document.getElementById('groupsContainer');
            if (!groups.length) {
                c.innerHTML = '<p class="text-muted text-center py-3">No groups yet. Create one above.</p>';
                return;
            }
            c.innerHTML = groups.map(g => `
        <div class="group-card" id="group-card-${g.id}">
            <div class="group-title">
                <span><i class="fas fa-layer-group me-2"></i>${escHtml(g.group_name)}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteGroup(${g.id})"><i class="fas fa-trash me-1"></i>Delete Group</button>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <strong class="d-block mb-2"><i class="fas fa-user-graduate me-1"></i>Members</strong>
                    <div id="members-${g.id}">
                        ${g.members.map(m=>`<span class="member-tag">${escHtml(m.student_name)} <small class="text-muted">(${escHtml(m.student_id)})</small><button class="remove-btn" onclick="removeMember(${m.id},${g.id},${eventId})">×</button></span>`).join('')}
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <select class="form-select form-select-sm" id="memberSelect-${g.id}" style="max-width:220px;">
                            <option value="">— Add approved student —</option>
                        </select>
                        <button class="btn btn-sm btn-primary" onclick="addMemberToGroup(${g.id},${eventId})"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <strong class="d-block mb-2"><i class="fas fa-chalkboard-teacher me-1"></i>Teachers / Coaches</strong>
                    <div id="teachers-${g.id}">
                        ${g.teachers.map(t=>`<span class="teacher-tag">${escHtml(t.teacher_name)}<button class="remove-btn" onclick="removeTeacher(${t.id},${g.id},${eventId})">×</button></span>`).join('')}
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <input type="text" class="form-control form-control-sm" id="teacherInput-${g.id}" placeholder="Teacher / Coach name" style="max-width:200px;">
                        <button class="btn btn-sm btn-success" onclick="addTeacher(${g.id},${eventId})"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
            </div>
        </div>`).join('');

            // Populate member dropdowns
            ajaxPost({
                action: 'get_approved_applicants',
                event_id: eventId
            }).then(data => {
                if (data.status !== 'success') return;
                groups.forEach(g => {
                    const sel = document.getElementById(`memberSelect-${g.id}`);
                    if (!sel) return;
                    const assignedIds = g.members.map(m => m.student_id);
                    data.applicants.forEach(a => {
                        if (!assignedIds.includes(a.student_id)) {
                            const opt = document.createElement('option');
                            opt.value = a.student_id;
                            opt.dataset.name = a.student_name;
                            opt.textContent = `${a.student_name} (${a.student_id})`;
                            sel.appendChild(opt);
                        }
                    });
                });
            });
        }

        function createGroup() {
            const eid = document.getElementById('groupEventSelect').value;
            const name = document.getElementById('newGroupName').value.trim();
            if (!eid) {
                showToast('Please select an event first.', 'warning');
                return;
            }
            if (!name) {
                showToast('Please enter a group name.', 'warning');
                return;
            }
            ajaxPost({
                action: 'create_group',
                event_id: eid,
                group_name: name
            }).then(data => {
                if (data.status === 'success') {
                    showToast(`Group "${name}" created!`, 'success');
                    document.getElementById('newGroupName').value = '';
                    loadGroupsForEvent();
                } else showToast('Error: ' + data.message, 'error');
            });
        }

        function deleteGroup(groupId) {
            if (!confirm('Delete this group and all its members?')) return;
            ajaxPost({
                action: 'delete_group',
                group_id: groupId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Group deleted.', 'success');
                    loadGroupsForEvent();
                } else showToast('Error.', 'error');
            });
        }

        function addMemberToGroup(groupId, eventId) {
            const sel = document.getElementById(`memberSelect-${groupId}`);
            const sid = sel.value;
            const sname = sel.options[sel.selectedIndex]?.dataset?.name || '';
            if (!sid) {
                showToast('Select a student.', 'warning');
                return;
            }
            ajaxPost({
                action: 'add_member',
                group_id: groupId,
                student_id: sid,
                student_name: sname
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Student added!', 'success');
                    loadGroupsForEvent();
                } else showToast('Error adding student.', 'error');
            });
        }

        function removeMember(memberId, groupId, eventId) {
            if (!confirm('Remove this student from the group?')) return;
            ajaxPost({
                action: 'remove_member',
                member_id: memberId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Member removed.', 'success');
                    loadGroupsForEvent();
                } else showToast('Error.', 'error');
            });
        }

        function addTeacher(groupId, eventId) {
            const inp = document.getElementById(`teacherInput-${groupId}`);
            const name = inp.value.trim();
            if (!name) {
                showToast('Enter a teacher/coach name.', 'warning');
                return;
            }
            ajaxPost({
                action: 'add_teacher',
                group_id: groupId,
                teacher_name: name
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Teacher added!', 'success');
                    inp.value = '';
                    loadGroupsForEvent();
                } else showToast('Error.', 'error');
            });
        }

        function removeTeacher(teacherId, groupId, eventId) {
            if (!confirm('Remove this teacher/coach?')) return;
            ajaxPost({
                action: 'remove_teacher',
                teacher_id: teacherId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Teacher removed.', 'success');
                    loadGroupsForEvent();
                } else showToast('Error.', 'error');
            });
        }

        // ============================================================
        //  EVENT MANAGEMENT CRUD FUNCTIONS
        // ============================================================
        function openCreateModal() {
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            document.getElementById('eventForm').reset();
            document.getElementById('eventDays').value = '1';
            // Set default date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('eventDate').value = today;
            if (document.getElementById('eventStartDateDisplay')) {
                document.getElementById('eventStartDateDisplay').value = today;
            }
            document.getElementById('eventDateDisplay').textContent = new Date().toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            loadEventsForDate(today);
            modal.show();
        }

        function editEvent(eventId) {
            ajaxPost({
                action: 'get_event',
                event_id: eventId
            }).then(data => {
                if (data.status !== 'success') {
                    showToast('Could not load event details.', 'error');
                    return;
                }
                const ev = data.event;

                // Core fields
                document.getElementById('editEventId').value        = ev.id;
                document.getElementById('editEventDate').value      = ev.event_date;
                document.getElementById('editEventTitle').value     = ev.title || '';
                document.getElementById('editEventCategory').value  = ev.category || 'Academic';
                document.getElementById('editEventLocation').value  = ev.location || '';
                document.getElementById('editEventLevel').value    = ev.event_level || '';
                document.getElementById('editEventStartTime').value = ev.event_start_time || '';
                document.getElementById('editEventEndTime').value   = ev.event_end_time || '';
                document.getElementById('editEventEndDate').value   = ev.event_end_date || '';
                document.getElementById('editEventDescription').value = ev.description || '';

                // Date display
                if (document.getElementById('editEventDateDisplay'))
                    document.getElementById('editEventDateDisplay').value = ev.event_date;

                // Organizer fields
                document.getElementById('editResponsibleOffice').value = ev.responsible_office || '';
                document.getElementById('editOrgName').value           = ev.organizer_name || '';
                document.getElementById('editOrgPosition').value       = ev.organizer_position || '';

                // Image preview
                const prev = document.getElementById('currentImagePreview');
                prev.innerHTML = ev.image
                    ? `<div class="d-flex align-items-center gap-2 p-2" style="background:var(--forest-softer);border-radius:8px;border:1px solid var(--border);">
                         <img src="../../assets/img/events/${escHtml(ev.image)}" style="max-height:70px;border-radius:6px;" alt="Current image">
                         <small class="text-muted">${escHtml(ev.image)}</small>
                       </div>`
                    : '<small class="text-muted"><i class="fas fa-image me-1"></i>No image uploaded.</small>';

                new bootstrap.Modal(document.getElementById('editEventModal')).show();
            }).catch(() => showToast('Error loading event details.', 'error'));
        }

        function viewEventDetails(eventId) {
            // Open event in new tab or show details modal
            window.open(`../../events.php#event-${eventId}`, '_blank');
        }

        function deleteEvent(eventId, eventTitle) {
            if (!confirm(`Are you sure you want to delete "${eventTitle}"? This action cannot be undone.`)) {
                return;
            }
            
            ajaxPost({
                action: 'delete_event',
                id: eventId
            }).then(data => {
                if (data.status === 'success') {
                    showToast('Event deleted successfully.', 'success');
                    // Remove row from table
                    const row = document.getElementById(`event-row-${eventId}`);
                    if (row) row.remove();
                    // Refresh other components
                    loadUpcomingEvents();
                    loadEventsForMonth(currentYear, currentMonth + 1);
                } else {
                    showToast('Error deleting event: ' + (data.message || 'Unknown error'), 'error');
                }
            }).catch(() => showToast('Error deleting event.', 'error'));
        }

        // Search and filter functionality
        document.getElementById('eventSearchInput')?.addEventListener('input', function() {
            filterEventsTable();
        });

        document.getElementById('eventCategoryFilter')?.addEventListener('change', function() {
            filterEventsTable();
        });

        document.getElementById('eventStatusFilter')?.addEventListener('change', function() {
            filterEventsTable();
        });

        function filterEventsTable() {
            const searchTerm = document.getElementById('eventSearchInput')?.value.toLowerCase() || '';
            const categoryFilter = document.getElementById('eventCategoryFilter')?.value || '';
            const statusFilter = document.getElementById('eventStatusFilter')?.value || '';
            
            const rows = document.querySelectorAll('#eventsManagementBody tr');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            rows.forEach(row => {
                if (row.cells.length === 1) return; // Skip "no events" row
                
                const title = row.cells[1].textContent.toLowerCase();
                const category = row.cells[2].textContent.trim();
                const dateText = row.cells[0].textContent.trim();
                const statusBadge = row.cells[4].querySelector('.status-badge');
                const status = statusBadge ? statusBadge.textContent.trim().toLowerCase() : '';
                
                // Extract date from the date cell
                const dateMatch = dateText.match(/(\w{3})\s(\d{1,2})/);
                let eventDate = null;
                if (dateMatch) {
                    const month = new Date(Date.parse(dateMatch[1] + " 1, 2020")).getMonth();
                    const day = parseInt(dateMatch[2]);
                    const year = parseInt(dateText.match(/\d{4}/)?.[0] || new Date().getFullYear());
                    eventDate = new Date(year, month, day);
                }
                
                let matchesSearch = !searchTerm || title.includes(searchTerm);
                let matchesCategory = !categoryFilter || category === categoryFilter;
                let matchesStatus = true;
                
                if (statusFilter === 'upcoming') {
                    matchesStatus = eventDate && eventDate >= today;
                } else if (statusFilter === 'past') {
                    matchesStatus = eventDate && eventDate < today;
                } else if (statusFilter === 'official') {
                    matchesStatus = status.includes('official');
                }
                
                row.style.display = matchesSearch && matchesCategory && matchesStatus ? '' : 'none';
            });
        }

        // ============================================================
        //  INIT
        // ============================================================

</script>
</body>

</html>