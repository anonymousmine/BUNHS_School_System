<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Buyoan National highschool</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicons -->
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">

    <style>
        /* Scoped CSS for News Modal */
        .news-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .news-modal {
            background: white;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #333;
        }

        .modal-image img {
            width: 100%;
            height: auto;
            border-radius: 8px 8px 0 0;
        }

        .modal-content {
            padding: 20px;
        }

        .modal-text {
            margin-bottom: 15px;
            font-size: 16px;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            padding: 10px 0;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            color: #65676b;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn:hover {
            color: #1877f2;
        }

        .comments-section {
            max-height: 300px;
            overflow-y: auto;
        }

        .comment-item {
            display: flex;
            margin-bottom: 15px;
            align-items: flex-start;
        }

        .comment-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0e0e0;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .comment-content {
            flex: 1;
        }

        .comment-user {
            font-weight: bold;
            font-size: 14px;
            color: #333;
        }

        .comment-text {
            font-size: 14px;
            margin: 5px 0;
        }

        .comment-meta {
            font-size: 12px;
            color: #65676b;
            display: flex;
            gap: 10px;
        }

        .comment-meta a {
            color: #65676b;
            text-decoration: none;
        }

        .comment-meta a:hover {
            text-decoration: underline;
        }

        .add-comment {
            display: flex;
            margin-top: 15px;
            border-top: 1px solid #e0e0e0;
            padding-top: 15px;
        }

        .add-comment input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
        }

        .add-comment button {
            background: #1877f2;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            margin-left: 10px;
            cursor: pointer;
        }

        .add-comment button:hover {
            background: #166fe5;
        }

        @media (max-width: 768px) {
            .news-modal {
                width: 95%;
                max-height: 90vh;
            }

            .modal-actions {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>

    <!-- =======================================================
  * Template Name: MySchool
  * Template URL: https://bootstrapmade.com/myschool-bootstrap-school-template/
  * Updated: Jul 28 2025 with Bootstrap v5.3.7
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
</head>

<?php include 'db_connection.php'; ?>

<body class="news-page">

    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">

            <a href="index.html" class="logo d-flex align-items-center">
                <!-- School Logo -->
                <img src="assets/img/Bagong_Pilipinas_logo.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 20px;">
                <img src="assets/img/DepED logo circle.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 0px;">
                <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 50px;">

                <!-- School Name -->
                <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
            </a>

            <div id="nav-placeholder"></div>

        </div>
    </header>

    <main class="main">

        <!-- Page Title -->
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">News</h1>
                            <p class="mb-0">
                                Here are the latest news and announcements that may influence activities and operations at Buyoan National High School.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="index.html">Home</a></li>
                        <li class="current">News</li>
                    </ol>
                </div>
            </nav>
        </div><!-- End Page Title -->

        <!-- News Hero Section -->
        <section id="news-hero" class="news-hero section">

            <div class="container">

                <div class="row g-4">
                    <!-- Main Content Area -->
                    <div class="col-lg-8">
                        <?php include 'news_hero_dynamic.php'; ?>
                    </div><!-- End Main Content Area -->

                    <!-- Sidebar with Tabs -->
                    <div class="col-lg-4">
                        <div class="news-tabs">
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#top-stories" type="button">Latest News</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#trending" type="button">Top stories</button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <?php include 'news_sidebar_dynamic.php'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section><!-- /News Hero Section -->

        <!-- News Posts Section -->
        <section id="news-posts" class="news-posts section">

            <div class="container">

                <div class="row gy-5">
                    <?php include 'news_posts_dynamic.php'; ?>
                </div>

            </div>

        </section><!-- /News Posts Section -->

        <!-- Pagination 2 Section -->
        <section id="pagination-2" class="pagination-2 section">

            <div class="container">
                <nav class="d-flex justify-content-center" aria-label="Page navigation">
                    <ul>
                        <li>
                            <a href="#" aria-label="Previous page">
                                <i class="bi bi-arrow-left"></i>
                                <span class="d-none d-sm-inline">Previous</span>
                            </a>
                        </li>

                        <li><a href="#" class="active">1</a></li>
                        <li><a href="#">2</a></li>
                        <li><a href="#">3</a></li>
                        <li class="ellipsis">...</li>
                        <li><a href="#">8</a></li>
                        <li><a href="#">9</a></li>
                        <li><a href="#">10</a></li>

                        <li>
                            <a href="#" aria-label="Next page">
                                <span class="d-none d-sm-inline">Next</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

        </section><!-- /Pagination 2 Section -->

    </main>

    <footer id="footer" class="footer-16 footer position-relative dark-background">

        <div class="container">

            <div class="footer-main">
                <div class="row align-items-start">

                    <div class="col-lg-5">
                        <div class="brand-section">
                            <a href="index.html" class="logo d-flex align-items-center mb-4">
                                <span class="sitename">MySchool</span>
                            </a>
                            <p class="brand-description">Crafting exceptional digital experiences through thoughtful design and innovative solutions that elevate your brand presence.</p>

                            <div class="contact-info mt-5">
                                <div class="contact-item">
                                    <i class="bi bi-geo-alt"></i>
                                    <span>123 Creative Boulevard, Design District, NY 10012</span>
                                </div>
                                <div class="contact-item">
                                    <i class="bi bi-telephone"></i>
                                    <span>+1 (555) 987-6543</span>
                                </div>
                                <div class="contact-item">
                                    <i class="bi bi-envelope"></i>
                                    <span>hello@designstudio.com</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="footer-nav-wrapper">
                            <div class="row">

                                <div class="col-6 col-lg-3">
                                    <div class="nav-column">
                                        <h6>Studio</h6>
                                        <nav class="footer-nav">
                                            <a href="#">Our Story</a>
                                            <a href="#">Design Process</a>
                                            <a href="#">Portfolio</a>
                                            <a href="#">Case Studies</a>
                                            <a href="#">Awards</a>
                                        </nav>
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <div class="nav-column">
                                        <h6>Services</h6>
                                        <nav class="footer-nav">
                                            <a href="#">Brand Identity</a>
                                            <a href="#">Web Design</a>
                                            <a href="#">Mobile Apps</a>
                                            <a href="#">Digital Strategy</a>
                                            <a href="#">Consultation</a>
                                        </nav>
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <div class="nav-column">
                                        <h6>Resources</h6>
                                        <nav class="footer-nav">
                                            <a href="#">Design Blog</a>
                                            <a href="#">Style Guide</a>
                                            <a href="#">Free Assets</a>
                                            <a href="#">Tutorials</a>
                                            <a href="#">Inspiration</a>
                                        </nav>
                                    </div>
                                </div>

                                <div class="col-6 col-lg-3">
                                    <div class="nav-column">
                                        <h6>Connect</h6>
                                        <nav class="footer-nav">
                                            <a href="#">Start Project</a>
                                            <a href="#">Schedule Call</a>
                                            <a href="#">Join Newsletter</a>
                                            <a href="#">Follow Updates</a>
                                            <a href="#">Partnership</a>
                                        </nav>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="footer-social">
                <div class="row align-items-center">

                    <div class="col-lg-6">
                        <div class="newsletter-section">
                            <h5>Stay Inspired</h5>
                            <p>Subscribe to receive design insights and creative inspiration delivered monthly.</p>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="social-section">
                            <div class="social-links">
                                <a href="#" aria-label="Dribbble" class="social-link">
                                    <i class="bi bi-dribbble"></i>
                                    <span>Dribbble</span>
                                </a>
                                <a href="#" aria-label="Behance" class="social-link">
                                    <i class="bi bi-behance"></i>
                                    <span>Behance</span>
                                </a>
                                <a href="#" aria-label="Instagram" class="social-link">
                                    <i class="bi bi-instagram"></i>
                                    <span>Instagram</span>
                                </a>
                                <a href="#" aria-label="LinkedIn" class="social-link">
                                    <i class="bi bi-linkedin"></i>
                                    <span>LinkedIn</span>
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <div class="footer-bottom">
            <div class="container">
                <div class="bottom-content">
                    <div class="row align-items-center">

                        <div class="col-lg-6">
                            <div class="copyright">
                                <p>© <span class="sitename">MyWebsite</span>. All rights reserved.</p>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="legal-links">
                                <a href="#">Privacy Policy</a>
                                <a href="#">Terms of Service</a>
                                <a href="#">Cookie Policy</a>
                                <div class="credits">
                                    <!-- All the links in the footer should remain intact. -->
                                    <!-- You can delete the links only if you've purchased the pro version. -->
                                    <!-- Licensing information: https://bootstrapmade.com/license/ -->
                                    <!-- Purchase the pro version with working PHP/AJAX contact form: [buy-url] -->
                                    Designed by <a href="https://bootstrapmade.com/">BootstrapMade</a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </footer>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Preloader -->
    <div id="preloader"></div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

    <!-- Include Navigation -->
    <script>
        fetch('nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('nav-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading navigation:', error));
    </script>

    <!-- Include Modals -->
    <script>
        fetch('modals.php')
            .then(response => response.text())
            .then(data => {
                document.body.insertAdjacentHTML('beforeend', data);
                // Add event listeners for login and signup buttons
                document.addEventListener('DOMContentLoaded', function() {
                    const loginBtn = document.querySelector('.btn-login');
                    const signupBtn = document.querySelector('.btn-signup');

                    if (loginBtn) {
                        loginBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                            loginModal.show();
                        });
                    }

                    if (signupBtn) {
                        signupBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
                            signupModal.show();
                        });
                    }
                });
            })
            .catch(error => console.error('Error loading modals:', error));
    </script>

    <!-- News Modal -->
    <div id="news-modal-overlay" class="news-modal-overlay" style="display: none;">
        <div class="news-modal">
            <button class="close-modal" onclick="closeNewsModal()">&times;</button>
            <div class="modal-image">
                <img id="modal-image" src="" alt="Post Image">
            </div>
            <div class="modal-content">
                <div class="modal-text" id="modal-text"></div>
                <div class="modal-actions">
                    <button class="action-btn" onclick="toggleLikeModal()">
                        <i class="fas fa-heart"></i> Like
                    </button>
                    <button class="action-btn" onclick="toggleCommentModal()">
                        <i class="fas fa-comment"></i> Comment
                    </button>
                    <button class="action-btn" onclick="shareModal()">
                        <i class="fas fa-share"></i> Share
                    </button>
                </div>
                <div class="comments-section">
                    <div id="comments-list" class="comments-list">
                        <!-- Comments will be populated here -->
                    </div>
                </div>
                <div class="add-comment">
                    <input type="text" id="comment-input" placeholder="Write a comment..." onkeypress="addCommentModal(event)">
                    <button onclick="submitCommentModal()">Post</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentPostId = null;
        let commentsData = {}; // In-memory comments storage

        function openNewsModal(postId) {
            currentPostId = postId;
            const postElement = document.querySelector(`.interaction-bar[data-post-id="${postId}"]`).closest('.post-box');
            const imageSrc = postElement.querySelector('.post-img img').src;
            const text = postElement.querySelector('.excerpt').textContent.trim();
            const title = postElement.querySelector('.post-title').textContent.trim();

            document.getElementById('modal-image').src = imageSrc;
            document.getElementById('modal-text').innerHTML = `<h3>${title}</h3><p>${text}</p>`;

            // Load comments
            loadCommentsModal();

            document.getElementById('news-modal-overlay').style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeNewsModal() {
            document.getElementById('news-modal-overlay').style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
            currentPostId = null;
        }

        function loadCommentsModal() {
            const commentsList = document.getElementById('comments-list');
            commentsList.innerHTML = '';

            if (!commentsData[currentPostId]) {
                commentsData[currentPostId] = [{
                        user: 'John Doe',
                        text: 'Great post!',
                        timestamp: '2 hours ago'
                    },
                    {
                        user: 'Jane Smith',
                        text: 'Thanks for sharing.',
                        timestamp: '1 hour ago'
                    }
                ];
            }

            commentsData[currentPostId].forEach(comment => {
                const commentItem = document.createElement('div');
                commentItem.className = 'comment-item';
                commentItem.innerHTML = `
          <div class="comment-avatar"></div>
          <div class="comment-content">
            <div class="comment-user">${comment.user}</div>
            <div class="comment-text">${comment.text}</div>
            <div class="comment-meta">
              <a href="#">Like</a>
              <a href="#">Reply</a>
              <span>${comment.timestamp}</span>
            </div>
          </div>
        `;
                commentsList.appendChild(commentItem);
            });
        }

        function addCommentModal(event) {
            if (event.key === 'Enter') {
                submitCommentModal();
            }
        }

        function submitCommentModal() {
            const input = document.getElementById('comment-input');
            const commentText = input.value.trim();
            if (commentText && currentPostId) {
                if (!commentsData[currentPostId]) {
                    commentsData[currentPostId] = [];
                }
                commentsData[currentPostId].push({
                    user: 'You', // Placeholder for current user
                    text: commentText,
                    timestamp: 'Just now'
                });
                input.value = '';
                loadCommentsModal();
            }
        }

        function toggleLikeModal() {
            // Placeholder for like functionality
            alert('Like functionality not implemented yet.');
        }

        function toggleCommentModal() {
            // Focus on comment input
            document.getElementById('comment-input').focus();
        }

        function shareModal() {
            // Placeholder for share functionality
            alert('Share functionality not implemented yet.');
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Attach click listeners to comment buttons
            document.querySelectorAll('.comment-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const postId = this.closest('.interaction-bar').dataset.postId;
                    openNewsModal(postId);
                });
            });
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && document.getElementById('news-modal-overlay').style.display === 'flex') {
                closeNewsModal();
            }
        });

        // Close modal on outside click
        document.getElementById('news-modal-overlay').addEventListener('click', function(event) {
            if (event.target === this) {
                closeNewsModal();
            }
        });
    </script>

</body>

</html>