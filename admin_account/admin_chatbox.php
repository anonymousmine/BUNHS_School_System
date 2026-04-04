<?php

/**
 * admin_chatbox.php
 * Enhanced: file request approval, club group chat support.
 * With integrated navigation & professional UI design
 * UI Redesign: Deep Forest #102C26 header, improved chat visibility
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

$_script     = $_SERVER['SCRIPT_NAME'];
$adminBase   = rtrim(dirname($_script), '/') . '/';
$assetsBase  = rtrim(dirname(dirname($_script)), '/') . '/';

$preloadConvId  = (int) ($_GET['conv'] ?? 0);
$adminInitial   = strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1));

function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expires'] = time() + 1800; // 30 minutes
    }
    
    return $_SESSION['csrf_token'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbox – BUNHS Admin</title>
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <link rel="stylesheet" href="<?= $assetsBase ?>admin_account/admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="../overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ════════════════════════════════════════════
           ROOT DESIGN TOKENS
        ════════════════════════════════════════════ */
        :root {
            /* Moss Green Palette */
            --forest: #2d4a2b;
            --forest-mid: #3a5f38;
            --forest-light: #4a7347;
            --forest-pale: rgba(45, 74, 43, .08);
            --forest-glow: rgba(45, 74, 43, .18);

            /* Accent */
            --accent: #6fa870;
            --accent-soft: rgba(111, 168, 112, .12);
            --accent-border: rgba(111, 168, 112, .3);

            /* Surface */
            --bg: #f5f8f5;
            --surface: #ffffff;
            --surface2: #f8faf8;
            --border: #e0e8e0;
            --border2: #d0e0d0;

            /* Text */
            --text: #1a2d19;
            --text-2: #3a5f38;
            --muted: #5a7f58;

            /* Status */
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;

            /* Shadows */
            --shadow-sm: 0 1px 4px rgba(45, 74, 43, .07);
            --shadow-md: 0 4px 20px rgba(45, 74, 43, .10);
            --shadow-lg: 0 12px 40px rgba(45, 74, 43, .14);

            /* Shape */
            --radius: 16px;
            --radius-sm: 10px;

            /* Typography */
            --font: 'Plus Jakarta Sans', sans-serif;
            --font-d: 'Outfit', sans-serif;
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
           PAGE STRUCTURE
        ════════════════════════════════════════════ */
        .page-content {
            padding: 80px 28px 40px;
            width: 100%;
            min-height: calc(100vh - 72px);
            box-sizing: border-box;
            background: var(--bg);
            margin-top: 0;
        }

        .chat-page {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 150px); /* Fixed height for more space */
            flex: 1;
        }

        /* ════════════════════════════════════════════
           HEADER — DEEP FOREST
        ════════════════════════════════════════════ */
        .chat-page-header {
            background: var(--forest);
            border-radius: var(--radius);
            padding: 20px 28px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .chat-page-header::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(61, 184, 138, .07);
            pointer-events: none;
        }

        .chat-page-header::after {
            content: '';
            position: absolute;
            bottom: -60px;
            left: 30%;
            width: 300px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .03);
            pointer-events: none;
        }

        .chat-page-header-left {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .chat-page-header h1 {
            font-family: var(--font-d);
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-page-header h1 i {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, .12);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--accent);
        }

        .chat-page-header p {
            color: rgba(255, 255, 255, .55);
            font-size: 13px;
            padding-left: 52px;
        }

        .header-stats {
            display: flex;
            gap: 16px;
        }

        .stat-chip {
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .15);
            border-radius: 10px;
            padding: 8px 16px;
            text-align: center;
            backdrop-filter: blur(8px);
        }

        .stat-chip .num {
            font-family: var(--font-d);
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }

        .stat-chip .lbl {
            font-size: 10px;
            color: rgba(255, 255, 255, .55);
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-top: 2px;
        }

        /* ════════════════════════════════════════════
           FILTER PILLS
        ════════════════════════════════════════════ */
        .filter-strip {
            display: flex;
            gap: 8px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 8px 16px;
            border-radius: 24px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid var(--border);
            background: var(--surface);
            color: var(--muted);
            transition: all .17s ease;
            display: flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
            box-shadow: var(--shadow-sm);
        }

        .filter-pill:hover {
            border-color: var(--accent);
            color: var(--forest);
            background: var(--accent-soft);
        }

        .filter-pill.active {
            background: var(--forest);
            color: #fff;
            border-color: var(--forest);
            box-shadow: 0 4px 14px rgba(16, 44, 38, .25);
        }

        .filter-pill .cnt {
            background: rgba(255, 255, 255, .25);
            color: inherit;
            font-size: 11px;
            padding: 1px 7px;
            border-radius: 12px;
            font-weight: 700;
        }

        .filter-pill:not(.active) .cnt {
            background: var(--accent-soft);
            color: var(--forest);
        }

        /* ════════════════════════════════════════════
           CHAT LAYOUT
        ════════════════════════════════════════════ */
        .chat-layout {
            display: grid;
            grid-template-columns: 310px 1fr;
            gap: 18px;
            height: calc(100vh - 180px); /* Adjusted for better proportional sizing */
            min-height: 500px; /* Minimum height for usability */
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
            padding: 16px 18px 12px;
            border-bottom: 1px solid var(--border);
            background: var(--forest);
        }

        .conv-list-header h3 {
            font-family: var(--font-d);
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .conv-list-header h3 i {
            color: var(--accent);
        }

        .conv-search {
            width: 100%;
            padding: 9px 14px;
            border: 1.5px solid rgba(255, 255, 255, .18);
            border-radius: var(--radius-sm);
            font-size: 13px;
            outline: none;
            font-family: var(--font);
            background: rgba(255, 255, 255, .1);
            color: #fff;
            transition: all .18s ease;
        }

        .conv-search::placeholder {
            color: rgba(255, 255, 255, .45);
        }

        .conv-search:focus {
            border-color: var(--accent);
            background: rgba(255, 255, 255, .15);
        }

        .conv-items {
            flex: 1;
            overflow-y: auto;
            max-height: calc(7 * 73px); /* 7 users visible without scrolling */
            background: var(--surface);
            border-radius: 0 0 var(--radius) var(--radius) 0;
        }

        .conv-items::-webkit-scrollbar {
            width: 4px;
        }

        .conv-items::-webkit-scrollbar-track {
            background: transparent;
        }

        .conv-items::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 4px;
        }

        .conv-items::-webkit-scrollbar-thumb:hover {
            background: var(--forest-light);
        }

        .conv-section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--muted);
            padding: 12px 18px 5px;
            background: var(--surface2);
        }

        .conv-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all .15s ease;
            position: relative;
            background: var(--surface);
        }

        .conv-item:hover {
            background: var(--accent-soft);
        }

        .conv-item.active {
            background: var(--forest-pale);
            border-left: 3px solid var(--forest);
        }

        .conv-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--forest-mid), var(--forest));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(16, 44, 38, .2);
        }

        .conv-avatar.club-av {
            background: linear-gradient(135deg, #2d7a60, #1a4a38);
        }

        .conv-info {
            flex: 1;
            min-width: 0;
        }

        .conv-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
            line-height: 1.2;
        }

        .club-tag {
            background: rgba(16, 44, 38, .1);
            color: var(--forest);
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 20px;
            border: 1px solid rgba(16, 44, 38, .15);
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
            color: #aab;
            white-space: nowrap;
        }

        .conv-unread {
            background: var(--forest);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            position: absolute;
            top: 12px;
            right: 12px;
        }

        .conv-filereq {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--warning);
            position: absolute;
            top: 12px;
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

        #chatArea {
            display: flex !important;
            flex: 1;
            flex-direction: column;
        }

        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            gap: 14px;
            background: var(--surface2);
        }

        .chat-empty i {
            font-size: 52px;
            opacity: .12;
            color: var(--forest);
        }

        .chat-empty p {
            font-size: 15px;
            font-weight: 500;
        }

        /* Chat header */
        .chat-win-header {
            padding: 14px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;

.chat-empty p {
    font-size: 15px;
    font-weight: 500;
}

/* Chat header */
.chat-win-header {
    padding: 14px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 14px;
    background: var(--forest);
}
        }

        .chat-win-name {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
        }

        .chat-win-status {
            font-size: 11.5px;
            color: rgba(255, 255, 255, .55);
            margin-top: 1px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .chat-win-status::before {
            content: '';
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--accent);
            display: inline-block;
        }

        .pending-req-badge {
            margin-left: auto;
            background: rgba(245, 158, 11, .2);
            color: #fcd34d;
            border: 1px solid rgba(245, 158, 11, .35);
            padding: 6px 13px;
            border-radius: 24px;
            font-size: 12px;
            font-weight: 700;
            display: none;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all .15s ease;
        }

        .pending-req-badge:hover {
            background: rgba(245, 158, 11, .3);
            transform: translateY(-1px);
        }

        .pending-req-badge.show {
            display: flex;
        }

        /* Messages container */
        .chat-messages {
            flex: 1;
            padding: 22px 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #f8faf8;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(45, 74, 43, .025) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(111, 168, 112, .02) 0%, transparent 40%);
            margin-bottom: 0; /* Ensure no gap between messages and input */
        }

        .chat-messages::-webkit-scrollbar {
            width: 5px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--border2);
            border-radius: 5px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--forest-light);
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
            z-index: 0;
        }

        .date-sep span {
            background: #f8faf8;
            padding: 0 12px;
            position: relative;
            z-index: 1;
        }

        /* Message rows */
        .msg-row {
            display: flex;
            gap: 10px;
            margin-bottom: 2px;
            animation: msgIn .2s ease;
        }

        @keyframes msgIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg-row.mine {
            justify-content: flex-end;
        }

        .msg-row.mine .msg-av {
            order: 2;
        }

        .msg-av {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--forest-mid), var(--forest));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(16, 44, 38, .2);
        }

        .msg-row.mine .msg-av {
            background: linear-gradient(135deg, var(--accent), #2a9970);
        }

        .msg-bubble {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 11px 16px;
            border-radius: 14px 14px 14px 4px;
            box-shadow: var(--shadow-sm);
            max-width: 68%;
            word-wrap: break-word;
        }

        .msg-row.mine .msg-bubble {
            background: var(--forest);
            color: #fff;
            border: none;
            border-radius: 14px 14px 4px 14px;
            box-shadow: 0 3px 12px rgba(16, 44, 38, .25);
        }

        .msg-text {
            font-size: 13.5px;
            line-height: 1.55;
            color: inherit;
        }

        .conv-time {
            font-size: 11px;
            color: var(--muted);
            font-weight: 500;
        }

        .conv-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }

        .msg-row.mine .msg-time {
            text-align: right;
            color: rgba(255, 255, 255, .55);
        }

        /* File request bubble */
        .file-req-bubble {
            background: linear-gradient(135deg, #fffdf0, #fffae0) !important;
            border: 1.5px solid #ffe58a !important;
            border-radius: 14px 14px 14px 4px !important;
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
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(245, 158, 11, .15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #92400e;
            font-size: 13px;
        }

        .file-req-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #92400e;
        }

        .file-req-name {
            font-weight: 700;
            font-size: 13.5px;
            color: var(--text);
            margin-bottom: 6px;
        }

        .file-req-reason {
            font-size: 12.5px;
            color: var(--text-2);
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .req-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 16px;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 8px;
        }

        .req-status-pill.pending {
            background: rgba(245, 158, 11, .15);
            color: #92400e;
        }

        .req-status-pill.approved {
            background: rgba(16, 185, 129, .15);
            color: #065f46;
        }

        .req-status-pill.rejected {
            background: rgba(239, 68, 68, .15);
            color: #7f1d1d;
        }

        .approval-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
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
            gap: 5px;
        }

        .approve-btn {
            background: var(--success);
            color: #fff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, .2);
        }

        .approve-btn:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, .3);
        }

        .reject-btn {
            background: var(--danger);
            color: #fff;
            box-shadow: 0 2px 8px rgba(239, 68, 68, .2);
        }

        .reject-btn:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, .3);
        }

        /* ════════════════════════════════════════════
           MESSAGE INPUT
        ════════════════════════════════════════════ */
        .chat-input-box {
            padding: 16px 22px;
            border-top: 1px solid var(--border);
            background: var(--surface);
            display: flex;
            gap: 10px;
            align-items: flex-end;
            margin-top: auto; /* Push to bottom */
            flex-shrink: 0; /* Prevent shrinking */
        }

        .msg-input {
            flex: 1;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-family: var(--font);
            outline: none;
            background: var(--surface2);
            transition: all .18s ease;
            resize: none;
            max-height: 100px;
            min-height: 44px;
            color: var(--text);
        }

        .msg-input:focus {
            border-color: var(--forest);
            background: var(--surface);
            box-shadow: 0 0 0 3px rgba(16, 44, 38, .07);
        }

        .send-btn {
            padding: 12px 22px;
            background: var(--forest);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: all .18s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(16, 44, 38, .25);
        }

        .send-btn:hover:not(:disabled) {
            background: var(--forest-mid);
            box-shadow: 0 6px 18px rgba(16, 44, 38, .35);
            transform: translateY(-2px);
        }

        .send-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        /* ════════════════════════════════════════════
           TOAST
        ════════════════════════════════════════════ */
        #toastZone {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: var(--surface);
            border: 1.5px solid var(--border);
            padding: 13px 18px;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 280px;
            font-size: 13px;
            font-weight: 600;
            opacity: 0;
            transform: translateX(400px);
            transition: all .3s cubic-bezier(.34, 1.56, .64, 1);
            color: var(--text);
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast.success {
            border-color: var(--success);
        }

        .toast.success i {
            color: var(--success);
        }

        .toast.error {
            border-color: var(--danger);
        }

        .toast.error i {
            color: var(--danger);
        }

        /* ════════════════════════════════════════════
           EMPTY / LOADING STATES
        ════════════════════════════════════════════ */
        .conv-empty-state {
            padding: 60px 40px;
            text-align: center;
            color: var(--muted);
        }

        .empty-icon {
            font-size: 48px;
            color: var(--forest);
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .empty-desc {
            font-size: 14px;
            line-height: 1.5;
            max-width: 280px;
            margin: 0 auto;
        }

        .loading-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 20px;
            justify-content: center;
            color: var(--muted);
            font-size: 13px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .spin {
            animation: spin .8s linear infinite;
        }

        /* ════════════════════════════════════════════
           RESPONSIVE
        ════════════════════════════════════════════ */
        @media (max-width: 900px) {
            .page-content {
                padding: 90px 16px 24px; /* Adjust for mobile with hamburger */
            }

            .chat-page {
                height: calc(100vh - 114px); /* Adjust: 90px + 24px = 114px */
            }

            .chat-layout {
                grid-template-columns: 260px 1fr;
                gap: 14px;
                height: calc(100% - 50px); /* Use more space on tablet */
            }

            .header-stats {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .page-content {
                padding: 80px 12px 24px;
            }

            .chat-page {
                height: calc(100vh - 104px);
            }

            .chat-layout {
                grid-template-columns: 1fr;
                height: calc(100% - 40px); /* Use more space on mobile */
            }

            .conv-list {
                display: none;
            }

            .msg-bubble {
                max-width: 90%;
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
        if (typeof initializeNavigation === 'function') initializeNavigation();
    </script>

    <section class="page-content" id="chatbox-content">
        <div class="chat-page">

            <!-- ══ HEADER ══════════════════════════════════════════ -->
            <div class="chat-page-header">
                <div class="chat-page-header-left">
                    <h1>
                        <i class="fas fa-comments"></i>
                        Messages
                    </h1>
                    <p>Manage conversations and file requests</p>
                </div>
                <div class="header-stats">
                    <div class="stat-chip">
                        <div class="num" id="statAll">0</div>
                        <div class="lbl">Total</div>
                    </div>
                    <div class="stat-chip">
                        <div class="num" id="statUnread">0</div>
                        <div class="lbl">Unread</div>
                    </div>
                    <div class="stat-chip">
                        <div class="num" id="statFiles">0</div>
                        <div class="lbl">Requests</div>
                    </div>
                </div>
            </div>

            <!-- ══ FILTER STRIP ════════════════════════════════════ -->
            <div class="filter-strip">
                <button class="filter-pill active" onclick="filterConversations('all')">
                    <i class="fas fa-inbox"></i> All Messages
                    <span class="cnt" id="countAll">0</span>
                </button>
                <button class="filter-pill" onclick="filterConversations('unread')">
                    <i class="fas fa-bell"></i> Unread
                    <span class="cnt" id="countUnread">0</span>
                </button>
                <button class="filter-pill" onclick="filterConversations('files')">
                    <i class="fas fa-file-alt"></i> File Requests
                    <span class="cnt" id="countFiles">0</span>
                </button>
                <button class="filter-pill" onclick="filterConversations('clubs')">
                    <i class="fas fa-users"></i> Club Groups
                    <span class="cnt" id="countClubs">0</span>
                </button>
            </div>

            <!-- ══ CHAT LAYOUT ═════════════════════════════════════ -->
            <div class="chat-layout">

                <!-- Conversation List -->
                <div class="conv-list">
                    <div class="conv-list-header">
                        <h3><i class="fas fa-list-ul"></i> Conversations</h3>
                        <input type="text" class="conv-search" placeholder="Search conversations…"
                            id="convSearch" oninput="searchConversations(this.value)">
                    </div>
                    <div class="conv-items" id="convItems">
                        <div class="loading-row">
                            <i class="fas fa-spinner spin"></i> Loading…
                        </div>
                    </div>
                </div>

                <!-- Chat Window -->
                <div class="chat-win">
                    <!-- Actual chat area (hidden until conv selected) -->
                    <div id="chatArea" style="display:none;">

                        <!-- Chat Header (deep forest) -->
                        <div class="chat-win-header">
                            <div class="chat-win-avatar" id="chatAvatar">A</div>
                            <div class="chat-win-info">
                                <div class="chat-win-name" id="chatName">—</div>
                                <div class="chat-win-status" id="chatStatus">Online</div>
                            </div>
                            <div class="pending-req-badge" id="pendingReqBadge" onclick="scrollToPendingRequest()">
                                <i class="fas fa-clock"></i>
                                <span><span id="pendingReqCount">0</span> pending</span>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="chat-messages" id="chatMessages"></div>

                        <!-- Input -->
                        <div class="chat-input-box">
                            <textarea class="msg-input" id="msgInput"
                                placeholder="Type your message…" rows="1"
                                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
                            <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                                <i class="fas fa-paper-plane"></i>
                                <span>Send</span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <div id="toastZone"></div>

    <script>
        /* ════════ CONFIG ════════ */
        const API = '<?= $adminBase ?>chat_api.php';
        const FILE_REQ_API = '<?= $adminBase ?>chat_api.php';
        const ADMIN_INIT = '<?= $adminInitial ?>';

        let activeConvId = <?= $preloadConvId ?>;
        let activeStudent = {};
        let allConvs = [];
        let currentFilter = 'all';

        /* ════════ FILTER ════════ */
        function applyFilter(convs) {
            if (currentFilter === 'unread') return convs.filter(c => c.unread_count > 0);
            if (currentFilter === 'files') return convs.filter(c => c.has_file_request);
            if (currentFilter === 'clubs') return convs.filter(c => c.is_club_group);
            return convs;
        }

        function updateFilterCounts() {
            const total = allConvs.length;
            const unread = allConvs.filter(c => c.unread_count > 0).length;
            const files = allConvs.filter(c => c.has_file_request).length;
            const clubs = allConvs.filter(c => c.is_club_group).length;
            document.getElementById('countAll').textContent = total;
            document.getElementById('countUnread').textContent = unread;
            document.getElementById('countFiles').textContent = files;
            document.getElementById('countClubs').textContent = clubs;
            // stats in header
            document.getElementById('statAll').textContent = total;
            document.getElementById('statUnread').textContent = unread;
            document.getElementById('statFiles').textContent = files;
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

        /* ════════ RENDER CONVERSATIONS ════════ */
        function renderConvs(convs) {
            const box = document.getElementById('convItems');
            if (!convs.length) {
                box.innerHTML = `<div class="conv-empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div class="empty-title">No Student Conversations</div>
                    <div class="empty-desc">Students haven't started chatting with you yet. When they do, their conversations will appear here.</div>
                </div>`;
                return;
            }

            let html = '',
                lastSection = '';
            convs.forEach(c => {
                const isClub = c.is_club_group;
                const section = isClub ? 'clubs' : 'students';
                if (section !== lastSection) {
                    html += `<div class="conv-section-label">${isClub ? 'Club Groups' : 'Students'}</div>`;
                    lastSection = section;
                }
                const init = isClub ? c.club_name.substring(0, 2).toUpperCase() : c.student_name.substring(0, 1).toUpperCase();
                const active = activeConvId === c.conversation_id ? 'active' : '';
                const unread = c.unread_count > 0 ? `<div class="conv-unread">${c.unread_count}</div>` : '';
                const fileInd = c.has_file_request && !c.unread_count ? '<div class="conv-filereq"></div>' : '';
                const clubTag = isClub ? '<span class="club-tag">GROUP</span>' : '';
                html += `
                <div class="conv-item ${active}"
                     onclick="selectConversation(${c.conversation_id}, ${JSON.stringify(c).replace(/"/g,'&quot;')})">
                    <div class="conv-avatar ${isClub ? 'club-av' : ''}" title="${isClub ? 'Group Chat' : 'Student Chat'}">
                        ${init}
                        ${!isClub && c.unread_count > 0 ? '<div class="conv-avatar online"></div>' : ''}
                    </div>
                    <div class="conv-info">
                        <div class="conv-name">
                            ${escHtml(isClub ? c.club_name : c.student_name)} 
                            ${clubTag}
                            ${!isClub && c.unread_count > 0 ? '<span style="color: var(--accent); font-size: 10px; margin-left: 4px;">●</span>' : ''}
                        </div>
                        <div class="conv-preview">${escHtml(c.last_message || '—')}</div>
                        <div class="conv-meta">
                            <div class="conv-time">${escHtml(c.time_ago || '')}</div>
                            ${c.unread_count > 0 ? `<div class="conv-unread">${c.unread_count}</div>` : ''}
                        </div>
                    </div>
                    ${unread}${fileInd}
                </div>`;
            });
            box.innerHTML = html;
        }

        async function loadConversations() {
            try {
                const fd = new FormData();
                fd.append('action', 'fetch_conversations');
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
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
            } catch (error) {
                console.error('Load conversations error:', error);
            }
        }

        async function selectConversation(convId, conv) {
            activeConvId = convId;
            activeStudent = conv;

            document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
            event?.target?.closest('.conv-item')?.classList.add('active');

            const isClub = conv.is_club_group;
            document.getElementById('chatAvatar').textContent = isClub ?
                conv.club_name.substring(0, 2).toUpperCase() :
                conv.student_name.substring(0, 1).toUpperCase();
            document.getElementById('chatName').textContent = isClub ? conv.club_name : conv.student_name;
            document.getElementById('chatStatus').textContent = isClub ? 'Club Group' : 'Student';

            document.getElementById('chatArea').style.display = 'flex';

            markRead();
            await loadMessages();
        }

        /* ════════ MESSAGES ════════ */
        async function loadMessages() {
            try {
                const fd = new FormData();
                fd.append('action', 'fetch_messages');
                fd.append('conversation_id', activeConvId);
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                if (!data.success) return;
                renderMessages(data.messages);
            } catch (error) {
                console.error('Load messages error:', error);
            }
        }

        function renderMessages(msgs) {
            const box = document.getElementById('chatMessages');
            const atBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 40;
            let pendingCount = 0,
                lastDate = '';

            if (!msgs || !msgs.length) {
                box.innerHTML = `<div style="text-align:center; padding:40px; color:var(--muted); font-size:13px; opacity:.7;">
                    <i class="fas fa-comments" style="font-size:32px; display:block; margin-bottom:10px; opacity:.2;"></i>
                    No messages yet. Start the conversation!
                </div>`;
                return;
            }

            box.innerHTML = msgs.map(m => {
                const isMine = m.sender_role === 'admin';
                const init = isMine ? ADMIN_INIT : (activeStudent.avatar_letter || activeStudent.student_name?.substring(0, 1) || '?');
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
            <span class="req-status-pill ${s.cls}"><i class="fas ${s.icon}"></i> ${s.label}</span>
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

        /* ════════ FILE REQUEST APPROVAL ════════ */
        async function processFileRequest(requestId, action) {
            const fd = new FormData();
            fd.append('action', 'process_file_request');
            fd.append('request_id', requestId);
            fd.append('decision', action);
            fd.append('csrf_token', getCSRFToken());
            const res = await fetch(API, {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                toast(`${action === 'approve' ? '✅ Approved' : '❌ Rejected'}: ${data.student_name || 'Request'}`, 'success');
                await loadMessages();
                await refreshConvList();
            } else {
                toast(data.message || 'Action failed.', 'error');
            }
        }

        /* ════════ SEND MESSAGE ════════ */
        async function sendMessage() {
            try {
                const input = document.getElementById('msgInput');
                const text = input.value.trim();
                if (!text || !activeConvId) return;

                const btn = document.getElementById('sendBtn');
                btn.disabled = true;
                input.value = '';
                input.style.height = '';

                const fd = new FormData();
                fd.append('action', 'send_message');
                fd.append('conversation_id', activeConvId);
                fd.append('message', text);
                fd.append('csrf_token', getCSRFToken());

                const res = await fetch(API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                btn.disabled = false;

                if (data.success) {
                    await loadMessages();
                    await refreshConvList();
                } else {
                    input.value = text;
                    toast(data.message || 'Failed to send message.', 'error');
                }
            } catch (error) {
                console.error('Send message error:', error);
                const btn = document.getElementById('sendBtn');
                const input = document.getElementById('msgInput');
                
                if (btn) btn.disabled = false;
                if (input && text) input.value = text;
                
                toast('Network error. Please check your connection and try again.', 'error');
            }
        }

        function markRead() {
            try {
                if (!activeConvId) return;
                const fd = new FormData();
                fd.append('action', 'mark_read');
                fd.append('conversation_id', activeConvId);
                fd.append('csrf_token', getCSRFToken());
                
                fetch(API, {
                    method: 'POST',
                    body: fd
                }).catch(error => {
                    console.error('Mark read error:', error);
                });
            } catch (error) {
                console.error('Mark read error:', error);
            }
        }

        async function refreshConvList() {
            try {
                const fd = new FormData();
                fd.append('action', 'fetch_conversations');
                fd.append('csrf_token', getCSRFToken());
                
                const res = await fetch(API, {
                    method: 'POST',
                    body: fd
                });
                
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                const data = await res.json();
                if (!data.success) return;
                allConvs = data.conversations;
                updateFilterCounts();
                renderConvs(applyFilter(allConvs));
            } catch (error) {
                console.error('Refresh conversation list error:', error);
            }
        }

        /* ════════ HELPERS ════════ */
        function escHtml(s) {
            if (s == null) return '';
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function getCSRFToken() {
            // Get CSRF token from meta tag or generate one
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                return metaTag.getAttribute('content');
            }
            
            // Fallback: make a request to get token
            console.warn('CSRF token not found in meta tag');
            return '';
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
            t.innerHTML = `<i class="fas ${type==='success'?'fa-check-circle':'fa-exclamation-circle'}"></i><span>${escHtml(msg)}</span>`;
            zone.appendChild(t);
            requestAnimationFrame(() => t.classList.add('show'));
            setTimeout(() => {
                t.classList.remove('show');
                setTimeout(() => t.remove(), 300);
            }, 3500);
        }

        // Auto-grow textarea
        document.addEventListener('DOMContentLoaded', () => {
            const ta = document.getElementById('msgInput');
            if (ta) {
                ta.addEventListener('input', () => {
                    ta.style.height = '';
                    ta.style.height = Math.min(ta.scrollHeight, 100) + 'px';
                });
            }
            loadConversations();
        });

        setInterval(refreshConvList, 15000);
    </script>
</body>

</html>