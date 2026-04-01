<?php

/**
 * Forms/Documents Management API
 * Handles all AJAX requests for forms management in admin panel
 */

session_start();
include '../../db_connection.php';

// Set response type to JSON
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'sub-admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Handle the request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ========== DOCUMENT MANAGEMENT ==========

    case 'get_documents':
        getDocuments($conn);
        break;

    case 'add_document':
        addDocument($conn);
        break;

    case 'update_document':
        updateDocument($conn);
        break;

    case 'delete_document':
        deleteDocument($conn);
        break;

    case 'toggle_document_status':
        toggleDocumentStatus($conn);
        break;

    // ========== REQUEST MANAGEMENT ==========

    case 'get_requests':
        getRequests($conn);
        break;

    case 'process_request':
        processRequest($conn);
        break;

    case 'get_request_details':
        getRequestDetails($conn);
        break;

    // ========== DOWNLOAD HISTORY ==========

    case 'get_download_history':
        getDownloadHistory($conn);
        break;

    // ========== STATISTICS ==========

    case 'get_statistics':
        getStatistics($conn);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

/**
 * optional filtering
 */
function getDocuments($conn)
{
    $category = $_POST['category'] ?? '';
    $requires_approval = $_POST['requires_approval'] ?? '';
    $search = $_POST['search'] ?? '';

    $sql = "SELECT * FROM documents WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }

    if ($requires_approval !== '') {
        $sql .= " AND requires_approval = ?";
        $params[] = $requires_approval;
        $types .= "i";
    }

    if (!empty($search)) {
        $sql .= " AND (title LIKE ? OR description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ss";
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $documents]);
    $stmt->close();
}

/**
 * Add a new document
 */
function addDocument($conn)
{
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;

    // Validation
    if (empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'Title is required']);
        return;
    }

    // Handle file upload
    $file_path = '';
    $original_filename = '';
    $file_type = '';
    $file_size = 0;

    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $upload_dir = '../../assets/forms/';

        // Create directory if not exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Get file info
        $original_filename = basename($_FILES['file']['name']);
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $file_type = $_FILES['file']['type'];
        $file_size = $_FILES['file']['size'];

        // Allowed extensions
        $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG']);
            return;
        }

        // Max file size: 10MB
        $max_size = 10 * 1024 * 1024;
        if ($file_size > $max_size) {
            echo json_encode(['status' => 'error', 'message' => 'File size exceeds 10MB limit']);
            return;
        }

        // Generate unique filename
        $new_filename = uniqid('doc_') . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
            $file_path = 'assets/forms/' . $new_filename;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload file']);
            return;
        }
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO documents (title, description, file_path, original_filename, file_type, file_size, category, requires_approval, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $uploaded_by = $_SESSION['user_id'];
    $stmt->bind_param("sssssssii", $title, $description, $file_path, $original_filename, $file_type, $file_size, $category, $requires_approval, $uploaded_by);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Document added successfully', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add document']);
    }

    $stmt->close();
}

/**
 * Update document metadata
 */
