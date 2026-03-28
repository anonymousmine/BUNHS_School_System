<?php
require_once '../session_config.php';  // unified session params — must replace session_start()
require_once '../db_connection.php';

// Simple session check (same as other admin pages)
$is_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['admin', 'sub-admin']))
    || (isset($_SESSION['admin_id']));

// Force test session for debugging (remove this in production)
$_SESSION['user_id'] = 'test_admin';
$_SESSION['user_type'] = 'admin';
$_SESSION['username'] = 'Test Admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['created_at'] = time();
$_SESSION['last_activity'] = time();
$_SESSION['session_timeout'] = 3600;
$is_logged_in = true;

// Redirect if still not logged in
if (!$is_logged_in) {
    header('Location: ../index.php');
    exit;
}

// Load helper classes (but don't use strict auth for now)
require_once __DIR__ . '/helpers/auth_helper.php';
require_once __DIR__ . '/helpers/permission_manager.php';
require_once __DIR__ . '/helpers/database_helper.php';
require_once __DIR__ . '/helpers/validation_helper.php';

// Set user variables
$user_type = $_SESSION['user_type'] ?? 'admin';
$user_role = AuthHelper::getCurrentRole();
$user_permissions = PermissionManager::getRolePermissions();

// Initialize database helper
try {
    $db = new DatabaseHelper($conn, false);
} catch (Exception $e) {
    error_log("Database helper error: " . $e->getMessage());
    // Continue without database helper
    $db = null;
}

// Load settings with role awareness
$configPath = __DIR__ . '/config/settings.json';
$settings = [];
if (file_exists($configPath)) {
    $settings = json_decode(file_get_contents($configPath), true) ?: [];
}

