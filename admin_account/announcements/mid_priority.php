<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Admin Dashboard</title>
    <link rel="stylesheet" href="../admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div id="navigation-container"></div>

    <script>
        // Load navigation from admin_nav.php
        fetch('../admin_nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('navigation-container').innerHTML = data;

                // Move page content to .main div
                const mainDiv = document.querySelector('.main');
                const pageContent = document.querySelector('.page-content');
                if (mainDiv && pageContent) {
                    mainDiv.appendChild(pageContent);
                }
                
                // Fix navigation paths for subfolder context
                fixNavigationPaths();
            })
            .catch(error => console.error('Error loading navigation:', error));
            
        function fixNavigationPaths() {
            // Fix all links to go up one level since we're in announcements folder
            document.querySelectorAll('.menu-item:not(.dropdown-toggle)').forEach(link => {
                const href = link.getAttribute('href');
                if (href && !href.startsWith('#') && !href.startsWith('javascript:') && !href.startsWith('../')) {
                    link.setAttribute('href', '../' + href);
                }
            });
            
            // Fix dropdown items - they should stay relative since they're in same folder
            document.querySelectorAll('.dropdown-item').forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.startsWith('announcements/')) {
                    link.setAttribute('href', href.replace('announcements/', ''));
                }
            });
            
            // Initialize dropdowns
            initializeDropdowns();
        }
        
        function initializeDropdowns() {
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
        <!-- Finance page content will go here -->
    </section>

    <script src="../admin_assets/js/admin_script.js"></script>
</body>

</html>