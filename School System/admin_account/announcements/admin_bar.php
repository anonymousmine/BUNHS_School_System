<!-- Sidebar -->
<aside class="sidebar">
    <div class="logo" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
        <img src="../assets/img/logo.jpg" alt="School Logo"
            style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
        <h2 style="text-align: center; margin: 0; color: #8A9A5B;">Buyoan National High School</h2>
    </div>

    <div class="profile">
        <img src="../assets/img/person/school head.jpg" alt="Profile">
        <div class="info">
            <h4>Jojo Apuli</h4>
            <p>Administrator</p>
        </div>
    </div>

    <div class="menu-divider"></div>

    <nav class="menu">
        <div class="menu-section">
            <span class="menu-label">MAIN MENU</span>
            <a href="admin_dashboard.html" class="menu-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
                <span class="menu-badge">New</span>
            </a>
            <a href="students.html" class="menu-item">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
                <span class="menu-count">276</span>
            </a>
            <a href="teachers.html" class="menu-item">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Teachers</span>
                <span class="menu-count">25</span>
            </a>
            <a href="tracks.html" class="menu-item">
                <i class="fas fa-book-open"></i>
                <span>Tracks</span>
                <span class="menu-count">7</span>
            </a>
        </div>

        <div class="menu-section">
            <span class="menu-label">MANAGEMENT</span>
            <div class="dropdown">
                <a href="javascript:void(0);" class="menu-item dropdown-toggle">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Announcements</span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="announcements/view_sched.html" class="dropdown-item" data-page="view_sched.html">
                        <i class="fas fa-calendar-check"></i>
                        <span>View Schedule</span>
                    </a>
                    <a href="announcements/create_new.html" class="dropdown-item" data-page="create_new.html">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create New</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="announcements/low_priority.html" class="dropdown-item" data-page="low_priority.html">
                        <i class="fas fa-flag"></i>
                        <span>Low Priority</span>
                    </a>
                    <a href="announcements/mid_priority.html" class="dropdown-item" data-page="mid_priority.html">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Medium Priority</span>
                    </a>
                    <a href="announcements/high_priority.html" class="dropdown-item" data-page="high_priority.html">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>High Priority</span>
                    </a>
                </div>
            </div>
            <a href="reports.html" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Reports</span>
            </a>
            <a href="finance.html" class="menu-item">
                <i class="fas fa-wallet"></i>
                <span>Finance</span>
            </a>
        </div>

        <div class="menu-section">
            <span class="menu-label">SYSTEM</span>
            <a href="settings.html" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="help&support.html" class="menu-item">
                <i class="fas fa-question-circle"></i>
                <span>Help & Support</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="storage-info">
            <div class="storage-header">
                <span>Storage Used</span>
                <span class="storage-percentage">68%</span>
            </div>
            <div class="storage-bar">
                <div class="storage-progress" style="width: 68%"></div>
            </div>
            <p class="storage-text">6.8 GB of 10 GB</p>
        </div>
        <a href="#logout" class="logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Main Content -->
<div class="main">
    <header class="topbar">
        <div class="search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search students, teachers, courses...">
        </div>
        <div class="topbar-right">
            <button class="icon-btn">
                <i class="fas fa-envelope"></i>
                <span class="badge">3</span>
            </button>
            <button class="icon-btn">
                <i class="fas fa-bell"></i>
                <span class="badge">5</span>
            </button>
            <div class="user">
                <img href="../assets/img/person/school head.jpg" alt="User">
                <div class="user-info">
                    <span class="user-name">Jojo Apuli</span>
                    <span class="user-role">Admin</span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </div>
        </div>
    </header>
</div>