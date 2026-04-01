<?php

/**
 * Student Document Requests API
 * Handles student document download and request functionality
 */

session_start();
include '../../db_connection.php';

// Set response type to JSON
header('Content-Type: application/json');

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$student_id = $_SESSION['user_id'];

// Handle the request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // Get available documents (for direct download + requestable)
    case 'get_available_documents':
        getAvailableDocuments($conn);
        break;

    // Submit a document request
    case 'submit_request':
        submitRequest($conn, $student_id);
        break;

    // Get student's request history
    case 'get_my_requests':
        getMyRequests($conn, $student_id);
        break;

    // Cancel a pending request
    case 'cancel_request':
        cancelRequest($conn, $student_id);
        break;

    // Download direct document
    case 'download_document':
        downloadDocument($conn, $student_id);
        break;

    // Get document details
    case 'get_document_details':
        getDocumentDetails($conn);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

/**
 * Get available documents (both direct download and requestable)
 */
function getAvailableDocuments($conn)
{
    $type = $_GET['type'] ?? 'all'; // 'all', 'direct', 'requestable'

    $sql = "SELECT * FROM documents WHERE is_active = 1";

    if ($type === 'direct') {
        $sql .= " AND requires_approval = 0";
    } elseif ($type === 'requestable') {
        $sql .= " AND requires_approval = 1";
    }

    $sql .= " ORDER BY category, title";

    $result = $conn->query($sql);
    $documents = [];

    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => $documents]);
}

/**
 * Submit a new document request
 */
function submitRequest($conn, $student_id)
{
    $document_id = intval($_POST['document_id'] ?? 0);
    $request_type = $_POST['request_type'] ?? 'new_copy';
    $purpose = trim($_POST['purpose'] ?? '');

    // Validation
    if (empty($document_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a document']);
        return;
    }

    // Check if document exists and requires approval
    $stmt = $conn->prepare("SELECT id, title, requires_approval FROM documents WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result->num_rows) {
        echo json_encode(['status' => 'error', 'message' => 'Document not found']);
        $stmt->close();
        return;
    }

    $doc = $result->fetch_assoc();
    $stmt->close();

    if (!$doc['requires_approval']) {
        echo json_encode(['status' => 'error', 'message' => 'This document is available for direct download']);
        return;
    }

    // Check if there's already a pending request for this document
    $stmt = $conn->prepare("SELECT id FROM document_requests WHERE student_id = ? AND document_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $student_id, $document_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You already have a pending request for this document']);
        $stmt->close();
        return;
    }
    $stmt->close();

    // Validate request type
    $allowed_types = ['new_copy', 'verification', 'official_use'];
    if (!in_array($request_type, $allowed_types)) {
        $request_type = 'new_copy';
    }

    // Insert the request
    $stmt = $conn->prepare("INSERT INTO document_requests (student_id, document_id, request_type, purpose, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iiss", $student_id, $document_id, $request_type, $purpose);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully! You will be notified once processed.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit request']);
    }

    $stmt->close();
}

/**
 * Get student's request history
 */
function getMyRequests($conn, $student_id)
{
    $status = $_GET['status'] ?? '';

    $sql = "SELECT dr.*, d.title as document_title, d.category, d.requires_approval
            FROM document_requests dr
            JOIN documents d ON dr.document_id = d.id
            WHERE dr.student_id = ?";

    $params = [$student_id];

    if (!empty($status)) {
        $sql .= " AND dr.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY dr.requested_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat("i", count($params)), ...$params);
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
 * Cancel a pending request
 */
function cancelRequest($conn, $student_id)
{
    $request_id = intval($_POST['request_id'] ?? 0);

    if (empty($request_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        return;
    }

    // Only allow canceling pending requests
    $stmt = $conn->prepare("UPDATE document_requests SET status = 'cancelled' WHERE id = ? AND student_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $request_id, $student_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Request cancelled successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Cannot cancel this request. It may already be processed.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to cancel request']);
    }

    $stmt->close();
}

/**
 * Handle document download
 */
function downloadDocument($conn, $student_id)
{
    $document_id = intval($_GET['document_id'] ?? 0);

    if (empty($document_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid document']);
        return;
    }

    // Get document info
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result->num_rows) {
        echo json_encode(['status' => 'error', 'message' => 'Document not found']);
        $stmt->close();
        return;
    }

    $doc = $result->fetch_assoc();
    $stmt->close();

    // Check if document requires approval
    if ($doc['requires_approval']) {
        // Check if student has an approved request
        $stmt = $conn->prepare("SELECT id FROM document_requests WHERE student_id = ? AND document_id = ? AND status = 'approved'");
        $stmt->bind_param("ii", $student_id, $document_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result->num_rows) {
            echo json_encode(['status' => 'error', 'message' => 'You need approval to download this document. Please submit a request first.']);
            $stmt->close();
            return;
        }
        $stmt->close();
    }

    // Check if file exists
    $file_path = '../../' . $doc['file_path'];
    if (!file_exists($file_path) || empty($doc['file_path'])) {
        echo json_encode(['status' => 'error', 'message' => 'File not found. Please contact administrator.']);
        return;
    }

    // Log the download
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO document_downloads (document_id, user_id, user_type, ip_address) VALUES (?, ?, 'student', ?)");
    $stmt->bind_param("iis", $document_id, $student_id, $ip_address);
    $stmt->execute();
    $stmt->close();

    // Increment download count
    $stmt = $conn->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id = ?");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $stmt->close();

    // Trigger file download
    $filename = $doc['original_filename'] ?: basename($doc['file_path']);

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));

    readfile($file_path);
    exit;
}

/**
 * Get document details
 */
function getDocumentDetails($conn)
{
    $document_id = intval($_GET['document_id'] ?? 0);

    if (empty($document_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid document']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($doc = $result->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'data' => $doc]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Document not found']);
    }

    $stmt->close();
}
