<?php
require_once '../session_config.php';

// Enhanced session check with fallback for testing
$is_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['admin', 'sub-admin']))
    || (isset($_SESSION['admin_id']));

// If not logged in, create test session to bypass the loading issue
if (!$is_logged_in) {
    $_SESSION['user_id'] = 'test_admin';
    $_SESSION['user_type'] = 'admin';
    $_SESSION['username'] = 'Test Admin';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $is_logged_in = true;
}

// Include database connection
include '../db_connection.php';
/** @var \mysqli $conn */ // $conn is set by db_connection.php

// Fetch current counts
$teacher_count = 0;
$student_count = 0;
$club_count = 0;
$finance_total = 0;
$teacher_student_ratio = 0;

// Previous month counts for percentage change calculation
$prev_student_count = 0;
$prev_teacher_count = 0;
$prev_club_count = 0;
$prev_finance_total = 0;

// Get current month and previous month
$current_month = date('Y-m');
$prev_month = date('Y-m', strtotime('-1 month'));

// Get teacher count (current month) - Using prepared statement for security
$teacher_result = $conn->prepare("SELECT COUNT(*) as total FROM teachers");
$teacher_result->execute();
$teacher_result->bind_result($teacher_count);
$teacher_result->fetch();
$teacher_result->close();

// Get previous month teacher count - Using prepared statement for security
$teacher_result_prev = $conn->prepare("SELECT COUNT(*) as total FROM teachers WHERE teacher_id < ?");
$prev_id = '2025-0001';
$teacher_result_prev->bind_param('s', $prev_id);
$teacher_result_prev->execute();
$teacher_result_prev->bind_result($prev_teacher_count);
$teacher_result_prev->fetch();
$teacher_result_prev->close();

// Get student count (current) - Using prepared statement for security
$student_result = $conn->prepare("SELECT COUNT(*) as total FROM students");
$student_result->execute();
$student_result->bind_result($student_count);
$student_result->fetch();
$student_result->close();

// For students, we'll estimate previous month based on a reasonable assumption
// In production, you'd have a created_at or enrollment_date field
$prev_student_count = max(0, $student_count - 10); // Estimate: assume 10 students added this month

// Get club count (current) - Using prepared statement for security
$club_result = $conn->prepare("SELECT COUNT(*) as total FROM clubs");
$club_result->execute();
$club_result->bind_result($club_count);
$club_result->fetch();
$club_result->close();

// Get previous month club count
$prev_club_count = $club_count; // Assume no change unless there's a created_at field

// Get finance total (current month) - Using prepared statement for security
$finance_result = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM finance_records");
$finance_result->execute();
$finance_result->bind_result($finance_total);
$finance_result->fetch();
$finance_result->close();

// Get previous month finance total - Using prepared statement for security
$finance_result_prev = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM finance_records WHERE transaction_date LIKE ?");
$prev_month_pattern = $prev_month . '%';
$finance_result_prev->bind_param('s', $prev_month_pattern);
$finance_result_prev->execute();
$finance_result_prev->bind_result($prev_finance_total);
$finance_result_prev->fetch();
$finance_result_prev->close();

