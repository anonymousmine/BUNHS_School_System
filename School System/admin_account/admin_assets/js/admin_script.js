// Enhanced Navigation and Dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    
    // Fix dropdown item paths based on current location
    const currentPath = window.location.pathname;
    const isInSubfolder = currentPath.includes('/announcements/');
    const pathPrefix = isInSubfolder ? '../announcements/' : 'announcements/';
    
    document.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
        const page = item.getAttribute('data-page');
        item.href = pathPrefix + page;
    });
    
    // Mobile Menu Toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const body = document.body;
    
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            body.classList.toggle('sidebar-active');
        });
        
        // Close sidebar when clicking outside on mobile
        body.addEventListener('click', function(e) {
            if (body.classList.contains('sidebar-active') && 
                !sidebar.contains(e.target) && 
                !mobileMenuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                body.classList.remove('sidebar-active');
            }
        });
    }
    
    // User Dropdown Menu
    const userDropdown = document.querySelector('.user-dropdown');
    const userButton = userDropdown?.querySelector('.user');
    
    if (userButton) {
        userButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
            
            // Close other dropdowns
            document.querySelectorAll('.dropdown.active').forEach(d => {
                d.classList.remove('active');
            });
        });
    }
    
    // Close user dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (userDropdown && !userDropdown.contains(e.target)) {
            userDropdown.classList.remove('active');
        }
    });
    
    // Keyboard shortcut for search (Ctrl+K)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.search input');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // ESC to close dropdowns and mobile menu
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown.active, .user-dropdown.active').forEach(d => {
                d.classList.remove('active');
            });
            if (sidebar?.classList.contains('active')) {
                sidebar.classList.remove('active');
                body.classList.remove('sidebar-active');
            }
        }
    });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            if (this.getAttribute('href') !== '#' && this.getAttribute('href') !== '#logout') {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });

    // Dropdown toggle functionality with keyboard support
    function toggleDropdown(dropdown) {
        const isActive = dropdown.classList.contains('active');
        const toggle = dropdown.querySelector('.dropdown-toggle');
        
        // Close all dropdowns
        document.querySelectorAll('.dropdown').forEach(d => {
            d.classList.remove('active');
            const t = d.querySelector('.dropdown-toggle');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
        
        // Toggle the clicked dropdown
        if (!isActive) {
            dropdown.classList.add('active');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }
    }

    // Add event listeners to all dropdown toggles
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const dropdown = this.closest('.dropdown');
            toggleDropdown(dropdown);
        });
        
        // Keyboard navigation
        toggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const dropdown = this.closest('.dropdown');
                toggleDropdown(dropdown);
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown').forEach(dropdown => {
                dropdown.classList.remove('active');
                const toggle = dropdown.querySelector('.dropdown-toggle');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Active menu item highlighting based on current page
    const currentPage = window.location.pathname.split('/').pop() || 'admin_dashboard.html';
    document.querySelectorAll('.menu-item[href], .dropdown-item[href]').forEach(item => {
        const itemHref = item.getAttribute('href');
        if (itemHref === currentPage || itemHref.includes(currentPage)) {
            // Remove active class from all items and toggles
            document.querySelectorAll('.menu-item, .dropdown-toggle').forEach(i => i.classList.remove('active'));
            // Add active class to current item or its toggle
            if (item.classList.contains('dropdown-item')) {
                const toggle = item.closest('.dropdown').querySelector('.dropdown-toggle');
                if (toggle) toggle.classList.add('active');
            } else {
                item.classList.add('active');
            }
        }
    });

    // Update active state on menu click with visual feedback
    document.querySelectorAll('.menu-item:not(.dropdown-toggle)').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            // Remove active from all menu items and toggles
            document.querySelectorAll('.menu-item, .dropdown-toggle').forEach(i => i.classList.remove('active'));
            // Add active to clicked item
            this.classList.add('active');

            // Close mobile menu on item click
            if (window.innerWidth <= 768) {
                sidebar?.classList.remove('active');
                body.classList.remove('sidebar-active');
            }

            // Navigate after a short delay to show the gradient swap
            setTimeout(() => {
                window.location.href = this.href;
            }, 300);
        });
    });

    // Update active state on dropdown item click with visual feedback
    document.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            // Remove active from all menu items and toggles
            document.querySelectorAll('.menu-item, .dropdown-toggle').forEach(i => i.classList.remove('active'));
            // Add active to the toggle of this dropdown
            const toggle = this.closest('.dropdown').querySelector('.dropdown-toggle');
            if (toggle) toggle.classList.add('active');

            // Close mobile menu on item click
            if (window.innerWidth <= 768) {
                sidebar?.classList.remove('active');
                body.classList.remove('sidebar-active');
            }

            // Navigate after a short delay to show the gradient swap
            setTimeout(() => {
                window.location.href = this.href;
            }, 300);
        });
    });

    // Sidebar scroll shadow effect
    if (sidebar) {
        sidebar.addEventListener('scroll', function() {
            if (this.scrollTop > 20) {
                this.style.boxShadow = '2px 0 25px rgba(0, 0, 0, 0.12)';
            } else {
                this.style.boxShadow = '2px 0 20px rgba(0, 0, 0, 0.08)';
            }
        });
    }

    // Add hover effect and accessibility improvements
    document.querySelectorAll('.menu-item, .dropdown-item').forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.25s cubic-bezier(0.4, 0, 0.2, 1)';
        });
        
        // Keyboard navigation for menu items
        item.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                if (!this.classList.contains('dropdown-toggle')) {
                    e.preventDefault();
                    this.click();
                }
            }
        });
    });

    // Profile card interaction
    const profile = document.querySelector('.profile');
    if (profile) {
        profile.addEventListener('click', function() {
            // Future: Can add profile quick-view or dropdown
            console.log('Profile clicked - ready for expansion');
        });
        
        // Keyboard support for profile
        profile.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    }

    // Storage info animation on load
    const storageProgress = document.querySelector('.storage-progress');
    if (storageProgress) {
        const targetWidth = storageProgress.style.width;
        storageProgress.style.width = '0%';
        setTimeout(() => {
            storageProgress.style.width = targetWidth;
        }, 500);
    }

    // Notification badge pulse animation
    const badges = document.querySelectorAll('.badge');
    badges.forEach(badge => {
        if (parseInt(badge.textContent) > 0) {
            badge.style.animation = 'pulse 2s ease-in-out infinite';
        }
    });

    // Add pulse animation to CSS dynamically if not exists
    if (!document.querySelector('#pulse-animation')) {
        const style = document.createElement('style');
        style.id = 'pulse-animation';
        style.textContent = `
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
        `;
        document.head.appendChild(style);
    }

    // Search enhancement - show recent searches (placeholder for future)
    const searchInput = document.querySelector('.search input');
    if (searchInput) {
        searchInput.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        searchInput.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    }

    // Loading state for menu items
    document.querySelectorAll('.menu-item[href]').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!this.classList.contains('dropdown-toggle') && 
                !this.getAttribute('href').startsWith('#') &&
                !this.getAttribute('href').startsWith('javascript:')) {
                // Add loading indicator
                const icon = this.querySelector('i:first-child');
                if (icon) {
                    icon.classList.add('fa-spin');
                }
            }
        });
    });

    // Smooth transitions for all interactive elements
    document.querySelectorAll('.icon-btn, .logout, .user').forEach(el => {
        el.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
    });

    // Console message for developers
    console.log('%c🎓 Buyoan National High School Admin Panel', 'color: #8A9A5B; font-size: 16px; font-weight: bold;');
    console.log('%cUI Enhanced with Professional Features', 'color: #22775e; font-size: 12px;');
    console.log('%c✓ Accessibility improvements\n✓ Keyboard navigation\n✓ Mobile responsive\n✓ Smooth animations', 'color: #6b7280; font-size: 11px;');
});
