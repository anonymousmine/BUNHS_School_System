<?php
session_start();
include '../db_connection.php';

// Handle POST requests for approve, reject, delete, edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'approve') {
            $id = mysqli_real_escape_string($conn, $_POST['subadmin_id']);
            $sql = "UPDATE `sub_admin` SET status='approved' WHERE id='$id'";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Sub-admin approved successfully.";
            } else {
                $_SESSION['error'] = "Failed to approve sub-admin: " . mysqli_error($conn);
            }
        } elseif ($action == 'reject') {
            $id = mysqli_real_escape_string($conn, $_POST['subadmin_id']);
            $sql = "DELETE FROM `sub_admin` WHERE id='$id'";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Sub-admin rejected and deleted successfully.";
            } else {
                $_SESSION['error'] = "Failed to reject sub-admin: " . mysqli_error($conn);
            }
        } elseif ($action == 'delete') {
            $id = mysqli_real_escape_string($conn, $_POST['subadmin_id']);
            $sql = "DELETE FROM `sub_admin` WHERE id='$id'";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Sub-admin deleted successfully.";
            } else {
                $_SESSION['error'] = "Failed to delete sub-admin: " . mysqli_error($conn);
            }
        } elseif ($action == 'edit') {
            $id = mysqli_real_escape_string($conn, $_POST['subadmin_id']);
            $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
            $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $sql = "UPDATE `sub_admin` SET first_name='$first_name', last_name='$last_name', email='$email', username='$username' WHERE id='$id'";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Sub-admin updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update sub-admin: " . mysqli_error($conn);
            }
        }

        // Redirect back to the page
        header("Location: admins.php");
        exit();
    }
}

// Fetch pending sub-admins
$sql_pending = "SELECT * FROM `sub_admin` WHERE status='pending' ORDER BY id DESC";
$result_pending = mysqli_query($conn, $sql_pending);
$pending_subadmins = [];
if ($result_pending) {
    while ($row = mysqli_fetch_assoc($result_pending)) {
        $pending_subadmins[] = $row;
    }
}

