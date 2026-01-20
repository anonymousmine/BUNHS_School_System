<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>School Rating - MySchool Bootstrap Template</title>
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

    <!-- =======================================================
  * Template Name: MySchool
  * Template URL: https://bootstrapmade.com/myschool-bootstrap-school-template/
  * Updated: Jul 28 2025 with Bootstrap v5.3.7
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->

    <style>
        .rating-number {
            font-size: 4rem;
            font-weight: bold;
            color: #333;
        }

        .stars {
            font-size: 1.5rem;
            color: #ffc107;
            margin: 10px 0;
        }

        .review-count {
            font-size: 1.2rem;
            color: #666;
        }

        .rating-breakdown {
            margin-top: 30px;
        }

        .rating-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .star-label {
            width: 30px;
            font-weight: bold;
        }

        .progress {
            flex: 1;
            height: 8px;
            margin: 0 10px;
        }

        .progress-bar {
            background-color: #ffc107;
        }

        .percentage {
            width: 40px;
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>

<body class="campus-facilities-page">

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
                            <h1 class="heading-title">School Rating</h1>
                            <p class="mb-0">Esse dolorum voluptatum ullam est sint nemo et est ipsa porro placeat quibusdam quia assumenda numquam molestias.</p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="index.html">Home</a></li>
                        <li class="current">School Rating</li>
                    </ol>
                </div>
            </nav>
        </div><!-- End Page Title -->

        <!-- School Rating Section -->
        <section id="school-rating" class="school-rating section">

            <div class="container">

                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="rating-summary text-center">
                            <div class="overall-rating">
                                <span class="rating-number">4.0</span>
                                <div class="stars">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="far fa-star"></i>
                                </div>
                                <div class="review-count">
                                    <span>9,689 reviews</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="rating-breakdown">
                            <div class="rating-bar">
                                <span class="star-label">5★</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 60%;"></div>
                                </div>
                                <span class="percentage">60%</span>
                            </div>
                            <div class="rating-bar">
                                <span class="star-label">4★</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 25%;"></div>
                                </div>
                                <span class="percentage">25%</span>
                            </div>
                            <div class="rating-bar">
                                <span class="star-label">3★</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 10%;"></div>
                                </div>
                                <span class="percentage">10%</span>
                            </div>
                            <div class="rating-bar">
                                <span class="star-label">2★</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 3%;"></div>
                                </div>
                                <span class="percentage">3%</span>
                            </div>
                            <div class="rating-bar">
                                <span class="star-label">1★</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 2%;"></div>
                                </div>
                                <span class="percentage">2%</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </section><!-- /School Rating Section -->

        <!-- User Reviews Section -->
        <section id="user-reviews" class="user-reviews section">

            <div class="container">

                <div class="row justify-content-center">

                    <div class="col-lg-8">

                        <h2 class="text-center mb-4">User Reviews</h2>

                        <?php
                        // Static sample reviews data
                        $reviews = [
                            [
                                'name' => 'John Doe',
                                'rating' => 5,
                                'date' => '2023-10-15',
                                'text' => 'Excellent school with great teachers and facilities. Highly recommended!',
                                'helpful' => 2019
                            ],
                            [
                                'name' => 'Jane Smith',
                                'rating' => 4,
                                'date' => '2023-09-22',
                                'text' => 'Good overall experience, but could improve on extracurricular activities.',
                                'helpful' => 1543
                            ],
                            [
                                'name' => 'Mike Johnson',
                                'rating' => 3,
                                'date' => '2023-08-10',
                                'text' => 'Average school. Some subjects are well-taught, others need improvement.',
                                'helpful' => 987
                            ],
                            [
                                'name' => 'Emily Davis',
                                'rating' => 5,
                                'date' => '2023-07-05',
                                'text' => 'Amazing community and supportive staff. My child loves it here!',
                                'helpful' => 3124
                            ],
                            [
                                'name' => 'Robert Wilson',
                                'rating' => 4,
                                'date' => '2023-06-18',
                                'text' => 'Solid education with a focus on student development.',
                                'helpful' => 876
                            ]
                        ];

                        foreach ($reviews as $review) {
                            echo '<div class="review-item mb-4 p-3 border rounded">';
                            echo '<div class="review-header d-flex justify-content-between align-items-center mb-2">';
                            echo '<div class="reviewer-info">';
                            echo '<strong>' . htmlspecialchars($review['name']) . '</strong>';
                            echo '<div class="stars mt-1">';
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $review['rating']) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            echo '</div>';
                            echo '</div>';
                            echo '<small class="text-muted">' . htmlspecialchars($review['date']) . '</small>';
                            echo '</div>';
                            echo '<p class="review-text mb-2">' . htmlspecialchars($review['text']) . '</p>';
                            echo '<div class="review-footer d-flex justify-content-between align-items-center">';
                            echo '<small class="text-muted">' . number_format($review['helpful']) . ' people found this helpful</small>';
                            echo '<div class="helpful-buttons">';
                            echo '<span class="me-2">Was this review helpful?</span>';
                            echo '<button class="btn btn-sm btn-outline-primary me-1">Yes</button>';
                            echo '<button class="btn btn-sm btn-outline-secondary">No</button>';
                            echo '</div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>

                    </div>

                </div>

            </div>

        </section><!-- /User Reviews Section -->

    </main>

    <footer id="footer" class="footer-16 footer position-relative dark-background">

        <div class="container">

            <div class="footer-main">
                <div class="row align-items-start">

                    <div class="col-lg-5">
                        <div class="brand-section">
                            <a href="index.html" class="logo d-flex align-items-center mb-4">
                                <span class="sitename">Buyoan National HighSchool</span>
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

</body>

</html>