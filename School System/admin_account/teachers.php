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
        .teachers-table-container {
            background: linear-gradient(135deg, #ffffff 0%, #f1f3f4 100%);
            border-radius: 16px; /* Make corners consistent */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            margin-top: 16px;
            width: 100%; /* Adjust width to fit container */
            margin-right: 0; /* Remove negative margin */
        }
        .teachers-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .teachers-table thead th {
            background: linear-gradient(135deg, #22775e 0%, #10b981 100%);
            color: white;
            padding: 20px;
            text-align: left;
            font-weight: 700;
            font-size: 18px;
            border-bottom: 2px solid #10b981;
            position: relative;
        }
        .teachers-table thead th:first-child {
            border-top-left-radius: 0px; /* Match container radius */
        }
        .teachers-table thead th:last-child {
            border-top-right-radius: 0px; /* Match container radius */
        }
        .teachers-table thead th[colspan="5"] {
            text-align: center;
            font-size: 24px;
            padding: 30px;
            background: linear-gradient(135deg, #10b981 0%, #10b981 100%);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
            border-top-left-radius: 16px; /* Apply radius to colspan header */
            border-top-right-radius: 16px; /* Apply radius to colspan header */
        }
        .teachers-table tbody tr {
            transition: all 0.3s ease;
        }
        .teachers-table tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .teachers-table tbody tr:last-child td {
            border-bottom: none; /* Remove border from last row */
        }
        .teachers-table td {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
            background-color: #ffffff;
        }
        .teacher-info h4 {
            margin: 0;
            font-weight: 600;
            color: #2d3748;
        }
        .teacher-info p {
            margin: 5px 0 0 0;
            color: #718096;
            font-size: 12px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-active {
            display: inline-flex;
            align-items: center;
            color: #10b981; /* Set text color for active status */
        }
        .status-active::before {
            content: "\f192"; /* Circle icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900; /* Solid icon */
            color: #10b981;
            margin-right: 5px;
        }
        .status-pending {
            display: inline-flex;
            align-items: center;
            color: #FFD43B; /* Set text color for pending status */
        }
        .status-pending::before {
            content: "\f110"; /* Spinner icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900; /* Solid icon */
            color: #FFD43B;
            margin-right: 5px;
            animation: fa-spin 2s infinite linear; /* Add spin animation */
        }
        .status-rejected {
            display: inline-flex;
            align-items: center;
            color: #df111b; /* Set text color for rejected status */
        }
        .status-rejected::before {
            content: "\f057"; /* Times circle icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900; /* Solid icon */
            color: #df111b;
            margin-right: 5px;
        }
        /* Keyframe for spinner animation */
        @keyframes fa-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .action-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #667eea;
            background-color: rgba(102, 126, 234, 0.1);
        }
        .action-icon:hover {
            background-color: #667eea;
            color: white;
            transform: scale(1.1);
        }
        .series {
            font-weight: 500;
            color: #4a5568;
        }
    </style>
</head>

<body>
    <div id="navigation-container"></div>

    <script>
        // Load navigation from admin_nav.html
        fetch('admin_nav.html')
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
            // Dropdown toggle functionality
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                toggle.addEventListener('click', function (e) {
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
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                    });
                }
            });
        }
    </script>

    <section class="page-content">
        <div class="teachers-table-container" style="margin-bottom: 20px;">
            <table class="teachers-table">
                <thead>
                    <tr>
                        <th colspan="5">Pending Teacher Applications</th>
                    </tr>
                    <tr>
                        <th>Avatar</th>
                        <th>Name & Details</th>
                        <th>Series</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher1.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>John Applicant</h4><p>Mathematics Teacher</p></td>
                        <td class="series">LAC</td>
                        <td><span class="status-badge status-pending">Pending</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-check"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher2.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Jane Applicant</h4><p>Science Teacher</p></td>
                        <td class="series">Post-Graduate</td>
                        <td><span class="status-badge status-pending">Pending</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-check"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="teachers-table-container" style="margin-top: 20px;">
            <table class="teachers-table">
                <thead>
                    <tr>
                        <th colspan="5">Teacher Professional Development</th>
                    </tr>
                    <tr>
                        <th>Avatar</th>
                        <th>Name & Details</th>
                        <th>Series</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher1.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>John Doe</h4><p>Mathematics Teacher</p></td>
                        <td class="series">LAC</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher2.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Jane Smith</h4><p>Science Teacher</p></td>
                        <td class="series">LAC</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher3.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Mike Brown</h4><p>English Teacher</p></td>
                        <td class="series">LAC</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher4.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Alice Lee</h4><p>History Teacher</p></td>
                        <td class="series">Post-Graduate</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher5.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Robert Garcia</h4><p>Physics Teacher</p></td>
                        <td class="series">Post-Graduate</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher8.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Sarah Anderson</h4><p>Geography Teacher</p></td>
                        <td class="series">Post-Graduate</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher11.jpeg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>James Rodriguez</h4><p>Music Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher2.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Nancy Gonzalez</h4><p>Psychology Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher4.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Laura Young</h4><p>Literature Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher7.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Steven Lewis</h4><p>Statistics Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher9.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Daniel Robinson</h4><p>AI Teacher</p></td>
                        <td class="series">Content</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher12.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Amanda Baker</h4><p>Cybersecurity Teacher</p></td>
                        <td class="series">Content</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher2.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Barbara Evans</h4><p>Graphic Design Teacher</p></td>
                        <td class="series">Others</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher5.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Charles Morris</h4><p>Game Development Teacher</p></td>
                        <td class="series">Others</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher6.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Emily Wilson</h4><p>Chemistry Teacher</p></td>
                        <td class="series">Post-Graduate</td>
                        <td><span class="status-badge status-rejected">Offline</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher7.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>David Taylor</h4><p>Biology Teacher</p></td>
                        <td class="series">Post-Graduate</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher8.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Sarah Anderson</h4><p>Geography Teacher</p></td>
                        <td class="series">Post-Graduate</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher9.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Chris Thomas</h4><p>Computer Science Teacher</p></td>
                        <td class="series">Post-Graduate</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher10.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Lisa Martinez</h4><p>Art Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher11.jpeg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>James Rodriguez</h4><p>Music Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher12.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Karen Hernandez</h4><p>Physical Education Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-rejected">Offline</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher1.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Paul Lopez</h4><p>Economics Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher2.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Nancy Gonzalez</h4><p>Psychology Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher3.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Ronald Clark</h4><p>Sociology Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher4.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Laura Young</h4><p>Literature Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher5.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Michael King</h4><p>Philosophy Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-rejected">Offline</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher6.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Angela Wright</h4><p>Ethics Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher7.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Steven Lewis</h4><p>Statistics Teacher</p></td>
                        <td class="series">K to 12</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher8.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Helen Carter</h4><p>Data Science Teacher</p></td>
                        <td class="series">Content</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher9.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Daniel Robinson</h4><p>AI Teacher</p></td>
                        <td class="series">Content</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher10.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Jennifer Miller</h4><p>Machine Learning Teacher</p></td>
                        <td class="series">Content</td>
                        <td><span class="status-badge status-rejected">Offline</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher11.jpeg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Thomas Hall</h4><p>Blockchain Teacher</p></td>
                        <td class="series">Content</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher12.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Amanda Baker</h4><p>Cybersecurity Teacher</p></td>
                        <td class="series">Content</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher1.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Richard Green</h4><p>Web Development Teacher</p></td>
                        <td class="series">Others</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher2.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Barbara Evans</h4><p>Graphic Design Teacher</p></td>
                        <td class="series">Others</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher3.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Joseph Collins</h4><p>Photography Teacher</p></td>
                        <td class="series">Others</td>
                        <td><span class="status-badge status-rejected">Offline</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher4.webp" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Mary Stewart</h4><p>Animation Teacher</p></td>
                        <td class="series">Others</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher5.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Charles Morris</h4><p>Game Development Teacher</p></td>
                        <td class="series">Others</td>
                        <td><span class="status-badge status-pending">Busy</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                    <tr>
                        <td><img src="admin_assets/teacher/teacher6.jpg" alt="Avatar" class="avatar"></td>
                        <td class="teacher-info"><h4>Susan Rogers</h4><p>UX/UI Design Teacher</p></td>
                        <td class="series">Others</td>
                        <td><span class="status-badge status-active">Online</span></td>
                        <td class="actions">
                            <div class="action-icon"><i class="fas fa-edit"></i></div>
                            <div class="action-icon"><i class="fas fa-trash"></i></div>
                            <div class="action-icon"><i class="fas fa-eye"></i></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <script src="admin_assets/js/admin_script.js"></script>
