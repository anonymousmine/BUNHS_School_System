<?php
require_once '../session_config.php';

// Enhanced session check with fallback for testing
$is_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['admin', 'sub-admin']))
    || (isset($_SESSION['admin_id']));

// If not logged in, redirect to login
if (!$is_logged_in) {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../db_connection.php';
/** @var \mysqli $conn */ // $conn is set by db_connection.php
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Buyoan National High School Admin Dashboard">
    <title>Admin Dashboard</title>
    <base href="/BUNHS_School_System/">
    <link rel="stylesheet" href="/BUNHS_School_System/admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php include 'admin_nav.php'; ?>

    <section class="page-content dashboard" id="dashboard-content" style="display: block !important;">
        <!-- Simple Dashboard Header -->
        <div class="dashboard-header">
            <div>
                <p class="breadcrumb" aria-label="Breadcrumb">
                    <span>Home</span>
                    <i class="fas fa-chevron-right"></i>
                    <span>Dashboard</span>
                </p>
            </div>
        </div>

    <script src="/BUNHS_School_System/admin_account/admin_assets/js/admin_script.js"></script>
    <script>
        // Initialize Dashboard
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard DOM loaded');
        });

        // Initialize Mobile Navigation
        function initMobileNav() {
            var hamburger = document.getElementById('navHamburgerBtn');
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (!hamburger || !sidebar || !overlay) return;

            // Remove any previous listeners by cloning the button
            var fresh = hamburger.cloneNode(true);
            hamburger.parentNode.replaceChild(fresh, hamburger);
            hamburger = fresh;

            function openSidebar() {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('visible');
                hamburger.classList.add('open');
                hamburger.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                hamburger.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }

            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
            });

            overlay.addEventListener('click', closeSidebar);

            sidebar.querySelectorAll('a.menu-item').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 900) closeSidebar();
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 900) closeSidebar();
            });
        }

        // Initialize mobile navigation when DOM is loaded
        document.addEventListener('DOMContentLoaded', initMobileNav);
    </script>
</body>

</html>