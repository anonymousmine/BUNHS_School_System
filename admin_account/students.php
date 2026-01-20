<?php
session_start();
include '../db_connection.php';

// Handle POST requests for add, edit, delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add') {
            // Add student
            $name = mysqli_real_escape_string($conn, $_POST['student_name']);
            $id = mysqli_real_escape_string($conn, $_POST['student_id']);
            $grade_section = mysqli_real_escape_string($conn, $_POST['grade_section']);
            $gender = mysqli_real_escape_string($conn, $_POST['gender']);
            $age = (int)$_POST['age'];

            // Handle image upload
            $image_path = '';
            if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] == 0) {
                $target_dir = "../assets/img/person/";
                $file_name = basename($_FILES["student_image"]["name"]);
                $target_file = $target_dir . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                // Check if image file is a actual image or fake image
                $check = getimagesize($_FILES["student_image"]["tmp_name"]);
                if ($check !== false) {
                    if (move_uploaded_file($_FILES["student_image"]["tmp_name"], $target_file)) {
                        $image_path = $target_file;
                    }
                }
            }

            $sql = "INSERT INTO students (student_name, student_id, grade_section, gender, age, student_image) VALUES ('$name', '$id', '$grade_section', '$gender', $age, '$image_path')";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Student added successfully.";
            } else {
                $_SESSION['error'] = "Failed to add student: " . mysqli_error($conn);
            }
        } elseif ($action == 'edit') {
            // Edit student
            $id = mysqli_real_escape_string($conn, $_POST['student_id']);
            $name = mysqli_real_escape_string($conn, $_POST['student_name']);
            $grade_section = mysqli_real_escape_string($conn, $_POST['grade_section']);
            $gender = mysqli_real_escape_string($conn, $_POST['gender']);
            $age = (int)$_POST['age'];

            // Handle image upload
            $image_path = '';
            if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] == 0) {
                $target_dir = "../assets/img/person/";
                $file_name = basename($_FILES["student_image"]["name"]);
                $target_file = $target_dir . $file_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                $check = getimagesize($_FILES["student_image"]["tmp_name"]);
                if ($check !== false) {
                    if (move_uploaded_file($_FILES["student_image"]["tmp_name"], $target_file)) {
                        $image_path = $target_file;
                    }
                }
            }

            $sql = "UPDATE students SET student_name='$name', grade_section='$grade_section', gender='$gender', age=$age";
            if ($image_path) {
                $sql .= ", student_image='$image_path'";
            }
            $sql .= " WHERE student_id='$id'";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Student updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update student: " . mysqli_error($conn);
            }
        } elseif ($action == 'delete') {
            // Delete student
            $id = mysqli_real_escape_string($conn, $_POST['student_id']);
            $sql = "DELETE FROM students WHERE student_id='$id'";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Student deleted successfully.";
            } else {
                $_SESSION['error'] = "Failed to delete student: " . mysqli_error($conn);
            }
        }

        // Redirect back to the page
        header("Location: students.php");
        exit();
    }
}