// Fetch approved sub-admins
$sql_approved = "SELECT * FROM `sub_admin` WHERE status='approved' ORDER BY id DESC";
$result_approved = mysqli_query($conn, $sql_approved);
$approved_subadmins = [];
if ($result_approved) {
    while ($row = mysqli_fetch_assoc($result_approved)) {
        $approved_subadmins[] = $row;
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
            margin: 30px 0;
            width: 100%;
        }
        .subadmin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .subadmin-table th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
        }
        .subadmin-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        .subadmin-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .subadmin-table .actions {
            display: flex;
            gap: 8px;
        }
        .subadmin-table .actions a, .subadmin-table .actions button {
            color: #6c757d;
            text-decoration: none;
            padding: 6px 8px;
            border-radius: 6px;
            transition: all 0.2s;
            border: none;
            background: none;
            cursor: pointer;
        }
        .subadmin-table .actions .approve:hover {
            color: #28a745;
        }
        .subadmin-table .actions .reject:hover {
            color: #dc3545;
        }
        .subadmin-table .actions .edit:hover {
            color: #007bff;
        }
        .subadmin-table .actions .delete:hover {
            color: #dc3545;
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
        .subadmin-form {
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
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus {
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
        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
            .subadmin-form {
                padding: 20px;
            }
            .modal-header {
                padding: 15px 20px;
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
            <div class="col-lg-8">
              <h1 class="heading-title">Sub-Admins</h1>
              <p class="mb-0">
                Manage all the Sub-Admin in your school.
              </p>
            </div>
          </div>
        </div>
      </div>
      <nav class="breadcrumbs">
        <div class="container">
          <ol>
            <li><a href="admin_dashboard.php" >Home</a></li>
            <li class="current">Admins</li>
          </ol>
        </div>
      </nav>
    </div><!-- End Page Title -->

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



    <section class="page-content section">
      <div class="container-fluid">
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

        <!-- Pending Sub-Admins Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>Pending Sub-Admins</h3>
            </div>
            <table class="subadmin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pending_subadmins) > 0): ?>
                        <?php foreach ($pending_subadmins as $subadmin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subadmin['first_name'] . ' ' . $subadmin['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($subadmin['email']); ?></td>
                                <td><?php echo htmlspecialchars($subadmin['username']); ?></td>
                                <td class="actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="subadmin_id" value="<?php echo htmlspecialchars($subadmin['id']); ?>">
                                        <button type="submit" class="approve" title="Approve"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="subadmin_id" value="<?php echo htmlspecialchars($subadmin['id']); ?>">
                                        <button type="submit" class="reject" title="Reject" onclick="return confirm('Are you sure you want to reject this sub-admin? This will delete their account.')"><i class="fas fa-times"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">No pending sub-admins.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Approved Sub-Admins Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>Approved Sub-Admins</h3>
            </div>
            <table class="subadmin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($approved_subadmins) > 0): ?>
                        <?php foreach ($approved_subadmins as $subadmin): ?>
                            <tr data-id="<?php echo htmlspecialchars($subadmin['id']); ?>">
                                <td><?php echo htmlspecialchars($subadmin['first_name'] . ' ' . $subadmin['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($subadmin['email']); ?></td>
                                <td><?php echo htmlspecialchars($subadmin['username']); ?></td>
                                <td class="actions">
                                    <a href="#" class="edit" title="Edit" data-id="<?php echo htmlspecialchars($subadmin['id']); ?>"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="delete" title="Delete" data-id="<?php echo htmlspecialchars($subadmin['id']); ?>"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 20px;">No approved sub-admins.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
      </div>

        <!-- Edit Sub-Admin Modal -->
        <div id="edit-subadmin-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Sub-Admin</h3>
                    <span class="close-modal">&times;</span>
                </div>
                <form id="edit-subadmin-form" class="subadmin-form" action="" method="post">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit-subadmin-id" name="subadmin_id">
                    <div class="form-group">
                        <label for="edit-first-name">First Name</label>
                        <input type="text" id="edit-first-name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-last-name">Last Name</label>
                        <input type="text" id="edit-last-name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-email">Email</label>
                        <input type="email" id="edit-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-username">Username</label>
                        <input type="text" id="edit-username" name="username" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn">Cancel</button>
                        <button type="submit" class="submit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Hidden Delete Form -->
        <form id="delete-subadmin-form" action="" method="post" style="display:none;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="subadmin_id" id="delete-subadmin-id">
        </form>
    </section>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = document.getElementById('edit-subadmin-modal');
            const closeButtons = document.querySelectorAll('.close-modal');
            const cancelButtons = document.querySelectorAll('.cancel-btn');

            // Handle Delete Sub-Admin
            document.querySelectorAll('.delete').forEach(deleteBtn => {
                deleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const subadminId = this.getAttribute('data-id');
                    if (confirm('Are you sure you want to delete this sub-admin?')) {
                        document.getElementById('delete-subadmin-id').value = subadminId;
                        document.getElementById('delete-subadmin-form').submit();
                    }
                });
            });

            // Open Edit Sub-Admin Modal
            document.querySelectorAll('.edit').forEach(editBtn => {
                editBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const row = this.closest('tr');
                    const cells = row.querySelectorAll('td');

                    // Extract sub-admin data from row
                    const nameParts = cells[0].textContent.trim().split(' ');
                    const firstName = nameParts[0] || '';
                    const lastName = nameParts.slice(1).join(' ') || '';
                    const email = cells[1].textContent.trim();
                    const username = cells[2].textContent.trim();
                    const id = row.getAttribute('data-id');

                    // Populate form fields
                    document.getElementById('edit-subadmin-id').value = id;
                    document.getElementById('edit-first-name').value = firstName;
                    document.getElementById('edit-last-name').value = lastName;
                    document.getElementById('edit-email').value = email;
                    document.getElementById('edit-username').value = username;

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
                if (e.target === editModal) {
                    closeModal(editModal);
                }
            });

            // ESC key to close
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (editModal.style.display === 'block') {
                        closeModal(editModal);
                    }
                }
            });

            // Form submissions
            document.getElementById('edit-subadmin-form').addEventListener('submit', function(e) {
                // Form will submit normally to PHP backend
                closeModal(editModal);
            });
        });
    </script>

    <script src="admin_assets/js/admin_script.js"></script>
</body>

</html>
