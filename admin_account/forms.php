<?php
// Start session and check admin authentication
require_once '../session_config.php';
$is_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['admin', 'sub-admin']))
    || (isset($_SESSION['admin_id']));
if (!$is_logged_in) {
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
    <title>Forms & Documents - School Admin Dashboard</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="../overall_body.css">
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
            margin-top: 0;
            margin-right: 0;
            margin-bottom: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--light-color);
            color: var(--text-primary);
            line-height: 1.5;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Main layout — .main is opened by admin_nav.php */
        .main {
            margin-left: 240px;
            min-height: 100vh;
            width: calc(100vw - 240px);
            box-sizing: border-box;
            flex: 1;
        }

        .forms-page {
            padding: 24px 0 24px 0;
            width: 100%;
            overflow-x: hidden;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            padding: 20px 20px 20px 0;
        }

        .heading {
            padding: 0;
            text-align: left;
        }
        
        .heading-title {
            margin: 0;
            padding: 0;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
            width: 100%;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            border-radius: 12px 0 0 12px;
        }

        .stat-card.blue::before {
            background: var(--info-color);
        }

        .stat-card.green::before {
            background: var(--success-color);
        }

        .stat-card.orange::before {
            background: var(--warning-color);
        }

        .stat-card.purple::before {
            background: #8b5cf6;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-content h3 {
            font-size: 24px;
            font-weight: 700;
        }

        .stat-content p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Tab Navigation */
        .tabs-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .tabs-header {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
        }

        .tab-btn {
            padding: 16px 24px;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid transparent;
        }

        .tab-btn:hover {
            color: var(--primary-color);
            background: rgba(138, 154, 91, 0.05);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-btn .badge {
            background: var(--danger-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .tab-content {
            padding: 24px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .search-box {
            position: relative;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-select {
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .btn {
            padding: 10px 16px;
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

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        /* Documents Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .document-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.2s;
        }

        .document-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .document-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
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

        .document-actions {
            display: flex;
            gap: 8px;
        }

        .document-actions button {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            background: var(--light-color);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .document-actions button:hover {
            background: var(--primary-color);
            color: white;
        }

        .document-actions button.delete:hover {
            background: var(--danger-color);
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
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .document-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
        }

        .category-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            text-transform: capitalize;
        }

        .category-badge.enrollment {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .category-badge.registration {
            background: #d1fae5;
            color: #059669;
        }

        .category-badge.clearance {
            background: #fef3c7;
            color: #d97706;
        }

        .category-badge.certificate {
            background: #fce7f3;
            color: #db2777;
        }

        .category-badge.report {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .category-badge.other {
            background: #f3f4f6;
            color: #6b7280;
        }

        .approval-badge {
            font-size: 11px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .approval-badge.direct {
            color: var(--success-color);
        }

        .approval-badge.approval {
            color: var(--warning-color);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
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

        .table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .table tbody tr:hover {
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

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn.approve {
            background: var(--success-color);
            color: white;
        }

        .action-btn.reject {
            background: var(--danger-color);
            color: white;
        }

        .action-btn.view {
            background: var(--info-color);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        /* Modal Styles */
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
            max-height: calc(90vh - 160px);
            overflow-y: auto;
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

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .file-upload {
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .file-upload:hover {
            border-color: var(--primary-color);
            background: rgba(138, 154, 91, 0.05);
        }

        .file-upload input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload i {
            font-size: 36px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .file-upload p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .file-upload .hint {
            font-size: 12px;
            color: var(--text-secondary);
            opacity: 0.7;
            margin-top: 4px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--light-color);
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
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 56px;
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

        @media (max-width: 768px) {
            .forms-page {
                padding: 16px;
                overflow-x: hidden;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'admin_nav.php'; ?>
    <script>
        // Initialize navigation functionality after include
        if (typeof initializeNavigation === 'function') {
            initializeNavigation();
        }
    </script>

    <main class="main page-content" id="main-content" style="display: block;">
        <div class="page-title">
            <div class="heading">
                <h1 class="heading-title">Forms & Documents</h1>
                <p class="mb-0">Manage downloadable forms and process document requests</p>
            </div>
            <nav class="breadcrumbs">
                <ol>
                    <li><a href="../admin_dashboard.php"><i class="fas fa-home me-1"></i>Home</a></li>
                    <li class="current">Forms & Documents</li>
                </ol>
            </nav>
        </div>

        <div class="forms-page">
            <div class="page-header">
                <div>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Document
                </button>
            </div>
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon blue"><i class="fas fa-file-alt"></i></div>
                    <div class="stat-content">
                        <h3 id="stat-total">0</h3>
                        <p>Total Documents</p>
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-icon green"><i class="fas fa-download"></i></div>
                    <div class="stat-content">
                        <h3 id="stat-direct">0</h3>
                        <p>Direct Download</p>
                    </div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h3 id="stat-pending">0</h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon purple"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h3 id="stat-approved">0</h3>
                        <p>Approved Requests</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="documents">
                        <i class="fas fa-file-alt"></i> Available Forms
                    </button>
                    <button class="tab-btn" data-tab="requests">
                        <i class="fas fa-clipboard-list"></i> Document Requests
                        <span class="badge" id="pending-badge" style="display: none;">0</span>
                    </button>
                    <button class="tab-btn" data-tab="history">
                        <i class="fas fa-history"></i> Download History
                    </button>
                </div>

                <div class="tab-content">
                    <!-- Documents Tab -->
                    <div class="tab-pane active" id="documents">
                        <div class="toolbar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="doc-search" placeholder="Search documents..." oninput="loadDocuments()">
                            </div>
                            <select class="filter-select" id="doc-category" onchange="loadDocuments()">
                                <option value="">All Categories</option>
                                <option value="enrollment">Enrollment</option>
                                <option value="registration">Registration</option>
                                <option value="clearance">Clearance</option>
                                <option value="certificate">Certificate</option>
                                <option value="report">Report</option>
                                <option value="other">Other</option>
                            </select>
                            <select class="filter-select" id="doc-type" onchange="loadDocuments()">
                                <option value="">All Types</option>
                                <option value="0">Direct Download</option>
                                <option value="1">Requires Approval</option>
                            </select>
                        </div>
                        <div class="documents-grid" id="documents-grid">
                            <!-- Documents will be loaded here -->
                        </div>
                    </div>

                    <!-- Requests Tab -->
                    <div class="tab-pane" id="requests">
                        <div class="toolbar">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="req-search" placeholder="Search requests..." oninput="loadRequests()">
                            </div>
                            <select class="filter-select" id="req-status" onchange="loadRequests()">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="requests-table">
                                    <!-- Requests will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- History Tab -->
                    <div class="tab-pane" id="history">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Document</th>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody id="history-table">
                                    <!-- History will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add/Edit Document Modal -->
    <div class="modal-overlay" id="documentModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> <span id="modal-title">Add New Document</span></h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="documentForm">
                    <input type="hidden" id="doc-id">
                    <div class="form-group">
                        <label>Title <span style="color: red;">*</span></label>
                        <input type="text" id="doc-title" required placeholder="Enter document title">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="doc-description" placeholder="Enter document description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Category <span style="color: red;">*</span></label>
                        <select id="doc-category-select" required>
                            <option value="">Select Category</option>
                            <option value="enrollment">Enrollment</option>
                            <option value="registration">Registration</option>
                            <option value="clearance">Clearance</option>
                            <option value="certificate">Certificate</option>
                            <option value="report">Report</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Document File</label>
                        <div class="file-upload" id="file-upload">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click or drag to upload file</p>
                            <span class="hint">PDF, DOC, DOCX, JPG, PNG (Max 10MB)</span>
                            <input type="file" id="doc-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                        <div id="current-file" style="margin-top: 10px; display: none;">
                            <small>Current file: <span id="current-filename"></span></small>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="doc-requires-approval">
                            <label for="doc-requires-approval">Requires approval before download</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveDocument()">
                    <i class="fas fa-save"></i> Save Document
                </button>
            </div>
        </div>
    </div>

    <!-- View Request Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-clipboard-list"></i> Request Details</h3>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="request-details">
                    <!-- Details will be loaded here -->
                </div>
                <div class="form-group" id="admin-notes-group" style="margin-top: 20px;">
                    <label>Admin Notes</label>
                    <textarea id="admin-notes" placeholder="Add notes (optional)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeViewModal()">Close</button>
                <button class="btn btn-danger" onclick="processRequest('reject')">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button class="btn btn-success" onclick="processRequest('approve')">
                    <i class="fas fa-check"></i> Approve
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script src="admin_assets/js/admin_script.js"></script>
    <script>
        // Current request being processed
        let currentRequestId = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadNavigation();
            loadStatistics();
            loadDocuments();
            loadRequests();
            initTabs();
        });

        function loadNavigation() {
            const container = document.getElementById('navigation-container');
            fetch('./admin_nav.php')
                .then(response => {
                    if (!response.ok) throw new Error('Failed to load navigation');
                    return response.text();
                })
                .then(data => {
                    container.innerHTML = data;
                    initializeNavigation();
                    document.getElementById('main-content').style.display = 'block';
                })
                .catch(error => {
                    container.innerHTML = '<div class="nav-error"><i class="fas fa-exclamation-triangle"></i><h3>Unable to Load Navigation</h3><p>There was a problem loading the navigation menu.</p><button class="btn-retry" onclick="loadNavigation()"><i class="fas fa-redo"></i> Try Again</button></div>';
                });
        }

        function initializeNavigation() {
            const mainDiv = document.querySelector('.main');
            const pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent) {
                mainDiv.appendChild(pageContent);
            }
        }

        function initTabs() {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');

                    // Update buttons
                    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    // Update content
                    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');

                    // Load data for the tab
                    if (tabId === 'documents') loadDocuments();
                    if (tabId === 'requests') loadRequests();
                    if (tabId === 'history') loadHistory();
                });
            });
        }

        // Statistics
        function loadStatistics() {
            fetch('api/forms_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=get_statistics'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('stat-total').textContent = data.data.total_documents;
                        document.getElementById('stat-direct').textContent = data.data.direct_download;
                        document.getElementById('stat-pending').textContent = data.data.pending_requests;
                        document.getElementById('stat-approved').textContent = data.data.approved_requests;

                        if (data.data.pending_requests > 0) {
                            const badge = document.getElementById('pending-badge');
                            badge.textContent = data.data.pending_requests;
                            badge.style.display = 'inline';
                        }
                    }
                });
        }

        // Documents
        function loadDocuments() {
            const search = document.getElementById('doc-search').value;
            const category = document.getElementById('doc-category').value;
            const type = document.getElementById('doc-type').value;

            const formData = new FormData();
            formData.append('action', 'get_documents');
            if (search) formData.append('search', search);
            if (category) formData.append('category', category);
            if (type) formData.append('requires_approval', type);

            fetch('api/forms_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderDocuments(data.data);
                    }
                });
        }

        function renderDocuments(documents) {
            const grid = document.getElementById('documents-grid');

            if (documents.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-folder-open"></i>
                        <h4>No Documents Found</h4>
                        <p>Add your first document to get started</p>
                    </div>
                `;
                return;
            }

            grid.innerHTML = documents.map(doc => {
                const iconClass = getFileIcon(doc.file_type);
                const approvalText = doc.requires_approval ? 'Requires Approval' : 'Direct Download';
                const approvalClass = doc.requires_approval ? 'approval' : 'direct';

                return `
                    <div class="document-card">
                        <div class="document-header">
                            <div class="document-icon ${iconClass}">
                                <i class="fas ${getFileFaIcon(doc.file_type)}"></i>
                            </div>
                            <div class="document-actions">
                                <button onclick="editDocument(${doc.id})" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="delete" onclick="deleteDocument(${doc.id})" title="Delete"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <h4 class="document-title">${doc.title}</h4>
                        <p class="document-desc">${doc.description || 'No description'}</p>
                        <div class="document-meta">
                            <span class="category-badge ${doc.category}">${doc.category}</span>
                            <span class="approval-badge ${approvalClass}">
                                <i class="fas ${doc.requires_approval ? 'fa-lock' : 'fa-lock-open'}"></i>
                                ${approvalText}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function getFileIcon(fileType) {
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

        // Document Modal
        function openAddModal() {
            document.getElementById('modal-title').textContent = 'Add New Document';
            document.getElementById('documentForm').reset();
            document.getElementById('doc-id').value = '';
            document.getElementById('current-file').style.display = 'none';
            document.getElementById('documentModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('documentModal').classList.remove('active');
        }

        function editDocument(id) {
            // Get document details
            fetch('api/forms_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=get_documents`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const doc = data.data.find(d => d.id === id);
                        if (doc) {
                            document.getElementById('modal-title').textContent = 'Edit Document';
                            document.getElementById('doc-id').value = doc.id;
                            document.getElementById('doc-title').value = doc.title;
                            document.getElementById('doc-description').value = doc.description || '';
                            document.getElementById('doc-category-select').value = doc.category;
                            document.getElementById('doc-requires-approval').checked = doc.requires_approval == 1;

                            if (doc.file_path) {
                                document.getElementById('current-file').style.display = 'block';
                                document.getElementById('current-filename').textContent = doc.original_filename;
                            }

                            document.getElementById('documentModal').classList.add('active');
                        }
                    }
                });
        }

        function saveDocument() {
            const id = document.getElementById('doc-id').value;
            const title = document.getElementById('doc-title').value;
            const description = document.getElementById('doc-description').value;
            const category = document.getElementById('doc-category-select').value;
            const requiresApproval = document.getElementById('doc-requires-approval').checked;
            const fileInput = document.getElementById('doc-file');

            if (!title || !category) {
                showToast('Please fill in all required fields', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', id ? 'update_document' : 'add_document');
            if (id) {
                formData.append('id', id);
                formData.append('existing_file_path', '1'); // Keep existing
            }
            formData.append('title', title);
            formData.append('description', description);
            formData.append('category', category);
            if (requiresApproval) formData.append('requires_approval', '1');
            if (fileInput.files[0]) {
                formData.append('file', fileInput.files[0]);
            }

            fetch('api/forms_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast(data.message, 'success');
                        closeModal();
                        loadDocuments();
                        loadStatistics();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
        }

        function deleteDocument(id) {
            if (!confirm('Are you sure you want to delete this document?')) return;

            fetch('api/forms_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=delete_document&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Document deleted successfully', 'success');
                        loadDocuments();
                        loadStatistics();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
        }

        // Requests
        function loadRequests() {
            const search = document.getElementById('req-search').value;
            const status = document.getElementById('req-status').value;

            const formData = new FormData();
            formData.append('action', 'get_requests');
            if (search) formData.append('search', search);
            if (status) formData.append('status', status);

            fetch('api/forms_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderRequests(data.data);
                    }
                });
        }

        function renderRequests(requests) {
            const tbody = document.getElementById('requests-table');

            if (requests.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            <i class="fas fa-inbox" style="font-size: 32px; color: #ccc;"></i>
                            <p style="margin-top: 10px; color: #666;">No requests found</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = requests.map(req => `
                <tr>
                    <td>
                        <strong>${req.first_name} ${req.last_name}</strong><br>
                        <small style="color: #666;">LRN: ${req.lrn || 'N/A'}</small>
                    </td>
                    <td>${req.document_title}</td>
                    <td><span class="category-badge ${req.category}">${req.category}</span></td>
                    <td>${formatDate(req.requested_at)}</td>
                    <td><span class="status-badge ${req.status}">${req.status}</span></td>
                    <td>
                        <div class="action-btns">
                            <button class="action-btn view" onclick="viewRequest(${req.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${req.status === 'pending' ? `
                                <button class="action-btn approve" onclick="quickApprove(${req.id})">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="action-btn reject" onclick="quickReject(${req.id})">
                                    <i class="fas fa-times"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function viewRequest(id) {
            currentRequestId = id;

            const formData = new FormData();
            formData.append('action', 'get_request_details');
            formData.append('request_id', id);

            fetch('api/forms_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const req = data.data;
                        document.getElementById('request-details').innerHTML = `
                        <div style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <h4 style="margin-bottom: 12px;">Student Information</h4>
                            <p><strong>Name:</strong> ${req.first_name} ${req.last_name}</p>
                            <p><strong>LRN:</strong> ${req.lrn || 'N/A'}</p>
                            <p><strong>Grade/Section:</strong> ${req.grade_level || ''} - ${req.section || ''}</p>
                            <p><strong>Email:</strong> ${req.email || 'N/A'}</p>
                        </div>
                        <div style="background: #f9fafb; padding: 16px; border-radius: 8px;">
                            <h4 style="margin-bottom: 12px;">Document Request</h4>
                            <p><strong>Document:</strong> ${req.document_title}</p>
                            <p><strong>Request Type:</strong> ${req.request_type}</p>
                            <p><strong>Purpose:</strong> ${req.purpose || 'Not specified'}</p>
                            <p><strong>Requested:</strong> ${formatDate(req.requested_at)}</p>
                            ${req.admin_notes ? `<p><strong>Admin Notes:</strong> ${req.admin_notes}</p>` : ''}
                        </div>
                    `;
                        document.getElementById('viewModal').classList.add('active');
                    }
                });
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            currentRequestId = null;
            document.getElementById('admin-notes').value = '';
        }

        function processRequest(action) {
            if (!currentRequestId) return;

            const notes = document.getElementById('admin-notes').value;

            fetch('api/forms_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=process_request&request_id=${currentRequestId}&action=${action}&admin_notes=${encodeURIComponent(notes)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast(data.message, 'success');
                        closeViewModal();
                        loadRequests();
                        loadStatistics();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
        }

        function quickApprove(id) {
            fetch('api/forms_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=process_request&request_id=${id}&action=approve`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Request approved', 'success');
                        loadRequests();
                        loadStatistics();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
        }

        function quickReject(id) {
            if (!confirm('Are you sure you want to reject this request?')) return;

            fetch('api/forms_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=process_request&request_id=${id}&action=reject`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('Request rejected', 'success');
                        loadRequests();
                        loadStatistics();
                    } else {
                        showToast(data.message, 'error');
                    }
                });
        }

        // Download History
        function loadHistory() {
            fetch('api/forms_api.php?action=get_download_history')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderHistory(data.data);
                    }
                });
        }

        function renderHistory(history) {
            const tbody = document.getElementById('history-table');

            if (history.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <i class="fas fa-history" style="font-size: 32px; color: #ccc;"></i>
                            <p style="margin-top: 10px; color: #666;">No download history</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = history.map(h => `
                <tr>
                    <td>${h.document_title}</td>
                    <td>${h.user_name || 'Unknown'}</td>
                    <td>${h.user_type}</td>
                    <td>${formatDate(h.downloaded_at)}</td>
                    <td>${h.ip_address}</td>
                </tr>
            `).join('');
        }

        // Utilities
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
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

        // Close modals on overlay click
        document.getElementById('documentModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) closeViewModal();
        });
    </script>
</body>

</html>