<?php

/**
 * parent_profile.php — Comprehensive Parent Profile Page
 * 
 * Sections:
 * 1. Personal Information
 * 2. Contact Details
 * 3. Student Linkage
 * 4. Communication & Support
 * 5. Financials & Admin
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../session_config.php';
include '../db_connection.php';

// HSTS Header
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Auto-create parent profile table ──────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS parent_profiles (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    parent_id               VARCHAR(50) NOT NULL UNIQUE,
    full_name               VARCHAR(150) DEFAULT '',
    relationship_to_student VARCHAR(100) DEFAULT '',
    profile_picture         VARCHAR(255) DEFAULT '',
    occupation              VARCHAR(150) DEFAULT '',
    workplace               VARCHAR(150) DEFAULT '',
    home_address            TEXT DEFAULT NULL,
    mobile_number           VARCHAR(30) DEFAULT '',
    landline_number         VARCHAR(30) DEFAULT '',
    active_email            VARCHAR(150) DEFAULT '',
    email_verified          TINYINT(1) DEFAULT 0,
    emergency_contact_name  VARCHAR(150) DEFAULT '',
    emergency_contact_phone VARCHAR(30) DEFAULT '',
    linked_student_ids      TEXT DEFAULT NULL,
    total_outstanding_balance DECIMAL(10,2) DEFAULT 0.00,
    payment_history         TEXT DEFAULT NULL,
    enrollment_documents_status TEXT DEFAULT NULL,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auth guard
// Student functionality has been removed
header('Location: ../index.php');
exit;

$parent_id = $_SESSION['student_id'];
$parent_name = $_SESSION['student_name'] ?? 'Parent';

// Fetch parent profile
$parent_profile = [];
try {
    $stmt = $conn->prepare("SELECT * FROM parent_profiles WHERE parent_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $parent_id);
        $stmt->execute();
        $parent_profile = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Parent profile fetch error: " . $e->getMessage());
}

// Handle profile save (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_parent_profile') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Sanitize inputs
    $full_name              = htmlspecialchars(trim($_POST['full_name'] ?? ''), ENT_QUOTES);
    $relationship           = htmlspecialchars(trim($_POST['relationship'] ?? ''), ENT_QUOTES);
    $occupation             = htmlspecialchars(trim($_POST['occupation'] ?? ''), ENT_QUOTES);
    $workplace              = htmlspecialchars(trim($_POST['workplace'] ?? ''), ENT_QUOTES);
    $home_address           = htmlspecialchars(trim($_POST['home_address'] ?? ''), ENT_QUOTES);
    $mobile_number          = htmlspecialchars(trim($_POST['mobile_number'] ?? ''), ENT_QUOTES);
    $landline_number        = htmlspecialchars(trim($_POST['landline_number'] ?? ''), ENT_QUOTES);
    $active_email           = htmlspecialchars(trim($_POST['active_email'] ?? ''), ENT_QUOTES);
    $emergency_contact_name = htmlspecialchars(trim($_POST['emergency_contact_name'] ?? ''), ENT_QUOTES);
    $emergency_contact_phone = htmlspecialchars(trim($_POST['emergency_contact_phone'] ?? ''), ENT_QUOTES);

    // Handle profile picture upload
    $profile_picture = $parent_profile['profile_picture'] ?? '';
    if (!empty($_FILES['profile_picture']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (in_array($_FILES['profile_picture']['type'], $allowed) && $_FILES['profile_picture']['size'] <= 5242880) {
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $dest = '../assets/img/parents/parent_' . $parent_id . '_' . time() . '.' . $ext;
            @mkdir('../assets/img/parents/', 0775, true);
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $dest)) {
                $profile_picture = $dest;
            }
        }
    }

    // Upsert into parent_profiles
    $stmt = $conn->prepare("INSERT INTO parent_profiles
        (parent_id, full_name, relationship_to_student, occupation, workplace, home_address,
         mobile_number, landline_number, active_email, emergency_contact_name, emergency_contact_phone, profile_picture)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
        full_name=VALUES(full_name), relationship_to_student=VALUES(relationship_to_student),
        occupation=VALUES(occupation), workplace=VALUES(workplace), home_address=VALUES(home_address),
        mobile_number=VALUES(mobile_number), landline_number=VALUES(landline_number),
        active_email=VALUES(active_email), emergency_contact_name=VALUES(emergency_contact_name),
        emergency_contact_phone=VALUES(emergency_contact_phone), profile_picture=VALUES(profile_picture)");

    if ($stmt) {
        $stmt->bind_param('ssssssssssss', $parent_id, $full_name, $relationship, $occupation, $workplace,
                         $home_address, $mobile_number, $landline_number, $active_email, 
                         $emergency_contact_name, $emergency_contact_phone, $profile_picture);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully', 'profile_picture' => $profile_picture]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
    }
    exit;
}

// Prepare template data
$template_data = [
    'full_name' => $parent_profile['full_name'] ?? '',
    'relationship' => $parent_profile['relationship_to_student'] ?? '',
    'occupation' => $parent_profile['occupation'] ?? '',
    'workplace' => $parent_profile['workplace'] ?? '',
    'home_address' => $parent_profile['home_address'] ?? '',
    'mobile_number' => $parent_profile['mobile_number'] ?? '',
    'landline_number' => $parent_profile['landline_number'] ?? '',
    'active_email' => $parent_profile['active_email'] ?? '',
    'emergency_contact_name' => $parent_profile['emergency_contact_name'] ?? '',
    'emergency_contact_phone' => $parent_profile['emergency_contact_phone'] ?? '',
    'profile_picture' => $parent_profile['profile_picture'] ?? 'assets/img/person/unknown.jpg',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Profile — BUNHS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8f5f0 0%, #f0f7f4 100%);
            color: #1a3a2a;
        }

        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 32px;
            margin-bottom: 8px;
            color: #1a3a2a;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: rgba(82, 183, 136, 0.1);
            border: 1px solid rgba(82, 183, 136, 0.3);
            border-radius: 8px;
            text-decoration: none;
            color: #2d6a4f;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
            transition: all 0.2s;
        }

        .back-button:hover {
            background: rgba(82, 183, 136, 0.2);
        }

        .profile-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 2px 12px rgba(26, 58, 42, 0.08);
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a3a2a;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid rgba(82, 183, 136, 0.2);
        }

        .section-title i {
            color: #2d6a4f;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #1a3a2a;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
            border: 2px solid #dde8e2;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: #1e2d24;
            background: #f8f5f0;
            transition: all 0.2s;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #52b788;
            box-shadow: 0 0 0 3.5px rgba(82, 183, 136, .18);
            background: white;
        }

        .form-group textarea {
            min-height: 100px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .profile-picture-section {
            display: flex;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 2px solid rgba(82, 183, 136, 0.2);
        }

        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            background: #f8f5f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid #dde8e2;
            flex-shrink: 0;
        }

        .profile-picture-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture-upload {
            flex: 1;
        }

        .profile-picture-upload label {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #1a3a2a;
            text-transform: uppercase;
        }

        .file-input-wrapper {
            position: relative;
        }

        .file-input-wrapper input[type="file"] {
            display: none;
        }

        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #3a8c6a, #1a3a2a);
            color: white;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .file-input-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 58, 42, 0.3);
        }

        .section-grid {
            display: grid;
            gap: 24px;
        }

        .sub-section {
            padding: 20px;
            background: #f8f5f0;
            border-radius: 12px;
            border-left: 4px solid #52b788;
        }

        .sub-section h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1a3a2a;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sub-section p {
            font-size: 13px;
            color: #666;
            margin: 4px 0;
        }

        .student-link-card {
            background: white;
            border: 2px solid #dde8e2;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-link-info {
            flex: 1;
        }

        .student-link-info h5 {
            font-size: 14px;
            font-weight: 700;
            color: #1a3a2a;
        }

        .student-link-info p {
            font-size: 12px;
            color: #999;
            margin: 4px 0 0 0;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3a8c6a, #1a3a2a);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(26, 58, 42, 0.3);
        }

        .btn-secondary {
            background: rgba(82, 183, 136, 0.1);
            color: #2d6a4f;
            border: 1px solid rgba(82, 183, 136, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(82, 183, 136, 0.2);
        }

        .success-message {
            padding: 12px 16px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }

        .error-message {
            padding: 12px 16px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }

        @media (max-width: 768px) {
            .profile-container {
                padding: 24px 16px;
            }

            .profile-card {
                padding: 24px 16px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .profile-picture-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<div class="profile-container">

    <a href="Dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="header">
        <h1>👨‍👩‍👧 Parent Profile</h1>
        <p style="color: #666; margin-top: 8px;">Manage your account and student connections</p>
    </div>

    <!-- Success/Error Messages -->
    <div class="success-message" id="successMsg">
        <i class="fas fa-check-circle"></i> <span id="successText"></span>
    </div>
    <div class="error-message" id="errorMsg">
        <i class="fas fa-exclamation-circle"></i> <span id="errorText"></span>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         SECTION 1: PERSONAL INFORMATION
         ══════════════════════════════════════════════════════════════ -->
    <div class="profile-card">
        <h2 class="section-title">
            <i class="fas fa-user"></i> Personal Information
        </h2>

        <div class="profile-picture-section">
            <div class="profile-picture-preview">
                <img id="picturePreview" src="<?php echo htmlspecialchars($template_data['profile_picture']); ?>" alt="Profile Picture">
            </div>
            <div class="profile-picture-upload">
                <label>Profile Picture</label>
                <div class="file-input-wrapper">
                    <input type="file" id="profilePictureInput" accept="image/*">
                    <label for="profilePictureInput" class="file-input-label">
                        <i class="fas fa-upload"></i> Upload Photo
                    </label>
                </div>
                <p style="font-size: 11px; color: #999; margin-top: 8px;">Max 5MB. JPG, PNG, WebP, or GIF</p>
            </div>
        </div>

        <form id="personalInfoForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="save_parent_profile">

            <div class="form-grid">
                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="full_name" required 
                           value="<?php echo htmlspecialchars($template_data['full_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="relationship">Relationship to Student *</label>
                    <select id="relationship" name="relationship" required>
                        <option value="">Select relationship…</option>
                        <option value="Father" <?php echo $template_data['relationship'] === 'Father' ? 'selected' : ''; ?>>Father</option>
                        <option value="Mother" <?php echo $template_data['relationship'] === 'Mother' ? 'selected' : ''; ?>>Mother</option>
                        <option value="Guardian" <?php echo $template_data['relationship'] === 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                        <option value="Grandparent" <?php echo $template_data['relationship'] === 'Grandparent' ? 'selected' : ''; ?>>Grandparent</option>
                        <option value="Other" <?php echo $template_data['relationship'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="occupation">Occupation</label>
                    <input type="text" id="occupation" name="occupation" 
                           placeholder="e.g., Software Engineer, Teacher"
                           value="<?php echo htmlspecialchars($template_data['occupation']); ?>">
                </div>

                <div class="form-group">
                    <label for="workplace">Workplace / Company</label>
                    <input type="text" id="workplace" name="workplace" 
                           placeholder="e.g., ABC Corporation"
                           value="<?php echo htmlspecialchars($template_data['workplace']); ?>">
                </div>

                <div class="form-group full-width">
                    <label for="homeAddress">Home Address</label>
                    <textarea id="homeAddress" name="home_address" 
                              placeholder="Complete home address…"><?php echo htmlspecialchars($template_data['home_address']); ?></textarea>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Personal Info
                </button>
            </div>
        </form>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         SECTION 2: CONTACT DETAILS
         ══════════════════════════════════════════════════════════════ -->
    <div class="profile-card">
        <h2 class="section-title">
            <i class="fas fa-phone"></i> Contact Details
        </h2>

        <form id="contactForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="save_parent_profile">

            <div class="form-grid">
                <div class="form-group">
                    <label for="mobileNumber">Mobile Number</label>
                    <input type="tel" id="mobileNumber" name="mobile_number" 
                           placeholder="09XXXXXXXXX"
                           value="<?php echo htmlspecialchars($template_data['mobile_number']); ?>">
                </div>

                <div class="form-group">
                    <label for="landlineNumber">Landline Number</label>
                    <input type="tel" id="landlineNumber" name="landline_number" 
                           placeholder="(XXX) XXX-XXXX"
                           value="<?php echo htmlspecialchars($template_data['landline_number']); ?>">
                </div>

                <div class="form-group full-width">
                    <label for="activeEmail">Active Email Address *</label>
                    <input type="email" id="activeEmail" name="active_email" required
                           placeholder="your@email.com"
                           value="<?php echo htmlspecialchars($template_data['active_email']); ?>">
                </div>

                <div class="form-group full-width">
                    <label for="emergencyContactName">Emergency Contact Name</label>
                    <input type="text" id="emergencyContactName" name="emergency_contact_name" 
                           placeholder="Full name"
                           value="<?php echo htmlspecialchars($template_data['emergency_contact_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="emergencyContactPhone">Emergency Contact Number</label>
                    <input type="tel" id="emergencyContactPhone" name="emergency_contact_phone" 
                           placeholder="09XXXXXXXXX"
                           value="<?php echo htmlspecialchars($template_data['emergency_contact_phone']); ?>">
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Contact Info
                </button>
            </div>
        </form>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         SECTION 3: STUDENT LINKAGE
         ══════════════════════════════════════════════════════════════ -->
    <div class="profile-card">
        <h2 class="section-title">
            <i class="fas fa-link"></i> Linked Student Profile
        </h2>

        <p style="color: #666; margin-bottom: 16px;">
            Connect to your child's student account to monitor academic progress and receive updates.
        </p>

        <div class="section-grid">
            <div class="sub-section">
                <h4><i class="fas fa-graduation-cap"></i> Student Information</h4>
                <p><strong>Feature:</strong> Link student accounts</p>
                <p><strong>Status:</strong> Coming soon - Add student via ID code</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-plus-circle"></i> Add Student
                </button>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-chart-line"></i> Academic Progress</h4>
                <p><strong>Grade Level:</strong> View current grade level</p>
                <p><strong>GPA:</strong> Monitor current GPA and progress</p>
                <p><strong>Subjects:</strong> Track all enrolled courses</p>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-calendar-check"></i> Attendance Records</h4>
                <p><strong>Present:</strong> Monitor attendance percentage</p>
                <p><strong>Absent:</strong> View absence details</p>
                <p><strong>Tardiness:</strong> Check late arrivals</p>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-file-pdf"></i> Report Cards & Behavioral Records</h4>
                <p><strong>Report Card:</strong> Download academic report cards</p>
                <p><strong>Behavioral:</strong> Review conduct records</p>
                <p><strong>Merit:</strong> View achievements and awards</p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         SECTION 4: COMMUNICATION & SUPPORT
         ══════════════════════════════════════════════════════════════ -->
    <div class="profile-card">
        <h2 class="section-title">
            <i class="fas fa-comments"></i> Communication & Support
        </h2>

        <p style="color: #666; margin-bottom: 24px;">
            Stay connected with teachers and school administration through various communication channels.
        </p>

        <div class="section-grid">
            <div class="sub-section">
                <h4><i class="fas fa-envelope"></i> Direct Messaging (Teacher-Parent Chat)</h4>
                <p>Send and receive messages directly from teachers about your child's progress.</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-comments"></i> Go to Messages
                </button>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-bullhorn"></i> School Announcements & Newsfeed</h4>
                <p>Receive official announcements, school news, and important updates.</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-newspaper"></i> View Announcements
                </button>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-calendar-alt"></i> Event Calendar</h4>
                <p>View school events, parent-teacher meetings, holidays, and important dates.</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-calendar"></i> View Calendar
                </button>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-file-signature"></i> Digital Permission Slips</h4>
                <p>Sign and submit permission slips for school trips and activities online.</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-pen-fancy"></i> View Slips
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════
         SECTION 5: FINANCIALS & ADMIN
         ══════════════════════════════════════════════════════════════ -->
    <div class="profile-card">
        <h2 class="section-title">
            <i class="fas fa-credit-card"></i> Financials & Administration
        </h2>

        <p style="color: #666; margin-bottom: 24px;">
            Manage tuition payments, view billing history, and track document requirements.
        </p>

        <div class="section-grid">
            <div class="sub-section">
                <h4><i class="fas fa-wallet"></i> Tuition Status</h4>
                <p><strong>Outstanding Balance:</strong> PHP 15,000.00</p>
                <p><strong>Due Date:</strong> End of Month</p>
                <p><strong>Status:</strong> Overdue</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-eye"></i> View Details
                </button>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-receipt"></i> Payment History & Receipts</h4>
                <p>Track all tuition and miscellaneous fee payments made to the school.</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-history"></i> View History
                </button>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-credit-card"></i> Online Payment Portal</h4>
                <p>Pay tuition and fees securely online using various payment methods.</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-credit-card"></i> Pay Now
                </button>
            </div>

            <div class="sub-section">
                <h4><i class="fas fa-folder-open"></i> Enrollment Documents & Requirements</h4>
                <p><strong>Status:</strong> All documents complete</p>
                <p>View required documents and check submission status.</p>
                <button type="button" class="btn btn-secondary" style="margin-top: 12px;">
                    <i class="fas fa-file-upload"></i> View Documents
                </button>
            </div>
        </div>
    </div>

</div>

<script>
    // Profile picture preview
    document.getElementById('profilePictureInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('picturePreview').src = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // Form submission
    ['personalInfoForm', 'contactForm'].forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                
                // Add profile picture if selected
                const fileInput = document.getElementById('profilePictureInput');
                if (fileInput && fileInput.files.length > 0) {
                    formData.append('profile_picture', fileInput.files[0]);
                }

                fetch('parent_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('successText').textContent = data.message;
                        document.getElementById('successMsg').style.display = 'flex';
                        setTimeout(() => {
                            document.getElementById('successMsg').style.display = 'none';
                        }, 4000);
                    } else {
                        document.getElementById('errorText').textContent = data.message;
                        document.getElementById('errorMsg').style.display = 'flex';
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    document.getElementById('errorText').textContent = 'An error occurred. Please try again.';
                    document.getElementById('errorMsg').style.display = 'flex';
                });
            });
        }
    });
</script>

</body>
</html>
