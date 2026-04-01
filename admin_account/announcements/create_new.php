<?php
include '../../db_connection.php';
session_start();

// Get current author data
$current_author = get_author_data($conn);

// Get author data from admin session (matches admin_profile.php logic)
function get_author_data($conn)
{
    $user_id = null;
    if (isset($_SESSION['user_id'])) $user_id = (int)$_SESSION['user_id'];
    elseif (isset($_SESSION['admin_id'])) $user_id = (int)$_SESSION['admin_id'];

    if ($user_id) {
        $user_type = $_SESSION['user_type'] ?? 'admin';
        
        if ($user_type === 'admin') {
            $stmt = $conn->prepare("SELECT full_name, title, principal_title FROM admin WHERE id = ? LIMIT 1");
        } else {
            $stmt = $conn->prepare("SELECT full_name, title FROM sub_admin WHERE id = ? LIMIT 1");
        }
        
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return [
                    'name' => !empty($row['full_name']) ? $row['full_name'] : 'Administrator',
                    'type' => $user_type,
                    'title' => $row['title'] ?? 'School Administrator',
                    'principal_title' => $row['principal_title'] ?? ''
                ];
            }
            $stmt->close();
        }
    }
    // Final fallback
    return [
        'name' => isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Administrator',
        'type' => $_SESSION['user_type'] ?? 'admin',
        'title' => 'School Administrator',
        'principal_title' => ''
    ];
}

// Auto-generate short_description from content (max 200 chars, end with ellipsis)
function generate_short_description($content, $max = 200)
{
    $plain = strip_tags($content);
    $plain = preg_replace('/\s+/', ' ', trim($plain));
    if (mb_strlen($plain) <= $max) return $plain;
    $cut = mb_substr($plain, 0, $max);
    $last_space = mb_strrpos($cut, ' ');
    if ($last_space !== false) $cut = mb_substr($cut, 0, $last_space);
    return $cut . '...';
}

// Function to insert news
function insert_news($conn)
{
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $news_date = $_POST['news_date'];
    $author_name = $_POST['author_name'] ?? '';
    $author_position = $_POST['author_position'] ?? '';

    if (empty($news_date)) $news_date = date("Y-m-d");

    // Auto-generate short description from content
    $short_description = generate_short_description($content);

    // Get author from admin profile session
    $author_data = get_author_data($conn);
    $author = !empty($author_name) ? $author_name : $author_data['name'];
    
    // Combine author name and position for display
    if (!empty($author_position)) {
        $author .= ' - ' . $author_position;
    }

    // Handle multiple images — store as comma-separated filenames; first image is primary
    $images = [];
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/assets/img/blog/";
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // Debug: Check if files are being uploaded
    error_log("FILES array: " . print_r($_FILES, true));
    
    // Ensure directory exists and is writable
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
        error_log("Created directory: " . $target_dir);
    }
    
    if (!is_writable($target_dir)) {
        error_log("Directory is not writable: " . $target_dir);
        chmod($target_dir, 0755);
    }

    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        error_log("Processing images array upload");
        $file_count = count($_FILES['images']['name']);
        error_log("Found " . $file_count . " files to upload");
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['images']['error'][$i] == UPLOAD_ERR_OK) {
                error_log("Processing file " . $i . ": " . $_FILES['images']['name'][$i]);
                
                if (!in_array($_FILES['images']['type'][$i], $allowed_types)) {
                    error_log("Invalid file type: " . $_FILES['images']['type'][$i]);
                    continue;
                }
                if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) {
                    error_log("File too large: " . $_FILES['images']['size'][$i]);
                    continue;
                }
                
                $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                $filename = uniqid('news_', true) . '.' . $ext;
                $target_file = $target_dir . $filename;
                
                error_log("Target file: " . $target_file);
                
                if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $target_file)) {
                    $images[] = $filename;
                    error_log("Image uploaded successfully: " . $filename);
                } else {
                    error_log("Failed to upload image: " . $_FILES['images']['name'][$i] . " Error: " . $_FILES['images']['error'][$i]);
                }
            } else {
                error_log("Upload error for file " . $_FILES['images']['name'][$i] . ": " . $_FILES['images']['error'][$i]);
            }
        }
    }
    // Backward-compat: also check single 'image' field
    if (empty($images) && isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $filename = uniqid('news_', true) . '.' . $ext;
        $target_file = $target_dir . $filename;
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $images[] = $filename;
            error_log("Single image uploaded successfully: " . $filename);
        } else {
            error_log("Failed to upload single image: " . $_FILES["image"]["name"] . " Error: " . $_FILES["image"]["error"]);
        }
    }

    // Primary image for display (first one), extra images stored in extra_images column if it exists
    $image = !empty($images) ? $images[0] : '';
    $extra_images = count($images) > 1 ? implode(',', array_slice($images, 1)) : '';

    error_log("Images processed - Primary: " . $image . " Extra: " . $extra_images);

    // Auto-create extra_images column if it doesn't exist yet
    $conn->query("ALTER TABLE news ADD COLUMN IF NOT EXISTS extra_images VARCHAR(2000) DEFAULT ''");

    $stmt = $conn->prepare("INSERT INTO news (title, short_description, content, image, extra_images, category, news_date, author, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssssss", $title, $short_description, $content, $image, $extra_images, $category, $news_date, $author);
    $success = $stmt->execute();
    $news_id = $conn->insert_id;
    $stmt->close();
    
    if ($success) {
        error_log("News inserted successfully with image: " . $image);
        
        // Send email notifications to subscribers
        include __DIR__ . '/../../email_notification_functions.php';
        $notification_count = notify_subscribers_new_news($conn, $news_id);
        error_log("Email notifications sent to {$notification_count} subscribers for new news ID: {$news_id}");
    } else {
        error_log("Failed to insert news: " . $stmt->error);
    }
    
    return $success;
}

