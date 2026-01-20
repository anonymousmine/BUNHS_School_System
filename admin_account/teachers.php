<?php
session_start();
include '../db_connection.php';

// Handle POST requests for add, edit, delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add') {
            // Add teacher
            $name = mysqli_real_escape_string($conn, $_POST['teacher_name']);
            $id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
            $email = mysqli_real_escape_string($conn, $_POST['teacher_email']);
            $contact = mysqli_real_escape_string($conn, $_POST['teacher_contact']);
            $grades = isset($_POST['teacher_grades']) ? implode(', ', $_POST['teacher_grades']) : '';
            $subjects = isset($_POST['teacher_subjects']) ? implode(', ', $_POST['teacher_subjects']) : '';
            $qualification = mysqli_real_escape_string($conn, $_POST['teacher_qualification']);

            // Handle image upload
            $image_path = '';
            if (isset($_FILES['teacher_image']) && $_FILES['teacher_image']['error'] == 0) {
                $target_dir = "../assets/img/person/";
                $file_name = basename($_FILES["teacher_image"]["name"]);
                $target_file = $target_dir . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                // Check if image file is a actual image or fake image
                $check = getimagesize($_FILES["teacher_image"]["tmp_name"]);
                if ($check !== false) {
                    if (move_uploaded_file($_FILES["teacher_image"]["tmp_name"], $target_file)) {
                        $image_path = $target_file;
                    }
                }
            }

            $sql = "INSERT INTO teachers (teacher_name, teacher_id, teacher_email, teacher_contact, teacher_grades, teacher_subjects, teacher_qualification, teacher_image) VALUES ('$name', '$id', '$email', '$contact', '$grades', '$subjects', '$qualification', '$image_path')";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Teacher added successfully.";
            } else {
                $_SESSION['error'] = "Failed to add teacher: " . mysqli_error($conn);
            }
        } elseif ($action == 'edit') {
            // Edit teacher
            $id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
            $name = mysqli_real_escape_string($conn, $_POST['teacher_name']);
            $email = mysqli_real_escape_string($conn, $_POST['teacher_email']);
            $contact = mysqli_real_escape_string($conn, $_POST['teacher_contact']);
            $grades = isset($_POST['teacher_grades']) ? implode(', ', $_POST['teacher_grades']) : '';
            $subjects = isset($_POST['teacher_subjects']) ? implode(', ', $_POST['teacher_subjects']) : '';
            $qualification = mysqli_real_escape_string($conn, $_POST['teacher_qualification']);

            // Handle image upload
            $image_path = '';
            if (isset($_FILES['teacher_image']) && $_FILES['teacher_image']['error'] == 0) {
                $target_dir = "../assets/img/person/";
                $file_name = basename($_FILES["teacher_image"]["name"]);
                $target_file = $target_dir . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                $check = getimagesize($_FILES["teacher_image"]["tmp_name"]);
                if ($check !== false) {
                    if (move_uploaded_file($_FILES["teacher_image"]["tmp_name"], $target_file)) {
                        $image_path = $target_file;
                    }
                }
            }

            $sql = "UPDATE teachers SET teacher_name='$name', teacher_email='$email', teacher_contact='$contact', teacher_grades='$grades', teacher_subjects='$subjects', teacher_qualification='$qualification'";
            if ($image_path) {
                $sql .= ", teacher_image='$image_path'";
            }
            $sql .= " WHERE teacher_id='$id'";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Teacher updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update teacher: " . mysqli_error($conn);
            }
        } elseif ($action == 'delete') {
            // Delete teacher
            $id = mysqli_real_escape_string($conn, $_POST['teacher_id']);
            $sql = "DELETE FROM teachers WHERE teacher_id='$id'";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Teacher deleted successfully.";
            } else {
                $_SESSION['error'] = "Failed to delete teacher: " . mysqli_error($conn);
            }
        }

        // Redirect back to the page
        header("Location: teachers.php");
        exit();
    }
}