// Load settings from database
if ($db) {
    try {
        $db_settings = $db->fetchAll("SELECT setting_key, setting_value FROM school_settings");
        foreach ($db_settings as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Failed to load settings from database: " . $e->getMessage());
    }
}

// Dynamic data from DB
$phpVersion = phpversion();
$auditLogs = [];
if ($db) {
    try {
        $logsRes = $db->fetchAll("SELECT * FROM student_logs ORDER BY timestamp DESC LIMIT 6");
        $auditLogs = $logsRes;
    } catch (Exception $e) {
        error_log("Failed to load audit logs: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — School Admin Dashboard</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="../overall_body.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ─── CSS Variables ─────────────────────────────────── */
        :root {
            --bg-page: #f0f2f7;
            --bg-card: #ffffff;
            --bg-card-alt: #f8f9fc;
            --border: #e4e8f0;
            --border-focus: #5a7a45;
            --text-primary: #1a1d2e;
            --text-secondary: #5a6278;
            --text-muted: #9aa0b5;
            --accent: #6b8f47;
            --accent-soft: #eef4e8;
            --accent-hover: #4e6b32;
            --danger: #e84b4b;
            --danger-soft: #fef1f1;
            --success: #2ecc85;
            --success-soft: #edfbf5;
            --warning: #f0a328;
            --warning-soft: #fff8ec;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .05), 0 1px 2px rgba(0, 0, 0, .04);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, .07);
            --shadow-card: 0 2px 8px rgba(0, 0, 0, .06), 0 0 0 1px rgba(0, 0, 0, .04);
            --radius-sm: 7px;
            --radius-md: 11px;
            --radius-lg: 15px;
            --font: 'DM Sans', 'Plus Jakarta Sans', sans-serif;
            --font-mono: 'DM Mono', monospace;
            --transition: .16s ease;
        }

        [data-theme="dark"] {
            --bg-page: #12141f;
            --bg-card: #1c1f2e;
            --bg-card-alt: #242738;
            --border: #2e3248;
            --text-primary: #e8eaf5;
            --text-secondary: #9aa0b5;
            --text-muted: #5a6278;
            --accent-soft: #1e2b14;
            --danger-soft: #2e1a1a;
            --success-soft: #152b22;
            --warning-soft: #2b2010;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .2);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .3);
            --shadow-card: 0 2px 8px rgba(0, 0, 0, .25), 0 0 0 1px rgba(255, 255, 255, .04);
        }

        /* ─── Base Reset ───────────────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font);
            background: var(--light-color);
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── Main layout — .main is opened by admin_nav.php */
        .main {
            margin-left: 240px;
            min-height: 100vh;
            width: calc(100% - 240px);
            box-sizing: border-box;
        }

        /* PAGE CONTENT — fills the remaining space after sidebar */
        .page-content {
            padding: 28px 32px 48px;
            width: 100%;
            min-height: calc(100vh - 72px);
            box-sizing: border-box;
            background: #f8fafc;
        }

        /* Settings page specific styles */
        .page-content.settings {
            background: #f8fafc;
        }

        /* Layout: main content wrapper ────────────────── */
        .settings-wrapper {
            width: 100%;
            max-width: 100%;
            padding: 0;
        }

        .settings-json {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            pointer-events: none;
            height: 1px;
            width: 1px;
        }

        /* ─── Page Header ──────────────────────────────────── */
        .settings-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .settings-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .settings-header-icon {
            width: 44px;
            height: 44px;
            background: var(--accent);
            border-radius: var(--radius-md);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 18px;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(107, 143, 71, .35);
        }

        .settings-header h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -.4px;
            line-height: 1.2;
        }

        .settings-header p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 3px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* ─── Settings Nav Tabs ────────────────────────────── */
        .settings-nav {
            display: flex;
            gap: 2px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 5px;
            margin-bottom: 26px;
            overflow-x: auto;
            scrollbar-width: none;
            flex-wrap: wrap;
            box-shadow: var(--shadow-sm);
        }

        .settings-nav::-webkit-scrollbar {
            display: none;
        }

        .s-tab {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            border: none;
            background: transparent;
            border-radius: var(--radius-sm);
            font-family: var(--font);
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all var(--transition);
            white-space: nowrap;
            letter-spacing: -.01em;
        }

        .s-tab i {
            font-size: 12px;
            opacity: .85;
        }

        .s-tab:hover {
            background: var(--bg-card-alt);
            color: var(--text-primary);
        }

        .s-tab.active {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 2px 8px rgba(107, 143, 71, .28);
            font-weight: 600;
        }

        .s-tab.active i {
            opacity: 1;
        }

        /* ─── Settings Sections ────────────────────────────── */
        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
            animation: fadeUp .18s ease both;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ─── Card ─────────────────────────────────────────── */
        .s-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 18px;
            box-shadow: var(--shadow-card);
            overflow: hidden;
            transition: box-shadow var(--transition);
        }

        .s-card:hover {
            box-shadow: 0 4px 18px rgba(0, 0, 0, .09), 0 0 0 1px rgba(0, 0, 0, .05);
        }

        .s-card-header {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-card-alt);
        }

        .s-card-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            display: grid;
            place-items: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .s-card-header h3 {
            font-size: 13.5px;
            font-weight: 650;
            color: var(--text-primary);
            letter-spacing: -.01em;
        }

        .s-card-header p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .s-card-body {
            padding: 4px 0;
        }

        /* ─── Setting Row ──────────────────────────────────── */
        .s-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 22px;
            gap: 20px;
            border-bottom: 1px solid var(--border);
            transition: background var(--transition);
        }

        .s-row:last-child {
            border-bottom: none;
        }

        .s-row:hover {
            background: #fafbfd;
        }

        [data-theme="dark"] .s-row:hover {
            background: var(--bg-card-alt);
        }

        .s-row-label {
            flex: 1;
            min-width: 0;
        }

        .s-row-label strong {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            display: block;
            letter-spacing: -.01em;
        }

        .s-row-label span {
            font-size: 11.5px;
            color: var(--text-muted);
            display: block;
            margin-top: 2px;
            line-height: 1.5;
        }

        .s-row-control {
            flex-shrink: 0;
        }

        /* ─── Toggle Switch ────────────────────────────────── */
        .s-toggle {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
        }

        .s-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .s-toggle-slider {
            position: absolute;
            inset: 0;
            background: #d8dce8;
            border-radius: 999px;
            cursor: pointer;
            transition: background .2s ease;
        }

        .s-toggle-slider::before {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            left: 3px;
            top: 3px;
            background: #fff;
            border-radius: 50%;
            transition: transform .2s ease;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .18);
        }

        .s-toggle input:checked+.s-toggle-slider {
            background: var(--accent);
        }

        .s-toggle input:checked+.s-toggle-slider::before {
            transform: translateX(18px);
        }

        /* ─── Select / Input ───────────────────────────────── */
        .s-select,
        .s-input {
            padding: 7.5px 11px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg-card);
            color: var(--text-primary);
            font-family: var(--font);
            font-size: 13px;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
            min-width: 180px;
        }

        .s-select:hover,
        .s-input:hover {
            border-color: #c5cdd8;
        }

        .s-select:focus,
        .s-input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(107, 143, 71, .12);
        }

        .s-input-sm {
            min-width: 100px;
        }

        /* ─── Radio Group ──────────────────────────────────── */
        .s-radio-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .s-radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition);
            font-size: 13px;
            font-weight: 500;
        }

        .s-radio-option:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
        }

        .s-radio-option input[type="radio"] {
            accent-color: var(--accent);
        }

        .s-radio-option.selected {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: var(--accent);
        }

        /* ─── Checkbox Grid ────────────────────────────────── */
        .s-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(158px, 1fr));
            gap: 7px;
        }

        .s-checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 12.5px;
            font-weight: 500;
            transition: all var(--transition);
        }

        .s-checkbox-item:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
        }

        .s-checkbox-item input[type="checkbox"] {
            accent-color: var(--accent);
        }

        /* ─── Buttons ──────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8.5px 17px;
            border: none;
            border-radius: var(--radius-sm);
            font-family: var(--font);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            letter-spacing: -.01em;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 2px 8px rgba(107, 143, 71, .28);
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            box-shadow: 0 4px 14px rgba(107, 143, 71, .38);
            transform: translateY(-1px);
        }

        .btn-ghost {
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            background: var(--bg-card-alt);
            color: var(--text-primary);
            border-color: #c5cdd8;
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
            box-shadow: 0 2px 6px rgba(232, 75, 75, .2);
        }

        .btn-danger:hover {
            background: #c93a3a;
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success);
            color: #fff;
        }

        .btn-success:hover {
            background: #26b872;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* ─── Badge ────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2.5px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .01em;
        }

        .badge-blue {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .badge-green {
            background: var(--success-soft);
            color: var(--success);
        }

        .badge-orange {
            background: var(--warning-soft);
            color: var(--warning);
        }

        .badge-red {
            background: var(--danger-soft);
            color: var(--danger);
        }

        /* ─── Progress Bar ─────────────────────────────────── */
        .s-progress {
            height: 6px;
            background: var(--border);
            border-radius: 999px;
            overflow: hidden;
        }

        .s-progress-bar {
            height: 100%;
            border-radius: 999px;
            transition: width .5s ease;
        }

        /* ─── Table ────────────────────────────────────────── */
        .s-table-wrap {
            max-height: 280px;
            overflow-y: auto;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        .s-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .s-table thead tr {
            background: var(--bg-card-alt);
        }

        .s-table th,
        .s-table td {
            padding: 10px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .s-table th {
            font-weight: 600;
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .s-table tbody tr:hover {
            background: #fafbfd;
        }

        [data-theme="dark"] .s-table tbody tr:hover {
            background: var(--bg-card-alt);
        }

        .s-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ─── Two-column grid ──────────────────────────────── */
        .s-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        @media (max-width: 700px) {
            .s-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        /* ─── Stat Cards ───────────────────────────────────── */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px 14px;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--transition), transform var(--transition);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }

        .stat-card .stat-icon {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .stat-card .stat-num {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            letter-spacing: -.03em;
        }

        .stat-card .stat-label {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 5px;
        }

        /* ─── Role Table ───────────────────────────────────── */
        .role-matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .role-matrix th,
        .role-matrix td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            text-align: center;
        }

        .role-matrix th:first-child,
        .role-matrix td:first-child {
            text-align: left;
            font-weight: 600;
        }

        .role-matrix thead {
            background: var(--bg-card-alt);
        }

        .role-matrix thead th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--text-muted);
        }

        .role-matrix i.fa-check {
            color: var(--success);
        }

        .role-matrix i.fa-xmark {
            color: var(--border);
        }

        /* ─── Encryption Key input ─────────────────────────── */
        .key-field-wrap {
            position: relative;
        }

        .key-field-wrap .s-input {
            padding-right: 40px;
            width: 100%;
            font-family: var(--font-mono);
            letter-spacing: .04em;
            color: var(--text-muted);
            font-size: 12.5px;
        }

        .key-field-wrap .eye-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            transition: color var(--transition);
        }

        .key-field-wrap .eye-btn:hover {
            color: var(--text-primary);
        }

        /* ─── File Upload Zone ─────────────────────────────── */
        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 28px 20px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
            cursor: pointer;
            transition: all var(--transition);
        }

        .upload-zone:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: var(--accent);
        }

        .upload-zone i {
            font-size: 26px;
            display: block;
            margin-bottom: 10px;
            opacity: .7;
        }

        .upload-zone:hover i {
            opacity: 1;
        }

        /* ─── Save Bar ─────────────────────────────────────── */
        .save-bar {
            position: sticky;
            bottom: 0;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            padding: 13px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, .06);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
            flex-wrap: wrap;
        }

        .save-bar-note {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .save-bar-note i {
            color: var(--warning);
        }

        /* ─── Info Text / Read-only ────────────────────────── */
        .s-info-value {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-primary);
            background: var(--bg-card-alt);
            padding: 6.5px 13px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-family: var(--font-mono);
        }

        /* ─── Full-width row ───────────────────────────────── */
        .s-row-full {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .s-row-full .s-row-control {
            width: 100%;
        }

        /* ─── Scrollable table wrapper ─────────────────────── */
        .audit-wrap {
            max-height: 300px;
            overflow-y: auto;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        /* ─── Section divider label ────────────────────────── */
        .s-section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: var(--text-muted);
            padding: 0 2px;
            margin: 22px 0 10px;
        }

        /* ─── Toast Notification ───────────────────────────── */
        #toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--text-primary);
            color: var(--bg-card);
            padding: 11px 18px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 500;
            opacity: 0;
            transform: translateY(10px);
            transition: all .22s ease;
            pointer-events: none;
            z-index: 9999;
            max-width: 320px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .15);
        }

        #toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* ─── Responsive tweaks ────────────────────────────── */
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
            }

            .page-content {
                padding: 22px 20px 40px;
                width: 100%;
            }
        }

        @media (max-width: 560px) {
            .page-content {
                padding: 14px 14px 36px;
            }

            .s-row {
                flex-direction: column;
                align-items: flex-start;
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

    <script>

        // Load settings
        loadSettings();
        // Auto-save on change
        initAutoSave();

        function initializeDropdowns() {
            // Initialize Navigation Functionality (already handled by admin_nav.php)
            // No need for dropdown path fixing since PHP handles it correctly
            if (typeof initializeNavigation === 'function') {
                initializeNavigation();
            }
        }
    </script>

    <!-- ══════════════════════════════════════════════════════════
         PAGE CONTENT
    ═══════════════════════════════════════════════════════════ -->
    <section class="page-content settings" id="settings-content" style="display: block !important;">
        <div class="settings-container">
                <!-- ── Header ── -->
                <div class="settings-header">
                    <div class="settings-header-left">
                        <div class="settings-header-icon">
                            <i class="fa-solid fa-gear"></i>
                        </div>
                        <div>
                            <h1>Admin Settings</h1>
                            <p>Manage system preferences, security, school configuration, and more.</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-ghost btn-sm" onclick="resetDefaults()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                        <button class="btn btn-primary btn-sm" onclick="saveSettings()"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
                    </div>
                </div>

                <!-- ── Nav Tabs (Role-Aware) ── -->
                <nav class="settings-nav" id="settingsNav">
                    <?php
                    // Define all possible tabs with their permissions
                    $all_tabs = [
                        'appearance' => ['icon' => 'fa-palette', 'label' => 'Appearance', 'permission' => null],
                        'locale' => ['icon' => 'fa-earth-asia', 'label' => 'Language', 'permission' => null],
                        'security' => ['icon' => 'fa-shield-halved', 'label' => 'Security', 'permission' => 'system.security'],
                        'system' => ['icon' => 'fa-server', 'label' => 'System', 'permission' => 'system.settings'],
                        'database' => ['icon' => 'fa-database', 'label' => 'Database', 'permission' => 'system.database'],
                        'school' => ['icon' => 'fa-school', 'label' => 'School', 'permission' => 'system.school'],
                        'admin' => ['icon' => 'fa-user-shield', 'label' => 'Admin', 'permission' => 'system.admin'],
                        'finance' => ['icon' => 'fa-peso-sign', 'label' => 'Finance', 'permission' => 'finance.view'],
                        'files' => ['icon' => 'fa-folder-open', 'label' => 'Files', 'permission' => 'system.files'],
                        'clubs' => ['icon' => 'fa-people-group', 'label' => 'Clubs', 'permission' => 'clubs.view'],
                        'overview' => ['icon' => 'fa-chart-pie', 'label' => 'Overview', 'permission' => 'system.reports']
                    ];
                    
                    // Filter tabs based on user permissions
                    $available_tabs = [];
                    foreach ($all_tabs as $section_id => $tab_info) {
                        if (PermissionManager::canAccessSettingsSection($section_id)) {
                            $available_tabs[$section_id] = $tab_info;
                        }
                    }
                    
                    // Render tabs
                    $first_tab = true;
                    foreach ($available_tabs as $section_id => $tab_info):
                        $active_class = $first_tab ? 'active' : '';
                        $first_tab = false;
                    ?>
                        <button class="s-tab <?php echo $active_class; ?>" data-section="<?php echo $section_id; ?>">
                            <i class="fa-solid <?php echo $tab_info['icon']; ?>"></i> <?php echo $tab_info['label']; ?>
                        </button>
                    <?php endforeach; ?>
                </nav>

                <!-- Debug Information (moved after tabs are defined) -->
                <div class="s-card" style="margin-bottom:20px;">
                    <h3>🔍 Debug Information</h3>
                    <p><strong>User Type:</strong> <span class="badge badge-<?php echo $user_type === 'admin' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($user_type); ?></span></p>
                    <p><strong>Available Tabs:</strong> <?php echo count($available_tabs); ?> tabs</p>
                    <p><strong>Can Access Admin:</strong> <?php echo PermissionManager::canAccessSettingsSection('admin') ? '✅ YES' : '❌ NO'; ?></p>
                    <p><strong>Database Connected:</strong> <?php echo $db ? '✅ YES' : '❌ NO'; ?></p>
                    <p><strong>Settings Loaded:</strong> <?php echo count($settings); ?> settings</p>
                    <p><strong>Tab List:</strong> <?php echo implode(', ', array_keys($available_tabs)); ?></p>
                    
                    <h4>Permission Test Results:</h4>
                    <?php
                    $test_sections = ['appearance', 'security', 'system', 'admin', 'finance'];
                    foreach ($test_sections as $section) {
                        $can_access = PermissionManager::canAccessSettingsSection($section);
                        echo "<p><strong>$section:</strong> " . ($can_access ? '✅ ACCESS' : '❌ DENIED') . "</p>";
                    }
                    ?>
                </div>

                <!-- ══════════════════════════════════════════════════
             1. APPEARANCE
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section active" id="sec-appearance">

                    <!-- Theme -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--accent-soft);color:var(--accent)"><i class="fa-solid fa-sun"></i></div>
                            <div>
                                <h3>Theme Options</h3>
                                <p>Choose how the admin interface looks</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row s-row-full">
                                <div class="s-row-label">
                                    <strong>Color Theme</strong>
                                    <span>Select a display theme that matches your working environment</span>
                                </div>
                                <div class="s-row-control">
                                    <div class="s-radio-group" id="themeRadioGroup">
                                        <label class="s-radio-option selected">
                                            <input type="radio" name="theme" value="light" checked> <i class="fa-solid fa-sun"></i> Light Mode
                                        </label>
                                        <label class="s-radio-option">
                                            <input type="radio" name="theme" value="dark"> <i class="fa-solid fa-moon"></i> Dark Mode
                                        </label>
                                        <label class="s-radio-option">
                                            <input type="radio" name="theme" value="system"> <i class="fa-solid fa-circle-half-stroke"></i> System Default
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Compact Table Mode</strong>
                                    <span>Reduces row spacing in tables for denser data display</span>
                                </div>
                                <div class="s-row-control">
                                    <label class="s-toggle"><input type="checkbox" id="compactMode"><span class="s-toggle-slider"></span></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pinned Shortcuts -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--warning-soft);color:var(--warning)"><i class="fa-solid fa-thumbtack"></i></div>
                            <div>
                                <h3>Pinned Shortcuts</h3>
                                <p>Quick navigation items shown in the dashboard header bar</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row s-row-full">
                                <div class="s-row-label">
                                    <strong>Select Quick Access Pages</strong>
                                    <span>These will appear as shortcut buttons in the navigation</span>
                                </div>
                                <div class="s-row-control">
                                    <div class="s-checkbox-grid">
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-chart-bar"></i> Reports</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-user-graduate"></i> Students</label>
                                        <label class="s-checkbox-item"><input type="checkbox"> <i class="fa-solid fa-people-group"></i> Clubs</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-peso-sign"></i> Finance</label>
                                        <label class="s-checkbox-item"><input type="checkbox"> <i class="fa-solid fa-hard-drive"></i> File Storage</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--success-soft);color:var(--success)"><i class="fa-solid fa-bell"></i></div>
                            <div>
                                <h3>Notification Preferences</h3>
                                <p>Control how and where system alerts are delivered</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Email Notifications</strong>
                                    <span>Receive system alerts and reports via email</span>
                                </div>
                                <div class="s-row-control">
                                    <label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>In-App Notifications</strong>
                                    <span>Show alerts inside the dashboard interface</span>
                                </div>
                                <div class="s-row-control">
                                    <label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Push Notifications</strong>
                                    <span>Browser push alerts for urgent events (optional)</span>
                                </div>
                                <div class="s-row-control">
                                    <label class="s-toggle"><input type="checkbox"><span class="s-toggle-slider"></span></label>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /appearance -->

                <!-- ══════════════════════════════════════════════════
             2. LANGUAGE & LOCALIZATION
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-locale">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--accent-soft);color:var(--accent)"><i class="fa-solid fa-language"></i></div>
                            <div>
                                <h3>Language &amp; Localization</h3>
                                <p>Configure regional formats used throughout the system</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>System Language</strong>
                                    <span>Interface language for all admin users</span>
                                </div>
                                <div class="s-row-control">
                                    <select class="s-select">
                                        <option value="en" selected>🇺🇸 English</option>
                                        <option value="tl">🇵🇭 Tagalog</option>
                                    </select>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Date Format</strong>
                                    <span>How dates are displayed across records and reports</span>
                                </div>
                                <div class="s-row-control">
                                    <select class="s-select">
                                        <option value="MMM DD, YYYY" selected>MMM DD, YYYY (Apr 15, 2025)</option>
                                        <option value="MM/DD/YYYY">MM/DD/YYYY (04/15/2025)</option>
                                        <option value="DD/MM/YYYY">DD/MM/YYYY (15/04/2025)</option>
                                        <option value="YYYY-MM-DD">YYYY-MM-DD (2025-04-15)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Timezone</strong>
                                    <span>Used for scheduling, logs, and report timestamps</span>
                                </div>
                                <div class="s-row-control">
                                    <select class="s-select">
                                        <option value="Asia/Manila" selected>Asia/Manila (UTC+8)</option>
                                        <option value="UTC">UTC (Coordinated Universal Time)</option>
                                        <option value="America/New_York">America/New_York (UTC-5)</option>
                                        <option value="Europe/London">Europe/London (UTC+0/+1)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /locale -->

                <!-- ══════════════════════════════════════════════════
             3. SECURITY
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-security">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--danger-soft);color:var(--danger)"><i class="fa-solid fa-lock"></i></div>
                            <div>
                                <h3>Security Settings</h3>
                                <p>Manage authentication, session, and data privacy controls</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Two-Factor Authentication (2FA)</strong>
                                    <span>Require a second verification step for all admin accounts</span>
                                </div>
                                <div class="s-row-control">
                                    <label class="s-toggle"><input type="checkbox"><span class="s-toggle-slider"></span></label>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Minimum Password Length</strong>
                                    <span>Enforce a minimum character count for user passwords (8–12)</span>
                                </div>
                                <div class="s-row-control">
                                    <input type="number" class="s-input s-input-sm" value="8" min="8" max="12">
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Session Lifetime</strong>
                                    <span>Idle session expiration in seconds (recommended: 3600)</span>
                                </div>
                                <div class="s-row-control">
                                    <input type="number" class="s-input s-input-sm" value="3600" min="300" step="300">
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Privacy Mode</strong>
                                    <span>Blurs sensitive student information when screen sharing is active</span>
                                </div>
                                <div class="s-row-control">
                                    <label class="s-toggle"><input type="checkbox"><span class="s-toggle-slider"></span></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /security -->

                <!-- ══════════════════════════════════════════════════
             4. SYSTEM & SERVER
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-system">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--warning-soft);color:var(--warning)"><i class="fa-solid fa-server"></i></div>
                            <div>
                                <h3>System &amp; Server Settings</h3>
                                <p>PHP environment configuration and upload limits</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>PHP Version</strong>
                                    <span>Server-side scripting engine version</span>
                                </div>
                                <div class="s-row-control">
                                    <span class="s-info-value"><?php echo phpversion() ?: '8.x'; ?></span>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Memory Limit</strong>
                                    <span>Maximum memory allocated per PHP script</span>
                                </div>
                                <div class="s-row-control">
                                    <input type="text" class="s-input s-input-sm" value="512M">
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Max Execution Time</strong>
                                    <span>Maximum seconds a PHP script may run</span>
                                </div>
                                <div class="s-row-control">
                                    <input type="number" class="s-input s-input-sm" value="120" min="30">
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>upload_max_filesize</strong>
                                    <span>Maximum file size allowed per upload</span>
                                </div>
                                <div class="s-row-control">
                                    <input type="text" class="s-input s-input-sm" value="50M">
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>post_max_size</strong>
                                    <span>Maximum size of POST data the server will accept</span>
                                </div>
                                <div class="s-row-control">
                                    <input type="text" class="s-input s-input-sm" value="50M">
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Error Reporting</strong>
                                    <span>Toggle between development (ON) and production (OFF) error display</span>
                                </div>
                                <div class="s-row-control">
                                    <select class="s-select">
                                        <option value="on">ON — Development</option>
                                        <option value="off" selected>OFF — Production</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /system -->

                <!-- ══════════════════════════════════════════════════
             5. DATABASE & SECURITY MANAGEMENT
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-database">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--success-soft);color:var(--success)"><i class="fa-solid fa-database"></i></div>
                            <div>
                                <h3>Database &amp; Security Management</h3>
                                <p>Backups, SSL, maintenance mode, and encryption settings</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Automatic Database Backups</strong>
                                    <span>Schedule regular backups of all school data</span>
                                </div>
                                <div class="s-row-control">
                                    <select class="s-select">
                                        <option value="daily" selected>Daily Backup</option>
                                        <option value="weekly">Weekly Full Backup</option>
                                        <option value="none">Disabled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>HTTPS / SSL Enforcement</strong>
                                    <span>Redirect all HTTP traffic to HTTPS for secure access</span>
                                </div>
                                <div class="s-row-control">
                                    <label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label">
                                    <strong>Maintenance Mode</strong>
                                    <span>Temporarily disable public access while performing updates</span>
                                </div>
                                <div class="s-row-control">
                                    <label class="s-toggle"><input type="checkbox"><span class="s-toggle-slider"></span></label>
                                </div>
                            </div>
                            <div class="s-row s-row-full">
                                <div class="s-row-label">
                                    <strong>System Encryption Key</strong>
                                    <span>Used for password hashing and sensitive data encryption. Keep this secret.</span>
                                </div>
                                <div class="s-row-control">
                                    <div class="key-field-wrap">
                                        <input type="password" class="s-input" id="encKey" value="<?= htmlspecialchars(getenv('STRIPE_KEY') ?: '') ?>" placeholder="Enter encryption key">
                                        <button class="eye-btn" onclick="toggleKey()" type="button"><i class="fa-solid fa-eye" id="eyeIcon"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /database -->

                <!-- ══════════════════════════════════════════════════
             6. SCHOOL SETTINGS
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-school">
                    <!-- ── Our Impact Settings (DB-backed) ────────────────────────── -->
                    <div class="s-card" style="margin-bottom:18px;">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--accent-soft);color:var(--accent)"><i class="fa-solid fa-star"></i></div>
                            <div>
                                <h3>About Page — Our Impact Section</h3>
                                <p>These values display on the public About page under "Our Impact"</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row" style="flex-direction:column;align-items:flex-start;gap:8px;">
                                <div class="s-row-label">
                                    <strong>School Founded Year</strong>
                                    <span>Used to calculate "X+ Years Excellence" automatically</span>
                                </div>
                                <input type="number" class="s-input s-input-sm" id="school_founded_year"
                                    value="<?php
                                            $fy_r = $conn->query("SELECT setting_value FROM school_settings WHERE setting_key='school_founded_year' LIMIT 1");
                                            echo ($fy_r && $fy_r->num_rows) ? htmlspecialchars($fy_r->fetch_assoc()['setting_value']) : '2017';
                                            ?>"
                                    min="1900" max="<?php echo date('Y'); ?>" placeholder="e.g. 2017" style="max-width:160px;">
                            </div>
                            <div class="s-row" style="flex-direction:column;align-items:flex-start;gap:8px;margin-top:14px;">
                                <div class="s-row-label">
                                    <strong>"Measurable Excellence in Education" Subtitle</strong>
                                    <span>The descriptive text shown under "Our Impact" heading on about.php</span>
                                </div>
                                <textarea class="s-input" id="impact_subtitle" rows="4" style="width:100%;resize:vertical;"><?php
                                                                                                                            $is_r = $conn->query("SELECT setting_value FROM school_settings WHERE setting_key='impact_subtitle' LIMIT 1");
                                                                                                                            echo ($is_r && $is_r->num_rows) ? htmlspecialchars($is_r->fetch_assoc()['setting_value']) : '';
                                                                                                                            ?></textarea>
                            </div>
                            <div style="margin-top:12px;text-align:right;">
                                <button class="btn-save-impact" onclick="saveImpactSettings()" style="padding:9px 20px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius-md);font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Impact Settings
                                </button>
                            </div>
                            <div id="impact-save-msg" style="display:none;margin-top:10px;padding:9px 14px;border-radius:8px;font-size:13px;font-weight:600;"></div>
                        </div>
                    </div>

                    <div class="s-grid-2">
                        <!-- Academic Year -->
                        <div class="s-card">
                            <div class="s-card-header">
                                <div class="s-card-icon" style="background:var(--accent-soft);color:var(--accent)"><i class="fa-solid fa-calendar-days"></i></div>
                                <div>
                                    <h3>Academic Year</h3>
                                    <p>Set the current school year</p>
                                </div>
                            </div>
                            <div class="s-card-body">
                                <div class="s-row">
                                    <div class="s-row-label"><strong>Active Year</strong><span>Affects enrollment, records, and reports</span></div>
                                    <div class="s-row-control">
                                        <select class="s-select">
                                            <option>2024–2025</option>
                                            <option selected>2025–2026</option>
                                            <option>2026–2027</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Logo Upload -->
                        <div class="s-card">
                            <div class="s-card-header">
                                <div class="s-card-icon" style="background:var(--warning-soft);color:var(--warning)"><i class="fa-solid fa-image"></i></div>
                                <div>
                                    <h3>School Logo &amp; Branding</h3>
                                    <p>Upload logo shown in reports and header</p>
                                </div>
                            </div>
                            <div class="s-card-body">
                                <div class="s-row s-row-full">
                                    <div class="s-row-control">
                                        <div class="upload-zone" onclick="document.getElementById('logoUpload').click()">
                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                            Click to upload school logo<br>
                                            <small style="color:var(--text-muted)">PNG, JPG — max 2MB</small>
                                        </div>
                                        <input type="file" id="logoUpload" accept="image/*" style="display:none" onchange="handleLogoUpload(event)">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--success-soft);color:var(--success)"><i class="fa-solid fa-envelope"></i></div>
                            <div>
                                <h3>SMTP Email Configuration</h3>
                                <p>Used for reports, announcements, and password reset emails</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label"><strong>SMTP Host</strong><span>Your email server hostname</span></div>
                                <div class="s-row-control"><input type="text" class="s-input" placeholder="smtp.gmail.com"></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>SMTP Port</strong><span>Usually 587 (TLS) or 465 (SSL)</span></div>
                                <div class="s-row-control"><input type="number" class="s-input s-input-sm" value="587"></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Email Username</strong><span>Sending email address</span></div>
                                <div class="s-row-control"><input type="email" class="s-input" placeholder="admin@school.edu.ph"></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Email Password</strong><span>App-specific password or SMTP credential</span></div>
                                <div class="s-row-control"><input type="password" class="s-input" placeholder="••••••••••••"></div>
                            </div>
                        </div>
                    </div>
                </div><!-- /school -->

                <!-- ══════════════════════════════════════════════════
             7. ADMINISTRATIVE CONTROLS (Role-Aware)
        ═══════════════════════════════════════════════════ -->
                <?php if (PermissionManager::canAccessSettingsSection('admin')): ?>
                <div class="settings-section" id="sec-admin">

                    <!-- User Management -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--accent-soft);color:var(--accent)"><i class="fa-solid fa-users"></i></div>
                            <div>
                                <h3>User Management</h3>
                                <p>Add new users to the system</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label"><strong>Quick Add User</strong><span>Create a new account with the appropriate role</span></div>
                                <div class="s-row-control" style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <?php if (PermissionManager::hasPermission($_SESSION['user_id'], 'students.create')): ?>
                                        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-user-graduate"></i> Add Student</button>
                                    <?php endif; ?>
                                    <?php if (PermissionManager::hasPermission($_SESSION['user_id'], 'teachers.create')): ?>
                                        <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-chalkboard-teacher"></i> Add Teacher</button>
                                    <?php endif; ?>
                                    <?php if (PermissionManager::hasPermission($_SESSION['user_id'], 'system.admin')): ?>
                                        <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-user-tie"></i> Add Staff</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-Admin Management (Admin Only) -->
                    <?php if (AuthHelper::isAdmin()): ?>
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--danger-soft);color:var(--danger)"><i class="fa-solid fa-users-gear"></i></div>
                            <div>
                                <h3>Sub-Admin Management</h3>
                                <p>Manage sub-admin accounts and permissions</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label"><strong>Sub-Admin Actions</strong><span>Manage sub-admin accounts and their access levels</span></div>
                                <div class="s-row-control" style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a href="admins.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-shield"></i> Manage Sub-Admins</a>
                                    <a href="subadmin_signup.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-user-plus"></i> Add Sub-Admin</a>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Current Sub-Admins</strong><span>Active sub-admin accounts</span></div>
                                <div class="s-row-control">
                                    <?php
                                    if ($db) {
                                        try {
                                            $subadmin_count = $db->fetchValue("SELECT COUNT(*) FROM sub_admin WHERE status = 'approved'");
                                            $pending_count = $db->fetchValue("SELECT COUNT(*) FROM sub_admin WHERE status = 'pending'");
                                            echo "<span class='badge badge-success'>{$subadmin_count} Active</span> ";
                                            echo "<span class='badge badge-warning'>{$pending_count} Pending</span>";
                                        } catch (Exception $e) {
                                            echo "<span class='text-muted'>Unable to load statistics</span>";
                                        }
                                    } else {
                                        echo "<span class='text-muted'>Database unavailable</span>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Roles & Permissions Matrix -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--warning-soft);color:var(--warning)"><i class="fa-solid fa-user-lock"></i></div>
                            <div>
                                <h3>Role &amp; Permission Matrix</h3>
                                <p>Current access rights per system role</p>
                            </div>
                        </div>
                        <div class="s-card-body" style="padding:16px 22px;">
                            <div style="overflow-x:auto;">
                                <table class="role-matrix">
                                    <thead>
                                        <tr>
                                            <th>Role</th>
                                            <th>Reports</th>
                                            <th>Finance</th>
                                            <th>Files</th>
                                            <th>Settings</th>
                                            <th>Clubs</th>
                                            <th>Students</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $all_roles = PermissionManager::getAllRoles();
                                        foreach ($all_roles as $role):
                                            $permissions = $role['permissions'];
                                        ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($role['label']); ?></strong></td>
                                            <td><?php echo in_array('*', $permissions) || in_array('system.reports', $permissions) ? '✅' : '❌'; ?></td>
                                            <td><?php echo in_array('*', $permissions) || in_array('finance.view', $permissions) ? '✅' : '❌'; ?></td>
                                            <td><?php echo in_array('*', $permissions) || in_array('system.files', $permissions) ? '✅' : '❌'; ?></td>
                                            <td><?php echo in_array('*', $permissions) || in_array('system.settings', $permissions) ? '✅' : '❌'; ?></td>
                                            <td><?php echo in_array('*', $permissions) || in_array('clubs.view', $permissions) ? '✅' : '❌'; ?></td>
                                            <td><?php echo in_array('*', $permissions) || in_array('students.view', $permissions) ? '✅' : '❌'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <td><strong>Admin</strong></td>
                                            <td>✅</td>
                                            <td>✅</td>
                                            <td>✅</td>
                                            <td>✅</td>
                                            <td>✅</td>
                                            <td>✅</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Current User Session Info -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--info-soft);color:var(--info)"><i class="fa-solid fa-info-circle"></i></div>
                            <div>
                                <h3>Current Session Information</h3>
                                <p>Your current session details and role information</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label"><strong>User Type</strong></div>
                                <div class="s-row-control">
                                    <span class="badge badge-primary"><?php echo ucfirst($user_type); ?></span>
                                    <?php if ($user_type === 'sub-admin'): ?>
                                        <span class="badge badge-secondary"><?php echo PermissionManager::getRoleLabel($user_role); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Session ID</strong></div>
                                <div class="s-row-control">
                                    <code style="font-size: 12px;"><?php echo htmlspecialchars(session_id()); ?></code>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Session Started</strong></div>
                                <div class="s-row-control">
                                    <?php 
                                    $session_start = $_SESSION['created_at'] ?? time();
                                    echo date('M j, Y H:i:s', $session_start); 
                                    ?>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Last Activity</strong></div>
                                <div class="s-row-control">
                                    <?php 
                                    $last_activity = $_SESSION['last_activity'] ?? time();
                                    echo date('M j, Y H:i:s', $last_activity); 
                                    ?>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Session Timeout</strong></div>
                                <div class="s-row-control">
                                    <?php 
                                    $timeout = $_SESSION['session_timeout'] ?? 3600;
                                    $remaining = $timeout - (time() - $last_activity);
                                    echo $remaining > 0 ? round($remaining / 60) . ' minutes' : 'Expired';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <?php endif; ?>
                                        <tr>
                                            <td><span class="badge badge-blue">Principal</span></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-blue">Vice Principal</span></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-green">Teacher</span></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-green">Club Adviser</span></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-orange">Finance Officer</span></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-orange">Sub Admin</span></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-check"></i></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-red">Student</span></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                            <td><i class="fa-solid fa-xmark"></i></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Logs -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--danger-soft);color:var(--danger)"><i class="fa-solid fa-clipboard-list"></i></div>
                            <div>
                                <h3>Audit Logs</h3>
                                <p>System activity trail — recent admin actions</p>
                            </div>
                        </div>
                        <div class="s-card-body" style="padding:16px 22px;">
                            <div class="audit-wrap">
                                <table class="s-table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Today 09:14</td>
                                            <td>admin</td>
                                            <td>Logged in</td>
                                            <td><span class="badge badge-blue">Login</span></td>
                                            <td><span class="badge badge-green">Success</span></td>
                                        </tr>
                                        <tr>
                                            <td>Today 09:20</td>
                                            <td>teacher01</td>
                                            <td>Uploaded club document</td>
                                            <td><span class="badge badge-orange">File Upload</span></td>
                                            <td><span class="badge badge-green">Success</span></td>
                                        </tr>
                                        <tr>
                                            <td>Today 10:05</td>
                                            <td>admin</td>
                                            <td>Approved club request</td>
                                            <td><span class="badge badge-blue">Approval</span></td>
                                            <td><span class="badge badge-green">Success</span></td>
                                        </tr>
                                        <tr>
                                            <td>Today 11:30</td>
                                            <td>admin</td>
                                            <td>Changed SMTP settings</td>
                                            <td><span class="badge badge-red">System</span></td>
                                            <td><span class="badge badge-green">Saved</span></td>
                                        </tr>
                                        <tr>
                                            <td>Yesterday 14:00</td>
                                            <td>finance01</td>
                                            <td>Generated finance report</td>
                                            <td><span class="badge badge-orange">Report</span></td>
                                            <td><span class="badge badge-green">Success</span></td>
                                        </tr>
                                        <tr>
                                            <td>Yesterday 16:45</td>
                                            <td>admin</td>
                                            <td>Reset student password</td>
                                            <td><span class="badge badge-blue">System</span></td>
                                            <td><span class="badge badge-green">Success</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div style="text-align:right;margin-top:12px">
                                <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-download"></i> Export Logs</button>
                            </div>
                        </div>
                    </div>
                </div><!-- /admin -->

                <!-- ══════════════════════════════════════════════════
             8. FINANCE MANAGEMENT
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-finance">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--success-soft);color:var(--success)"><i class="fa-solid fa-peso-sign"></i></div>
                            <div>
                                <h3>Finance Settings</h3>
                                <p>Budget categories and expense tracking configuration</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row s-row-full">
                                <div class="s-row-label"><strong>Budget Categories</strong><span>These categories appear in expense forms and financial reports</span></div>
                                <div class="s-row-control">
                                    <div class="s-checkbox-grid">
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-people-group"></i> Clubs</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-calendar-star"></i> Events</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-box"></i> School Supplies</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-wrench"></i> Maintenance</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-user-graduate"></i> Student Programs</label>
                                    </div>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Expense Tracking</strong><span>Enable full expense tracking with approval workflow</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Receipt Upload Required</strong><span>Force receipt upload for all expense submissions</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Financial Reports Dashboard</strong><span>View and export financial summaries</span></div>
                                <div class="s-row-control"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-chart-line"></i> Open Dashboard</button></div>
                            </div>
                        </div>
                    </div>
                </div><!-- /finance -->

                <!-- ══════════════════════════════════════════════════
             9. FILE STORAGE
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-files">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--accent-soft);color:var(--accent)"><i class="fa-solid fa-folder-open"></i></div>
                            <div>
                                <h3>File Storage System</h3>
                                <p>Manage central file repository settings</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row s-row-full">
                                <div class="s-row-label"><strong>File Categories</strong><span>Active storage buckets available to users</span></div>
                                <div class="s-row-control">
                                    <div class="s-checkbox-grid">
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-bullhorn"></i> Announcements</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-file-lines"></i> Forms</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-people-group"></i> Club Documents</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-scale-balanced"></i> School Policies</label>
                                        <label class="s-checkbox-item"><input type="checkbox" checked> <i class="fa-solid fa-chalkboard-teacher"></i> Teacher Materials</label>
                                    </div>
                                </div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>File Preview Support</strong><span>Allow inline preview for PDFs, images, and documents</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Version History Tracking</strong><span>Keep previous versions of uploaded files for recovery</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Download Analytics</strong><span>Track how many times each file has been downloaded</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox"><span class="s-toggle-slider"></span></label></div>
                            </div>
                        </div>
                    </div>
                </div><!-- /files -->

                <!-- ══════════════════════════════════════════════════
             10. CLUBS
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-clubs">
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--warning-soft);color:var(--warning)"><i class="fa-solid fa-people-group"></i></div>
                            <div>
                                <h3>Club Management System</h3>
                                <p>Club creation, approval, and member settings</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label"><strong>Club Creation Requests</strong><span>Allow students or advisers to submit club proposals</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Principal Approval Required</strong><span>All new clubs require principal sign-off before activation</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Auto-Assign Adviser</strong><span>Automatically assign an available teacher as club adviser</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox"><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Member List Management</strong><span>Allow advisers to add or remove members independently</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Club Announcements &amp; Events</strong><span>Let club advisers post announcements and schedule events</span></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending requests table -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--danger-soft);color:var(--danger)"><i class="fa-solid fa-clock"></i></div>
                            <div>
                                <h3>Pending Club Requests</h3>
                                <p>Review and approve or reject club applications</p>
                            </div>
                        </div>
                        <div class="s-card-body" style="padding:16px 22px;">
                            <div class="s-table-wrap">
                                <table class="s-table">
                                    <thead>
                                        <tr>
                                            <th>Club Name</th>
                                            <th>Requested By</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Robotics Club</td>
                                            <td>teacher_reyes</td>
                                            <td>Mar 5, 2025</td>
                                            <td style="display:flex;gap:6px;"><button class="btn btn-success btn-sm">Approve</button><button class="btn btn-danger btn-sm">Reject</button></td>
                                        </tr>
                                        <tr>
                                            <td>Chess Club</td>
                                            <td>teacher_cruz</td>
                                            <td>Mar 7, 2025</td>
                                            <td style="display:flex;gap:6px;"><button class="btn btn-success btn-sm">Approve</button><button class="btn btn-danger btn-sm">Reject</button></td>
                                        </tr>
                                        <tr>
                                            <td>Drama Society</td>
                                            <td>teacher_santos</td>
                                            <td>Mar 9, 2025</td>
                                            <td style="display:flex;gap:6px;"><button class="btn btn-success btn-sm">Approve</button><button class="btn btn-danger btn-sm">Reject</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div><!-- /clubs -->

                <!-- ══════════════════════════════════════════════════
             11. SYSTEM OVERVIEW
        ═══════════════════════════════════════════════════ -->
                <div class="settings-section" id="sec-overview">

                    <!-- Stat Cards -->
                    <div class="stat-cards">
                        <div class="stat-card">
                            <div class="stat-icon" style="color:var(--accent)">🎓</div>
                            <div class="stat-num">1,248</div>
                            <div class="stat-label">Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="color:var(--success)">👩‍🏫</div>
                            <div class="stat-num">87</div>
                            <div class="stat-label">Teachers</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="color:var(--warning)">🏅</div>
                            <div class="stat-num">24</div>
                            <div class="stat-label">Active Clubs</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="color:var(--danger)">📋</div>
                            <div class="stat-num">312</div>
                            <div class="stat-label">Reports</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon" style="color:var(--text-secondary)">📁</div>
                            <div class="stat-num">5.4 GB</div>
                            <div class="stat-label">Files</div>
                        </div>
                    </div>

                    <!-- Storage -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--accent-soft);color:var(--accent)"><i class="fa-solid fa-hard-drive"></i></div>
                            <div>
                                <h3>Storage Usage</h3>
                                <p>Disk space consumed by uploaded files and backups</p>
                            </div>
                        </div>
                        <div class="s-card-body" style="padding:16px 22px;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;font-weight:600;">
                                <span>5.4 GB used of 20 GB</span><span style="color:var(--text-secondary)">27%</span>
                            </div>
                            <div class="s-progress">
                                <div class="s-progress-bar" style="width:27%;background:var(--accent)"></div>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-top:16px;font-size:12px;color:var(--text-secondary);">
                                <span>📁 Documents: 2.1 GB</span>
                                <span>🖼️ Images: 1.2 GB</span>
                                <span>💾 Backups: 1.8 GB</span>
                                <span>📊 Reports: 0.3 GB</span>
                            </div>
                        </div>
                    </div>

                    <!-- Broadcast & Report Moderation -->
                    <div class="s-grid-2">
                        <div class="s-card">
                            <div class="s-card-header">
                                <div class="s-card-icon" style="background:var(--warning-soft);color:var(--warning)"><i class="fa-solid fa-bullhorn"></i></div>
                                <div>
                                    <h3>Announcement Broadcasting</h3>
                                    <p>System-wide announcement controls</p>
                                </div>
                            </div>
                            <div class="s-card-body">
                                <div class="s-row">
                                    <div class="s-row-label"><strong>Allow Teacher Broadcasts</strong><span>Let teachers send school-wide announcements</span></div>
                                    <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                                </div>
                                <div class="s-row">
                                    <div class="s-row-label"><strong>Approval Required</strong><span>Admin must approve before broadcast is published</span></div>
                                    <div class="s-row-control"><label class="s-toggle"><input type="checkbox"><span class="s-toggle-slider"></span></label></div>
                                </div>
                            </div>
                        </div>

                        <div class="s-card">
                            <div class="s-card-header">
                                <div class="s-card-icon" style="background:var(--danger-soft);color:var(--danger)"><i class="fa-solid fa-flag"></i></div>
                                <div>
                                    <h3>Report Moderation</h3>
                                    <p>Flagged incident report statuses</p>
                                </div>
                            </div>
                            <div class="s-card-body" style="padding:16px 22px;">
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <span class="badge badge-orange"><i class="fa-solid fa-circle"></i> Pending: 12</span>
                                    <span class="badge badge-blue"><i class="fa-solid fa-circle"></i> Investigating: 5</span>
                                    <span class="badge badge-green"><i class="fa-solid fa-circle"></i> Resolved: 48</span>
                                    <span class="badge badge-red"><i class="fa-solid fa-circle"></i> Dismissed: 3</span>
                                </div>
                                <div style="margin-top:14px">
                                    <button class="btn btn-primary btn-sm"><i class="fa-solid fa-arrow-right"></i> Open Moderation Panel</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-Admin Permissions -->
                    <div class="s-card">
                        <div class="s-card-header">
                            <div class="s-card-icon" style="background:var(--success-soft);color:var(--success)"><i class="fa-solid fa-user-gear"></i></div>
                            <div>
                                <h3>Sub-Admin Permission Toggles</h3>
                                <p>Control what sub-admins can access and modify</p>
                            </div>
                        </div>
                        <div class="s-card-body">
                            <div class="s-row">
                                <div class="s-row-label"><strong>Manage Students</strong></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Manage Files</strong></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>View Finance Reports</strong></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox"><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Moderate Reports</strong></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                            <div class="s-row">
                                <div class="s-row-label"><strong>Post Announcements</strong></div>
                                <div class="s-row-control"><label class="s-toggle"><input type="checkbox" checked><span class="s-toggle-slider"></span></label></div>
                            </div>
                        </div>
                    </div>

                </div><!-- /overview -->

                <!-- ── Global Save Bar ── -->
                <div class="s-card">
                    <div class="save-bar">
                        <span class="save-bar-note"><i class="fa-solid fa-triangle-exclamation"></i>Unsaved changes will be lost if you navigate away</span>
                        <div style="display:flex;gap:10px;">
                            <button class="btn btn-ghost" onclick="resetDefaults()"><i class="fa-solid fa-rotate-left"></i> Reset to Default</button>
                            <button class="btn btn-primary" onclick="saveSettings()"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
                        </div>
                    </div>
                </div>

            </div><!-- /settings-wrapper -->
    </section>

    <!-- Toast -->
    <div id="toast"></div>

    <script src="admin_assets/js/admin_script.js"></script>

    <script>
        // ─── SETTINGS FUNCTIONALITY ─────────────────────────
        let currentSettings = {};

        function loadSettings() {
            // Simple settings loading without API for now
            currentSettings = <?php echo json_encode($settings); ?>;
            populateForms(currentSettings);
            console.log('Settings loaded:', currentSettings);
        }

        function populateForms(settings) {
            // Theme
            const themeRadio = document.querySelector(`input[name="theme"][value="${settings.appearance?.theme || 'light'}"]`);
            if (themeRadio) themeRadio.checked = true;
            const compactMode = document.getElementById('compactMode');
            if (compactMode) compactMode.checked = !!settings.appearance?.compactMode;

            // Locale (example)
            const langSelect = document.querySelector('#sec-locale select');
            if (langSelect) {
                const option = langSelect.querySelector(`option[value="${settings.locale?.language || 'en'}"]`);
                if (option) option.selected = true;
            }

            // Dynamic audit logs
            const auditTbody = document.querySelector('#sec-admin .s-table tbody');
            if (auditTbody) {
                <?php if (!empty($auditLogs)): ?>
                    auditTbody.innerHTML = `<?php echo json_encode($auditLogs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>`.map(log => `
                    <tr>
                        <td>${new Date(log.timestamp).toLocaleString()}</td>
                        <td>${log.admin_name || 'Admin'}</td>
                        <td>${log.action || 'System Activity'}</td>
                        <td><span class="badge badge-blue">Activity</span></td>
                        <td><span class="badge badge-green">Success</span></td>
                    </tr>
                `).join('');
                <?php endif; ?>
            }
        }

        function initAutoSave() {
            // Save toggles, selects, inputs on change
            document.addEventListener('change', function(e) {
                const target = e.target;
                if (target.matches('input.s-toggle, .s-select, .s-input, input[name="theme"]')) {
                    debounceSave(target);
                }
            });
        }

        function debounceSave(target, delay = 500) {
            clearTimeout(window.saveTimeout);
            window.saveTimeout = setTimeout(() => {
                const path = target.dataset.path || target.name;
                if (!path) return;
                const value = target.type === 'checkbox' ? target.checked : target.value;
                saveSingleSetting(path, value);
            }, delay);
        }

        function saveSingleSetting(path, value) {
            fetch('settings_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save',
                    data: {
                        [path]: value
                    }
                })
            }).then(r => r.json()).then(res => {
                if (res.success) showToast('Setting saved');
                currentSettings[path] = value;
            }).catch(e => console.error('Save failed:', e));
        }

        // ─── Tab Navigation ──────────────────────────────────────
        document.querySelectorAll('.s-tab').forEach(tab => {
                    tab.addEventListener('click', () => {

                                // ─── Theme Switching ─────────────────────────────────────
                                document.querySelectorAll('input[name="theme"]').forEach(radio => {
                                    radio.addEventListener('change', function() {
                                        const val = this.value;
                                        document.querySelectorAll('.s-radio-option').forEach(o => {
                                            o.classList.toggle('selected', o.querySelector('input').value === val);
                                        });
                                        if (val === 'dark') {
                                            document.documentElement.setAttribute('data-theme', 'dark');
                                        } else if (val === 'light') {
                                            document.documentElement.removeAttribute('data-theme');
                                        } else {
                                            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                                            prefersDark
                                                ?
                                                document.documentElement.setAttribute('data-theme', 'dark') :
                                                document.documentElement.removeAttribute('data-theme');
                                        }
                                    });
                                });

                                // ─── Encryption Key Toggle ───────────────────────────────
                                function toggleKey() {
                                    const el = document.getElementById('encKey');
                                    const icon = document.getElementById('eyeIcon');
                                    if (el.type === 'password') {
                                        el.type = 'text';
                                        icon.classList.replace('fa-eye', 'fa-eye-slash');
                                    } else {
                                        el.type = 'password';
                                        icon.classList.replace('fa-eye-slash', 'fa-eye');
                                    }
                                }

                                // ─── Logo Upload Preview ─────────────────────────────────
                                function handleLogoUpload(e) {
                                    const file = e.target.files[0];
                                    if (!file) return;
                                    const zone = document.querySelector('.upload-zone');
                                    const reader = new FileReader();
                                    reader.onload = ev => {
                                        zone.innerHTML = `<img src="${ev.target.result}" style="max-height:80px;border-radius:6px;"><br><small style="color:var(--success)">${file.name} uploaded</small>`;
                                    };
                                    reader.readAsDataURL(file);
                                }

                                // ─── Save Settings ───────────────────────────────────────
                                function saveSettings() {
                                    // Collect all form values here for a real PHP POST
                                    showToast('✅ Settings saved successfully!');
                                }

                                // ─── Reset to Default ────────────────────────────────────
                                window.resetDefaults = function() {
                                    if (!confirm('Reset all settings to their default values?')) return;
                                    fetch('settings_api.php?action=reset')
                                        .then(r => r.json())
                                        .then(data => {
                                            currentSettings = data;
                                            populateForms(data);
                                            showToast('Settings reset to defaults');
                                        })
                                        .catch(e => console.error('Reset failed:', e));
                                }

                                // ─── Toast ───────────────────────────────────────────────
                                function showToast(msg) {
                                    const t = document.getElementById('toast');
                                    t.textContent = msg;
                                    t.classList.add('show');
                                    setTimeout(() => t.classList.remove('show'), 3000);
                                }
    </script>
    <script>
        // ─── Save Impact Settings to DB via settings_api.php ─────────────
        async function saveImpactSettings() {
            const foundedYear = document.getElementById('school_founded_year')?.value?.trim();
            const subtitle = document.getElementById('impact_subtitle')?.value?.trim();
            const msg = document.getElementById('impact-save-msg');

            if (!foundedYear || isNaN(foundedYear)) {
                msg.style.display = 'block';
                msg.style.background = '#fde8d8';
                msg.style.color = '#a33';
                msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Please enter a valid year.';
                return;
            }

            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('data[school_founded_year]', foundedYear);
            fd.append('data[impact_subtitle]', subtitle);

            try {
                const res = await fetch('settings_api.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.success) {
                    msg.style.display = 'block';
                    msg.style.background = '#d4edda';
                    msg.style.color = '#155724';
                    msg.innerHTML = '<i class="fa-solid fa-circle-check"></i> Impact settings saved successfully!';
                } else {
                    throw new Error(data.error || 'Save failed');
                }
            } catch (e) {
                msg.style.display = 'block';
                msg.style.background = '#fde8d8';
                msg.style.color = '#a33';
                msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + e.message;
            }
            setTimeout(() => msg.style.display = 'none', 4000);
        }
    </script>

    <script>
        // Move page-content into .main (opened by admin_nav.php) — same pattern as admin_dashboard.php
        (function() {
            var mainDiv = document.querySelector('.main');
            var pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent && !mainDiv.contains(pageContent)) {
                mainDiv.appendChild(pageContent);
            }
        })();
    </script>
</body>

</html>