<!-- Sidebar -->
<aside class="sidebar" role="navigation" aria-label="Main navigation">
    <div class="logo" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
        <img src="../assets/img/logo.jpg" alt="School Logo" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="text-align: center; margin: 0; color: #8A9A5B; font-size: 16px; line-height: 1.3;">Buyoan National High School</h2>
    </div>

    <div class="profile" role="button" tabindex="0" aria-label="User profile">
        <img src="../assets/img/person/school head.jpg" alt="Profile picture of Jojo Apuli">
        <div class="info">
            <h4>Jojo Apuli</h4>
            <p>Administrator</p>
        </div>
        <i class="fas fa-chevron-right profile-arrow"></i>
    </div>

    <div class="menu-divider"></div>

    <nav class="menu" role="menu">
        <div class="menu-section">
            <span class="menu-label">MAIN MENU</span>
            <a href="admin_dashboard.php" class="menu-item active" role="menuitem" aria-label="Dashboard" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
                <span class="menu-badge">New</span>
            </a>
            <a href="students.php" class="menu-item" role="menuitem" aria-label="Students Management" title="Manage Students">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
                <span class="menu-count">276</span>
            </a>
            <a href="teachers.php" class="menu-item" role="menuitem" aria-label="Teachers Management" title="Manage Teachers">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Teachers</span>
                <span class="menu-count">25</span>
            </a>
            <a href="tracks.php" class="menu-item" role="menuitem" aria-label="Academic Tracks" title="Manage Academic Tracks">
                <i class="fas fa-book-open"></i>
                <span>Tracks</span>
                <span class="menu-count">7</span>
            </a>
            <a href="education.php" class="menu-item" role="menuitem" aria-label="Academic Tracks" title="Manage Academic Tracks">
                <i class="fa-solid fa-book-open-reader"></i>
                <span>Education</span>
                <span class="menu-count">New</span>
            </a>
        </div>

        <div class="menu-section">
            <span class="menu-label">MANAGEMENT</span>
            <div class="dropdown">
                <a href="javascript:void(0);" class="menu-item dropdown-toggle" role="menuitem" aria-haspopup="true" aria-expanded="false" title="Announcements">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Announcements</span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </a>
                <div class="dropdown-menu" role="menu">
                    <a href="announcements/view_sched.php" class="dropdown-item" data-page="view_sched.php" role="menuitem" title="View Schedule">
                        <i class="fas fa-calendar-check"></i>
                        <span>View Schedule</span>
                    </a>
                    <a href="announcements/create_new.php" class="dropdown-item" data-page="create_new.php" role="menuitem" title="Create New Announcement">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create New</span>
                    </a>
                    <div class="dropdown-divider" role="separator"></div>
                    <a href="announcements/low_priority.php" class="dropdown-item" data-page="low_priority.php" role="menuitem" title="Low Priority Announcements">
                        <i class="fas fa-flag"></i>
                        <span>Low Priority</span>
                    </a>
                    <a href="announcements/mid_priority.php" class="dropdown-item" data-page="mid_priority.php" role="menuitem" title="Medium Priority Announcements">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Medium Priority</span>
                    </a>
                    <a href="announcements/high_priority.php" class="dropdown-item" data-page="high_priority.php" role="menuitem" title="High Priority Announcements">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>High Priority</span>
                    </a>
                </div>
            </div>
            <a href="reports.php" class="menu-item" role="menuitem" aria-label="Reports" title="View Reports">
                <i class="fas fa-chart-line"></i>
                <span>Reports</span>
            </a>
            <a href="finance.php" class="menu-item" role="menuitem" aria-label="Finance" title="Financial Management">
                <i class="fas fa-wallet"></i>
                <span>Finance</span>
            </a>
        </div>

        <div class="menu-section">
            <span class="menu-label">SYSTEM</span>
            <a href="settings.php" class="menu-item" role="menuitem" aria-label="Settings" title="System Settings">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="help&support.php" class="menu-item" role="menuitem" aria-label="Help and Support" title="Get Help & Support">
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
            <div class="storage-bar" role="progressbar" aria-valuenow="68" aria-valuemin="0" aria-valuemax="100" title="Storage usage: 6.8 GB of 10 GB">
                <div class="storage-progress" style="width: 68%"></div>
            </div>
            <p class="storage-text">6.8 GB of 10 GB</p>
        </div>
        <a href="#logout" class="logout" role="button" aria-label="Logout" title="Logout from system" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fa-solid fa-power-off"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" aria-label="Toggle navigation menu" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Main Content -->
<div class="main">
    <header class="topbar">
        <div class="search" role="search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search students, teachers, courses..." aria-label="Search" title="Search for students, teachers, or courses">
            <kbd class="search-shortcut" title="Keyboard shortcut: Ctrl+K">Ctrl+K</kbd>
        </div>
        <div class="topbar-right">
            <button class="icon-btn" aria-label="Messages" title="Messages (3 unread)">
                <i class="fas fa-envelope"></i>
                <span class="badge">3</span>
            </button>
            <button class="icon-btn" aria-label="Notifications" title="Notifications (5 new)">
                <i class="fas fa-bell"></i>
                <span class="badge">5</span>
            </button>
            <div class="user-dropdown">
                <button class="user" aria-haspopup="true" aria-expanded="false" title="User menu">
                    <img src="../assets/img/person/school head.jpg" alt="User profile picture">
                    <div class="user-info">
                        <span class="user-name">Jojo Apuli</span>
                        <span class="user-role">Admin</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="user-menu" role="menu">
                    <a href="profile.html" class="user-menu-item" role="menuitem">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="settings.html" class="user-menu-item" role="menuitem">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <div class="user-menu-divider"></div>
                    <a href="#logout" class="user-menu-item logout-item" role="menuitem" onclick="return confirm('Are you sure you want to logout?');">
                        <i class="fa-solid fa-power-off"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>
</div>
