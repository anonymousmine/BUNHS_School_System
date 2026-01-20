<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
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

    <section class="page-content dashboard">
            <div class="dashboard-header">
                <div>
                    <p class="breadcrumb">
                        <span>Home</span>
                        <i class="fas fa-chevron-right"></i>
                        <span>Dashboard</span>
                    </p>
                </div>
                <button class="btn-primary">
                    <i class="fas fa-download"></i>
                    Export Report
                </button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <p class="stat-label">Total Students</p>
                        <h3 class="stat-value">263</h3>
                        <p class="stat-change negative">
                            <i class="fas fa-arrow-down"></i>
                            <span>7% for the current school year</span>
                        </p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-content">
                        <p class="stat-label">Total Teachers</p>
                        <h3 class="stat-value">15</h3>
                        <p class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>2% from last month</span>
                        </p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-content">
                        <p class="stat-label">School Clubs</p>
                        <h3 class="stat-value">5</h3>
                        <p class="stat-change neutral">
                            <i class="fas fa-minus"></i>
                            <span>No change</span>
                        </p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div class="stat-content">
                        <p class="stat-label">Revenue</p>
                        <h3 class="stat-value">₱780,000</h3>
                        <p class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>15.3% from last month</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="content-grid">
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Enrollment Trends</h3>
                        <select class="select-period">
                            <option>Last 6 months</option>
                            <option>Last year</option>
                            <option>All time</option>
                        </select>
                    </div>
                    <div class="chart-placeholder">
                        <i class="fas fa-chart-line"></i>
                        <p>Chart visualization would appear here</p>
                    </div>
                </div>

                <div class="activity-card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                        <a href="#" class="view-all">View All</a>
                    </div>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon blue">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-text">New student registered</p>
                                <p class="activity-time">2 minutes ago</p>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon green">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-text">Course assignment completed</p>
                                <p class="activity-time">15 minutes ago</p>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon orange">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-text">Payment reminder sent</p>
                                <p class="activity-time">1 hour ago</p>
                            </div>
                        </div>
                        <div class="activity-item">
                            <div class="activity-icon purple">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-text">New event scheduled</p>
                                <p class="activity-time">3 hours ago</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    <script src="admin_assets/js/admin_script.js"></script>
</body>

</html>