// Fetch all teachers
$sql = "SELECT * FROM teachers ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
$teachers = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Information</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .page-content {
            padding: 20px;
            width: 100%;
            margin: 0;
        }
        .table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin: 30px auto;
            max-width: 1200px;
        }
        .teacher-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .teacher-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
        }
        .teacher-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        .teacher-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .teacher-table .actions {
            display: flex;
            gap: 8px;
        }
        .teacher-table .actions a {
            color: #6c757d;
            text-decoration: none;
            padding: 6px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .teacher-table .actions a:hover {
            background-color: #e9ecef;
        }
        .teacher-table .actions .edit:hover {
            color: #007bff;
        }
        .teacher-table .actions .delete:hover {
            color: #dc3545;
        }
        .qualification-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .qualification-badge.lac {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .qualification-badge.post-graduate {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        .qualification-badge.k12 {
            background-color: #e8f5e8;
            color: #388e3c;
        }
        .qualification-badge.content {
            background-color: #fff3e0;
            color: #f57c00;
        }
        .qualification-badge.others {
            background-color: #fafafa;
            color: #616161;
        }
        .teacher-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
            margin-right: 12px;
        }
        .teacher-info {
            display: flex;
            align-items: center;
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .table-header h3 {
            margin: 0;
            color: #495057;
            font-weight: 600;
        }
        .add-teacher-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }
        .add-teacher-btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-out;
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 500px;
            animation: slideIn 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            color: #495057;
            font-weight: 600;
        }
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.2s;
        }
        .close-modal:hover {
            color: #dc3545;
        }
        .teacher-form {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        .cancel-btn:hover {
            background: #5a6268;
        }
        .submit-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        .submit-btn:hover {
            background: #0056b3;
        }
        .edit-modal-layout {
            display: flex;
            max-width: 800px;
            height: auto;
        }
        .profile-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 20px;
            background: #f8f9fa;
            border-radius: 12px 0 0 12px;
        }
        .profile-image-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
        }
        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            cursor: pointer;
        }
        .profile-image-container:hover .image-overlay {
            opacity: 1;
        }
        .image-overlay i {
            color: white;
            font-size: 24px;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            transition: width 0.3s;
        }
        .details-section {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
        }
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .teacher-name {
            font-size: 24px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 5px;
        }
        .teacher-location {
            font-size: 14px;
            color: #6c757d;
            font-weight: 400;
        }
        .edit-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .edit-btn:hover {
            background: #0056b3;
        }
        .details-grid {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .detail-row {
            display: flex;
            gap: 15px;
        }
        .detail-row .form-group {
            flex: 1;
        }
        .detail-row .form-group label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .detail-row .form-group input,
        .detail-row .form-group select {
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            border: none;
            border-bottom: 2px solid #e9ecef;
            border-radius: 0;
            padding: 8px 0;
            background: transparent;
        }
        .detail-row .form-group input:focus,
        .detail-row .form-group select:focus {
            border-bottom-color: #007bff;
            box-shadow: none;
        }
        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: #6c757d;
            cursor: pointer;
            z-index: 1001;
        }
        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
            .teacher-form {
                padding: 20px;
            }
            .modal-header {
                padding: 15px 20px;
            }
            .edit-modal-layout {
                flex-direction: column;
                max-width: 500px;
            }
            .profile-section {
                border-radius: 12px 12px 0 0;
            }
            .details-section {
                padding: 20px;
            }
            .detail-row {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <div id="navigation-container"></div>

    <!-- main content -->
    <main class="main page-content">

    <!-- Page Title -->
    <div class="page-title">
      <div class="heading">
        <div class="container">
          <div class="row d-flex justify-content-center text-center">
            <div class="col-lg-8 text-center">
              <h1 class="heading-title">Teachers</h1>
              <p class="mb-0">
                View and manage teacher information in the system.
              </p>
            </div>
          </div>
        </div>
      </div>
      <nav class="breadcrumbs">
        <div class="container-fluid">
          <ol>
            <li><a href="../admin_dashboard.php" >Home</a></li>
            <li class="current">Teachers</li>
          </ol>
        </div>
      </nav>
    </div><!-- End Page Title -->

    <!-- Teacher Information Table -->
    <div class="table-card">
        <div class="table-header">
            <h3>Teacher Information</h3>
            <button id="add-teacher-btn" class="add-teacher-btn" title="Add Teacher">
                <i class="fa-solid fa-circle-plus"></i>
            </button>
        </div>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="margin-bottom: 20px; padding: 15px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 6px;">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px; padding: 15px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 6px;">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        <table class="teacher-table">
            <thead>
                <tr>
                    <th>Teacher Name</th>
                    <th>ID Number</th>
                    <th>Email</th>
                    <th>Contact Number</th>
                    <th>Grades</th>
                    <th>Subjects</th>
                    <th>Qualifications</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($teachers) > 0): ?>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr data-id="<?php echo htmlspecialchars($teacher['teacher_id']); ?>">
                            <td>
                                <div class="teacher-info">
                                    <img src="<?php echo htmlspecialchars($teacher['teacher_image'] ?: '../assets/img/person/default.png'); ?>" alt="<?php echo htmlspecialchars($teacher['teacher_name']); ?>" class="teacher-avatar">
                                    <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['teacher_email']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['teacher_contact']); ?></td>
                            <td><?php echo htmlspecialchars($teacher['teacher_grades'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($teacher['teacher_subjects'] ?? 'N/A'); ?></td>
                            <td><span class="qualification-badge <?php echo htmlspecialchars($teacher['teacher_qualification']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $teacher['teacher_qualification']))); ?></span></td>
                            <td class="actions">
                                <a href="#" class="edit" title="Edit" data-id="<?php echo htmlspecialchars($teacher['teacher_id']); ?>"><i class="fas fa-edit"></i></a>
                                <a href="#" class="delete" title="Delete" data-id="<?php echo htmlspecialchars($teacher['teacher_id']); ?>"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">No teachers found. Add a teacher to get started.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Teacher Modal -->
    <div id="add-teacher-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Teacher</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form id="add-teacher-form" class="teacher-form" action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="teacher-name">Teacher Name</label>
                    <input type="text" id="teacher-name" name="teacher_name" required>
                </div>
                <div class="form-group">
                    <label for="teacher-id">ID Number</label>
                    <input type="text" id="teacher-id" name="teacher_id" required>
                </div>
                <div class="form-group">
                    <label for="teacher-email">Email</label>
                    <input type="email" id="teacher-email" name="teacher_email" required>
                </div>
                <div class="form-group">
                    <label for="teacher-contact">Contact Number</label>
                    <input type="tel" id="teacher-contact" name="teacher_contact" required>
                </div>
                <div class="form-group">
                    <label>Grades Teaching</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <label><input type="checkbox" name="teacher_grades[]" value="7"> Grade 7</label>
                        <label><input type="checkbox" name="teacher_grades[]" value="8"> Grade 8</label>
                        <label><input type="checkbox" name="teacher_grades[]" value="9"> Grade 9</label>
                        <label><input type="checkbox" name="teacher_grades[]" value="10"> Grade 10</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Subjects Teaching</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <label><input type="checkbox" name="teacher_subjects[]" value="Math"> Math</label>
                        <label><input type="checkbox" name="teacher_subjects[]" value="Science"> Science</label>
                        <label><input type="checkbox" name="teacher_subjects[]" value="MAPEH"> MAPEH</label>
                        <label><input type="checkbox" name="teacher_subjects[]" value="Filipino"> Filipino</label>
                        <label><input type="checkbox" name="teacher_subjects[]" value="English"> English</label>
                        <label><input type="checkbox" name="teacher_subjects[]" value="Araling Panlipunan"> Araling Panlipunan</label>
                        <label><input type="checkbox" name="teacher_subjects[]" value="ESP"> ESP</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="teacher-qualification">Qualifications</label>
                    <select id="teacher-qualification" name="teacher_qualification" required>
                        <option value="">Select Qualification</option>
                        <option value="post-graduate">Post-graduate</option>
                        <option value="lac">LAC</option>
                        <option value="k12">K to 12</option>
                        <option value="content">Content</option>
                        <option value="others">Others</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="teacher-image">Teacher Image</label>
                    <input type="file" id="teacher-image" name="teacher_image" accept="image/*">
                </div>
                <div class="form-actions">
                    <button type="button" class="cancel-btn">Cancel</button>
                    <button type="submit" class="submit-btn">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div id="edit-teacher-modal" class="modal">
        <div class="modal-content edit-modal-layout">
            <span class="close-modal">&times;</span>
            <div class="profile-section">
                <div class="profile-image-container">
                    <img id="edit-teacher-profile-img" src="../assets/img/person/default.png" alt="Teacher Profile" class="profile-image">
                    <div class="image-overlay">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 75%;"></div>
                </div>
            </div>
            <div class="details-section">
                <div class="details-header">
                    <div class="teacher-name" id="edit-teacher-display-name">Teacher Name</div>
                    <div class="teacher-location" id="edit-teacher-location">Location</div>
                    <button class="edit-btn">Edit</button>
                </div>
                <form id="edit-teacher-form" class="teacher-form" action="" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit-teacher-name" name="teacher_name">
                    <input type="hidden" id="edit-teacher-id" name="teacher_id">
                    <div class="details-grid">
                        <div class="detail-row">
                            <div class="form-group">
                                <label for="edit-teacher-email">Email</label>
                                <input type="email" id="edit-teacher-email" name="teacher_email" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-teacher-contact">Phone</label>
                                <input type="tel" id="edit-teacher-contact" name="teacher_contact" required>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="form-group">
                                <label for="edit-teacher-advisory">Advisory</label>
                                <input type="checkbox" id="edit-teacher-advisory" name="teacher_advisory" value="1">
                            </div>
                            <div class="form-group">
                                <label for="edit-teacher-qualification">Qualifications</label>
                                <select id="edit-teacher-qualification" name="teacher_qualification" required>
                                    <option value="">Select Qualification</option>
                                    <option value="post-graduate">Post-graduate</option>
                                    <option value="lac">LAC</option>
                                    <option value="k12">K to 12</option>
                                    <option value="content">Content</option>
                                    <option value="others">Others</option>
                                </select>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="form-group">
                                <label>Grades Teaching</label>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <label><input type="checkbox" name="teacher_grades[]" value="7" id="edit-grade-7"> Grade 7</label>
                                    <label><input type="checkbox" name="teacher_grades[]" value="8" id="edit-grade-8"> Grade 8</label>
                                    <label><input type="checkbox" name="teacher_grades[]" value="9" id="edit-grade-9"> Grade 9</label>
                                    <label><input type="checkbox" name="teacher_grades[]" value="10" id="edit-grade-10"> Grade 10</label>
                                </div>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="form-group">
                                <label>Subjects Teaching</label>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <label><input type="checkbox" name="teacher_subjects[]" value="Math" id="edit-subject-math"> Math</label>
                                    <label><input type="checkbox" name="teacher_subjects[]" value="Science" id="edit-subject-science"> Science</label>
                                    <label><input type="checkbox" name="teacher_subjects[]" value="MAPEH" id="edit-subject-mapeh"> MAPEH</label>
                                    <label><input type="checkbox" name="teacher_subjects[]" value="Filipino" id="edit-subject-filipino"> Filipino</label>
                                    <label><input type="checkbox" name="teacher_subjects[]" value="English" id="edit-subject-english"> English</label>
                                    <label><input type="checkbox" name="teacher_subjects[]" value="Araling Panlipunan" id="edit-subject-ap"> Araling Panlipunan</label>
                                    <label><input type="checkbox" name="teacher_subjects[]" value="ESP" id="edit-subject-esp"> ESP</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="file" id="edit-teacher-image" name="teacher_image" accept="image/*" style="display: none;">
                    <div class="form-actions">
                        <button type="button" class="cancel-btn">Cancel</button>
                        <button type="submit" class="submit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Delete Form -->
    <form id="delete-teacher-form" action="" method="post" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="teacher_id" id="delete-teacher-id">
    </form>

    <script>
        // Load navigation from admin_nav.php
        fetch('admin_nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('navigation-container').innerHTML = data;

                // Move page content to .main div
                const mainDiv = document.querySelector('.main');
                const pageContent = document.querySelector('.page-content');
                if (mainDiv && pageContent) {
                    mainDiv.appendChild(pageContent);
                }

                // Initialize dropdown functionality after navigation loads
                initializeDropdowns();
            })
            .catch(error => console.error('Error loading navigation:', error));

        // Dropdown initialization function
        function initializeDropdowns() {
            // Fix dropdown item paths based on current location
            const currentPath = window.location.pathname;
            const isInSubfolder = currentPath.includes('/announcements/');
            const pathPrefix = isInSubfolder ? '../announcements/' : 'announcements/';

            document.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
                const page = item.getAttribute('data-page');
                item.href = pathPrefix + page;
            });

            // Dropdown toggle functionality
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = this.closest('.dropdown');
                    const isActive = dropdown.classList.contains('active');

                    // Close all dropdowns
                    document.querySelectorAll('.dropdown').forEach(d => {
                        d.classList.remove('active');
                    });

                    // Toggle the clicked dropdown
                    if (!isActive) {
                        dropdown.classList.add('active');
                    }
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                    });
                }
            });
        }

        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const addTeacherBtn = document.getElementById('add-teacher-btn');
            const addModal = document.getElementById('add-teacher-modal');
            const editModal = document.getElementById('edit-teacher-modal');
            const closeButtons = document.querySelectorAll('.close-modal');
            const cancelButtons = document.querySelectorAll('.cancel-btn');

            // Open Add Teacher Modal
            addTeacherBtn.addEventListener('click', function() {
                addModal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            });

            // Handle Delete Teacher
            document.querySelectorAll('.delete').forEach(deleteBtn => {
                deleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const teacherId = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to delete this teacher?')) {
                        document.getElementById('delete-teacher-id').value = teacherId;
                        document.getElementById('delete-teacher-form').submit();
                    }
                });
            });

            // Open Edit Teacher Modal
            document.querySelectorAll('.edit').forEach(editBtn => {
                editBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const row = this.closest('tr');
                    const cells = row.querySelectorAll('td');

                    // Extract teacher data from row
                    const name = cells[0].textContent.trim().split('\n')[1]?.trim() || cells[0].textContent.trim();
                    const id = cells[1].textContent.trim();
                    const email = cells[2].textContent.trim();
                    const contact = cells[3].textContent.trim();
                    const grades = cells[4].textContent.trim();
                    const subjects = cells[5].textContent.trim();
                    const qualification = cells[6].querySelector('.qualification-badge').classList[1];
                    const imageSrc = cells[0].querySelector('img').src;

                    // Populate display elements
                    document.getElementById('edit-teacher-display-name').textContent = name;
                    document.getElementById('edit-teacher-location').textContent = qualification.replace('-', ' ').toUpperCase();
                    document.getElementById('edit-teacher-profile-img').src = imageSrc;

                    // Populate hidden form fields
                    document.getElementById('edit-teacher-name').value = name;
                    document.getElementById('edit-teacher-id').value = id;
                    document.getElementById('edit-teacher-email').value = email;
                    document.getElementById('edit-teacher-contact').value = contact;
                    document.getElementById('edit-teacher-advisory').checked = false; // Default to false since not in table
                    document.getElementById('edit-teacher-qualification').value = qualification;

                    // Populate grades checkboxes
                    const gradeCheckboxes = document.querySelectorAll('input[name="teacher_grades[]"]');
                    gradeCheckboxes.forEach(cb => cb.checked = false);
                    if (grades !== 'N/A') {
                        const gradeArray = grades.split(', ');
                        gradeArray.forEach(grade => {
                            const checkbox = document.getElementById('edit-grade-' + grade);
                            if (checkbox) checkbox.checked = true;
                        });
                    }

                    // Populate subjects checkboxes
                    const subjectCheckboxes = document.querySelectorAll('input[name="teacher_subjects[]"]');
                    subjectCheckboxes.forEach(cb => cb.checked = false);
                    if (subjects !== 'N/A') {
                        const subjectArray = subjects.split(', ');
                        subjectArray.forEach(subject => {
                            const subjectId = 'edit-subject-' + subject.toLowerCase().replace(/\s+/g, '-');
                            const checkbox = document.getElementById(subjectId);
                            if (checkbox) checkbox.checked = true;
                        });
                    }

                    // Handle image overlay click
                    const imageOverlay = document.querySelector('.image-overlay');
                    const imageInput = document.getElementById('edit-teacher-image');
                    imageOverlay.addEventListener('click', function() {
                        imageInput.click();
                    });

                    // Handle image preview
                    imageInput.addEventListener('change', function() {
                        const file = this.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                document.getElementById('edit-teacher-profile-img').src = e.target.result;
                            };
                            reader.readAsDataURL(file);
                        }
                    });

                    editModal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                });
            });

            // Close modals
            function closeModal(modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            // Close button click
            closeButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    closeModal(modal);
                });
            });

            // Cancel button click
            cancelButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    closeModal(modal);
                });
            });

            // Click outside modal to close
            window.addEventListener('click', function(e) {
                if (e.target === addModal || e.target === editModal) {
                    closeModal(e.target);
                }
            });

            // ESC key to close
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (addModal.style.display === 'block') {
                        closeModal(addModal);
                    } else if (editModal.style.display === 'block') {
                        closeModal(editModal);
                    }
                }
            });

            // Form submissions
            document.getElementById('add-teacher-form').addEventListener('submit', function(e) {
                // Form will submit normally to PHP backend
            });

            document.getElementById('edit-teacher-form').addEventListener('submit', function(e) {
                // Form will submit normally to PHP backend
                closeModal(editModal);
            });
        });
    </script>



    <script src="admin_assets/js/admin_script.js"></script>
</body>
</html>
