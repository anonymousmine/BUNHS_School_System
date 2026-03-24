<?php

/**
 * admin_chatbox.php
 * Enhanced: file request approval, club group chat support.
 * With integrated navigation & professional UI design
 */

require_once '../session_config.php';

if (
    !(isset($_SESSION['user_id']) && in_array($_SESSION['user_type'] ?? '', ['admin', 'sub-admin']))
    && !isset($_SESSION['admin_id'])
) {
    header('Location: ../index.php');
    exit;
}

include '../db_connection.php';

// Build base URLs relative to this file's actual location on disk.
// admin_chatbox.php lives in /admin_account/, so:
//   adminBase  = the URL path of /admin_account/  (for API calls)
//   assetsBase = the URL path one level up         (for shared assets)
// Using dirname() on SCRIPT_NAME is reliable regardless of sub-folder depth
// or whether the project is installed at root or in a sub-directory.
$_script     = $_SERVER['SCRIPT_NAME'];            // e.g. /BUNHS/admin_account/admin_chatbox.php
$adminBase   = rtrim(dirname($_script), '/') . '/';          // /BUNHS/admin_account/
$assetsBase  = rtrim(dirname(dirname($_script)), '/') . '/'; // /BUNHS/

$preloadConvId  = (int) ($_GET['conv'] ?? 0);
$adminInitial   = strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbox – BUNHS Admin</title>
    <link rel="stylesheet" href="<?= $assetsBase ?>admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ════════════════════════════════════════════
           ROOT DESIGN TOKENS
        ════════════════════════════════════════════ */
        :root {
            --moss: #7a8f4e;
            --moss-dark: #5c6b38;
            --moss-light: #adbf72;
            --moss-ultra: rgba(122, 143, 78, .1);
            --moss-glow: rgba(122, 143, 78, .3);
            --bg: #f2f5f0;
            --surface: #ffffff;
            --border: #e4e9de;
            --text: #1a2010;
            --muted: #6b7c55;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, .06);
            --shadow-md: 0 6px 24px rgba(0, 0, 0, .09);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, .12);
            --radius: 14px;
            --radius-sm: 8px;
            --font: 'DM Sans', sans-serif;
            --font-d: 'Syne', sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
        }

        /* ════════════════════════════════════════════
           PAGE STRUCTURE WITH NAVIGATION
        ════════════════════════════════════════════ */
        .page-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            overflow: hidden;
        }

        /* Navigation Error Display */
        .nav-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 16px;
        }

        .nav-error i {
            font-size: 48px;
            color: var(--danger);
            margin-bottom: 12px;
        }

        .nav-error h3 {
            color: var(--danger);
            margin-bottom: 8px;
        }

        .nav-error p {
            color: var(--muted);
            margin-bottom: 16px;
        }

        .nav-error .btn-retry {
            background: var(--moss);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .nav-error .btn-retry:hover {
            background: var(--moss-dark);
            transform: translateY(-2px);
        }

        .chat-page {
            padding: 24px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 70px);
            flex: 1;
        }

        /* ════════════════════════════════════════════
           HEADER SECTION
        ════════════════════════════════════════════ */
        .chat-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .chat-page-header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .chat-page-header h1 {
            font-family: var(--font-d);
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-page-header h1 i {
            color: var(--moss);
        }

        .chat-page-header p {
            color: var(--muted);
            font-size: 13px;
        }

        /* ════════════════════════════════════════════
           FILTER SECTION
        ════════════════════════════════════════════ */
        .filter-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 8px 18px;
            border-radius: 24px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--muted);
            transition: all .18s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .filter-pill:hover {
            border-color: var(--moss);
            color: var(--moss);
            background: var(--moss-ultra);
        }

        .filter-pill.active {
            background: var(--moss);
            color: #fff;
            border-color: var(--moss);
            box-shadow: 0 4px 12px rgba(122, 143, 78, .25);
        }

        .filter-pill .cnt {
            background: rgba(255, 255, 255, .3);
            color: inherit;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 12px;
            font-weight: 700;
        }

        .filter-pill:not(.active) .cnt {
            background: var(--moss-ultra);
            color: var(--moss);
        }

        /* ════════════════════════════════════════════
           CHAT LAYOUT (Two-column grid)
        ════════════════════════════════════════════ */
        .chat-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
            flex: 1;
            min-height: 0;
            margin-bottom: 20px;
            width: 100%;
        }

        /* ── CONVERSATION LIST ── */
        .conv-list {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .conv-list-header {
            padding: 18px 20px 14px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(122, 143, 78, .03), transparent);
        }

        .conv-list-header h3 {
            font-family: var(--font-d);
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .conv-list-header h3 i {
            color: var(--moss);
        }

        .conv-search {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            outline: none;
            font-family: var(--font);
            background: #fafbf9;
            transition: all .18s ease;
        }

        .conv-search:focus {
            border-color: var(--moss);
            background: var(--surface);
            box-shadow: 0 0 0 3px var(--moss-ultra);
        }

        .conv-items {
            flex: 1;
            overflow-y: auto;
        }

        .conv-items::-webkit-scrollbar {
            width: 6px;
        }

        .conv-items::-webkit-scrollbar-track {
            background: transparent;
        }

        .conv-items::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        .conv-items::-webkit-scrollbar-thumb:hover {
            background: var(--moss-light);
        }

        .conv-section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--muted);
            padding: 12px 20px 6px;
            background: var(--moss-ultra);
        }

        .conv-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f5f8f2;
            transition: all .15s ease;
            position: relative;
        }

        .conv-item:hover {
            background: var(--moss-ultra);
        }

        .conv-item.active {
            background: var(--moss-ultra);
            border-left: 4px solid var(--moss);
        }

        .conv-avatar {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(122, 143, 78, .2);
        }

        .conv-avatar.club-av {
            background: linear-gradient(135deg, #2d6a4f, #1b4332);
        }

        .conv-info {
            flex: 1;
            min-width: 0;
        }

        .conv-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .club-tag {
            background: linear-gradient(135deg, rgba(45, 106, 79, .15), rgba(27, 67, 50, .15));
            color: #2d6a4f;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 7px;
            border-radius: 20px;
            border: 1px solid rgba(45, 106, 79, .25);
        }

        .conv-preview {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-time {
            font-size: 10px;
            color: #999;
            white-space: nowrap;
        }

        .conv-unread {
            background: var(--moss);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            position: absolute;
            top: 14px;
            right: 12px;
            box-shadow: 0 2px 8px rgba(122, 143, 78, .3);
        }

        .conv-filereq {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--warning);
            position: absolute;
            top: 14px;
            right: 12px;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .15);
        }

        /* ════════════════════════════════════════════
           CHAT WINDOW
        ════════════════════════════════════════════ */
        .chat-win {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            gap: 16px;
            background: linear-gradient(135deg, rgba(122, 143, 78, .02), transparent);
        }

        .chat-empty i {
            font-size: 56px;
            opacity: .15;
            color: var(--moss);
        }

        .chat-empty p {
            font-size: 15px;
            font-weight: 500;
        }

        /* Chat header */
        .chat-win-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            background: linear-gradient(90deg, rgba(122, 143, 78, .05), transparent);
        }

        .chat-win-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(122, 143, 78, .2);
        }

        .chat-win-info {
            flex: 1;
        }

        .chat-win-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .chat-win-status {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .pending-req-badge {
            margin-left: auto;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #fcd34d;
            padding: 7px 14px;
            border-radius: 24px;
            font-size: 12px;
            font-weight: 700;
            display: none;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            transition: all .15s ease;
            box-shadow: 0 2px 8px rgba(245, 158, 11, .2);
        }

        .pending-req-badge:hover {
            background: linear-gradient(135deg, #fde68a, #fcd34d);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, .3);
        }

        .pending-req-badge.show {
            display: flex;
        }

        /* Messages container */
        .chat-messages {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: linear-gradient(180deg, #fafbf9 0%, #f6f9f2 100%);
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--moss-light);
        }

        /* Date divider */
        .date-sep {
            text-align: center;
            font-size: 11px;
            color: var(--muted);
            position: relative;
            margin: 8px 0;
            font-weight: 500;
        }

        .date-sep::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            border-top: 1px solid var(--border);
            z-index: -1;
        }

        .date-sep span {
            background: linear-gradient(180deg, #fafbf9 0%, #f6f9f2 100%);
            padding: 0 12px;
        }

        /* Message rows */
        .msg-row {
            display: flex;
            gap: 12px;
            margin-bottom: 4px;
        }

        .msg-row.mine {
            justify-content: flex-end;
        }

        .msg-row.mine .msg-av {
            order: 2;
        }

        .msg-av {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--moss), var(--moss-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(122, 143, 78, .15);
        }

        .msg-bubble {
            background: linear-gradient(135deg, var(--surface), #fcfdfc);
            border: 1px solid var(--border);
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            max-width: 70%;
            word-wrap: break-word;
        }

        .msg-row.mine .msg-bubble {
            background: linear-gradient(135deg, var(--moss), var(--moss-light));
            color: #fff;
            border: none;
        }

        .msg-text {
            font-size: 14px;
            line-height: 1.5;
            color: inherit;
        }

        .msg-time {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
            text-align: left;
        }

        .msg-row.mine .msg-time {
            text-align: right;
        }

        /* File request bubble */
        .file-req-bubble {
            background: linear-gradient(135deg, #fef3c7, #fef0ca) !important;
            border: 1.5px solid #fde68a !important;
        }

        .file-req-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(245, 158, 11, .2);
        }

        .file-req-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(245, 158, 11, .15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #92400e;
            font-size: 14px;
        }

        .file-req-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #92400e;
        }

        .file-req-name {
            font-weight: 700;
            font-size: 14px;
            color: #1a2010;
            margin-bottom: 8px;
        }

        .file-req-reason {
            font-size: 13px;
            color: #5a5a5a;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .req-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 11px;
            border-radius: 16px;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 10px;
        }

        .req-status-pill.pending {
            background: rgba(245, 158, 11, .2);
            color: #92400e;
        }

        .req-status-pill.approved {
            background: rgba(16, 185, 129, .2);
            color: #065f46;
        }

        .req-status-pill.rejected {
            background: rgba(239, 68, 68, .2);
            color: #7f1d1d;
        }

        /* Approval actions */
        .approval-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .approve-btn,
        .reject-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .approve-btn {
            background: var(--success);
            color: #fff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, .2);
        }

        .approve-btn:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, .3);
            transform: translateY(-1px);
        }

        .reject-btn {
            background: var(--danger);
            color: #fff;
            box-shadow: 0 2px 8px rgba(239, 68, 68, .2);
        }

        .reject-btn:hover {
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(239, 68, 68, .3);
            transform: translateY(-1px);
        }

        /* ════════════════════════════════════════════
           MESSAGE INPUT
        ════════════════════════════════════════════ */
        .chat-input-box {
            padding: 18px 24px;
            border-top: 1px solid var(--border);
            background: linear-gradient(180deg, #fafbf9 0%, var(--surface) 100%);
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .msg-input-wrapper {
            flex: 1;
            display: flex;
            gap: 8px;
        }

        .msg-input {
            flex: 1;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: var(--font);
            outline: none;
            background: var(--surface);
            transition: all .18s ease;
            resize: none;
            max-height: 100px;
            min-height: 44px;
        }

        .msg-input:focus {
            border-color: var(--moss);
            box-shadow: 0 0 0 3px var(--moss-ultra);
        }

        .send-btn {
            padding: 12px 22px;
            background: var(--moss);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all .18s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(122, 143, 78, .25);
        }

        .send-btn:hover:not(:disabled) {
            background: var(--moss-dark);
            box-shadow: 0 6px 16px rgba(122, 143, 78, .35);
            transform: translateY(-2px);
        }

        .send-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        /* ════════════════════════════════════════════
           NOTIFICATIONS & TOAST
        ════════════════════════════════════════════ */
        #toastZone {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toast {
            background: var(--surface);
            border: 1.5px solid var(--border);
            padding: 14px 18px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            font-size: 13px;
            font-weight: 600;
            opacity: 0;
            transform: translateX(400px);
            transition: all .3s ease;
            color: var(--text);
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast.success {
            border-color: var(--success);
            color: var(--success);
        }

        .toast.success i {
            color: var(--success);
        }

        .toast.error {
            border-color: var(--danger);
            color: var(--danger);
        }

        .toast.error i {
            color: var(--danger);
        }

        /* ════════════════════════════════════════════
           RESPONSIVE DESIGN
        ════════════════════════════════════════════ */
        @media (max-width: 900px) {
            .chat-page {
                padding: 90px 16px 24px !important;
            }

            .chat-layout {
                grid-template-columns: 280px 1fr;
                gap: 16px;
            }

            .msg-bubble {
                max-width: 85%;
            }
        }

        @media (max-width: 600px) {
            .chat-page {
                padding: 80px 12px 24px !important;
                height: calc(100vh - 60px);
            }

            .chat-page-header {
                margin-bottom: 16px;
            }

            .chat-page-header h1 {
                font-size: 22px;
            }

            .filter-pills {
                gap: 8px;
            }

            .chat-layout {
                grid-template-columns: 1fr;
            }

            .conv-list {
                display: none;
            }

            .msg-bubble {
                max-width: 100%;
            }

            #toastZone {
                bottom: 24px;
                right: 12px;
                top: auto;
            }

            .toast {
                min-width: 100%;
                max-width: calc(100vw - 24px);
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

    <!-- Chat Page Content -->
    <section class="page-content" id="chatbox-content" style="display: none;">
        <!-- Chat Page -->
        <div class="chat-page">
            <!-- Header Section -->
            <div class="chat-page-header">
                <div class="chat-page-header-left">
                    <h1>
                        <i class="fas fa-comments"></i>
                        Messages
                    </h1>
                    <p>Manage conversations and file requests</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div style="margin-bottom: 20px; display: flex; gap: 8px;">
                <button class="filter-pill active" onclick="filterConversations('all')">
                    <i class="fas fa-inbox"></i>
                    All Messages
                    <span class="cnt" id="countAll">0</span>
                </button>
                <button class="filter-pill" onclick="filterConversations('unread')">
                    <i class="fas fa-bell"></i>
                    Unread
                    <span class="cnt" id="countUnread">0</span>
                </button>
                <button class="filter-pill" onclick="filterConversations('files')">
                    <i class="fas fa-file"></i>
                    File Requests
                    <span class="cnt" id="countFiles">0</span>
                </button>
                <button class="filter-pill" onclick="filterConversations('clubs')">
                    <i class="fas fa-users"></i>
                    Club Groups
                    <span class="cnt" id="countClubs">0</span>
                </button>
            </div>

            <!-- Chat Layout -->
            <div class="chat-layout">
                <!-- Conversation List -->
                <div class="conv-list">
                    <div class="conv-list-header">
                        <h3>
                            <i class="fas fa-list"></i>
                            Conversations
                        </h3>
                        <input type="text" class="conv-search" placeholder="Search conversations..." id="convSearch" oninput="searchConversations(this.value)">
                    </div>
                    <div class="conv-items" id="convItems"></div>
                </div>

                <!-- Chat Window -->
                <div class="chat-win">
                    <div id="chatArea" style="display:none; flex:1; display:flex; flex-direction:column;">
                        <!-- Chat Header -->
                        <div class="chat-win-header">
                            <div class="chat-win-avatar" id="chatAvatar">A</div>
                            <div class="chat-win-info">
                                <div class="chat-win-name" id="chatName">—</div>
                                <div class="chat-win-status" id="chatStatus">—</div>
                            </div>
                            <div class="pending-req-badge" id="pendingReqBadge" onclick="scrollToPendingRequest()">
                                <i class="fas fa-clock"></i>
                                <span><span id="pendingReqCount">0</span> pending</span>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="chat-messages" id="chatMessages"></div>

                        <!-- Input Box -->
                        <div class="chat-input-box">
                            <div class="msg-input-wrapper">
                                <textarea class="msg-input" id="msgInput" placeholder="Type your message..." rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault(); sendMessage();}"></textarea>
                                <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Send</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div class="chat-empty" id="chatEmpty">
                        <i class="fas fa-comments"></i>
                        <p>Select a conversation to start messaging</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Toast notification zone -->
    <div id="toastZone"></div>

    <script>
        /* ════════════════════════════════════════════
           CONFIGURATION
        ════════════════════════════════════════════ */
        const API = '<?= $adminBase ?>chat_api.php';
        const FILE_REQ_API = '<?= $adminBase ?>chat_api.php';
        const ADMIN_INIT = '<?= $adminInitial ?>';

        let activeConvId = <?= $preloadConvId ?>;
        let activeStudent = {};
        let allConvs = [];
        let currentFilter = 'all';

        /* ════════════════════════════════════════════
           FILTER CONVERSATIONS
        ════════════════════════════════════════════ */
        function applyFilter(convs) {
            if (currentFilter === 'unread') {
                return convs.filter(c => c.unread_count > 0);
            }
            if (currentFilter === 'files') {
                return convs.filter(c => c.has_file_request);
            }
            if (currentFilter === 'clubs') {
                return convs.filter(c => c.is_club_group);
            }
            return convs;
        }

        function updateFilterCounts() {
            document.getElementById('countAll').textContent = allConvs.length;
            document.getElementById('countUnread').textContent = allConvs.filter(c => c.unread_count > 0).length;
            document.getElementById('countFiles').textContent = allConvs.filter(c => c.has_file_request).length;
            document.getElementById('countClubs').textContent = allConvs.filter(c => c.is_club_group).length;
        }

        function filterConversations(filter) {
            currentFilter = filter;
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            event.target.closest('.filter-pill').classList.add('active');
            renderConvs(applyFilter(allConvs));
        }

        function searchConversations(query) {
            const q = query.toLowerCase().trim();
            const filtered = allConvs.filter(c =>
                (c.student_name || '').toLowerCase().includes(q) ||
                (c.club_name || '').toLowerCase().includes(q)
            );
            renderConvs(applyFilter(filtered));
        }

        /* ════════════════════════════════════════════
           RENDER CONVERSATIONS LIST
        ════════════════════════════════════════════ */
        function renderConvs(convs) {
            const box = document.getElementById('convItems');
            if (!convs.length) {
                box.innerHTML = '<div style="padding:20px; text-align:center; color:var(--muted); font-size:13px;"><i class="fas fa-inbox" style="display:block; font-size:32px; opacity:.2; margin-bottom:8px;"></i>No conversations</div>';
                return;
            }

            let html = '';
            let lastSection = '';

            convs.forEach(c => {
                const isClub = c.is_club_group;
                const section = isClub ? 'clubs' : 'students';

                if (section !== lastSection) {
                    html += `<div class="conv-section-label">${isClub ? 'Club Groups' : 'Students'}</div>`;
                    lastSection = section;
                }

                const initials = isClub ?
                    c.club_name.substring(0, 2).toUpperCase() :
                    c.student_name.substring(0, 1).toUpperCase();

                const active = activeConvId === c.conversation_id ? 'active' : '';
                const unread = c.unread_count > 0 ? `<div class="conv-unread">${c.unread_count}</div>` : '';
                const fileReq = c.has_file_request ? '<div class="conv-filereq"></div>' : '';
                const clubTag = isClub ? '<span class="club-tag">GROUP</span>' : '';

                html += `
                <div class="conv-item ${active}" onclick="selectConversation(${c.conversation_id}, ${JSON.stringify(c).replace(/"/g, '&quot;')})">
                    <div class="conv-avatar ${isClub ? 'club-av' : ''}">${initials}</div>
                    <div class="conv-info">
                        <div class="conv-name">
                            ${escHtml(isClub ? c.club_name : c.student_name)}
                            ${clubTag}
                        </div>
                        <div class="conv-preview">${escHtml(c.last_message || '—')}</div>
                        <div class="conv-time">${escHtml(c.time_ago || '')}</div>
                    </div>
                    ${unread}
                    ${fileReq}
                </div>`;
            });

            box.innerHTML = html;
        }

        async function loadConversations() {
            const fd = new FormData();
            fd.append('action', 'fetch_conversations');

            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.success) {
                allConvs = data.conversations || [];
                updateFilterCounts();
                renderConvs(applyFilter(allConvs));

                if (activeConvId > 0) {
                    const conv = allConvs.find(c => c.conversation_id === activeConvId);
                    if (conv) selectConversation(activeConvId, conv);
                }
            }
        }

        async function selectConversation(convId, conv) {
            activeConvId = convId;
            activeStudent = conv;

            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
            event.target.closest('.conv-item').classList.add('active');

            const isClub = conv.is_club_group;
            document.getElementById('chatAvatar').textContent = isClub ?
                conv.club_name.substring(0, 2).toUpperCase() :
                conv.student_name.substring(0, 1).toUpperCase();
            document.getElementById('chatName').textContent = isClub ? conv.club_name : conv.student_name;
            document.getElementById('chatStatus').textContent = isClub ? 'Club Group' : 'Student';

            document.getElementById('chatEmpty').style.display = 'none';
            document.getElementById('chatArea').style.display = 'flex';

            markRead();
            await loadMessages();
        }

        /* ════════════════════════════════════════════
           LOAD & RENDER MESSAGES
        ════════════════════════════════════════════ */
        async function loadMessages() {
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('conversation_id', activeConvId);

            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (!data.success) return;
            renderMessages(data.messages);
            await refreshConvList();
        }

        function renderMessages(msgs) {
            const box = document.getElementById('chatMessages');
            const atBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 40;

            let pendingCount = 0;
            let lastDate = '';

            box.innerHTML = msgs.map(m => {
                const isMine = m.sender_role === 'admin';
                const init = isMine ? ADMIN_INIT : (activeStudent.avatar_letter || '?');
                const msgDate = (m.created_at || '').substring(0, 10);

                let divider = '';
                if (msgDate && msgDate !== lastDate) {
                    lastDate = msgDate;
                    divider = `<div class="date-sep"><span>${formatDate(msgDate)}</span></div>`;
                }

                if (m.message_type === 'file_request') {
                    if (m.request_status === 'pending') pendingCount++;
                    return divider + buildFileReqBubble(m, isMine, init);
                }

                return `${divider}
        <div class="msg-row ${isMine ? 'mine' : ''}">
            <div class="msg-av">${escHtml(init)}</div>
            <div>
                <div class="msg-bubble">
                    <div class="msg-text">${escHtml(m.message)}</div>
                </div>
                <div class="msg-time">${escHtml(m.time_label)}</div>
            </div>
        </div>`;
            }).join('');

            if (atBottom) box.scrollTop = box.scrollHeight;

            const badge = document.getElementById('pendingReqBadge');
            document.getElementById('pendingReqCount').textContent = pendingCount;
            badge.classList.toggle('show', pendingCount > 0);
        }

        function buildFileReqBubble(m, isMine, init) {
            const statusMap = {
                pending: {
                    cls: 'pending',
                    icon: 'fa-clock',
                    label: 'Pending Approval'
                },
                approved: {
                    cls: 'approved',
                    icon: 'fa-check-circle',
                    label: 'Approved'
                },
                rejected: {
                    cls: 'rejected',
                    icon: 'fa-times-circle',
                    label: 'Rejected'
                },
            };
            const s = statusMap[m.request_status] || statusMap.pending;

            const actionBtns = (m.request_status === 'pending') ? `
        <div class="approval-actions" id="req_actions_${m.request_id}">
            <button class="approve-btn" onclick="processFileRequest(${m.request_id}, 'approve')">
                <i class="fas fa-check"></i> Approve
            </button>
            <button class="reject-btn"  onclick="processFileRequest(${m.request_id}, 'reject')">
                <i class="fas fa-times"></i> Reject
            </button>
        </div>` : '';

            return `
    <div class="msg-row ${isMine ? 'mine' : ''}" id="msg_req_${m.request_id}">
        <div class="msg-av">${escHtml(init)}</div>
        <div>
            <div class="msg-bubble file-req-bubble">
                <div class="file-req-header">
                    <div class="file-req-icon"><i class="fas fa-file-lock"></i></div>
                    <span class="file-req-label">FILE REQUEST</span>
                </div>
                <div class="file-req-name">${escHtml(m.file_name)}</div>
                <div class="file-req-reason"><strong>Reason:</strong> ${escHtml(m.message)}</div>
                <span class="req-status-pill ${s.cls}">
                    <i class="fas ${s.icon}"></i> ${s.label}
                </span>
                ${actionBtns}
            </div>
            <div class="msg-time">${escHtml(m.time_label)}</div>
        </div>
    </div>`;
        }

        function scrollToPendingRequest() {
            const box = document.getElementById('chatMessages');
            const first = box.querySelector('.req-status-pill.pending');
            if (first) first.closest('.msg-row')?.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }

        /* ════════════════════════════════════════════
           FILE REQUEST APPROVAL
        ════════════════════════════════════════════ */
        async function processFileRequest(requestId, action) {
            const fd = new FormData();
            fd.append('action', 'process_file_request');
            fd.append('request_id', requestId);
            fd.append('decision', action);

            const res = await fetch(API, { // Use main API endpoint
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.success) {
                toast(`${action === 'approve' ? '✅ Approved' : '❌ Rejected'}: ${data.student_name || 'Request'}`, 'success');
                await loadMessages();
                await refreshConvList(); // Refresh conversation list too
            } else {
                toast(data.message || 'Action failed.', 'error');
            }
        }

        /* ════════════════════════════════════════════
           SEND MESSAGE
        ════════════════════════════════════════════ */
        async function sendMessage() {
            const input = document.getElementById('msgInput');
            const text = input.value.trim();
            if (!text || !activeConvId) return;

            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            input.value = '';

            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('conversation_id', activeConvId);
            fd.append('message', text);

            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            btn.disabled = false;

            if (data.success) {
                await loadMessages();
                await refreshConvList();
            } else {
                input.value = text;
                toast('Failed to send message.', 'error');
            }
        }

        function markRead() {
            const fd = new FormData();
            fd.append('action', 'mark_read');
            fd.append('conversation_id', activeConvId);
            fetch(API, {
                method: 'POST',
                body: fd
            });
        }

        async function refreshConvList() {
            const fd = new FormData();
            fd.append('action', 'fetch_conversations');

            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (!data.success) return;
            allConvs = data.conversations;
            updateFilterCounts();
            renderConvs(applyFilter(allConvs));
        }

        /* ════════════════════════════════════════════
           HELPERS
        ════════════════════════════════════════════ */
        function escHtml(s) {
            if (s == null) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function formatDate(d) {
            const today = new Date().toISOString().substring(0, 10);
            if (d === today) return 'Today';
            const yest = new Date(Date.now() - 86400000).toISOString().substring(0, 10);
            if (d === yest) return 'Yesterday';
            return new Date(d).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function toast(msg, type = 'success') {
            const zone = document.getElementById('toastZone');
            const t = document.createElement('div');
            t.className = `toast ${type}`;
            t.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i><span>${escHtml(msg)}</span>`;
            zone.appendChild(t);
            requestAnimationFrame(() => t.classList.add('show'));
            setTimeout(() => {
                t.classList.remove('show');
                setTimeout(() => t.remove(), 300);
            }, 3500);
        }

        /* ════════════════════════════════════════════
           INITIALIZATION
        ════════════════════════════════════════════ */

        // Load Navigation with Error Handling
        function loadNavigation() {
            const container = document.getElementById('navigation-container');
            const navPath = 'admin_nav.php';

            fetch(navPath)
                .then(response => {
                    if (!response.ok) throw new Error('Failed to load navigation: ' + response.status);
                    return response.text();
                })
                .then(data => {
                    container.innerHTML = data;
                    initializeNavigation();
                    document.getElementById('chatbox-content').style.display = 'block';
                })
                .catch(error => {
                    console.error('Navigation error:', error);
                    container.innerHTML = `
                        <div class="nav-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>Unable to Load Navigation</h3>
                            <p>There was a problem loading the navigation menu.</p>
                            <p style="font-size: 12px; color: #666;">Error: ${error.message}</p>
                            <button class="btn-retry" onclick="loadNavigation()">
                                <i class="fas fa-redo"></i> Try Again
                            </button>
                        </div>
                    `;
                });
        }

        // Initialize Navigation Functionality
        function initializeNavigation() {
            // Move page content to .main div
            const mainDiv = document.querySelector('.main');
            const pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent) {
                mainDiv.appendChild(pageContent);
            }

            // Initialize Navigation Functionality (already handled by admin_nav.php)
            // No need for dropdown path fixing since PHP handles it correctly
            if (typeof initializeNavigation === 'function') {
                initializeNavigation();
            }

            // Mobile hamburger sidebar toggle
            initMobileNav();
        }

        function initMobileNav() {
            var hamburger = document.getElementById('navHamburgerBtn');
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (!hamburger || !sidebar || !overlay) return;

            var fresh = hamburger.cloneNode(true);
            hamburger.parentNode.replaceChild(fresh, hamburger);
            hamburger = fresh;

            function openSidebar() {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('visible');
                hamburger.classList.add('open');
                hamburger.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                hamburger.classList.remove('open');
                hamburger.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }

            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                if (sidebar.classList.contains('mobile-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });

            overlay.addEventListener('click', closeSidebar);

            sidebar.querySelectorAll('a.menu-item').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 900) closeSidebar();
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 900) {
                    closeSidebar();
                }
            });
        }

        // Start loading navigation on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadNavigation();
            loadConversations();
        });

        setInterval(refreshConvList, 15000);
    </script>
</body>

</html>