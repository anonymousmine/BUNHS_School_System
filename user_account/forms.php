<?php
// Start session and check student authentication
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

include '../db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Forms & Documents - Student Portal</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8a9a5b;
            --secondary-color: #22775e;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --light-color: #f9fafb;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-color);
            color: var(--text-primary);
        }

        .student-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
        }

        .page-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 4px;
        }

        /* Sections */
        .section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-color);
        }

        /* Documents Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .document-card {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .document-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
            transform: scaleY(0);
            transition: transform 0.2s;
        }

        .document-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .document-card:hover::before {
            transform: scaleY(1);
        }

        .document-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 16px;
        }

        .document-icon.pdf {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .document-icon.doc {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .document-icon.image {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .document-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .document-desc {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .download-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 154, 91, 0.3);
        }

        .request-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--warning-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .request-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        /* Requests Table */
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }

        .requests-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            background: var(--light-color);
            border-bottom: 2px solid var(--border-color);
        }

        .requests-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .requests-table tbody tr:hover {
            background: rgba(138, 154, 91, 0.04);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #059669;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-badge.completed {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-badge.cancelled {
            background: #f3f4f6;
            color: #6b7280;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
            transform: scale(0.9);
            transition: all 0.3s;
        }

        .modal-overlay.active .modal {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(138, 154, 91, 0.08), rgba(34, 119, 94, 0.08));
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: var(--primary-color);
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 18px;
            color: var(--text-secondary);
        }

        .modal-close:hover {
            background: var(--light-color);
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--light-color);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(138, 154, 91, 0.3);
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 10000;
        }

        .toast {
            background: white;
            border-radius: 10px;
            padding: 16px 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            transform: translateX(120%);
            transition: all 0.3s;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--danger-color);
        }

        .toast i {
            font-size: 20px;
        }

        .toast.success i {
            color: var(--success-color);
        }

        .toast.error i {
            color: var(--danger-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 48px;
            color: var(--border-color);
            margin-bottom: 16px;
        }

        .empty-state h4 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0;
        }

        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .tab-btn:hover {
            color: var(--primary-color);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }

            .requests-table {
                font-size: 13px;
            }

            .requests-table th,
            .requests-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>

