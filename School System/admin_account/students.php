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

    <script>
        // Track filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.toggle-btn');
            const trackFilter = document.querySelector('.track-filter');
            const trackSelector = document.getElementById('trackSelector');
            const trackSections = document.querySelectorAll('.track-table-section');

            // Toggle between view modes
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const view = this.getAttribute('data-view');
                    
                    // Update active button
                    toggleButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    if (view === 'all') {
                        // Show all tracks
                        trackFilter.style.display = 'none';
                        trackSections.forEach(section => {
                            section.style.display = 'block';
                        });
                    } else {
                        // Show filter dropdown
                        trackFilter.style.display = 'block';
                        // Trigger change to show selected track
                        filterTracks(trackSelector.value);
                    }
                });
            });

            // Filter tracks based on selection
            trackSelector.addEventListener('change', function() {
                filterTracks(this.value);
            });

            function filterTracks(selectedTrack) {
                if (selectedTrack === 'all') {
                    trackSections.forEach(section => {
                        section.style.display = 'block';
                    });
                } else {
                    trackSections.forEach(section => {
                        const trackType = section.getAttribute('data-track');
                        if (trackType === selectedTrack) {
                            section.style.display = 'block';
                            section.style.animation = 'fadeInUp 0.5s ease-out';
                        } else {
                            section.style.display = 'none';
                        }
                    });
                }
            }
        });
    </script>

    <section class="page-content">
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

            <div class="charts-grid">
                <!-- Current Year Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h2>Current Academic Year Enrollment</h2>
                        <p class="chart-subtitle">2025-2026 Student Demographics</p>
                    </div>
                    <div class="chart-wrapper">
                        <div class="chart-content">
                            <div class="bar-container">
                                <div class="bar-group">
                                    <div class="bar male-bar" data-value="450">
                                        <div class="bar-fill male-fill"></div>
                                        <span class="bar-value">153</span>
                                    </div>
                                    <div class="bar-label">
                                        <div class="label-icon male-icon">
                                            <i class="fas fa-male"></i>
                                        </div>
                                        <span>Male Students</span>
                                    </div>
                                </div>
                                <div class="bar-group">
                                    <div class="bar female-bar" data-value="520">
                                        <div class="bar-fill female-fill"></div>
                                        <span class="bar-value">102</span>
                                    </div>
                                    <div class="bar-label">
                                        <div class="label-icon female-icon">
                                            <i class="fas fa-female"></i>
                                        </div>
                                        <span>Female Students</span>
                                    </div>
                                </div>
                            </div>
                            <div class="total-count">
                                <div class="total-box">
                                    <i class="fas fa-users"></i>
                                    <div class="total-info">
                                        <span class="total-label">Total Students</span>
                                        <span class="total-number">255</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Last Year Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h2>Previous Academic Year Enrollment</h2>
                        <p class="chart-subtitle">2024-2025 Student Demographics</p>
                    </div>
                    <div class="chart-wrapper">
                        <div class="chart-content">
                            <div class="bar-container">
                                <div class="bar-group">
                                    <div class="bar male-bar" data-value="420">
                                        <div class="bar-fill male-fill" style="height: 70%;"></div>
                                        <span class="bar-value">165</span>
                                    </div>
                                    <div class="bar-label">
                                        <div class="label-icon male-icon">
                                            <i class="fas fa-male"></i>
                                        </div>
                                        <span>Male Students</span>
                                    </div>
                                </div>
                                <div class="bar-group">
                                    <div class="bar female-bar" data-value="480">
                                        <div class="bar-fill female-fill" style="height: 80%;"></div>
                                        <span class="bar-value">111</span>
                                    </div>
                                    <div class="bar-label">
                                        <div class="label-icon female-icon">
                                            <i class="fas fa-female"></i>
                                        </div>
                                        <span>Female Students</span>
                                    </div>
                                </div>
                            </div>
                            <div class="total-count">
                                <div class="total-box">
                                    <i class="fas fa-users"></i>
                                    <div class="total-info">
                                        <span class="total-label">Total Students</span>
                                        <span class="total-number">276</span>
                                    </div>
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

            <!-- Students List Tables by Track -->
            <div class="students-tables-section">
                <div class="section-header">
                    <div class="header-content">
                        <h2><i class="fas fa-list"></i> Enrolled Students by Academic Track</h2>
                        <div class="view-toggle">
                            <button class="toggle-btn active" data-view="all">
                                <i class="fas fa-th-large"></i> View All
                            </button>
                            <button class="toggle-btn" data-view="individual">
                                <i class="fas fa-filter"></i> Filter by Track
                            </button>
                        </div>
                    </div>
                    <div class="track-filter" style="display: none;">
                        <select id="trackSelector" class="track-select">
                            <option value="all">All Tracks</option>
                            <option value="abm">ABM - Accountancy, Business and Management</option>
                            <option value="humss">HUMSS - Humanities and Social Sciences</option>
                            <option value="stem">STEM - Science, Technology, Engineering, and Mathematics</option>
                            <option value="gas">GAS - General Academic Strand</option>
                            <option value="tvl-he">TVL-HE - Home Economics</option>
                            <option value="tvl-ia">TVL-IA - Industrial Arts</option>
                            <option value="tvl-ict">TVL-ICT - Information and Communications Technology</option>
                        </select>
                    </div>
                </div>

                <!-- ABM Track Table -->
                <div class="track-table-section" data-track="abm">
                    <div class="track-table-header abm-header">
                        <h3><i class="fas fa-briefcase"></i> Accountancy, Business and Management (ABM)</h3>
                        <span class="student-count">Total Enrolled: 2 Students</span>
                    </div>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Grade Level</th>
                                    <th>Gender</th>
                                    <th>Room Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2024-001</td>
                                    <td>Maria Santos</td>
                                    <td>Grade 11</td>
                                    <td>Female</td>
                                    <td><span class="room-badge">Room 101</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                                <tr>
                                    <td>2024-002</td>
                                    <td>Juan Dela Cruz</td>
                                    <td>Grade 12</td>
                                    <td>Male</td>
                                    <td><span class="room-badge">Room 102</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- HUMSS Track Table -->
                <div class="track-table-section" data-track="humss">
                    <div class="track-table-header humss-header">
                        <h3><i class="fas fa-book"></i> Humanities and Social Sciences (HUMSS)</h3>
                        <span class="student-count">Total Enrolled: 2 Students</span>
                    </div>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Grade Level</th>
                                    <th>Gender</th>
                                    <th>Room Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2024-003</td>
                                    <td>Ana Reyes</td>
                                    <td>Grade 11</td>
                                    <td>Female</td>
                                    <td><span class="room-badge">Room 201</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                                <tr>
                                    <td>2024-004</td>
                                    <td>Pedro Garcia</td>
                                    <td>Grade 12</td>
                                    <td>Male</td>
                                    <td><span class="room-badge">Room 202</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- STEM Track Table -->
                <div class="track-table-section" data-track="stem">
                    <div class="track-table-header stem-header">
                        <h3><i class="fas fa-flask"></i> Science, Technology, Engineering, and Mathematics (STEM)</h3>
                        <span class="student-count">Total Enrolled: 2 Students</span>
                    </div>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Grade Level</th>
                                    <th>Gender</th>
                                    <th>Room Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2024-005</td>
                                    <td>Sofia Martinez</td>
                                    <td>Grade 11</td>
                                    <td>Female</td>
                                    <td><span class="room-badge">Room 301</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                                <tr>
                                    <td>2024-006</td>
                                    <td>Miguel Torres</td>
                                    <td>Grade 12</td>
                                    <td>Male</td>
                                    <td><span class="room-badge">Room 302</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- GAS Track Table -->
                <div class="track-table-section" data-track="gas">
                    <div class="track-table-header gas-header">
                        <h3><i class="fas fa-graduation-cap"></i> General Academic Strand (GAS)</h3>
                        <span class="student-count">Total Enrolled: 2 Students</span>
                    </div>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Grade Level</th>
                                    <th>Gender</th>
                                    <th>Room Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2024-007</td>
                                    <td>Isabella Ramos</td>
                                    <td>Grade 11</td>
                                    <td>Female</td>
                                    <td><span class="room-badge">Room 401</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                                <tr>
                                    <td>2024-008</td>
                                    <td>Carlos Mendoza</td>
                                    <td>Grade 12</td>
                                    <td>Male</td>
                                    <td><span class="room-badge">Room 402</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TVL - Home Economics Table -->
                <div class="track-table-section" data-track="tvl-he">
                    <div class="track-table-header tvl-he-header">
                        <h3><i class="fas fa-utensils"></i> Technical-Vocational-Livelihood - Home Economics (TVL-HE)</h3>
                        <span class="student-count">Total Enrolled: 2 Students</span>
                    </div>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Grade Level</th>
                                    <th>Gender</th>
                                    <th>Room Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2024-009</td>
                                    <td>Lucia Fernandez</td>
                                    <td>Grade 11</td>
                                    <td>Female</td>
                                    <td><span class="room-badge">Room 501</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                                <tr>
                                    <td>2024-010</td>
                                    <td>Rosa Castillo</td>
                                    <td>Grade 12</td>
                                    <td>Female</td>
                                    <td><span class="room-badge">Room 502</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TVL - Industrial Arts Table -->
                <div class="track-table-section" data-track="tvl-ia">
                    <div class="track-table-header tvl-ia-header">
                        <h3><i class="fas fa-tools"></i> Technical-Vocational-Livelihood - Industrial Arts (TVL-IA)</h3>
                        <span class="student-count">Total Enrolled: 2 Students</span>
                    </div>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Grade Level</th>
                                    <th>Gender</th>
                                    <th>Room Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2024-011</td>
                                    <td>Roberto Cruz</td>
                                    <td>Grade 11</td>
                                    <td>Male</td>
                                    <td><span class="room-badge">Room 601</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                                <tr>
                                    <td>2024-012</td>
                                    <td>Diego Alvarez</td>
                                    <td>Grade 12</td>
                                    <td>Male</td>
                                    <td><span class="room-badge">Room 602</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TVL - ICT Table -->
                <div class="track-table-section" data-track="tvl-ict">
                    <div class="track-table-header tvl-ict-header">
                        <h3><i class="fas fa-laptop-code"></i> Technical-Vocational-Livelihood - Information and Communications Technology (TVL-ICT)</h3>
                        <span class="student-count">Total Enrolled: 2 Students</span>
                    </div>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Grade Level</th>
                                    <th>Gender</th>
                                    <th>Room Number</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2024-013</td>
                                    <td>Elena Morales</td>
                                    <td>Grade 11</td>
                                    <td>Female</td>
                                    <td><span class="room-badge">Room 701</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                                <tr>
                                    <td>2024-014</td>
                                    <td>Gabriel Santos</td>
                                    <td>Grade 12</td>
                                    <td>Male</td>
                                    <td><span class="room-badge">Room 702</span></td>
                                    <td><button class="btn-view" aria-label="View student details"><i class="fas fa-eye"></i> View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        * {
            box-sizing: border-box;
        }

        .students-container {
            padding: 2rem;
            max-width: 100%;
            margin: 0 auto;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .charts-grid {
            display: flex;
            gap: 2rem;
            justify-content: center;
            align-items: stretch;
            margin-bottom: 3rem;
        }

        .chart-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex: 1;
            max-width: 500px;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }



        .chart-header {
            margin-bottom: 2rem;
            text-align: center;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .chart-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.5px;
        }

        .chart-subtitle {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0;
            font-weight: 500;
        }

        .chart-wrapper {
            padding: 0;
        }

        .chart-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .bar-container {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 3rem;
            padding: 1.5rem 0;
        }

        .bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
            flex: 1;
            max-width: 150px;
        }

        .bar {
            width: 100%;
            height: 240px;
            background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px 16px 0 0;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 2px solid #f1f5f9;
        }

        .bar:hover {
            transform: scale(1.03);
            box-shadow: inset 0 2px 12px rgba(0, 0, 0, 0.1);
        }

        .bar-fill {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            border-radius: 12px 12px 0 0;
            animation: fillBar 1.5s ease-out forwards;
            transform-origin: bottom;
        }

        .male-fill {
            background: linear-gradient(180deg, #60a5fa 0%, #3b82f6 30%, #2563eb 70%, #1e40af 100%);
            box-shadow: 0 -4px 20px rgba(59, 130, 246, 0.6), 
                        0 0 40px rgba(59, 130, 246, 0.4),
                        inset 0 0 20px rgba(255, 255, 255, 0.2);
        }

        .female-fill {
            background: linear-gradient(180deg, #f9a8d4 0%, #f472b6 30%, #ec4899 70%, #db2777 100%);
            box-shadow: 0 -4px 20px rgba(236, 72, 153, 0.6), 
                        0 0 40px rgba(236, 72, 153, 0.4),
                        inset 0 0 20px rgba(255, 255, 255, 0.2);
        }

        .male-bar .bar-fill {
            height: 75%;
        }

        .female-bar .bar-fill {
            height: 87%;
        }

        @keyframes fillBar {
            from {
                height: 0;
            }
            to {
                height: var(--bar-height);
            }
        }

        .bar-value {
            position: relative;
            z-index: 2;
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffffff;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.4), 0 0 20px rgba(255, 255, 255, 0.6);
            animation: fadeIn 0.8s ease-out 1s forwards;
            opacity: 0;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        .bar-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .label-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #ffffff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .label-icon:hover {
            transform: scale(1.1);
        }

        .male-icon {
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.35);
        }

        .female-icon {
            background: linear-gradient(135deg, #f472b6, #ec4899);
            box-shadow: 0 4px 16px rgba(236, 72, 153, 0.35);
        }

        .bar-label span {
            font-size: 0.95rem;
            font-weight: 600;
            color: #334155;
            text-align: center;
        }

        .total-count {
            display: flex;
            justify-content: center;
            padding-top: 1.5rem;
            border-top: 2px solid #f1f5f9;
        }

        .total-box {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem 2.5rem;
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .total-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(245, 158, 11, 0.4);
        }

        .total-box i {
            font-size: 2rem;
            color: #ffffff;
        }

        .total-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .total-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .total-number {
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
        }

        /* Students Tables Section */
        .students-tables-section {
            margin-top: 3rem;
        }

        .section-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-header h2 i {
            color: #f59e0b;
        }

        .view-toggle {
            display: flex;
            gap: 0.75rem;
            background: #f1f5f9;
            padding: 0.4rem;
            border-radius: 12px;
        }

        .toggle-btn {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .toggle-btn:hover {
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
        }

        .toggle-btn.active {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .track-filter {
            width: 100%;
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

        .track-select {
            width: 100%;
            max-width: 500px;
            padding: 1rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            color: #334155;
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23f59e0b' d='M10.293 3.293L6 7.586 1.707 3.293A1 1 0 00.293 4.707l5 5a1 1 0 001.414 0l5-5a1 1 0 10-1.414-1.414z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1.5rem center;
            padding-right: 3rem;
        }

        .track-select:hover {
            border-color: #f59e0b;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
        }

        .track-select:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .track-table-section {
            background: #ffffff;
            border-radius: 20px;
            padding: 0;
            margin-bottom: 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }



        .track-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.75rem 2rem;
            color: #ffffff;
            position: relative;
            overflow: hidden;
        }

        .track-table-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .track-table-header:hover::before {
            opacity: 1;
        }

        .track-table-header h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .track-table-header h3 i {
            font-size: 1.3rem;
        }

        .student-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.6rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }

        .abm-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .humss-header {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
        }

        .stem-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .gas-header {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .tvl-he-header {
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
        }

        .tvl-ia-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        }

        .tvl-ict-header {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
        }

        .table-container {
            overflow-x: auto;
            padding: 1.5rem 2rem 2rem 2rem;
        }

        .students-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.95rem;
        }

        .students-table thead {
            background: linear-gradient(135deg, #22775e 0%, #1a5d4a 100%);
        }

        .students-table th {
            padding: 1.25rem 1.5rem;
            text-align: left;
            font-weight: 700;
            color: #ffffff;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            border: none;
        }

        .students-table th:first-child {
            border-radius: 12px 0 0 0;
        }

        .students-table th:last-child {
            border-radius: 0 12px 0 0;
        }

        .students-table tbody tr {
            background: #ffffff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 1px solid #f1f5f9;
        }

        .students-table tbody tr:last-child {
            border-bottom: none;
        }

        .students-table tbody tr:hover {
            background: linear-gradient(90deg, #d1f5ea 0%, #b8ebe0 100%);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(34, 119, 94, 0.15);
        }

        .students-table td {
            padding: 1.25rem 1.5rem;
            color: #475569;
            font-weight: 500;
            border: none;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.active {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .room-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #22775e 0%, #1a5d4a 100%);
            color: #ffffff;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(34, 119, 94, 0.25);
            transition: all 0.3s ease;
        }

        .room-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 119, 94, 0.4);
        }

        .room-badge::before {
            content: "📍";
            font-size: 0.9rem;
        }

        .btn-view {
            padding: 0.6rem 1.25rem;
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.875rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }

        .btn-view:active {
            transform: translateY(0);
        }

        /* Grade 7-10 Enrollment Bar Graph Styles */
        .chart-card.full-width {
            width: calc(100% + 4rem);
            max-width: none;
            margin-left: -2rem;
            margin-right: -2rem;
            margin-bottom: 1rem;
        }



        .grade-bar-container {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            gap: 2rem;
            padding: 2rem 0;
        }

        .grade-bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .grade-label {
            font-weight: 600;
            color: #334155;
            font-size: 1rem;
        }

        .year-bars {
            display: flex;
            gap: 1rem;
        }

        .year-bar-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .year-bar {
            width: 60px;
            background: #e2e8f0;
            border-radius: 4px 4px 0 0;
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .year-bar:hover {
            transform: scale(1.05);
        }

        .year-bar.sy2024 {
            background: linear-gradient(to top, #60a5fa 0%, #3b82f6 100%);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.3);
        }

        .year-bar.sy2025 {
            background: linear-gradient(to top, #f472b6 0%, #ec4899 100%);
            box-shadow: 0 0 10px rgba(236, 72, 153, 0.3);
        }

        .year-bar-value {
            color: #ffffff;
            font-weight: 700;
            font-size: 0.8rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .year-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            text-align: center;
        }

        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 2px;
        }

        .sy2024-color {
            background: linear-gradient(to right, #60a5fa, #3b82f6);
        }

        .sy2025-color {
            background: linear-gradient(to right, #f472b6, #ec4899);
        }
        /* BMI Status Bar Graph Styles */
        .bmi-bar-container {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            gap: 2rem;
            padding: 2rem 0;
        }
        .bmi-bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        .bmi-label {
            font-weight: 600;
            color: #334155;
            font-size: 1rem;
        }

        /* HFA Status Bar Graph Styles */
        .hfa-bar-container {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            gap: 2rem;
            padding: 2rem 0;
        }
        .hfa-bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        .hfa-label {
            font-weight: 600;
            color: #334155;
            font-size: 1rem;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .charts-grid {
                flex-direction: column;
                align-items: center;
            }

            .chart-card {
                max-width: 600px;
                width: 100%;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .view-toggle {
                width: 100%;
                justify-content: space-between;
            }

            .toggle-btn {
                flex: 1;
                justify-content: center;
            }

            .track-table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .students-table {
                font-size: 0.85rem;
            }

            .students-table th,
            .students-table td {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .students-container {
                padding: 1rem;
                background: #f8fafc;
            }

            .chart-card {
                padding: 1.5rem;
            }

            .bar-container {
                gap: 2rem;
            }

            .bar {
                height: 200px;
            }

            .chart-header h2 {
                font-size: 1.3rem;
            }

            .section-header {
                padding: 1.5rem;
            }

            .section-header h2 {
                font-size: 1.5rem;
            }

            .view-toggle {
                flex-direction: column;
                width: 100%;
            }

            .toggle-btn {
                width: 100%;
                padding: 1rem;
            }

            .track-select {
                font-size: 0.9rem;
            }

            .total-box {
                padding: 1rem 1.75rem;
            }

            .total-number {
                font-size: 1.75rem;
            }

            .bar-value {
                font-size: 1.3rem;
            }

            .track-table-header h3 {
                font-size: 1rem;
            }

            .students-table {
                font-size: 0.8rem;
            }

            .students-table th,
            .students-table td {
                padding: 0.875rem;
            }

            .btn-view {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
        }
    </style>

    <script src="admin_assets/js/admin_script.js"></script>
</body>

</html>