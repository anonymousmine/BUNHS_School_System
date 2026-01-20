<?php
include '../../db_connection.php';

// Function to insert news
function insert_news($conn)
{
    $title = $_POST['title'];
    $short_description = $_POST['short_description'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $news_date = $_POST['news_date'];
    $author = $_POST['author'];

    // Set defaults
    if (empty($news_date)) {
        $news_date = date("Y-m-d");
    }
    if (empty($author)) {
        $author = "Unknown";
    }

    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../../assets/img/blog/";
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = basename($_FILES["image"]["name"]);
        }
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO news (title, short_description, content, image, category, news_date, author, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssss", $title, $short_description, $content, $image, $category, $news_date, $author);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Function to delete news
function delete_news($conn, $id)
{
    $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['id'])) {
            $success = delete_news($conn, $_POST['id']);
            header('Content-Type: application/json');
            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'News post deleted successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error deleting news post.']);
            }
        } else {
            $success = insert_news($conn);
            header('Content-Type: application/json');
            if ($success) {
                echo json_encode(['status' => 'success', 'message' => 'News post created successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error creating news post.']);
            }
        }
    }
    exit;
}

// Handle regular POST requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $success = insert_news($conn);
    if ($success) {
        echo "<script>alert('News post created successfully!'); window.location.reload();</script>";
    } else {
        echo "<script>alert('Error creating news post.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Announcement - School Admin Dashboard</title>
    <link rel="stylesheet" href="../admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <!-- Vendor CSS Files -->
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="../../assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

    <!-- Main CSS File -->
    <link href="../../assets/css/main.css" rel="stylesheet">
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

    <!-- main content -->
    <main class="main page-content">

        <!-- Page Title -->
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">Post Announcement</h1>
                            <p class="mb-0">
                                Fill in the details below to upload a news announcement.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="../admin_dashboard.php">Home</a></li>
                        <li class="current">Announcement</li>
                    </ol>
                </div>
            </nav>
        </div><!-- End Page Title -->

        <!-- Events 2 Section -->
        <section id="events-2" class="events-2 section">

            <div class="container">

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="events-list">
                            <div class="event-item">
                                <div class="event-date">
                                    <span class="day">15</span>
                                    <span class="month">JUN</span>
                                </div>
                                <div class="event-content">
                                    <h3>Annual Science Fair Exhibition</h3>
                                    <div class="event-meta">
                                        <p><i class="bi bi-clock"></i> 09:00 AM - 04:00 PM</p>
                                        <p><i class="bi bi-geo-alt"></i> Main Campus Auditorium</p>
                                    </div>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Proin gravida dolor sit amet lacus accumsan.</p>
                                    <a href="#" class="btn-event">Learn More <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div><!-- End Event Item -->

                            <div class="event-item">
                                <div class="event-date">
                                    <span class="day">22</span>
                                    <span class="month">JUN</span>
                                </div>
                                <div class="event-content">
                                    <h3>Parent-Teacher Conference</h3>
                                    <div class="event-meta">
                                        <p><i class="bi bi-clock"></i> 01:00 PM - 06:00 PM</p>
                                        <p><i class="bi bi-geo-alt"></i> Multiple Classrooms</p>
                                    </div>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo. Proin sagittis nisl rhoncus mattis rhoncus.</p>
                                    <a href="#" class="btn-event">Learn More <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div><!-- End Event Item -->

                            <div class="event-item">
                                <div class="event-date">
                                    <span class="day">30</span>
                                    <span class="month">JUN</span>
                                </div>
                                <div class="event-content">
                                    <h3>Summer Sports Tournament Final</h3>
                                    <div class="event-meta">
                                        <p><i class="bi bi-clock"></i> 02:30 PM - 05:30 PM</p>
                                        <p><i class="bi bi-geo-alt"></i> Sports Complex</p>
                                    </div>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Curabitur non nulla sit amet nisl tempus convallis quis ac lectus. Mauris blandit aliquet elit, eget tincidunt nibh pulvinar a.</p>
                                    <a href="#" class="btn-event">Learn More <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div><!-- End Event Item -->

                            <div class="event-item">
                                <div class="event-date">
                                    <span class="day">05</span>
                                    <span class="month">JUL</span>
                                </div>
                                <div class="event-content">
                                    <h3>Graduation Ceremony Class of 2023</h3>
                                    <div class="event-meta">
                                        <p><i class="bi bi-clock"></i> 10:00 AM - 01:00 PM</p>
                                        <p><i class="bi bi-geo-alt"></i> Central Auditorium</p>
                                    </div>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Pellentesque in ipsum id orci porta dapibus. Vivamus suscipit tortor eget felis porttitor volutpat. Vestibulum ante ipsum primis.</p>
                                    <a href="#" class="btn-event">Learn More <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div><!-- End Event Item -->
                        </div>

                        <div class="pagination-wrapper">
                            <ul class="pagination justify-content-center">
                                <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1"><i class="bi bi-chevron-left"></i></a></li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="sidebar">
                            <div class="sidebar-item">
                                <h3 class="sidebar-title">Upcoming Events</h3>
                                <div class="event-calendar">
                                    <div class="calendar-header">
                                        <h4>June 2023</h4>
                                    </div>
                                    <div class="calendar-body">
                                        <div class="weekdays">
                                            <div>Su</div>
                                            <div>Mo</div>
                                            <div>Tu</div>
                                            <div>We</div>
                                            <div>Th</div>
                                            <div>Fr</div>
                                            <div>Sa</div>
                                        </div>
                                        <div class="days">
                                            <div class="day other-month">28</div>
                                            <div class="day other-month">29</div>
                                            <div class="day other-month">30</div>
                                            <div class="day other-month">31</div>
                                            <div class="day">1</div>
                                            <div class="day">2</div>
                                            <div class="day">3</div>
                                            <div class="day">4</div>
                                            <div class="day">5</div>
                                            <div class="day">6</div>
                                            <div class="day">7</div>
                                            <div class="day">8</div>
                                            <div class="day">9</div>
                                            <div class="day">10</div>
                                            <div class="day">11</div>
                                            <div class="day">12</div>
                                            <div class="day">13</div>
                                            <div class="day">14</div>
                                            <div class="day has-event">15</div>
                                            <div class="day">16</div>
                                            <div class="day">17</div>
                                            <div class="day">18</div>
                                            <div class="day">19</div>
                                            <div class="day">20</div>
                                            <div class="day">21</div>
                                            <div class="day has-event">22</div>
                                            <div class="day">23</div>
                                            <div class="day">24</div>
                                            <div class="day">25</div>
                                            <div class="day">26</div>
                                            <div class="day">27</div>
                                            <div class="day">28</div>
                                            <div class="day">29</div>
                                            <div class="day has-event">30</div>
                                            <div class="day other-month">1</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="sidebar-item featured-event">
                                <h3 class="sidebar-title">Featured Event</h3>
                                <div class="featured-event-content">
                                    <img src="assets/img/education/events-5.webp" alt="Featured Event" class="img-fluid">
                                    <h4>Annual Arts Festival</h4>
                                    <p><i class="bi bi-calendar-event"></i> July 15-17, 2023</p>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Proin eget tortor risus consectetur adipiscing elit.</p>
                                    <a href="#" class="btn-register">Register Now</a>
                                </div>
                            </div>

                            <div class="sidebar-item">
                                <h3 class="sidebar-title">Event Categories</h3>
                                <div class="categories">
                                    <ul>
                                        <li><a href="#">Academic <span>(12)</span></a></li>
                                        <li><a href="#">Sports <span>(8)</span></a></li>
                                        <li><a href="#">Cultural <span>(6)</span></a></li>
                                        <li><a href="#">Workshops <span>(4)</span></a></li>
                                        <li><a href="#">Conferences <span>(3)</span></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </section><!-- /Events 2 Section -->

    </main>

    <!-- Vendor JS Files -->
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="../../assets/vendor/glightbox/js/glightbox.min.js"></script>
    <!-- Main JS File -->
    <script src="../../assets/js/main.js"></script>

    <script src="../admin_assets/js/admin_script.js"></script>

    <script>
        // Function to update content character count
        function updateContentCount() {
            const contentInput = document.getElementById('content');
            const contentCount = document.getElementById('contentCount');
            const length = contentInput.value.length;
            contentCount.textContent = length + ' characters (min 50)';
            if (length < 50) {
                contentInput.classList.add('is-invalid');
                contentInput.classList.remove('is-valid');
            } else {
                contentInput.classList.remove('is-invalid');
                contentInput.classList.add('is-valid');
            }
        }

        // Character count functions
        function updateTitleCount() {
            const titleInput = document.getElementById('title');
            const titleCount = document.getElementById('titleCount');
            const length = titleInput.value.length;
            titleCount.textContent = length + '/100';
        }

        function updateDescCount() {
            const shortDescInput = document.getElementById('short_description');
            const descCount = document.getElementById('descCount');
            const length = shortDescInput.value.length;
            descCount.textContent = length + '/200';
        }

        // Form validation and submission handling
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('newsForm');
            const submitBtn = document.getElementById('submitBtn');

            // Character count validation
            const titleInput = document.getElementById('title');
            const shortDescInput = document.getElementById('short_description');
            const contentInput = document.getElementById('content');
            const authorInput = document.getElementById('author');

            // Real-time validation using update functions
            titleInput.addEventListener('input', updateTitleCount);
            shortDescInput.addEventListener('input', updateDescCount);
            contentInput.addEventListener('input', updateContentCount);

            authorInput.addEventListener('input', function() {
                const length = this.value.length;
                if (length > 0 && length < 2) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else if (length === 0 || length >= 2) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });

            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                // Validate all fields before submission
                let isValid = true;

                // Title validation
                const titleLength = titleInput.value.length;
                if (titleLength < 5 || titleLength > 100) {
                    titleInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    titleInput.classList.remove('is-invalid');
                    titleInput.classList.add('is-valid');
                }

                // Short description validation
                const descLength = shortDescInput.value.length;
                if (descLength < 10 || descLength > 200) {
                    shortDescInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    shortDescInput.classList.remove('is-invalid');
                    shortDescInput.classList.add('is-valid');
                }

                // Content validation
                const contentLength = contentInput.value.length;
                if (contentLength < 50) {
                    contentInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    contentInput.classList.remove('is-invalid');
                    contentInput.classList.add('is-valid');
                }

                // Author validation (optional but if provided, must be at least 2 chars)
                const authorLength = authorInput.value.length;
                if (authorLength > 0 && authorLength < 2) {
                    authorInput.classList.add('is-invalid');
                    isValid = false;
                } else if (authorLength === 0 || authorLength >= 2) {
                    authorInput.classList.remove('is-invalid');
                    if (authorLength >= 2) authorInput.classList.add('is-valid');
                }

                // Category validation
                const categorySelect = document.getElementById('category');
                if (!categorySelect.value) {
                    categorySelect.classList.add('is-invalid');
                    isValid = false;
                } else {
                    categorySelect.classList.remove('is-invalid');
                    categorySelect.classList.add('is-valid');
                }

                if (!isValid) {
                    // Scroll to first invalid field
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        firstInvalid.focus();
                    }
                    return false;
                }

                // Show confirmation modal
                const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
                confirmationModal.show();

                // Handle confirmation
                document.getElementById('confirmYes').addEventListener('click', function() {
                    confirmationModal.hide();

                    // Show loading state
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';

                    // Create FormData for AJAX submission
                    const formData = new FormData(form);

                    // Send AJAX request
                    fetch('', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                // Success: close modal, reset form, show success message, reload content
                                const modal = bootstrap.Modal.getInstance(document.getElementById('createNewsModal'));
                                modal.hide();
                                form.reset();
                                // Reset validation states
                                form.querySelectorAll('.is-valid, .is-invalid').forEach(el => {
                                    el.classList.remove('is-valid', 'is-invalid');
                                });
                                updateTitleCount();
                                updateDescCount();
                                updateContentCount();
                                // Reload the hero section to show new news
                                fetch('hero_dynamic.php')
                                    .then(response => response.text())
                                    .then(html => {
                                        document.querySelector('.col-lg-8').innerHTML = html;
                                    })
                                    .catch(error => console.error('Error reloading hero content:', error));

                                // Reload the news posts section to show new news
                                fetch('news_posts_dynamic.php')
                                    .then(response => response.text())
                                    .then(html => {
                                        document.querySelector('#news-posts .row.gy-5').innerHTML = html;
                                    })
                                    .catch(error => console.error('Error reloading news posts content:', error));
                                // Optional: show success toast or alert
                                alert('News post created successfully!');
                            } else {
                                // Error: show error message
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while creating the news post.');
                        })
                        .finally(() => {
                            // Reset button state
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Create Announcement';
                        });
                }, {
                    once: true
                }); // Use once to avoid multiple event listeners

                return; // Prevent further execution until confirmation
            });

            // File upload preview (optional enhancement)
            const imageInput = document.getElementById('image');
            imageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Basic file validation
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    const maxSize = 5 * 1024 * 1024; // 5MB

                    if (!allowedTypes.includes(file.type)) {
                        alert('Please select a valid image file (JPEG, PNG, GIF, or WebP).');
                        this.value = '';
                        return;
                    }

                    if (file.size > maxSize) {
                        alert('File size must be less than 5MB.');
                        this.value = '';
                        return;
                    }
                }
            });

            // Delete post functionality
            document.addEventListener('click', function(e) {
                if (e.target.closest('.delete-post')) {
                    e.preventDefault();
                    const button = e.target.closest('.delete-post');
                    const postId = button.getAttribute('data-id');

                    if (confirm('Are you sure you want to delete this news post?')) {
                        // Send delete request
                        fetch('', {
                                method: 'POST',
                                body: new URLSearchParams({
                                    'action': 'delete',
                                    'id': postId
                                }),
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                }
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    // Reload the hero section and sidebar to reflect changes
                                    fetch('hero_dynamic.php')
                                        .then(response => response.text())
                                        .then(html => {
                                            document.querySelector('.col-lg-8').innerHTML = html;
                                        })
                                        .catch(error => console.error('Error reloading hero content:', error));

                                    fetch('sidebar_dynamic.php')
                                        .then(response => response.text())
                                        .then(html => {
                                            document.querySelector('.tab-content').innerHTML = html;
                                        })
                                        .catch(error => console.error('Error reloading sidebar content:', error));

                                    // Reload the news posts section to reflect changes
                                    fetch('news_posts_dynamic.php')
                                        .then(response => response.text())
                                        .then(html => {
                                            document.querySelector('#news-posts .row.gy-5').innerHTML = html;
                                        })
                                        .catch(error => console.error('Error reloading news posts content:', error));

                                    alert('News post deleted successfully!');
                                } else {
                                    alert('Error: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred while deleting the news post.');
                            });
                    }
                }
            });
        });
    </script>

    <style>
        /* Moss Green Theme Variables */
        :root {
            --moss-green-primary: #4A5D23;
            /* hsl(75, 35%, 25%) */
            --moss-green-light: #6B7F3A;
            /* hsl(75, 35%, 35%) */
            --moss-green-lighter: #7A8F4A;
            /* hsl(75, 35%, 45%) */
            --moss-green-lightest: #8A9F5A;
            /* hsl(75, 35%, 48%) */
            --white: #FFFFFF;
            --gray-light: #F8F9FA;
            --text-primary: #212529;
            --text-secondary: #6C757D;
        }

        /* Modal Content Background */
        .modal-content {
            background-color: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        /* Form Labels - Highlight Primary Information */
        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-label::after {
            content: " *";
            color: var(--moss-green-primary);
            font-weight: 500;
        }

        /* Form Controls */
        .form-control,
        .form-select {
            border: 2px solid #E9ECEF;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--moss-green-light);
            box-shadow: 0 0 0 0.2rem rgba(74, 93, 35, 0.25);
        }

        /* Form Validation Styles */
        .form-control.is-valid {
            border-color: var(--moss-green-light);
            box-shadow: 0 0 0 0.2rem rgba(74, 93, 35, 0.25);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        /* Character Count Styling */
        .char-count {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        /* Form Text */
        .form-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--moss-green-primary);
            border-color: var(--moss-green-primary);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--moss-green-light);
            border-color: var(--moss-green-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 93, 35, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background-color: var(--gray-light);
            border-color: #DEE2E6;
            color: var(--text-primary);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
        }

        .btn-secondary:hover {
            background-color: #E9ECEF;
            border-color: #ADB5BD;
        }

        /* Visual Hierarchy - Group Fields */
        .row.g-3>.col-12,
        .row.g-3>.col-md-6 {
            margin-bottom: 1.5rem;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid rgba(74, 93, 35, 0.1);
        }

        /* Section Borders for Hierarchy */
        .modal-body {
            padding: 2rem;
        }

        .modal-header {
            border-bottom: 1px solid #E9ECEF;
            padding: 1.5rem 2rem;
        }

        .modal-footer {
            border-top: 1px solid #E9ECEF;
            padding: 1.5rem 2rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {

            .modal-body,
            .modal-header,
            .modal-footer {
                padding: 1rem;
            }

            .form-control,
            .form-select {
                padding: 0.5rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Highlight Required Fields */
        .form-label[for="title"],
        .form-label[for="short_description"],
        .form-label[for="content"],
        .form-label[for="category"] {
            position: relative;
        }

        .form-label[for="title"]::before,
        .form-label[for="short_description"]::before,
        .form-label[for="content"]::before,
        .form-label[for="category"]::before {
            content: "";
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            background-color: var(--moss-green-primary);
            border-radius: 50%;
        }

        /* Adjust main-page-content positioning */
        .page-content {
            margin-left: 0;
            width: 100vw;
        }

        /* Center the no news messages */
        .text-muted {
            text-align: center !important;
        }
    </style>
</body>

</html>