// Fetch all students
$sql = "SELECT * FROM students ORDER BY id DESC";
$result = mysqli_query($conn, $sql);
$students = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Admin Dashboard</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="admin_assets/cs/student.css">
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
        .student-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .student-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
        }
        .student-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        .student-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .student-table .actions {
            display: flex;
            gap: 8px;
        }
        .student-table .actions a {
            color: #6c757d;
            text-decoration: none;
            padding: 6px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .student-table .actions a:hover {
            background-color: #e9ecef;
        }
        .student-table .actions .edit:hover {
            color: #007bff;
        }
        .student-table .actions .delete:hover {
            color: #dc3545;
        }
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e9ecef;
            margin-right: 12px;
        }
        .student-info {
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
        .add-student-btn {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }
        .add-student-btn:hover {
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
        .student-form {
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
        .student-name {
            font-size: 24px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 5px;
        }
        .student-location {
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
            .student-form {
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
    </script>

    <section class="page-content">
        <!-- main content -->

    <!-- Page Title -->
    <div class="page-title">
      <div class="heading">
        <div class="container">
          <div class="row d-flex justify-content-center text-center">
            <div class="col-lg-8 text-center">
              <h1 class="heading-title">Student Records</h1>
              <p class="mb-0">
                Here is the Students Records of the School.
              </p>
            </div>
          </div>
        </div>
      </div>
      <nav class="breadcrumbs">
        <div class="container-fluid">
          <ol>
            <li><a href="admin_dashboard.php" >Home</a></li>
            <li class="current">Students</li>
          </ol>
        </div>
      </nav>
    </div><!-- End Page Title -->
        <div class="students-container">
            <!-- Grade 7-10 Enrollment Chart -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h2>Grade 7-10 Enrollment</h2>
                    <p class="chart-subtitle">SY 2024-2025 vs SY 2025-2026</p>
                </div>
                <div class="chart-wrapper">
                    <div class="chart-content">
                        <div class="grade-bar-container">
                            <div class="grade-bar-group">
                                <div class="grade-label">Grade 7</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 75%;" data-value="150">
                                            <span class="year-bar-value">150</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 85%;" data-value="170">
                                            <span class="year-bar-value">170</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="grade-bar-group">
                                <div class="grade-label">Grade 8</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 70%;" data-value="140">
                                            <span class="year-bar-value">140</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 80%;" data-value="160">
                                            <span class="year-bar-value">160</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="grade-bar-group">
                                <div class="grade-label">Grade 9</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 65%;" data-value="130">
                                            <span class="year-bar-value">130</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 72%;" data-value="145">
                                            <span class="year-bar-value">145</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="grade-bar-group">
                                <div class="grade-label">Grade 10</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 60%;" data-value="120">
                                            <span class="year-bar-value">120</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 68%;" data-value="135">
                                            <span class="year-bar-value">135</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color sy2024-color"></span>
                                <span>SY 2024-2025</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color sy2025-color"></span>
                                <span>SY 2025-2026</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Year Enrollment Comparison Chart -->
            <div class="chart-card enrollment-comparison-card">
                <div class="chart-header">
                    <h2>Academic Year Enrollment Comparison</h2>
                    <p class="chart-subtitle">Current Year (2025-2026) vs Previous Year (2024-2025)</p>
                </div>
                <div class="chart-wrapper">
                    <div class="chart-content">
                        <div class="enrollment-comparison-container">
                            <!-- Male Students Group -->
                            <div class="gender-group">
                                <div class="gender-label">
                                    <div class="gender-icon male-icon">
                                        <i class="fas fa-male"></i>
                                    </div>
                                    <span>Male Students</span>
                                </div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar current-year" style="height: 60%;" data-value="153">
                                            <span class="year-bar-value">153</span>
                                        </div>
                                        <div class="year-label">2025-2026</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar previous-year" style="height: 65%;" data-value="165">
                                            <span class="year-bar-value">165</span>
                                        </div>
                                        <div class="year-label">2024-2025</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Female Students Group -->
                            <div class="gender-group">
                                <div class="gender-label">
                                    <div class="gender-icon female-icon">
                                        <i class="fas fa-female"></i>
                                    </div>
                                    <span>Female Students</span>
                                </div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar current-year" style="height: 40%;" data-value="102">
                                            <span class="year-bar-value">102</span>
                                        </div>
                                        <div class="year-label">2025-2026</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar previous-year" style="height: 44%;" data-value="111">
                                            <span class="year-bar-value">111</span>
                                        </div>
                                        <div class="year-label">2024-2025</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="comparison-legend">
                            <div class="legend-item">
                                <span class="legend-color current-year-color"></span>
                                <span>Current Year (2025-2026)</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color previous-year-color"></span>
                                <span>Previous Year (2024-2025)</span>
                            </div>
                        </div>

                        <!-- Total Counts -->
                        <div class="total-counts-comparison">
                            <div class="total-box current-total">
                                <i class="fas fa-users"></i>
                                <div class="total-info">
                                    <span class="total-label">Current Year Total</span>
                                    <span class="total-number">255</span>
                                </div>
                            </div>
                            <div class="total-box previous-total">
                                <i class="fas fa-users"></i>
                                <div class="total-info">
                                    <span class="total-label">Previous Year Total</span>
                                    <span class="total-number">276</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BMI Status Chart -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h2>Body Mass Index (BMI) Status</h2>
                    <p class="chart-subtitle">BMI Status SY 2024-2025 vs SY 2025-2026</p>
                </div>
                <div class="chart-wrapper">
                    <div class="chart-content">
                        <div class="bmi-bar-container">
                            <div class="bmi-bar-group">
                                <div class="bmi-label">Severely Wasted</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 5%;">
                                            <span class="year-bar-value">5</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 12%;">
                                            <span class="year-bar-value">12</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bmi-bar-group">
                                <div class="bmi-label">Wasted</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 48%;">
                                            <span class="year-bar-value">48</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 30%;">
                                            <span class="year-bar-value">30</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bmi-bar-group">
                                <div class="bmi-label">Normal</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 100%;">
                                            <span class="year-bar-value">219</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 100%;">
                                            <span class="year-bar-value">214</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bmi-bar-group">
                                <div class="bmi-label">Overweight</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 5%;">
                                            <span class="year-bar-value">4</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 4%;">
                                            <span class="year-bar-value">6</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color sy2024-color"></span>
                                <span>SY 2024-2025</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color sy2025-color"></span>
                                <span>SY 2025-2026</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Height for Age (HFA) Status Chart -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h2>Height for Age (HFA) Status</h2>
                    <p class="chart-subtitle">HFA Status SY 2024-2025 vs SY 2025-2026</p>
                </div>
                <div class="chart-wrapper">
                    <div class="chart-content">
                        <div class="hfa-bar-container">
                            <div class="hfa-bar-group">
                                <div class="hfa-label">Severely Stunted</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 5%;">
                                            <span class="year-bar-value">10</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 100%;">
                                            <span class="year-bar-value">8</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="hfa-bar-group">
                                <div class="hfa-label">Stunted</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 15%;">
                                            <span class="year-bar-value">30</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 12.5%;">
                                            <span class="year-bar-value">25</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="hfa-bar-group">
                                <div class="hfa-label">Normal</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 70%;">
                                            <span class="year-bar-value">140</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 75%;">
                                            <span class="year-bar-value">150</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="hfa-bar-group">
                                <div class="hfa-label">Tall</div>
                                <div class="year-bars">
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2024" style="height: 10%;">
                                            <span class="year-bar-value">20</span>
                                        </div>
                                        <div class="year-label">SY 2024-2025</div>
                                    </div>
                                    <div class="year-bar-wrapper">
                                        <div class="year-bar sy2025" style="height: 8.5%;">
                                            <span class="year-bar-value">17</span>
                                        </div>
                                        <div class="year-label">SY 2025-2026</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color sy2024-color"></span>
                                <span>SY 2024-2025</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color sy2025-color"></span>
                                <span>SY 2025-2026</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Information Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>Student Information</h3>
                <button id="add-student-btn" class="add-student-btn" title="Add Student">
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
            <table class="student-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Student Name</th>
                        <th>ID Number</th>
                        <th>Grade & Section</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $fullName = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
                            $age = date_diff(date_create($student['birth_date']), date_create('today'))->y;
                            ?>
                            <tr data-id="<?php echo htmlspecialchars($student['student_id']); ?>">
                                <td>
                                    <img src="<?php echo htmlspecialchars($student['profile_image'] ?: '../assets/img/person/default.png'); ?>" alt="<?php echo $fullName; ?>" class="student-avatar">
                                </td>
                                <td><?php echo $fullName; ?></td>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['grade_level']); ?></td>
                                <td><?php echo htmlspecialchars($age); ?></td>
                                <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                <td class="actions">
                                    <a href="#" class="edit" title="Edit" data-id="<?php echo htmlspecialchars($student['student_id']); ?>"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="delete" title="Delete" data-id="<?php echo htmlspecialchars($student['student_id']); ?>"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px;">No students found. Add a student to get started.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Student Modal -->
        <div id="add-student-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add New Student</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <form id="add-student-form" class="student-form" action="" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="student-name">Student Name</label>
                        <input type="text" id="student-name" name="student_name" required>
                    </div>
                    <div class="form-group">
                        <label for="student-id">ID Number</label>
                        <input type="text" id="student-id" name="student_id" required>
                    </div>
                    <div class="form-group">
                        <label for="student-grade">Grade & Section</label>
                        <select id="student-grade" name="grade_section" required>
                            <option value="">Select Grade & Section</option>
                            <option value="Grade 7">Grade 7</option>
                            <option value="Grade 8">Grade 8</option>
                            <option value="Grade 9">Grade 9</option>
                            <option value="Grade 10">Grade 10</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="student-gender">Gender</label>
                        <select id="student-gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="student-age">Age</label>
                        <input type="number" id="student-age" name="age" required>
                    </div>
                    <div class="form-group">
                        <label for="student-image">Student Image</label>
                        <input type="file" id="student-image" name="student_image" accept="image/*">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn">Cancel</button>
                        <button type="submit" class="submit-btn">Submit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Student Modal -->
        <div id="edit-student-modal" class="modal">
            <div class="modal-content edit-modal-layout">
                <span class="close-modal">&times;</span>
                <div class="profile-section">
                    <div class="profile-image-container">
                        <img id="edit-student-profile-img" src="../assets/img/person/default.png" alt="Student Profile" class="profile-image">
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
                        <div class="student-name" id="edit-student-display-name">Student Name</div>
                        <div class="student-location" id="edit-student-location">Location</div>
                        <button class="edit-btn">Edit</button>
                    </div>
                    <form id="edit-student-form" class="student-form" action="" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="edit-student-name" name="student_name">
                        <input type="hidden" id="edit-student-id" name="student_id">
                        <div class="details-grid">
                            <div class="detail-row">
                                <div class="form-group">
                                    <label for="edit-student-grade">Grade & Section</label>
                                    <select id="edit-student-grade" name="grade_section" required>
                                        <option value="">Select Grade & Section</option>
                                        <option value="Grade 7">Grade 7</option>
                                        <option value="Grade 8">Grade 8</option>
                                        <option value="Grade 9">Grade 9</option>
                                        <option value="Grade 10">Grade 10</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="edit-student-gender">Gender</label>
                                    <select id="edit-student-gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="form-group">
                                    <label for="edit-student-age">Age</label>
                                    <input type="number" id="edit-student-age" name="age" required>
                                </div>
                            </div>
                        </div>
                        <input type="file" id="edit-student-image" name="student_image" accept="image/*" style="display: none;">
                        <div class="form-actions">
                            <button type="button" class="cancel-btn">Cancel</button>
                            <button type="submit" class="submit-btn">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Hidden Delete Form -->
        <form id="delete-student-form" action="" method="post" style="display:none;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="student_id" id="delete-student-id">
        </form>
    </section>

    <script src="admin_assets/js/admin_script.js"></script>

    <script>
        // Student Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Modal elements
            const addStudentModal = document.getElementById('add-student-modal');
            const editStudentModal = document.getElementById('edit-student-modal');
            const addStudentBtn = document.getElementById('add-student-btn');
            const closeModalBtns = document.querySelectorAll('.close-modal');
            const cancelBtns = document.querySelectorAll('.cancel-btn');

            // Add Student Modal
            addStudentBtn.addEventListener('click', function() {
                addStudentModal.style.display = 'block';
            });

            // Close modals
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    addStudentModal.style.display = 'none';
                    editStudentModal.style.display = 'none';
                });
            });

            cancelBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    addStudentModal.style.display = 'none';
                    editStudentModal.style.display = 'none';
                });
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == addStudentModal) {
                    addStudentModal.style.display = 'none';
                }
                if (event.target == editStudentModal) {
                    editStudentModal.style.display = 'none';
                }
            });

            // Edit Student Modal
            document.querySelectorAll('.edit').forEach(editBtn => {
                editBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-id');

                    // Fetch student data (assuming you have a way to get student data, e.g., via AJAX or pre-loaded)
                    // For now, we'll populate with dummy data or you can implement AJAX call
                    fetchStudentData(studentId).then(student => {
                        document.getElementById('edit-student-display-name').textContent = student.student_name;
                        document.getElementById('edit-student-location').textContent = student.grade_section;
                        document.getElementById('edit-student-profile-img').src = student.student_image || '../assets/img/person/default.png';
                        document.getElementById('edit-student-name').value = student.student_name;
                        document.getElementById('edit-student-id').value = student.student_id;
                        document.getElementById('edit-student-grade').value = student.grade_section;
                        document.getElementById('edit-student-gender').value = student.gender;
                        document.getElementById('edit-student-age').value = student.age;

                        editStudentModal.style.display = 'block';
                    });
                });
            });

            // Delete Student
            document.querySelectorAll('.delete').forEach(deleteBtn => {
                deleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-id');

                    if (confirm('Are you sure you want to delete this student?')) {
                        document.getElementById('delete-student-id').value = studentId;
                        document.getElementById('delete-student-form').submit();
                    }
                });
            });

            // Image upload for edit modal
            document.getElementById('edit-student-profile-img').addEventListener('click', function() {
                document.getElementById('edit-student-image').click();
            });

            document.getElementById('edit-student-image').addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('edit-student-profile-img').src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Form validation (optional)
            document.getElementById('add-student-form').addEventListener('submit', function(e) {
                // Add any custom validation here
            });

            document.getElementById('edit-student-form').addEventListener('submit', function(e) {
                // Add any custom validation here
            });
        });

        // Function to fetch student data (implement based on your backend)
        function fetchStudentData(studentId) {
            // This is a placeholder. In a real application, you'd make an AJAX call to your backend
            // For now, return a promise that resolves with dummy data
            return new Promise((resolve) => {
                // Simulate fetching data
                const students = <?php echo json_encode($students); ?>;
                const student = students.find(s => s.student_id == studentId);
                resolve(student || {});
            });
        }
    </script>
</body>

</html>