<body>
    <div id="nav-placeholder"></div>

    <div class="student-header">
        <div class="container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Forms & Documents</h1>
                    <p class="page-subtitle">Download forms or request official documents</p>
                </div>
            </div>
        </div>
    </div>

    <main class="container" style="margin-top: -20px;">
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="downloads">
                <i class="fas fa-download"></i> Quick Downloads
            </button>
            <button class="tab-btn" data-tab="requests">
                <i class="fas fa-clipboard-list"></i> Request Documents
            </button>
            <button class="tab-btn" data-tab="status">
                <i class="fas fa-clock"></i> My Request Status
            </button>
        </div>

        <!-- Quick Downloads Tab -->
        <div class="tab-content active" id="downloads">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-file-download"></i> Available for Direct Download
                    </h2>
                </div>
                <div class="documents-grid" id="direct-downloads">
                    <!-- Direct download documents will be loaded here -->
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading documents...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Request Documents Tab -->
        <div class="tab-content" id="requests">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-file-signature"></i> Documents Requiring Approval
                    </h2>
                </div>
                <div class="documents-grid" id="requestable-docs">
                    <!-- Requestable documents will be loaded here -->
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading documents...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Request Status Tab -->
        <div class="tab-content" id="status">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list-alt"></i> My Document Requests
                    </h2>
                    <select id="status-filter" onchange="loadMyRequests()" style="padding: 8px 12px; border: 2px solid var(--border-color); border-radius: 8px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="table-container">
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Type</th>
                                <th>Purpose</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="my-requests">
                            <!-- Requests will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Request Document Modal -->
    <div class="modal-overlay" id="requestModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-file-signature"></i> Request Document</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="requestForm">
                    <input type="hidden" id="req-document-id">
                    <div class="form-group">
                        <label>Document <span style="color: red;">*</span></label>
                        <input type="text" id="req-document-name" readonly style="background: var(--light-color);">
                    </div>
                    <div class="form-group">
                        <label>Request Type <span style="color: red;">*</span></label>
                        <select id="req-type" required>
                            <option value="">Select Type</option>
                            <option value="new_copy">New Copy</option>
                            <option value="verification">Verification</option>
                            <option value="official_use">Official Use</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Purpose</label>
                        <textarea id="req-purpose" rows="3" placeholder="Please specify the purpose of your request..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitRequest()">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load navigation
        fetch('../nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('nav-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading navigation:', error));

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initTabs();
            loadDirectDownloads();
            loadRequestableDocs();
            loadMyRequests();
        });

        function initTabs() {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');

                    // Update buttons
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    // Update content
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');
                });
            });
        }

        // Load Direct Download Documents
        function loadDirectDownloads() {
            fetch('api/document_requests_api.php?action=get_available_documents&type=direct')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderDirectDownloads(data.data);
                    }
                });
        }

        function renderDirectDownloads(docs) {
            const container = document.getElementById('direct-downloads');

            if (docs.length === 0) {
                container.innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-folder-open"></i>
                        <h4>No Documents Available</h4>
                        <p>There are no documents available for direct download at this time.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = docs.map(doc => `
                <div class="document-card">
                    <div class="document-icon ${getFileIconClass(doc.file_type)}">
                        <i class="fas ${getFileFaIcon(doc.file_type)}"></i>
                    </div>
                    <h4 class="document-title">${doc.title}</h4>
                    <p class="document-desc">${doc.description || 'No description available'}</p>
                    <button class="download-btn" onclick="downloadDocument(${doc.id})">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            `).join('');
        }

        // Load Requestable Documents
        function loadRequestableDocs() {
            fetch('api/document_requests_api.php?action=get_available_documents&type=requestable')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderRequestableDocs(data.data);
                    }
                });
        }

        function renderRequestableDocs(docs) {
            const container = document.getElementById('requestable-docs');

            if (docs.length === 0) {
                container.innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-folder-open"></i>
                        <h4>No Requestable Documents</h4>
                        <p>There are no documents requiring approval at this time.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = docs.map(doc => `
                <div class="document-card">
                    <div class="document-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <h4 class="document-title">${doc.title}</h4>
                    <p class="document-desc">${doc.description || 'No description available'}</p>
                    <button class="request-btn" onclick="openRequestModal(${doc.id}, '${doc.title}')">
                        <i class="fas fa-paper-plane"></i> Request
                    </button>
                </div>
            `).join('');
        }

        // Load My Requests
        function loadMyRequests() {
            const status = document.getElementById('status-filter').value;

            fetch(`api/document_requests_api.php?action=get_my_requests${status ? '&status=' + status : ''}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderMyRequests(data.data);
                    }
                });
        }

        function renderMyRequests(requests) {
            const tbody = document.getElementById('my-requests');

            if (requests.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <i class="fas fa-inbox" style="font-size: 32px; color: #ccc;"></i>
                            <p style="margin-top: 10px; color: #666;">You haven't submitted any document requests yet.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = requests.map(req => `
                <tr>
                    <td><strong>${req.document_title}</strong></td>
                    <td>${req.request_type.replace('_', ' ')}</td>
                    <td>${req.purpose ? req.purpose.substring(0, 50) + (req.purpose.length > 50 ? '...' : '') : '-'}</td>
                    <td>${formatDate(req.requested_at)}</td>
                    <td><span class="status-badge ${req.status}">${req.status}</span></td>
                    <td>
                        ${req.status === 'pending' ? `<button class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" onclick="cancelRequest(${req.id})">Cancel</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }

        // Download Document
        function downloadDocument(docId) {
            window.location.href = `api/document_requests_api.php?action=download_document&document_id=${docId}`;
        }

        // Request Modal
        function openRequestModal(docId, docTitle) {
            document.getElementById('req-document-id').value = docId;
            document.getElementById('req-document-name').value = docTitle;
            document.getElementById('requestModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('requestModal').classList.remove('active');
            document.getElementById('requestForm').reset();
        }

        // Submit Request
        function submitRequest() {
            const documentId = document.getElementById('req-document-id').value;
            const requestType = document.getElementById('req-type').value;
            const purpose = document.getElementById('req-purpose').value;

            if (!requestType) {
                showToast('Please select a request type', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'submit_request');
            formData.append('document_id', documentId);
            formData.append('request_type', requestType);
            formData.append('purpose', purpose);

            fetch('api/document_requests_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast(data.message, 'success');
                        closeModal();
                        loadMyRequests();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
        }

        // Cancel Request
        function cancelRequest(requestId) {
            if (!confirm('Are you sure you want to cancel this request?')) return;

            fetch('api/document_requests_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=cancel_request&request_id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Request cancelled successfully', 'success');
                        loadMyRequests();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
        }

        // Utilities
        function getFileIconClass(fileType) {
            if (!fileType) return 'doc';
            if (fileType.includes('pdf')) return 'pdf';
            if (fileType.includes('image')) return 'image';
            return 'doc';
        }

        function getFileFaIcon(fileType) {
            if (!fileType) return 'fa-file';
            if (fileType.includes('pdf')) return 'fa-file-pdf';
            if (fileType.includes('image')) return 'fa-file-image';
            if (fileType.includes('word') || fileType.includes('document')) return 'fa-file-word';
            return 'fa-file';
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                <span>${message}</span>
            `;
            container.appendChild(toast);

            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Close modal on overlay click
        document.getElementById('requestModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>

</html>