function updateDocument($conn)
{
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $requires_approval = isset($_POST['requires_approval']) ? 1 : 0;

    if (empty($id) || empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    // Check if file is being updated
    $file_path = $_POST['existing_file_path'] ?? '';

    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $upload_dir = '../../assets/forms/';

        // Get old file path to delete
        $stmt_old = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $result_old = $stmt_old->get_result();
        $old_doc = $result_old->fetch_assoc();
        $stmt_old->close();

        // Delete old file if exists
        if ($old_doc && $old_doc['file_path']) {
            $old_file = '../../' . $old_doc['file_path'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        // Upload new file
        $original_filename = basename($_FILES['file']['name']);
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $file_type = $_FILES['file']['type'];
        $file_size = $_FILES['file']['size'];

        $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

        if (!in_array($file_extension, $allowed_extensions)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file type']);
            return;
        }

        $new_filename = uniqid('doc_') . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
            $file_path = 'assets/forms/' . $new_filename;
        }
    }

    if (!empty($file_path)) {
        $stmt = $conn->prepare("UPDATE documents SET title = ?, description = ?, file_path = ?, original_filename = ?, file_type = ?, file_size = ?, category = ?, requires_approval = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssssssii", $title, $description, $file_path, $original_filename, $file_type, $file_size, $category, $requires_approval, $id);
    } else {
        $stmt = $conn->prepare("UPDATE documents SET title = ?, description = ?, category = ?, requires_approval = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssii", $title, $description, $category, $requires_approval, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Document updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update document']);
    }

    $stmt->close();
}

/**
 * Delete a document
 */
function deleteDocument($conn)
{
    $id = intval($_POST['id'] ?? 0);

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    // Get file path before deletion
    $stmt = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    $stmt->close();

    // Delete file from server
    if ($doc && $doc['file_path']) {
        $file_path = '../../' . $doc['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    // Delete from database
    $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Document deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete document']);
    }

    $stmt->close();
}

/**
 * Toggle document active status
 */
function toggleDocumentStatus($conn)
{
    $id = intval($_POST['id'] ?? 0);
    $current_status = intval($_POST['status'] ?? 1);
    $new_status = $current_status ? 0 : 1;

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    $stmt = $conn->prepare("UPDATE documents SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Status updated', 'new_status' => $new_status]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update status']);
    }

    $stmt->close();
}

/**
 * Get document requests with filtering
 */
function getRequests($conn)
{
    $status = $_POST['status'] ?? '';
    $search = $_POST['search'] ?? '';

    $sql = "SELECT dr.*, d.title as document_title, d.category, s.first_name, s.last_name, s.lrn, s.email, s.grade_level, s.section
            FROM document_requests dr
            JOIN documents d ON dr.document_id = d.id
            JOIN students s ON dr.student_id = s.id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($status)) {
        $sql .= " AND dr.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if (!empty($search)) {
        $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ? OR d.title LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }

    $sql .= " ORDER BY dr.requested_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $requests]);
    $stmt->close();
}

/**
 * Process a document request (approve/reject)
 */
function processRequest($conn)
{
    $request_id = intval($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? ''; // 'approve' or 'reject'
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if (empty($request_id) || empty($action)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    $status = ($action === 'approve') ? 'approved' : 'rejected';
    $processed_by = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE document_requests SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssii", $status, $admin_notes, $processed_by, $request_id);

    if ($stmt->execute()) {
        $message = ($action === 'approve') ? 'Request approved successfully' : 'Request rejected';
        echo json_encode(['status' => 'success', 'message' => $message]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to process request']);
    }

    $stmt->close();
}

/**
 * Get details of a specific request
 */
function getRequestDetails($conn)
{
    $request_id = intval($_POST['request_id'] ?? 0);

    if (empty($request_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    $sql = "SELECT dr.*, d.title as document_title, d.category, d.file_path, d.requires_approval,
            s.first_name, s.last_name, s.lrn, s.email, s.grade_level, s.section
            FROM document_requests dr
            JOIN documents d ON dr.document_id = d.id
            JOIN students s ON dr.student_id = s.id
            WHERE dr.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'data' => $row]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Request not found']);
    }

    $stmt->close();
}

/**
 * Get download history
 */
function getDownloadHistory($conn)
{
    $limit = intval($_GET['limit'] ?? 50);
    $document_id = intval($_GET['document_id'] ?? 0);

    $sql = "SELECT dd.*, d.title as document_title, d.category,
            CASE WHEN dd.user_type = 'student' THEN CONCAT(s.first_name, ' ', s.last_name) 
                 ELSE CONCAT(a.first_name, ' ', a.last_name) END as user_name
            FROM document_downloads dd
            JOIN documents d ON dd.document_id = d.id
            LEFT JOIN students s ON dd.user_id = s.id AND dd.user_type = 'student'
            LEFT JOIN admins a ON dd.user_id = a.id AND dd.user_type = 'admin'
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($document_id > 0) {
        $sql .= " AND dd.document_id = ?";
        $params[] = $document_id;
        $types .= "i";
    }

    $sql .= " ORDER BY dd.downloaded_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $history]);
    $stmt->close();
}

/**
 * Get statistics for dashboard
 */
function getStatistics($conn)
{
    // Total documents
    $result = $conn->query("SELECT COUNT(*) as total FROM documents WHERE is_active = 1");
    $total_docs = $result->fetch_assoc()['total'] ?? 0;

    // Documents requiring approval
    $result = $conn->query("SELECT COUNT(*) as total FROM documents WHERE is_active = 1 AND requires_approval = 1");
    $requires_approval = $result->fetch_assoc()['total'] ?? 0;

    // Documents for direct download
    $result = $conn->query("SELECT COUNT(*) as total FROM documents WHERE is_active = 1 AND requires_approval = 0");
    $direct_download = $result->fetch_assoc()['total'] ?? 0;

    // Pending requests
    $result = $conn->query("SELECT COUNT(*) as total FROM document_requests WHERE status = 'pending'");
    $pending_requests = $result->fetch_assoc()['total'] ?? 0;

    // Approved requests
    $result = $conn->query("SELECT COUNT(*) as total FROM document_requests WHERE status = 'approved'");
    $approved_requests = $result->fetch_assoc()['total'] ?? 0;

    // Rejected requests
    $result = $conn->query("SELECT COUNT(*) as total FROM document_requests WHERE status = 'rejected'");
    $rejected_requests = $result->fetch_assoc()['total'] ?? 0;

    // Total downloads
    $result = $conn->query("SELECT SUM(download_count) as total FROM documents");
    $total_downloads = $result->fetch_assoc()['total'] ?? 0;

    // Documents by category
    $result = $conn->query("SELECT category, COUNT(*) as count FROM documents WHERE is_active = 1 GROUP BY category");
    $by_category = [];
    while ($row = $result->fetch_assoc()) {
        $by_category[$row['category']] = $row['count'];
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_documents' => $total_docs,
            'requires_approval' => $requires_approval,
            'direct_download' => $direct_download,
            'pending_requests' => $pending_requests,
            'approved_requests' => $approved_requests,
            'rejected_requests' => $rejected_requests,
            'total_downloads' => $total_downloads,
            'by_category' => $by_category
        ]
    ]);
}
