<?php
/**
 * test_dashboard.php - Test dashboard without session check
 */

// Temporarily bypass session check for testing
// require_once '../session_config.php';
// $is_logged_in = (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['admin', 'sub-admin']))
//     || (isset($_SESSION['admin_id']));
// if (!$is_logged_in) {
//     header('Location: ../index.php');
//     exit();
// }

// Include database connection
include '../db_connection.php';
/** @var \mysqli $conn */ // $conn is set by db_connection.php

// Mock session data for testing
$_SESSION['user_id'] = 'test_admin';
$_SESSION['user_type'] = 'admin';
$_SESSION['username'] = 'Test Admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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

// Calculate ratio (students per teacher)
if ($teacher_count > 0) {
    $teacher_student_ratio = round($student_count / $teacher_count, 1);
}

// Dashboard Configuration
$config = [
    'dashboard' => [
        'breadcrumb' => ['Dashboard', 'Analytics'],
        'title' => 'School Dashboard',
        'subtitle' => 'Real-time overview of school metrics and activities'
    ],
    'stats' => [
        [
            'title' => 'Students',
            'value' => $student_count,
            'change' => $student_change,
            'icon' => 'fa-user-graduate',
            'color' => '#3b82f6',
            'link' => 'students.php'
        ],
        [
            'title' => 'Teachers',
            'value' => $teacher_count,
            'change' => $teacher_change,
            'icon' => 'fa-chalkboard-teacher',
            'color' => '#10b981',
            'link' => 'teachers.php'
        ],
        [
            'title' => 'Clubs',
            'value' => $club_count,
            'change' => $club_change,
            'icon' => 'fa-users',
            'color' => '#8b5cf6',
            'link' => 'clubs.php'
        ],
        [
            'title' => 'Revenue',
            'value' => $finance_total,
            'change' => $revenue_change,
            'icon' => 'fa-dollar-sign',
            'color' => '#f59e0b',
            'link' => 'finance.php'
        ]
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Dashboard - Admin Panel</title>
    <link rel="stylesheet" href="admin_assets/cs/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            background: #f0f2f7; 
            font-family: 'Inter', sans-serif; 
            padding: 20px;
        }
        .test-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        .test-header {
            background: #8a9a5b;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 20px;
        }
        .test-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #3b82f6;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
    </style>
</head>

<body>
    <div class="test-container">
        <div class="test-header">
            <h2>🧪 Dashboard Test Mode</h2>
            <p>This is a test version to isolate loading issues</p>
        </div>
        
        <div class="test-info">
            <h3>Session Status</h3>
            <p><strong>Session Active:</strong> <span class="success">✅ Yes (Test Mode)</span></p>
            <p><strong>User Type:</strong> <?php echo $_SESSION['user_type']; ?></p>
            <p><strong>CSRF Token:</strong> <?php echo substr($_SESSION['csrf_token'], 0, 8); ?>...</p>
        </div>
        
        <div class="test-info">
            <h3>Database Connection</h3>
            <p><strong>Status:</strong> <span class="success">✅ Connected</span></p>
            <p><strong>Teacher Count:</strong> <?php echo $teacher_count; ?></p>
            <p><strong>Student Count:</strong> <?php echo $student_count; ?></p>
        </div>
        
        <div class="test-info">
            <h3>Next Steps</h3>
            <p>If this test page loads correctly, the issue is with:</p>
            <ul>
                <li>Session validation in the main dashboard</li>
                <li>Navigation loading via AJAX</li>
                <li>JavaScript execution after navigation loads</li>
            </ul>
            <p><a href="admin_dashboard.php">← Back to Main Dashboard</a></p>
        </div>
    </div>
    
    <script>
        console.log('Test dashboard loaded successfully');
        console.log('Session data:', <?php echo json_encode($_SESSION); ?>);
        console.log('Database stats loaded');
    </script>
</body>
</html>