function delete_news($conn, $id)
{
    // First get the news post to retrieve image filenames
    $stmt = $conn->prepare("SELECT image, extra_images FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $news = $result->fetch_assoc();
    $stmt->close();
    
    if ($news) {
        // Delete primary image if it exists
        if (!empty($news['image'])) {
            $image_path = $_SERVER['DOCUMENT_ROOT'] . "/assets/img/blog/" . $news['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
                error_log("Deleted primary image: " . $image_path);
            }
        }
        
        // Delete extra images if they exist
        if (!empty($news['extra_images'])) {
            $extra_images = explode(',', $news['extra_images']);
            foreach ($extra_images as $extra_image) {
                $extra_image_path = $_SERVER['DOCUMENT_ROOT'] . "/assets/img/blog/" . trim($extra_image);
                if (file_exists($extra_image_path)) {
                    unlink($extra_image_path);
                    error_log("Deleted extra image: " . $extra_image_path);
                }
            }
        }
    }
    
    // Delete the news record from database
    $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action']) && $_POST['action'] == 'delete' && isset($_POST['id'])) {
            $success = delete_news($conn, $_POST['id']);
            header('Content-Type: application/json');
            echo json_encode($success
                ? ['status' => 'success', 'message' => 'News post deleted successfully!']
                : ['status' => 'error',   'message' => 'Error deleting news post.']);
        } else {
            $success = insert_news($conn);
            header('Content-Type: application/json');
            echo json_encode($success
                ? ['status' => 'success', 'message' => 'News post created successfully!']
                : ['status' => 'error',   'message' => 'Error creating news post.']);
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $success = insert_news($conn);
    if ($success) echo "<script>alert('News post created successfully!'); window.location.reload();</script>";
    else          echo "<script>alert('Error creating news post.');</script>";
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
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="../../assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="../../assets/css/main.css" rel="stylesheet">
</head>

<body>
    <div id="navigation-container"></div>

    <script>
        fetch('../admin_nav.php')
            .then(r => r.text())
            .then(data => {
                document.getElementById('navigation-container').innerHTML = data;
                const mainDiv = document.querySelector('.main');
                const pageContent = document.querySelector('.page-content');
                if (mainDiv && pageContent) mainDiv.appendChild(pageContent);
                fixAllNavLinks();
                initDropdowns();
            })
            .catch(e => console.error('Error loading navigation:', e));

        function getAdminBase() {
            const parts = window.location.pathname.split('/');
            const idx = parts.indexOf('admin_account');
            if (idx !== -1) return parts.slice(0, idx + 1).join('/') + '/';
            return window.location.pathname.split('/').slice(0, -1).join('/') + '/';
        }

        function fixAllNavLinks() {
            const adminBase = getAdminBase();
            document.querySelectorAll('.sidebar a[href], .topbar a[href], .user-menu a[href]').forEach(link => {
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('http') || href.startsWith('/')) return;
                if (href.startsWith('admin_account/')) link.setAttribute('href', adminBase + href.replace('admin_account/', ''));
                else if (!href.startsWith('../') && !href.startsWith('./')) link.setAttribute('href', adminBase + href);
            });
            document.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
                item.setAttribute('href', getAdminBase() + 'announcements/' + item.getAttribute('data-page'));
            });
        }

        function initDropdowns() {
            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                const fresh = toggle.cloneNode(true);
                toggle.parentNode.replaceChild(fresh, toggle);
                fresh.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const dropdown = this.closest('.dropdown');
                    const isActive = dropdown.classList.contains('active');
                    document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
                    if (!isActive) dropdown.classList.add('active');
                });
            });
            document.addEventListener('click', e => {
                if (!e.target.closest('.dropdown')) document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
            });
        }
    </script>

    <!-- Toast Container -->
    <div id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <main class="main page-content">

        <!-- Page Title -->
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">News Announcements</h1>
                            <p class="mb-0">Manage and publish school news announcements.</p>
                        </div>
                    </div>
                </div>
            </div>
            <nav class="breadcrumbs">
                <div class="container">
                    <ol>
                        <li><a href="../admin_dashboard.php">Home</a></li>
                        <li class="current">Create News</li>
                    </ol>
                </div>
            </nav>
        </div>

        <!-- Floating Create Button -->
        <div class="fab-container">
            <button class="fab-btn" data-bs-toggle="modal" data-bs-target="#createNewsModal" title="Create New Announcement">
                <i class="fas fa-plus fab-icon"></i>
                <span class="fab-label">New Post</span>
            </button>
        </div>

        <!-- ============================================================
             CREATE NEWS MODAL
        ============================================================ -->
        <div class="modal fade" id="createNewsModal" tabindex="-1" aria-labelledby="createNewsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">

                    <div class="modal-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="modal-icon-wrap">
                                <i class="fas fa-newspaper"></i>
                            </div>
                            <div>
                                <h5 class="modal-title mb-0" id="createNewsModalLabel">Create New Announcement</h5>
                                <p class="modal-subtitle mb-0">Fill in the details below</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Draft Restore Banner -->
                    <div id="draftRestoreBanner" class="draft-restore-banner" style="display:none;">
                        <i class="fas fa-history me-2"></i>
                        <span>You have an unsaved draft. <strong>Restore it?</strong></span>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn-restore" onclick="restoreDraft()">Restore</button>
                            <button class="btn-discard" onclick="discardDraft()">Discard</button>
                        </div>
                    </div>

                    <div class="modal-body p-0">


                        <!-- ── FORM ── -->
                        <div class="form-wrapper px-4 pt-3 pb-2">



                            <form action="" method="POST" enctype="multipart/form-data" id="newsForm" novalidate>

                                <!-- Row 1: Title -->
                                <div class="form-group-card mb-3">
                                    <label for="title" class="form-label required-label">
                                        <i class="fas fa-heading me-2 label-icon"></i>News Title
                                    </label>
                                    <input type="text" class="form-control" id="title" name="title"
                                        placeholder="Enter the news title here…" required>
                                    <div class="invalid-feedback" id="titleError">Title is required.</div>
                                </div>

                                <!-- Row 1.5: Written By -->
                                <div class="form-group-card mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user-pen me-2 label-icon"></i>Written By
                                    </label>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" id="author_name" name="author_name"
                                                placeholder="Enter author name..." required>
                                            <div class="field-meta mt-1">
                                                <span class="field-hint">Enter the author's name</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <select class="form-select" id="author_position" name="author_position">
                                                <option value="">Select Position...</option>
                                                <option value="Adviser">Adviser</option>
                                                <option value="Editor In Chief">Editor In Chief</option>
                                                <option value="Associate Editor">Associate Editor</option>
                                                <option value="Managing Editor">Managing Editor</option>
                                                <option value="News Editor">News Editor</option>
                                                <option value="Feature Editor">Feature Editor</option>
                                                <option value="Sports Editor">Sports Editor</option>
                                                <option value="Literary Editor">Literary Editor</option>
                                                <option value="Staff Writers">Staff Writers</option>
                                                <option value="Photojournalist">Photojournalist</option>
                                                <option value="Layout Artist">Layout Artist</option>
                                                <option value="Cartoonist">Cartoonist</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 2: Full Content -->
                                <div class="form-group-card mb-3">
                                    <label for="content" class="form-label required-label">
                                        <i class="fas fa-file-alt me-2 label-icon"></i>Full Article Content
                                    </label>
                                    <textarea class="form-control content-textarea" id="content" name="content"
                                        placeholder="Write the complete announcement here…" rows="8"></textarea>
                                    <div class="field-meta d-flex justify-content-between mt-1">
                                        <span class="field-hint" id="contentHint"></span>
                                    </div>
                                    <div class="invalid-feedback" id="contentError">Content is required.</div>
                                </div>

                                <!-- Row 4: Category + Date -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <div class="form-group-card">
                                            <label for="category" class="form-label required-label">
                                                <i class="fas fa-tag me-2 label-icon"></i>Category
                                            </label>
                                            <select class="form-select" id="category" name="category">
                                                <option value="">Choose a category…</option>
                                                <option>Education</option>
                                                <option>Politics</option>
                                                <option>Travel &amp; Tourism</option>
                                                <option>Technology</option>
                                                <option>Community Updates</option>
                                                <option>Emergency Notices</option>
                                                <option>Health &amp; Safety</option>
                                                <option>Public Service Information</option>
                                            </select>
                                            <div class="invalid-feedback" id="categoryError">Please select a category.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group-card">
                                            <label for="news_date" class="form-label">
                                                <i class="fas fa-calendar me-2 label-icon"></i>Publication Date
                                            </label>
                                            <input type="date" class="form-control" id="news_date" name="news_date">
                                            <div class="field-meta mt-1">
                                                <span class="field-hint">Leave blank to use today's date</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 5: Images Upload (multiple) -->
                                <div class="form-group-card mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-images me-2 label-icon"></i>Photos
                                    </label>
                                    <div class="multi-image-upload-zone" id="imageUploadZone">
                                        <div class="image-upload-placeholder" id="imagePlaceholder">
                                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                            <p class="upload-text">Drag &amp; drop photos here, or <label for="images" class="upload-link">browse files</label></p>
                                            <p class="upload-sub">JPEG, PNG, GIF, WebP · Max 5 MB each · First photo is the featured image</p>
                                        </div>
                                        <div class="multi-image-previews" id="multiImagePreviews" style="display:none;">
                                            <div class="preview-grid" id="previewGrid"></div>
                                            <label for="images" class="add-more-photos-btn">
                                                <i class="fas fa-plus me-1"></i>Add More Photos
                                            </label>
                                        </div>
                                        <input type="file" class="d-none" id="images" name="images[]" accept="image/*" multiple>
                                    </div>
                                    <div class="invalid-feedback d-block" id="imageError" style="display:none!important;"></div>
                                </div>

                            </form>
                        </div><!-- /form-wrapper -->

                    </div><!-- /modal-body -->

                    <div class="modal-footer justify-content-between">
                        <div class="footer-left d-flex align-items-center gap-2">
                            <span class="draft-indicator" id="draftIndicator" style="display:none;">
                                <i class="fas fa-circle-notch fa-spin me-1 text-muted" style="font-size:11px;"></i>
                                <span class="text-muted" style="font-size:12px;">Saving draft…</span>
                            </span>
                            <span class="draft-saved" id="draftSaved" style="display:none;">
                                <i class="fas fa-check-circle me-1" style="color:#4A5D23;font-size:11px;"></i>
                                <span style="font-size:12px;color:#4A5D23;">Draft saved</span>
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancel
                            </button>
                            <button type="submit" form="newsForm" class="btn btn-publish" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Publish Announcement
                            </button>
                        </div>
                    </div>

                </div><!-- /modal-content -->
            </div>
        </div>

        <!-- ============================================================
             DELETE CONFIRMATION MODAL (replaces native confirm())
        ============================================================ -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content delete-modal-content">
                    <div class="delete-modal-icon">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div class="delete-modal-body">
                        <h6>Delete this post?</h6>
                        <p id="deleteModalTitle" class="text-muted"></p>
                    </div>
                    <div class="delete-modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-sm btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal (submit) -->
        <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title"><i class="fas fa-check-circle text-success me-2"></i>Ready to Publish?</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <p class="text-muted mb-0">Your announcement will be published immediately and visible to all users. Review your content once more before confirming.</p>
                        <div class="confirm-summary mt-3" id="confirmSummary"></div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Go Back</button>
                        <button type="button" class="btn btn-publish btn-sm" id="confirmYes">
                            <i class="fas fa-paper-plane me-1"></i>Yes, Publish
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- News Hero Section -->
        <section id="news-hero" class="news-hero section">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-8"><?php include 'hero_dynamic.php'; ?></div>
                    <div class="col-lg-4">
                        <div class="news-tabs">
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#top-stories" type="button">Latest News</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#trending" type="button">Top Stories</button>
                                </li>
                            </ul>
                            <div class="tab-content"><?php include 'sidebar_dynamic.php'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- News Posts Section -->
        <section id="news-posts" class="news-posts section">
            <div class="container">
                <div class="row gy-5"><?php include 'news_posts_dynamic.php'; ?></div>
            </div>
        </section>

        <!-- Pagination -->
        <section id="pagination-2" class="pagination-2 section">
            <div class="container">
                <nav class="d-flex justify-content-center" aria-label="Page navigation">
                    <ul>
                        <li><a href="#" aria-label="Previous page"><i class="fa-solid fa-circle-chevron-left"></i><span class="d-none d-sm-inline">Previous</span></a></li>
                        <li><a href="#" class="active">1</a></li>
                        <li><a href="#">2</a></li>
                        <li><a href="#">3</a></li>
                        <li class="ellipsis">...</li>
                        <li><a href="#">8</a></li>
                        <li><a href="#">9</a></li>
                        <li><a href="#">10</a></li>
                        <li><a href="#" aria-label="Next page"><span class="d-none d-sm-inline">Next</span><i class="fa-solid fa-circle-chevron-right"></i></a></li>
                    </ul>
                </nav>
            </div>
        </section>

    </main>

    <!-- Vendor JS -->
    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/vendor/swiper/swiper-bundle.min.js"></script>
    <script src="../../assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../admin_assets/js/admin_script.js"></script>

    <script>
        // ============================================================
        // TOAST NOTIFICATIONS  (replaces all alert() calls)
        // ============================================================
        function showToast(message, type = 'success', duration = 4000) {
            const icons = {
                success: 'check-circle',
                error: 'times-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };
            const colors = {
                success: '#4A5D23',
                error: '#dc3545',
                warning: '#f59e0b',
                info: '#3b82f6'
            };
            const id = 'toast-' + Date.now();
            const html = `
            <div id="${id}" class="custom-toast custom-toast-${type}" role="alert" aria-live="assertive">
                <i class="fas fa-${icons[type] || 'info-circle'} toast-icon" style="color:${colors[type]}"></i>
                <span class="toast-msg">${message}</span>
                <button class="toast-close" onclick="dismissToast('${id}')"><i class="fas fa-times"></i></button>
                <div class="toast-progress" style="animation-duration:${duration}ms;background:${colors[type]}"></div>
            </div>`;
            document.getElementById('toastContainer').insertAdjacentHTML('beforeend', html);
            const el = document.getElementById(id);
            // trigger enter animation
            requestAnimationFrame(() => el.classList.add('toast-visible'));
            setTimeout(() => dismissToast(id), duration);
        }

        function dismissToast(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.classList.remove('toast-visible');
            el.classList.add('toast-exit');
            setTimeout(() => el.remove(), 350);
        }

        // ============================================================
        // CHARACTER / WORD COUNTERS  +  PROGRESS BAR
        // ============================================================
        const FIELDS = {
            title: {
                min: 1,
                max: 100
            },
            content: {
                min: 1,
                max: Infinity
            },
            category: {
                min: 1,
                max: Infinity
            }
        };

        function wordCount(str) {
            return str.trim() ? str.trim().split(/\s+/).length : 0;
        }

        function updateTitleCount() {
            const el = document.getElementById('title');
            const countEl = document.getElementById('titleCount');
            if (!el || !countEl) return;
            const len = el.value.length;
            countEl.textContent = len + ' / 100';
            validateField(el, len >= FIELDS.title.min && len <= FIELDS.title.max, 'titleError', 'titleHint');
            scheduleDraftSave();
        }

        function updateContentCount() {
            const el = document.getElementById('content');
            const countEl = document.getElementById('contentCount');
            const wEl = document.getElementById('wordCount');
            if (!el || !countEl || !wEl) return;
            const len = el.value.length;
            const words = wordCount(el.value);
            countEl.textContent = len + ' chars';
            wEl.textContent = words + ' word' + (words !== 1 ? 's' : '');
            validateField(el, len >= FIELDS.content.min, 'contentError', 'contentHint');
            scheduleDraftSave();
        }

        function validateField(el, valid, errorId, hintId) {
            if (!el) return;
            if (el.value.length === 0) {
                el.classList.remove('is-valid', 'is-invalid');
                const errEl = document.getElementById(errorId);
                if (errEl) errEl.style.display = 'none';
                return;
            }
            el.classList.toggle('is-invalid', !valid);
            el.classList.toggle('is-valid', valid);
            const errEl = document.getElementById(errorId);
            if (errEl) errEl.style.display = valid ? 'none' : 'block';
        }


        // ============================================================
        // AUTO-SAVE DRAFT  (localStorage)
        // ============================================================
        let draftTimer = null;

        function scheduleDraftSave() {
            clearTimeout(draftTimer);
            document.getElementById('draftIndicator').style.display = 'flex';
            document.getElementById('draftSaved').style.display = 'none';
            draftTimer = setTimeout(saveDraft, 1200);
        }

        function saveDraft() {
            const draft = {
                title: document.getElementById('title').value,
                content: document.getElementById('content').value,
                category: document.getElementById('category').value,
                news_date: document.getElementById('news_date').value,
                savedAt: new Date().toISOString()
            };
            localStorage.setItem('news_draft', JSON.stringify(draft));
            document.getElementById('draftIndicator').style.display = 'none';
            document.getElementById('draftSaved').style.display = 'flex';
        }

        function checkForDraft() {
            const raw = localStorage.getItem('news_draft');
            if (!raw) return;
            try {
                const draft = JSON.parse(raw);
                const hasContent = draft.title || draft.content;
                if (hasContent) {
                    document.getElementById('draftRestoreBanner').style.display = 'flex';
                }
            } catch (e) {}
        }

        function restoreDraft() {
            const draft = JSON.parse(localStorage.getItem('news_draft') || '{}');
            if (draft.title) document.getElementById('title').value = draft.title;
            if (draft.content) document.getElementById('content').value = draft.content;
            if (draft.category) document.getElementById('category').value = draft.category;
            if (draft.news_date) document.getElementById('news_date').value = draft.news_date;
            updateTitleCount();
            updateContentCount();
            document.getElementById('draftRestoreBanner').style.display = 'none';
            showToast('Draft restored successfully!', 'info');
        }

        function discardDraft() {
            localStorage.removeItem('news_draft');
            document.getElementById('draftRestoreBanner').style.display = 'none';
        }

        // ============================================================
        // MULTI-IMAGE UPLOAD  — drag & drop + sortable previews
        // ============================================================
        let uploadedFiles = []; // DataTransfer-backed file list

        function initImageUpload() {
            const zone = document.getElementById('imageUploadZone');
            const input = document.getElementById('images');

            zone.addEventListener('dragover', e => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });
            zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
            zone.addEventListener('drop', e => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                handleImageFiles(Array.from(e.dataTransfer.files));
            });
            zone.addEventListener('click', e => {
                if (e.target.closest('.preview-thumb') || e.target.closest('.add-more-photos-btn')) return;
                if (uploadedFiles.length === 0) input.click();
            });
            input.addEventListener('change', () => {
                if (input.files.length) handleImageFiles(Array.from(input.files));
                input.value = ''; // reset so same file can be re-added
            });
        }

        function handleImageFiles(files) {
            const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            let skipped = 0;
            files.forEach(file => {
                if (!allowed.includes(file.type)) {
                    skipped++;
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    showToast(`"${file.name}" exceeds 5 MB and was skipped.`, 'warning');
                    return;
                }
                uploadedFiles.push(file);
            });
            if (skipped) showToast(`${skipped} file(s) skipped — use JPEG, PNG, GIF, or WebP.`, 'warning');
            syncFilesToInput();
            renderPreviews();
        }

        function syncFilesToInput() {
            const input = document.getElementById('images');
            const dt = new DataTransfer();
            uploadedFiles.forEach(f => dt.items.add(f));
            input.files = dt.files;
        }

        function removeUploadedImage(idx) {
            uploadedFiles.splice(idx, 1);
            syncFilesToInput();
            renderPreviews();
        }

        function renderPreviews() {
            const placeholder = document.getElementById('imagePlaceholder');
            const previewsWrap = document.getElementById('multiImagePreviews');
            const grid = document.getElementById('previewGrid');

            if (uploadedFiles.length === 0) {
                placeholder.style.display = 'flex';
                previewsWrap.style.display = 'none';
                grid.innerHTML = '';
                return;
            }

            placeholder.style.display = 'none';
            previewsWrap.style.display = 'flex';
            grid.innerHTML = '';

            uploadedFiles.forEach((file, idx) => {
                const thumb = document.createElement('div');
                thumb.className = 'preview-thumb' + (idx === 0 ? ' is-featured' : '');
                thumb.dataset.idx = idx;

                const reader = new FileReader();
                reader.onload = e => {
                    thumb.innerHTML = `
                        <img src="${e.target.result}" alt="${file.name}">
                        ${idx === 0 ? '<span class="featured-badge"><i class="fas fa-star"></i> Featured</span>' : ''}
                        <span class="thumb-name">${file.name.length > 16 ? file.name.slice(0,14)+'…' : file.name}</span>
                        <button type="button" class="thumb-remove" onclick="removeUploadedImage(${idx})" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                };
                reader.readAsDataURL(file);
                grid.appendChild(thumb);
            });

            document.getElementById('imageError').style.display = 'none';
        }

        function removeImage() {
            uploadedFiles = [];
            syncFilesToInput();
            renderPreviews();
        }


        // ============================================================
        // FORM VALIDATION + SUBMISSION
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {

            initImageUpload();
            checkForDraft();

            // Live validation listeners
            document.getElementById('title').addEventListener('input', updateTitleCount);
            document.getElementById('content').addEventListener('input', updateContentCount);
            document.getElementById('category').addEventListener('change', () => {
                scheduleDraftSave();
            });

            // Clear draft + reset form on modal close
            document.getElementById('createNewsModal').addEventListener('hidden.bs.modal', function() {
                // Don't clear draft on close — let it persist for restore
            });

            // ── FORM SUBMIT ──
            const form = document.getElementById('newsForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Ensure files are properly synced before submission
                syncFilesToInput();

                const titleInput = document.getElementById('title');
                const contentInput = document.getElementById('content');
                const categorySelect = document.getElementById('category');

                let isValid = true;
                const checks = [
                    [titleInput, titleInput.value.length >= 1 && titleInput.value.length <= 100, 'titleError'],
                    [contentInput, contentInput.value.length >= 1, 'contentError'],
                    [categorySelect, !!categorySelect.value, 'categoryError'],
                ];

                checks.forEach(([el, valid, errId]) => {
                    el.classList.toggle('is-invalid', !valid);
                    el.classList.toggle('is-valid', valid);
                    document.getElementById(errId).style.display = valid ? 'none' : 'block';
                    if (!valid) isValid = false;
                });

                if (!isValid) {
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        firstInvalid.focus();
                    }
                    showToast('Please fix the highlighted fields before publishing.', 'error');
                    return;
                }

                // Show confirm modal with summary
                const summary = `
                <div class="confirm-row"><span class="confirm-label">Title:</span><span>${titleInput.value}</span></div>
                <div class="confirm-row"><span class="confirm-label">Category:</span><span>${categorySelect.value}</span></div>
            `;
                document.getElementById('confirmSummary').innerHTML = summary;
                new bootstrap.Modal(document.getElementById('confirmationModal')).show();

                document.getElementById('confirmYes').addEventListener('click', function() {
                    bootstrap.Modal.getInstance(document.getElementById('confirmationModal')).hide();
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Publishing…';

                    fetch('', {
                            method: 'POST',
                            body: new FormData(form),
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'success') {
                                bootstrap.Modal.getInstance(document.getElementById('createNewsModal'))?.hide();
                                form.reset();
                                uploadedFiles = [];
                                renderPreviews();
                                form.querySelectorAll('.is-valid, .is-invalid').forEach(el => el.classList.remove('is-valid', 'is-invalid'));
                                form.querySelectorAll('.invalid-feedback').forEach(el => el.style.display = 'none');
                                updateTitleCount();
                                updateContentCount();
                                document.getElementById('draftSaved').style.display = 'none';
                                localStorage.removeItem('news_draft');

                                // Refresh sections
                                fetch('hero_dynamic.php').then(r => r.text()).then(html => {
                                    document.querySelector('.col-lg-8').innerHTML = html;
                                });
                                fetch('news_posts_dynamic.php').then(r => r.text()).then(html => {
                                    document.querySelector('#news-posts .row.gy-5').innerHTML = html;
                                });
                                fetch('sidebar_dynamic.php').then(r => r.text()).then(html => {
                                    document.querySelector('.tab-content').innerHTML = html;
                                });

                                showToast('🎉 Announcement published successfully!', 'success', 5000);
                            } else {
                                showToast('Error: ' + data.message, 'error');
                            }
                        })
                        .catch(() => showToast('A network error occurred. Please try again.', 'error'))
                        .finally(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Publish Announcement';
                        });
                }, {
                    once: true
                });
            });

            // ── DELETE with modal ──
            let pendingDeleteId = null;

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.delete-post')) return;
                e.preventDefault();
                const btn = e.target.closest('.delete-post');
                pendingDeleteId = btn.getAttribute('data-id');
                const postTitle = btn.getAttribute('data-title') || 'this post';
                document.getElementById('deleteModalTitle').textContent = '"' + postTitle + '"';
                new bootstrap.Modal(document.getElementById('deleteModal')).show();
            });

            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                if (!pendingDeleteId) return;
                const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                modal.hide();
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting…';

                fetch('', {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'delete',
                            id: pendingDeleteId
                        }),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            fetch('hero_dynamic.php').then(r => r.text()).then(html => {
                                document.querySelector('.col-lg-8').innerHTML = html;
                            });
                            fetch('sidebar_dynamic.php').then(r => r.text()).then(html => {
                                document.querySelector('.tab-content').innerHTML = html;
                            });
                            fetch('news_posts_dynamic.php').then(r => r.text()).then(html => {
                                document.querySelector('#news-posts .row.gy-5').innerHTML = html;
                            });
                            showToast('Post deleted successfully.', 'success');
                        } else {
                            showToast('Error: ' + data.message, 'error');
                        }
                    })
                    .catch(() => showToast('A network error occurred while deleting.', 'error'))
                    .finally(() => {
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-trash me-1"></i>Delete';
                        pendingDeleteId = null;
                    });
            });
        });
    </script>

    <style>
        /* ============================================================
           MOSS GREEN THEME VARIABLES
        ============================================================ */
        :root {
            --mg-primary: #4A5D23;
            --mg-light: #6B7F3A;
            --mg-lighter: #7A8F4A;
            --mg-50: #f0f7e6;
            --mg-100: #e0edc8;
            --mg-200: #c8d8a8;
            --white: #FFFFFF;
            --gray-50: #F8F9FA;
            --gray-100: #F1F3F5;
            --gray-200: #E9ECEF;
            --gray-400: #CED4DA;
            --gray-600: #6C757D;
            --text-primary: #212529;
            --text-secondary: #6C757D;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .10);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, .14);
        }

        /* ============================================================
           TOAST NOTIFICATIONS
        ============================================================ */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
            max-width: 380px;
        }

        .custom-toast {
            background: #fff;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            padding: 13px 16px 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            pointer-events: all;
            opacity: 0;
            transform: translateX(30px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .custom-toast.toast-visible {
            opacity: 1;
            transform: translateX(0);
        }

        .custom-toast.toast-exit {
            opacity: 0;
            transform: translateX(30px);
        }

        .toast-icon {
            font-size: 16px;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .toast-msg {
            font-size: 13.5px;
            color: var(--text-primary);
            flex: 1;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--gray-600);
            font-size: 11px;
            padding: 2px;
            flex-shrink: 0;
            margin-left: auto;
        }

        .toast-close:hover {
            color: var(--text-primary);
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            border-radius: 0 0 0 var(--radius-md);
            animation: toastProgress linear forwards;
            opacity: 0.6;
        }

        @keyframes toastProgress {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }

        /* ============================================================
           FLOATING ACTION BUTTON
        ============================================================ */
        .fab-container {
            position: fixed;
            bottom: 32px;
            right: 32px;
            z-index: 1050;
        }

        .fab-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--mg-primary), var(--mg-light));
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px 22px 14px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 24px rgba(74, 93, 35, 0.45);
            transition: all 0.25s ease;
            letter-spacing: 0.2px;
        }

        .fab-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 32px rgba(74, 93, 35, 0.55);
        }

        .fab-icon {
            font-size: 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ============================================================
           MODAL IMPROVEMENTS
        ============================================================ */
        .modal-content {
            border-radius: var(--radius-lg);
            border: none;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            border-bottom: 1px solid var(--gray-200);
            padding: 1.25rem 1.75rem;
        }

        .modal-icon-wrap {
            width: 42px;
            height: 42px;
            background: var(--mg-50);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--mg-primary);
            font-size: 18px;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-subtitle {
            font-size: 12px;
            color: var(--gray-600);
        }

        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 1rem 1.75rem;
        }

        .footer-left {
            min-width: 120px;
        }

        .draft-indicator,
        .draft-saved {
            display: flex;
            align-items: center;
        }

        /* ── Draft Restore Banner ── */
        .draft-restore-banner {
            background: #fffbeb;
            border-bottom: 1px solid #fde68a;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #92400e;
        }

        .btn-restore {
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-discard {
            background: transparent;
            color: #92400e;
            border: 1px solid #fcd34d;
            border-radius: var(--radius-sm);
            padding: 4px 12px;
            font-size: 12px;
            cursor: pointer;
        }

        
        /* ============================================================
           FORM GROUP CARDS
        ============================================================ */
        .form-group-card {
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            transition: border-color 0.2s;
        }

        .form-group-card:focus-within {
            border-color: var(--mg-light);
            box-shadow: 0 0 0 3px rgba(74, 93, 35, 0.08);
        }

        .required-label::after {
            content: ' *';
            color: var(--mg-primary);
            font-weight: 600;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
        }

        .label-icon {
            color: var(--mg-light);
            font-size: 12px;
        }

        .form-control,
        .form-select {
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-sm);
            padding: .65rem .85rem;
            font-size: 14px;
            transition: border-color .2s, box-shadow .2s;
            background: var(--gray-50);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--mg-light);
            box-shadow: 0 0 0 3px rgba(74, 93, 35, 0.12);
            background: var(--white);
        }

        .form-control.is-valid,
        .form-select.is-valid {
            border-color: var(--mg-light);
        }

        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545;
        }

        .content-textarea {
            min-height: 160px;
            resize: vertical;
        }

        .field-meta {
            font-size: 12px;
            color: var(--gray-600);
        }

        .field-hint {
            color: var(--gray-600);
        }

        .char-count {
            font-weight: 600;
            color: var(--mg-primary);
        }

        .invalid-feedback {
            font-size: 12px;
            color: #dc3545;
            display: none;
            margin-top: 4px;
        }

        /* ============================================================
           IMAGE UPLOAD ZONE
        ============================================================ */
        /* ── Multi-Image Upload Zone ── */
        .multi-image-upload-zone {
            border: 2px dashed var(--gray-400);
            border-radius: var(--radius-md);
            background: var(--gray-50);
            transition: border-color .2s, background .2s;
            cursor: pointer;
            overflow: hidden;
            min-height: 130px;
        }

        .multi-image-upload-zone:hover,
        .multi-image-upload-zone.drag-over {
            border-color: var(--mg-light);
            background: var(--mg-50);
        }

        .image-upload-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 28px 20px;
            text-align: center;
        }

        .upload-icon {
            font-size: 32px;
            color: var(--gray-400);
            margin-bottom: 8px;
        }

        .multi-image-upload-zone:hover .upload-icon {
            color: var(--mg-light);
        }

        .upload-text {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .upload-link {
            color: var(--mg-primary);
            font-weight: 600;
            cursor: pointer;
            text-decoration: underline;
        }

        .upload-sub {
            font-size: 12px;
            color: var(--gray-600);
            margin: 0;
        }

        /* Preview grid */
        .multi-image-previews {
            display: flex;
            flex-direction: column;
            padding: 14px 14px 10px;
            gap: 10px;
            background: white;
        }

        .preview-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .preview-thumb {
            position: relative;
            width: 110px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid var(--gray-200);
            background: #fff;
            box-shadow: var(--shadow-sm);
            transition: border-color .2s;
        }

        .preview-thumb.is-featured {
            border-color: var(--mg-primary);
        }

        .preview-thumb img {
            width: 110px;
            height: 80px;
            object-fit: cover;
            display: block;
        }

        .featured-badge {
            position: absolute;
            top: 4px;
            left: 4px;
            background: var(--mg-primary);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .thumb-name {
            display: block;
            font-size: 10px;
            color: var(--text-secondary);
            padding: 4px 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .thumb-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(220, 53, 69, .85);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .15s;
        }

        .thumb-remove:hover {
            background: #dc3545;
        }

        .add-more-photos-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--mg-50);
            border: 1.5px dashed var(--mg-200);
            border-radius: 8px;
            color: var(--mg-primary);
            font-size: 12px;
            font-weight: 600;
            padding: 7px 14px;
            cursor: pointer;
            transition: background .15s;
            align-self: flex-start;
        }

        .add-more-photos-btn:hover {
            background: var(--mg-100);
        }


        /* ============================================================
           PUBLISH BUTTON
        ============================================================ */
        .btn-publish {
            background: linear-gradient(135deg, var(--mg-primary), var(--mg-light));
            border: none;
            color: white;
            font-weight: 700;
            padding: .65rem 1.4rem;
            border-radius: var(--radius-md);
            transition: all .2s;
            font-size: 14px;
        }

        .btn-publish:hover {
            background: linear-gradient(135deg, var(--mg-light), var(--mg-lighter));
            transform: translateY(-1px);
            box-shadow: 0 5px 16px rgba(74, 93, 35, .35);
            color: white;
        }

        .btn-publish:disabled {
            opacity: .65;
            cursor: not-allowed;
            transform: none;
        }

        /* ============================================================
           CONFIRM SUMMARY
        ============================================================ */
        .confirm-summary {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: 10px 14px;
        }

        .confirm-row {
            display: flex;
            gap: 10px;
            font-size: 13px;
            padding: 3px 0;
        }

        .confirm-label {
            font-weight: 600;
            color: var(--gray-600);
            min-width: 70px;
            flex-shrink: 0;
        }

        /* ============================================================
           DELETE MODAL
        ============================================================ */
        .delete-modal-content {
            border-radius: var(--radius-lg);
            text-align: center;
            padding: 0;
            overflow: hidden;
        }

        .delete-modal-icon {
            background: #fff5f5;
            padding: 24px;
            font-size: 28px;
            color: #dc3545;
        }

        .delete-modal-body {
            padding: 16px 24px 8px;
        }

        .delete-modal-body h6 {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .delete-modal-body p {
            font-size: 13px;
        }

        .delete-modal-footer {
            padding: 12px 24px 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .delete-btn {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            border-color: rgba(220, 53, 69, 0.2);
            transition: all 0.2s ease;
            position: relative;
            z-index: 1;
        }
        
        .delete-btn:hover {
            color: #fff;
            background: #dc3545;
            border-color: #dc3545;
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        .delete-btn:active {
            transform: scale(0.95);
        }

        .post-actions {
            margin-top: 8px;
            display: flex;
            justify-content: flex-end;
        }

        .post-actions .delete-btn {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
        }

        /* Specific styling for sidebar delete buttons */
        .tab-post .delete-btn {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
            padding: 2px 6px;
            font-size: 11px;
            border-radius: 3px;
            margin-left: auto;
        }

        .tab-post .delete-btn:hover {
            background: #dc3545;
            color: #fff;
            transform: scale(1.05);
        }

        /* Fix interaction bar layout */
        .interaction-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .interaction-btn {
            min-width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 14px;
            transition: all 0.2s ease;
            position: relative;
            z-index: 1;
        }

        .interaction-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }


        /* ============================================================
           MISC
        ============================================================ */
        .page-content {
            margin-left: 0;
            width: calc(100vw - 240px);
            max-width: 100%;
            padding: 0 20px;
        }

        @media (max-width: 768px) {
            .page-content {
                width: 100%;
            }

            .fab-btn .fab-label {
                display: none;
            }

            .fab-btn {
                padding: 14px;
                border-radius: 50%;
            }

            #toastContainer {
                max-width: calc(100vw - 40px);
            }
        }
    </style>

</body>

</html>