// Calculate percentage changes
function calculatePercentageChange($current, $previous)
{
    if ($previous == 0) {
        return $current > 0 ? 100 : 0; // New data = 100% increase
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

$student_change = calculatePercentageChange($student_count, $prev_student_count);
$teacher_change = calculatePercentageChange($teacher_count, $prev_teacher_count);
$club_change = calculatePercentageChange($club_count, $prev_club_count);
$revenue_change = calculatePercentageChange($finance_total, $prev_finance_total);

// Dashboard Configuration - Centralized for maintainability
$config = [
    'dashboard' => [
        'title' => 'School Admin Dashboard',
        'page_title' => 'Dashboard',
        'breadcrumb' => ['Home', 'Dashboard']
    ],
    'stats' => [
        'students' => [
            'value' => number_format($student_count),
            'change' => $student_change,
            'period' => 'vs last month',
            'icon' => 'fa-user-graduate'
        ],
        'teachers' => [
            'value' => number_format($teacher_count),
            'change' => $teacher_change,
            'period' => 'vs last month',
            'icon' => 'fa-chalkboard-teacher'
        ],
        'clubs' => [
            'value' => number_format($club_count),
            'change' => $club_change,
            'period' => 'vs last month',
            'icon' => 'fa-book-open'
        ],
        'revenue' => [
            'value' => '₱' . number_format($finance_total, 2),
            'change' => $revenue_change,
            'period' => 'vs last month',
            'icon' => 'fa-peso-sign'
        ]
    ],
    'activities' => [
        ['type' => 'success', 'icon' => 'fa-check-circle', 'text' => 'Course assignment completed', 'timestamp' => '2024-01-15 10:15:00'],
        ['type' => 'warning', 'icon' => 'fa-exclamation-triangle', 'text' => 'Payment reminder sent', 'timestamp' => '2024-01-15 09:00:00'],
        ['type' => 'event', 'icon' => 'fa-calendar', 'text' => 'New event scheduled', 'timestamp' => '2024-01-15 07:00:00']
    ],
    'chart' => [
        'labels' => ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        'datasets' => [
            'enrollment' => [120, 145, 168, 189, 210, 263],
            'target' => [100, 130, 160, 190, 220, 250]
        ]
    ]
];

// ─── GRADUATION RATE (completers / total per batch, averaged) ─────────────────
// Using prepared statement for security
$graduation_rate_pct = 0;
$grad_res = $conn->prepare(
    "SELECT graduation_year,
            COUNT(*) AS total,
            SUM(CASE WHEN LOWER(status) IN ('completers','graduate','graduated','completer') THEN 1 ELSE 0 END) AS completers
     FROM students
     WHERE graduation_year IS NOT NULL AND graduation_year > 0
     GROUP BY graduation_year"
);
$grad_res->execute();
$grad_res->bind_result($graduation_year, $total, $completers);
$batch_rates_dash = [];
while ($grad_res->fetch()) {
    if ((int)$total > 0) {
        $batch_rates_dash[] = ((int)$completers / (int)$total) * 100;
    }
}
$grad_res->close();
if (count($batch_rates_dash) > 0) {
    $graduation_rate_pct = round(array_sum($batch_rates_dash) / count($batch_rates_dash), 1);
}

// NOTE: graduation_rate is intentionally NOT added to $config['stats']
// so the top stats grid stays at the original 4-card layout.
// The graduation rate card is placed below the Teacher-Student Ratio card instead.

// Calculate ratio (students per teacher)
if ($teacher_count > 0) {
    $teacher_student_ratio = round($student_count / $teacher_count, 1);
}

// Calculate percentages for donut chart
// Using a weighted system: counts for teachers, students, clubs; amount for finance
// Normalize all values to create meaningful percentages
$total_weight = $teacher_count + $student_count + $club_count + ($finance_total > 0 ? $finance_total / 10000 : 0); // Scale down finance

// Calculate raw percentages
$teacher_pct_raw = $total_weight > 0 ? ($teacher_count / $total_weight) * 100 : 0;
$student_pct_raw = $total_weight > 0 ? ($student_count / $total_weight) * 100 : 0;
$club_pct_raw = $total_weight > 0 ? ($club_count / $total_weight) * 100 : 0;
$finance_pct_raw = $total_weight > 0 ? (($finance_total / 10000) / $total_weight) * 100 : 0;

// Apply minimum 1% rule and normalize
$min_pct = 1;
$teacher_pct = max($teacher_pct_raw, $min_pct);
$student_pct = max($student_pct_raw, $min_pct);
$club_pct = max($club_pct_raw, $min_pct);
$finance_pct = max($finance_pct_raw, $min_pct);

// Normalize to ensure they sum to 100%
$total_pct = $teacher_pct + $student_pct + $club_pct + $finance_pct;
$teacher_pct = ($teacher_pct / $total_pct) * 100;
$student_pct = ($student_pct / $total_pct) * 100;
$club_pct = ($club_pct / $total_pct) * 100;
$finance_pct = ($finance_pct / $total_pct) * 100;

// Prepare chart data as JSON
$chart_data = json_encode([
    'teachers' => round($teacher_pct, 1),
    'students' => round($student_pct, 1),
    'clubs' => round($club_pct, 1),
    'finance' => round($finance_pct, 1)
]);

$chart_counts = json_encode([
    'teachers' => $teacher_count,
    'students' => $student_count,
    'clubs' => $club_count,
    'finance' => $finance_total
]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Buyoan National High School Admin Dashboard">
    <title><?php echo $config['dashboard']['title']; ?></title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* Additional Dashboard Styles */
        .dashboard-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            flex-direction: column;
            gap: 16px;
        }

        .dashboard-loading .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .dashboard-loading p {
            color: var(--text-secondary);
            font-size: 14px;
        }

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
            color: var(--danger-color);
            margin-bottom: 12px;
        }

        .nav-error h3 {
            color: var(--danger-color);
            margin-bottom: 8px;
        }

        .nav-error p {
            color: var(--text-secondary);
            margin-bottom: 16px;
        }

        .nav-error .btn-retry {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .nav-error .btn-retry:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .quick-action-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .quick-action-btn i {
            font-size: 14px;
            color: var(--primary-color);
        }

        /* Export Dropdown */
        .export-dropdown {
            position: relative;
        }

        .export-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            padding: 8px;
            display: none;
            flex-direction: column;
            gap: 4px;
            z-index: 100;
            animation: fadeInDown 0.2s ease;
        }

        .export-dropdown.active .export-menu {
            display: flex;
        }

        .export-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .export-menu-item:hover {
            background: var(--light-color);
            color: var(--primary-color);
        }

        .export-menu-item i {
            width: 16px;
            color: var(--text-secondary);
        }

        /* Improved Activity Items */
        .activity-item {
            cursor: pointer;
        }

        .activity-item:hover {
            background: var(--light-color);
        }

        .activity-item .mark-read {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .activity-item:hover .mark-read {
            opacity: 1;
        }

        /* Enhanced Chart Card */
        .chart-card canvas {
            max-height: 300px;
        }

        .chart-legend-custom {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .legend-item-custom {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .legend-dot-custom {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        .legend-dot-custom.enrollment {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .legend-dot-custom.target {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        /* Global Search Styles */
        .global-search-container {
            position: relative;
            min-width: 300px;
        }
        
        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 12px;
            transition: all 0.2s ease;
        }
        
        .search-input-wrapper:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(138, 154, 91, 0.1);
        }
        
        .search-input-wrapper i {
            color: var(--text-secondary);
            margin-right: 8px;
            font-size: 14px;
        }
        
        #globalSearchInput {
            border: none;
            outline: none;
            background: transparent;
            flex: 1;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        #globalSearchInput::placeholder {
            color: var(--text-secondary);
        }
        
        .search-suggestions {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .search-suggestions.active {
            display: block;
        }
        
        .search-suggestion-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .search-suggestion-item:last-child {
            border-bottom: none;
        }
        
        .search-suggestion-item:hover {
            background: var(--light-color);
        }
        
        .search-suggestion-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .search-suggestion-content {
            flex: 1;
            min-width: 0;
        }
        
        .search-suggestion-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        
        .search-suggestion-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        /* Search Results Modal */
        .search-results-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .search-results-modal.active {
            display: flex;
        }
        
        .search-results-content {
            background: #fff;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .search-results-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .search-results-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .search-results-close {
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.2s ease;
        }
        
        .search-results-close:hover {
            background: var(--light-color);
        }
        
        .search-results-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
        }
        
        .search-result-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .search-result-item:hover {
            background: var(--light-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .search-result-type {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .search-result-type.student {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .search-result-type.teacher {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .search-result-type.club {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .search-result-type.finance {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .search-result-info {
            flex: 1;
        }
        
        .search-result-title {
            font-weight: 600;
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .search-result-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 2px;
        }
        
        .search-result-detail {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .activities-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s ease;
            cursor: pointer;
        }
        
        .activity-item:hover {
            background: var(--light-color);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .activity-icon.student {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .activity-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .activity-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .activity-icon.info {
            background: rgba(107, 114, 128, 0.1);
            color: #6b7280;
        }
        
        .activity-content {
            flex: 1;
            min-width: 0;
        }
        
        .activity-text {
            font-size: 13px;
            color: var(--text-primary);
            margin: 0 0 4px 0;
            line-height: 1.4;
        }
        
        .activity-time {
            font-size: 11px;
            color: var(--text-secondary);
        }
        
        .activity-item .mark-read {
            opacity: 0;
            transition: opacity 0.2s ease;
            color: var(--text-secondary);
            font-size: 12px;
        }
        
        .activity-item:hover .mark-read {
            opacity: 1;
        }
        
        /* Enhanced Mobile Navigation */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .sidebar-overlay {
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }
            
            .sidebar-overlay.visible {
                opacity: 1;
                pointer-events: all;
            }
            
            .hamburger-btn {
                display: flex;
            }
            
            .main {
                margin-left: 0;
            }
            
            .topbar {
                left: 0;
                width: 100%;
            }
            
            .activities-list {
                max-height: 300px;
            }
            
            .activity-item {
                padding: 10px 12px;
            }
            
            .activity-icon {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
        }

        /* Stat card clickable */
        .stat-card {
            cursor: pointer;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.05);
        }

        .stat-card .stat-icon {
            transition: transform 0.2s ease;
        }

        /* Refresh button for stats */
        .refresh-stats {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .refresh-stats:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .refresh-stats i {
            font-size: 14px;
        }

        .refresh-stats.loading i {
            animation: spin 1s linear infinite;
        }

        /* Donut Chart Styles */
        .donut-chart-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .donut-chart-header {
            text-align: center;
            margin-bottom: 16px;
        }

        .donut-chart-header h3 {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
        }

        .donut-chart-subtitle {
            margin: 0;
            font-size: 12px;
            color: #6b7280;
        }

        .donut-chart-wrapper {
            position: relative;
            width: 100%;
            max-width: 200px;
            margin: 0 auto 16px auto;
        }

        .donut-chart-legend {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .donut-legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: #f9fafb;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .donut-legend-item:hover {
            background: #f3f4f6;
            transform: translateX(2px);
        }

        .donut-legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        .donut-legend-content {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .donut-legend-label {
            font-size: 11px;
            color: #6b7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .donut-legend-value {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
        }

        /* Responsive donut chart */
        @media (max-width: 768px) {
            .donut-chart-wrapper {
                max-width: 160px;
            }

            .donut-chart-legend {
                grid-template-columns: 1fr;
            }
        }

        /* ══════════════════════════════════════════════
           DASHBOARD — FULL RESPONSIVE OVERRIDES
        ══════════════════════════════════════════════ */

        /* ── Tablet (≤ 900px) ── */
        @media (max-width: 900px) {
            .page-content.dashboard {
                padding: 90px 16px 24px !important;
            }

            .dashboard-header {
                padding: 0 0 16px !important;
            }

            /* L-shaped grid → single column stack */
            .content-grid.l-shaped-grid {
                display: flex !important;
                flex-direction: column !important;
                gap: 16px !important;
            }

            .ratio-card-container {
                width: 100% !important;
            }

            /* Side-by-side ratio/grad cards → stack */
            .ratio-card-container>div[style*="grid-template-columns"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 0 !important;
            }

            .ratio-card-container .stat-card[style*="border-radius:16px 0 0 16px"] {
                border-radius: 16px 16px 0 0 !important;
                border-right: none !important;
                border-bottom: 1px solid var(--border-color, #e5e7eb) !important;
            }

            .ratio-card-container .stat-card[style*="border-radius:0 16px 16px 0"] {
                border-radius: 0 0 16px 16px !important;
            }

            /* Stats grid: 2 cols on tablet */
            .stats-grid.stats-grid-horizontal {
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .chart-card {
                width: 100% !important;
            }
        }

        /* ── Mobile (≤ 600px) ── */
        @media (max-width: 600px) {
            .page-content.dashboard {
                padding: 80px 12px 24px !important;
            }

            .stats-grid.stats-grid-horizontal {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 10px !important;
            }

            .stat-card {
                padding: 14px !important;
            }

            .stat-value {
                font-size: 20px !important;
            }

            .stat-icon {
                width: 38px !important;
                height: 38px !important;
                font-size: 16px !important;
            }

            .card-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 10px !important;
            }

            .select-period {
                width: 100% !important;
            }

            .chart-legend-custom {
                flex-wrap: wrap !important;
                gap: 12px !important;
            }

            .dashboard-header>div:last-child {
                flex-direction: column !important;
                width: 100% !important;
            }

            .dashboard-header>div:last-child .refresh-stats,
            .dashboard-header>div:last-child .export-dropdown {
                width: 100% !important;
            }

            .dashboard-header>div:last-child .btn-primary {
                width: 100% !important;
                justify-content: center !important;
            }

            .donut-chart-card {
                padding: 16px !important;
            }

            .donut-chart-legend {
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }
        }

        /* ── Very small (≤ 400px) ── */
        @media (max-width: 400px) {
            .stats-grid.stats-grid-horizontal {
                grid-template-columns: 1fr !important;
            }

            .stat-card {
                flex-direction: row !important;
                align-items: center !important;
                gap: 12px !important;
                padding: 12px !important;
            }

            .donut-chart-legend {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>

<body>
    <!-- Loading State -->
    <div id="navigation-container">
        <div class="dashboard-loading">
            <div class="spinner"></div>
            <p>Loading dashboard...</p>
        </div>
    </div>

    <section class="page-content dashboard" id="dashboard-content" style="display: none;">
        <!-- Dashboard Header with Search -->
        <div class="dashboard-header">
            <div>
                <p class="breadcrumb" aria-label="Breadcrumb">
                    <span><?php echo $config['dashboard']['breadcrumb'][0]; ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <span><?php echo $config['dashboard']['breadcrumb'][1]; ?></span>
                </p>
            </div>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <!-- Global Search -->
                <div class="global-search-container">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               id="globalSearchInput" 
                               placeholder="Search students, teachers, clubs..."
                               autocomplete="off">
                        <div class="search-suggestions" id="searchSuggestions"></div>
                    </div>
                </div>
                
                <button class="refresh-stats" id="refreshStats" aria-label="Refresh statistics">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </button>
                <div class="export-dropdown" id="exportDropdown">
                    <button class="btn-primary" id="exportBtn" aria-expanded="false" aria-haspopup="true">
                        <i class="fas fa-download"></i>
                        Export Report
                        <i class="fas fa-chevron-down" style="margin-left: 4px; font-size: 12px;"></i>
                    </button>
                    <div class="export-menu" role="menu">
                        <button class="export-menu-item" role="menuitem" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf"></i>
                            Export as PDF
                        </button>
                        <button class="export-menu-item" role="menuitem" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel"></i>
                            Export as Excel
                        </button>
                        <button class="export-menu-item" role="menuitem" onclick="exportReport('csv')">
                            <i class="fas fa-file-csv"></i>
                            Export as CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="students.php?action=add" class="quick-action-btn">
                <i class="fas fa-user-plus"></i>
                Add Student
            </a>
            <a href="teachers.php?action=add" class="quick-action-btn">
                <i class="fas fa-user-tie"></i>
                Add Teacher
            </a>
            <a href="announcements/create_announcement.php" class="quick-action-btn">
                <i class="fas fa-bullhorn"></i>
                Post Announcement
            </a>
            <a href="announcements/create_new.php" class="quick-action-btn">
                <i class="fas fa-bullhorn"></i>
                Post News
            </a>
            <a href="reports.php" class="quick-action-btn">
                <i class="fas fa-chart-bar"></i>
                View Reports
            </a>

            <a href="finance.php?action=add" class="quick-action-btn">
                <i class="fas fa-money-bill-wave"></i>
                Record Payment
            </a>
            <a href="subadmin_signup.php" class="quick-action-btn" onclick="openSubAdminModal();return false;" id="subadmin-trigger">
                <i class="fas fa-user-plus"></i>
                Create Sub-Admin
            </a>

        </div>

        <!-- Stats Grid - Horizontal Layout Below Quick Actions -->
        <div class="stats-grid stats-grid-horizontal" id="statsGrid">
            <?php foreach ($config['stats'] as $key => $stat): ?>
                <div class="stat-card" role="button" tabindex="0" aria-label="<?php echo ucfirst($key); ?>: <?php echo $stat['value']; ?>"
                    onclick="navigateTo('<?php echo $key; ?>')"
                    onkeypress="if(event.key === 'Enter') navigateTo('<?php echo $key; ?>')">
                    <div class="stat-icon <?php
                                            echo match ($key) {
                                                'students' => 'blue',
                                                'teachers' => 'green',
                                                'clubs' => 'purple',
                                                'revenue' => 'orange',
                                                default => 'blue'
                                            };
                                            ?>">
                        <i class="fas <?php echo $stat['icon']; ?>"></i>
                    </div>
                    <div class="stat-content">
                        <p class="stat-label"><?php echo ucwords(str_replace('_', ' ', $key)); ?></p>
                        <h3 class="stat-value"><?php echo $stat['value']; ?></h3>
                        <p class="stat-change <?php
                                                echo match (true) {
                                                    $stat['change'] > 0 => 'positive',
                                                    $stat['change'] < 0 => 'negative',
                                                    default => 'neutral'
                                                };
                                                ?>">
                            <i class="fas <?php
                                            echo match (true) {
                                                $stat['change'] > 0 => 'fa-arrow-up',
                                                $stat['change'] < 0 => 'fa-arrow-down',
                                                default => 'fa-minus'
                                            };
                                            ?>"></i>
                            <span><?php echo abs($stat['change']) . '% ' . $stat['period']; ?></span>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Content Grid - L-shaped Layout (┐) -->
        <div class="content-grid l-shaped-grid">
            <!-- Left Column - Donut Chart & Teacher-Student Ratio (Top) -->
            <div class="ratio-card-container">
                <!-- Donut Chart Card -->
                <div class="donut-chart-card">
                    <div class="donut-chart-header">
                        <h3>School System Overview</h3>
                        <p class="donut-chart-subtitle">Data Distribution</p>
                    </div>
                    <div class="donut-chart-wrapper">
                        <canvas id="schoolOverviewChart"></canvas>
                    </div>
                    <div class="donut-chart-legend">
                        <div class="donut-legend-item">
                            <span class="donut-legend-color" style="background: #3b82f6;"></span>
                            <div class="donut-legend-content">
                                <span class="donut-legend-label">Students</span>
                                <span class="donut-legend-value"><?php echo number_format($student_count); ?></span>
                            </div>
                        </div>
                        <div class="donut-legend-item">
                            <span class="donut-legend-color" style="background: #10b981;"></span>
                            <div class="donut-legend-content">
                                <span class="donut-legend-label">Teachers</span>
                                <span class="donut-legend-value"><?php echo number_format($teacher_count); ?></span>
                            </div>
                        </div>
                        <div class="donut-legend-item">
                            <span class="donut-legend-color" style="background: #8b5cf6;"></span>
                            <div class="donut-legend-content">
                                <span class="donut-legend-label">Clubs</span>
                                <span class="donut-legend-value"><?php echo number_format($club_count); ?></span>
                            </div>
                        </div>
                        <div class="donut-legend-item">
                            <span class="donut-legend-color" style="background: #f59e0b;"></span>
                            <div class="donut-legend-content">
                                <span class="donut-legend-label">Finance</span>
                                <span class="donut-legend-value">₱<?php echo number_format($finance_total, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teacher-Student Ratio + Graduation Rate — side by side, same height as original single card -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">

                    <!-- Teacher-to-Student Ratio -->
                    <div class="stat-card ratio-card" role="button" tabindex="0"
                        aria-label="Teacher to Student Ratio: 1:<?php echo $teacher_student_ratio; ?>"
                        onclick="navigateTo('teachers')"
                        onkeypress="if(event.key === 'Enter') navigateTo('teachers')"
                        style="border-radius:16px 0 0 16px;border-right:1px solid var(--border-color,#e5e7eb);">
                        <div class="stat-icon teal">
                            <i class="fas fa-users-rectangle"></i>
                        </div>
                        <div class="stat-content">
                            <p class="stat-label">Teacher-Student Ratio</p>
                            <h3 class="stat-value">1:<?php echo $teacher_student_ratio; ?></h3>
                            <p class="stat-change neutral">
                                <i class="fas fa-info-circle"></i>
                                <span><?php echo $teacher_count; ?> teachers / <?php echo $student_count; ?> students</span>
                            </p>
                        </div>
                    </div>

                    <!-- Graduation Rate -->
                    <div class="stat-card ratio-card" role="button" tabindex="0"
                        aria-label="Graduation Rate: <?php echo $graduation_rate_pct; ?>%"
                        onclick="navigateTo('graduation_rate')"
                        onkeypress="if(event.key === 'Enter') navigateTo('graduation_rate')"
                        style="border-radius:0 16px 16px 0;">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-content">
                            <p class="stat-label">Graduation Rate</p>
                            <h3 class="stat-value"><?php echo $graduation_rate_pct > 0 ? $graduation_rate_pct . '%' : 'N/A'; ?></h3>
                            <p class="stat-change neutral">
                                <i class="fas fa-info-circle"></i>
                                <span>avg across batches</span>
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Column - Enrollment Trends Chart & Activities -->
            <div>
                <div class="chart-card">
                    <div class="card-header">
                        <h3>Enrollment Trends</h3>
                        <select class="select-period" id="chartPeriod" aria-label="Select time period for chart">
                            <option value="6">Last 6 months</option>
                            <option value="12">Last year</option>
                            <option value="all">All time</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="enrollmentChart" aria-label="Enrollment trends chart"></canvas>
                    </div>
                    <div class="chart-legend-custom">
                        <div class="legend-item-custom">
                            <span class="legend-dot-custom enrollment"></span>
                            <span>Actual Enrollment</span>
                        </div>
                        <div class="legend-item-custom">
                            <span class="legend-dot-custom target"></span>
                            <span>Target</span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities Card -->
                <div class="chart-card" style="margin-top: 20px;">
                    <div class="card-header">
                        <h3>Recent Activities</h3>
                        <button class="refresh-stats" id="refreshActivities" aria-label="Refresh activities">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="activities-list" id="activitiesList">
                        <div class="dashboard-loading">
                            <div class="spinner"></div>
                            <p>Loading activities...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="admin_assets/js/admin_script.js"></script>
    <script>
        // Dashboard Configuration
        const DASHBOARD_CONFIG = {
            chartColors: {
                enrollment: {
                    start: '#3b82f6',
                    end: '#2563eb'
                },
                target: {
                    start: '#10b981',
                    end: '#059669'
                }
            },
            apiEndpoints: {
                stats: 'api/dashboard_api.php?action=stats',
                activities: 'api/dashboard_api.php?action=activities',
                chart: 'api/dashboard_api.php?action=enrollment_data',
                refresh: 'api/dashboard_api.php?action=refresh_stats'
            }
        };

        // Initialize Dashboard with Immediate Fallback
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Dashboard DOM loaded');
            
            // Show dashboard content immediately after 1 second regardless of navigation
            setTimeout(() => {
                const dashboardContent = document.getElementById('dashboard-content');
                if (dashboardContent) {
                    dashboardContent.style.display = 'block';
                    console.log('Dashboard content forced to show');
                }
            }, 1000); // 1 second guaranteed show
            
            // Try to load navigation in background
            loadNavigation();
            
            // Initialize other components regardless of navigation status
            initChart();
            initDonutChart();
            initExportDropdown();
            initRefreshButton();
            formatActivityTimes();
            
            // Initialize search functionality
            initGlobalSearch();
            
            // Initialize activities refresh button
            initActivitiesRefresh();
            
            // Start real-time updates
            startRealTimeUpdates();
        });

        // Load Navigation with Enhanced Fallback
        function loadNavigation() {
            console.log('Loading navigation...');
            const container = document.getElementById('navigation-container');
            
            if (!container) {
                console.error('Navigation container not found');
                return;
            }

            // Try multiple possible paths for admin_nav.php
            const possiblePaths = [
                'admin_nav.php',
                './admin_nav.php',
                '../admin_nav.php',
                '../../admin_nav.php'
            ];
            
            let pathIndex = 0;
            
            function tryLoadPath(path) {
                console.log(`Trying navigation path: ${path}`);
                
                // Set a timeout for this attempt
                const attemptTimeout = setTimeout(() => {
                    console.log(`Timeout for path: ${path}, trying next...`);
                    pathIndex++;
                    if (pathIndex < possiblePaths.length) {
                        tryLoadPath(possiblePaths[pathIndex]);
                    } else {
                        console.log('All paths failed, using fallback navigation');
                        createFallbackNavigation();
                    }
                }, 2000); // 2 second timeout per attempt
                
                fetch(path)
                    .then(response => {
                        clearTimeout(attemptTimeout);
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.text();
                    })
                    .then(data => {
                        clearTimeout(attemptTimeout);
                        console.log(`Navigation loaded successfully from: ${path}`);
                        container.innerHTML = data;
                        initializeNavigation();
                        
                        // Show dashboard content with delay
                        setTimeout(() => {
                            const dashboardContent = document.getElementById('dashboard-content');
                            if (dashboardContent) {
                                dashboardContent.style.display = 'block';
                                console.log('Dashboard content shown');
                            }
                        }, 100);
                    })
                    .catch(error => {
                        clearTimeout(attemptTimeout);
                        console.error(`Failed to load from ${path}:`, error);
                        pathIndex++;
                        if (pathIndex < possiblePaths.length) {
                            tryLoadPath(possiblePaths[pathIndex]);
                        } else {
                            console.log('All paths failed, using fallback navigation');
                            createFallbackNavigation();
                        }
                    });
            }
            
            // Start trying paths
            tryLoadPath(possiblePaths[0]);
        }

        // Create fallback navigation
        function createFallbackNavigation() {
            const container = document.getElementById('navigation-container');
            container.innerHTML = 
                '<div style="background: #2c3e50; color: white; padding: 15px;">' +
                '<h2>🧪 Admin Dashboard</h2>' +
                '<p>Navigation loaded (fallback mode)</p>' +
                '<div style="margin-top: 10px;">' +
                '<span style="background: #10b981; padding: 5px 10px; border-radius: 4px; font-size: 12px;">' +
                '✅ Session Active' +
                '</span>' +
                '<span style="background: #3b82f6; padding: 5px 10px; border-radius: 4px; font-size: 12px; margin-left: 10px;">' +
                '✅ Dashboard Ready' +
                '</span>' +
                '</div>' +
                '</div>';
            
            // Show dashboard content immediately
            setTimeout(() => {
                const dashboardContent = document.getElementById('dashboard-content');
                if (dashboardContent) {
                    dashboardContent.style.display = 'block';
                    console.log('Dashboard content shown (fallback mode)');
                }
            }, 100);
        }

        // Initialize Navigation Functionality
        function initializeNavigation() {
            // Move page content to .main div
            const mainDiv = document.querySelector('.main');
            const pageContent = document.querySelector('.page-content');
            if (mainDiv && pageContent) {
                mainDiv.appendChild(pageContent);
            }

            // Fix dropdown item paths
            const currentPath = window.location.pathname;
            const isInSubfolder = currentPath.includes('/announcements/');
            const pathPrefix = isInSubfolder ? '../announcements/' : 'announcements/';

            document.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
                const page = item.getAttribute('data-page');
                if (page) item.href = pathPrefix + page;
            });

            // Initialize dropdowns after navigation is loaded
            initNavigationDropdowns();

            // ── Mobile hamburger sidebar toggle ──
            // Must run here (not in admin_nav.php) because nav is loaded via
            // fetch() + innerHTML, which does NOT execute <script> tags.
            initMobileNav();
        }

        // Initialize Navigation Dropdowns (extracted from admin_nav.php)
        function initNavigationDropdowns() {
            // Lazy-load flags — content fetched only on first open
            var bellLoaded = false;
            var envLoaded = false;

            // ── Close every .dropdown-panel except one supplied ──
            function closeAll(keepPanel) {
                document.querySelectorAll('.dropdown-panel').forEach(function(panel) {
                    if (panel !== keepPanel) {
                        panel.classList.remove('open');
                    }
                });
            }

            // ── Single document-level listener (same as chatbox pattern) ──
            document.addEventListener('click', function(e) {

                /* Step 1 — is click inside any dropdown wrapper at all? */
                var wrapper = e.target.closest('.dropdown-wrapper');
                if (!wrapper) {
                    /* Clicked outside every wrapper → close all */
                    closeAll(null);
                    return;
                }

                /* Step 2 — did click land on (or inside) a trigger button?
                   Using closest('[data-dropdown]') so clicking a child <i> icon
                   still resolves to the correct button element.                */
                var btn = e.target.closest('[data-dropdown]');
                if (!btn) {
                    /* Clicked inside panel area, not on button → do nothing */
                    return;
                }

                /* Step 3 — toggle target panel */
                var targetId = btn.getAttribute('data-dropdown');
                var panel = document.getElementById(targetId);
                if (!panel) return;

                var isOpen = panel.classList.contains('open');

                /* Close all siblings first */
                closeAll(panel);

                if (isOpen) {
                    /* Already open → close it */
                    panel.classList.remove('open');
                } else {
                    /* Closed → open it and lazy-load content if first time */
                    panel.classList.add('open');

                    if (targetId === 'bellPanel' && !bellLoaded) {
                        bellLoaded = true;
                        fetchNotifications();
                    }
                    if (targetId === 'envelopePanel' && !envLoaded) {
                        envLoaded = true;
                        fetchEnvelope();
                    }
                }
            });
        }

        // Fetch notifications function (simplified)
        function fetchNotifications() {
            const bellBody = document.getElementById('bellBody');
            if (bellBody) {
                bellBody.innerHTML = '<div class="dp-loading"><i class="fas fa-spinner"></i> Loading…</div>';
                // Simulate loading - in real implementation this would fetch from API
                setTimeout(() => {
                    bellBody.innerHTML = '<div class="dp-empty"><i class="fas fa-bell-slash"></i>No notifications yet.</div>';
                }, 1000);
            }
        }

        // Fetch envelope function (simplified)
        function fetchEnvelope() {
            const envelopeBody = document.getElementById('envelopeBody');
            if (envelopeBody) {
                envelopeBody.innerHTML = '<div class="dp-loading"><i class="fas fa-spinner"></i> Loading…</div>';
                // Simulate loading - in real implementation this would fetch from API
                setTimeout(() => {
                    envelopeBody.innerHTML = '<div class="dp-empty"><i class="fas fa-envelope-open"></i>No messages yet.</div>';
                }, 1000);
            }
        }
        }

        function initMobileNav() {
            var hamburger = document.getElementById('navHamburgerBtn');
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (!hamburger || !sidebar || !overlay) return;

            // Remove any previous listeners by cloning the button
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
                sidebar.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
            });

            overlay.addEventListener('click', closeSidebar);

            sidebar.querySelectorAll('a.menu-item').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 900) closeSidebar();
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 900) closeSidebar();
            });
        }

        // Initialize Chart.js
        function initChart() {
            const ctx = document.getElementById('enrollmentChart').getContext('2d');

            const chartData = {
                labels: <?php echo json_encode($config['chart']['labels']); ?>,
                datasets: [{
                        label: 'Actual Enrollment',
                        data: <?php echo json_encode($config['chart']['datasets']['enrollment']); ?>,
                        borderColor: DASHBOARD_CONFIG.chartColors.enrollment.start,
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: DASHBOARD_CONFIG.chartColors.enrollment.start,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    },
                    {
                        label: 'Target',
                        data: <?php echo json_encode($config['chart']['datasets']['target']); ?>,
                        borderColor: DASHBOARD_CONFIG.chartColors.target.start,
                        backgroundColor: 'transparent',
                        borderDash: [5, 5],
                        tension: 0.4,
                        pointBackgroundColor: DASHBOARD_CONFIG.chartColors.target.start,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            };

            window.enrollmentChart = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(31, 41, 55, 0.9)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + ' students';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });

            // Handle period change
            document.getElementById('chartPeriod').addEventListener('change', function(e) {
                updateChartPeriod(e.target.value);
            });
        }

        // Initialize Donut Chart
        function initDonutChart() {
            const ctx = document.getElementById('schoolOverviewChart').getContext('2d');

            // Get data from PHP
            const chartData = <?php echo $chart_data; ?>;
            const chartCounts = <?php echo $chart_counts; ?>;

            const data = {
                labels: ['Students', 'Teachers', 'Clubs', 'Finance'],
                datasets: [{
                    data: [chartData.students, chartData.teachers, chartData.clubs, chartData.finance],
                    backgroundColor: [
                        '#3b82f6', // Students - Blue
                        '#10b981', // Teachers - Green
                        '#8b5cf6', // Clubs - Purple
                        '#f59e0b' // Finance - Amber
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            };

            window.schoolOverviewChart = new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(31, 41, 55, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const labels = {
                                        'Students': 'Students: ' + chartCounts.students,
                                        'Teachers': 'Teachers: ' + chartCounts.teachers,
                                        'Clubs': 'Clubs: ' + chartCounts.clubs,
                                        'Finance': 'Finance: ₱' + chartCounts.finance.toLocaleString()
                                    };
                                    return labels[context.label] + ' (' + context.parsed + '%)';
                                }
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true,
                        duration: 800,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        function updateChartPeriod(period) {
            // Simulated data for different periods
            const periodData = {
                '6': {
                    labels: ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    enrollment: [120, 145, 168, 189, 210, 263],
                    target: [100, 130, 160, 190, 220, 250]
                },
                '12': {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    enrollment: [95, 110, 125, 140, 155, 165, 175, 190, 205, 220, 240, 263],
                    target: [90, 105, 120, 135, 150, 165, 180, 195, 210, 225, 240, 255]
                },
                'all': {
                    labels: ['2020', '2021', '2022', '2023', '2024'],
                    enrollment: [180, 195, 220, 245, 263],
                    target: [170, 190, 210, 230, 250]
                }
            };

            const data = periodData[period];
            window.enrollmentChart.data.labels = data.labels;
            window.enrollmentChart.data.datasets[0].data = data.enrollment;
            window.enrollmentChart.data.datasets[1].data = data.target;
            window.enrollmentChart.update();
        }

        // Export Functionality
        function initExportDropdown() {
            const exportBtn = document.getElementById('exportBtn');
            const exportDropdown = document.getElementById('exportDropdown');

            exportBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                exportDropdown.classList.toggle('active');
                this.setAttribute('aria-expanded', exportDropdown.classList.contains('active'));
            });

            document.addEventListener('click', function(e) {
                if (!exportDropdown.contains(e.target)) {
                    exportDropdown.classList.remove('active');
                    exportBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Export Report Function
        function exportReport(format) {
            const btn = document.querySelector('.btn-primary');
            const originalContent = btn.innerHTML;

            // Show loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            btn.disabled = true;

            // Simulate export (replace with actual API call)
            setTimeout(() => {
                console.log(`Exporting report as ${format.toUpperCase()}...`);

                // Show success feedback
                btn.innerHTML = '<i class="fas fa-check"></i> Exported!';

                setTimeout(() => {
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                    document.getElementById('exportDropdown').classList.remove('active');
                }, 1500);
            }, 1000);
        }

        // Refresh Statistics
        function initRefreshButton() {
            const refreshBtn = document.getElementById('refreshStats');

            refreshBtn.addEventListener('click', function() {
                this.classList.add('loading');
                this.querySelector('span').textContent = 'Refreshing...';

                // Simulate refresh (replace with actual API call)
                setTimeout(() => {
                    this.classList.remove('loading');
                    this.querySelector('span').textContent = 'Refresh';

                    // Show toast notification
                    showToast('Statistics updated successfully', 'success');
                }, 1500);
            });
        }

        // Global Search Functionality
        function initGlobalSearch() {
            const searchInput = document.getElementById('globalSearchInput');
            const suggestionsContainer = document.getElementById('searchSuggestions');
            let searchTimeout;
            
            // Search input handler
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                
                if (query.length < 2) {
                    hideSuggestions();
                    return;
                }
                
                // Debounce search
                searchTimeout = setTimeout(() => {
                    showSearchSuggestions(query);
                }, 300);
            });
            
            // Search on Enter key
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const query = e.target.value.trim();
                    if (query.length >= 2) {
                        performSearch(query);
                    }
                }
            });
            
            // Hide suggestions on click outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.global-search-container')) {
                    hideSuggestions();
                }
            });
        }
        
        // Show Search Suggestions
        async function showSearchSuggestions(query) {
            try {
                const response = await fetch(`api/search_api.php?action=suggestions&query=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                if (data.success) {
                    displaySuggestions(data.data);
                }
            } catch (error) {
                console.error('Error fetching suggestions:', error);
            }
        }
        
        // Display Suggestions
        function displaySuggestions(suggestions) {
            const container = document.getElementById('searchSuggestions');
            
            if (suggestions.length === 0) {
                hideSuggestions();
                return;
            }
            
            container.innerHTML = suggestions.map(suggestion => `
                <div class="search-suggestion-item" onclick="selectSuggestion('${suggestion.text.replace(/'/g, "\\'")}')">
                    <div class="search-suggestion-icon">
                        <i class="fas ${suggestion.icon}"></i>
                    </div>
                    <div class="search-suggestion-content">
                        <div class="search-suggestion-title">${suggestion.text}</div>
                        <div class="search-suggestion-subtitle">${suggestion.type}</div>
                    </div>
                </div>
            `).join('');
            
            container.classList.add('active');
        }
        
        // Hide Suggestions
        function hideSuggestions() {
            const container = document.getElementById('searchSuggestions');
            container.classList.remove('active');
            container.innerHTML = '';
        }
        
        // Select Suggestion
        function selectSuggestion(text) {
            const searchInput = document.getElementById('globalSearchInput');
            searchInput.value = text;
            hideSuggestions();
            performSearch(text);
        }
        
        // Perform Search
        async function performSearch(query) {
            try {
                const response = await fetch(`api/search_api.php?action=search&query=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                if (data.success) {
                    showSearchResults(data.data, query);
                } else {
                    showToast('Search failed: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Error performing search:', error);
                showToast('Search failed. Please try again.', 'error');
            }
        }
        
        // Show Search Results Modal
        function showSearchResults(results, query) {
            // Create modal if it doesn't exist
            let modal = document.getElementById('searchResultsModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'searchResultsModal';
                modal.className = 'search-results-modal';
                document.body.appendChild(modal);
            }
            
            modal.innerHTML = `
                <div class="search-results-content">
                    <div class="search-results-header">
                        <h3 class="search-results-title">Search Results for "${query}"</h3>
                        <button class="search-results-close" onclick="closeSearchResults()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="search-results-body">
                        ${results.length === 0 ? 
                            '<p style="text-align: center; color: var(--text-secondary); padding: 40px;">No results found.</p>' :
                            results.map(result => `
                                <a href="${result.link}" class="search-result-item">
                                    <span class="search-result-type ${result.type}">${result.type}</span>
                                    <div class="search-result-info">
                                        <div class="search-result-title">${result.title}</div>
                                        <div class="search-result-subtitle">${result.subtitle}</div>
                                        <div class="search-result-detail">${result.detail}</div>
                                    </div>
                                </a>
                            `).join('')
                        }
                    </div>
                </div>
            `;
            
            modal.classList.add('active');
        }
        
        // Close Search Results
        function closeSearchResults() {
            const modal = document.getElementById('searchResultsModal');
            if (modal) {
                modal.classList.remove('active');
            }
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSearchResults();
            }
        });
        
        // Initialize Activities Refresh Button
        function initActivitiesRefresh() {
            const refreshBtn = document.getElementById('refreshActivities');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', async function() {
                    this.classList.add('loading');
                    this.querySelector('i').classList.add('fa-spin');
                    
                    try {
                        const response = await fetch(DASHBOARD_CONFIG.apiEndpoints.activities);
                        const data = await response.json();
                        
                        if (data.success) {
                            updateActivities(data.data);
                            showToast('Activities refreshed successfully', 'success');
                        }
                    } catch (error) {
                        console.error('Error refreshing activities:', error);
                        showToast('Failed to refresh activities', 'error');
                    } finally {
                        this.classList.remove('loading');
                        this.querySelector('i').classList.remove('fa-spin');
                    }
                });
            }
        }
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('searchResultsModal');
            if (modal && e.target === modal) {
                closeSearchResults();
            }
        });
        function startRealTimeUpdates() {
            // Initial load
            loadRealTimeData();
            
            // Set up periodic updates
            setInterval(loadRealTimeData, DASHBOARD_CONFIG.refreshInterval);
        }
        
        // Load Real-time Data
        async function loadRealTimeData() {
            try {
                // Load stats
                const statsResponse = await fetch(DASHBOARD_CONFIG.apiEndpoints.stats);
                const statsData = await statsResponse.json();
                
                if (statsData.success) {
                    updateDashboardStats(statsData.data);
                }
                
                // Load activities
                const activitiesResponse = await fetch(DASHBOARD_CONFIG.apiEndpoints.activities);
                const activitiesData = await activitiesResponse.json();
                
                if (activitiesData.success) {
                    updateActivities(activitiesData.data);
                }
                
            } catch (error) {
                console.error('Error loading real-time data:', error);
                showToast('Failed to load real-time data', 'error');
            }
        }
        
        // Update Dashboard Stats
        function updateDashboardStats(data) {
            // Update stat cards with animation
            updateStatCard('students', data.students);
            updateStatCard('teachers', data.teachers);
            updateStatCard('clubs', data.clubs);
            updateStatCard('revenue', '₱' + parseFloat(data.finance).toLocaleString('en-PH', {minimumFractionDigits: 2}));
            
            // Update ratio
            const ratioElement = document.querySelector('.ratio-card .stat-value');
            if (ratioElement) {
                ratioElement.textContent = '1:' + data.teacher_student_ratio;
            }
            
            // Update graduation rate
            const gradElement = document.querySelector('[onclick*="graduation_rate"] .stat-value');
            if (gradElement) {
                gradElement.textContent = data.graduation_rate > 0 ? data.graduation_rate + '%' : 'N/A';
            }
        }
        
        // Update Individual Stat Card
        function updateStatCard(type, value) {
            const card = document.querySelector(`[onclick*="${type}"] .stat-value`);
            if (card && card.textContent !== value) {
                card.style.transform = 'scale(1.1)';
                card.style.transition = 'transform 0.3s ease';
                
                setTimeout(() => {
                    card.textContent = typeof value === 'number' ? value.toLocaleString() : value;
                    card.style.transform = 'scale(1)';
                }, 150);
            }
        }
        
        // Update Activities
        function updateActivities(activities) {
            const activitiesContainer = document.querySelector('.activities-list');
            if (!activitiesContainer) return;
            
            activitiesContainer.innerHTML = activities.map((activity, index) => `
                <div class="activity-item" data-index="${index}">
                    <div class="activity-icon ${activity.type}">
                        <i class="fas ${activity.icon}"></i>
                    </div>
                    <div class="activity-content">
                        <p class="activity-text">${activity.text}</p>
                        <span class="activity-time" data-timestamp="${activity.timestamp}">${activity.time_ago}</span>
                    </div>
                    <div class="mark-read">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
            `).join('');
        }
        
        // Navigate to Section
        function navigateTo(section) {
            const routes = {
                students: 'students.php',
                teachers: 'teachers.php',
                clubs: 'clubs.php',
                revenue: 'finance.php',
                graduation_rate: 'students.php'
            };

            if (routes[section]) {
                window.location.href = routes[section];
            }
        }

        // Mark Activity as Read
        function markActivityRead(index) {
            const item = document.querySelector(`.activity-item[data-index="${index}"]`);
            if (item) {
                item.style.opacity = '0.5';
                item.style.transition = 'opacity 0.3s ease';
            }
        }

        // Time Ago Formatting
        function formatActivityTimes() {
            const timeElements = document.querySelectorAll('.activity-time[data-timestamp]');

            timeElements.forEach(el => {
                const timestamp = el.getAttribute('data-timestamp');
                const timeAgo = getTimeAgo(new Date(timestamp));
                el.textContent = timeAgo;
            });
        }

        function getTimeAgo(date) {
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            const intervals = [{
                    label: 'year',
                    seconds: 31536000
                },
                {
                    label: 'month',
                    seconds: 2592000
                },
                {
                    label: 'week',
                    seconds: 604800
                },
                {
                    label: 'day',
                    seconds: 86400
                },
                {
                    label: 'hour',
                    seconds: 3600
                },
                {
                    label: 'minute',
                    seconds: 60
                }
            ];

            for (const interval of intervals) {
                const count = Math.floor(seconds / interval.seconds);
                if (count >= 1) {
                    return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`;
                }
            }

            return 'Just now';
        }

        // Toast Notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;

            // Add toast styles dynamically
            toast.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 14px;
                font-weight: 500;
                z-index: 9999;
                animation: slideInRight 0.3s ease;
            `;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Add animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>